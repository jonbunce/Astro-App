<?php
defined( 'ABSPATH' ) || exit;

/**
 * The Allstars app's API for a member's own data (namespace was/v1): profile, children,
 * children's photos, inbox and account deletion. See docs/api-contract.md.
 *
 * Inert unless the separate Allstars app plugin lets the user in: every route needs a
 * signed-in user AND the was_rest_user_allowed filter (which only that plugin adds) to
 * say yes. The website doesn't use any of it. The GoCardless webhook route (also
 * was/v1) is registered in the main plugin file and is unaffected.
 *
 * Nothing sent from here includes medical answers, addresses, phone numbers,
 * GoCardless IDs or tokens.
 */
class WAS_Rest {

    const NS          = 'was/v1';
    const PHOTO_ROUTE = '/players/(?P<id>\d+)/photo';

    /** ASTRO roles the app knows about. */
    const ROLES = array( 'parent', 'coach', 'committee', 'treasurer', 'social_media', 'registration_officer' );

    /** Image types a child's photo may be sent as. */
    const PHOTO_TYPES = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );

    /** The photo file to stream for this request (set by get_photo(), sent by serve_photo()). */
    private static $photo_file = '';

    public static function init() {
        add_action( 'rest_api_init',          array( __CLASS__, 'register_routes' ) );
        add_filter( 'rest_post_dispatch',     array( __CLASS__, 'no_store' ), 10, 3 );
        add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_photo' ), 10, 4 );
        add_filter( 'was_user_team_ids',      array( __CLASS__, 'user_team_ids' ), 10, 2 );
        add_action( 'deleted_user',           array( 'WAS_Inbox', 'delete_for_user' ) );
    }

    public static function register_routes() {
        $gate = array( __CLASS__, 'permission' );

        register_rest_route( self::NS, '/me', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_me' ),
            'permission_callback' => $gate,
        ) );
        register_rest_route( self::NS, '/me/delete-request', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'post_delete_request' ),
            'permission_callback' => $gate,
        ) );
        register_rest_route( self::NS, '/family', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_family' ),
            'permission_callback' => $gate,
        ) );
        register_rest_route( self::NS, self::PHOTO_ROUTE, array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_photo' ),
            'permission_callback' => $gate,
        ) );
        register_rest_route( self::NS, '/inbox', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_inbox' ),
            'permission_callback' => $gate,
            'args'                => array(
                'page' => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
            ),
        ) );
        register_rest_route( self::NS, '/inbox/unread-count', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_unread_count' ),
            'permission_callback' => $gate,
        ) );
        register_rest_route( self::NS, '/inbox/(?P<id>\d+)/read', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'post_inbox_read' ),
            'permission_callback' => $gate,
        ) );
    }

    // ── Access and caching ─────────────────────────────────────────────────────

    /** Signed in (by the app's token), not closed, and let in by the Allstars app plugin. */
    public static function permission( $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id || WAS_Accounts::login_disabled( $user_id ) ) {
            return new WP_Error( 'was_signed_out', 'Please sign in again.', array( 'status' => 401 ) );
        }
        if ( ! apply_filters( 'was_rest_user_allowed', false, $user_id ) ) {
            return new WP_Error( 'was_not_allowed', "The Allstars app isn't open for this account yet.", array( 'status' => 403 ) );
        }
        return true;
    }

    /** Every was/v1 response is private to one family: never cached by the host or a proxy. */
    public static function no_store( $response, $server, $request ) {
        $route = (string) $request->get_route();
        if ( $route !== '/' . self::NS && strpos( $route, '/' . self::NS . '/' ) !== 0 ) return $response;

        if ( $response instanceof WP_HTTP_Response ) {
            $response->header( 'Cache-Control', 'no-store, private' );
        }
        if ( ! headers_sent() ) {
            nocache_headers();
        }
        return $response;
    }

    // ── GET /me ────────────────────────────────────────────────────────────────

    public static function get_me( $request ) {
        $user     = wp_get_current_user();
        $guardian = WAS_Accounts::current_guardian();

        $first = trim( (string) $user->first_name );
        $last  = trim( (string) $user->last_name );
        if ( $first === '' && $guardian ) $first = trim( (string) $guardian->first_name );
        if ( $last === '' && $guardian )  $last  = trim( (string) $guardian->last_name );

        return rest_ensure_response( array(
            'id'              => (int) $user->ID,
            'firstName'       => $first,
            'lastName'        => $last,
            'email'           => (string) $user->user_email,
            'roles'           => array_values( array_intersect( self::ROLES, was_get_roles( $user->ID ) ) ),
            'profileComplete' => WAS_Accounts::guardian_profile_complete( $guardian ),
        ) );
    }

    // ── GET /family ────────────────────────────────────────────────────────────

    /** The signed-in family's children this season, pending or active. */
    public static function get_family( $request ) {
        $guardian = WAS_Accounts::current_guardian();
        if ( ! $guardian ) return rest_ensure_response( array() );

        $out = array();
        // Inactive = Direct Debit cancelled; shown (as on the website) so the parent can rejoin.
        foreach ( WAS_Database::get_players_by_guardian( $guardian->id, was_season(), array( 'pending', 'active', 'inactive' ) ) as $player ) {
            $out[] = self::child( $player );
        }
        return rest_ensure_response( $out );
    }

    /** One Child (see the contract). Worked out the same way as the parent portal's card. */
    private static function child( $p ) {
        global $wpdb;
        $signed    = ( $p->participation_type === 'signed' );
        $breakdown = was_player_payment_breakdown( $p );   // pounds
        $payments  = WAS_Database::get_player_payments( $p->id );   // newest first
        $consents  = WAS_Database::get_consents( $p->id );

        $lines = array();
        foreach ( $breakdown['items'] as $it ) {
            $lines[] = array(
                'label'  => $it['label'] . ( ! empty( $it['note'] ) ? ' (' . $it['note'] . ')' : '' ),
                'amount' => self::money( $it['amount'] ),
            );
        }

        // Outstanding collections, as the portal's arrears banner (signed players only).
        $arrears = null;
        if ( $signed ) {
            $summary = WAS_Arrears::summary( $p );
            if ( (float) $summary['total'] > 0 ) {
                $arrears = array(
                    'total'  => self::money( $summary['total'] ),
                    'reason' => WAS_Arrears::parent_reason( $p, $summary ),
                );
            }
        }

        $medical   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}was_medical WHERE player_id = %d LIMIT 1", $p->id ) );
        $emergency = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}was_emergency_contacts WHERE player_id = %d LIMIT 1", $p->id ) );

        return array(
            'id'              => (int) $p->id,
            'firstName'       => (string) $p->first_name,
            'lastName'        => (string) $p->last_name,
            'team'            => self::team( WAS_Database::get_team( $p->team_id ), $p ),
            'participation'   => $signed ? 'signed' : 'train_only',
            'status'          => (string) $p->registration_status,
            'monthly'         => self::money( $breakdown['total'] ),
            // gocardless | sibling_rate | team_direct (paid to the team) | exempt — so the app can
            // say "Exempt" or "Paid to the team" rather than "£0.00 a month".
            'paymentType'     => (string) $p->payment_type,
            'breakdown'       => $lines,
            'nextPayment'     => self::next_payment( $p, $breakdown, $payments ),
            'lastPayment'     => self::last_payment( $payments ),
            'arrears'         => $arrears,
            // No answer on file counts as "agree", as everywhere else in ASTRO (portal, social media list).
            'consents'        => array(
                'photography' => ( $consents['photography'] ?? 'agree' ) === 'agree',
                'legal'       => ( $consents['legal_proceedings'] ?? 'agree' ) === 'agree',
            ),
            'medicalOnFile'   => ! empty( $medical ),
            'emergencyOnFile' => ! empty( $emergency ),
            'hasPhoto'        => ! empty( $p->photo_path ),
        );
    }

    private static function team( $team, $player = null ) {
        if ( ! $team ) {
            return array(
                'id'       => (int) ( $player->team_id ?? 0 ),
                'name'     => trim( (string) ( $player->team_name ?? '' ) ),
                'ageGroup' => '',
                'crest'    => null,
            );
        }
        return array(
            'id'       => (int) $team->id,
            'name'     => trim( (string) $team->name ),
            'ageGroup' => (string) $team->age_group,
            'crest'    => ! empty( $team->logo_path ) ? was_logo_url( $team->logo_path ) : null,
        );
    }

    /**
     * When the next monthly Direct Debit collection is due, or null if none is expected
     * (train only, not active, nothing to pay, or no Direct Debit). A collection GoCardless
     * has already scheduled wins (its date may have moved off a weekend); otherwise the
     * child's payment day after today, or after the club's deferred first-payment date.
     */
    private static function next_payment( $p, $breakdown, $payments ) {
        if ( $p->participation_type !== 'signed' || $p->registration_status !== 'active' ) return null;
        if ( empty( $breakdown['billable'] ) || empty( $p->gc_mandate_id ) || empty( $p->gc_subscription_id ) ) return null;

        $today = current_time( 'Y-m-d' );
        $next  = '';
        foreach ( $payments as $pay ) {
            if ( ( $pay->kind ?? WAS_Arrears::KIND_SUB ) !== WAS_Arrears::KIND_SUB || (int) ( $pay->retry_count ?? 0 ) > 0 ) continue;
            if ( ! in_array( $pay->status, array( 'pending_submission', 'submitted' ), true ) ) continue;
            if ( empty( $pay->charge_date ) || $pay->charge_date < $today ) continue;
            if ( $next === '' || $pay->charge_date < $next ) $next = $pay->charge_date;
        }
        if ( $next === '' ) {
            $from = was_first_payment_date() ?: date( 'Y-m-d', strtotime( $today . ' +1 day' ) );
            $next = WAS_GoCardless::day_on_or_after( $from, was_payment_day( $p ) );
        }
        return self::iso( $next . ' 00:00:00' );
    }

    /** The most recent collection that has fallen due (cancelled ones never happened). */
    private static function last_payment( $payments ) {
        $today = current_time( 'Y-m-d' );
        foreach ( $payments as $pay ) {
            if ( $pay->status === 'cancelled' ) continue;
            $date = ! empty( $pay->charge_date ) ? (string) $pay->charge_date : substr( (string) $pay->created_at, 0, 10 );
            if ( $date === '' || $date > $today ) continue;   // not due yet (that's nextPayment)
            $iso = self::iso( $date . ' 00:00:00' );
            if ( ! $iso ) continue;
            return array(
                'date'   => $iso,
                'amount' => self::money( $pay->amount ),
                'status' => self::payment_status( $pay ),
            );
        }
        return null;
    }

    /**
     * paid / pending / failed, matching the portal's payment history: a failed collection
     * the club has since marked settled counts as paid, and one covered by a catch-up
     * collection that's on its way counts as pending.
     */
    private static function payment_status( $pay ) {
        if ( in_array( $pay->status, array( 'confirmed', 'paid_out' ), true ) ) return 'paid';
        if ( in_array( $pay->status, array( 'failed', 'charged_back', 'customer_approval_denied' ), true ) ) {
            $recovered = (string) ( $pay->recovered_by ?? '' );
            if ( $recovered === 'manual' ) return 'paid';
            return $recovered !== '' ? 'pending' : 'failed';
        }
        return 'pending';
    }

    // ── GET /players/{id}/photo ────────────────────────────────────────────────

    /**
     * A child's photo, for the same people who can see it on the website
     * (was_can_view_player_photo()). Streamed by serve_photo().
     */
    public static function get_photo( $request ) {
        $player = WAS_Database::get_player( absint( $request['id'] ) );
        // The same answer for "no such child" as for "not yours", so IDs can't be probed.
        if ( ! $player || ! was_can_view_player_photo( $player, get_current_user_id() ) ) {
            return new WP_Error( 'was_photo_forbidden', "Sorry, you can't see this photo.", array( 'status' => 403 ) );
        }

        $none = new WP_Error( 'was_photo_none', 'No photo has been added yet.', array( 'status' => 404 ) );
        if ( empty( $player->photo_path ) ) return $none;
        $path = was_photo_dir() . basename( $player->photo_path );
        if ( ! is_file( $path ) || ! is_readable( $path ) ) return $none;

        // Only ever send a real raster image, typed by its content.
        $info = @getimagesize( $path );
        $mime = ( $info && ! empty( $info['mime'] ) ) ? $info['mime'] : '';
        if ( ! in_array( $mime, self::PHOTO_TYPES, true ) ) return $none;

        self::$photo_file = $path;
        $response = new WP_REST_Response( null, 200 );
        $response->header( 'Content-Type', $mime );
        $response->header( 'Content-Length', (string) filesize( $path ) );
        $response->header( 'X-Content-Type-Options', 'nosniff' );
        return $response;
    }

    /** Send the photo's bytes instead of JSON (headers, incl. no-store, are already out). */
    public static function serve_photo( $served, $result, $request, $server ) {
        if ( $served || self::$photo_file === '' ) return $served;
        if ( ! ( $result instanceof WP_REST_Response ) || $result->get_status() !== 200
            || $result->get_matched_route() !== '/' . self::NS . self::PHOTO_ROUTE ) {
            return $served;
        }
        $file = self::$photo_file;
        self::$photo_file = '';
        if ( $request->get_method() !== 'HEAD' ) {
            while ( ob_get_level() > 0 && @ob_end_clean() ) {
                // drop anything buffered so only the image goes out
            }
            readfile( $file );
        }
        return true;
    }

    // ── Inbox ──────────────────────────────────────────────────────────────────

    public static function get_inbox( $request ) {
        $page = min( 1000, max( 1, (int) $request->get_param( 'page' ) ) );
        $rows = WAS_Inbox::get_page( get_current_user_id(), $page );
        return rest_ensure_response( array_map( array( __CLASS__, 'inbox_item' ), $rows ) );
    }

    public static function get_unread_count( $request ) {
        return rest_ensure_response( array( 'count' => WAS_Inbox::unread_count( get_current_user_id() ) ) );
    }

    public static function post_inbox_read( $request ) {
        if ( ! WAS_Inbox::mark_read( get_current_user_id(), absint( $request['id'] ) ) ) {
            return new WP_Error( 'was_inbox_not_found', "We couldn't find that message.", array( 'status' => 404 ) );
        }
        return rest_ensure_response( array( 'ok' => true ) );
    }

    private static function inbox_item( $row ) {
        $data = json_decode( (string) $row->data, true );
        if ( ! is_array( $data ) ) $data = array();

        $item = array(
            'id'        => (int) $row->id,
            'type'      => (string) $row->type,
            'title'     => (string) $row->title,
            'body'      => (string) $row->body,
            'createdAt' => self::iso( $row->created_at ),
            'read'      => ! empty( $row->read_at ),
        );
        if ( ! empty( $data['from'] ) ) {
            $item['from'] = (string) $data['from'];
        }
        if ( array_key_exists( 'image', $data ) ) {
            $item['image'] = ! empty( $data['image'] ) ? (string) $data['image'] : null;
        }
        return $item;
    }

    // ── POST /me/delete-request ────────────────────────────────────────────────

    /** The member asks for their account to be deleted, confirming with their password. */
    public static function post_delete_request( $request ) {
        $user = wp_get_current_user();

        // Closing the account that runs the website would lock the club out of it.
        if ( user_can( $user, 'manage_options' ) ) {
            return new WP_Error( 'was_delete_admin', "This account looks after the website, so it can't be closed from the app. Please contact the club.", array( 'status' => 403 ) );
        }

        $throttle = 'was_delete_fail_' . $user->ID;
        $failures = (int) get_transient( $throttle );
        if ( $failures >= 5 ) {
            return new WP_Error( 'was_delete_throttled', 'Too many attempts. Please wait 15 minutes and try again.', array( 'status' => 429 ) );
        }

        $password = $request->get_param( 'password' );
        if ( ! is_string( $password ) || $password === '' || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
            set_transient( $throttle, $failures + 1, 15 * MINUTE_IN_SECONDS );
            return new WP_Error( 'was_wrong_password', "That password isn't right. Please try again.", array( 'status' => 403 ) );
        }
        delete_transient( $throttle );

        WAS_Accounts::request_deletion( $user->ID );
        return rest_ensure_response( array( 'ok' => true ) );
    }

    // ── Filter: was_user_team_ids ──────────────────────────────────────────────

    /**
     * The teams a member follows in the app: their children's teams this season (main and
     * additional teams, pending or active registrations) plus any team they coach.
     */
    public static function user_team_ids( $ids, $user_id ) {
        global $wpdb;
        $ids     = array_map( 'intval', (array) $ids );
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) return $ids;

        $guardian = ( $user_id === get_current_user_id() )
            ? WAS_Accounts::current_guardian()
            : $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}was_guardians WHERE wp_user_id = %d LIMIT 1", $user_id ) );

        if ( $guardian ) {
            foreach ( WAS_Database::get_players_by_guardian( $guardian->id, was_season(), array( 'pending', 'active' ) ) as $p ) {
                $ids[] = (int) $p->team_id;
                foreach ( WAS_Database::get_player_extra_teams( $p->id ) as $xt ) {
                    $ids[] = (int) $xt->team_id;
                }
            }
        }
        foreach ( WAS_Accounts::get_coach_teams( $user_id ) as $t ) {
            $ids[] = (int) $t->id;
        }
        return array_values( array_unique( array_filter( $ids ) ) );
    }

    // ── Formatting ─────────────────────────────────────────────────────────────

    /** A stored site-time date/time as ISO 8601 with the site's offset, or null. */
    private static function iso( $mysql ) {
        $mysql = (string) $mysql;
        if ( $mysql === '' || strpos( $mysql, '0000-00-00' ) === 0 ) return null;
        $dt = date_create( $mysql, wp_timezone() );
        return $dt ? $dt->format( DATE_ATOM ) : null;
    }

    /** Pounds as a number: whole pounds as an integer (22), otherwise pence included (22.5). */
    private static function money( $amount ) {
        $v = round( (float) $amount, 2 );
        return ( floor( $v ) === $v ) ? (int) $v : $v;
    }
}
