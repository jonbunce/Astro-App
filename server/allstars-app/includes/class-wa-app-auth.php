<?php
/**
 * App sign-in tokens.
 *
 * - POST /wa/v1/auth/login swaps email + password for a random token; only its sha256 is stored.
 * - The determine_current_user filter accepts that token ONLY on REST requests for /wa/v1/ and
 *   /was/v1/ routes — never wp-admin, admin-ajax, /wp/v2 or anything else — and a rest_pre_dispatch
 *   guard refuses any other route that somehow arrives with a token user.
 * - Tokens slide: each use (at most every 5 minutes) pushes expiry 180 days out.
 * - All of a user's tokens are revoked on password reset/change, an ASTRO deletion request,
 *   account deletion, and when the user is no longer allowed to use the app.
 */

defined( 'ABSPATH' ) || exit;

class WA_App_Auth {

    const TTL          = 15552000; // 180 days
    const TOUCH_EVERY  = 300;      // seconds between last_used_at writes
    const MAX_FAILURES = 5;
    const FAIL_WINDOW  = 900;      // 15 minutes

    /** @var object|null Session row behind this request's token. */
    private static $session = null;

    /** True when this request sent a token to an app route and it was refused. */
    private static $rejected = false;

    /** Route this request targets, when it is an app REST request. */
    private static $route = '';

    private static $checked = false;

    public static function init() {
        add_filter( 'determine_current_user', array( __CLASS__, 'determine_current_user' ), 20 );
        add_filter( 'rest_authentication_errors', array( __CLASS__, 'authentication_errors' ), 90 );
        add_filter( 'rest_pre_dispatch', array( __CLASS__, 'guard_scope' ), 5, 3 );

        add_action( 'after_password_reset', array( __CLASS__, 'on_password_reset' ), 10, 1 );
        add_action( 'profile_update', array( __CLASS__, 'on_profile_update' ), 10, 2 );
        add_action( 'was_account_deletion_requested', array( __CLASS__, 'on_deletion_requested' ), 10, 1 );
        add_action( 'deleted_user', array( __CLASS__, 'on_deleted_user' ), 10, 1 );
    }

    // ── Who is calling? ──────────────────────────────────────────────────────

    public static function determine_current_user( $user_id ) {
        if ( self::$checked ) {
            return self::$session ? (int) self::$session->user_id : $user_id;
        }
        self::$checked = true;

        if ( 'off' === wa_app_mode() ) {
            return $user_id; // completely inert while the app is switched off
        }
        $token = self::header_token();
        if ( '' === $token || ! self::is_app_rest_request() ) {
            return $user_id;
        }

        $session = self::find_session( $token );
        if ( ! $session ) {
            self::$rejected = true;
            return 0;
        }
        $uid = (int) $session->user_id;
        if ( ! wa_app_user_allowed( $uid ) ) {
            // No longer allowed (removed from testers, account gone…): end every app sign-in they have.
            self::revoke_user( $uid );
            self::$rejected = true;
            return 0;
        }

        self::touch( $session );
        self::$session = $session;
        // A stray WordPress cookie on the same request must not make core demand a REST nonce.
        $GLOBALS['wp_rest_auth_cookie'] = false;
        return $uid;
    }

    /** The current request's session row, if it was authenticated by an app token. */
    public static function current_session() {
        $uid = get_current_user_id(); // makes sure determine_current_user has run
        if ( ! self::$session || $uid !== (int) self::$session->user_id ) {
            return null;
        }
        return self::$session;
    }

    /**
     * A refused token on an app route answers 401 straight away, so the app signs out cleanly.
     * Not while the app is off (routes then say "not open yet"), and never for health or login.
     */
    public static function authentication_errors( $result ) {
        if ( ! empty( $result ) ) {
            return $result;
        }
        wp_get_current_user();
        if ( self::$rejected && 'off' !== wa_app_mode() && ! in_array( rtrim( self::$route, '/' ), array( '/wa/v1/health', '/wa/v1/auth/login' ), true ) ) {
            return wa_app_error( 'wa_signed_out', 'You have been signed out. Please sign in again.', 401 );
        }
        return $result;
    }

    /** Belt and braces: a token user can only ever reach wa/v1 and was/v1 routes. */
    public static function guard_scope( $result, $server, $request ) {
        if ( self::$session && $request instanceof WP_REST_Request && ! self::is_app_route( (string) $request->get_route() ) ) {
            return wa_app_error( 'wa_token_scope', 'This sign-in only works in the Allstars app.', 403 );
        }
        return $result;
    }

