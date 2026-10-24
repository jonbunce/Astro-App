<?php
/**
 * Plugin Name:  Allstars Adult Teams
 * Plugin URI:   https://wombourneallstars.co.uk
 * Description:  Fixtures, results and league tables for the club's adult teams, pulled once a day from FA Full-Time. Configure each team's Full-Time link under Adult Teams, then use [adult-team-info-1], [adult-team-info-2] … on any page, or [adult-team-widget-1] for a compact front-page card. [pitch-schedule] shows the groundsman every home game on club pitches this weekend and next.
 * Version:      1.3.2
 * Author:       Wombourne Allstars FC
 * Text Domain:  allstars-adult-teams
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AAT_VERSION', '1.3.2' );
define( 'AAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'AAT_URL', plugin_dir_url( __FILE__ ) );

require_once AAT_DIR . 'includes/class-adult-teams.php';
require_once AAT_DIR . 'includes/class-widget.php';
require_once AAT_DIR . 'includes/class-pitches.php';

add_action( 'init', array( 'Allstars_Adult_Teams', 'init' ) );
add_action( 'init', array( 'Allstars_Pitches', 'init' ) );

// Front-end stylesheet — registered here, enqueued only when a shortcode actually renders.
add_action( 'wp_enqueue_scripts', function() {
    wp_register_style( 'allstars-adult-teams', AAT_URL . 'assets/style.css', array(), AAT_VERSION );
} );

// Admin screen
add_action( 'admin_menu', function() {
    add_menu_page(
        'Adult Teams — Fixtures & Results',
        'Adult Teams',
        'manage_options',
        'adult-teams',
        function() { include AAT_DIR . 'admin/settings.php'; },
        'dashicons-calendar-alt',
        31
    );
} );

// Admin assets: Media Library picker on our screen only
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook !== 'toplevel_page_adult-teams' ) return;
    wp_enqueue_media();
    wp_enqueue_script( 'aat-admin', AAT_URL . 'admin/admin.js', array( 'jquery' ), AAT_VERSION, true );
} );

// Daily sync schedule
register_activation_hook( __FILE__, array( 'Allstars_Adult_Teams', 'ensure_scheduled' ) );
register_deactivation_hook( __FILE__, array( 'Allstars_Adult_Teams', 'unschedule' ) );
