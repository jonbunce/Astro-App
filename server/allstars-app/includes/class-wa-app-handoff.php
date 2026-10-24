<?php
/**
 * Website handoff: open an ASTRO page from the app, already signed in.
 *
 * 1. The app calls POST /wa/v1/auth/handoff { path } with its token and gets back a single-use
 *    admin-ajax.php?action=wa_handoff&t=… link, valid for 60 seconds (admin-ajax is never page-cached).
 * 2. Opening it signs the browser in (wp_set_auth_cookie, session cookie only), sets the session
 *    cookie wa_app=1 and redirects to the path.
 * 3. While wa_app=1 is set on a signed-in page, the site hides the Flatsome header/footer and shows
 *    a "Back to the app" button (wombourneallstars://done). Logged-out pages are never changed, so
 *    they stay cacheable.
 */

defined( 'ABSPATH' ) || exit;

class WA_App_Handoff {

    const TTL        = 60;
    const COOKIE     = 'wa_app';
    const RETURN_URL = 'wombourneallstars://done';

    public static function init() {
        add_action( 'wp_ajax_nopriv_wa_handoff', array( __CLASS__, 'handle' ) );
        add_action( 'wp_ajax_wa_handoff', array( __CLASS__, 'handle' ) );

        add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
        add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar' ), 100 );
        add_action( 'wp_head', array( __CLASS__, 'print_styles' ), 100 );
        add_action( 'wp_footer', array( __CLASS__, 'print_button' ), 100 );
    }

    /** Pages the app may open signed in. */
    public static function allowed_prefixes(): array {
        return (array) apply_filters( 'wa_app_handoff_prefixes', array( '/account-portal', '/registration', '/registrations-unified' ) );
    }

    /**
     * A site-relative path under one of the allowed prefixes, or null. No scheme, no host,
     * no "..", no backslashes, no encoded dots/slashes, nothing outside a plain URL alphabet.
     */
    public static function clean_path( $path ): ?string {
        if ( ! is_string( $path ) ) {
            return null;
        }
        $path = trim( $path );
        if ( '' === $path || strlen( $path ) > 500 || '/' !== $path[0] || 0 === strpos( $path, '//' ) ) {
            return null;
        }
        if ( false !== strpos( $path, '..' ) || false !== strpos( $path, '\\' ) || preg_match( '/%(2e|2f|5c|00)/i', $path ) ) {
            return null;
        }
        if ( ! preg_match( '@^/[A-Za-z0-9\-._~/?#&=%+,]*$@', $path ) ) {
            return null;
        }
        $ok = false;
        foreach ( self::allowed_prefixes() as $prefix ) {
            $prefix = '/' . trim( (string) $prefix, '/' );
            if ( '/' === $prefix ) {
                continue;
            }
            if ( $path === $prefix || preg_match( '@^' . preg_quote( $prefix, '@' ) . '[/?#]@', $path ) ) {
                $ok = true;
                break;
            }
        }
        if ( ! $ok ) {
            return null;
        }
        $parts = wp_parse_url( $path );
        if ( ! is_array( $parts ) || isset( $parts['scheme'] ) || isset( $parts['host'] ) ) {
            return null;
        }
        return $path;
    }

    private static function key( string $token ): string {
        return 'wa_handoff_' . hash( 'sha256', $token );
    }

    /** POST /auth/handoff { path } → { url } */
    public static function rest_mint( WP_REST_Request $request ) {
        $path = self::clean_path( $request->get_param( 'path' ) );
        if ( null === $path ) {
            return wa_app_error( 'wa_handoff_path', "That page can't be opened from the app.", 400 );
        }
        $session = WA_App_Auth::current_session();
        $token   = wa_app_random_token();
        set_transient( self::key( $token ), array(
            'user_id'    => get_current_user_id(),
            'session_id' => $session ? (int) $session->id : 0,
            'path'       => $path,
            'expires'    => time() + self::TTL,
        ), self::TTL );

        $url = add_query_arg( array( 'action' => 'wa_handoff', 't' => $token ), admin_url( 'admin-ajax.php' ) );
        return rest_ensure_response( array( 'url' => $url ) );
    }

