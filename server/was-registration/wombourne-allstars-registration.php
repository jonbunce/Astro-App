<?php
/**
 * Plugin Name:  ASTRO
 * Plugin URI:   https://wombourneallstarsfc.co.uk
 * Description:  ASTRO — Allstars System for Tracking Registrations and Onboarding. Built for our stars. Powered for their journey.
 * Version:      2.29.0
 * Author:       Wombourne Allstars FC
 * Text Domain:  was-reg
 */

defined( 'ABSPATH' ) || exit;

define( 'WAS_VERSION',    '2.29.0' );
define( 'WAS_DB_VERSION', '2.16.0' );

// Age in whole years from a YYYY-MM-DD date of birth, or '' if not set/invalid.
function was_age( $dob ) {
    if ( empty( $dob ) || $dob === '0000-00-00' ) return '';
    try {
        $d = new DateTime( $dob );
    } catch ( Exception $e ) {
        return '';
    }
    $now = new DateTime( 'today' );
    if ( $d > $now ) return '';
    return $now->diff( $d )->y;
}
define( 'WAS_PATH',       plugin_dir_path( __FILE__ ) );
define( 'WAS_URL',        plugin_dir_url( __FILE__ ) );

// Photo storage — one level above public_html
function was_photo_dir() {
    $base = dirname( ABSPATH );
    $dir  = $base . '/player-photos/';
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '.htaccess', "Deny from all\n" );
    }
    return $dir;
}

// Photo URL helper
function was_photo_url( $player_id ) {
    return admin_url( 'admin-ajax.php' )
        . '?action=was_serve_photo&player_id=' . absint( $player_id )
        . '&nonce=' . wp_create_nonce( 'was_photo_nonce' );
}

// Team logo storage — public uploads directory
// ASTRO Portal logo — returns URL of uploaded logo or bundled default
function was_portal_logo_url() {
    $uploaded = get_option( 'was_portal_logo_url', '' );
    if ( $uploaded ) return $uploaded;
    return WAS_URL . 'assets/img/astro-logo-default.gif';
}

// Dark counterpart of the wordmark, for light-background pages (account cards etc.)
function was_portal_logo_dark_url() {
    $uploaded = get_option( 'was_portal_logo_dark_url', '' );
    if ( $uploaded ) return $uploaded;
    return WAS_URL . 'assets/img/astro-logo-dark.png';
}

function was_logo_dir() {
    $dir = wp_upload_dir()['basedir'] . '/was-team-logos/';
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
    }
    return $dir;
}

function was_logo_url( $filename ) {
    if ( ! $filename ) return '';
    return wp_upload_dir()['baseurl'] . '/was-team-logos/' . basename( $filename );
}

/**
 * Validate an uploaded image and save a clean copy of it — used for every image upload
 * (child photos, team logos, team news photos).
 *
 * Only real JPG, PNG and WebP images are accepted: checked by content as well as by
 * name, so SVGs, scripts or anything renamed to .jpg are refused. The image is turned
 * upright, scaled down to at most $max_dim pixels on its longest side and re-encoded,
 * which drops EXIF data such as the GPS location of the phone that took it.
 *
 * Callers check the file size themselves (photos 8MB, logos 2MB).
 *
 * @param array  $file     One upload: name, tmp_name, error, size (as in $_FILES).
 * @param string $dir      Folder to save into.
 * @param string $basename File name to save as, without an extension.
 * @param int    $max_dim  Longest side in pixels.
 * @return array|WP_Error  array( 'file' => saved file name, 'path' => full path ), or an error.
 */
