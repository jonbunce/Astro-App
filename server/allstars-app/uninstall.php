<?php
/**
 * Deleting the plugin removes everything it stored: its three tables, its options,
 * its transients and its cron event.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

foreach ( array( 'wa_app_sessions', 'wa_app_devices', 'wa_push_queue' ) as $wa_app_table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$wa_app_table}" );
}

foreach ( array(
    'wa_app_mode',
    'wa_app_testers',
    'wa_app_min_version',
    'wa_app_db_version',
    'wa_app_push_last_run',
    'wa_app_last_purge',
    'wa_app_push_lock',
) as $wa_app_option ) {
    delete_option( $wa_app_option );
}

delete_transient( 'wa_app_home_fixtures' );
delete_transient( 'wa_app_config_pages' );

// Sign-in throttle counters and unused website handoff links.
foreach ( array( 'wa_login_fail_', 'wa_handoff_' ) as $wa_app_prefix ) {
    foreach ( array( '_transient_', '_transient_timeout_' ) as $wa_app_kind ) {
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( $wa_app_kind . $wa_app_prefix ) . '%'
        ) );
    }
}

wp_clear_scheduled_hook( 'wa_app_push_cron' );
