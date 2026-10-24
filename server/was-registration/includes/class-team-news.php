<?php
defined( 'ABSPATH' ) || exit;

/**
 * Team News — coach-submitted news posts, moderated by social media officers,
 * treasurers or admins, published as real WordPress posts in a per-team category
 * (parent category "team-news"), and optionally pushed to the club's Facebook
 * page. Public [was_team_pages] shortcode renders the all-teams grid and each
 * team's news list.
 */
class WAS_Team_News {

    const META_TEAM_ID   = '_was_news_team_id';
    const META_TEAM_SLUG = '_was_news_team_slug';
    const META_COACH     = '_was_news_coach_id';
    const META_PHOTOS    = '_was_news_photos';
    const META_FB_STATUS = '_was_news_fb_status';   // none | manual | api
    const META_FB_POST   = '_was_news_fb_post_id';
    const META_REJECT    = '_was_news_reject_reason';
    const META_TYPE      = '_was_news_type';        // match_report | general_news | shoutout
    const META_SOCIAL    = '_was_news_social';      // 1 = coach suggested it for the club's socials
    const MAX_PHOTOS     = 5;

    public static function type_label( $type ) {
        $map = array(
            'match_report' => 'Match Report',
            'general_news' => 'General News',
            'shoutout'     => 'Shoutout',
        );
        return $map[ $type ] ?? '';
    }

    public static function init() {
        add_shortcode( 'was_team_pages',     array( __CLASS__, 'render_team_pages' ) );
        add_shortcode( 'was_team_news_feed', array( __CLASS__, 'render_news_feed' ) );

        // Old static team pages 301 to the Team Profiles page
        add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_pages' ) );

        add_action( 'wp_ajax_was_news_submit',  array( __CLASS__, 'ajax_submit' ) );
        add_action( 'wp_ajax_was_news_update',  array( __CLASS__, 'ajax_update' ) );
        add_action( 'wp_ajax_was_news_approve', array( __CLASS__, 'ajax_approve' ) );
        add_action( 'wp_ajax_was_news_reject',  array( __CLASS__, 'ajax_reject' ) );
        add_action( 'wp_ajax_was_news_fb_push', array( __CLASS__, 'ajax_fb_push' ) );
        add_action( 'wp_ajax_was_news_fb_mark', array( __CLASS__, 'ajax_fb_mark' ) );
    }