function was_save_uploaded_image( $file, $dir, $basename, $max_dim = 2000 ) {
    $types = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
    );
    $exts = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' );

    if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
        return new WP_Error( 'was_image_upload', 'The image did not upload properly. Please try again.' );
    }

    // Name and content must agree on one of the allowed types.
    $check = wp_check_filetype_and_ext( $file['tmp_name'], (string) ( $file['name'] ?? '' ), $types );
    $info  = @getimagesize( $file['tmp_name'] );
    if ( empty( $check['type'] ) || ! $info || empty( $info['mime'] ) || ! isset( $exts[ $info['mime'] ] ) || $check['type'] !== $info['mime'] ) {
        return new WP_Error( 'was_image_type', 'Please choose a JPG, PNG or WebP image.' );
    }
    // A small file can still unpack to an enormous bitmap — refuse before decoding it.
    if ( (int) $info[0] * (int) $info[1] > 50000000 ) {
        return new WP_Error( 'was_image_size', 'That image is too large. Please choose a smaller one.' );
    }
    $mime = $info['mime'];

    // Prefer the GD editor: it never copies metadata into the new file. WordPress's Imagick
    // editor deliberately keeps EXIF, so if GD can't handle the type, strip it ourselves.
    $gd_only = function () { return array( 'WP_Image_Editor_GD' ); };
    add_filter( 'wp_image_editors', $gd_only );
    $editor = wp_get_image_editor( $file['tmp_name'], array( 'mime_type' => $mime ) );
    remove_filter( 'wp_image_editors', $gd_only );
    $via_gd = ! is_wp_error( $editor );
    if ( ! $via_gd ) {
        $editor = wp_get_image_editor( $file['tmp_name'], array( 'mime_type' => $mime ) );
    }
    $failed = new WP_Error( 'was_image_save', 'Sorry, that image could not be processed. Please try a different one.' );
    if ( is_wp_error( $editor ) ) return $failed;

    // Phones often store the picture sideways plus an EXIF "rotate me" flag, and that flag
    // is about to be thrown away — so turn the pixels upright first.
    $editor->maybe_exif_rotate();

    $size = $editor->get_size();
    if ( max( (int) $size['width'], (int) $size['height'] ) > $max_dim ) {
        $resized = $editor->resize( $max_dim, $max_dim, false );
        if ( is_wp_error( $resized ) ) return $failed;
    }

    $saved = $editor->save( trailingslashit( $dir ) . $basename . '.' . $exts[ $mime ], $mime );
    if ( is_wp_error( $saved ) || empty( $saved['path'] ) || empty( $saved['file'] ) ) return $failed;

    if ( ! $via_gd ) {
        if ( ! class_exists( 'Imagick' ) ) {
            @unlink( $saved['path'] );
            return $failed;
        }
        try {
            $im = new Imagick( $saved['path'] );
            $im->stripImage();
            $im->writeImage( $saved['path'] );
            $im->clear();
        } catch ( Exception $e ) {
            @unlink( $saved['path'] );
            return $failed;
        }
    }

    return array( 'file' => $saved['file'], 'path' => $saved['path'] );
}

function was_season() {
    return get_option( 'was_current_season', '2024-25' );
}

// Deferred first payment. If a FUTURE "first payment date" is set, new Direct Debits are
// created with that start_date so no money is collected until then (monthly thereafter).
// Returns YYYY-MM-DD, or '' to let GoCardless charge on its normal schedule.
function was_first_payment_date() {
    $d = get_option( 'was_season_start_date', '' );
    if ( $d ) {
        $ts = strtotime( $d );
        if ( $ts && $ts > strtotime( 'today' ) ) {
            return date( 'Y-m-d', $ts );
        }
    }
    return '';
}

// Day of the month a child's Direct Debit is collected on: the 1st unless the club has
// set a manual override (1-28) on the admin player screen.
function was_payment_day( $player ) {
    $d = (int) ( $player->payment_day ?? 0 );
    return ( $d >= 1 && $d <= 28 ) ? $d : 1;
}

// 1 -> "1st", 2 -> "2nd", 15 -> "15th", 22 -> "22nd".
function was_ordinal( $n ) {
    $n = (int) $n;
    if ( in_array( $n % 100, array( 11, 12, 13 ), true ) ) return $n . 'th';
    $suffix = array( 1 => 'st', 2 => 'nd', 3 => 'rd' );
    return $n . ( $suffix[ $n % 10 ] ?? 'th' );
}

/**
 * Progress indicator for the registration journey.
 *
 * @param int|string $active 1-based index of the current step, or 'done' for all complete.
 * @param string     $type   'signed' (includes the Direct Debit step) or 'train'.
 */
