<?php
/**
 * Read-only club content for the app: GET /config, /content/news, /content/home and
 * /content/pages/{slug}.
 *
 * News is ordinary WordPress posts. A post with meta _was_news_team_id is team news (ASTRO);
 * a post in the "Adults Team News" category is adults news; everything else is club news.
 * Home fixtures come from the allstars-adult-teams plugin's cached Full-Time data (option aat_data).
 * Club information pages are sent as text blocks (WA_App_Page_Text) straight from the page
 * itself, so editing a page in WordPress changes the website and the app together.
 */

defined( 'ABSPATH' ) || exit;

class WA_App_Content {

    const PER_PAGE           = 20;
    const FIXTURES_TRANSIENT = 'wa_app_home_fixtures';
    const PAGES_TRANSIENT    = 'wa_app_config_pages_2';
    /** + page slug. The number changes when the block format does, so old copies are never served. */
    const PAGE_TRANSIENT     = 'wa_app_page_1_';
    const META_TEAM_ID       = '_was_news_team_id';
    const META_TEAM_SLUG     = '_was_news_team_slug';

    /** @var array<int, object|null> ASTRO team rows looked up this request. */
    private static $teams = array();

    public static function init() {
        // Drop cached copies as soon as their sources change.
        add_action( 'update_option_aat_data', array( __CLASS__, 'flush_fixtures' ) );
        add_action( 'update_option_aat_slots', array( __CLASS__, 'flush_fixtures' ) );
        add_action( 'save_post_page', array( __CLASS__, 'flush_pages' ) );
    }

    public static function flush_fixtures() {
        delete_transient( self::FIXTURES_TRANSIENT );
    }

    public static function flush_pages( $post_id = 0 ) {
        delete_transient( self::PAGES_TRANSIENT );
        $slug = $post_id ? get_post_field( 'post_name', (int) $post_id ) : '';
        if ( is_string( $slug ) && '' !== $slug ) {
            delete_transient( self::PAGE_TRANSIENT . $slug );
        }
    }

    /**
     * The More tab's club pages: [ title, path, shown as text in the app ].
     * Pages that are mostly a form or a plugin the API can't render stay on the website.
     */
    private static function page_defs(): array {
        return array(
            'club' => array(
                array( 'Our story', '/our-story/', true ),
                array( 'Walking football', '/walking-football/', true ),
                array( 'Meet the committee', '/meet-the-committee/', true ),
                array( 'Our sponsors', '/sponsors-directory/', false ),
                array( 'Girls & ladies', '/girls-ladies-football/', true ),
                array( 'Coach or volunteer', '/coaching-opportunities/', true ),
            ),
            'help' => array(
                array( 'Safeguarding', '/safeguarding/', true ),
                array( 'Documents & policies', '/document-library/', true ),
                array( 'Contact the club', '/contact-us/', false ),
                array( 'Privacy policy', '/privacy-policy/', true ),
            ),
        );
    }

    /** Slugs the app may fetch as text. */
    public static function text_page_slugs(): array {
        $slugs = array();
        foreach ( self::page_defs() as $defs ) {
            foreach ( $defs as $def ) {
                if ( $def[2] ) {
                    $slugs[] = trim( $def[1], '/' );
                }
            }
        }
        /** Change which page slugs the app can show as text (GET /content/pages/{slug}). */
        return array_values( array_filter( (array) apply_filters( 'wa_app_text_pages', $slugs ), 'is_string' ) );
    }

    // ── GET /config ──────────────────────────────────────────────────────────

    public static function rest_config( WP_REST_Request $request ) {
        $pages = get_transient( self::PAGES_TRANSIENT );
        if ( ! is_array( $pages ) || ! isset( $pages['club'], $pages['help'] ) ) {
            $defs  = self::page_defs();
            $pages = array(
                'club' => self::links( $defs['club'] ),
                'help' => self::links( $defs['help'] ),
            );
            set_transient( self::PAGES_TRANSIENT, $pages, HOUR_IN_SECONDS );
        }

        $config = array(
            'minVersion' => self::min_version(),
            'storeUrls'  => self::store_urls(),
            'clubPages'  => $pages['club'],
            'helpPages'  => $pages['help'],
        );
        /** Change the app's config (minVersion, storeUrls, clubPages, helpPages). */
        return rest_ensure_response( apply_filters( 'wa_app_config', $config ) );
    }

