<?php
/**
 * wa/v1 route table, the gates in front of each route, and no-store headers on every
 * wa/v1 and was/v1 response (SiteGround's cache must never share one family's data).
 *
 * Gates:
 *   open    — only GET /health: always answers, even when the app is off.
 *   login   — POST /auth/login: closed (403) when off; otherwise anyone may try.
 *   token   — closed when off; otherwise needs a live app token for an allowed user (401 if not).
 *   content — closed when off; 'testers' needs an allowed signed-in user; 'on' is public.
 */

defined( 'ABSPATH' ) || exit;

class WA_App_REST {

    const NS = 'wa/v1';

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        add_filter( 'rest_post_dispatch', array( __CLASS__, 'no_store' ), 10, 3 );
    }

    public static function register_routes() {
        register_rest_route( self::NS, '/health', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'health' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NS, '/auth/login', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( 'WA_App_Auth', 'rest_login' ),
            'permission_callback' => array( __CLASS__, 'gate_login' ),
        ) );
        register_rest_route( self::NS, '/auth/logout', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( 'WA_App_Auth', 'rest_logout' ),
            'permission_callback' => array( __CLASS__, 'gate_token' ),
        ) );
        register_rest_route( self::NS, '/auth/sessions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( 'WA_App_Auth', 'rest_sessions' ),
            'permission_callback' => array( __CLASS__, 'gate_token' ),
        ) );
        register_rest_route( self::NS, '/auth/sessions/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( 'WA_App_Auth', 'rest_delete_session' ),
            'permission_callback' => array( __CLASS__, 'gate_token' ),
        ) );
        register_rest_route( self::NS, '/auth/handoff', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( 'WA_App_Handoff', 'rest_mint' ),
            'permission_callback' => array( __CLASS__, 'gate_token' ),
        ) );

        register_rest_route( self::NS, '/devices', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( 'WA_App_Push', 'rest_register_device' ),
                'permission_callback' => array( __CLASS__, 'gate_token' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( 'WA_App_Push', 'rest_unregister_device' ),
                'permission_callback' => array( __CLASS__, 'gate_token' ),
            ),
        ) );

        register_rest_route( self::NS, '/config', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( 'WA_App_Content', 'rest_config' ),
            'permission_callback' => array( __CLASS__, 'gate_content' ),
        ) );
        register_rest_route( self::NS, '/content/home', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( 'WA_App_Content', 'rest_home' ),
            'permission_callback' => array( __CLASS__, 'gate_content' ),
        ) );
        register_rest_route( self::NS, '/content/news', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( 'WA_App_Content', 'rest_news' ),
            'permission_callback' => array( __CLASS__, 'gate_content' ),
        ) );
    }

    // ── Gates ────────────────────────────────────────────────────────────────

    public static function gate_login() {
        return 'off' === wa_app_mode() ? wa_app_closed_error() : true;
    }

    public static function gate_token() {
        if ( 'off' === wa_app_mode() ) {
            return wa_app_closed_error();
        }
        if ( ! get_current_user_id() || ! WA_App_Auth::current_session() ) {
            return wa_app_error( 'wa_not_signed_in', 'Please sign in again.', 401 );
        }
        return true;
    }

    public static function gate_content() {
        $mode = wa_app_mode();
        if ( 'off' === $mode ) {
            return wa_app_closed_error();
        }
        if ( 'on' === $mode ) {
            return true;
        }
        $uid = get_current_user_id();
        if ( ! $uid ) {
            return wa_app_error( 'wa_sign_in_required', 'Please sign in to use the app.', 401 );
        }
        if ( ! wa_app_user_allowed( $uid ) ) {
            return wa_app_error( 'wa_app_not_allowed', "This account can't use the Allstars app yet.", 403 );
        }
        return true;
    }

    // ── Routes ───────────────────────────────────────────────────────────────

    /** GET /health — did the auth headers survive the trip to PHP? (No secrets.) */
    public static function health() {
        return rest_ensure_response( array(
            'ok'              => true,
            'version'         => WA_APP_VERSION,
            'auth_header'     => ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) || ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ),
            'wa_token_header' => ! empty( $_SERVER['HTTP_X_WA_TOKEN'] ),
        ) );
    }

    // ── Headers ──────────────────────────────────────────────────────────────

    public static function no_store( $response, $server, $request ) {
        if ( $request instanceof WP_REST_Request && WA_App_Auth::is_app_route( (string) $request->get_route() ) ) {
            nocache_headers();
            if ( $response instanceof WP_HTTP_Response ) {
                $response->header( 'Cache-Control', 'no-store, private' );
            }
        }
        return $response;
    }
}
