<?php
defined( 'ABSPATH' ) || exit;

class WAS_Portal {

    public static function init() {
        add_shortcode( 'was_portal', array( __CLASS__, 'render' ) );
        add_action( 'wp_ajax_was_upload_team_logo',  array( __CLASS__, 'ajax_upload_team_logo' ) );
        add_action( 'wp_ajax_was_team_profile_save', array( __CLASS__, 'ajax_team_profile_save' ) );
        add_action( 'wp_ajax_was_update_child',       array( __CLASS__, 'ajax_update_child' ) );
        add_action( 'wp_ajax_was_update_child_photo', array( __CLASS__, 'ajax_update_child_photo' ) );
        add_action( 'wp_ajax_was_update_consent',     array( __CLASS__, 'ajax_update_consent' ) );

        // AJAX — role-gated actions from the portal
        add_action( 'wp_ajax_was_portal_send_reminder', array( __CLASS__, 'ajax_send_reminder' ) );
        add_action( 'wp_ajax_was_gc_sync',              array( __CLASS__, 'ajax_gc_sync' ) );

        // AJAX — club messages (committee/admin)
        add_action( 'wp_ajax_was_msg_prepare', array( __CLASS__, 'ajax_msg_prepare' ) );
        add_action( 'wp_ajax_was_msg_batch',   array( __CLASS__, 'ajax_msg_batch' ) );
        add_action( 'wp_ajax_was_msg_test',    array( __CLASS__, 'ajax_msg_test' ) );
        add_action( 'wp_ajax_was_msg_schedule', array( __CLASS__, 'ajax_msg_schedule' ) );
        add_action( 'wp_ajax_was_msg_cancel',   array( __CLASS__, 'ajax_msg_cancel' ) );

        // Scheduled club messages: a cron event starts each one on time, a 5-minute watchdog
        // catches anything missed, and a loopback request carries on a long send.
        add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
        add_action( 'was_msg_run_scheduled', array( __CLASS__, 'run_scheduled_message' ) );
        add_action( 'was_msg_watchdog',      array( __CLASS__, 'msg_watchdog' ) );
        add_action( 'wp_ajax_nopriv_was_msg_continue', array( __CLASS__, 'ajax_msg_continue' ) );
        add_action( 'wp_ajax_was_msg_continue',        array( __CLASS__, 'ajax_msg_continue' ) );

        // Redirect guests away from portal
        add_action( 'template_redirect', array( __CLASS__, 'redirect_guests' ) );
    }

    public static function redirect_guests() {
        if ( is_user_logged_in() ) return;
        global $post;
        if ( ! $post ) return;
        if ( has_shortcode( $post->post_content, 'was_portal' ) ) {
            wp_redirect( add_query_arg( 'redirect_to', get_permalink(), home_url( '/account-login/' ) ) );
            exit;
        }
    }

    // ── Main shortcode ─────────────────────────────────────────────────────────

    public static function render() {
        if ( ! is_user_logged_in() ) {
            return '<p>Please <a href="' . esc_url( home_url( '/account-login/' ) ) . '">sign in</a> to view your account.</p>';
        }

        ob_start();
        $user    = wp_get_current_user();
        $user_id = $user->ID;
        $roles   = was_get_roles( $user_id );
        $is_admin = current_user_can( 'manage_options' );

        // Determine which sections to show.
        // 'committee' = full-access club role: every team's player data, payments,
        // photo/media consents, news review, and the Messages tab.
        $is_committee      = in_array( 'committee', $roles, true );
        $show_parent       = $is_admin || in_array( 'parent',       $roles, true );
        $show_coach        = $is_admin || $is_committee || in_array( 'coach', $roles, true );
        $show_treasurer    = $is_admin || $is_committee || in_array( 'treasurer',    $roles, true );
        $show_social_media = $is_admin || $is_committee || in_array( 'social_media', $roles, true );
        $show_news_review   = $is_admin || $is_committee || in_array( 'social_media', $roles, true ) || in_array( 'treasurer', $roles, true );
        $show_team_news_tab = $show_news_review || $show_coach;
        $show_messages      = $is_admin || $is_committee;
        $show_registrations = $is_admin || $is_committee || in_array( 'registration_officer', $roles, true );
        $show_interest      = WAS_Interest::can_manage(); // committee/admin — the waiting list
        $coach_all_teams    = $is_admin || $is_committee; // committee/admin see EVERY team in the coach-style view
        $no_roles           = ! $is_admin && empty( $roles );

        include WAS_PATH . 'templates/portal-header.php';

        // Email confirmation (see WAS_Accounts): explain why an existing family record isn't
        // showing yet, and send the confirmation link if one isn't already waiting.
        $verify_pending = WAS_Accounts::verification_pending( $user_id );
        self::render_verification_notice( $user, $verify_pending );

        if ( $no_roles && $verify_pending ) {
            // Saving the onboarding profile now would create a duplicate family record.
            include WAS_PATH . 'templates/portal-footer.php';
            return ob_get_clean();
        }

        if ( $no_roles ) {
            // Check if guardian profile is complete
            $onboard_guardian = WAS_Accounts::current_guardian();
            $profile_complete = $onboard_guardian
                && ! empty( $onboard_guardian->first_name )
                && ! empty( $onboard_guardian->telephone )
                && ! empty( $onboard_guardian->address_line1 )
                && ! empty( $onboard_guardian->postcode );

            if ( $profile_complete ) {
                // Profile done but role not yet set — edge case, show waiting message
                ?>
                <div class="was-gate was-gate--center">
                    <div class="was-gate__icon">&#9203;</div>
                    <h3 class="was-gate__title">Almost there!</h3>
                    <p class="was-gate__text">Your profile is complete. A club administrator will activate your account shortly. If you believe this is an error, please contact the club directly.</p>
                </div>
                <?php
            } else {
                // Show onboarding prompt with profile form
                $occ_cats = WAS_Accounts::occupation_categories();
                ?>
                <div class="was-gate">
                    <div class="was-gate--center" style="margin-bottom:1.5rem;">
                        <div class="was-gate__icon" style="margin-bottom:0.5rem;">👋</div>
                        <h3 class="was-gate__title">Welcome to ASTRO!</h3>
                        <p class="was-gate__text">To get started, please complete your profile below. This takes less than a minute and gives you instant access to register your child.</p>
                    </div>

                    <div id="was-onboard-success" class="was-alert was-alert--success" style="display:none;margin-bottom:1rem;"></div>
                    <div id="was-onboard-error"   class="was-alert was-alert--error"   style="display:none;margin-bottom:1rem;"></div>

                    <div class="was-field-row">
                        <div class="was-field">
                            <label class="was-label was-label--required">First name</label>
                            <input type="text" id="was-ob-first" class="was-input" value="<?php echo esc_attr( $onboard_guardian->first_name ?? get_user_meta( $user_id, 'first_name', true ) ); ?>">
                        </div>
                        <div class="was-field">
                            <label class="was-label was-label--required">Last name</label>
                            <input type="text" id="was-ob-last" class="was-input" value="<?php echo esc_attr( $onboard_guardian->last_name ?? get_user_meta( $user_id, 'last_name', true ) ); ?>">
                        </div>
                    </div>
                    <div class="was-field">
                        <label class="was-label was-label--required">Telephone</label>
                        <input type="tel" id="was-ob-tel" class="was-input" value="<?php echo esc_attr( $onboard_guardian->telephone ?? '' ); ?>">
                    </div>
                    <div class="was-field">
                        <label class="was-label was-label--required">First line of home address</label>
                        <input type="text" id="was-ob-address" class="was-input" value="<?php echo esc_attr( $onboard_guardian->address_line1 ?? '' ); ?>">
                    </div>
                    <div class="was-field was-field--half">
                        <label class="was-label was-label--required">Postcode</label>
                        <input type="text" id="was-ob-postcode" class="was-input" value="<?php echo esc_attr( $onboard_guardian->postcode ?? '' ); ?>" style="text-transform:uppercase;">
                    </div>
                    <div class="was-field">
                        <label class="was-label was-label--required">Occupation category <span class="was-hint">(FA inclusion data — confidential)</span></label>
                        <select id="was-ob-occ" class="was-select">
                            <?php foreach ( $occ_cats as $val => $label ) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected( $onboard_guardian->occupation_category ?? '', $val ); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="was-field">
                        <label class="was-label">Employer / business name <span class="was-hint">(optional)</span></label>
                        <input type="text" id="was-ob-employer" class="was-input" value="<?php echo esc_attr( $onboard_guardian->employer_name ?? '' ); ?>" placeholder="e.g. Smith Plumbing, Wolverhampton">
                    </div>

                    <button type="button" id="was-onboard-save-btn" class="was-btn was-btn--primary was-btn--full" style="margin-top:0.5rem;">
                        <span class="was-btn-label">Complete profile &amp; continue &rarr;</span>
                        <span class="was-btn-spinner" style="display:none;">Saving&hellip;</span>
                    </button>
                </div>
                <?php
            }
            include WAS_PATH . 'templates/portal-footer.php';
            return ob_get_clean();
        }

        if ( $show_parent )       self::render_parent_section( $user_id );
        if ( $show_coach )        self::render_coach_section( $user_id, $coach_all_teams );
        if ( $show_treasurer )    self::render_treasurer_section();
        if ( $show_social_media ) self::render_social_media_section();
        if ( $show_team_news_tab ) self::render_team_news_section( $user_id );
        if ( $show_messages )     self::render_messages_section( $user_id );
        if ( $show_registrations ) self::render_registrations_section();
        if ( $show_interest )      self::render_interest_section();

        include WAS_PATH . 'templates/portal-footer.php';

        return ob_get_clean();
    }