    // Legacy "Our Teams" pages (pre-2.14.0 static content) → the profiles page.
    public static function redirect_legacy_pages() {
        if ( is_page( array( 'our-teams-old', 'our-teams-u12-u16' ) ) ) {
            wp_safe_redirect( home_url( '/our-teams/' ), 301 );
            exit;
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public static function team_slug( $team ) {
        return sanitize_title( $team->name );
    }

    /**
     * The WP category holding a team's news. Keyed by a slug derived from the
     * team NAME (not the row id) so a season-rollover clone of the team reuses
     * the same category and its history carries over.
     */
    public static function category_id( $team ) {
        $parent = get_term_by( 'slug', 'team-news', 'category' );
        if ( $parent ) {
            $parent_id = (int) $parent->term_id;
        } else {
            $res = wp_insert_term( 'Team News', 'category', array( 'slug' => 'team-news' ) );
            if ( is_wp_error( $res ) ) {
                $existing  = $res->get_error_data( 'term_exists' );
                if ( ! $existing ) return 0;
                $parent_id = (int) $existing;
            } else {
                $parent_id = (int) $res['term_id'];
            }
        }

        $slug = 'team-' . self::team_slug( $team );
        $term = get_term_by( 'slug', $slug, 'category' );
        if ( $term ) return (int) $term->term_id;

        $res = wp_insert_term( $team->name, 'category', array( 'slug' => $slug, 'parent' => $parent_id ) );
        if ( is_wp_error( $res ) ) {
            $existing = $res->get_error_data( 'term_exists' );
            return $existing ? (int) $existing : 0;
        }
        return (int) $res['term_id'];
    }

    public static function find_team_by_slug( $slug ) {
        if ( ! $slug ) return null;
        foreach ( WAS_Database::get_teams( was_season() ) as $t ) {
            if ( self::team_slug( $t ) === $slug ) return $t;
        }
        return null;
    }

    // Names of active players in this team whose photography consent is 'disagree'.
    public static function no_photo_players( $team_id ) {
        $out = array();
        foreach ( WAS_Database::get_players_by_team( $team_id, was_season() ) as $p ) {
            if ( ( $p->registration_status ?? '' ) !== 'active' ) continue;
            $consents = WAS_Database::get_consents( $p->id );
            if ( ( $consents['photography'] ?? 'agree' ) === 'disagree' ) {
                $out[] = trim( $p->first_name . ' ' . $p->last_name );
            }
        }
        return $out;
    }

    // Guard: our AJAX endpoints must only ever touch posts this feature created.
    public static function is_news_post( $post_id ) {
        return $post_id && metadata_exists( 'post', $post_id, self::META_TEAM_SLUG );
    }

    public static function is_reviewer() {
        return was_has_role( 'social_media' ) || was_has_role( 'treasurer' ) || was_has_role( 'committee' );
    }

    public static function photo_ids( $post_id ) {
        $ids = get_post_meta( $post_id, self::META_PHOTOS, true );
        return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
    }

    // ── Query helpers ────────────────────────────────────────────────────────

    public static function get_coach_posts( $user_id, $limit = 10 ) {
        return get_posts( array(
            'post_type'      => 'post',
            'post_status'    => array( 'pending', 'publish', 'draft' ),
            'posts_per_page' => $limit,
            'meta_key'       => self::META_COACH,
            'meta_value'     => (string) $user_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
    }

    public static function get_pending_posts() {
        return get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'pending',
            'posts_per_page' => -1,
            'meta_key'       => self::META_TEAM_SLUG,
            'meta_compare'   => 'EXISTS',
            'orderby'        => 'date',
            'order'          => 'ASC',
        ) );
    }

    public static function get_recent_published( $limit = 10 ) {
        return get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_key'       => self::META_TEAM_SLUG,
            'meta_compare'   => 'EXISTS',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
    }

    // ── Public page: [was_team_pages] ────────────────────────────────────────

    public static function render_team_pages( $atts = array() ) {
        $slug = sanitize_title( wp_unslash( $_GET['was_team'] ?? '' ) );
        $view = 'grid';
        $team = null;
        $news = null;
        $page = 1;

        if ( $slug ) {
            $team = self::find_team_by_slug( $slug );
            if ( $team ) {
                $view = 'team';
                $page = max( 1, absint( $_GET['news_page'] ?? 1 ) );
                $news = new WP_Query( array(
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'cat'            => self::category_id( $team ),
                    'posts_per_page' => 9,
                    'paged'          => $page,
                ) );
            }
        }

        $teams = ( $view === 'grid' ) ? WAS_Database::get_teams( was_season() ) : array();

        // Grid is ordered youngest first (teams without an age band sort last, A-Z)
        usort( $teams, function( $a, $b ) {
            $aa = $a->age_band ? (int) $a->age_band : 99;
            $bb = $b->age_band ? (int) $b->age_band : 99;
            return $aa === $bb ? strcasecmp( $a->name, $b->name ) : $aa - $bb;
        } );

        ob_start();
        include WAS_PATH . 'templates/team-pages.php';
        if ( $news ) wp_reset_postdata();
        return ob_get_clean();
    }

    // ── Public page: [was_team_news_feed] (all teams, masonry/social feed) ───

    public static function render_news_feed( $atts = array() ) {
        $page = max( 1, absint( $_GET['news_page'] ?? 1 ) );
        $feed = new WP_Query( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'paged'          => $page,
            'meta_key'       => self::META_TEAM_SLUG,
            'meta_compare'   => 'EXISTS',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        ob_start();
        include WAS_PATH . 'templates/team-news-feed.php';
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * URL of a team's public profile (the page holding [was_team_pages]).
     * Returns '' if that page can't be found.
     */
    public static function profile_url( $slug ) {
        static $base = null;
        if ( $base === null ) {
            $page = get_page_by_path( 'our-teams' );
            if ( ! $page || $page->post_status !== 'publish' || ! has_shortcode( $page->post_content, 'was_team_pages' ) ) {
                global $wpdb;
                $id = $wpdb->get_var(
                    "SELECT ID FROM {$wpdb->posts}
                     WHERE post_type = 'page' AND post_status = 'publish'
                       AND post_content LIKE '%[was_team_pages%'
                     ORDER BY ID ASC LIMIT 1"
                );
                $page = $id ? get_post( $id ) : null;
            }
            $base = $page ? get_permalink( $page ) : '';
        }
        if ( ! $base || ! $slug ) return '';
        return add_query_arg( 'was_team', $slug, $base );
    }

    // Team row for a news post (team name/logo on feed cards). Falls back to null.
    public static function post_team( $post_id ) {
        $team_id = (int) get_post_meta( $post_id, self::META_TEAM_ID, true );
        return $team_id ? WAS_Database::get_team( $team_id ) : null;
    }

    // ── AJAX: coach submits a news item ──────────────────────────────────────

    public static function ajax_submit() {
        self::guard_oversized_post();
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() || ! was_has_role( 'coach' ) ) {
            wp_send_json_error( array( 'message' => 'Not authorised.' ) );
        }

        $user_id = get_current_user_id();
        $team_id = absint( $_POST['team_id'] ?? 0 );

        // Must be a coach assigned to this team, or admin
        if ( ! current_user_can( 'manage_options' ) ) {
            $team_ids = array_map( function( $t ) { return (int) $t->id; }, WAS_Accounts::get_coach_teams( $user_id ) );
            if ( ! in_array( $team_id, $team_ids, true ) ) {
                wp_send_json_error( array( 'message' => 'Not authorised for this team.' ) );
            }
        }

        $team = WAS_Database::get_team( $team_id );
        if ( ! $team || ! $team->active || $team->season !== was_season() ) {
            wp_send_json_error( array( 'message' => 'Team not found for the current season.' ) );
        }

        $title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
        if ( $title === '' )   wp_send_json_error( array( 'message' => 'Please add a title.' ) );
        if ( $content === '' ) wp_send_json_error( array( 'message' => 'Please add a short description.' ) );

        $cat_id  = self::category_id( $team );
        $post_id = wp_insert_post( array(
            'post_type'     => 'post',
            'post_status'   => 'pending',
            'post_title'    => $title,
            'post_content'  => $content,
            'post_author'   => $user_id,
            'post_category' => $cat_id ? array( $cat_id ) : array(),
        ), true );
        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Could not save the post. Please try again.' ) );
        }

        $type = sanitize_key( $_POST['news_type'] ?? '' );
        if ( ! self::type_label( $type ) ) $type = 'general_news';

        update_post_meta( $post_id, self::META_TEAM_ID,   $team_id );
        update_post_meta( $post_id, self::META_TEAM_SLUG, self::team_slug( $team ) );
        update_post_meta( $post_id, self::META_COACH,     $user_id );
        update_post_meta( $post_id, self::META_FB_STATUS, 'none' );
        update_post_meta( $post_id, self::META_TYPE,      $type );
        update_post_meta( $post_id, self::META_SOCIAL,    empty( $_POST['social_queue'] ) ? 0 : 1 );

        $photos = self::attach_photos( $post_id );
        if ( is_wp_error( $photos ) ) {
            wp_delete_post( $post_id, true );
            wp_send_json_error( array( 'message' => $photos->get_error_message() ) );
        }
        if ( $photos ) {
            set_post_thumbnail( $post_id, $photos[0] );
        }

        WAS_Notifications::team_news_submitted( $post_id, $team );

        wp_send_json_success( array(
            'message' => 'Thanks — your news item has been sent for review. It will appear on the website once approved.',
            'post_id' => $post_id,
        ) );
    }

    // ── AJAX: reviewer actions ───────────────────────────────────────────────

    public static function ajax_update() {
        self::guard_oversized_post();
        $post_id = self::reviewer_post_or_die();
        if ( get_post_status( $post_id ) !== 'pending' ) {
            wp_send_json_error( array( 'message' => 'Only pending posts can be edited here.' ) );
        }
        $err = self::apply_edits( $post_id );
        if ( $err ) wp_send_json_error( array( 'message' => $err ) );
        wp_send_json_success( array( 'message' => 'Changes saved.' ) );
    }

    public static function ajax_approve() {
        self::guard_oversized_post();
        $post_id = self::reviewer_post_or_die();

        $err = self::apply_edits( $post_id );
        if ( $err ) wp_send_json_error( array( 'message' => $err ) );

        wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );

        // Re-assert the team category in case it was dropped by a wp-admin edit
        $team_id = (int) get_post_meta( $post_id, self::META_TEAM_ID, true );
        $team    = $team_id ? WAS_Database::get_team( $team_id ) : null;
        if ( ! $team ) {
            $team = self::find_team_by_slug( get_post_meta( $post_id, self::META_TEAM_SLUG, true ) );
        }
        if ( $team ) {
            $cat_id = self::category_id( $team );
            if ( $cat_id ) wp_set_post_categories( $post_id, array( $cat_id ) );
        }

        delete_post_meta( $post_id, self::META_REJECT );

        WAS_Notifications::team_news_decision( $post_id, 'approved', '' );

        wp_send_json_success( array(
            'message'   => 'Published.',
            'permalink' => get_permalink( $post_id ),
        ) );
    }

    public static function ajax_reject() {
        $post_id = self::reviewer_post_or_die();
        if ( get_post_status( $post_id ) !== 'pending' ) {
            wp_send_json_error( array( 'message' => 'Only pending posts can be rejected.' ) );
        }
        $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
        wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
        update_post_meta( $post_id, self::META_REJECT, $reason );
        WAS_Notifications::team_news_decision( $post_id, 'rejected', $reason );
        wp_send_json_success( array( 'message' => 'Rejected — the coach has been notified.' ) );
    }

    public static function ajax_fb_mark() {
        $post_id = self::reviewer_post_or_die();
        if ( get_post_status( $post_id ) !== 'publish' ) {
            wp_send_json_error( array( 'message' => 'Only published posts can be marked as posted.' ) );
        }
        update_post_meta( $post_id, self::META_FB_STATUS, 'manual' );
        wp_send_json_success( array( 'message' => 'Marked as posted to Facebook.' ) );
    }

    public static function ajax_fb_push() {
        $post_id = self::reviewer_post_or_die();
        if ( get_post_status( $post_id ) !== 'publish' ) {
            wp_send_json_error( array( 'message' => 'Only published posts can be pushed to Facebook.' ) );
        }
        if ( ! WAS_Facebook::is_enabled() ) {
            wp_send_json_error( array( 'message' => 'Facebook API posting is not enabled. Set it up in ASTRO → Settings.' ) );
        }
        $res = WAS_Facebook::push_post( $post_id );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( array( 'message' => 'Facebook error: ' . $res->get_error_message() ) );
        }
        update_post_meta( $post_id, self::META_FB_POST,   $res );
        update_post_meta( $post_id, self::META_FB_STATUS, 'api' );
        wp_send_json_success( array( 'message' => 'Posted to Facebook.', 'fb_post_id' => $res ) );
    }