    /** Store listing hosts, by platform. */
    const STORE_HOSTS = array(
        'ios'     => 'apps.apple.com',
        'android' => 'play.google.com',
    );

    /** { ios, android }: each the store listing for the update prompt, or null if not set. */
    public static function store_urls(): array {
        $out = array();
        foreach ( array_keys( self::STORE_HOSTS ) as $platform ) {
            $out[ $platform ] = self::clean_store_url( (string) get_option( 'wa_app_store_url_' . $platform, '' ), $platform );
        }
        return $out;
    }

    /** An https link on that platform's store, or null. */
    public static function clean_store_url( string $url, string $platform ): ?string {
        $host = self::STORE_HOSTS[ $platform ] ?? '';
        $url  = esc_url_raw( trim( $url ), array( 'https' ) );
        if ( '' === $host || '' === $url ) {
            return null;
        }
        $parts = wp_parse_url( $url );
        return ( is_array( $parts ) && isset( $parts['host'] ) && strtolower( $parts['host'] ) === $host ) ? $url : null;
    }

    public static function min_version(): string {
        $version = get_option( 'wa_app_min_version', '1.0.0' );
        return ( is_string( $version ) && preg_match( '/^\d+(\.\d+){0,3}$/', $version ) ) ? $version : '1.0.0';
    }

    /** page_defs() rows → InfoLink[] with the page's featured image when it has one. */
    private static function links( array $defs ): array {
        $text  = self::text_page_slugs();
        $out   = array();
        foreach ( $defs as $def ) {
            $title = (string) $def[0];
            $path  = (string) $def[1];
            $slug  = trim( $path, '/' );
            $image = null;
            $page  = get_page_by_path( $slug );
            if ( $page instanceof WP_Post ) {
                $url   = get_the_post_thumbnail_url( $page, 'medium_large' );
                $image = $url ? $url : null;
            }
            $out[] = array(
                'title'  => $title,
                'url'    => home_url( $path ),
                'image'  => $image,
                'slug'   => $slug,
                'native' => in_array( $slug, $text, true ),
            );
        }
        return $out;
    }

    // ── GET /content/pages/{slug} ────────────────────────────────────────────