    /** admin-ajax.php?action=wa_handoff&t=… */
    public static function handle() {
        nocache_headers();
        header( 'Cache-Control: no-store, private' );

        $raw  = isset( $_GET['t'] ) && is_string( $_GET['t'] ) ? (string) wp_unslash( $_GET['t'] ) : '';
        $data = null;
        if ( wa_app_token_looks_valid( $raw ) ) {
            $key  = self::key( $raw );
            $data = get_transient( $key );
            delete_transient( $key ); // single use, whatever happens next
        }
        if ( 'off' === wa_app_mode() || ! is_array( $data ) || (int) ( $data['expires'] ?? 0 ) < time() ) {
            self::fail( 'This link has expired. Please go back to the app and try again.' );
        }

        $uid     = (int) ( $data['user_id'] ?? 0 );
        $session = (int) ( $data['session_id'] ?? 0 );
        $path    = self::clean_path( $data['path'] ?? '' );
        if ( $uid <= 0 || null === $path || ! wa_app_user_allowed( $uid )
            || ( $session > 0 && ! WA_App_Auth::session_is_active( $session, $uid ) ) ) {
            self::fail( "Sorry, we couldn't sign you in on the website. Please go back to the app and try again." );
        }

        if ( is_user_logged_in() && get_current_user_id() !== $uid ) {
            wp_clear_auth_cookie();
        }
        wp_set_current_user( $uid );
        wp_set_auth_cookie( $uid, false ); // session cookie only; deliberately no wp_login action
        self::set_app_cookie();

        wp_safe_redirect( home_url( $path ) );
        exit;
    }

    private static function set_app_cookie() {
        setcookie( self::COOKIE, '1', array(
            'expires'  => 0,
            'path'     => '/',
            'domain'   => ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? (string) COOKIE_DOMAIN : '',
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ) );
    }

    /** A small self-contained page (wp_die inside admin-ajax would print bare text). */
    private static function fail( string $message ) {
        status_header( 403 );
        header( 'Content-Type: text/html; charset=utf-8' );
        echo '<!doctype html><html lang="en-GB"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . '<title>Wombourne Allstars</title><style>'
            . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f6f7f9;color:#14161a;'
            . 'font:16px/1.5 Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}'
            . 'main{max-width:420px;margin:24px 16px;padding:28px 24px;background:#fff;border-radius:16px;box-shadow:0 6px 24px rgba(20,22,26,.08);text-align:center}'
            . 'h1{margin:0 0 8px;font:800 22px/1.2 Archivo,Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}'
            . 'a{display:inline-block;margin-top:12px;padding:12px 22px;border-radius:999px;background:#C8102E;color:#fff;font-weight:700;text-decoration:none}'
            . '</style></head><body><main><h1>Wombourne Allstars</h1><p>' . esc_html( $message ) . '</p>'
            . '<a href="' . esc_attr( self::RETURN_URL ) . '">Back to the app</a></main></body></html>';
        exit;
    }

    // ── App view on the website ──────────────────────────────────────────────

    /** Signed-in page opened from the app (wa_app cookie). Logged-out pages are never touched. */
    public static function is_app_view(): bool {
        return ! is_admin()
            && ! empty( $_COOKIE[ self::COOKIE ] )
            && 'off' !== wa_app_mode()
            && is_user_logged_in();
    }

    public static function body_class( $classes ) {
        if ( self::is_app_view() && is_array( $classes ) ) {
            $classes[] = 'wa-app';
        }
        return $classes;
    }

    public static function hide_admin_bar( $show ) {
        return self::is_app_view() ? false : $show;
    }

    public static function print_styles() {
        if ( ! self::is_app_view() ) {
            return;
        }
        echo "<style id=\"wa-app-view\">\n"
            . "#header,#footer,.header-wrapper,.absolute-footer,#top-link{display:none!important}\n"
            . "body.wa-app{padding-bottom:calc(88px + env(safe-area-inset-bottom,0px))}\n"
            . ".wa-app-back{position:fixed;left:50%;bottom:calc(16px + env(safe-area-inset-bottom,0px));transform:translateX(-50%);z-index:99999;"
            . "display:inline-block;padding:14px 24px;border-radius:999px;background:#C8102E;color:#fff!important;"
            . "font:700 16px/1 Archivo,Inter,-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Helvetica,Arial,sans-serif;"
            . "letter-spacing:.01em;text-decoration:none!important;white-space:nowrap;box-shadow:0 6px 20px rgba(20,22,26,.28)}\n"
            . ".wa-app-back:hover,.wa-app-back:focus,.wa-app-back:active{background:#a00d24;color:#fff!important}\n"
            . "</style>\n";
    }

    public static function print_button() {
        if ( ! self::is_app_view() ) {
            return;
        }
        echo '<a class="wa-app-back" href="' . esc_attr( self::RETURN_URL ) . '">&larr; Back to the app</a>' . "\n";
    }
}