    /** Bearer token from Authorization (or Apache's REDIRECT_ copy), else X-WA-Token. */
    public static function header_token(): string {
        foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $key ) {
            if ( isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] )
                && preg_match( '/^\s*Bearer\s+([A-Za-z0-9_\-]+)\s*$/i', $_SERVER[ $key ], $m ) ) {
                return $m[1];
            }
        }
        if ( isset( $_SERVER['HTTP_X_WA_TOKEN'] ) && is_string( $_SERVER['HTTP_X_WA_TOKEN'] ) ) {
            return trim( $_SERVER['HTTP_X_WA_TOKEN'] );
        }
        return '';
    }

    /**
     * Is this a REST request for a wa/v1 or was/v1 route? Every route indicator present
     * (?rest_route= in GET or POST, and a /wp-json/ path) must point at an app route, because
     * WordPress lets ?rest_route= override the path.
     */
    public static function is_app_rest_request(): bool {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron()
            || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
            || ( defined( 'WP_CLI' ) && WP_CLI )
            || ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) ) {
            return false;
        }
        $route = '';
        foreach ( array( $_GET, $_POST ) as $source ) {
            if ( ! isset( $source['rest_route'] ) ) {
                continue;
            }
            if ( ! is_string( $source['rest_route'] ) || ! self::is_app_route( $source['rest_route'] ) ) {
                return false;
            }
            $route = (string) wp_unslash( $source['rest_route'] );
        }
        $from_path = self::route_from_path();
        if ( null !== $from_path ) {
            if ( ! self::is_app_route( $from_path ) ) {
                return false;
            }
            if ( '' === $route ) {
                $route = $from_path;
            }
        }
        if ( '' === $route ) {
            return false;
        }
        self::$route = strtolower( '/' . ltrim( $route, '/' ) );
        return true;
    }

    /** The REST route in the request path ("/wp-json/wa/v1/x" → "/wa/v1/x"), or null if it isn't a REST path. */
    private static function route_from_path(): ?string {
        $uri  = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $path = rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
        $home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        $base = rtrim( $home, '/' ) . '/' . trim( rest_get_url_prefix(), '/' ) . '/';
        if ( 0 !== strpos( $path, $base ) ) {
            return null;
        }
        return '/' . substr( $path, strlen( $base ) );
    }

    public static function is_app_route( string $route ): bool {
        $route = strtolower( '/' . ltrim( (string) wp_unslash( $route ), '/' ) );
        if ( false !== strpos( $route, '..' ) || false !== strpos( $route, "\0" ) ) {
            return false;
        }
        return 0 === strpos( $route, '/wa/v1/' ) || 0 === strpos( $route, '/was/v1/' );
    }

    /** Live session row for a raw token, or null (unknown, revoked or expired). */
    private static function find_session( string $token ) {
        if ( ! wa_app_token_looks_valid( $token ) ) {
            return null;
        }
        global $wpdb;
        $hash  = hash( 'sha256', $token );
        $table = WA_App_DB::sessions_table();
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s LIMIT 1", $hash ) );
        if ( ! $row || ! hash_equals( (string) $row->token_hash, $hash ) ) {
            return null;
        }
        if ( null !== $row->revoked_at || wa_app_ts_from_gmt( $row->expires_at ) <= time() ) {
            return null;
        }
        return $row;
    }

    /** Sliding expiry, written at most every TOUCH_EVERY seconds. */
    private static function touch( $session ) {
        $now = time();
        if ( $now - wa_app_ts_from_gmt( $session->last_used_at ) < self::TOUCH_EVERY ) {
            return;
        }
        global $wpdb;
        $session->last_used_at = wa_app_gmt( $now );
        $session->expires_at   = wa_app_gmt( $now + self::TTL );
        $wpdb->update(
            WA_App_DB::sessions_table(),
            array( 'last_used_at' => $session->last_used_at, 'expires_at' => $session->expires_at ),
            array( 'id' => (int) $session->id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    /** Is this session still live and owned by this user? (Used by the website handoff.) */
    public static function session_is_active( int $session_id, int $user_id ): bool {
        global $wpdb;
        $table = WA_App_DB::sessions_table();
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT user_id, expires_at, revoked_at FROM {$table} WHERE id = %d", $session_id ) );
        return $row && (int) $row->user_id === $user_id && null === $row->revoked_at && wa_app_ts_from_gmt( $row->expires_at ) > time();
    }

    // ── Routes ───────────────────────────────────────────────────────────────

    /** POST /auth/login { email, password, device?, platform?, app_version? } */
    public static function rest_login( WP_REST_Request $request ) {
        $email    = $request->get_param( 'email' );
        $password = $request->get_param( 'password' );
        $email    = is_string( $email ) ? strtolower( trim( sanitize_text_field( $email ) ) ) : '';
        $password = is_string( $password ) ? $password : '';
        if ( '' === $email || '' === $password ) {
            return wa_app_error( 'wa_login_missing', 'Please enter your email address and password.', 400 );
        }

        // Brute-force brake: 5 wrong attempts for this email from this IP address within 15 minutes.
        $key      = 'wa_login_fail_' . md5( self::client_ip() . '|' . $email );
        $failures = get_transient( $key );
        if ( is_array( $failures ) && (int) ( $failures['count'] ?? 0 ) >= self::MAX_FAILURES ) {
            return wa_app_error( 'wa_login_throttled', 'Too many unsuccessful sign-in attempts. Please wait 15 minutes and try again, or reset your password on the club website.', 429 );
        }

        // Look the account up by email, then check the password against its login name.
        // wp_authenticate() runs the normal checks (and sg-security's limiter) but does not fire
        // wp_login, which sg-security's two-factor step reacts to.
        $user  = get_user_by( 'email', $email );
        $login = $user ? $user->user_login : $email;
        $auth  = wp_authenticate( $login, $password );
        if ( ! ( $auth instanceof WP_User ) ) {
            self::record_failure( $key, $failures );
            return wa_app_error( 'wa_login_failed', 'Email address or password is incorrect. Please try again.', 401 );
        }
        delete_transient( $key );

        $uid = (int) $auth->ID;
        if ( ! wa_app_user_allowed( $uid ) ) {
            return wa_app_error( 'wa_app_not_allowed', "This account can't use the Allstars app yet. If you think it should, please contact the club.", 403 );
        }

        global $wpdb;
        $token = wa_app_random_token();
        $now   = time();
        $saved = $wpdb->insert(
            WA_App_DB::sessions_table(),
            array(
                'user_id'      => $uid,
                'token_hash'   => hash( 'sha256', $token ),
                'device'       => self::clean_device( $request->get_param( 'device' ) ),
                'platform'     => self::clean_platform( $request->get_param( 'platform' ) ),
                'app_version'  => self::clean_version( $request->get_param( 'app_version' ) ),
                'created_at'   => wa_app_gmt( $now ),
                'last_used_at' => wa_app_gmt( $now ),
                'expires_at'   => wa_app_gmt( $now + self::TTL ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        if ( ! $saved ) {
            return wa_app_error( 'wa_login_error', 'Something went wrong signing you in. Please try again.', 500 );
        }

        return rest_ensure_response( array(
            'token' => $token,
            'user'  => self::user_payload( $auth ),
        ) );
    }

    /** POST /auth/logout — ends this sign-in and forgets its device. */
    public static function rest_logout( WP_REST_Request $request ) {
        $session = self::current_session();
        if ( $session ) {
            self::revoke_session( (int) $session->id );
        }
        return rest_ensure_response( array( 'ok' => true ) );
    }

    /** GET /auth/sessions — this account's signed-in devices. */
    public static function rest_sessions( WP_REST_Request $request ) {
        global $wpdb;
        $table   = WA_App_DB::sessions_table();
        $session = self::current_session();
        $current = $session ? (int) $session->id : 0;
        $rows    = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, device, platform, created_at, last_used_at FROM {$table}
             WHERE user_id = %d AND revoked_at IS NULL AND expires_at > %s
             ORDER BY last_used_at DESC LIMIT 50",
            get_current_user_id(),
            wa_app_now()
        ) );
        $out = array();
        foreach ( (array) $rows as $row ) {
            $out[] = array(
                'id'         => (int) $row->id,
                'device'     => '' !== (string) $row->device ? (string) $row->device : 'Phone',
                'platform'   => (string) $row->platform,
                'createdAt'  => wa_app_iso_from_gmt( $row->created_at ),
                'lastUsedAt' => wa_app_iso_from_gmt( $row->last_used_at ),
                'current'    => (int) $row->id === $current,
            );
        }
        return rest_ensure_response( $out );
    }

    /** DELETE /auth/sessions/{id} — sign out one of your own devices. */
    public static function rest_delete_session( WP_REST_Request $request ) {
        global $wpdb;
        $id    = (int) $request['id'];
        $table = WA_App_DB::sessions_table();
        $owner = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE id = %d AND revoked_at IS NULL", $id ) );
        if ( null === $owner || (int) $owner !== get_current_user_id() ) {
            return wa_app_error( 'wa_session_not_found', 'That device is no longer signed in.', 404 );
        }
        self::revoke_session( $id );
        return rest_ensure_response( array( 'ok' => true ) );
    }

    // ── Revocation ───────────────────────────────────────────────────────────

    public static function revoke_session( int $session_id ) {
        global $wpdb;
        $sessions = WA_App_DB::sessions_table();
        $devices  = WA_App_DB::devices_table();
        $wpdb->query( $wpdb->prepare( "UPDATE {$sessions} SET revoked_at = %s WHERE id = %d AND revoked_at IS NULL", wa_app_now(), $session_id ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$devices} WHERE session_id = %d", $session_id ) );
    }

    /** Ends every app sign-in for a user and forgets their devices. Returns sessions revoked. */
    public static function revoke_user( $user_id ): int {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return 0;
        }
        global $wpdb;
        $sessions = WA_App_DB::sessions_table();
        $devices  = WA_App_DB::devices_table();
        $count    = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$sessions} SET revoked_at = %s WHERE user_id = %d AND revoked_at IS NULL", wa_app_now(), $user_id ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$devices} WHERE user_id = %d", $user_id ) );
        if ( self::$session && (int) self::$session->user_id === $user_id ) {
            self::$session = null;
        }
        return $count;
    }

    /** Admin "sign everyone out". Returns sessions revoked. */
    public static function revoke_all(): int {
        global $wpdb;
        $sessions = WA_App_DB::sessions_table();
        $devices  = WA_App_DB::devices_table();
        $count    = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$sessions} SET revoked_at = %s WHERE revoked_at IS NULL", wa_app_now() ) );
        $wpdb->query( "DELETE FROM {$devices}" );
        return $count;
    }

    public static function on_password_reset( $user ) {
        if ( $user instanceof WP_User ) {
            self::revoke_user( $user->ID );
        }
    }

    /** profile_update: revoke only when the stored password hash actually changed. */
    public static function on_profile_update( $user_id, $old_user_data = null ) {
        if ( ! is_object( $old_user_data ) || ! isset( $old_user_data->user_pass ) ) {
            return;
        }
        global $wpdb;
        $now_pass = $wpdb->get_var( $wpdb->prepare( "SELECT user_pass FROM {$wpdb->users} WHERE ID = %d", (int) $user_id ) );
        if ( null !== $now_pass && ! hash_equals( (string) $old_user_data->user_pass, (string) $now_pass ) ) {
            self::revoke_user( (int) $user_id );
        }
    }

    public static function on_deletion_requested( $user_id ) {
        self::revoke_user( (int) $user_id );
    }

    /** Account deleted: nothing of theirs is kept here. */
    public static function on_deleted_user( $user_id ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return;
        }
        global $wpdb;
        $wpdb->delete( WA_App_DB::sessions_table(), array( 'user_id' => $user_id ), array( '%d' ) );
        $wpdb->delete( WA_App_DB::devices_table(), array( 'user_id' => $user_id ), array( '%d' ) );
        $wpdb->delete( WA_App_DB::queue_table(), array( 'user_id' => $user_id ), array( '%d' ) );
    }

    /** Housekeeping: drop sessions that ended before the cutoff (UTC MySQL datetime). */
    public static function purge( string $cutoff ) {
        global $wpdb;
        $table = WA_App_DB::sessions_table();
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE ( revoked_at IS NOT NULL AND revoked_at < %s ) OR expires_at < %s",
            $cutoff,
            $cutoff
        ) );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function record_failure( string $key, $failures ) {
        if ( ! is_array( $failures ) ) {
            $failures = array( 'count' => 0, 'start' => time() );
        }
        $failures['count'] = (int) ( $failures['count'] ?? 0 ) + 1;
        // Keep the original 15-minute window rather than extending it on every attempt.
        $ttl = (int) ( $failures['start'] ?? time() ) + self::FAIL_WINDOW - time();
        set_transient( $key, $failures, max( MINUTE_IN_SECONDS, $ttl ) );
    }

    private static function client_ip(): string {
        return isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    public static function user_payload( WP_User $user ): array {
        return array(
            'id'        => (int) $user->ID,
            'firstName' => (string) get_user_meta( $user->ID, 'first_name', true ),
            'lastName'  => (string) get_user_meta( $user->ID, 'last_name', true ),
            'email'     => (string) $user->user_email,
        );
    }

    public static function clean_device( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }
        return mb_substr( trim( sanitize_text_field( $value ) ), 0, 100 );
    }

    public static function clean_platform( $value ): string {
        $value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
        return in_array( $value, array( 'ios', 'android' ), true ) ? $value : '';
    }

    private static function clean_version( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }
        return substr( (string) preg_replace( '/[^0-9A-Za-z.+\-]/', '', $value ), 0, 20 );
    }
}
