<?php
/**
 * Devices and push notifications (Expo Push API).
 *
 * - POST/DELETE /wa/v1/devices register an Expo push token against the current app sign-in.
 * - ASTRO's was_message_delivered / was_team_news_published hooks queue one row per enabled
 *   device of each allowed user, with GENERIC text only (never names, medical or money).
 * - A WP-Cron event every minute (custom 'wa_every_minute' schedule, only scheduled while the app
 *   isn't off) sends queued rows to Expo in batches of 100, stores ticket IDs, retries failed sends
 *   up to 3 times, checks receipts after 15 minutes, switches off DeviceNotRegistered devices and
 *   purges rows older than 30 days.
 */

defined( 'ABSPATH' ) || exit;

class WA_App_Push {

    const CRON_HOOK    = 'wa_app_push_cron';
    const SCHEDULE     = 'wa_every_minute';
    const SEND_URL     = 'https://exp.host/--/api/v2/push/send';
    const RECEIPT_URL  = 'https://exp.host/--/api/v2/push/getReceipts';
    const BATCH        = 100;
    const MAX_BATCHES  = 10;    // per cron run
    const MAX_ATTEMPTS = 3;
    const KEEP_DAYS    = 30;
    const LOCK         = 'wa_app_push_lock';

    public static function init() {
        add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
        add_action( 'init', array( __CLASS__, 'sync_schedule' ) );
        add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );

