<?php
/**
 * The plugin's three tables, created/updated with dbDelta whenever WA_APP_DB_VERSION changes.
 * All datetimes are stored in UTC.
 */

defined( 'ABSPATH' ) || exit;

class WA_App_DB {

    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
    }

    public static function sessions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'wa_app_sessions';
    }

    public static function devices_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'wa_app_devices';
    }

    public static function queue_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'wa_push_queue';
    }

    /** Activation: tables, default options, and the push cron (only scheduled when the app isn't off). */
    public static function activate() {
        self::install();
        WA_App_Push::sync_schedule();
    }

    public static function maybe_upgrade() {
        if ( get_option( 'wa_app_db_version' ) !== WA_APP_DB_VERSION ) {
            self::install();
        }
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset  = $wpdb->get_charset_collate();
        $sessions = self::sessions_table();
        $devices  = self::devices_table();
        $queue    = self::queue_table();

        // One row per app sign-in. Only the sha256 of the token is stored.
        dbDelta( "CREATE TABLE {$sessions} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  token_hash char(64) NOT NULL,
  device varchar(100) NOT NULL DEFAULT '',
  platform varchar(20) NOT NULL DEFAULT '',
  app_version varchar(20) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  last_used_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  revoked_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY token_hash (token_hash),
  KEY user_id (user_id),
  KEY expires_at (expires_at)
) {$charset};" );

        // Expo push tokens, each tied to the sign-in (session) that registered it.
        // Expo tokens are ~45 characters, so a 191-character unique prefix is the whole token.
        dbDelta( "CREATE TABLE {$devices} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL,
  expo_push_token varchar(255) NOT NULL,
  platform varchar(20) NOT NULL DEFAULT '',
  enabled tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY expo_push_token (expo_push_token(191)),
  KEY user_id (user_id),
  KEY session_id (session_id)
) {$charset};" );

        // One row per push per device. status: queued | sent | delivered | failed | skipped | unconfirmed.
        // TEXT/LONGTEXT columns have no DEFAULT (MySQL rejects it); inserts always supply them.
        dbDelta( "CREATE TABLE {$queue} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  device_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(255) NOT NULL DEFAULT '',
  body text NOT NULL,
  data longtext NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'queued',
  attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
  expo_ticket_id varchar(100) NOT NULL DEFAULT '',
  error varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  sent_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY status (status,id),
  KEY user_id (user_id),
  KEY created_at (created_at)
) {$charset};" );

        // Autoloaded defaults, so reading them never costs a query. add_option() leaves existing values alone.
        add_option( 'wa_app_mode', 'off' );
        add_option( 'wa_app_testers', array() );
        add_option( 'wa_app_min_version', '1.0.0' );

        update_option( 'wa_app_db_version', WA_APP_DB_VERSION );
    }
}