    /** "Please confirm your email address" notice (or the "confirmed" thank-you). */
    private static function render_verification_notice( $user, $pending ) {
        if ( ! $pending ) {
            if ( ! empty( $_GET['was_verified'] ) && WAS_Accounts::is_email_verified( $user->ID ) ) {
                echo '<div class="was-alert was-alert--success" style="margin-bottom:1rem;">Thanks &mdash; your email address is confirmed.</div>';
            }
            return;
        }

        // Accounts that registered with a matching email already have a link on its way;
        // anyone else (e.g. an older shop account) gets one now. Never more than one per 48h
        // unless they ask for it again.
        WAS_Accounts::maybe_send_verification_email( $user->ID, 2 * DAY_IN_SECONDS );
        ?>
        <div class="was-info-box" style="margin-bottom:1rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <strong>Please confirm your email address</strong>
                <p>To keep your family&rsquo;s details safe we need to check that <strong><?php echo esc_html( $user->user_email ); ?></strong> is really yours before we show your children&rsquo;s registrations here. We&rsquo;ve emailed you a confirmation link &mdash; click it and they&rsquo;ll appear. It can take a few minutes, so please check your spam / junk folder too.</p>
                <?php if ( ! empty( $_GET['was_verify_sent'] ) ) : ?>
                <p><strong>We&rsquo;ve sent you a new link.</strong></p>
                <?php elseif ( ! empty( $_GET['was_verify_error'] ) ) : ?>
                <p><strong>That link has expired or has been replaced by a newer one.</strong> Please use the most recent email, or send the link again.</p>
                <?php endif; ?>
                <p><a href="<?php echo esc_url( WAS_Accounts::resend_verification_url() ); ?>" class="was-link">Send the link again</a></p>
            </div>
        </div>
        <?php
    }

    // ── Parent section ─────────────────────────────────────────────────────────

    private static function render_parent_section( $user_id ) {
        global $wpdb;
        $season   = was_season();
        $guardian = WAS_Accounts::current_guardian();
        $players  = $guardian
            ? WAS_Database::get_players_by_guardian( $guardian->id, $season )
            : array();

        // Fetch latest payment per player, plus their arrears state (failed collections
        // not yet covered by a catch-up, any catch-up in flight, and the hold flag).
        $payment_map = array();
        $arrears_map = array();
        if ( $guardian ) {
            foreach ( $players as $p ) {
                $payments = WAS_Database::get_player_payments( $p->id );
                $payment_map[ $p->id ] = $payments;
                $arrears_map[ $p->id ] = WAS_Arrears::summary( $p );
            }
        }

        // For the "add another team" request panel: all teams this season,
        // minus the ones each child is already on (computed in the template).
        $all_teams = WAS_Database::get_teams( $season );

        include WAS_PATH . 'templates/portal-parent.php';
    }

    // ── Coach section ──────────────────────────────────────────────────────────