function was_steps_html( $active, $type = 'signed' ) {
    $steps = ( $type === 'train' )
        ? array( 'Your team', 'Your details', 'Done' )
        : array( 'Your team', 'Direct Debit', 'Your details', 'Done' );

    $done_all = ( $active === 'done' );
    $active   = (int) $active;

    $out = '<ol class="was-steps" aria-label="Registration progress">';
    foreach ( $steps as $i => $label ) {
        $n   = $i + 1;
        $cls = '';
        $dot = (string) $n;
        if ( $done_all || $n < $active ) {
            $cls = 'is-done';
            $dot = '&#10003;';
        } elseif ( $n === $active ) {
            $cls = 'is-active';
        }
        $dd = ( $label === 'Direct Debit' ) ? ' was-step--dd' : '';
        $out .= '<li class="was-step ' . $cls . $dd . '"><span class="was-step__dot">' . $dot . '</span>'
              . '<span class="was-step__label">' . esc_html( $label ) . '</span></li>';
    }
    $out .= '</ol>';
    return $out;
}

// Role helpers
function was_get_roles( $user_id ) {
    $roles = get_user_meta( $user_id, 'was_roles', true );
    return is_array( $roles ) ? $roles : array();
}
function was_has_role( $role, $user_id = null ) {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) return false;
    if ( user_can( $user_id, 'manage_options' ) ) return true;
    return in_array( $role, was_get_roles( $user_id ), true );
}
function was_add_role( $user_id, $role ) {
    $roles = was_get_roles( $user_id );
    if ( ! in_array( $role, $roles, true ) ) {
        $roles[] = $role;
        update_user_meta( $user_id, 'was_roles', $roles );
    }
}
function was_remove_role( $user_id, $role ) {
    $roles = array_values( array_diff( was_get_roles( $user_id ), array( $role ) ) );
    update_user_meta( $user_id, 'was_roles', $roles );
}

// Compute a child's combined monthly subscription: an explicit custom override wins;
// otherwise primary-team rate (full or sibling) + each additional signed team's
// second-team supplement. Train-only primary = £0 (collected at team level).
/**
 * Itemised monthly Direct Debit breakdown for a player. Single source of truth for
 * both the total (was_player_monthly_amount) and the on-screen "payment schedule".
 * Returns array( 'items' => array of array('label','amount','note'), 'total' => float,
 *                'billable' => bool, 'summary' => string ).
 */
function was_player_payment_breakdown( $player ) {
    $out = array( 'items' => array(), 'total' => 0.0, 'billable' => false, 'summary' => '' );
    if ( ! $player ) return $out;

    // A whole-child custom amount set by the club overrides everything.
    if ( isset( $player->subs_override ) && $player->subs_override !== null && $player->subs_override !== '' ) {
        $amt = (float) $player->subs_override;
        $out['items'][] = array( 'label' => 'Agreed monthly amount (set by the club)', 'amount' => $amt, 'note' => '' );
        $out['total']    = round( $amt, 2 );
        $out['billable'] = ( $amt > 0 );
        return $out;
    }

    if ( $player->participation_type !== 'signed' ) {
        $out['summary'] = 'Train only — subscription is paid directly to the team, not collected by the club.';
        return $out;
    }

    $primary = WAS_Database::get_team( $player->team_id );
    if ( ! $primary ) return $out;

    $is_sibling  = ( $player->payment_type === 'sibling_rate' && $primary->sibling_amount > 0 );
    $primary_amt = $is_sibling ? (float) $primary->sibling_amount : (float) $primary->subs_amount;
    $out['items'][] = array(
        'label'  => trim( $primary->name ) . ' (main team)',
        'amount' => $primary_amt,
        'note'   => $is_sibling ? 'sibling rate' : '',
    );
    $total = $primary_amt;

    foreach ( WAS_Database::get_player_extra_teams( $player->id ) as $xt ) {
        if ( $xt->participation_type === 'signed' ) {
            $has_ov = ( isset( $xt->amount_override ) && $xt->amount_override !== null && $xt->amount_override !== '' );
            $amt    = $has_ov ? (float) $xt->amount_override : (float) ( $xt->second_team_amount ?? 0 );
            $out['items'][] = array(
                'label'  => trim( $xt->team_name ) . ' (additional team)',
                'amount' => $amt,
                'note'   => ( $amt == 0.0 ) ? 'no charge' : '',
            );
            $total += $amt;
        } else {
            $out['items'][] = array(
                'label'  => trim( $xt->team_name ) . ' (additional — train only)',
                'amount' => 0.0,
                'note'   => 'not collected by the club',
            );
        }
    }

    // Per-player monthly supplement (e.g. "JPL league supplement") — itemised on top.
    // Note: a whole-child custom override (handled above) replaces everything incl. this.
    if ( ! empty( $player->supplement_amount ) && (float) $player->supplement_amount > 0 ) {
        $out['items'][] = array(
            'label'  => trim( (string) ( $player->supplement_label ?? '' ) ) ?: 'Club supplement',
            'amount' => (float) $player->supplement_amount,
            'note'   => '',
        );
        $total += (float) $player->supplement_amount;
    }

    $out['total']    = round( $total, 2 );
    $out['billable'] = ( $out['total'] > 0 );
    return $out;
}

