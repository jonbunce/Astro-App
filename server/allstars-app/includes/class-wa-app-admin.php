<?php
/**
 * Settings → Allstars App: the app switch, tester emails, minimum app version and store links,
 * status, and "sign everyone out of the app".
 */

defined( 'ABSPATH' ) || exit;

class WA_App_Admin {

    const PAGE = 'allstars-app';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_wa_app_save_settings', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_wa_app_revoke_all', array( __CLASS__, 'revoke_all' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( WA_APP_FILE ), array( __CLASS__, 'action_links' ) );
    }

    public static function menu() {
        add_options_page( 'Allstars App', 'Allstars App', 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
    }

    public static function action_links( $links ) {
        $links   = is_array( $links ) ? $links : array();
        $links[] = '<a href="' . esc_url( self::page_url() ) . '">Settings</a>';
        return $links;
    }

    private static function page_url( array $args = array() ): string {
        return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'options-general.php' ) );
    }

    private static function modes(): array {
        return array(
            'off'     => array( 'Off', 'nobody can use the app; every app route except the health check says the app isn\'t open yet.' ),
            'testers' => array( 'Testers only', 'only the accounts listed below can sign in, see content and receive notifications.' ),
            'on'      => array( 'On', 'anyone with a website account can sign in; news, fixtures and club pages are public.' ),
        );
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public static function save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sorry, you are not allowed to change these settings.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'wa_app_save_settings' );

        $mode = isset( $_POST['wa_app_mode'] ) && is_string( $_POST['wa_app_mode'] ) ? sanitize_key( wp_unslash( $_POST['wa_app_mode'] ) ) : 'off';
        if ( ! array_key_exists( $mode, self::modes() ) ) {
            $mode = 'off';
        }

        $raw    = isset( $_POST['wa_app_testers'] ) && is_string( $_POST['wa_app_testers'] ) ? (string) wp_unslash( $_POST['wa_app_testers'] ) : '';
        $lines  = preg_split( '/[\r\n,;]+/', $raw );
        $lines  = is_array( $lines ) ? $lines : array();
        $emails = array();
        $bad    = 0;
        foreach ( $lines as $line ) {
            $line = strtolower( trim( $line ) );
            if ( '' === $line ) {
                continue;
            }
            $clean = sanitize_email( $line );
            if ( '' !== $clean && $clean === $line && is_email( $clean ) ) {
                $emails[ $clean ] = $clean;
            } else {
                $bad++;
            }
        }

        $min     = isset( $_POST['wa_app_min_version'] ) && is_string( $_POST['wa_app_min_version'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['wa_app_min_version'] ) ) ) : '';
        $bad_ver = 0;
        if ( '' === $min ) {
            $min = '1.0.0';
        } elseif ( ! preg_match( '/^\d+(\.\d+){0,3}$/', $min ) ) {
            $min     = WA_App_Content::min_version();
            $bad_ver = 1;
        }

        $bad_url = 0;
        foreach ( WA_App_Content::STORE_HOSTS as $platform => $host ) {
            $key = 'wa_app_store_url_' . $platform;
            $url   = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? trim( wp_unslash( $_POST[ $key ] ) ) : '';
            $clean = '' === $url ? '' : WA_App_Content::clean_store_url( $url, $platform );
            if ( null === $clean ) {
                $bad_url++;
            } else {
                update_option( $key, $clean );
            }
        }

        update_option( 'wa_app_mode', $mode );
        update_option( 'wa_app_testers', array_values( $emails ) );
        update_option( 'wa_app_min_version', $min );
        WA_App_Push::sync_schedule();

        wp_safe_redirect( self::page_url( array( 'wa_notice' => 'saved', 'wa_bad' => $bad, 'wa_bad_ver' => $bad_ver, 'wa_bad_url' => $bad_url ) ) );
        exit;
    }

    public static function revoke_all() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sorry, you are not allowed to do that.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'wa_app_revoke_all' );
        $count = WA_App_Auth::revoke_all();
        wp_safe_redirect( self::page_url( array( 'wa_notice' => 'revoked', 'wa_count' => $count ) ) );
        exit;
    }

    // ── Page ─────────────────────────────────────────────────────────────────

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $mode    = wa_app_mode();
        $testers = wa_app_testers();
        $stats   = self::stats();
        $queue   = $stats['queue'];
        $next    = wp_next_scheduled( WA_App_Push::CRON_HOOK );
        $last    = (int) get_option( 'wa_app_push_last_run', 0 );
        $format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        ?>
        <div class="wrap">
            <h1>Allstars App</h1>
            <?php self::notices(); ?>

            <p>Server side of the club app: app sign-in, opening website pages signed in, push notifications and club content.
                Current mode: <strong><?php echo esc_html( self::modes()[ $mode ][0] ); ?></strong>.</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="wa_app_save_settings">
                <?php wp_nonce_field( 'wa_app_save_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">App mode</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span>App mode</span></legend>
                                <?php foreach ( self::modes() as $value => $label ) : ?>
                                    <label>
                                        <input type="radio" name="wa_app_mode" value="<?php echo esc_attr( $value ); ?>" <?php checked( $mode, $value ); ?>>
                                        <strong><?php echo esc_html( $label[0] ); ?></strong> &ndash; <?php echo esc_html( $label[1] ); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wa_app_testers">Tester emails</label></th>
                        <td>
                            <textarea name="wa_app_testers" id="wa_app_testers" rows="8" cols="50" class="large-text code"><?php echo esc_textarea( implode( "\n", $testers ) ); ?></textarea>
                            <p class="description">One email address per line &ndash; the email on each tester's website account. Used when the mode is <em>Testers only</em>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wa_app_min_version">Minimum app version</label></th>
                        <td>
                            <input type="text" name="wa_app_min_version" id="wa_app_min_version" class="regular-text" value="<?php echo esc_attr( WA_App_Content::min_version() ); ?>">
                            <p class="description">Older versions of the app ask people to update, e.g. <code>1.0.0</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wa_app_store_url_ios">App Store link</label></th>
                        <td>
                            <input type="url" name="wa_app_store_url_ios" id="wa_app_store_url_ios" class="regular-text code" value="<?php echo esc_attr( (string) get_option( 'wa_app_store_url_ios', '' ) ); ?>" placeholder="https://apps.apple.com/gb/app/…">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wa_app_store_url_android">Google Play link</label></th>
                        <td>
                            <input type="url" name="wa_app_store_url_android" id="wa_app_store_url_android" class="regular-text code" value="<?php echo esc_attr( (string) get_option( 'wa_app_store_url_android', '' ) ); ?>" placeholder="https://play.google.com/store/apps/details?id=…">
                            <p class="description">Where the "Time to update" screen sends people. Leave blank until the app is listed; the screen then tells them to update from the store or TestFlight.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save settings' ); ?>
            </form>

            <h2>Status</h2>
            <table class="widefat striped" style="max-width:760px">
                <tbody>
                    <tr>
                        <td style="width:220px">Signed in to the app</td>
                        <td><?php echo esc_html( sprintf( '%d %s (%d %s)', $stats['sessions'], 1 === $stats['sessions'] ? 'session' : 'sessions', $stats['users'], 1 === $stats['users'] ? 'person' : 'people' ) ); ?></td>
                    </tr>
                    <tr>
                        <td>Phones with notifications</td>
                        <td><?php echo esc_html( (string) $stats['devices'] ); ?></td>
                    </tr>
                    <tr>
                        <td>Push queue (last 30 days)</td>
                        <td>
                            <?php
                            $labels = array(
                                'queued'      => 'waiting',
                                'sent'        => 'sent, awaiting receipt',
                                'delivered'   => 'delivered',
                                'failed'      => 'failed',
                                'skipped'     => 'skipped',
                                'unconfirmed' => 'no receipt',
                            );
                            $parts  = array();
                            foreach ( $labels as $status => $text ) {
                                $parts[] = (int) ( $queue[ $status ] ?? 0 ) . ' ' . $text;
                            }
                            echo esc_html( implode( ' · ', $parts ) );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Push sender</td>
                        <td>
                            <?php
                            if ( $next ) {
                                echo esc_html( 'Runs every minute; next run ' . wp_date( $format, (int) $next ) . '.' );
                            } else {
                                echo esc_html( 'off' === $mode ? 'Not scheduled (the app is off).' : 'Not scheduled yet – it will be on the next page load.' );
                            }
                            if ( $last ) {
                                echo ' ' . esc_html( 'Last run ' . wp_date( $format, $last ) . '.' );
                            }
                            if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
                                echo '<br><em>' . esc_html( 'WP-Cron is disabled on this site, so pushes go out when the server cron runs (wp cron event run --due-now).' ) . '</em>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Expo access token</td>
                        <td><?php echo ( defined( 'WA_EXPO_ACCESS_TOKEN' ) && WA_EXPO_ACCESS_TOKEN ) ? 'Set in wp-config.php' : 'Not set (optional &ndash; only needed if push security is switched on in Expo)'; ?></td>
                    </tr>
                </tbody>
            </table>

            <h2>Sign everyone out of the app</h2>
            <p>Ends every app sign-in and forgets every phone registered for notifications. People simply sign in again. Website sign-ins are not affected.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Sign everyone out of the Allstars app?');">
                <input type="hidden" name="action" value="wa_app_revoke_all">
                <?php wp_nonce_field( 'wa_app_revoke_all' ); ?>
                <?php submit_button( 'Revoke all app sessions', 'secondary', 'submit', false ); ?>
            </form>
        </div>
        <?php
    }

    private static function notices() {
        $notice = isset( $_GET['wa_notice'] ) && is_string( $_GET['wa_notice'] ) ? sanitize_key( $_GET['wa_notice'] ) : '';
        if ( 'saved' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
            $bad = isset( $_GET['wa_bad'] ) ? absint( $_GET['wa_bad'] ) : 0;
            if ( $bad ) {
                echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( '%d tester %s didn\'t look like a valid email address and %s left out.', $bad, 1 === $bad ? 'entry' : 'entries', 1 === $bad ? 'was' : 'were' ) ) . '</p></div>';
            }
            if ( ! empty( $_GET['wa_bad_ver'] ) ) {
                echo '<div class="notice notice-warning"><p>The minimum app version must look like 1.0.0, so it was not changed.</p></div>';
            }
            if ( ! empty( $_GET['wa_bad_url'] ) ) {
                echo '<div class="notice notice-warning"><p>Store links must be https://apps.apple.com/… or https://play.google.com/… addresses, so any other link was left as it was.</p></div>';
            }
        } elseif ( 'revoked' === $notice ) {
            $count = isset( $_GET['wa_count'] ) ? absint( $_GET['wa_count'] ) : 0;
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( 'Done: %d app %s ended.', $count, 1 === $count ? 'session' : 'sessions' ) ) . '</p></div>';
        }
    }

    private static function stats(): array {
        global $wpdb;
        $sessions = WA_App_DB::sessions_table();
        $devices  = WA_App_DB::devices_table();
        $now      = wa_app_now();
        return array(
            'sessions' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions} WHERE revoked_at IS NULL AND expires_at > %s", $now ) ),
            'users'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$sessions} WHERE revoked_at IS NULL AND expires_at > %s", $now ) ),
            'devices'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$devices} WHERE enabled = 1" ),
            'queue'    => WA_App_Push::queue_counts(),
        );
    }
}