    private static function render_coach_section( $user_id, $all_teams = false ) {
        $season = was_season();
        // Committee/admin get every active team; coaches get their assigned teams.
        $teams  = $all_teams ? WAS_Database::get_teams( $season ) : WAS_Accounts::get_coach_teams( $user_id );

        $team_data = array();
        foreach ( $teams as $team ) {
            $players = WAS_Database::get_players_by_team( $team->id, $season );
            // For each player attach medical and consents
            foreach ( $players as &$p ) {
                $p->medical  = WAS_Database::get_medical( $p->id );
                $p->consents = WAS_Database::get_consents( $p->id );
                // Emergency contact
                global $wpdb;
                $p->emergency = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}was_emergency_contacts WHERE player_id = %d",
                    $p->id
                ) );
            }
            unset( $p );
            $team_data[] = array( 'team' => $team, 'players' => $players );
        }

        include WAS_PATH . 'templates/portal-coach.php';
    }

    // ── Players tab: registration officer's full-club register ─────────────────

    private static function render_registrations_section() {
        global $wpdb;
        $season = was_season();

        // Every completed (non-pending) registration this season with team, guardian + address
        $reg_players = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, t.name AS team_name, t.age_group,
                    g.first_name AS g_first, g.last_name AS g_last, g.email AS g_email,
                    g.telephone AS g_tel, g.address_line1 AS g_address, g.postcode AS g_postcode
             FROM {$wpdb->prefix}was_players p
             LEFT JOIN {$wpdb->prefix}was_teams t     ON p.team_id = t.id
             LEFT JOIN {$wpdb->prefix}was_guardians g ON p.primary_guardian_id = g.id
             WHERE p.season = %s AND p.registration_status NOT IN ( 'withdrawn', 'pending' )
             ORDER BY t.name ASC, p.last_name ASC, p.first_name ASC",
            $season
        ) );

        foreach ( $reg_players as $rp ) {
            $rp->extra_teams = WAS_Database::get_player_extra_teams( $rp->id );
        }

        $reg_teams   = WAS_Database::get_teams( $season );
        $photo_nonce = wp_create_nonce( 'was_photo_nonce' );

        include WAS_PATH . 'templates/portal-registrations.php';
    }

    // ── Interest List tab: committee's view of the waiting list ─────────────────

    private static function render_interest_section() {
        global $wpdb;
        $table = "{$wpdb->prefix}was_interest";

        $int_rows = $wpdb->get_results(
            "SELECT * FROM {$table}
             ORDER BY FIELD(status,'new','contacted','offered','placed','archived'), created_at DESC"
        );

        // Live demand for the pitch/council case — excludes anyone already sorted.
        $int_demand = $wpdb->get_results(
            "SELECT age_group, COUNT(*) c, SUM(play_up) up
             FROM {$table}
             WHERE status NOT IN ('placed','archived')
             GROUP BY age_group
             ORDER BY LENGTH(age_group), age_group"
        );

        $int_statuses = WAS_Interest::statuses();
        $int_counts   = array_fill_keys( array_keys( $int_statuses ), 0 );
        foreach ( $int_rows as $r ) {
            if ( isset( $int_counts[ $r->status ] ) ) $int_counts[ $r->status ]++;
        }

        $int_csv_url = WAS_Interest::export_url( get_permalink() );

        include WAS_PATH . 'templates/portal-interest.php';
    }

    // ── Messages tab: committee/admin bulk email ────────────────────────────────

    private static function render_messages_section( $user_id ) {
        global $wpdb;
        $season    = was_season();
        $msg_teams = WAS_Database::get_teams( $season );
        // Queued/in-progress first, then the most recent by when they went (or were due to go) out
        $history   = $wpdb->get_results(
            "SELECT m.*, u.display_name AS sender_name
             FROM {$wpdb->prefix}was_messages m
             LEFT JOIN {$wpdb->users} u ON m.sender_user_id = u.ID
             ORDER BY ( m.status IN ('scheduled','sending') ) DESC, COALESCE( m.sent_at, m.send_at, m.created_at ) DESC
             LIMIT 20"
        );
        $msg_me_email   = wp_get_current_user()->user_email;
        $msg_club_email = WAS_Notifications::club_contact_email();
        include WAS_PATH . 'templates/portal-messages.php';
    }

    /** One row of the Recent messages table (page render + AJAX refresh after schedule/cancel). */
    public static function msg_history_row( $m, $teams ) {
        $aud = implode( ' + ', array_map( 'ucfirst', array_filter( explode( ',', $m->audiences ) ) ) );
        if ( $m->team_id ) {
            foreach ( $teams as $t ) { if ( (int) $t->id === (int) $m->team_id ) { $aud .= ' (' . $t->name . ')'; break; } }
        }
        switch ( $m->status ) {
            case 'scheduled': $when = $m->send_at; $count = '—'; $label = 'Scheduled'; break;
            case 'sending':   $when = $m->send_at; $count = (int) $m->sent_count . ' / ' . (int) $m->recipients; $label = 'Sending'; break;
            case 'cancelled': $when = $m->send_at; $count = '—'; $label = 'Cancelled'; break;
            case 'failed':    $when = $m->send_at; $count = '0'; $label = 'Not sent: no recipients'; break;
            default:          $when = $m->sent_at ?: $m->created_at; $count = (int) $m->recipients; $label = 'Sent';
        }
        ob_start(); ?>
            <tr data-msg-id="<?php echo (int) $m->id; ?>">
                <td><?php echo esc_html( self::msg_uk_time( $when ) ); ?></td>
                <td><?php echo esc_html( ( $m->sender_name ?: '—' ) . ( $m->from_mode === 'club' ? ' (as the club)' : '' ) ); ?></td>
                <td><?php echo esc_html( $aud ); ?></td>
                <td><?php echo esc_html( $m->subject ); ?></td>
                <td><?php echo esc_html( $count ); ?></td>
                <td><?php echo esc_html( $label ); ?><?php if ( $m->status === 'scheduled' ) : ?> <button type="button" class="was-linkbtn was-msg-cancel" data-id="<?php echo (int) $m->id; ?>">Cancel</button><?php endif; ?></td>
            </tr>
        <?php
        return ob_get_clean();
    }

    private static function msg_row( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*, u.display_name AS sender_name
             FROM {$wpdb->prefix}was_messages m
             LEFT JOIN {$wpdb->users} u ON m.sender_user_id = u.ID
             WHERE m.id = %d",
            $id
        ) );
    }

    /** Stored times are site time (UTC here); people schedule and read them in UK time. */
    private static function msg_uk_time( $mysql ) {
        if ( ! $mysql ) return '—';
        $dt = date_create( $mysql, wp_timezone() );
        return $dt ? $dt->setTimezone( new DateTimeZone( 'Europe/London' ) )->format( 'd M Y H:i' ) : '—';
    }

    /**
     * Who a club message comes from. 'me': the site's usual From (noreply@) with replies to
     * the sender. 'club': from, and replies to, the club's general inbox.
     * Returns array( from_mode, from_email, reply_to ).
     */
    private static function msg_sender( $mode, $user ) {
        if ( $mode === 'club' ) {
            $club = WAS_Notifications::club_contact_email();
            return array( 'club', $club, $club );
        }
        return array( 'me', '', $user->user_email );
    }

    private static function msg_gate() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() || ! was_has_role( 'committee' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
    }

    /**
     * Resolve the recipient list for the chosen audiences.
     * parents  = distinct guardian emails of active players this season (optional team filter,
     *            counting a child into every team they play for, like the coach view)
     * coaches  = every user with the coach role
     * committee = every user with committee, treasurer or social_media
     */
    private static function msg_recipients( array $audiences, $team_id ) {
        global $wpdb;
        $season = was_season();
        $emails = array();

        if ( in_array( 'parents', $audiences, true ) ) {
            if ( $team_id ) {
                $rows = $wpdb->get_col( $wpdb->prepare(
                    "SELECT DISTINCT LOWER(g.email) FROM {$wpdb->prefix}was_guardians g
                     JOIN {$wpdb->prefix}was_players p ON p.primary_guardian_id = g.id
                     WHERE p.season = %s AND p.registration_status = 'active' AND g.email <> ''
                       AND ( p.team_id = %d OR EXISTS (
                             SELECT 1 FROM {$wpdb->prefix}was_player_teams pt
                             WHERE pt.player_id = p.id AND pt.team_id = %d ) )",
                    $season, $team_id, $team_id
                ) );
            } else {
                $rows = $wpdb->get_col( $wpdb->prepare(
                    "SELECT DISTINCT LOWER(g.email) FROM {$wpdb->prefix}was_guardians g
                     JOIN {$wpdb->prefix}was_players p ON p.primary_guardian_id = g.id
                     WHERE p.season = %s AND p.registration_status = 'active' AND g.email <> ''",
                    $season
                ) );
            }
            $emails = array_merge( $emails, $rows );
        }
        if ( in_array( 'coaches', $audiences, true ) ) {
            $emails = array_merge( $emails, WAS_Notifications::role_emails( array( 'coach' ) ) );
        }
        if ( in_array( 'committee', $audiences, true ) ) {
            $emails = array_merge( $emails, WAS_Notifications::role_emails( array( 'committee', 'treasurer', 'social_media' ) ) );
        }
        return array_values( array_unique( array_filter( array_map( 'trim', $emails ), 'is_email' ) ) );
    }

    /** Validated compose fields from $_POST (already unslashed): array( audiences, team_id, subject, body ). */
    private static function msg_compose_input() {
        $audiences = array_values( array_intersect(
            array_map( 'sanitize_key', (array) ( $_POST['audiences'] ?? array() ) ),
            array( 'parents', 'coaches', 'committee' )
        ) );
        $team_id = absint( $_POST['team_id'] ?? 0 );
        $subject = sanitize_text_field( $_POST['subject'] ?? '' );
        $body    = sanitize_textarea_field( $_POST['body'] ?? '' );

        if ( ! $audiences )          wp_send_json_error( array( 'message' => 'Choose at least one audience.' ) );
        if ( $subject === '' )       wp_send_json_error( array( 'message' => 'A subject is required.' ) );
        if ( trim( $body ) === '' )  wp_send_json_error( array( 'message' => 'The message is empty.' ) );

        return array( $audiences, $team_id, $subject, $body );
    }

    public static function ajax_msg_prepare() {
        self::msg_gate();

        $_POST = wp_unslash( $_POST );
        list( $audiences, $team_id, $subject, $body ) = self::msg_compose_input();

        $emails = self::msg_recipients( $audiences, $team_id );
        if ( ! $emails ) wp_send_json_error( array( 'message' => 'No recipients match that selection.' ) );

        $sender = wp_get_current_user();
        list( $from_mode, $from_email, $reply_to ) = self::msg_sender( sanitize_key( $_POST['from_mode'] ?? '' ), $sender );
        $token  = wp_generate_password( 24, false, false );
        set_transient( 'was_msg_' . $token, array(
            'emails'     => $emails,
            'subject'    => $subject,
            'body'       => $body,
            'audiences'  => implode( ',', $audiences ),
            'team_id'    => $team_id ?: null,
            'sender_id'  => $sender->ID,
            'from_mode'  => $from_mode,
            'from_email' => $from_email,
            'reply_to'   => $reply_to,
            'sent'       => 0,
        ), HOUR_IN_SECONDS );

        wp_send_json_success( array( 'token' => $token, 'total' => count( $emails ) ) );
    }

    /** Send the composed message to one address only, subject marked [TEST]. Not logged. */
    public static function ajax_msg_test() {
        self::msg_gate();

        $_POST   = wp_unslash( $_POST );
        $to      = sanitize_email( $_POST['to'] ?? '' );
        $subject = sanitize_text_field( $_POST['subject'] ?? '' );
        $body    = sanitize_textarea_field( $_POST['body'] ?? '' );

        if ( ! is_email( $to ) )     wp_send_json_error( array( 'message' => 'Enter a valid email address for the test.' ) );
        if ( $subject === '' )       wp_send_json_error( array( 'message' => 'A subject is required.' ) );
        if ( trim( $body ) === '' )  wp_send_json_error( array( 'message' => 'The message is empty.' ) );

        list( , $from_email, $reply_to ) = self::msg_sender( sanitize_key( $_POST['from_mode'] ?? '' ), wp_get_current_user() );
        if ( ! WAS_Notifications::send_club_message( $to, '[TEST] ' . $subject, $body, $reply_to, $from_email ) ) {
            wp_send_json_error( array( 'message' => 'The test email could not be sent.' ) );
        }
        wp_send_json_success( array( 'to' => $to ) );
    }

    public static function ajax_msg_batch() {
        self::msg_gate();

        $token = sanitize_key( $_POST['token'] ?? '' );
        $job   = get_transient( 'was_msg_' . $token );
        if ( ! $job ) wp_send_json_error( array( 'message' => 'This send has expired — please start again.' ) );

        $batch = array_slice( $job['emails'], $job['sent'], 20 );
        foreach ( $batch as $to ) {
            WAS_Notifications::send_club_message( $to, $job['subject'], $job['body'], $job['reply_to'], $job['from_email'] ?? '' );
        }
        $job['sent'] += count( $batch );

        if ( $job['sent'] >= count( $job['emails'] ) ) {
            global $wpdb;
            $now = current_time( 'mysql' );
            $wpdb->insert( "{$wpdb->prefix}was_messages", array(
                'sender_user_id' => $job['sender_id'],
                'audiences'      => $job['audiences'],
                'team_id'        => $job['team_id'],
                'subject'        => $job['subject'],
                'body'           => $job['body'],
                'recipients'     => count( $job['emails'] ),
                'created_at'     => $now,
                'status'         => 'sent',
                'from_mode'      => $job['from_mode'] ?? 'me',
                'reply_to'       => $job['reply_to'],
                'sent_at'        => $now,
                'sent_count'     => count( $job['emails'] ),
            ) );
            delete_transient( 'was_msg_' . $token );
            wp_send_json_success( array( 'sent' => $job['sent'], 'total' => count( $job['emails'] ), 'done' => true ) );
        }

        set_transient( 'was_msg_' . $token, $job, HOUR_IN_SECONDS );
        wp_send_json_success( array( 'sent' => $job['sent'], 'total' => count( $job['emails'] ), 'done' => false ) );
    }

    // ── Scheduled club messages ─────────────────────────────────────────────────

    public static function cron_schedules( $schedules ) {
        $schedules['was_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Every 5 minutes (ASTRO messages)' );
        return $schedules;
    }

    /** datetime-local value, read as UK time → Unix timestamp, or an error message string. */
    private static function msg_parse_send_at( $value ) {
        $uk = new DateTimeZone( 'Europe/London' );
        $dt = DateTime::createFromFormat( '!Y-m-d\TH:i', (string) $value, $uk )
           ?: DateTime::createFromFormat( '!Y-m-d\TH:i:s', (string) $value, $uk );
        if ( ! $dt ) return 'Choose the date and time to send it.';
        $ts = $dt->getTimestamp();
        if ( $ts < time() + 2 * MINUTE_IN_SECONDS ) return 'Choose a time at least a couple of minutes from now.';
        if ( $ts > time() + 90 * DAY_IN_SECONDS )   return 'Messages can be scheduled up to 90 days ahead.';
        return $ts;
    }

    /**
     * Schedule the composed message (UK time). With dry_run it only validates and counts,
     * so the browser can confirm first. Recipients are worked out again when it goes out.
     */
    public static function ajax_msg_schedule() {
        self::msg_gate();

        $_POST = wp_unslash( $_POST );
        list( $audiences, $team_id, $subject, $body ) = self::msg_compose_input();

        $ts = self::msg_parse_send_at( $_POST['send_at'] ?? '' );
        if ( is_string( $ts ) ) wp_send_json_error( array( 'message' => $ts ) );

        $emails = self::msg_recipients( $audiences, $team_id );
        if ( ! $emails ) wp_send_json_error( array( 'message' => 'No recipients match that selection.' ) );

        $when = wp_date( 'l j F \a\t H:i', $ts, new DateTimeZone( 'Europe/London' ) );
        if ( ! empty( $_POST['dry_run'] ) ) {
            wp_send_json_success( array( 'total' => count( $emails ), 'when' => $when ) );
        }

        global $wpdb;
        $sender = wp_get_current_user();
        list( $from_mode, , $reply_to ) = self::msg_sender( sanitize_key( $_POST['from_mode'] ?? '' ), $sender );
        $wpdb->insert( "{$wpdb->prefix}was_messages", array(
            'sender_user_id' => $sender->ID,
            'audiences'      => implode( ',', $audiences ),
            'team_id'        => $team_id ?: null,
            'subject'        => $subject,
            'body'           => $body,
            'recipients'     => 0,
            'created_at'     => current_time( 'mysql' ),
            'status'         => 'scheduled',
            'from_mode'      => $from_mode,
            'reply_to'       => $reply_to,
            'send_at'        => wp_date( 'Y-m-d H:i:s', $ts ),
            'run_key'        => wp_generate_password( 32, false, false ),
        ) );
        $id = (int) $wpdb->insert_id;
        if ( ! $id ) wp_send_json_error( array( 'message' => 'The message could not be saved. Please try again.' ) );

        wp_schedule_single_event( $ts, 'was_msg_run_scheduled', array( $id ) );
        if ( ! wp_next_scheduled( 'was_msg_watchdog' ) ) {
            wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'was_five_minutes', 'was_msg_watchdog' );
        }

        wp_send_json_success( array(
            'when'  => $when,
            'total' => count( $emails ),
            'row'   => self::msg_history_row( self::msg_row( $id ), WAS_Database::get_teams( was_season() ) ),
        ) );
    }

    public static function ajax_msg_cancel() {
        self::msg_gate();
        global $wpdb;

        // Only while still waiting: once a run has locked it, it is already going out.
        $id   = absint( $_POST['id'] ?? 0 );
        $done = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}was_messages SET status = 'cancelled'
             WHERE id = %d AND status = 'scheduled' AND lock_until < %d",
            $id, time()
        ) );
        if ( ! $done ) wp_send_json_error( array( 'message' => 'This message can no longer be cancelled; it has already started sending.' ) );

        wp_clear_scheduled_hook( 'was_msg_run_scheduled', array( $id ) );
        wp_send_json_success( array( 'row' => self::msg_history_row( self::msg_row( $id ), WAS_Database::get_teams( was_season() ) ) ) );
    }

    public static function run_scheduled_message( $id ) {
        self::msg_process( (int) $id );
    }

    /** Every 5 minutes while anything is queued: start what's due, resume a stalled send, then stop. */
    public static function msg_watchdog() {
        global $wpdb;
        $t   = "{$wpdb->prefix}was_messages";
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM $t WHERE ( status = 'scheduled' AND send_at <= %s ) OR status = 'sending' ORDER BY id",
            current_time( 'mysql' )
        ) );
        foreach ( $ids as $id ) {
            self::msg_process( (int) $id );
        }
        if ( ! $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status IN ('scheduled','sending')" ) ) {
            wp_clear_scheduled_hook( 'was_msg_watchdog' );
        }
    }

    /** Loopback from msg_process(): carry on a long send in a fresh request. Keyed per message. */
    public static function ajax_msg_continue() {
        global $wpdb;
        $id  = absint( $_POST['id'] ?? 0 );
        $key = (string) ( $_POST['key'] ?? '' );
        $run = $id ? $wpdb->get_var( $wpdb->prepare( "SELECT run_key FROM {$wpdb->prefix}was_messages WHERE id = %d", $id ) ) : '';
        if ( ! $run || ! hash_equals( $run, $key ) ) wp_die( '', '', array( 'response' => 403 ) );

        ignore_user_abort( true );
        self::msg_process( $id );
        wp_die();
    }

    /**
     * Send a scheduled message, picking up where it left off. Each run sends for about 40
     * seconds then hands over to a fresh request, so a long list never hits the PHP time
     * limit. lock_until stops two runs overlapping; sent_count is saved after every email
     * so a run that dies part-way resumes without re-sending.
     */
    private static function msg_process( $id ) {
        global $wpdb;
        $t     = "{$wpdb->prefix}was_messages";
        $start = time();

        $locked = $wpdb->query( $wpdb->prepare(
            "UPDATE $t SET lock_until = %d WHERE id = %d AND status IN ('scheduled','sending') AND lock_until < %d",
            $start + 2 * MINUTE_IN_SECONDS, $id, $start
        ) );
        if ( ! $locked ) return;

        $m = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) );

        if ( $m->status === 'scheduled' ) {
            if ( $m->send_at > current_time( 'mysql' ) ) {   // not due yet
                $wpdb->update( $t, array( 'lock_until' => 0 ), array( 'id' => $id ) );
                return;
            }
            $emails = self::msg_recipients( array_filter( explode( ',', $m->audiences ) ), (int) $m->team_id );
            if ( ! $emails ) {
                $wpdb->update( $t, array( 'status' => 'failed', 'lock_until' => 0 ), array( 'id' => $id ) );
                return;
            }
            $m->recipient_list = wp_json_encode( $emails );
            $m->sent_count     = 0;
            $wpdb->update( $t, array(
                'status'         => 'sending',
                'recipient_list' => $m->recipient_list,
                'recipients'     => count( $emails ),
                'sent_count'     => 0,
            ), array( 'id' => $id ) );
        }

        $emails = json_decode( (string) $m->recipient_list, true ) ?: array();
        $from   = $m->from_mode === 'club' ? WAS_Notifications::club_contact_email() : '';
        $i      = (int) $m->sent_count;
        while ( $i < count( $emails ) && time() - $start < 40 ) {
            WAS_Notifications::send_club_message( $emails[ $i ], $m->subject, $m->body, $m->reply_to, $from );
            $i++;
            $wpdb->update( $t, array( 'sent_count' => $i ), array( 'id' => $id ) );
        }

        if ( $i >= count( $emails ) ) {
            $wpdb->update( $t, array(
                'status'         => 'sent',
                'sent_at'        => current_time( 'mysql' ),
                'lock_until'     => 0,
                'recipient_list' => null,
            ), array( 'id' => $id ) );
            return;
        }

        // More to go: free the lock and continue straight away in a new request (the watchdog
        // picks it up within 5 minutes if this loopback doesn't get through).
        $wpdb->update( $t, array( 'lock_until' => 0 ), array( 'id' => $id ) );
        wp_remote_post( admin_url( 'admin-ajax.php' ), array(
            'blocking'  => false,
            'timeout'   => 0.01,
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
            'body'      => array( 'action' => 'was_msg_continue', 'id' => $id, 'key' => $m->run_key ),
        ) );
    }

    // ── Team News tab: coach submission + reviewer tools (role-aware) ──────────

    private static function render_team_news_section( $user_id ) {
        $is_admin   = current_user_can( 'manage_options' );
        $roles      = was_get_roles( $user_id );
        $can_submit = $is_admin || in_array( 'coach', $roles, true );
        $can_review = $is_admin || in_array( 'social_media', $roles, true ) || in_array( 'treasurer', $roles, true ) || in_array( 'committee', $roles, true );

        // Coach submission data (teams only — no player detail needed here)
        $team_data     = array();
        $my_news       = array();
        $news_no_photo = array();
        if ( $can_submit ) {
            foreach ( WAS_Accounts::get_coach_teams( $user_id ) as $t ) {
                $team_data[]             = array( 'team' => $t );
                $news_no_photo[ $t->id ] = WAS_Team_News::no_photo_players( $t->id );
            }
            $my_news = WAS_Team_News::get_coach_posts( $user_id );
        }

        // Reviewer data
        $pending = $published = array();
        if ( $can_review ) {
            foreach ( WAS_Team_News::get_pending_posts() as $p ) {
                $pending[] = self::news_row( $p, true );
            }
            foreach ( WAS_Team_News::get_recent_published() as $p ) {
                $published[] = self::news_row( $p, false );
            }
        }
        $fb_page_url = get_option( 'was_fb_page_url', '' );
        $fb_api      = WAS_Facebook::is_enabled();

        include WAS_PATH . 'templates/portal-team-news.php';
    }

    private static function news_row( $p, $with_consents ) {
        $team_id = (int) get_post_meta( $p->ID, WAS_Team_News::META_TEAM_ID, true );
        $team    = $team_id ? WAS_Database::get_team( $team_id ) : null;
        $coach   = get_userdata( (int) get_post_meta( $p->ID, WAS_Team_News::META_COACH, true ) );
        return array(
            'post'       => $p,
            'type_label' => WAS_Team_News::type_label( get_post_meta( $p->ID, WAS_Team_News::META_TYPE, true ) ),
            'team_name'  => $team ? $team->name : (string) get_post_meta( $p->ID, WAS_Team_News::META_TEAM_SLUG, true ),
            'coach'      => $coach ? $coach->display_name : '—',
            'photos'     => WAS_Team_News::photo_ids( $p->ID ),
            'no_photo'   => ( $with_consents && $team ) ? WAS_Team_News::no_photo_players( $team->id ) : array(),
            'fb_status'  => get_post_meta( $p->ID, WAS_Team_News::META_FB_STATUS, true ) ?: 'none',
            'fb_post_id' => get_post_meta( $p->ID, WAS_Team_News::META_FB_POST, true ),
            'social'     => (bool) get_post_meta( $p->ID, WAS_Team_News::META_SOCIAL, true ),
        );
    }

    // ── Treasurer section ──────────────────────────────────────────────────────

    private static function render_treasurer_section() {
        global $wpdb;
        $season = was_season();
        $teams  = WAS_Database::get_teams();

        $teams = WAS_Database::get_teams( $season );

        // Awaiting first payment = signed+active players who genuinely have nothing
        // in motion: no confirmed payment AND no in-flight (submitted/pending) payment.
        // Exempt players (club-agreed £0 — subs_override = 0) need no Direct Debit and
        // are excluded entirely.
        $overdue = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, t.name AS team_name,
                    g.first_name AS g_first, g.last_name AS g_last, g.email AS g_email
             FROM {$wpdb->prefix}was_players p
             LEFT JOIN {$wpdb->prefix}was_teams t     ON p.team_id = t.id
             LEFT JOIN {$wpdb->prefix}was_guardians g ON p.primary_guardian_id = g.id
             WHERE p.season = %s AND p.participation_type = 'signed'
               AND p.registration_status = 'active'
               AND ( p.subs_override IS NULL OR p.subs_override > 0 )
               AND NOT EXISTS (
                   SELECT 1 FROM {$wpdb->prefix}was_payments
                   WHERE player_id = p.id
                     AND status IN ('confirmed','submitted','pending_submission')
               )
             ORDER BY t.name, p.first_name",
            $season
        ) );

        // ALL failed payments for the season — own block so the Failed tile always
        // matches what's on screen (the Recent list only renders the latest 50).
        $failed_payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT pay.*, p.first_name AS p_first, p.last_name AS p_last,
                    t.name AS team_name, p.team_id AS team_id, p.payment_hold_since,
                    g.first_name AS g_first, g.last_name AS g_last, g.email AS g_email
             FROM {$wpdb->prefix}was_payments pay
             LEFT JOIN {$wpdb->prefix}was_players p    ON pay.player_id   = p.id
             LEFT JOIN {$wpdb->prefix}was_teams t      ON p.team_id       = t.id
             LEFT JOIN {$wpdb->prefix}was_guardians g  ON pay.guardian_id = g.id
             WHERE pay.season = %s AND pay.status = 'failed'
             ORDER BY pay.charge_date DESC, pay.created_at DESC",
            $season
        ) );

        // Players in arrears (failed collections not covered by a catch-up), one row each.
        $arrears_players = WAS_Arrears::season_arrears( $season );
        $arrears_total   = 0.0;
        foreach ( $arrears_players as $ap ) $arrears_total += (float) $ap->arrears;

        // Recent payments (last 30)
        $recent_payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT pay.*, p.first_name AS p_first, p.last_name AS p_last,
                    t.name AS team_name, p.team_id AS team_id,
                    g.first_name AS g_first, g.last_name AS g_last
             FROM {$wpdb->prefix}was_payments pay
             LEFT JOIN {$wpdb->prefix}was_players p    ON pay.player_id   = p.id
             LEFT JOIN {$wpdb->prefix}was_teams t      ON p.team_id       = t.id
             LEFT JOIN {$wpdb->prefix}was_guardians g  ON pay.guardian_id = g.id
             WHERE pay.season = %s
             ORDER BY pay.charge_date DESC, pay.created_at DESC
             LIMIT 50",
            $season
        ) );

        // Stats
        $stats = array(
            'total_active'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_players WHERE season=%s AND registration_status='active' AND participation_type='signed'", $season ) ),
            'overdue_count' => count( $overdue ),
            'in_transit'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_payments WHERE season=%s AND status IN ('submitted','pending_submission')", $season ) ),
            'confirmed'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_payments WHERE season=%s AND status='confirmed'", $season ) ),
            'failed'        => count( $failed_payments ),
            'arrears'       => count( $arrears_players ),
            'arrears_total' => round( $arrears_total, 2 ),
            'exempt'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_players WHERE season=%s AND registration_status='active' AND participation_type='signed' AND subs_override = 0", $season ) ),
        );

        include WAS_PATH . 'templates/portal-treasurer.php';
    }

    // ── AJAX: send overdue reminder ────────────────────────────────────────────

    public static function ajax_send_reminder() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! was_has_role( 'treasurer' ) && ! was_has_role( 'committee' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $player_id = absint( $_POST['player_id'] ?? 0 );
        $player    = WAS_Database::get_player( $player_id );
        $guardian  = $player ? WAS_Database::get_guardian( $player->primary_guardian_id ) : null;

        if ( ! $player || ! $guardian ) {
            wp_send_json_error( array( 'message' => 'Player or guardian not found.' ) );
        }

        if ( WAS_Notifications::parent_emails_paused( $player ) ) {
            wp_send_json_error( array( 'message' => 'Payment emails are paused for this family (set on the admin player page), so no reminder was sent.' ) );
        }
        WAS_Notifications::payment_overdue_reminder( $player, $guardian );
        wp_send_json_success( array( 'message' => 'Reminder sent to ' . esc_html( $guardian->email ) . '.' ) );
    }

    // ── AJAX: sync GoCardless payments ─────────────────────────────────────────

    public static function ajax_gc_sync() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! was_has_role( 'treasurer' ) && ! was_has_role( 'committee' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        global $wpdb;
        $season  = was_season();
        $players = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_players
             WHERE season=%s AND participation_type='signed'
               AND gc_mandate_id != '' AND registration_status='active'",
            $season
        ) );

        foreach ( $players as $p ) {
            WAS_GoCardless::sync_player_payments( $p );
        }

        wp_send_json_success( array( 'message' => 'Synced ' . count( $players ) . ' players from GoCardless.' ) );
    }
    // ── Social Media Officer Section ────────────────────────────────────────────

    private static function render_social_media_section() {
        global $wpdb;
        $season = was_season();
        $teams  = WAS_Database::get_teams();

        // Get all players this season — pivot consent rows into columns
        // was_consents is EAV: consent_type / consent_value per player
        $players_raw = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, t.name AS team_name, t.age_group
             FROM {$wpdb->prefix}was_players p
             LEFT JOIN {$wpdb->prefix}was_teams t ON p.team_id = t.id
             WHERE p.season = %s
               AND p.registration_status = 'active'
             ORDER BY t.name ASC, p.last_name ASC, p.first_name ASC",
            $season
        ) );

        // Fetch consent values for each player and filter to those with at least one disagree
        $players = array();
        foreach ( $players_raw as $p ) {
            $consents = WAS_Database::get_consents( $p->id );
            // Keys match what class-registration.php saves: 'photography', 'legal_proceedings'
            $photo = $consents['photography']       ?? 'agree';
            $legal = $consents['legal_proceedings'] ?? 'agree';

            if ( $photo === 'disagree' || $legal === 'disagree' ) {
                $p->consent_photography = $photo;
                $p->consent_legal       = $legal;
                $players[]              = $p;
            }
        }

        include WAS_PATH . 'templates/portal-social-media.php';
    }
    // ── Parent: update child info ─────────────────────────────────────────────────

    public static function ajax_update_child() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $player_id = absint( $_POST['player_id'] ?? 0 );
        $guardian  = WAS_Accounts::current_guardian();

        if ( ! $guardian ) wp_send_json_error( array( 'message' => 'Guardian not found.' ) );

        // Verify this player belongs to this guardian
        global $wpdb;
        $player = WAS_Database::get_player( $player_id );
        if ( ! $player || $player->primary_guardian_id != $guardian->id ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }

        $update = array(
            'first_name' => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $_POST['last_name']  ?? '' ),
        );
        if ( $update['first_name'] === '' || $update['last_name'] === '' ) {
            wp_send_json_error( array( 'message' => "Please enter your child's first and last name." ) );
        }

        // Date of birth (only overwrite when a valid past date is supplied — lets existing families backfill)
        $dob_raw = sanitize_text_field( $_POST['dob'] ?? '' );
        if ( $dob_raw && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dob_raw ) && strtotime( $dob_raw ) && strtotime( $dob_raw ) <= time() ) {
            $update['dob'] = $dob_raw;
        }

        // School
        $school_id = absint( $_POST['school_id'] ?? 0 );
        if ( $school_id ) {
            $update['school_id']    = $school_id;
            $update['school_other'] = '';
        } else {
            $update['school_id']    = 0;
            $update['school_other'] = sanitize_text_field( $_POST['school_other'] ?? '' );
        }

        $wpdb->update( "{$wpdb->prefix}was_players", $update, array( 'id' => $player_id ) );

        // Update emergency contact
        $ec_name = sanitize_text_field( $_POST['emergency_name']      ?? '' );
        $ec_tel  = sanitize_text_field( $_POST['emergency_telephone'] ?? '' );

        if ( $ec_name || $ec_tel ) {
            $ec_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}was_emergency_contacts WHERE player_id = %d",
                $player_id
            ) );
            $ec_data = array( 'contact_name' => $ec_name, 'contact_telephone' => $ec_tel );
            if ( $ec_exists ) {
                $wpdb->update( "{$wpdb->prefix}was_emergency_contacts", $ec_data, array( 'player_id' => $player_id ) );
            } else {
                $wpdb->insert( "{$wpdb->prefix}was_emergency_contacts", array_merge( array( 'player_id' => $player_id ), $ec_data ) );
            }
        }

        // Update medical questions — store detail text (empty string = No, has text = Yes)
        $med_map = array(
            'health_needs'        => 'health_needs',
            'medication'          => 'current_medication',
            'medical_training'    => 'medical_training_needs',
            'access_needs'        => 'access_needs',
            'communication_needs' => 'communication_needs',
        );
        $med_update = array();
        foreach ( $med_map as $form_key => $db_col ) {
            $yn     = sanitize_text_field( $_POST[ 'yn_' . $form_key ] ?? 'no' );
            $detail = sanitize_textarea_field( $_POST[ 'med_detail_' . $form_key ] ?? '' );
            // If No, clear the detail; if Yes, save the detail text
            $med_update[ $db_col ] = ( $yn === 'yes' ) ? $detail : '';
        }
        $med_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}was_medical WHERE player_id = %d",
            $player_id
        ) );
        if ( $med_exists ) {
            $wpdb->update( "{$wpdb->prefix}was_medical", $med_update, array( 'player_id' => $player_id ) );
        } else {
            $wpdb->insert( "{$wpdb->prefix}was_medical", array_merge( array( 'player_id' => $player_id ), $med_update ) );
        }

        wp_send_json_success( array( 'message' => 'Child information updated.' ) );
    }

    // ── Parent: update child photo ─────────────────────────────────────────────

    public static function ajax_update_child_photo() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $player_id = absint( $_POST['player_id'] ?? 0 );
        $guardian  = WAS_Accounts::current_guardian();
        if ( ! $guardian ) wp_send_json_error( array( 'message' => 'Not authorised.' ) );

        global $wpdb;
        $player = WAS_Database::get_player( $player_id );
        if ( ! $player || $player->primary_guardian_id != $guardian->id ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }

        if ( empty( $_FILES['photo']['tmp_name'] ) ) {
            wp_send_json_error( array( 'message' => 'No photo uploaded.' ) );
        }

        $tmp  = $_FILES['photo']['tmp_name'];
        $name = sanitize_file_name( $_FILES['photo']['name'] );
        $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

        if ( ! in_array( $ext, array('jpg','jpeg','png','gif','webp'), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid file type. Please upload a JPEG or PNG.' ) );
        }

        if ( $_FILES['photo']['size'] > 8 * 1024 * 1024 ) {
            wp_send_json_error( array( 'message' => 'Photo must be under 8MB.' ) );
        }

        $filename = 'player-' . $player_id . '-' . time() . '.' . $ext;
        $dest     = was_photo_dir() . $filename;

        if ( ! move_uploaded_file( $tmp, $dest ) ) {
            wp_send_json_error( array( 'message' => 'Could not save the photo. Please try again.' ) );
        }

        // Delete old photo file
        if ( $player->photo_path ) {
            $old_path = was_photo_dir() . basename( $player->photo_path );
            if ( file_exists( $old_path ) ) @unlink( $old_path );
        }

        $wpdb->update(
            "{$wpdb->prefix}was_players",
            array( 'photo_path' => $filename ),
            array( 'id' => $player_id )
        );

        wp_send_json_success( array(
            'message'   => 'Photo updated.',
            'photo_url' => was_photo_url( $player_id ),
        ) );
    }

    // ── Parent: update consent + notify safeguarding ────────────────────────────

    public static function ajax_update_consent() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $player_id    = absint( $_POST['player_id']    ?? 0 );
        $consent_type = sanitize_key( $_POST['consent_type']  ?? '' );
        $consent_val  = sanitize_text_field( $_POST['consent_value'] ?? '' );

        $allowed_types = array( 'photography', 'legal_proceedings' );
        if ( ! in_array( $consent_type, $allowed_types, true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid consent type.' ) );
        }
        if ( ! in_array( $consent_val, array( 'agree', 'disagree' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid consent value.' ) );
        }

        $guardian = WAS_Accounts::current_guardian();
        if ( ! $guardian ) wp_send_json_error( array( 'message' => 'Not authorised.' ) );

        global $wpdb;
        $player = WAS_Database::get_player( $player_id );
        if ( ! $player || $player->primary_guardian_id != $guardian->id ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }

        // Get old value for the email
        $old_consents = WAS_Database::get_consents( $player_id );
        $old_val      = $old_consents[ $consent_type ] ?? 'agree';

        // Update consent
        $wpdb->delete( "{$wpdb->prefix}was_consents", array( 'player_id' => $player_id, 'consent_type' => $consent_type ) );
        $wpdb->insert( "{$wpdb->prefix}was_consents", array(
            'player_id'     => $player_id,
            'consent_type'  => $consent_type,
            'consent_value' => $consent_val,
            'ip_address'    => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
        ) );

        // Notify safeguarding, coaches and social media officers
        WAS_Notifications::consent_changed( $player, $guardian, $consent_type, $old_val, $consent_val );

        wp_send_json_success( array( 'message' => 'Consent updated.' ) );
    }

    // ── Coach: upload team logo ──────────────────────────────────────────────────

    public static function ajax_upload_team_logo() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $user_id = get_current_user_id();
        $team_id = absint( $_POST['team_id'] ?? 0 );

        // Must be a coach assigned to this team, or admin
        if ( ! current_user_can( 'manage_options' ) ) {
            $coach_teams = WAS_Accounts::get_coach_teams( $user_id );
            $team_ids    = array_map( function($t) { return (int) $t->id; }, $coach_teams );
            if ( ! in_array( $team_id, $team_ids, true ) ) {
                wp_send_json_error( array( 'message' => 'Not authorised for this team.' ) );
            }
        }

        if ( empty( $_FILES['logo']['tmp_name'] ) ) {
            wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
        }

        $tmp  = $_FILES['logo']['tmp_name'];
        $name = sanitize_file_name( $_FILES['logo']['name'] );
        $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

        if ( ! in_array( $ext, array('jpg','jpeg','png','gif','webp','svg'), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid file type. Please upload an image.' ) );
        }

        if ( $_FILES['logo']['size'] > 2 * 1024 * 1024 ) {
            wp_send_json_error( array( 'message' => 'File too large. Maximum size is 2MB.' ) );
        }

        $filename = 'team-' . $team_id . '-' . time() . '.' . $ext;
        $dest     = was_logo_dir() . $filename;

        if ( ! move_uploaded_file( $tmp, $dest ) ) {
            wp_send_json_error( array( 'message' => 'Could not save the file. Please try again.' ) );
        }

        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}was_teams",
            array( 'logo_path' => $filename ),
            array( 'id'        => $team_id )
        );

        wp_send_json_success( array(
            'message'  => 'Logo updated.',
            'logo_url' => was_logo_url( $filename ),
        ) );
    }

    // ── Coach: edit team profile (league + description) ──────────────────────────

    public static function ajax_team_profile_save() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $user_id = get_current_user_id();
        $team_id = absint( $_POST['team_id'] ?? 0 );

        // Must be a coach assigned to this team, or admin
        if ( ! current_user_can( 'manage_options' ) ) {
            $coach_teams = WAS_Accounts::get_coach_teams( $user_id );
            $team_ids    = array_map( function($t) { return (int) $t->id; }, $coach_teams );
            if ( ! in_array( $team_id, $team_ids, true ) ) {
                wp_send_json_error( array( 'message' => 'Not authorised for this team.' ) );
            }
        }

        $data = array(
            'league'        => sanitize_text_field( wp_unslash( $_POST['league'] ?? '' ) ),
            'description'   => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'training_info' => sanitize_text_field( wp_unslash( $_POST['training_info'] ?? '' ) ),
            'coach_names'   => sanitize_text_field( wp_unslash( $_POST['coach_names'] ?? '' ) ),
            'coach_contact' => sanitize_text_field( wp_unslash( $_POST['coach_contact'] ?? '' ) ),
            'honours'       => sanitize_textarea_field( wp_unslash( $_POST['honours'] ?? '' ) ),
            'sponsor_line'  => sanitize_text_field( wp_unslash( $_POST['sponsor_line'] ?? '' ) ),
        );

        global $wpdb;
        $wpdb->update( "{$wpdb->prefix}was_teams", $data, array( 'id' => $team_id ) );

        wp_send_json_success( array( 'message' => 'Team profile saved.' ) );
    }
}