function was_player_monthly_amount( $player ) {
    $b = was_player_payment_breakdown( $player );
    return $b['total'];
}

// Load all classes
require_once WAS_PATH . 'includes/class-database.php';
require_once WAS_PATH . 'includes/class-gocardless.php';
require_once WAS_PATH . 'includes/class-notifications.php';
require_once WAS_PATH . 'includes/class-registration.php';
require_once WAS_PATH . 'includes/class-accounts.php';
require_once WAS_PATH . 'includes/class-portal.php';
require_once WAS_PATH . 'includes/class-facebook.php';
require_once WAS_PATH . 'includes/class-team-news.php';
require_once WAS_PATH . 'includes/class-interest.php';
require_once WAS_PATH . 'includes/class-arrears.php';
require_once WAS_PATH . 'includes/class-inbox.php';
require_once WAS_PATH . 'includes/class-rest.php';
require_once WAS_PATH . 'admin/class-admin.php';

// A closed account (deletion requested in the app) can't sign in anywhere. Added now, not
// on init, so it is in place however early anything authenticates.
WAS_Accounts::init_login_guards();

// Activation
register_activation_hook( __FILE__, function() {
    WAS_Database::install();
    WAS_Database::seed_schools();
    // Schedule daily cron
    if ( ! wp_next_scheduled( 'was_daily_gc_sync' ) ) {
        wp_schedule_event( strtotime( 'midnight' ), 'daily', 'was_daily_gc_sync' );
    }
} );

// Deactivation — clear cron
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'was_daily_gc_sync' );
    wp_clear_scheduled_hook( 'was_msg_watchdog' );
} );

// DB upgrade on version change
add_action( 'plugins_loaded', function() {
    if ( get_option( 'was_db_version' ) !== WAS_DB_VERSION ) {
        WAS_Database::install();
        WAS_Database::seed_schools();
        // Ensure cron is scheduled
        if ( ! wp_next_scheduled( 'was_daily_gc_sync' ) ) {
            wp_schedule_event( strtotime( 'midnight' ), 'daily', 'was_daily_gc_sync' );
        }
    }

    // Migrate old single-credential options to new dual-credential options
    $old_key    = wp_unslash( get_option( 'was_gc_api_key', '' ) );
    $old_secret = wp_unslash( get_option( 'was_gc_webhook_secret', '' ) );
    if ( $old_key && ! get_option( 'was_gc_sandbox_api_key' ) ) {
        update_option( 'was_gc_sandbox_api_key', $old_key );
    }
    if ( $old_secret && ! get_option( 'was_gc_sandbox_webhook_secret' ) ) {
        update_option( 'was_gc_sandbox_webhook_secret', $old_secret );
    }
});

// ── Daily cron: sync GoCardless payment data ──────────────────────────────────
add_action( 'was_daily_gc_sync', function() {
    global $wpdb;
    $season  = was_season();
    $players = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}was_players
         WHERE season = %s
           AND participation_type = 'signed'
           AND registration_status = 'active'
           AND gc_mandate_id != ''",
        $season
    ) );

    foreach ( $players as $player ) {
        WAS_GoCardless::sync_player_payments( $player );
        // Also check mandate status directly
        if ( $player->gc_mandate_id ) {
            WAS_GoCardless::sync_mandate_status( $player );
        }
    }

    update_option( 'was_last_gc_sync', current_time( 'mysql' ) );
    error_log( '[WAS] Daily GoCardless sync completed — ' . count( $players ) . ' players processed.' );
} );

// Boot
add_action( 'init',       array( 'WAS_Registration', 'init' ) );
add_action( 'init',       array( 'WAS_Accounts', 'init' ) );
add_action( 'init',       array( 'WAS_Portal', 'init' ) );
add_action( 'init',       array( 'WAS_Team_News', 'init' ) );
add_action( 'init',       array( 'WAS_Interest', 'init' ) );
add_action( 'init',       array( 'WAS_Arrears', 'init' ) );
add_action( 'admin_menu', array( 'WAS_Admin', 'register_menus' ) );

