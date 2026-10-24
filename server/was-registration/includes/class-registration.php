<?php
defined( 'ABSPATH' ) || exit;

class WAS_Registration {

    public static function init() {
        add_shortcode( 'was_registration', array( __CLASS__, 'render' ) );

        // AJAX handlers — logged in and anonymous (parents may not have WP accounts)
        $ajax_actions = array(
            'was_start_gc'            => 'ajax_start_gc',
            'was_submit_form'         => 'ajax_submit_form',
            'was_send_reminder'       => 'ajax_send_reminder',
            'was_check_sibling'       => 'ajax_check_sibling',
            'was_request_second_team' => 'ajax_request_second_team',
        );
        foreach ( $ajax_actions as $action => $method ) {
            add_action( "wp_ajax_{$action}",        array( __CLASS__, $method ) );
            add_action( "wp_ajax_nopriv_{$action}", array( __CLASS__, $method ) );
        }

        // Logged-in only: lapsed-DD re-subscribe from the parent portal
        add_action( 'wp_ajax_was_rejoin_start', array( __CLASS__, 'ajax_rejoin_start' ) );

        // Intercept the GoCardless return redirect before WordPress renders anything
        add_action( 'template_redirect', array( __CLASS__, 'handle_gc_return' ) );
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Shortcode rendering — stage router
    // ───────────────────────────────────────────────────────────────────────────

    public static function render( $atts ) {
        ob_start();

        // Prevent caching of registration pages — token in URL must always be fresh
        if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );

        // Registration requires a logged-in account
        if ( ! is_user_logged_in() ) {
            $return_url = add_query_arg( $_GET, get_permalink() );
            wp_redirect( add_query_arg( 'redirect_to', urlencode( $return_url ), home_url( '/account-login/' ) ) );
            exit;
        }

        // Guardian profile must be complete before registering a child
        $guardian = WAS_Accounts::current_guardian();
        $required_fields = array( 'first_name', 'telephone', 'address_line1', 'postcode' );
        $profile_incomplete = false;
        if ( ! $guardian ) {
            $profile_incomplete = true;
        } else {
            foreach ( $required_fields as $field ) {
                if ( empty( $guardian->$field ) ) {
                    $profile_incomplete = true;
                    break;
                }
            }
        }

        if ( $profile_incomplete ) {
            ob_start();
            include WAS_PATH . 'templates/registration-incomplete-profile.php';
            return ob_get_clean();
        }

        $stage = sanitize_key( $_GET['was_stage'] ?? 'start' );
        $token = sanitize_text_field( $_GET['was_token'] ?? '' );

        switch ( $stage ) {

            case 'form':
                $player = WAS_Database::get_player_by_token( $token );
                if ( $player && $player->registration_status === 'pending' && self::is_own_registration( $player ) ) {
                    self::render_form( $player );
                } else {
                    self::render_error( 'This registration link is invalid or has already been used. Please start again.' );
                    self::render_start();
                }
                break;

            case 'complete':
                self::render_complete();
                break;

            default:
                if ( isset( $_GET['was_error'] ) ) {
                    $msgs = array(
                        'session_expired' => 'Your GoCardless session expired. Please try again.',
                        'gc_incomplete'   => 'It looks like the Direct Debit setup was not completed. Please try again.',
                        'gc_failed'       => 'There was a problem connecting to GoCardless. Please try again or contact the club.',
                    );
                    $key = sanitize_key( $_GET['was_error'] );
                    self::render_error( $msgs[ $key ] ?? 'An error occurred. Please try again.' );
                }
                self::render_start();
        }

        return ob_get_clean();
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Stage templates
    // ───────────────────────────────────────────────────────────────────────────

    private static function render_start() {
        $teams  = WAS_Database::get_teams();
        $resume = self::get_resume_pending();
        include WAS_PATH . 'templates/registration-start.php';
    }

    /**
     * If the logged-in parent abandoned a registration part-way (e.g. set up the
     * Direct Debit but never filled in the child's details/photo), surface it so the
     * start screen can offer a one-click "Resume" — instead of silently starting over,
     * which would create a duplicate GoCardless mandate. Uses the was_pending_token
     * breadcrumb written in create_pending_player(); returns null (and clears a stale
     * breadcrumb) when there's nothing to resume.
     */
    private static function get_resume_pending() {
        if ( ! is_user_logged_in() ) return null;

        $uid   = get_current_user_id();
        $token = get_user_meta( $uid, 'was_pending_token', true );
        if ( ! $token ) return null;

        $player = WAS_Database::get_player_by_token( $token );

        // Breadcrumb is stale — already completed, cancelled, or from a past season.
        if ( ! $player
            || $player->registration_status !== 'pending'
            || $player->season !== was_season() ) {
            delete_user_meta( $uid, 'was_pending_token' );
            return null;
        }

        return array(
            'player' => $player,
            'team'   => WAS_Database::get_team( $player->team_id ),
            'url'    => self::stage_url( 'form', $token ),
        );
    }

    private static function render_form( $player ) {
        $team     = WAS_Database::get_team( $player->team_id );
        // Use guardian from player record if already linked, otherwise use logged-in user's guardian
        $guardian = null;
        if ( $player->primary_guardian_id ) {
            $guardian = WAS_Database::get_guardian( $player->primary_guardian_id );
        }
        if ( ! $guardian ) {
            $guardian = WAS_Accounts::current_guardian();
        }
        include WAS_PATH . 'templates/registration-form.php';
    }

    private static function render_complete() {
        include WAS_PATH . 'templates/registration-complete.php';
    }

    private static function render_error( $msg ) {
        echo '<div class="was-alert was-alert--error"><p>' . esc_html( $msg ) . '</p></div>';
    }

    // ───────────────────────────────────────────────────────────────────────────
    // AJAX: Start GoCardless redirect flow (or skip to form for train-only)
    // ───────────────────────────────────────────────────────────────────────────

    public static function ajax_start_gc() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        $_POST = wp_unslash( $_POST ); // strip WordPress' added slashes before sanitising

        $team_id = absint( $_POST['team_id'] ?? 0 );
        $type    = sanitize_key( $_POST['participation_type'] ?? 'signed' );
        $email   = sanitize_email( $_POST['guardian_email'] ?? '' );
        $name    = sanitize_text_field( $_POST['guardian_name'] ?? '' );

        if ( ! $team_id ) {
            wp_send_json_error( array( 'message' => 'Please select a team.' ) );
        }

        $team = WAS_Database::get_team( $team_id );
        if ( ! $team ) {
            wp_send_json_error( array( 'message' => 'Team not found. Please try again.' ) );
        }

        // Train-only: skip GC entirely
        if ( $type === 'train_only' ) {
            $token    = self::create_pending_player( $team_id, 'train_only', '', '', 0 );
            $base_url = esc_url_raw( $_POST['current_url'] ?? home_url( '/' ) );
            // Strip any existing was_ query args cleanly
            $base_url = remove_query_arg( array( 'was_stage', 'was_token', 'was_error', 'was_gc_return', 'redirect_flow_id' ), $base_url );
            wp_send_json_success( array(
                'redirect' => add_query_arg( array( 'was_stage' => 'form', 'was_token' => $token ), $base_url ),
            ) );
            return;
        }

        // Signed player: create a GoCardless redirect flow
        $session_token = wp_generate_password( 32, false );
        $return_page   = esc_url_raw( $_POST['current_url'] ?? home_url( '/' ) );
        $return_page   = remove_query_arg( array( 'was_stage', 'was_token', 'was_error', 'was_gc_return', 'redirect_flow_id' ), $return_page );

        // Store session data server-side (transient keyed to session_token). user_id records
        // who started it, so the pending registration created on return belongs to them.
        set_transient( 'was_gc_' . $session_token, array(
            'team_id' => $team_id,
            'email'   => $email,
            'name'    => $name,
            'user_id' => get_current_user_id(),
            'time'    => time(),
        ), HOUR_IN_SECONDS );

        // Tell the browser to store the session token so we can retrieve it on return
        setcookie( 'was_gc_sess', $session_token, time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

        $flow = WAS_GoCardless::create_redirect_flow( $team_id, $session_token, $email, $name, $return_page );

        if ( ! $flow ) {
            wp_send_json_error( array( 'message' => 'Could not connect to GoCardless. Please try again or contact the club.' ) );
        }

        wp_send_json_success( array( 'gc_url' => $flow['redirect_url'] ) );
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Handle return from GoCardless (template_redirect hook)
    // ───────────────────────────────────────────────────────────────────────────

    public static function handle_gc_return() {
        if ( empty( $_GET['was_gc_return'] ) || empty( $_GET['redirect_flow_id'] ) ) {
            return;
        }

        $redirect_flow_id = sanitize_text_field( $_GET['redirect_flow_id'] );
        $team_id          = absint( $_GET['team_id'] ?? 0 );
        $session_token    = sanitize_text_field( $_COOKIE['was_gc_sess'] ?? '' );

        if ( ! $session_token ) {
            wp_redirect( self::error_url( 'session_expired' ) );
            exit;
        }

        $session = get_transient( 'was_gc_' . $session_token );

        if ( ! $session || ! $team_id ) {
            wp_redirect( self::error_url( 'session_expired' ) );
            exit;
        }

        // Lapsed-DD re-subscribe: attach the new mandate to the EXISTING player
        // instead of creating a fresh pending registration.
        if ( ! empty( $session['intent'] ) && $session['intent'] === 'rejoin' ) {
            self::complete_rejoin( $redirect_flow_id, $session_token, $session );
            exit;
        }

        // Complete the redirect flow with GoCardless
        $flow = WAS_GoCardless::complete_redirect_flow( $redirect_flow_id, $session_token );

        if ( ! $flow ) {
            wp_redirect( self::error_url( 'gc_incomplete' ) );
            exit;
        }

        $mandate_id  = $flow['links']['mandate']  ?? '';
        $customer_id = $flow['links']['customer'] ?? '';

        // Create the subscription on GC immediately (player name added once form is complete)
        // Subscription is created at form submission time once we know the
        // guardian and can detect siblings and apply the correct rate.
        // For now just create the pending player record with the mandate.
        $token = self::create_pending_player( $team_id, 'signed', $mandate_id, $customer_id, 0, '', (int) ( $session['user_id'] ?? 0 ) );

        // Clean up session
        delete_transient( 'was_gc_' . $session_token );
        setcookie( 'was_gc_sess', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );

        wp_redirect( self::stage_url( 'form', $token ) );
        exit;
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Lapsed-DD re-subscribe (parent portal "Set up a new Direct Debit" button)
    // ───────────────────────────────────────────────────────────────────────────

    public static function ajax_rejoin_start() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Please log in first.' ) );
        }
        $_POST = wp_unslash( $_POST );

        $player_id = absint( $_POST['player_id'] ?? 0 );
        $guardian  = WAS_Accounts::current_guardian();
        $player    = $player_id ? WAS_Database::get_player( $player_id ) : null;

        if ( ! $guardian || ! $player || (int) $player->primary_guardian_id !== (int) $guardian->id ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }
        if ( $player->season !== was_season() || $player->participation_type !== 'signed' || $player->registration_status !== 'inactive' ) {
            wp_send_json_error( array( 'message' => 'This registration does not need a new Direct Debit.' ) );
        }

        $guardian_name = trim( $guardian->first_name . ' ' . $guardian->last_name );
        $session_token = wp_generate_password( 32, false );
        $return_page   = home_url( '/account-portal/' );

        set_transient( 'was_gc_' . $session_token, array(
            'intent'    => 'rejoin',
            'player_id' => (int) $player->id,
            'team_id'   => (int) $player->team_id,
            'email'     => $guardian->email,
            'name'      => $guardian_name,
            'time'      => time(),
        ), HOUR_IN_SECONDS );
        setcookie( 'was_gc_sess', $session_token, time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

        $flow = WAS_GoCardless::create_redirect_flow( $player->team_id, $session_token, $guardian->email, $guardian_name, $return_page );
        if ( ! $flow ) {
            wp_send_json_error( array( 'message' => 'Could not connect to GoCardless. Please try again or contact the club.' ) );
        }
        wp_send_json_success( array( 'gc_url' => $flow['redirect_url'] ) );
    }

    private static function complete_rejoin( $redirect_flow_id, $session_token, $session ) {
        $portal = home_url( '/account-portal/' );

        $flow = WAS_GoCardless::complete_redirect_flow( $redirect_flow_id, $session_token );
        delete_transient( 'was_gc_' . $session_token );
        setcookie( 'was_gc_sess', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );

        $mandate_id  = $flow['links']['mandate']  ?? '';
        $customer_id = $flow['links']['customer'] ?? '';
        $player      = WAS_Database::get_player( (int) ( $session['player_id'] ?? 0 ) );

        if ( ! $flow || ! $mandate_id || ! $player ) {
            wp_redirect( add_query_arg( 'was_rejoin_error', '1', $portal ) );
            exit;
        }

        global $wpdb;
        // Order matters: store the new mandate, clear the dead subscription id and
        // reactivate FIRST — apply_player_subscription() only acts on active players.
        $wpdb->update( "{$wpdb->prefix}was_players", array(
            'gc_mandate_id'       => $mandate_id,
            'gc_customer_id'      => $customer_id,
            'gc_subscription_id'  => '',
            'registration_status' => 'active',
        ), array( 'id' => $player->id ) );

        // Recreate the Direct Debit at the correct current rate (sibling/extra-team/override
        // aware). Exempt (£0-override) players correctly get no subscription. Charges resume
        // from the next cycle — any missed period is collected manually by the treasurer.
        WAS_Database::apply_player_subscription( $player->id );

        $player   = WAS_Database::get_player( $player->id );
        $guardian = WAS_Database::get_guardian( $player->primary_guardian_id );
        if ( $guardian ) {
            WAS_Notifications::dd_reactivated( $player, $guardian );
        }

        wp_redirect( add_query_arg( 'was_rejoined', rawurlencode( $player->first_name ), $portal ) );
        exit;
    }

    // ───────────────────────────────────────────────────────────────────────────
    // AJAX: Submit the registration form
    // ───────────────────────────────────────────────────────────────────────────

    // Parent requests adding an already-registered child to another team.
    public static function ajax_request_second_team() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in first.' ) );

        $guardian = WAS_Accounts::current_guardian();
        if ( ! $guardian ) wp_send_json_error( array( 'message' => 'Your account could not be found.' ) );

        $child   = sanitize_text_field( $_POST['child'] ?? '' );
        $team_id = absint( $_POST['team_id'] ?? 0 );
        $team    = WAS_Database::get_team( $team_id );
        if ( ! $child || ! $team ) wp_send_json_error( array( 'message' => 'Missing request details.' ) );

        WAS_Notifications::second_team_request( $guardian, $child, $team );
        wp_send_json_success( array( 'message' => 'Request sent. The club will add ' . esc_html( $child ) . ' to ' . esc_html( $team->name ) . ' and confirm with you. Please do not register them again.' ) );
    }

    public static function ajax_submit_form() {
        global $wpdb;

        // Verify the security token WITHOUT dying. A stale token (almost always the page
        // was served from cache) otherwise reaches the browser as a generic "network
        // error"; give a clear, actionable message instead — and log so we can confirm.
        if ( ! check_ajax_referer( 'was_nonce', 'nonce', false ) ) {
            error_log( '[WAS-SUBMIT] nonce FAILED user=' . get_current_user_id() );
            wp_send_json_error( array(
                'message' => 'Your session has expired, or this page was loaded from a cached copy. Please refresh the page and submit again — the details you entered are safe.',
            ) );
        }
        error_log( '[WAS-SUBMIT] reached user=' . get_current_user_id() . ' token=' . ( empty( $_POST['was_token'] ) ? 'none' : 'yes' ) );

        // WordPress adds slashes to $_POST; strip them so apostrophes (O'Brien, St John's)
        // and similar aren't stored with stray backslashes.
        $_POST = wp_unslash( $_POST );

        $token  = sanitize_text_field( $_POST['was_token'] ?? '' );
        $player = WAS_Database::get_player_by_token( $token );

        // The token must match a pending registration that this account started. There is
        // deliberately no "most recent pending registration" fallback: guessing could hand
        // one family another family's pending row and Direct Debit mandate.
        if ( $player && ( $player->registration_status !== 'pending' || ! self::is_own_registration( $player ) ) ) {
            $player = null;
        }

        if ( ! $player ) {
            wp_send_json_error( array( 'message' => 'Your registration session could not be found. This can happen if the page was cached. Please go back and start registration again — your details will not have been saved.' ) );
        }

        // ── Duplicate-child safeguard ─────────────────────────────────────────
        // A child only needs ONE registration. If the same child (same name, same
        // guardian, this season) is already registered, abort this attempt and offer
        // to REQUEST an extra team so the club adds it to the existing registration
        // (keeping a single, correct Direct Debit) rather than creating a duplicate.
        $reg_first    = sanitize_text_field( $_POST['first_name'] ?? '' );
        $reg_last     = sanitize_text_field( $_POST['last_name'] ?? '' );
        $reg_guardian = WAS_Accounts::current_guardian();
        if ( $reg_guardian && $reg_first && $reg_last ) {
            $already = $wpdb->get_row( $wpdb->prepare(
                "SELECT p.id, t.name AS team_name FROM {$wpdb->prefix}was_players p
                 LEFT JOIN {$wpdb->prefix}was_teams t ON p.team_id = t.id
                 WHERE p.primary_guardian_id = %d AND p.season = %s AND p.id <> %d
                   AND LOWER(p.first_name) = LOWER(%s) AND LOWER(p.last_name) = LOWER(%s)
                   AND p.registration_status IN ( 'active', 'inactive' )
                 ORDER BY p.id DESC LIMIT 1",
                $reg_guardian->id, was_season(), $player->id, $reg_first, $reg_last
            ) );
            if ( $already ) {
                $want_team   = WAS_Database::get_team( $player->team_id );
                $child_name  = trim( $reg_first . ' ' . $reg_last );
                // Remove this incomplete duplicate attempt (no form data saved yet, no subscription created).
                $wpdb->delete( "{$wpdb->prefix}was_players", array( 'id' => $player->id ), array( '%d' ) );
                $existing_team_label = $already->team_name ? esc_html( $already->team_name ) : 'a team';
                $btn = '';
                if ( $want_team ) {
                    $btn = '<button type="button" class="was-btn was-btn--primary was-request-team" data-child="'
                         . esc_attr( $child_name ) . '" data-team-id="' . (int) $player->team_id . '" style="margin-top:12px;">'
                         . 'Request to add ' . esc_html( $child_name ) . ' to ' . esc_html( $want_team->name ) . '</button>';
                }
                wp_send_json_error( array(
                    'message' => '<strong>' . esc_html( $child_name ) . ' is already registered</strong> with ' . $existing_team_label
                        . ' for this season, so there is no need to register them again. If they also play for '
                        . ( $want_team ? esc_html( $want_team->name ) : 'another team' )
                        . ', use the button below to request it &mdash; the club will add it to their existing registration and adjust the Direct Debit. ' . $btn,
                ) );
            }
        }

        // ── Validate ──────────────────────────────────────────────────────────
        $errors = self::validate_post( $_POST );
        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 'message' => implode( '<br>', $errors ) ) );
        }

        // ── Handle photo upload ───────────────────────────────────────────────
        $photo_filename = '';
        if ( ! empty( $_FILES['player_photo']['tmp_name'] ) ) {
            $file     = $_FILES['player_photo'];
            $allowed  = array( 'image/jpeg', 'image/png' );
            $max_size = 8 * 1024 * 1024; // 8MB

            if ( ! in_array( $file['type'], $allowed, true ) ) {
                wp_send_json_error( array( 'message' => 'Photo must be a JPG or PNG file.' ) );
            }
            if ( $file['size'] > $max_size ) {
                wp_send_json_error( array( 'message' => 'Photo must be under 8MB.' ) );
            }

            $ext            = $file['type'] === 'image/png' ? 'png' : 'jpg';
            $photo_filename = 'player-' . $player->id . '-' . time() . '.' . $ext;
            $dest           = was_photo_dir() . $photo_filename;

            if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
                wp_send_json_error( array( 'message' => 'Could not save photo. Please try again.' ) );
            }

            // Delete old photo if exists
            if ( $player->photo_path && $player->photo_path !== $photo_filename ) {
                $old_path = was_photo_dir() . basename( $player->photo_path );
                if ( file_exists( $old_path ) ) @unlink( $old_path );
            }
        }

        // ── Upsert guardian ───────────────────────────────────────────────────
        $full_name   = sanitize_text_field( $_POST['guardian_name'] );
        $name_parts  = explode( ' ', $full_name, 2 );
        $g_first     = $name_parts[0];
        $g_last      = $name_parts[1] ?? '';
        $g_email     = sanitize_email( $_POST['email'] );

        $existing_g  = WAS_Database::get_guardian_by_email( $g_email );

        if ( $existing_g ) {
            $wpdb->update(
                "{$wpdb->prefix}was_guardians",
                array(
                    'first_name'    => $g_first,
                    'last_name'     => $g_last,
                    'telephone'     => sanitize_text_field( $_POST['telephone'] ),
                    'address_line1' => sanitize_text_field( $_POST['address'] ),
                    'postcode'      => strtoupper( sanitize_text_field( $_POST['postcode'] ) ),
                    'updated_at'    => current_time( 'mysql' ),
                ),
                array( 'id' => $existing_g->id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            $guardian_id = $existing_g->id;
        } else {
            $wpdb->insert(
                "{$wpdb->prefix}was_guardians",
                array(
                    'first_name'    => $g_first,
                    'last_name'     => $g_last,
                    'email'         => $g_email,
                    'telephone'     => sanitize_text_field( $_POST['telephone'] ),
                    'address_line1' => sanitize_text_field( $_POST['address'] ),
                    'postcode'      => strtoupper( sanitize_text_field( $_POST['postcode'] ) ),
                ),
                array( '%s', '%s', '%s', '%s', '%s', '%s' )
            );
            $guardian_id = $wpdb->insert_id;
        }

        // ── Detect sibling discount via logged-in account ────────────────────
        // Use the logged-in guardian for reliable sibling detection
        $logged_in_guardian = WAS_Accounts::current_guardian();
        $lookup_guardian_id = $logged_in_guardian ? $logged_in_guardian->id : $guardian_id;
        $siblings     = WAS_Database::get_players_by_guardian( $lookup_guardian_id );
        $sibling_count = count( array_filter( $siblings, function( $s ) use ( $player ) {
            return $s->id !== $player->id && $s->registration_status !== 'withdrawn';
        } ) );

        $payment_type = $player->participation_type === 'signed' ? 'gocardless' : 'team_direct';
        if ( $sibling_count > 0 && $player->participation_type === 'signed' ) {
            $payment_type = 'sibling_rate';
        }

        // ── Create GoCardless subscription now we know the correct rate ───────
        $gc_subscription_id = $player->gc_subscription_id ?: '';
        $late_signup = null; // populated if the mandate can't be charged by the target first-payment date
        if ( $player->participation_type === 'signed' && $player->gc_mandate_id && ! $gc_subscription_id ) {
            $team = WAS_Database::get_team( $player->team_id );
            if ( $team ) {
                // Per-child custom override (set by admin) wins over sibling/full team rate
                if ( $player->subs_override !== null && $player->subs_override !== '' ) {
                    $monthly = (float) $player->subs_override;
                } elseif ( $payment_type === 'sibling_rate' && $team->sibling_amount > 0 ) {
                    $monthly = $team->sibling_amount;
                } else {
                    $monthly = $team->subs_amount;
                }

                $amount_pence  = (int) round( $monthly * 100 );
                $player_name   = sanitize_text_field( $_POST['first_name'] ?? '' ) . ' ' . sanitize_text_field( $_POST['last_name'] ?? '' );
                $sub_name      = get_bloginfo( 'name' ) . ' — ' . $team->name . ' (' . trim( $player_name ) . ')';

                if ( $amount_pence > 0 ) {
                    // Deferred first payment + late-sign-up handling. If a future first-payment
                    // date is set but the mandate can't be charged in time for it (Bacs lead
                    // time), the monthly DD is rolled to the next valid date AND a one-off for
                    // the (full) missed month is auto-collected on the earliest possible date.
                    $reg = WAS_GoCardless::create_registration_subscription( $player->gc_mandate_id, $amount_pence, $sub_name, was_first_payment_date() );
                    if ( ! empty( $reg['subscription_id'] ) ) {
                        $gc_subscription_id = $reg['subscription_id'];
                    }
                    if ( ! empty( $reg['late'] ) ) {
                        $late_signup = array(
                            'sub_start'      => $reg['sub_start'],
                            'one_off_date'   => $reg['one_off_date'],      // '' if the one-off failed / was waived
                            'one_off_ok'     => empty( $reg['one_off_failed'] ) && ! empty( $reg['one_off_date'] ),
                            'amount'         => $monthly,
                            // Mid-season sign-up: the one-off is a pro-rata part-month amount.
                            'prorata'        => ! empty( $reg['prorata'] ),
                            'prorata_from'   => $reg['prorata_from'] ?? '',
                            'one_off_amount' => (float) ( $reg['one_off_amount'] ?? $monthly ),
                            'one_off_waived' => ! empty( $reg['one_off_waived'] ),
                        );
                    }
                }
            }
        }

        // ── Update player record ──────────────────────────────────────────────
        $school_id    = absint( $_POST['school_id']    ?? 0 );
        $school_other = sanitize_text_field( $_POST['school_other'] ?? '' );

        $dob_in = sanitize_text_field( $_POST['dob'] ?? '' );
        $dob    = ( $dob_in && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dob_in ) && strtotime( $dob_in ) ) ? $dob_in : null;

        $player_update = array(
            'first_name'          => sanitize_text_field( $_POST['first_name'] ?? $_POST['player_name'] ?? '' ),
            'last_name'           => sanitize_text_field( $_POST['last_name']  ?? '' ),
            'dob'                 => $dob,
            'primary_guardian_id' => $guardian_id,
            'payment_type'        => $payment_type,
            'registration_status' => 'active',
            'registration_token'  => '',
            'school_id'           => $school_id,
            'school_other'        => $school_other,
        );
        if ( $photo_filename ) {
            $player_update['photo_path'] = $photo_filename;
        }
        if ( $gc_subscription_id ) {
            $player_update['gc_subscription_id'] = $gc_subscription_id;
        }
        $wpdb->update(
            "{$wpdb->prefix}was_players",
            $player_update,
            array( 'id' => $player->id )
        );

        // ── Emergency contact ─────────────────────────────────────────────────
        $ec_name = sanitize_text_field( $_POST['emergency_name'] ?? '' );
        if ( $ec_name ) {
            $ec_existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}was_emergency_contacts WHERE player_id = %d",
                $player->id
            ) );
            $ec = array(
                'contact_name'       => $ec_name,
                'contact_telephone'  => sanitize_text_field( $_POST['emergency_telephone'] ?? '' ),
                'contact_occupation' => sanitize_text_field( $_POST['emergency_occupation'] ?? '' ),
                'contact_employer'   => sanitize_text_field( $_POST['emergency_employer'] ?? '' ),
            );
            if ( $ec_existing ) {
                $wpdb->update( "{$wpdb->prefix}was_emergency_contacts", $ec, array( 'player_id' => $player->id ) );
            } else {
                $wpdb->insert( "{$wpdb->prefix}was_emergency_contacts", array_merge( array( 'player_id' => $player->id ), $ec ) );
            }
        }

        // ── Medical data ──────────────────────────────────────────────────────
        $med_consent = sanitize_text_field( $_POST['consent_medical'] ?? '' ) === 'agree' ? 1 : 0;
        $med = array(
            'consent_given'          => $med_consent,
            'consent_date'           => $med_consent ? current_time( 'mysql' ) : null,
            'health_needs'           => sanitize_textarea_field( $_POST['health_needs']       ?? '' ),
            'current_medication'     => sanitize_textarea_field( $_POST['medication']         ?? '' ),
            'medical_training_needs' => sanitize_textarea_field( $_POST['medical_training']   ?? '' ),
            'access_needs'           => sanitize_textarea_field( $_POST['access_needs']       ?? '' ),
            'communication_needs'    => sanitize_textarea_field( $_POST['communication_needs']?? '' ),
        );

        $has_med = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}was_medical WHERE player_id = %d",
            $player->id
        ) );
        if ( $has_med ) {
            $wpdb->update( "{$wpdb->prefix}was_medical", $med, array( 'player_id' => $player->id ) );
        } else {
            $wpdb->insert( "{$wpdb->prefix}was_medical", array_merge( array( 'player_id' => $player->id ), $med ) );
        }

        // ── Consents ──────────────────────────────────────────────────────────
        $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
        $consents = array(
            'photography'       => sanitize_text_field( $_POST['consent_photography'] ?? 'disagree' ),
            'legal_proceedings' => sanitize_text_field( $_POST['consent_legal']       ?? 'disagree' ),
            'medical_data'      => $med_consent ? 'agree' : 'disagree',
            'privacy_policy'    => 'agree',
        );
        foreach ( $consents as $type => $value ) {
            // Always replace — allows updating consent if re-registration
            $wpdb->delete( "{$wpdb->prefix}was_consents", array( 'player_id' => $player->id, 'consent_type' => $type ) );
            $wpdb->insert( "{$wpdb->prefix}was_consents", array(
                'player_id'     => $player->id,
                'consent_type'  => $type,
                'consent_value' => $value,
                'ip_address'    => $ip,
            ) );
        }

        // ── Notify ────────────────────────────────────────────────────────────
        $fresh_player = WAS_Database::get_player( $player->id );
        $guardian_obj = WAS_Database::get_guardian( $guardian_id );
        if ( $fresh_player && $guardian_obj ) {
            WAS_Notifications::registration_confirmed( $fresh_player, $guardian_obj );
        }

        // Late / mid-season sign-up: monthly DD starts on a later 1st + a one-off (missed
        // month, or pro-rata part month) is taken. Tell the parent on-screen (short-lived transient read by the complete page) and
        // by email, and alert the treasurer/admin.
        if ( $late_signup && $fresh_player && $guardian_obj ) {
            set_transient( 'was_late_signup_' . get_current_user_id(), $late_signup, 15 * MINUTE_IN_SECONDS );
            WAS_Notifications::late_signup_alert( $fresh_player, $guardian_obj, $late_signup );
        }

        error_log( '[WAS-SUBMIT] OK player=' . $player->id . ' type=' . $player->participation_type );

        // Build completion URL from the page URL passed by the browser
        $base_url = esc_url_raw( $_POST['current_url'] ?? home_url( '/' ) );
        $base_url = remove_query_arg( array( 'was_stage', 'was_token', 'was_error', 'was_gc_return', 'redirect_flow_id' ), $base_url );
        wp_send_json_success( array(
            'redirect' => add_query_arg( 'was_stage', 'complete', $base_url ),
        ) );
    }

    // ───────────────────────────────────────────────────────────────────────────
    // AJAX: Send overdue reminder (treasurer action)
    // ───────────────────────────────────────────────────────────────────────────

    public static function ajax_send_reminder() {
        check_ajax_referer( 'was_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $player_id  = absint( $_POST['player_id'] ?? 0 );
        $player     = WAS_Database::get_player( $player_id );
        if ( ! $player ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }

        $guardian = WAS_Database::get_guardian( $player->primary_guardian_id );
        if ( ! $guardian ) {
            wp_send_json_error( array( 'message' => 'Guardian not found for this player.' ) );
        }

        if ( WAS_Notifications::parent_emails_paused( $player ) ) {
            wp_send_json_error( array( 'message' => 'Payment emails are paused for this family (set on the admin player page), so no reminder was sent.' ) );
        }
        WAS_Notifications::payment_overdue_reminder( $player, $guardian );
        wp_send_json_success( array( 'message' => "Reminder sent to {$guardian->email}." ) );
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────────────────────────────────

    /**
     * Insert a minimal "pending" player row to anchor the GC mandate / token.
     * Completed on form submit.
     */
    private static function create_pending_player( $team_id, $type, $mandate_id, $customer_id, $amount_pence = 0, $sub_id = '', $owner_id = 0 ) {
        global $wpdb;

        $token = wp_generate_password( 32, false );

        // The account that started this registration — the only one allowed to complete it.
        $owner_id = $owner_id ? (int) $owner_id : get_current_user_id();

        $wpdb->insert(
            "{$wpdb->prefix}was_players",
            array(
                'first_name'          => '',
                'team_id'             => $team_id,
                'primary_guardian_id' => 0,
                'participation_type'  => $type,
                'payment_type'        => $type === 'signed' ? 'gocardless' : 'team_direct',
                'season'              => was_season(),
                'registration_status' => 'pending',
                'gc_mandate_id'       => $mandate_id,
                'gc_subscription_id'  => $sub_id,
                'gc_customer_id'      => $customer_id,
                'registration_token'  => $token,
                'created_by_user'     => $owner_id,
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
        );

        // Store token in user meta as backup in case of page cache issues
        if ( $owner_id ) {
            update_user_meta( $owner_id, 'was_pending_token', $token );
        }

        return $token;
    }

    /**
     * Does this pending registration belong to the signed-in account? Yes if the account
     * started it, or it is already linked to the account's own family record. Rows from
     * before created_by_user existed fall back to the account's own resume breadcrumb.
     */
    private static function is_own_registration( $player ) {
        $user_id = get_current_user_id();
        if ( ! $player || ! $user_id ) return false;

        if ( ! empty( $player->created_by_user ) ) {
            if ( (int) $player->created_by_user === $user_id ) return true;
        } elseif ( $player->registration_token !== '' ) {
            $breadcrumb = (string) get_user_meta( $user_id, 'was_pending_token', true );
            if ( $breadcrumb !== '' && hash_equals( $breadcrumb, (string) $player->registration_token ) ) return true;
        }

        if ( ! empty( $player->primary_guardian_id ) ) {
            $guardian = WAS_Accounts::current_guardian();
            if ( $guardian && (int) $guardian->id === (int) $player->primary_guardian_id ) return true;
        }
        return false;
    }

    private static function stage_url( $stage, $token = '' ) {
        $args = array( 'was_stage' => $stage );
        if ( $token ) {
            $args['was_token'] = $token;
        }
        // Attempt to use get_permalink() — works when called from template_redirect
        $base = get_permalink() ?: home_url( '/' );
        return add_query_arg( $args, remove_query_arg( array( 'was_gc_return', 'redirect_flow_id', 'team_id' ), $base ) );
    }

    private static function error_url( $code ) {
        $base = get_permalink() ?: home_url( '/' );
        return add_query_arg( 'was_error', $code, remove_query_arg( array( 'was_gc_return', 'redirect_flow_id', 'team_id' ), $base ) );
    }

    // ── AJAX: Check if email matches existing guardian with active siblings ────

    public static function ajax_check_sibling() {
        check_ajax_referer( 'was_nonce', 'nonce' );

        $team_id = absint( $_POST['team_id'] ?? 0 );
        $email   = sanitize_email( $_POST['email'] ?? '' );

        // Prefer logged-in guardian over email lookup
        $guardian = null;
        if ( is_user_logged_in() ) {
            $guardian = WAS_Accounts::current_guardian();
        }
        // Fall back to email if provided and no logged-in guardian found
        if ( ! $guardian && $email && is_email( $email ) ) {
            $guardian = WAS_Database::get_guardian_by_email( $email );
        }
        if ( ! $guardian ) {
            wp_send_json_success( array( 'match' => false ) );
        }

        $siblings = WAS_Database::get_players_by_guardian( $guardian->id, was_season() );
        $active_signed = array_filter( $siblings, function( $p ) {
            return $p->participation_type === 'signed'
                && $p->registration_status === 'active';
        } );

        if ( empty( $active_signed ) ) {
            wp_send_json_success( array( 'match' => false ) );
        }

        $team         = WAS_Database::get_team( $team_id );
        $sibling_rate = $team ? (float) $team->sibling_amount : 0;
        $full_rate    = $team ? (float) $team->subs_amount    : 0;

        $names = array_map( function( $p ) {
            return trim( $p->first_name . ' ' . $p->last_name );
        }, $active_signed );

        wp_send_json_success( array(
            'match'        => true,
            'count'        => count( $active_signed ),
            'names'        => array_values( $names ),
            'sibling_rate' => $sibling_rate,
            'full_rate'    => $full_rate,
            'guardian'     => $guardian->first_name,
        ) );
    }

    private static function validate_post( $data ) {
        $errors = array();

        if ( empty( $data['first_name'] ) ) {
            $errors[] = 'Player first name is required.';
        }
        if ( empty( $data['last_name'] ) ) {
            $errors[] = 'Player last name is required.';
        }
        if ( empty( $data['dob'] ) ) {
            $errors[] = "Your child's date of birth is required.";
        } elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['dob'] ) || ! strtotime( $data['dob'] ) || strtotime( $data['dob'] ) > time() ) {
            $errors[] = 'Please enter a valid date of birth.';
        }
        if ( empty( $data['guardian_name'] ) ) {
            $errors[] = 'Parent / Guardian name is required.';
        }
        if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
            $errors[] = 'A valid email address is required.';
        }
        if ( empty( $data['telephone'] ) ) {
            $errors[] = 'Telephone number is required.';
        }
        if ( empty( $data['address'] ) ) {
            $errors[] = 'Home address is required.';
        }
        if ( empty( $data['postcode'] ) ) {
            $errors[] = 'Postcode is required.';
        }
        if ( empty( $_FILES['player_photo']['tmp_name'] ) ) {
            $errors[] = 'A player photo is required.';
        }
        if ( empty( $data['school_id'] ) && empty( $data['school_other'] ) ) {
            $errors[] = "Please select your child's school.";
        }
        if ( empty( $data['emergency_name'] ) ) {
            $errors[] = 'Alternative emergency contact name is required.';
        }
        if ( empty( $data['emergency_telephone'] ) ) {
            $errors[] = 'Alternative emergency contact telephone is required.';
        }
        if ( empty( $data['consent_privacy'] ) || $data['consent_privacy'] !== 'agree' ) {
            $errors[] = 'You must agree to the Privacy Policy to register.';
        }
        if ( ! in_array( $data['consent_photography'] ?? '', array( 'agree', 'disagree' ), true ) ) {
            $errors[] = 'Please indicate your photography consent preference.';
        }
        if ( ! in_array( $data['consent_legal'] ?? '', array( 'agree', 'disagree' ), true ) ) {
            $errors[] = 'Please answer the question about family, care or legal proceedings.';
        }
        if ( ( $data['consent_medical'] ?? '' ) !== 'agree' ) {
            $errors[] = 'You must consent to the club holding medical information to register.';
        }

        return $errors;
    }
}