    /** A club page as text blocks (cached for an hour, and dropped as soon as the page is saved). */
    public static function rest_page( WP_REST_Request $request ) {
        $slug = (string) $request->get_param( 'slug' );
        if ( ! in_array( $slug, self::text_page_slugs(), true ) ) {
            return wa_app_error( 'wa_page_not_found', "That page isn't available in the app.", 404 );
        }
        $cached = get_transient( self::PAGE_TRANSIENT . $slug );
        if ( is_array( $cached ) ) {
            return rest_ensure_response( $cached );
        }
        $post = get_page_by_path( $slug );
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
            return wa_app_error( 'wa_page_not_found', "That page isn't available in the app.", 404 );
        }
        $page = self::page_text( $post );
        set_transient( self::PAGE_TRANSIENT . $slug, $page, HOUR_IN_SECONDS );
        return rest_ensure_response( $page );
    }

    private static function page_text( WP_Post $post ): array {
        // Render the page exactly as the website does (shortcodes, blocks, the theme's builder).
        $GLOBALS['post'] = $post;
        setup_postdata( $post );
        $html = (string) apply_filters( 'the_content', $post->post_content );
        wp_reset_postdata();

        $title    = self::plain( get_the_title( $post ) );
        $modified = get_post_datetime( $post, 'modified' );
        return array(
            'slug'     => $post->post_name,
            'title'    => $title,
            'url'      => (string) get_permalink( $post ),
            'modified' => $modified ? $modified->format( 'c' ) : '',
            'blocks'   => WA_App_Page_Text::blocks( $html, home_url( '/' ), $title ),
        );
    }

    // ── GET /content/news ────────────────────────────────────────────────────

    public static function rest_news( WP_REST_Request $request ) {
        $filter = $request->get_param( 'filter' );
        $filter = ( is_string( $filter ) && in_array( $filter, array( 'all', 'mine', 'club', 'adults' ), true ) ) ? $filter : 'all';
        $page   = $request->get_param( 'page' );
        $page   = is_scalar( $page ) ? max( 1, min( 100, (int) $page ) ) : 1;
        $uid    = get_current_user_id();
        if ( 'mine' === $filter && ! $uid ) {
            return wa_app_error( 'wa_sign_in_required', "Please sign in to see your teams' news.", 401 );
        }
        return rest_ensure_response( self::news( $filter, $page, self::PER_PAGE, $uid, true ) );
    }

    /**
     * NewsItem[] newest first. 'featured' marks the first club item on page 1 when $mark_featured.
     */
    public static function news( string $filter, int $page, int $per_page, int $user_id, bool $mark_featured ): array {
        $args = array(
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => $per_page,
            'paged'               => $page,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
            'has_password'        => false,
        );
        $exclude = self::excluded_ids();
        if ( $exclude ) {
            $args['post__not_in'] = $exclude;
        }
        $adults = self::adults_term_ids();

        switch ( $filter ) {
            case 'mine':
                $meta = self::mine_meta_query( $user_id );
                if ( ! $meta ) {
                    return array();
                }
                $args['meta_query'] = $meta;
                break;
            case 'club':
                $args['meta_query'] = array(
                    array( 'key' => self::META_TEAM_ID, 'compare' => 'NOT EXISTS' ),
                );
                if ( $adults ) {
                    $args['category__not_in'] = $adults;
                }
                break;
            case 'adults':
                if ( ! $adults ) {
                    return array();
                }
                $args['category__in'] = $adults;
                break;
        }

        $query = new WP_Query( $args );
        if ( function_exists( 'update_post_thumbnail_cache' ) ) {
            update_post_thumbnail_cache( $query );
        }

        $items    = array();
        $featured = ! $mark_featured || $page > 1; // true = already handed out
        foreach ( $query->posts as $post ) {
            if ( ! ( $post instanceof WP_Post ) ) {
                continue;
            }
            $item             = self::news_item( $post, $adults );
            $item['featured'] = false;
            if ( ! $featured && 'club' === $item['kind'] ) {
                $item['featured'] = true;
                $featured         = true;
            }
            $items[] = $item;
        }
        return $items;
    }

    private static function news_item( WP_Post $post, array $adults ): array {
        $id   = (int) $post->ID;
        $cats = get_the_category( $id );
        $cats = is_array( $cats ) ? $cats : array();
        $meta = get_post_meta( $id, self::META_TEAM_ID, true );
        $kind = 'club';
        $team = '';
        if ( is_scalar( $meta ) && '' !== (string) $meta && '0' !== (string) $meta ) {
            $kind = 'team';
            $team = self::team_for_post( $id, (int) $meta, $cats );
        } elseif ( $adults && self::in_terms( $cats, $adults ) ) {
            $kind = 'adults';
        }

        $item = array(
            'id'    => $id,
            'title' => self::plain( get_the_title( $post ) ),
            'date'  => self::post_date( $post ),
            'kind'  => $kind,
        );
        $category = self::primary_category( $id, $cats );
        if ( '' !== $category ) {
            $item['category'] = $category;
        }
        if ( 'team' === $kind && '' !== $team ) {
            $item['team'] = $team;
        }
        $image         = get_the_post_thumbnail_url( $post, 'medium_large' );
        $item['image'] = $image ? $image : null;
        $item['url']   = (string) get_permalink( $post );
        return $item;
    }

    /** "U9 Tigers": ASTRO's team (age group + name), else its category under Team News, else the prettified slug. */
    private static function team_for_post( int $post_id, int $team_id, array $cats ): string {
        $label = self::team_label( $team_id );
        if ( '' !== $label ) {
            return $label;
        }
        $parent = self::team_news_parent_id();
        if ( $parent ) {
            foreach ( $cats as $cat ) {
                if ( $cat instanceof WP_Term && (int) $cat->parent === $parent ) {
                    return self::plain( $cat->name );
                }
            }
        }
        $slug = get_post_meta( $post_id, self::META_TEAM_SLUG, true );
        if ( is_string( $slug ) && '' !== $slug ) {
            return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
        }
        return '';
    }

    /** ASTRO team label with its age group in front ("U9 Tigers"), or '' if ASTRO doesn't know it. */
    public static function team_label( int $team_id ): string {
        $team = self::team_row( $team_id );
        if ( ! $team || empty( $team->name ) ) {
            return '';
        }
        $name = trim( (string) $team->name );
        $age  = isset( $team->age_group ) ? trim( (string) $team->age_group ) : '';
        if ( '' !== $age && false === stripos( $name, $age ) ) {
            $name = $age . ' ' . $name;
        }
        return self::plain( $name );
    }

    private static function team_row( int $team_id ) {
        if ( $team_id <= 0 || ! class_exists( 'WAS_Database' ) || ! method_exists( 'WAS_Database', 'get_team' ) ) {
            return null;
        }
        if ( ! array_key_exists( $team_id, self::$teams ) ) {
            $row                      = WAS_Database::get_team( $team_id );
            self::$teams[ $team_id ] = is_object( $row ) ? $row : null;
        }
        return self::$teams[ $team_id ];
    }

    /** Team IDs of the user's children (+ coached teams), from ASTRO. */
    public static function user_team_ids( int $user_id ): array {
        if ( $user_id <= 0 ) {
            return array();
        }
        $ids = apply_filters( 'was_user_team_ids', array(), $user_id );
        $out = array();
        foreach ( (array) $ids as $id ) {
            $id = is_scalar( $id ) ? (int) $id : 0;
            if ( $id > 0 ) {
                $out[ $id ] = $id;
            }
        }
        return array_values( $out );
    }

    /**
     * Posts for the user's teams: by team ID, or by team slug so news from before a season
     * rollover (when ASTRO clones teams with new IDs) still counts.
     */
    private static function mine_meta_query( int $user_id ): array {
        $team_ids = self::user_team_ids( $user_id );
        if ( ! $team_ids ) {
            return array();
        }
        $query = array(
            'relation' => 'OR',
            array( 'key' => self::META_TEAM_ID, 'value' => array_map( 'strval', $team_ids ), 'compare' => 'IN' ),
        );
        $slugs = array();
        foreach ( $team_ids as $team_id ) {
            $team = self::team_row( $team_id );
            if ( $team && ! empty( $team->name ) ) {
                $slugs[] = ( class_exists( 'WAS_Team_News' ) && method_exists( 'WAS_Team_News', 'team_slug' ) )
                    ? (string) WAS_Team_News::team_slug( $team )
                    : sanitize_title( (string) $team->name );
            }
        }
        $slugs = array_values( array_unique( array_filter( $slugs ) ) );
        if ( $slugs ) {
            $query[] = array( 'key' => self::META_TEAM_SLUG, 'value' => $slugs, 'compare' => 'IN' );
        }
        return $query;
    }

    /** The stray "Sample Post" never reaches the app. */
    private static function excluded_ids(): array {
        $ids  = array();
        $post = get_page_by_path( 'sample-post', OBJECT, 'post' );
        if ( $post instanceof WP_Post ) {
            $ids[] = (int) $post->ID;
        }
        return array_values( array_filter( array_map( 'intval', (array) apply_filters( 'wa_app_news_exclude', $ids ) ) ) );
    }

    /** "Adults Team News" (id 25 on live) and its children, resolved by slug or name. */
    private static function adults_term_ids(): array {
        static $ids = null;
        if ( null !== $ids ) {
            return $ids;
        }
        $term = get_term_by( 'slug', 'adults-team-news', 'category' );
        if ( ! $term ) {
            $term = get_term_by( 'name', 'Adults Team News', 'category' );
        }
        $found = array();
        if ( $term instanceof WP_Term ) {
            $found[] = (int) $term->term_id;
            $children = get_term_children( $term->term_id, 'category' );
            if ( is_array( $children ) ) {
                foreach ( $children as $child ) {
                    $found[] = (int) $child;
                }
            }
        }
        $ids = array_values( array_filter( array_map( 'intval', (array) apply_filters( 'wa_app_adults_category_ids', $found ) ) ) );
        return $ids;
    }

    private static function team_news_parent_id(): int {
        static $id = null;
        if ( null === $id ) {
            $term = get_term_by( 'slug', 'team-news', 'category' );
            $id   = $term instanceof WP_Term ? (int) $term->term_id : 0;
        }
        return $id;
    }

    private static function in_terms( array $cats, array $ids ): bool {
        foreach ( $cats as $cat ) {
            if ( $cat instanceof WP_Term && in_array( (int) $cat->term_id, $ids, true ) ) {
                return true;
            }
        }
        return false;
    }

    /** Yoast / Rank Math primary category, else the first real category. */
    private static function primary_category( int $post_id, array $cats ): string {
        foreach ( array( '_yoast_wpseo_primary_category', 'rank_math_primary_category' ) as $key ) {
            $primary = (int) get_post_meta( $post_id, $key, true );
            if ( $primary ) {
                foreach ( $cats as $cat ) {
                    if ( $cat instanceof WP_Term && (int) $cat->term_id === $primary ) {
                        return self::plain( $cat->name );
                    }
                }
            }
        }
        foreach ( $cats as $cat ) {
            if ( $cat instanceof WP_Term && 'uncategorized' !== $cat->slug ) {
                return self::plain( $cat->name );
            }
        }
        return '';
    }

    private static function post_date( WP_Post $post ): string {
        $dt = get_post_datetime( $post );
        return $dt ? $dt->format( 'c' ) : '';
    }

    /** Plain text for the app: no tags, no HTML entities. */
    private static function plain( $text ): string {
        $text = is_scalar( $text ) ? (string) $text : '';
        return trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    }

    // ── GET /content/home ────────────────────────────────────────────────────

    public static function rest_home( WP_REST_Request $request ) {
        $uid    = get_current_user_id();
        $latest = $uid ? self::news( 'mine', 1, 2, $uid, false ) : array();
        if ( ! $latest ) {
            $latest = self::news( 'club', 1, 2, 0, false );
        }
        return rest_ensure_response( array(
            'fixtures' => self::fixtures(),
            'latest'   => $latest,
        ) );
    }

    /** Next game for each adult team, soonest first (cached 10 minutes). */
    public static function fixtures(): array {
        $cached = get_transient( self::FIXTURES_TRANSIENT );
        if ( is_array( $cached ) ) {
            return $cached;
        }
        $list = self::build_fixtures( time() );
        set_transient( self::FIXTURES_TRANSIENT, $list, 10 * MINUTE_IN_SECONDS );
        return $list;
    }

    private static function build_fixtures( int $now ): array {
        $data  = get_option( 'aat_data', array() );
        $slots = get_option( 'aat_slots', array() );
        if ( ! is_array( $data ) ) {
            return array();
        }
        if ( ! is_array( $slots ) ) {
            $slots = array();
        }

        $found = array();
        foreach ( $data as $slot => $d ) {
            if ( ! is_array( $d ) || empty( $d['fetched_at'] ) || empty( $d['team_name'] ) || ! is_string( $d['team_name'] ) ) {
                continue;
            }
            $us = self::squash( $d['team_name'] );

            // Next game: first fixture from now on that is still on (no "P - P"/walkover status, not played).
            $fixtures = isset( $d['fixtures'] ) && is_array( $d['fixtures'] ) ? array_filter( $d['fixtures'], 'is_array' ) : array();
            usort( $fixtures, function ( $a, $b ) {
                return (int) ( $a['ts'] ?? 0 ) <=> (int) ( $b['ts'] ?? 0 );
            } );
            $next = null;
            foreach ( $fixtures as $f ) {
                if ( (int) ( $f['ts'] ?? 0 ) < $now || ! empty( $f['played'] ) || ! empty( $f['status'] ) ) {
                    continue;
                }
                $home = self::squash( $f['home'] ?? '' );
                $away = self::squash( $f['away'] ?? '' );
                if ( $home === $us || $away === $us ) {
                    $next = $f;
                    break;
                }
            }
            if ( ! $next ) {
                continue;
            }

            $is_home = self::squash( $next['home'] ?? '' ) === $us;
            $label   = isset( $slots[ $slot ]['label'] ) && is_string( $slots[ $slot ]['label'] ) && '' !== $slots[ $slot ]['label']
                ? $slots[ $slot ]['label']
                : $d['team_name'];

            $fixture = array(
                'team'     => self::plain( $label ),
                'opponent' => self::plain( $is_home ? ( $next['away'] ?? '' ) : ( $next['home'] ?? '' ) ),
                'homeAway' => $is_home ? 'H' : 'A',
                'kickoff'  => wa_app_iso_from_ts( (int) $next['ts'] ),
            );
            $venue = self::plain( $next['venue'] ?? '' );
            if ( '' !== $venue ) {
                $fixture['venue'] = $venue;
            }
            $league = self::plain( $d['division_name'] ?? '' );
            if ( '' === $league ) {
                $league = self::plain( $d['league_name'] ?? '' );
            }
            if ( '' !== $league ) {
                $fixture['league'] = $league;
            }

            // League position: our row in the table.
            foreach ( ( isset( $d['table'] ) && is_array( $d['table'] ) ? $d['table'] : array() ) as $row ) {
                if ( is_array( $row ) && self::squash( $row['team'] ?? '' ) === $us && (int) ( $row['pos'] ?? 0 ) > 0 ) {
                    $fixture['position'] = self::ordinal( (int) $row['pos'] );
                    break;
                }
            }

            // Form: the last (up to) 5 results, oldest first, from our side of the score.
            $results = isset( $d['results'] ) && is_array( $d['results'] ) ? array_filter( $d['results'], 'is_array' ) : array();
            usort( $results, function ( $a, $b ) {
                return (int) ( $a['ts'] ?? 0 ) <=> (int) ( $b['ts'] ?? 0 );
            } );
            $form = array();
            foreach ( $results as $r ) {
                if ( ! isset( $r['hs'], $r['as'] ) || ! is_numeric( $r['hs'] ) || ! is_numeric( $r['as'] ) ) {
                    continue;
                }
                $home = self::squash( $r['home'] ?? '' );
                $away = self::squash( $r['away'] ?? '' );
                if ( $home !== $us && $away !== $us ) {
                    continue;
                }
                $ours   = $home === $us ? (int) $r['hs'] : (int) $r['as'];
                $theirs = $home === $us ? (int) $r['as'] : (int) $r['hs'];
                $form[] = $ours > $theirs ? 'W' : ( $ours < $theirs ? 'L' : 'D' );
            }
            $form = array_slice( $form, -5 );
            if ( $form ) {
                $fixture['form'] = $form;
            }
            $fixture['photo'] = null;

            $found[] = array( 'ts' => (int) $next['ts'], 'fixture' => $fixture );
        }

        usort( $found, function ( $a, $b ) {
            return $a['ts'] <=> $b['ts'];
        } );
        return array_map( function ( $x ) {
            return $x['fixture'];
        }, $found );
    }

    /** Team names compared ignoring case and spaces ("Wombourne All Stars" = "Wombourne Allstars"). */
    private static function squash( $name ): string {
        $name = is_scalar( $name ) ? (string) $name : '';
        return strtolower( (string) preg_replace( '/\s+/u', '', html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) ) );
    }

    private static function ordinal( int $n ): string {
        if ( $n % 100 >= 11 && $n % 100 <= 13 ) {
            return $n . 'th';
        }
        switch ( $n % 10 ) {
            case 1:
                return $n . 'st';
            case 2:
                return $n . 'nd';
            case 3:
                return $n . 'rd';
            default:
                return $n . 'th';
        }
    }
}