// Admin AJAX actions
add_action( 'wp_ajax_was_hard_delete_player', array( 'WAS_Admin', 'ajax_hard_delete' ) );
add_action( 'wp_ajax_was_run_sync',           array( 'WAS_Admin', 'ajax_run_sync' ) );
add_action( 'wp_ajax_was_set_custom_subs',     array( 'WAS_Admin', 'ajax_set_custom_subs' ) );
add_action( 'wp_ajax_was_set_supplement',      array( 'WAS_Admin', 'ajax_set_supplement' ) );
add_action( 'wp_ajax_was_preview_payment_day', array( 'WAS_Admin', 'ajax_preview_payment_day' ) );
add_action( 'wp_ajax_was_set_payment_day',     array( 'WAS_Admin', 'ajax_set_payment_day' ) );
add_action( 'wp_ajax_was_set_payment_emails_paused', array( 'WAS_Admin', 'ajax_set_payment_emails_paused' ) );
add_action( 'wp_ajax_was_add_player_team',     array( 'WAS_Admin', 'ajax_add_player_team' ) );
add_action( 'wp_ajax_was_remove_player_team',  array( 'WAS_Admin', 'ajax_remove_player_team' ) );
add_action( 'wp_ajax_was_withdraw_team',       array( 'WAS_Admin', 'ajax_withdraw_team' ) );

// REST endpoints
add_action( 'rest_api_init', function () {
    register_rest_route( 'was/v1', '/webhook/gocardless', array(
        'methods'             => 'POST',
        'callback'            => array( 'WAS_GoCardless', 'handle_webhook' ),
        'permission_callback' => '__return_true',
    ) );
} );

// The Allstars app's member API (the rest of was/v1). Inert unless the separate Allstars
// app plugin lets the signed-in user in — see WAS_Rest.
WAS_Rest::init();

/**
 * May this (signed-in) user see this child's photo?
 *  - admins, committee, social media and registration officers: any child;
 *  - coaches: children on one of their own teams (main team or an additional team);
 *  - parents: their own children.
 * Nobody else — e.g. a treasurer with no other role — sees children's photos.
 */
function was_can_view_player_photo( $player, $user_id ) {
    if ( user_can( $user_id, 'manage_options' ) ) return true;

    $roles = was_get_roles( $user_id );
    if ( array_intersect( array( 'committee', 'social_media', 'registration_officer' ), $roles ) ) return true;

    if ( in_array( 'coach', $roles, true ) ) {
        $team_ids = array_map( function( $t ) { return (int) $t->id; }, WAS_Accounts::get_coach_teams( $user_id ) );
        if ( in_array( (int) $player->team_id, $team_ids, true ) ) return true;
        foreach ( WAS_Database::get_player_extra_teams( $player->id ) as $xt ) {
            if ( in_array( (int) $xt->team_id, $team_ids, true ) ) return true;
        }
    }

    // Own children (current_guardian() is the signed-in user's family record).
    $guardian = WAS_Accounts::current_guardian();
    return $guardian && (int) $player->primary_guardian_id === (int) $guardian->id;
}

// Photo serving via admin-ajax (cookie auth works here)
function was_serve_player_photo_ajax() {
    if ( ! wp_verify_nonce( $_GET['nonce'] ?? '', 'was_photo_nonce' ) ) {
        status_header( 403 ); exit( 'Forbidden' );
    }
    $user_id = get_current_user_id();
    if ( ! $user_id ) { status_header( 401 ); exit( 'Not authenticated' ); }
    $player_id = absint( $_GET['player_id'] ?? 0 );
    $player    = WAS_Database::get_player( $player_id );
    if ( ! $player ) {
        status_header( 404 ); exit( 'Not found' );
    }
    if ( ! was_can_view_player_photo( $player, $user_id ) ) {
        status_header( 403 ); exit( 'Forbidden' );
    }
    if ( empty( $player->photo_path ) ) {
        status_header( 404 ); exit( 'Not found' );
    }
    $path = was_photo_dir() . basename( $player->photo_path );
    if ( ! file_exists( $path ) ) { status_header( 404 ); exit( 'File not found' ); }
    $mime = mime_content_type( $path ) ?: 'image/jpeg';
    header( 'Content-Type: ' . $mime );
    header( 'Content-Length: ' . filesize( $path ) );
    header( 'Cache-Control: private, max-age=3600' );
    readfile( $path );
    exit;
}
add_action( 'wp_ajax_was_serve_photo',        'was_serve_player_photo_ajax' );
add_action( 'wp_ajax_nopriv_was_serve_photo', function() { status_header(401); exit('Not authenticated'); } );