        add_action( 'was_message_delivered', array( __CLASS__, 'on_message_delivered' ), 10, 3 );
        add_action( 'was_team_news_published', array( __CLASS__, 'on_team_news_published' ), 10, 4 );
    }

    // ── Schedule ─────────────────────────────────────────────────────────────

    public static function cron_schedules( $schedules ) {
        if ( ! is_array( $schedules ) ) {
            $schedules = array();
        }
        $schedules[ self::SCHEDULE ] = array(
            'interval' => MINUTE_IN_SECONDS,
            'display'  => 'Every minute (Allstars App push)',
        );
        return $schedules;
    }

    /** The sender only exists while the app is testers/on. */
    public static function sync_schedule() {
        $next = wp_next_scheduled( self::CRON_HOOK );
        if ( 'off' === wa_app_mode() ) {
            if ( $next ) {
                wp_clear_scheduled_hook( self::CRON_HOOK );
            }
            return;
        }
        if ( ! $next ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::CRON_HOOK );
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    // ── Devices ──────────────────────────────────────────────────────────────

    /** ExponentPushToken[…] or ExpoPushToken[…], else ''. */
    public static function clean_expo_token( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }
        $value = trim( $value );
        if ( strlen( $value ) > 255 || ! preg_match( '/^Expo(?:nent)?PushToken\[[^\[\]\s]{1,200}\]$/', $value ) ) {
            return '';
        }
        return $value;
    }

    /** POST /devices { expoPushToken, platform } — one device per app sign-in. */
    public static function rest_register_device( WP_REST_Request $request ) {
        $token = self::clean_expo_token( $request->get_param( 'expoPushToken' ) );
        if ( '' === $token ) {
            return wa_app_error( 'wa_device_token', "This phone couldn't be set up for notifications. Please try again later.", 400 );
        }
        $session = WA_App_Auth::current_session();
        if ( ! $session ) {
            return wa_app_error( 'wa_not_signed_in', 'Please sign in again.', 401 );
        }

        global $wpdb;
        $table = WA_App_DB::devices_table();
        $now   = wa_app_now();
        // The same phone may have belonged to another sign-in (or another family) before: it moves here.
        $saved = $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table} (session_id, user_id, expo_push_token, platform, enabled, created_at, updated_at)
             VALUES (%d, %d, %s, %s, 1, %s, %s)
             ON DUPLICATE KEY UPDATE session_id = VALUES(session_id), user_id = VALUES(user_id),
               platform = VALUES(platform), enabled = 1, updated_at = VALUES(updated_at)",
            (int) $session->id,
            get_current_user_id(),
            $token,
            WA_App_Auth::clean_platform( $request->get_param( 'platform' ) ),
            $now,
            $now
        ) );
        if ( false === $saved ) {
            return wa_app_error( 'wa_device_error', "Notifications couldn't be switched on. Please try again later.", 500 );
        }
        // A sign-in has one phone: drop any older token it registered.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE session_id = %d AND expo_push_token <> %s",
            (int) $session->id,
            $token
        ) );
        return rest_ensure_response( array( 'ok' => true ) );
    }

    /** DELETE /devices { expoPushToken } — own devices only; idempotent. */
    public static function rest_unregister_device( WP_REST_Request $request ) {
        $token = self::clean_expo_token( $request->get_param( 'expoPushToken' ) );
        if ( '' !== $token ) {
            global $wpdb;
            $wpdb->delete(
                WA_App_DB::devices_table(),
                array( 'expo_push_token' => $token, 'user_id' => get_current_user_id() ),
                array( '%s', '%d' )
            );
        }
        return rest_ensure_response( array( 'ok' => true ) );
    }

    // ── ASTRO events ─────────────────────────────────────────────────────────

    public static function on_message_delivered( $message_id, $user_ids = array(), $payload = array() ) {
        if ( 'off' === wa_app_mode() ) {
            return;
        }
        self::queue( (array) $user_ids, 'Wombourne Allstars', 'New message from the club', array(
            'type'   => 'club_message',
            'ref_id' => (int) $message_id,
        ) );
    }

    public static function on_team_news_published( $post_id, $team_id = 0, $user_ids = array(), $payload = array() ) {
        if ( 'off' === wa_app_mode() ) {
            return;
        }
        $team = self::team_name( is_array( $payload ) ? $payload : array(), (int) $team_id );
        self::queue( (array) $user_ids, 'Wombourne Allstars', '' !== $team ? 'New team news for ' . $team : 'New team news', array(
            'type'   => 'team_news',
            'ref_id' => (int) $post_id,
        ) );
    }

    /** Team name from the payload title ("New U9 Tigers team news"), else from ASTRO's team row. */
    private static function team_name( array $payload, int $team_id ): string {
        $title = isset( $payload['title'] ) && is_string( $payload['title'] )
            ? wp_strip_all_tags( html_entity_decode( $payload['title'], ENT_QUOTES, 'UTF-8' ) )
            : '';
        $name = '';
        if ( preg_match( '/^\s*New\s+(.+?)\s+team\s+news\s*$/iu', $title, $m ) ) {
            $name = $m[1];
        } elseif ( $team_id > 0 ) {
            $name = WA_App_Content::team_label( $team_id );
        }
        $name = trim( (string) preg_replace( '/\s+/u', ' ', $name ) );
        return mb_substr( $name, 0, 60 );
    }

    // ── Queue ────────────────────────────────────────────────────────────────

    /** See wa_queue_push(). */
    public static function queue( array $user_ids, string $title, string $body, array $data = array() ): int {
        if ( 'off' === wa_app_mode() ) {
            return 0;
        }
        $ids = array();
        foreach ( $user_ids as $id ) {
            $id = is_scalar( $id ) ? (int) $id : 0;
            if ( $id > 0 ) {
                $ids[ $id ] = $id;
            }
        }
        if ( ! $ids ) {
            return 0;
        }
        $ids = array_values( $ids );
        if ( function_exists( 'cache_users' ) ) {
            cache_users( $ids );
        }
        $ids = array_values( array_filter( $ids, 'wa_app_user_allowed' ) );
        if ( ! $ids ) {
            return 0;
        }

        global $wpdb;
        $devices  = WA_App_DB::devices_table();
        $sessions = WA_App_DB::sessions_table();
        $queue    = WA_App_DB::queue_table();
        $title    = self::clip( wp_strip_all_tags( $title ), 120 );
        $body     = self::clip( wp_strip_all_tags( $body ), 178 );
        $json     = wp_json_encode( (object) $data );
        $json     = is_string( $json ) ? $json : '{}';
        $now      = wa_app_now();
        $count    = 0;

        foreach ( array_chunk( $ids, 200 ) as $chunk ) {
            $in   = implode( ',', array_map( 'intval', $chunk ) );
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT d.id, d.user_id FROM {$devices} d
                 INNER JOIN {$sessions} s ON s.id = d.session_id
                 WHERE d.enabled = 1 AND d.user_id IN ({$in}) AND d.user_id = s.user_id
                   AND s.revoked_at IS NULL AND s.expires_at > %s",
                $now
            ) );
            foreach ( array_chunk( (array) $rows, 100 ) as $part ) {
                $places = array();
                $args   = array();
                foreach ( $part as $device ) {
                    $places[] = '(%d, %d, %s, %s, %s, %s, %d, %s, %s, %s)';
                    array_push( $args, (int) $device->user_id, (int) $device->id, $title, $body, $json, 'queued', 0, '', '', $now );
                }
                $sql = "INSERT INTO {$queue} (user_id, device_id, title, body, data, status, attempts, expo_ticket_id, error, created_at) VALUES "
                    . implode( ', ', $places );
                $done = $wpdb->query( $wpdb->prepare( $sql, $args ) );
                if ( $done ) {
                    $count += (int) $done;
                }
            }
        }
        return $count;
    }

    // ── Sender (cron) ────────────────────────────────────────────────────────

    public static function run() {
        if ( 'off' === wa_app_mode() ) {
            return; // nothing is ever sent while the app is off
        }
        if ( ! self::lock() ) {
            return;
        }
        try {
            $started = time();
            self::expire_stale();
            for ( $i = 0; $i < self::MAX_BATCHES && time() - $started < 25; $i++ ) {
                if ( ! self::send_batch() ) {
                    break;
                }
            }
            self::check_receipts();
            self::maybe_purge();
            update_option( 'wa_app_push_last_run', time(), false );
        } finally {
            self::unlock();
        }
    }

    /** A push about something from yesterday is just noise. */
    private static function expire_stale() {
        global $wpdb;
        $queue = WA_App_DB::queue_table();
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$queue} SET status = 'skipped', error = %s WHERE status = 'queued' AND created_at < %s",
            'Too old to send',
            wa_app_gmt( time() - DAY_IN_SECONDS )
        ) );
    }

    /** Sends up to BATCH queued rows. Returns true when it is worth trying another batch now. */
    private static function send_batch(): bool {
        global $wpdb;
        $queue    = WA_App_DB::queue_table();
        $devices  = WA_App_DB::devices_table();
        $sessions = WA_App_DB::sessions_table();
        $now_ts   = time();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT q.id, q.user_id, q.device_id, q.title, q.body, q.data, q.attempts,
                    d.expo_push_token, d.enabled, d.user_id AS device_user_id,
                    s.revoked_at AS session_revoked_at, s.expires_at AS session_expires_at
             FROM {$queue} q
             LEFT JOIN {$devices} d ON d.id = q.device_id
             LEFT JOIN {$sessions} s ON s.id = d.session_id
             WHERE q.status = 'queued'
             ORDER BY q.id ASC
             LIMIT %d",
            self::BATCH
        ) );
        if ( ! $rows ) {
            return false;
        }

        $send     = array();
        $messages = array();
        foreach ( $rows as $row ) {
            $uid = (int) $row->user_id;
            // The phone must still be signed in to the same (still allowed) account.
            $live = ! empty( $row->expo_push_token )
                && (int) $row->enabled === 1
                && (int) $row->device_user_id === $uid
                && null === $row->session_revoked_at
                && wa_app_ts_from_gmt( $row->session_expires_at ) > $now_ts
                && wa_app_user_allowed( $uid );
            if ( ! $live ) {
                self::mark( (int) $row->id, 'skipped', 'Device or account no longer signed in', false );
                continue;
            }
            $data    = json_decode( (string) $row->data, true );
            $message = array(
                'to'       => (string) $row->expo_push_token,
                'title'    => (string) $row->title,
                'body'     => (string) $row->body,
                'data'     => (object) ( is_array( $data ) ? $data : array() ),
                'sound'    => 'default',
                'priority' => 'high',
            );
            /** Adjust an outgoing Expo message (e.g. add an Android channelId). */
            $messages[] = apply_filters( 'wa_app_push_message', $message, $row );
            $send[]     = $row;
        }
        if ( ! $send ) {
            return count( $rows ) >= self::BATCH;
        }

        $response = wp_remote_post( self::SEND_URL, array(
            'timeout'     => 20,
            'headers'     => self::headers(),
            'body'        => wp_json_encode( $messages ),
            'data_format' => 'body',
        ) );
        $code    = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        $json    = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );
        $tickets = ( is_array( $json ) && isset( $json['data'] ) && is_array( $json['data'] ) ) ? array_values( $json['data'] ) : null;

        if ( 200 !== $code || null === $tickets || count( $tickets ) !== count( $send ) ) {
            $why = is_wp_error( $response ) ? $response->get_error_message() : 'Expo answered HTTP ' . $code;
            if ( is_array( $json ) && isset( $json['errors'][0]['message'] ) && is_string( $json['errors'][0]['message'] ) ) {
                $why .= ': ' . $json['errors'][0]['message'];
            }
            foreach ( $send as $row ) {
                self::retry_or_fail( $row, $why );
            }
            return false; // try again next minute
        }

        $now     = wa_app_now();
        $backoff = false;
        foreach ( $send as $i => $row ) {
            $ticket = is_array( $tickets[ $i ] ) ? $tickets[ $i ] : array();
            if ( 'ok' === ( $ticket['status'] ?? '' ) && isset( $ticket['id'] ) && is_string( $ticket['id'] ) && '' !== $ticket['id'] ) {
                $wpdb->update(
                    $queue,
                    array(
                        'status'         => 'sent',
                        'expo_ticket_id' => substr( $ticket['id'], 0, 100 ),
                        'attempts'       => (int) $row->attempts + 1,
                        'error'          => '',
                        'sent_at'        => $now,
                    ),
                    array( 'id' => (int) $row->id ),
                    array( '%s', '%s', '%d', '%s', '%s' ),
                    array( '%d' )
                );
                continue;
            }
            $error   = self::str( $ticket['details']['error'] ?? '' );
            $message = self::str( $ticket['message'] ?? '' );
            if ( 'DeviceNotRegistered' === $error ) {
                self::disable_device( (int) $row->device_id );
                self::mark( (int) $row->id, 'failed', 'DeviceNotRegistered', true );
            } elseif ( 'MessageRateExceeded' === $error ) {
                self::retry_or_fail( $row, 'MessageRateExceeded' );
                $backoff = true;
            } else {
                self::mark( (int) $row->id, 'failed', trim( $error . ' ' . ( '' !== $message ? $message : 'Expo refused the message' ) ), true );
            }
        }
        return ! $backoff && count( $rows ) >= self::BATCH;
    }

    /** Receipts for tickets older than 15 minutes; DeviceNotRegistered switches the device off. */
    private static function check_receipts() {
        global $wpdb;
        $queue = WA_App_DB::queue_table();
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, device_id, expo_ticket_id, sent_at FROM {$queue}
             WHERE status = 'sent' AND expo_ticket_id <> '' AND sent_at < %s
             ORDER BY id ASC LIMIT 1000",
            wa_app_gmt( time() - 15 * MINUTE_IN_SECONDS )
        ) );
        if ( ! $rows ) {
            return;
        }
        $ids = array();
        foreach ( $rows as $row ) {
            $ids[] = (string) $row->expo_ticket_id;
        }
        $response = wp_remote_post( self::RECEIPT_URL, array(
            'timeout'     => 20,
            'headers'     => self::headers(),
            'body'        => wp_json_encode( array( 'ids' => $ids ) ),
            'data_format' => 'body',
        ) );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return; // try again next run
        }
        $json     = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        $receipts = ( is_array( $json ) && isset( $json['data'] ) && is_array( $json['data'] ) ) ? $json['data'] : array();
        $give_up  = time() - DAY_IN_SECONDS; // Expo keeps receipts for about a day
        $ok       = array();
        foreach ( $rows as $row ) {
            $receipt = $receipts[ (string) $row->expo_ticket_id ] ?? null;
            if ( is_array( $receipt ) ) {
                if ( 'ok' === ( $receipt['status'] ?? '' ) ) {
                    $ok[] = (int) $row->id;
                    continue;
                }
                $error = self::str( $receipt['details']['error'] ?? '' );
                if ( 'DeviceNotRegistered' === $error ) {
                    self::disable_device( (int) $row->device_id );
                }
                self::mark( (int) $row->id, 'failed', trim( $error . ' ' . self::str( $receipt['message'] ?? '' ) ), false );
            } elseif ( wa_app_ts_from_gmt( $row->sent_at ) < $give_up ) {
                self::mark( (int) $row->id, 'unconfirmed', 'No receipt from Expo', false );
            }
        }
        if ( $ok ) {
            $wpdb->query( "UPDATE {$queue} SET status = 'delivered' WHERE id IN (" . implode( ',', array_map( 'intval', $ok ) ) . ')' );
        }
    }

    /** Once a day: queue rows, switched-off devices and ended sign-ins older than 30 days. */
    private static function maybe_purge() {
        if ( (int) get_option( 'wa_app_last_purge', 0 ) > time() - DAY_IN_SECONDS ) {
            return;
        }
        update_option( 'wa_app_last_purge', time(), false );
        global $wpdb;
        $cutoff  = wa_app_gmt( time() - self::KEEP_DAYS * DAY_IN_SECONDS );
        $queue   = WA_App_DB::queue_table();
        $devices = WA_App_DB::devices_table();
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$queue} WHERE created_at < %s", $cutoff ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$devices} WHERE enabled = 0 AND updated_at < %s", $cutoff ) );
        WA_App_Auth::purge( $cutoff );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function headers(): array {
        $headers = array(
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        );
        if ( defined( 'WA_EXPO_ACCESS_TOKEN' ) && is_string( WA_EXPO_ACCESS_TOKEN ) && '' !== WA_EXPO_ACCESS_TOKEN ) {
            $headers['Authorization'] = 'Bearer ' . WA_EXPO_ACCESS_TOKEN;
        }
        return $headers;
    }

    private static function retry_or_fail( $row, string $why ) {
        global $wpdb;
        $attempts = (int) $row->attempts + 1;
        $wpdb->update(
            WA_App_DB::queue_table(),
            array(
                'status'   => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'queued',
                'attempts' => $attempts,
                'error'    => self::clip( $why, 250 ),
            ),
            array( 'id' => (int) $row->id ),
            array( '%s', '%d', '%s' ),
            array( '%d' )
        );
    }

    private static function mark( int $id, string $status, string $error, bool $count_attempt ) {
        global $wpdb;
        $queue = WA_App_DB::queue_table();
        $extra = $count_attempt ? ', attempts = attempts + 1' : '';
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$queue} SET status = %s, error = %s{$extra} WHERE id = %d",
            $status,
            self::clip( $error, 250 ),
            $id
        ) );
    }

    private static function disable_device( int $device_id ) {
        if ( $device_id <= 0 ) {
            return;
        }
        global $wpdb;
        $devices = WA_App_DB::devices_table();
        $wpdb->query( $wpdb->prepare( "UPDATE {$devices} SET enabled = 0, updated_at = %s WHERE id = %d", wa_app_now(), $device_id ) );
    }

    /**
     * Single-runner lock in the options table. INSERT IGNORE is atomic on the unique
     * option_name (the same trick core's upgrader lock uses); a lock older than 5 minutes is stale.
     */
    private static function lock(): bool {
        global $wpdb;
        $now = time();
        $got = $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            self::LOCK,
            (string) $now
        ) );
        if ( $got ) {
            return true;
        }
        $held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) );
        if ( null === $held || (int) $held > $now - 5 * MINUTE_IN_SECONDS ) {
            return false;
        }
        return (bool) $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
            (string) $now,
            self::LOCK,
            (string) $held
        ) );
    }

    private static function unlock() {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) );
    }

    private static function clip( string $text, int $max ): string {
        return mb_substr( trim( $text ), 0, $max );
    }

    private static function str( $value ): string {
        return is_scalar( $value ) ? (string) $value : '';
    }

    // ── Admin status ─────────────────────────────────────────────────────────

    /** Queue rows by status, e.g. [ 'queued' => 3, 'delivered' => 120 ]. */
    public static function queue_counts(): array {
        global $wpdb;
        $queue = WA_App_DB::queue_table();
        $out   = array();
        foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$queue} GROUP BY status" ) as $row ) {
            $out[ (string) $row->status ] = (int) $row->c;
        }
        return $out;
    }
}
