<?php
/**
 * Plugin Name:       Allstars App
 * Description:       Server side of the Wombourne Allstars app: app sign-in, opening website pages signed in, push notifications and read-only club content (REST namespace wa/v1). Does nothing for the public until Settings → Allstars App is switched from Off.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Wombourne Allstars FC
 * Text Domain:       allstars-app
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WA_APP_VERSION' ) ) {
    return; // a second copy is already loaded
}

define( 'WA_APP_VERSION', '1.0.0' );
define( 'WA_APP_DB_VERSION', '1.0.0' );
define( 'WA_APP_FILE', __FILE__ );
define( 'WA_APP_PATH', plugin_dir_path( __FILE__ ) );

require_once WA_APP_PATH . 'includes/functions.php';
require_once WA_APP_PATH . 'includes/class-wa-app-db.php';
require_once WA_APP_PATH . 'includes/class-wa-app-auth.php';
require_once WA_APP_PATH . 'includes/class-wa-app-rest.php';
require_once WA_APP_PATH . 'includes/class-wa-app-handoff.php';
require_once WA_APP_PATH . 'includes/class-wa-app-push.php';
require_once WA_APP_PATH . 'includes/class-wa-app-content.php';
require_once WA_APP_PATH . 'includes/class-wa-app-admin.php';

register_activation_hook( __FILE__, array( 'WA_App_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WA_App_Push', 'unschedule' ) );

// The token filter must be in place before anything asks who the current user is.
WA_App_Auth::init();
WA_App_DB::init();
WA_App_REST::init();
WA_App_Handoff::init();
WA_App_Push::init();
WA_App_Content::init();
if ( is_admin() ) {
    WA_App_Admin::init();
}
