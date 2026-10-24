<?php
defined( 'ABSPATH' ) || exit;

/**
 * The app inbox (was_inbox): one row per member per item — a club message, team news,
 * and later payment or welcome notes. The app reads it through WAS_Rest.
 *
 * Rows are only written for people the Allstars app lets in (the was_rest_user_allowed
 * filter, added by the separate allstars-app plugin). While the app is switched off —
 * or that plugin isn't installed — nothing is stored and no hook is fired.
 *
 * Emails are unaffected: these helpers run after the emails have gone, and any problem
 * in them (or in a listener on the hooks) is logged and never stops a send.
 */
class WAS_Inbox {

    const TYPES     = array( 'club_message', 'team_news', 'payment', 'welcome' );
    const PAGE_SIZE = 50;

    /** Club roles an admin gives out. An account holding one is trusted with its own email address. */
    const STAFF_ROLES = array( 'coach', 'committee', 'treasurer', 'social_media', 'registration_officer' );

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'was_inbox';
    }

    /**
     * Add one item to a member's inbox. Low-level: callers decide who gets it (see
     * app_users()). Returns the new row ID, or 0.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $type    One of self::TYPES.
     * @param string $title   Plain text.
     * @param string $body    Plain text.
     * @param array  $data    Extra details for the app, stored as JSON (e.g. 'from', 'image').
     * @param int    $ref_id  The club message or post this item is about, or 0.
     */
    public static function add( $user_id, $type, $title, $body, $data = array(), $ref_id = 0 ) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ( $user_id <= 0 || ! in_array( $type, self::TYPES, true ) ) return 0;

        $json = wp_json_encode( (array) $data );
        $ok   = $wpdb->insert(
            self::table(),
            array(
                'user_id'    => $user_id,
                'type'       => $type,
                'title'      => mb_substr( (string) $title, 0, 255 ),
                'body'       => (string) $body,
                'data'       => $json ? $json : '{}',
                'ref_id'     => max( 0, (int) $ref_id ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
        );
        return $ok ? (int) $wpdb->insert_id : 0;
    }

    /** When a WordPress account is deleted, its inbox goes with it (hooked on deleted_user). */
    public static function delete_for_user( $user_id ) {
        global $wpdb;
        $wpdb->delete( self::table(), array( 'user_id' => (int) $user_id ), array( '%d' ) );
    }

    // ── Reading (the member's own rows only) ───────────────────────────────────

    /** A page of a member's inbox, newest first. */
    public static function get_page( $user_id, $page = 1 ) {
        global $wpdb;
        $page = max( 1, (int) $page );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, type, title, body, data, created_at, read_at
             FROM " . self::table() . "
             WHERE user_id = %d
             ORDER BY created_at DESC, id DESC
             LIMIT %d OFFSET %d",
            (int) $user_id, self::PAGE_SIZE, ( $page - 1 ) * self::PAGE_SIZE
        ) );
    }

    public static function unread_count( $user_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table() . " WHERE user_id = %d AND read_at IS NULL",
            (int) $user_id
        ) );
    }

    /** Mark one of a member's items as read. False if it isn't one of theirs. */
    public static function mark_read( $user_id, $id ) {
        global $wpdb;
        $own = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE id = %d AND user_id = %d",
            (int) $id, (int) $user_id
        ) );
        if ( ! $own ) return false;
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . self::table() . " SET read_at = %s WHERE id = %d AND user_id = %d AND read_at IS NULL",
            current_time( 'mysql' ), (int) $id, (int) $user_id
        ) );
        return true;
    }

    // ── Who gets an item ───────────────────────────────────────────────────────

    /**
     * The members, out of $user_ids, who use the app: the Allstars app plugin lets them in
     * and they haven't closed their account. Empty while the app is off.
     */
    public static function app_users( array $user_ids ) {
        if ( ! has_filter( 'was_rest_user_allowed' ) ) return array();
        $out = array();
        foreach ( array_unique( array_map( 'intval', $user_ids ) ) as $uid ) {
            if ( $uid <= 0 || WAS_Accounts::login_disabled( $uid ) ) continue;
            if ( apply_filters( 'was_rest_user_allowed', false, $uid ) ) {
                $out[] = $uid;
            }
        }
        return $out;
    }

    /**
     * The accounts behind a list of email addresses (club message recipients):
     *  - the account linked to a family record with that address (linked at sign-up, or
     *    once the account confirmed it owns the address); and
     *  - an account with that address that holds a club role, or has confirmed it.
     * An account that has merely signed up with someone else's address gets nothing.
     */
    public static function user_ids_for_emails( array $emails ) {
        global $wpdb;
        $emails = array_values( array_unique( array_filter(
            array_map( function ( $e ) { return strtolower( trim( (string) $e ) ); }, $emails ),
            'is_email'
        ) ) );
        if ( ! $emails ) return array();
        $in = implode( ',', array_fill( 0, count( $emails ), '%s' ) );

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT wp_user_id FROM {$wpdb->prefix}was_guardians
             WHERE LOWER(email) IN ( $in ) AND wp_user_id > 0",
            $emails
        ) );

        $users = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE user_email IN ( $in )",
            $emails
        ) );
        foreach ( $users as $uid ) {
            $uid = (int) $uid;
            if ( array_intersect( self::STAFF_ROLES, was_get_roles( $uid ) ) || WAS_Accounts::is_email_verified( $uid ) ) {
                $ids[] = $uid;
            }
        }
        return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
    }

    /** Of $user_ids, those who already have this item (type + ref_id). */
    private static function users_with( $type, $ref_id, array $user_ids ) {
        global $wpdb;
        $user_ids = array_filter( array_map( 'intval', $user_ids ) );
        if ( ! $ref_id || ! $user_ids ) return array();
        $in = implode( ',', $user_ids );
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id FROM " . self::table() . " WHERE type = %s AND ref_id = %d AND user_id IN ( $in )",
            $type, (int) $ref_id
        ) ) );
    }

    // ── Deliveries ─────────────────────────────────────────────────────────────

    /**
     * A batch of a club message has just been emailed to $emails: put it in the inbox of
     * each app user among them (once each), then tell the app plugin (was_message_delivered).
     *
     * @param int    $message_id was_messages row, or 0 if there isn't one yet ("send now").
     * @param array  $emails     The addresses this batch was sent to.
     * @param string $subject    Message subject.
     * @param string $body       Plain-text message body.
     * @param string $from       Who it's from, as the app shows it.
     * @param array  $already    User IDs who already have this message (earlier batches of
     *                           a "send now"; scheduled sends are checked against the table).
     * @return int[] The user IDs it was delivered to in this batch.
     */
    public static function deliver_club_message( $message_id, array $emails, $subject, $body, $from, array $already = array() ) {
        try {
            $user_ids = array_diff( self::app_users( self::user_ids_for_emails( $emails ) ), array_map( 'intval', $already ) );
            if ( $message_id ) {
                $user_ids = array_diff( $user_ids, self::users_with( 'club_message', $message_id, $user_ids ) );
            }
            $user_ids = array_values( $user_ids );
            if ( ! $user_ids ) return array();

            $title = trim( (string) $subject );
            $text  = self::excerpt( $body, 600 );
            foreach ( $user_ids as $uid ) {
                self::add( $uid, 'club_message', $title, $text, array( 'from' => (string) $from ), (int) $message_id );
            }

            do_action( 'was_message_delivered', (int) $message_id, $user_ids, array(
                'type'  => 'club_message',
                'title' => $title,
                'body'  => self::excerpt( $body, 140, true ),
            ) );
            return $user_ids;
        } catch ( Throwable $e ) {
            error_log( '[WAS] Inbox: club message delivery skipped — ' . $e->getMessage() );
            return array();
        }
    }

    /**
     * A team news post has just been published: put it in the inbox of the app users who
     * are parents of active players on that team (main or additional team), then tell the
     * app plugin (was_team_news_published). Once per post.
     */
    public static function deliver_team_news( $post_id, $team ) {
        global $wpdb;
        try {
            $post_id = (int) $post_id;
            if ( ! $post_id || ! $team ) return;

            $guardian_users = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT g.wp_user_id FROM {$wpdb->prefix}was_guardians g
                 JOIN {$wpdb->prefix}was_players p ON p.primary_guardian_id = g.id
                 WHERE p.season = %s AND p.registration_status = 'active' AND g.wp_user_id > 0
                   AND ( p.team_id = %d OR EXISTS (
                         SELECT 1 FROM {$wpdb->prefix}was_player_teams pt
                         WHERE pt.player_id = p.id AND pt.team_id = %d ) )",
                was_season(), (int) $team->id, (int) $team->id
            ) );
            $user_ids = self::app_users( $guardian_users );
            if ( ! $user_ids ) return;

            // Approving the same post twice (or re-publishing it) must not repeat it.
            if ( ! add_post_meta( $post_id, '_was_news_inbox_sent', time(), true ) ) return;
            $user_ids = array_values( array_diff( $user_ids, self::users_with( 'team_news', $post_id, $user_ids ) ) );
            if ( ! $user_ids ) return;

            $name = trim( (string) $team->name );
            $age  = trim( (string) $team->age_group );
            if ( $age !== '' && stripos( $name, $age ) === false ) {
                $name = $age . ' ' . $name;
            }
            $title = 'New ' . $name . ' team news';
            $body  = self::excerpt( get_post_field( 'post_title', $post_id, 'raw' ), 255, true );
            $image = ! empty( $team->logo_path ) ? was_logo_url( $team->logo_path ) : null;
            $data  = array(
                'image'   => $image,
                'url'     => get_permalink( $post_id ),
                'team_id' => (int) $team->id,
            );

            foreach ( $user_ids as $uid ) {
                self::add( $uid, 'team_news', $title, $body, $data, $post_id );
            }

            do_action( 'was_team_news_published', $post_id, (int) $team->id, $user_ids, array(
                'type'  => 'team_news',
                'title' => $title,
                'body'  => $body,
                'image' => $image,
            ) );
        } catch ( Throwable $e ) {
            error_log( '[WAS] Inbox: team news delivery skipped — ' . $e->getMessage() );
        }
    }

    // ── Text ───────────────────────────────────────────────────────────────────

    /**
     * Plain text cut to at most $max characters (at a word where possible, with "…").
     * $one_line folds all whitespace into single spaces (push notification text).
     */
    public static function excerpt( $text, $max, $one_line = false ) {
        $text  = str_replace( "\r", '', (string) $text );
        $clean = $one_line ? preg_replace( '/\s+/u', ' ', $text ) : preg_replace( "/\n{3,}/", "\n\n", $text );
        $text  = trim( is_string( $clean ) ? $clean : $text );
        if ( mb_strlen( $text ) <= $max ) return $text;

        $cut   = mb_substr( $text, 0, $max - 1 );
        $space = mb_strrpos( $cut, ' ' );
        if ( $space !== false && $space > $max * 0.6 ) {
            $cut = mb_substr( $cut, 0, $space );
        }
        return rtrim( $cut, " \t\n,.;:-" ) . '…';
    }
}