    // ── Internals ────────────────────────────────────────────────────────────

    // A POST bigger than the server limit arrives with $_POST/$_FILES empty —
    // catch that before the nonce check so the user gets a real message.
    private static function guard_oversized_post() {
        if ( empty( $_POST ) && empty( $_FILES ) && (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 ) > 0 ) {
            wp_send_json_error( array( 'message' => 'Upload too large — the combined size of your photos exceeds the server limit. Try fewer or smaller photos.' ) );
        }
    }

    private static function reviewer_post_or_die() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() || ! self::is_reviewer() ) {
            wp_send_json_error( array( 'message' => 'Not authorised.' ) );
        }
        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! self::is_news_post( $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Post not found.' ) );
        }
        return $post_id;
    }

    // Shared by update + approve: optional title/content edits, photo removals,
    // new photo uploads, featured-image resync. Returns an error string or null.
    private static function apply_edits( $post_id ) {
        $update = array( 'ID' => $post_id );
        if ( isset( $_POST['title'] ) ) {
            $title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
            if ( $title === '' ) return 'The title cannot be empty.';
            $update['post_title'] = $title;
        }
        if ( isset( $_POST['content'] ) ) {
            $content = sanitize_textarea_field( wp_unslash( $_POST['content'] ) );
            if ( $content === '' ) return 'The description cannot be empty.';
            $update['post_content'] = $content;
        }
        if ( count( $update ) > 1 ) {
            wp_update_post( $update );
        }

        // Remove photos (ids must belong to this post's photo list)
        $photos = self::photo_ids( $post_id );
        $remove = array_map( 'absint', (array) ( $_POST['remove_ids'] ?? array() ) );
        if ( $remove ) {
            foreach ( $remove as $rid ) {
                if ( ! in_array( $rid, $photos, true ) ) continue;
                $att = get_post( $rid );
                if ( $att && (int) $att->post_parent === (int) $post_id ) {
                    wp_delete_attachment( $rid, true );
                }
                $photos = array_values( array_diff( $photos, array( $rid ) ) );
            }
            update_post_meta( $post_id, self::META_PHOTOS, $photos );
        }

        // Add photos
        $added = self::attach_photos( $post_id );
        if ( is_wp_error( $added ) ) return $added->get_error_message();

        // Featured image tracks the first photo
        $photos = self::photo_ids( $post_id );
        if ( $photos ) {
            set_post_thumbnail( $post_id, $photos[0] );
        } else {
            delete_post_thumbnail( $post_id );
        }
        return null;
    }

    /**
     * Media-library upload of everything in $_FILES['photos'] (a multi-file
     * input's parallel arrays). Enforces the 5-photo cap counting existing
     * photos, validates type/size, and cleans up after itself on failure.
     * Returns the new attachment ids (possibly empty), or WP_Error.
     */
    private static function attach_photos( $post_id ) {
        if ( empty( $_FILES['photos'] ) || empty( $_FILES['photos']['name'] ) ) return array();

        $names     = (array) $_FILES['photos']['name'];
        $count_new = 0;
        foreach ( $names as $n ) {
            if ( $n !== '' ) $count_new++;
        }
        if ( ! $count_new ) return array();

        $existing = self::photo_ids( $post_id );
        if ( count( $existing ) + $count_new > self::MAX_PHOTOS ) {
            return new WP_Error( 'was_news_too_many', 'A post can have at most ' . self::MAX_PHOTOS . ' photos (this one would have ' . ( count( $existing ) + $count_new ) . ').' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $allowed = array( 'jpg', 'jpeg', 'png', 'webp' );
        $created = array();

        foreach ( $names as $i => $name ) {
            if ( $name === '' ) continue;

            $fail = '';
            $ext  = strtolower( pathinfo( sanitize_file_name( $name ), PATHINFO_EXTENSION ) );

            if ( (int) $_FILES['photos']['error'][ $i ] !== UPLOAD_ERR_OK ) {
                $fail = 'One of the photos failed to upload. Please try again.';
            } elseif ( ! in_array( $ext, $allowed, true ) ) {
                $fail = '"' . $name . '" is not a supported image type (jpg, png or webp).';
            } elseif ( (int) $_FILES['photos']['size'][ $i ] > 8 * 1024 * 1024 ) {
                $fail = '"' . $name . '" is too large. Each photo must be under 8MB.';
            } else {
                // Check it's a real image and save a re-encoded copy (no EXIF/GPS location
                // data — these photos are published), then hand that copy to the media library.
                $clean = was_save_uploaded_image( array(
                    'name'     => $_FILES['photos']['name'][ $i ],
                    'tmp_name' => $_FILES['photos']['tmp_name'][ $i ],
                    'error'    => $_FILES['photos']['error'][ $i ],
                    'size'     => $_FILES['photos']['size'][ $i ],
                ), get_temp_dir(), 'was-news-' . wp_generate_password( 12, false ) );

                if ( is_wp_error( $clean ) ) {
                    $fail = '"' . $name . '": ' . $clean->get_error_message();
                } else {
                    $base = sanitize_file_name( pathinfo( $name, PATHINFO_FILENAME ) );
                    $att  = media_handle_sideload( array(
                        'name'     => ( $base !== '' ? $base : 'photo' ) . '.' . pathinfo( $clean['file'], PATHINFO_EXTENSION ),
                        'tmp_name' => $clean['path'],
                    ), $post_id );
                    if ( file_exists( $clean['path'] ) ) @unlink( $clean['path'] ); // only left behind if the sideload failed
                    if ( is_wp_error( $att ) ) {
                        $fail = 'Could not save "' . $name . '": ' . $att->get_error_message();
                    } else {
                        $created[] = (int) $att;
                        continue;
                    }
                }
            }

            // A file failed — remove anything this call already created
            foreach ( $created as $aid ) {
                wp_delete_attachment( $aid, true );
            }
            return new WP_Error( 'was_news_photo', $fail );
        }

        if ( $created ) {
            update_post_meta( $post_id, self::META_PHOTOS, array_merge( $existing, $created ) );
        }
        return $created;
    }
}
