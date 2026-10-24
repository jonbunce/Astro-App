<?php
defined( 'ABSPATH' ) || exit;

class WAS_Admin {

    public static function register_menus() {
        add_menu_page(
            'ASTRO',
            'ASTRO',
            'manage_options',
            'was-dashboard',
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-groups',
            30
        );

        add_submenu_page( 'was-dashboard', 'Dashboard',  'Dashboard',  'manage_options', 'was-dashboard', array( __CLASS__, 'page_dashboard' ) );
        add_submenu_page( 'was-dashboard', 'Players',    'Players',    'manage_options', 'was-players',   array( __CLASS__, 'page_players' ) );
        add_submenu_page( 'was-dashboard', 'Interest List', 'Interest List', 'manage_options', 'was-interest', array( __CLASS__, 'page_interest' ) );
        add_submenu_page( 'was-dashboard', 'Teams',      'Teams',      'manage_options', 'was-teams',     array( __CLASS__, 'page_teams' ) );
        add_submenu_page( 'was-dashboard', 'Payments',   'Payments',   'manage_options', 'was-payments',  array( __CLASS__, 'page_payments' ) );
        add_submenu_page( 'was-dashboard', 'Members & Roles', 'Members & Roles', 'manage_options', 'was-users',       array( __CLASS__, 'page_users' ) );
        add_submenu_page( 'was-dashboard', 'Schools', 'Schools', 'manage_options', 'was-schools', array( __CLASS__, 'page_schools' ) );
        add_submenu_page( 'was-dashboard', 'Email Templates', 'Emails', 'manage_options', 'was-email-templates', array( __CLASS__, 'page_email_templates' ) );
        add_submenu_page( null, 'Edit Player', 'Edit Player', 'manage_options', 'was-player-edit', array( __CLASS__, 'page_player_edit' ) );

        add_submenu_page( 'was-dashboard', 'Season Rollover', 'Season Rollover', 'manage_options', 'was-season-rollover', array( __CLASS__, 'page_season_rollover' ) );

        add_submenu_page( 'was-dashboard', 'Settings',   'Settings',   'manage_options', 'was-settings',  array( __CLASS__, 'page_settings' ) );
    }

    // ── Hard delete AJAX ───────────────────────────────────────────────────────

    public static function ajax_hard_delete() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $player_id = absint( $_POST['player_id'] ?? 0 );
        if ( ! $player_id ) wp_send_json_error( array( 'message' => 'Invalid player ID.' ) );

        global $wpdb;
        $player = WAS_Database::get_player( $player_id );
        if ( $player && $player->photo_path ) {
            $photo = was_photo_dir() . basename( $player->photo_path );
            if ( file_exists( $photo ) ) @unlink( $photo );
        }

        $wpdb->delete( "{$wpdb->prefix}was_players",            array( 'id'        => $player_id ) );
        $wpdb->delete( "{$wpdb->prefix}was_medical",            array( 'player_id' => $player_id ) );
        $wpdb->delete( "{$wpdb->prefix}was_consents",           array( 'player_id' => $player_id ) );
        $wpdb->delete( "{$wpdb->prefix}was_emergency_contacts", array( 'player_id' => $player_id ) );
        $wpdb->delete( "{$wpdb->prefix}was_payments",           array( 'player_id' => $player_id ) );