// ── WooCommerce My Account integration ───────────────────────────────────────

// Add ASTRO Portal link to the My Account navigation menu
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    $new = array();
    foreach ( $items as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'dashboard' ) {
            $new['astro-portal'] = '&#x1F680; ASTRO Portal';
        }
    }
    return $new;
} );

// Return the direct portal URL for the ASTRO Portal menu item
add_filter( 'woocommerce_get_endpoint_url', function( $url, $endpoint, $value, $permalink ) {
    if ( $endpoint === 'astro-portal' ) {
        return home_url( '/account-portal/' );
    }
    return $url;
}, 10, 4 );

// Add ASTRO Portal tile to the My Account dashboard
add_action( 'woocommerce_account_dashboard', function() {
    $portal_url = home_url( '/account-portal/' );
    echo '<div class="astro-woo-tile-wrap">';
    echo '<a href="' . esc_url( $portal_url ) . '" class="astro-woo-tile">';
    echo '<div>';
    echo '<span class="astro-woo-tile__icon">🚀</span>';
    echo '<span class="astro-woo-tile__label">ASTRO Portal</span>';
    echo '</div>';
    echo '<span class="astro-woo-tile__sub">Player registrations &amp; club membership &rarr;</span>';
    echo '</a>';
    echo '</div>';
} );

// Inline CSS for the tile — lightweight, no extra stylesheet needed
add_action( 'wp_head', function() {
    if ( ! is_account_page() ) return;
    echo '<style>
    .astro-woo-tile-wrap { margin: 1.5rem 0; }
    .astro-woo-tile {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: #14161a;
        color: #fff;
        padding: 1rem 1.25rem;
        border-radius: 10px;
        text-decoration: none;
        transition: background 0.2s;
        max-width: 480px;
    }
    .astro-woo-tile:hover { background: #C8102E; color: #fff; text-decoration: none; }
    .astro-woo-tile__icon { font-size: 1.8rem; flex-shrink: 0; }
    .astro-woo-tile__label { font-size: 1rem; font-weight: 700; display: block; }
    .astro-woo-tile__sub { font-size: 0.82rem; opacity: 0.8; display: block; margin-top: 2px; }
    .astro-woo-tile > div { display: flex; flex-direction: column; }
    </style>';
} );

// Frontend scripts
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'was-registration', WAS_URL . 'assets/css/registration.css', array(), WAS_VERSION );
    wp_enqueue_style( 'was-portal',       WAS_URL . 'assets/css/portal.css',       array( 'was-registration' ), WAS_VERSION );
    wp_enqueue_script( 'was-registration', WAS_URL . 'assets/js/registration.js', array( 'jquery' ), WAS_VERSION, true );
    wp_enqueue_script( 'was-portal',       WAS_URL . 'assets/js/portal.js',       array( 'jquery' ), WAS_VERSION, true );
    wp_localize_script( 'was-registration', 'wasAjax', array(
        'ajaxurl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'was_nonce' ),
        'siteurl'     => home_url(),
        'login_url'   => home_url( '/account-login/' ),
        'portal_url'  => home_url( '/account-portal/' ),
        'photo_nonce' => wp_create_nonce( 'was_photo_nonce' ),
    ) );
} );

// Admin scripts
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( strpos( $hook, 'was-' ) === false ) return;
    // Media picker for logo upload on settings page
    if ( strpos( $hook, 'was-settings' ) !== false ) {
        wp_enqueue_media();
    }
    wp_enqueue_style( 'was-admin', WAS_URL . 'assets/css/admin.css', array(), WAS_VERSION );
    wp_enqueue_script( 'was-admin', WAS_URL . 'assets/js/admin.js', array( 'jquery' ), WAS_VERSION, true );
    wp_localize_script( 'was-admin', 'wasAdmin', array(
        'ajaxurl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'was_nonce' ),
        'photo_nonce' => wp_create_nonce( 'was_photo_nonce' ),
    ) );
} );