        wp_send_json_success( array( 'redirect' => admin_url( 'admin.php?page=was-players' ) ) );
    }

    // ── Set a per-player monthly supplement (e.g. JPL league supplement) ─────────
    // Stores an itemised extra on top of the standard rate and recomputes the
    // Direct Debit via apply_player_subscription (new sub first, then cancel old).
    public static function ajax_set_supplement() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
        $_POST = wp_unslash( $_POST );

        $player_id = absint( $_POST['player_id'] ?? 0 );
        $amount    = max( 0, (float) ( $_POST['amount'] ?? 0 ) );
        $label     = sanitize_text_field( $_POST['label'] ?? '' );

        $player = WAS_Database::get_player( $player_id );
        if ( ! $player ) wp_send_json_error( array( 'message' => 'Player not found.' ) );

        global $wpdb;
        $wpdb->update( "{$wpdb->prefix}was_players", array(
            'supplement_amount' => $amount,
            'supplement_label'  => $amount > 0 ? $label : '',
        ), array( 'id' => $player_id ) );

        $player = WAS_Database::get_player( $player_id );
        $new    = was_player_monthly_amount( $player );

        if ( $player->subs_override !== null && $player->subs_override !== '' ) {
            wp_send_json_success( array( 'message' => 'Supplement saved — but this child has a custom amount set, which overrides everything. The Direct Debit was NOT changed.' ) );
        }
        if ( $player->participation_type !== 'signed' || $player->registration_status !== 'active' || empty( $player->gc_mandate_id ) ) {
            wp_send_json_success( array( 'message' => 'Supplement saved. No active Direct Debit to update.' ) );
        }

        $ok = WAS_Database::apply_player_subscription( $player_id, self::notify_requested() );
        if ( ! $ok ) {
            wp_send_json_error( array( 'message' => 'Supplement saved, but the GoCardless subscription could NOT be updated — check the player\'s subscription in GoCardless.' ) );
        }
        wp_send_json_success( array( 'message' => 'Supplement applied. New monthly total: £' . number_format( $new, 2 ) . ' — the Direct Debit has been replaced at the new amount (starts on their next charge date, no double charge).' ) );
    }

    // ── Set a custom monthly subscription for a player ───────────────────────────
    // Stores a per-child override and, for an active Direct Debit, replaces the
    // GoCardless subscription (create new first, then cancel old) at the new amount.
    public static function ajax_set_custom_subs() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        global $wpdb;
        $player_id = absint( $_POST['player_id'] ?? 0 );
        $raw       = trim( (string) ( $_POST['amount'] ?? '' ) );
        $clear     = ( $raw === '' );
        $amount    = $clear ? null : round( (float) $raw, 2 );

        $player = WAS_Database::get_player( $player_id );
        if ( ! $player ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }
        if ( ! $clear && ( $amount < 0 || $amount > 1000 ) ) {
            wp_send_json_error( array( 'message' => 'Enter an amount between 0 and 1000, or leave blank to clear the override.' ) );
        }

        $is_billable = ( $player->participation_type === 'signed'
            && $player->registration_status === 'active'
            && ! empty( $player->gc_mandate_id ) );

        // 1) Clear the override — future Direct Debits revert to the standard rate.
        if ( $clear ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}was_players SET subs_override = NULL WHERE id = %d",
                $player_id
            ) );
            wp_send_json_success( array( 'message' => 'Custom amount removed. The standard team/sibling rate will apply to future Direct Debits. Any existing subscription was left unchanged.' ) );
        }

        // 2) Not yet billable (train-only, inactive, or no mandate) — store for later.
        if ( ! $is_billable ) {
            $wpdb->update( "{$wpdb->prefix}was_players", array( 'subs_override' => $amount ), array( 'id' => $player_id ), array( '%f' ), array( '%d' ) );
            wp_send_json_success( array( 'message' => 'Custom amount of £' . number_format( $amount, 2 ) . '/month saved. It will be used when a Direct Debit is next set up for this player.' ) );
        }

        // 3) Billable + £0 — cancel the Direct Debit and mark exempt.
        if ( $amount == 0 ) {
            $warn = '';
            if ( $player->gc_subscription_id ) {
                $cancelled = WAS_GoCardless::cancel_subscription( $player->gc_subscription_id );
                if ( ! $cancelled ) {
                    $warn = ' WARNING: the subscription (' . $player->gc_subscription_id . ') could not be cancelled automatically — please cancel it in GoCardless.';
                }
            }
            $wpdb->update(
                "{$wpdb->prefix}was_players",
                array( 'subs_override' => 0, 'gc_subscription_id' => '', 'payment_type' => 'exempt' ),
                array( 'id' => $player_id ),
                array( '%f', '%s', '%s' ),
                array( '%d' )
            );
            wp_send_json_success( array( 'message' => 'Player set to exempt (no monthly charge).' . ( $warn ?: ' Direct Debit cancelled.' ) ) );
        }

        // 4) Billable + amount > 0 — replace the subscription at the new amount.
        // The new one takes over the existing sub's next payment, on the 1st, so there is
        // no double charge and the child stays on the 1st-of-the-month schedule.
        $start_date = WAS_GoCardless::aligned_start_date( $player->gc_mandate_id, $player->gc_subscription_id, was_payment_day( $player ) );

        $name = get_bloginfo( 'name' ) . ' — ' . trim( $player->first_name . ' ' . $player->last_name ) . ' (custom)';
        $sub  = WAS_GoCardless::create_subscription( $player->gc_mandate_id, (int) round( $amount * 100 ), $name, $start_date, was_payment_day( $player ) );

        if ( ! $sub || empty( $sub['id'] ) ) {
            $err    = get_transient( 'was_gc_last_error' );
            $detail = ( is_array( $err ) && ! empty( $err['message'] ) ) ? ' (' . $err['message'] . ')' : '';
            wp_send_json_error( array( 'message' => 'Could not create the new GoCardless subscription' . $detail . '. Your existing Direct Debit is unchanged — nothing was altered.' ) );
        }

        // New subscription created — now cancel the old one and save.
        $cancel_warn = '';
        $old_sub     = null;
        if ( $player->gc_subscription_id ) {
            $old_sub   = WAS_GoCardless::get_subscription( $player->gc_subscription_id );
            $cancelled = WAS_GoCardless::cancel_subscription( $player->gc_subscription_id );
            if ( ! $cancelled ) {
                $cancel_warn = ' WARNING: the previous subscription (' . $player->gc_subscription_id . ') could not be cancelled automatically — please cancel it in GoCardless to avoid a double charge.';
            }
        }
        $wpdb->update(
            "{$wpdb->prefix}was_players",
            array( 'subs_override' => $amount, 'gc_subscription_id' => $sub['id'] ),
            array( 'id' => $player_id ),
            array( '%f', '%s' ),
            array( '%d' )
        );

        // GoCardless tells the parent the old subscription "has been cancelled" — explain.
        $told = '';
        if ( ! $cancel_warn && is_array( $old_sub ) && ( $old_sub['status'] ?? '' ) === 'active' && self::notify_requested() ) {
            $told = WAS_GoCardless::notify_dd_updated( $player, $old_sub, $sub ) ? ' The parent has been emailed to explain the change.' : '';
        }

        wp_send_json_success( array( 'message' => 'New Direct Debit of £' . number_format( $amount, 2 ) . '/month set up, first charging on ' . $start_date . '.' . ( $cancel_warn ?: ' The previous subscription has been cancelled.' ) . $told ) );
    }

    /**
     * Whether the admin wants the parent emailed about a Direct Debit change made on the
     * player page ("Email the parent…" tick-box). Defaults to yes when not sent.
     * Paused families are never emailed regardless (see parent_emails_paused()).
     */
    private static function notify_requested() {
        return ! isset( $_POST['notify'] ) || (string) $_POST['notify'] === '1';
    }

    /** Admin: pause / resume automatic payment emails to a family. */
    public static function ajax_set_payment_emails_paused() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
        $player = WAS_Database::get_player( absint( $_POST['player_id'] ?? 0 ) );
        if ( ! $player ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }
        $paused = ! empty( $_POST['paused'] ) && (string) $_POST['paused'] === '1';
        global $wpdb;
        $wpdb->update( "{$wpdb->prefix}was_players", array( 'payment_emails_paused' => $paused ? 1 : 0 ), array( 'id' => $player->id ), array( '%d' ), array( '%d' ) );
        error_log( "[WAS] Payment emails " . ( $paused ? 'PAUSED' : 'resumed' ) . " for player {$player->id} by user " . get_current_user_id() );
        wp_send_json_success( array( 'message' => $paused
            ? 'Automatic payment emails to this family are paused. The treasurer still gets copies, marked "Parent NOT emailed".'
            : 'Automatic payment emails to this family are back on.' ) );
    }

    // ── Manual payment-day override ──────────────────────────────────────────────
    // Moves a child's Direct Debit to another day of the month (e.g. a parent's
    // payday). Preview is read-only; Set carries the change out in GoCardless.
    // The parent is emailed to explain unless the admin un-ticks "Email the parent"
    // (GoCardless also notifies them of the new schedule either way).

    private static function payment_day_request() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
        $player = WAS_Database::get_player( absint( $_POST['player_id'] ?? 0 ) );
        if ( ! $player ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }
        return array( $player, absint( $_POST['day'] ?? 0 ) );
    }

    public static function ajax_preview_payment_day() {
        list( $player, $day ) = self::payment_day_request();
        $plan = WAS_GoCardless::plan_payment_day_change( $player, $day );
        if ( empty( $plan['ok'] ) ) {
            wp_send_json_error( array( 'message' => $plan['message'] ) );
        }
        wp_send_json_success( array( 'message' => $plan['message'], 'lines' => $plan['lines'], 'store_only' => $plan['store_only'] ) );
    }

    public static function ajax_set_payment_day() {
        list( $player, $day ) = self::payment_day_request();
        $res = WAS_GoCardless::change_payment_day( $player, $day, self::notify_requested() );
        if ( empty( $res['ok'] ) ) {
            wp_send_json_error( array( 'message' => $res['message'] ) );
        }
        wp_send_json_success( array( 'message' => $res['message'], 'lines' => $res['lines'] ?? array() ) );
    }

    // ── Multi-team: add / remove a child's additional team memberships ───────────
    // Primary team stays on was_players.team_id; extra teams live in was_player_teams.
    // Any change recomputes the child's single combined Direct Debit.

    public static function ajax_add_player_team() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Permission denied.' ) );

        global $wpdb;
        $player_id = absint( $_POST['player_id'] ?? 0 );
        $team_id   = absint( $_POST['team_id'] ?? 0 );
        // Choice: 'signed' (adds second-team rate), 'exempt' (signed but no charge), 'train_only' (£0).
        $choice = sanitize_key( $_POST['participation_type'] ?? 'signed' );
        $ptype  = ( $choice === 'train_only' ) ? 'train_only' : 'signed';

        $player = WAS_Database::get_player( $player_id );
        $team   = WAS_Database::get_team( $team_id );
        if ( ! $player || ! $team ) wp_send_json_error( array( 'message' => 'Player or team not found.' ) );
        if ( $team_id === (int) $player->team_id ) wp_send_json_error( array( 'message' => 'That team is already this child main team.' ) );

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}was_player_teams WHERE player_id = %d AND team_id = %d",
            $player_id, $team_id
        ) );
        if ( $exists ) wp_send_json_error( array( 'message' => 'This child is already in that team.' ) );

        $data = array( 'player_id' => $player_id, 'team_id' => $team_id, 'participation_type' => $ptype );
        if ( $choice === 'exempt' ) {
            $data['amount_override'] = 0.00; // signed, but adds nothing to the Direct Debit
        }
        $wpdb->insert( "{$wpdb->prefix}was_player_teams", $data );

        $extra_note = ( $choice === 'exempt' ) ? ' (added at no charge). ' : '. ';
        $msg = self::recompute_player_subscription( $player_id );
        wp_send_json_success( array( 'message' => 'Added to ' . esc_html( $team->name ) . $extra_note . $msg ) );
    }

    public static function ajax_remove_player_team() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Permission denied.' ) );

        global $wpdb;
        $player_id     = absint( $_POST['player_id'] ?? 0 );
        $membership_id = absint( $_POST['membership_id'] ?? 0 );
        if ( ! $player_id || ! $membership_id ) wp_send_json_error( array( 'message' => 'Invalid request.' ) );

        $wpdb->delete(
            "{$wpdb->prefix}was_player_teams",
            array( 'id' => $membership_id, 'player_id' => $player_id ),
            array( '%d', '%d' )
        );

        $msg = self::recompute_player_subscription( $player_id );
        wp_send_json_success( array( 'message' => 'Removed from extra team. ' . $msg ) );
    }

    // Recompute a child's combined monthly amount and apply it to their Direct Debit.
    private static function recompute_player_subscription( $player_id ) {
        global $wpdb;
        $player = WAS_Database::get_player( $player_id );
        if ( ! $player ) return '';

        // A manual custom override takes precedence — do not auto-change the DD.
        if ( $player->subs_override !== null && $player->subs_override !== '' ) {
            return 'Note: this child has a custom amount set (£' . number_format( (float) $player->subs_override, 2 ) . '), so their Direct Debit was not changed automatically.';
        }

        $amount   = was_player_monthly_amount( $player );
        $billable = ( $player->participation_type === 'signed' && $player->registration_status === 'active' && ! empty( $player->gc_mandate_id ) );

        if ( ! $billable ) {
            return 'Combined monthly total is now £' . number_format( $amount, 2 ) . ' — no active Direct Debit, so nothing was charged.';
        }

        $res = WAS_GoCardless::set_player_subscription( $player, (int) round( $amount * 100 ), self::notify_requested() );
        if ( empty( $res['ok'] ) ) {
            return 'WARNING: could not update the Direct Debit (' . $res['error'] . '). The team change was saved but billing is unchanged — please review in GoCardless.';
        }
        $wpdb->update( "{$wpdb->prefix}was_players", array( 'gc_subscription_id' => $res['sub_id'] ), array( 'id' => $player_id ), array( '%s' ), array( '%d' ) );

        $warn = empty( $res['cancel_ok'] ) ? ' (warning: the previous subscription could not be cancelled — check GoCardless).' : '';
        if ( $res['sub_id'] ) {
            return 'Direct Debit updated to £' . number_format( $amount, 2 ) . '/month from ' . $res['start_date'] . '.' . $warn . ( empty( $res['notified'] ) ? '' : ' The parent has been emailed to explain the change.' );
        }
        return 'Direct Debit cancelled (£0).' . $warn;
    }

    // ── Withdraw a team mid-season ───────────────────────────────────────────────

    public static function ajax_withdraw_team() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Permission denied.' ) );

        $team_id = absint( $_POST['team_id'] ?? 0 );
        $team    = WAS_Database::get_team( $team_id );
        if ( ! $team ) wp_send_json_error( array( 'message' => 'Team not found.' ) );

        $r = WAS_Database::withdraw_team( $team_id );
        if ( empty( $r['ok'] ) ) wp_send_json_error( array( 'message' => 'Could not withdraw the team.' ) );

        $msg = 'Team "' . $team->name . '" withdrawn and deactivated. '
             . (int) $r['deactivated'] . ' player(s) deactivated with Direct Debit cancelled, '
             . (int) $r['promoted'] . ' moved to a remaining team, '
             . (int) $r['extras_removed'] . ' extra-team link(s) removed.';
        if ( ! empty( $r['sub_errors'] ) ) {
            $msg .= ' WARNING: ' . (int) $r['sub_errors'] . ' Direct Debit update(s) failed — please check GoCardless.';
        }
        wp_send_json_success( array( 'message' => $msg ) );
    }

    // ── GC API test — tries different URL formats to find what works ──────────────

    public static function ajax_gc_test_payments() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        $mandate = sanitize_text_field( $_POST['mandate'] ?? '' );
        if ( ! $mandate ) wp_send_json_error( array( 'message' => 'No mandate' ) );

        $results = array();

        // Try each URL format
        $formats = array(
            '1_mandate_limit'     => '/payments?mandate=' . urlencode($mandate) . '&limit=200',
            '2_mandate_only'      => '/payments?mandate=' . urlencode($mandate),
            '3_mandate_limit_50'  => '/payments?mandate=' . urlencode($mandate) . '&limit=50',
            '4_no_filters'        => '/payments?limit=5',
            '5_subscription_list' => '/subscriptions?mandate=' . urlencode($mandate),
        );

        foreach ( $formats as $key => $url ) {
            // Use wp_remote_get directly to test
            if ( WAS_GoCardless::is_sandbox() ) {
                $base = 'https://api-sandbox.gocardless.com';
            } else {
                $base = 'https://api.gocardless.com';
            }
            $api_key = get_option('was_gc_sandbox_api_key', '');
            $response = wp_remote_get( $base . $url, array(
                'headers' => array(
                    'Authorization'      => 'Bearer ' . $api_key,
                    'GoCardless-Version' => '2015-07-06',
                    'Accept'             => 'application/json',
                ),
                'timeout' => 15,
            ) );
            if ( is_wp_error( $response ) ) {
                $results[$key] = 'WP_Error: ' . $response->get_error_message();
            } else {
                $code = wp_remote_retrieve_response_code( $response );
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                $results[$key] = array(
                    'http' => $code,
                    'url'  => $url,
                    'data' => $code === 200
                        ? array( 'count' => count($body[array_keys($body)[0]] ?? []) )
                        : ( $body['error']['message'] ?? 'unknown' ),
                );
            }
        }

        wp_send_json_success( $results );
    }

    // ── Manual GC sync trigger ─────────────────────────────────────────────────

    public static function ajax_run_sync() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        do_action( 'was_daily_gc_sync' );
        wp_send_json_success( array( 'message' => 'Sync completed at ' . current_time('H:i:s') ) );
    }

    // ── GC Debug ───────────────────────────────────────────────────────────────

    public static function ajax_gc_debug() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        $last       = get_transient( 'was_gc_last_error' );
        $next_cron  = wp_next_scheduled( 'was_daily_gc_sync' );
        $last_sync  = get_option( 'was_last_gc_sync', 'never' );
        wp_send_json_success( array(
            'last_error'    => $last ?: 'none stored',
            'sandbox_mode'  => WAS_GoCardless::is_sandbox() ? 'yes' : 'no',
            'api_key_set'   => get_option( WAS_GoCardless::is_sandbox() ? 'was_gc_sandbox_api_key' : 'was_gc_live_api_key' ) ? 'yes' : 'no',
            'wp_http_test'  => self::test_wp_http(),
            'last_sync'     => $last_sync,
            'next_cron'     => $next_cron ? date( 'Y-m-d H:i:s', $next_cron ) : 'not scheduled',
        ) );
    }

    private static function test_wp_http() {
        // Quick test to see if wp_remote_get works at all
        $r = wp_remote_get( 'https://api-sandbox.gocardless.com', array( 'timeout' => 10 ) );
        if ( is_wp_error( $r ) ) return 'WP_Error: ' . $r->get_error_message();
        return 'HTTP ' . wp_remote_retrieve_response_code( $r );
    }

    // ── Dashboard ──────────────────────────────────────────────────────────────

    public static function page_dashboard() {
        global $wpdb;
        $season = was_season();

        $stats = array(
            'active'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_players WHERE season=%s AND registration_status='active'", $season ) ),
            'signed'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_players WHERE season=%s AND participation_type='signed' AND registration_status='active'", $season ) ),
            'train_only'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_players WHERE season=%s AND participation_type='train_only' AND registration_status='active'", $season ) ),
            'pending'         => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_players WHERE season=%s AND registration_status='pending'", $season ) ),
            'payments_conf'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_payments WHERE season=%s AND status='confirmed'", $season ) ),
            'payments_failed' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_payments WHERE season=%s AND status='failed'", $season ) ),
        );

        $recent = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.first_name, p.participation_type, p.created_at, t.name AS team_name
             FROM {$wpdb->prefix}was_players p
             LEFT JOIN {$wpdb->prefix}was_teams t ON p.team_id = t.id
             WHERE p.season=%s AND p.registration_status='active'
             ORDER BY p.created_at DESC LIMIT 10",
            $season
        ) );

        include WAS_PATH . 'admin/views/dashboard.php';
    }

    // ── Players ────────────────────────────────────────────────────────────────

    public static function page_players() {
        global $wpdb;
        $season = was_season();

        $team_id = absint( $_GET['team'] ?? 0 );
        $status  = sanitize_key( $_GET['status'] ?? '' );
        $search  = sanitize_text_field( $_GET['s'] ?? '' );

        $where  = array( 'p.season = %s' );
        $params = array( $season );

        if ( $team_id ) {
            $where[]  = 'p.team_id = %d';
            $params[] = $team_id;
        }
        if ( $status ) {
            $where[]  = 'p.registration_status = %s';
            $params[] = $status;
        }
        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(p.first_name LIKE %s OR p.last_name LIKE %s OR g.email LIKE %s OR g.first_name LIKE %s OR g.last_name LIKE %s)';
            $params   = array_merge( $params, array( $like, $like, $like, $like, $like ) );
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        $players = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*,
                    t.name AS team_name,
                    g.first_name AS g_first, g.last_name AS g_last,
                    g.email AS g_email, g.telephone AS g_tel,
                    ( SELECT status FROM {$wpdb->prefix}was_payments
                      WHERE player_id = p.id ORDER BY charge_date DESC LIMIT 1 ) AS last_pay_status,
                    ( SELECT charge_date FROM {$wpdb->prefix}was_payments
                      WHERE player_id = p.id AND status = 'confirmed' ORDER BY charge_date DESC LIMIT 1 ) AS last_confirmed_date
             FROM {$wpdb->prefix}was_players p
             LEFT JOIN {$wpdb->prefix}was_teams t ON p.team_id = t.id
             LEFT JOIN {$wpdb->prefix}was_guardians g ON p.primary_guardian_id = g.id
             {$where_sql}
             ORDER BY t.name ASC, p.first_name ASC",
            ...$params
        ) );

        $teams = WAS_Database::get_teams( $season );

        include WAS_PATH . 'admin/views/players.php';
    }

    // ── Teams ──────────────────────────────────────────────────────────────────

    public static function page_teams() {
        // All logic handled in the view
        include WAS_PATH . 'admin/views/teams.php';
    }

    // ── Payments ───────────────────────────────────────────────────────────────

    public static function page_payments() {
        global $wpdb;
        $season = was_season();
        $notice = '';

        // Sync from GoCardless
        if ( isset( $_POST['sync_payments'] ) ) {
            check_admin_referer( 'was_sync_payments' );
            $players = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}was_players
                 WHERE season=%s AND participation_type='signed'
                   AND gc_mandate_id != '' AND registration_status='active'",
                $season
            ) );
            foreach ( $players as $p ) {
                WAS_GoCardless::sync_player_payments( $p );
            }
            $notice = 'Payment data synced from GoCardless (' . count( $players ) . ' players updated).';
        }

        $team_id = absint( $_GET['team']   ?? 0 );
        $status  = sanitize_key( $_GET['status'] ?? '' );

        // ── Players with no confirmed payment ─────────────────────────────────
        $no_pay = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, t.name AS team_name,
                    g.first_name AS g_first, g.last_name AS g_last, g.email AS g_email
             FROM {$wpdb->prefix}was_players p
             LEFT JOIN {$wpdb->prefix}was_teams t  ON p.team_id = t.id
             LEFT JOIN {$wpdb->prefix}was_guardians g ON p.primary_guardian_id = g.id
             WHERE p.season=%s AND p.participation_type='signed' AND p.registration_status='active'
               AND NOT EXISTS (
                   SELECT 1 FROM {$wpdb->prefix}was_payments
                   WHERE player_id = p.id AND status = 'confirmed'
               )
             ORDER BY t.name, p.first_name",
            $season
        ) );

        // ── Full payment log ──────────────────────────────────────────────────
        $where  = array( 'pay.season = %s' );
        $params = array( $season );

        if ( $team_id ) {
            $where[]  = 'p.team_id = %d';
            $params[] = $team_id;
        }
        if ( $status ) {
            $where[]  = 'pay.status = %s';
            $params[] = $status;
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        $payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT pay.*,
                    p.first_name AS p_first, p.last_name AS p_last, p.participation_type, p.payment_type,
                    t.name AS team_name,
                    g.first_name AS g_first, g.last_name AS g_last, g.email AS g_email
             FROM {$wpdb->prefix}was_payments pay
             LEFT JOIN {$wpdb->prefix}was_players p  ON pay.player_id  = p.id
             LEFT JOIN {$wpdb->prefix}was_teams t    ON p.team_id      = t.id
             LEFT JOIN {$wpdb->prefix}was_guardians g ON pay.guardian_id = g.id
             {$where_sql}
             ORDER BY pay.charge_date DESC, pay.created_at DESC",
            ...$params
        ) );

        $teams = WAS_Database::get_teams( $season );

        include WAS_PATH . 'admin/views/payments.php';
    }

    // ── Email Templates ─────────────────────────────────────────────────────────

    public static function page_email_templates() {
        include WAS_PATH . 'admin/views/email-templates.php';
    }

    // ── Schools ────────────────────────────────────────────────────────────────

    public static function page_schools() {
        include WAS_PATH . 'admin/views/schools.php';
    }

    public static function page_interest() {
        include WAS_PATH . 'admin/views/interest.php';
    }

    // ── Player edit ────────────────────────────────────────────────────────────

    public static function page_player_edit() {
        include WAS_PATH . 'admin/views/player-edit.php';
    }

    // ── Season rollover ──────────────────────────────────────────────────────────

    public static function page_season_rollover() {
        include WAS_PATH . 'admin/views/season-rollover.php';
    }

    // ── Members & Roles ────────────────────────────────────────────────────────

    public static function page_users() {
        include WAS_PATH . 'admin/views/users.php';
    }

    // ── Settings ───────────────────────────────────────────────────────────────

    public static function page_settings() {
        $notice = '';

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_settings_nonce'] ) ) {
            check_admin_referer( 'was_save_settings', 'was_settings_nonce' );

            update_option( 'was_gc_api_key',        sanitize_text_field( $_POST['gc_api_key'] ) );
            update_option( 'was_gc_webhook_secret', sanitize_text_field( $_POST['gc_webhook_secret'] ) );
            update_option( 'was_gc_sandbox',        (int) ( $_POST['gc_sandbox'] ?? 0 ) );
            update_option( 'was_current_season',    sanitize_text_field( $_POST['current_season'] ) );
            update_option( 'was_admin_email',       sanitize_email( $_POST['admin_email'] ) );

            $notice = 'Settings saved.';
        }

        include WAS_PATH . 'admin/views/settings.php';
    }
}
