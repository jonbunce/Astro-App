<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Allstars_Adult_Teams — FA Full-Time fixtures, results and league tables.
 *
 * Full-Time has no public API, so each configured team ("slot") is simply the team's
 * public page on fulltime.thefa.com (displayTeam.html?...&teamID=NNN). Once a day
 * (WP-Cron) we fetch that page plus the division fixture list, the division results
 * list and the league table, parse the HTML and cache a structured copy in an option.
 * Since Oct 2026 Cloudflare challenges the server on nearly every run, so the same pages
 * are also accepted from a browser refresh via POST /wp-json/aat/v1/import (see rest_import).
 *
 * The shortcodes render ONLY from that cache. If Full-Time is down, blocks the
 * request or changes its markup, the page keeps showing the last good data and the
 * error is recorded for the Adult Teams admin screen.
 *
 * Shortcodes:  [adult-team-info-1] … [adult-team-info-10]     full section, one slot each
 *              [adult-team-info]                              every configured slot
 *              [adult-team-widget-1] … [adult-team-widget-10] compact card (up next + latest)
 *              attributes: show="next,fixtures,results,table"  limit="6"
 *
 * Crests: our own teams always use the club badge (Settings → Our badge). Opponents use
 * an admin override if one is set, otherwise the crest Full-Time publishes, otherwise a
 * neutral fallback — so a league with no crests uploaded never shows blank circles.
 */
class Allstars_Adult_Teams {

    const OPT_SLOTS    = 'aat_slots';
    const OPT_DATA     = 'aat_data';
    const OPT_LASTRUN  = 'aat_last_run';
    const OPT_CRESTS   = 'aat_crests';     // manual opponent crest overrides: normalised name => URL
    const OPT_SETTINGS = 'aat_settings';   // our_badge, fallback_crest
    const OPT_SERVER   = 'aat_server_status';  // array( blocked_at, message ) while Cloudflare challenges the server
    const OPT_IMPORTKEY = 'aat_import_key';    // sha256 of the key the browser refresh sends to /wp-json/aat/v1/import
    const CRON_HOOK    = 'aat_daily_sync';
    const BASE         = 'https://fulltime.thefa.com';
    const TZ           = 'Europe/London';
    const MAX_SLOTS    = 10;
    const CLUB_PREFIX  = 'wombourne allstars';   // any team whose name starts with this gets the club badge

    // ── Boot ──────────────────────────────────────────────────────────────────

    public static function init() {
        add_shortcode( 'adult-team-info',   array( __CLASS__, 'shortcode_all' ) );
        add_shortcode( 'adult-team-widget', array( __CLASS__, 'shortcode_widget' ) );
        for ( $n = 1; $n <= self::MAX_SLOTS; $n++ ) {
            add_shortcode( 'adult-team-info-' . $n, function( $atts = array() ) use ( $n ) {
                return self::render( $n, $atts );
            } );
            add_shortcode( 'adult-team-widget-' . $n, function( $atts = array() ) use ( $n ) {
                return self::render_widget( $n, $atts );
            } );
        }
        add_action( self::CRON_HOOK, array( __CLASS__, 'sync_all' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
        self::ensure_scheduled();
    }

    /** Daily at 06:00 UK time — results are keyed in on Saturday evening / Sunday, fixture changes midweek. */
    public static function ensure_scheduled() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            $first = new DateTime( 'tomorrow 06:00', new DateTimeZone( self::TZ ) );
            wp_schedule_event( $first->getTimestamp(), 'daily', self::CRON_HOOK );
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    // ── Slots (configured teams) ──────────────────────────────────────────────

    public static function get_slots() {
        $s = get_option( self::OPT_SLOTS, array() );
        if ( ! is_array( $s ) ) return array();
        ksort( $s, SORT_NUMERIC );
        return $s;
    }

    public static function get_data() {
        $d = get_option( self::OPT_DATA, array() );
        return is_array( $d ) ? $d : array();
    }

    /** Lowest unused slot number, or 0 if all are taken. */
    public static function next_free_slot() {
        $slots = self::get_slots();
        for ( $n = 1; $n <= self::MAX_SLOTS; $n++ ) {
            if ( ! isset( $slots[ $n ] ) ) return $n;
        }
        return 0;
    }

    /**
     * Build a slot from a Full-Time team URL. Only teamID is required — league,
     * season and division IDs are discovered from the team page on first sync.
     * Returns array|WP_Error.
     */
    public static function slot_from_url( $slot, $url, $label, $page_url = '' ) {
        $slot = (int) $slot;
        if ( $slot < 1 || $slot > self::MAX_SLOTS ) {
            return new WP_Error( 'aat_slot', sprintf( 'Choose a slot between 1 and %d.', self::MAX_SLOTS ) );
        }
        $url = trim( (string) $url );
        if ( ! preg_match( '~^https?://(www\.)?fulltime\.thefa\.com/~i', $url ) ) {
            return new WP_Error( 'aat_url', 'That is not a fulltime.thefa.com link.' );
        }
        $q = array();
        parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
        $team_id = isset( $q['teamID'] ) ? preg_replace( '/\D/', '', $q['teamID'] ) : '';
        if ( ! $team_id ) {
            return new WP_Error( 'aat_url', 'The link needs a teamID= parameter — copy the address of the team\'s own page on Full-Time (it contains displayTeam.html).' );
        }
        $label = sanitize_text_field( $label );
        if ( ! $label ) $label = 'Team ' . $slot;

        $params = array( 'teamID' => $team_id );
        if ( ! empty( $q['divisionseason'] ) ) {
            $params = array( 'divisionseason' => preg_replace( '/\D/', '', $q['divisionseason'] ) ) + $params;
        }
        return array(
            'slot'     => $slot,
            'label'    => $label,
            'team_id'  => $team_id,
            'url'      => self::BASE . '/displayTeam.html?' . http_build_query( $params ),
            'page_url' => esc_url_raw( trim( (string) $page_url ) ),
            'added'    => time(),
        );
    }

    public static function save_slot( array $s ) {
        $slots = self::get_slots();
        $slots[ (int) $s['slot'] ] = $s;
        update_option( self::OPT_SLOTS, $slots, false );
    }

    /** Edit the site-facing bits of an existing slot (name + page link). */
    public static function update_slot( $slot, $label, $page_url ) {
        $slots = self::get_slots();
        $slot  = (int) $slot;
        if ( ! isset( $slots[ $slot ] ) ) return false;
        $label = sanitize_text_field( $label );
        if ( $label ) $slots[ $slot ]['label'] = $label;
        $slots[ $slot ]['page_url'] = esc_url_raw( trim( (string) $page_url ) );
        update_option( self::OPT_SLOTS, $slots, false );
        return true;
    }

    public static function remove_slot( $slot ) {
        $slot  = (int) $slot;
        $slots = self::get_slots();
        unset( $slots[ $slot ] );
        update_option( self::OPT_SLOTS, $slots, false );
        $data = self::get_data();
        unset( $data[ $slot ] );
        update_option( self::OPT_DATA, $data, false );
    }

    // ── Settings: badges & crests ─────────────────────────────────────────────

    public static function settings() {
        $s = get_option( self::OPT_SETTINGS, array() );
        return wp_parse_args( is_array( $s ) ? $s : array(), array( 'our_badge' => '', 'fallback_crest' => '' ) );
    }

    public static function save_settings( array $s ) {
        update_option( self::OPT_SETTINGS, array(
            'our_badge'      => esc_url_raw( trim( (string) ( $s['our_badge'] ?? '' ) ) ),
            'fallback_crest' => esc_url_raw( trim( (string) ( $s['fallback_crest'] ?? '' ) ) ),
        ), false );
    }

    /** The club badge used for every Wombourne Allstars side (bundled square crop unless overridden). */
    public static function our_badge_url() {
        $s = self::settings();
        return $s['our_badge'] ?: AAT_URL . 'assets/img/club-badge.png';
    }

    /** Neutral crest for opponents Full-Time has no image for. */
    public static function fallback_crest_url() {
        $s = self::settings();
        return $s['fallback_crest'] ?: AAT_URL . 'assets/img/crest-fallback.svg';
    }

    public static function crest_overrides() {
        $c = get_option( self::OPT_CRESTS, array() );
        return is_array( $c ) ? $c : array();
    }

    /** $map = array( normalised name => URL ); blanks remove the override. */
    public static function save_crest_overrides( array $map ) {
        $clean = array();
        foreach ( $map as $k => $url ) {
            $k   = self::norm( $k );
            $url = esc_url_raw( trim( (string) $url ) );
            if ( $k && $url ) $clean[ $k ] = $url;
        }
        update_option( self::OPT_CRESTS, $clean, false );
    }

    /** Spaces are ignored — the Sunday league registers the side as "Wombourne All Stars …". */
    public static function is_our_team( $name ) {
        return strpos( str_replace( ' ', '', self::norm( $name ) ), str_replace( ' ', '', self::CLUB_PREFIX ) ) === 0;
    }

    /**
     * Resolve the crest to show for a team name.
     * Order: club badge (our sides) → admin override → crest Full-Time published → neutral fallback.
     */
    public static function crest_for( $name, $d = null, $auto = '' ) {
        if ( self::is_our_team( $name ) ) return self::our_badge_url();
        $n  = self::norm( $name );
        $ov = self::crest_overrides();
        if ( ! empty( $ov[ $n ] ) ) return $ov[ $n ];
        if ( $auto ) return $auto;
        if ( $d && ! empty( $d['crests'][ $n ] ) ) return $d['crests'][ $n ];
        return self::fallback_crest_url();
    }

    /** Every opponent name seen in any slot's cache (normalised => display), for the admin crest table. */
    public static function all_opponents() {
        $names = array();
        foreach ( self::get_data() as $d ) {
            if ( empty( $d['fetched_at'] ) ) continue;
            foreach ( ( $d['table'] ?? array() ) as $r ) $names[ self::norm( $r['team'] ) ] = $r['team'];
            foreach ( array_merge( $d['fixtures'] ?? array(), $d['results'] ?? array() ) as $r ) {
                $names[ self::norm( $r['home'] ) ] = $r['home'];
                $names[ self::norm( $r['away'] ) ] = $r['away'];
            }
        }
        foreach ( array_keys( $names ) as $k ) {
            if ( $k === '' || self::is_our_team( $k ) ) unset( $names[ $k ] );
        }
        asort( $names, SORT_NATURAL | SORT_FLAG_CASE );
        return $names;
    }

    /** Crest Full-Time publishes for a (normalised) name, if any slot has harvested one. */
    public static function auto_crest( $norm ) {
        foreach ( self::get_data() as $d ) {
            if ( ! empty( $d['crests'][ $norm ] ) ) return $d['crests'][ $norm ];
        }
        return '';
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    /**
     * Fetch a Full-Time page. Full-Time sits behind Cloudflare bot protection which
     * challenges bare server-side requests; a normal browser header set passes.
     *
     * Since Sept 2026 Cloudflare also challenges WordPress's HTTP/1.1 transport however
     * the headers look, while the same request over HTTP/2 passes — and wp_remote_get()
     * can't speak HTTP/2 (Requests fails to parse the status line). So we talk to cURL
     * directly when it offers HTTP/2, and only fall back to wp_remote_get() without it.
     * Returns the HTML string or WP_Error.
     */
    public static function fetch( $url ) {
        $ua      = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36';
        $headers = array(
            'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language'           => 'en-GB,en;q=0.9',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest'            => 'document',
            'Sec-Fetch-Mode'            => 'navigate',
            'Sec-Fetch-Site'            => 'none',
            'Sec-Fetch-User'            => '?1',
            'sec-ch-ua'                 => '"Chromium";v="127", "Not)A;Brand";v="99", "Google Chrome";v="127"',
            'sec-ch-ua-mobile'          => '?0',
            'sec-ch-ua-platform'        => '"Windows"',
        );

        if ( function_exists( 'curl_init' ) && defined( 'CURL_HTTP_VERSION_2TLS' ) ) {
            $lines = array();
            foreach ( $headers as $k => $v ) $lines[] = $k . ': ' . $v;
            $mitigated = '';
            $ch = curl_init( $url );
            curl_setopt_array( $ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2TLS,
                CURLOPT_ENCODING       => '',   // every encoding this cURL build can decode
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_HTTPHEADER     => $lines,
                CURLOPT_HEADERFUNCTION => function( $ch, $line ) use ( &$mitigated ) {
                    if ( stripos( $line, 'cf-mitigated:' ) === 0 ) $mitigated = strtolower( trim( substr( $line, 13 ) ) );
                    return strlen( $line );
                },
            ) );
            $body = curl_exec( $ch );
            $code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
            $err  = curl_error( $ch );
            curl_close( $ch );
            if ( $body === false ) return new WP_Error( 'aat_http', ( $err ?: 'Request failed' ) . ' — ' . $url );
            $body = (string) $body;
        } else {
            $r = wp_remote_get( $url, array(
                'timeout'     => 25,
                'redirection' => 3,
                'user-agent'  => $ua,
                'headers'     => $headers,
            ) );
            if ( is_wp_error( $r ) ) return $r;
            $code      = (int) wp_remote_retrieve_response_code( $r );
            $body      = (string) wp_remote_retrieve_body( $r );
            $mitigated = (string) wp_remote_retrieve_header( $r, 'cf-mitigated' );
        }

        if ( $code !== 200 ) {
            $challenged = $mitigated === 'challenge' || stripos( $body, 'Attention Required' ) !== false;
            if ( $challenged ) return new WP_Error( 'aat_blocked', 'Full-Time\'s bot protection (Cloudflare) challenged the request — ' . $url );
            return new WP_Error( 'aat_http', 'HTTP ' . $code . ' — ' . $url );
        }
        if ( strlen( $body ) < 5000 ) {
            return new WP_Error( 'aat_empty', 'Unexpectedly short response — ' . $url );
        }
        return $body;
    }

    // ── Sync ──────────────────────────────────────────────────────────────────

    /** True when the last sync_slot() stopped because Cloudflare challenged the team page itself. */
    private static $team_blocked = false;

    /**
     * Cron entry point: refresh every configured slot. Returns number that synced OK.
     *
     * Since Oct 2026 Cloudflare challenges this server's IP on nearly every run, and the data is kept
     * fresh by the browser refresh posting to /wp-json/aat/v1/import instead. So the cron stays polite:
     * if the first team page is challenged it stops there (one request, not 15) and leaves every slot's
     * cached data and status untouched, recording the block in OPT_SERVER for the admin screen.
     */
    public static function sync_all() {
        $slots   = self::get_slots();
        $data    = self::get_data();
        $ok      = 0;
        $blocked = '';
        foreach ( $slots as $s ) {
            $id     = (int) $s['slot'];
            $before = $data[ $id ] ?? null;
            if ( self::sync_slot( $s, $data ) ) {
                $ok++;
            } elseif ( self::$team_blocked ) {
                if ( $before === null ) unset( $data[ $id ] ); else $data[ $id ] = $before;
                $blocked = 'Full-Time\'s bot protection (Cloudflare) challenged the server, so the automatic check stopped there.';
                break;
            }
            update_option( self::OPT_DATA, $data, false ); // persist progressively
            sleep( 1 );
        }
        update_option( self::OPT_LASTRUN, time(), false );
        if ( $blocked ) {
            update_option( self::OPT_SERVER, array( 'blocked_at' => time(), 'message' => $blocked ), false );
        } elseif ( $ok ) {
            delete_option( self::OPT_SERVER );
        }
        if ( $ok ) self::purge_page_cache();
        error_log( $blocked
            ? sprintf( '[Adult Teams] Full-Time sync — %d/%d team(s) OK, then blocked by Cloudflare; waiting for the browser refresh.', $ok, count( $slots ) )
            : sprintf( '[Adult Teams] Full-Time sync — %d/%d team(s) OK.', $ok, count( $slots ) ) );
        return $ok;
    }

    /** SiteGround Optimizer caches pages for anonymous visitors — drop pages carrying our shortcodes after a sync. */
    private static function purge_page_cache() {
        global $wpdb;
        $ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('page','post') AND post_status = 'publish' AND (post_content LIKE '%[adult-team-info%' OR post_content LIKE '%[adult-team-widget%')" );
        $front = (int) get_option( 'page_on_front' );
        if ( $front && is_active_widget( false, false, 'aat_team_widget', true ) ) $ids[] = $front;
        if ( ! $ids ) return;
        foreach ( array_unique( $ids ) as $id ) {
            $url = get_permalink( (int) $id );
            if ( $url && function_exists( 'sg_cachepress_purge_url' ) ) {
                sg_cachepress_purge_url( $url );
            } elseif ( function_exists( 'sg_cachepress_purge_cache' ) ) {
                sg_cachepress_purge_cache();
                break;
            }
        }
    }

    /**
     * Refresh one slot into $data[ slot ]. Previous good data is kept whenever a
     * page can't be fetched or parsed. Returns true if the team page itself synced.
     */
    public static function sync_slot( array $s, array &$data ) {
        $id    = (int) $s['slot'];
        $prev  = isset( $data[ $id ] ) && is_array( $data[ $id ] ) ? $data[ $id ] : array();
        $entry = $prev;
        $entry['last_attempt'] = time();
        self::$team_blocked    = false;

        $html = self::fetch( $s['url'] );
        if ( is_wp_error( $html ) ) {
            self::$team_blocked = $html->get_error_code() === 'aat_blocked';
            $entry['error'] = $html->get_error_message();
            $data[ $id ]    = $entry;
            return false;
        }

        $tp = self::parse_team_page( $html );
        if ( ! $tp['team_name'] || empty( $tp['ids']['league'] ) ) {
            $entry['error'] = 'Could not read the team page — Full-Time may have changed its layout.';
            $data[ $id ]    = $entry;
            return false;
        }

        $links = self::links_for( $s, $tp['ids'] );
        $pages = array();

        usleep( 700000 );
        $pages['fixtures'] = self::fetch( $links['fixtures'] );

        // Since mid-Sept 2026 Full-Time hard-blocks results.html and table.html for servers, while the
        // team, fixtures and division home pages stay open. The team page's latest results arrive on
        // every sync, so we keep the history we already hold (same team, same season) and nothing is
        // missed; after a block we leave results.html alone for a week rather than knock on it daily.
        $same_run   = ( $prev['team_name'] ?? '' ) === $tp['team_name'] && ( $prev['ids']['season'] ?? '' ) === $tp['ids']['season'];
        $blocked_at = $same_run ? (int) ( $prev['results_blocked_at'] ?? 0 ) : 0;
        if ( ! $blocked_at || time() - $blocked_at >= WEEK_IN_SECONDS ) {
            usleep( 700000 );
            $pages['results'] = self::fetch( $links['results'] );
        }

        // League table: the division home page carries the full table and isn't blocked; table.html is the fallback.
        if ( isset( $links['division'] ) ) {
            usleep( 700000 );
            $pages['division'] = self::fetch( $links['division'] );
        }
        if ( ! is_string( $pages['division'] ?? null ) || ! self::parse_table_page( $pages['division'] ) ) {
            usleep( 700000 );
            $pages['table'] = self::fetch( $links['table'] );
        }

        $data[ $id ] = self::build_entry( $entry, $tp, $links, $pages, 'server' );
        return true;
    }

    /** Full-Time list / table / division URLs for a slot, from the IDs on its team page. */
    private static function links_for( array $s, array $ids ) {
        $list_q = array(
            'league'                       => $ids['league'],
            'selectedSeason'               => $ids['season'],
            'selectedFixtureGroupAgeGroup' => 0,
            'selectedFixtureGroupKey'      => $ids['group'],
            'selectedRelatedFixtureOption' => 2,   // include cup / other groups
            'selectedClub'                 => '',
            'selectedTeam'                 => '',
            'selectedDateCode'             => 'all',
            'itemsPerPage'                 => 200,
        );
        $links = array(
            'team'     => $s['url'],
            'fixtures' => self::BASE . '/fixtures.html?' . http_build_query( $list_q ),
            'results'  => self::BASE . '/results.html?' . http_build_query( $list_q ),
            'table'    => self::BASE . '/table.html?' . http_build_query( array(
                'league'                  => $ids['league'],
                'selectedSeason'          => $ids['season'],
                'selectedDivision'        => $ids['division'],
                'selectedFixtureGroupKey' => $ids['group'],
            ) ),
        );
        if ( preg_match( '/_(\d+)$/', $ids['group'], $m ) ) {
            $links['division'] = self::BASE . '/index.html?divisionseason=' . $m[1];
        }
        return $links;
    }

    /**
     * The slot entry built from page HTML — shared by the server sync and the browser import, so both
     * parse and merge identically. $prev is the slot's previous entry (with last_attempt already set).
     * $pages: 'fixtures' | 'results' | 'division' | 'table' => HTML string, WP_Error (fetch failed) or
     * absent (not fetched this time — for results that means keep the history we hold).
     */
    private static function build_entry( array $prev, array $tp, array $links, array $pages, $source ) {
        $ids      = $tp['ids'];
        $name     = $tp['team_name'];
        $fixtures = $tp['fixtures'];
        $results  = $tp['results'];
        $crests   = is_array( $prev['crests'] ?? null ) ? $prev['crests'] : array();
        self::harvest_crests( array_merge( $fixtures, $results ), $crests );
        $errors   = array();

        $h = $pages['fixtures'] ?? null;
        if ( is_wp_error( $h ) ) {
            $errors[] = $h->get_error_message();
        } elseif ( is_string( $h ) ) {
            $all = self::parse_fixture_rows( $h );
            self::harvest_crests( $all, $crests );           // every club in the league, not just our games
            $fixtures = array_merge( $fixtures, self::filter_team( $all, $name ) );
        }

        $same_run   = ( $prev['team_name'] ?? '' ) === $name && ( $prev['ids']['season'] ?? '' ) === $ids['season'];
        $kept       = $same_run && is_array( $prev['results'] ?? null ) ? $prev['results'] : array();
        $blocked_at = $same_run ? (int) ( $prev['results_blocked_at'] ?? 0 ) : 0;
        $h = $pages['results'] ?? null;
        if ( is_wp_error( $h ) ) {
            $blocked_at = time();
            if ( ! $kept && ! $results ) $errors[] = $h->get_error_message();
        } elseif ( is_string( $h ) ) {
            $blocked_at = 0;
            $kept       = array();   // the full list is authoritative
            $all        = self::parse_results_page( $h );
            self::harvest_crests( $all, $crests );
            $results = array_merge( $results, self::filter_team( $all, $name ) );
        }
        $results = array_merge( $kept, $results );   // fresh rows last, so they win the merge

        $table = array();
        $division_name = '';
        $h = $pages['division'] ?? null;
        if ( is_string( $h ) ) {
            $table         = self::parse_table_page( $h );
            $division_name = self::division_heading( $h );
        }
        if ( ! $table ) {
            $h = $pages['table'] ?? null;
            if ( is_wp_error( $h ) ) {
                $errors[] = $h->get_error_message();
            } else {
                if ( is_string( $h ) ) {
                    $table         = self::parse_table_page( $h );
                    $division_name = self::title_part( $h, 1 );
                }
                if ( ! $table ) $errors[] = 'League table not readable (or not published yet).';
            }
        }

        // De-duplicate on the Full-Time fixture id; anything with a result wins over a fixture row.
        $results  = self::merge_rows( $results );
        $fixtures = self::merge_rows( $fixtures );
        foreach ( $results as $k => $r ) unset( $fixtures[ $k ] );
        $fixtures = array_values( $fixtures );
        $results  = array_values( $results );
        usort( $fixtures, function( $a, $b ) { return $a['ts'] - $b['ts']; } );
        usort( $results,  function( $a, $b ) { return $b['ts'] - $a['ts']; } );

        return array_merge( $prev, array(
            'team_name'     => $name,
            'league_name'   => $tp['league_name'],
            'division_name' => $division_name ?: ( $prev['division_name'] ?? '' ),
            'logo'          => $tp['logo'] ?: ( $prev['logo'] ?? '' ),   // what Full-Time shows for us (informational; we display the club badge)
            'crests'        => $crests,
            'ids'           => $ids,
            'links'         => $links,
            'fixtures'      => $fixtures,
            'results'       => $results,
            'table'         => $table ?: ( $prev['table'] ?? array() ),
            'results_blocked_at' => $blocked_at,
            'fetched_at'    => time(),
            'source'        => $source,   // 'server' (WP-Cron) or 'browser' (/wp-json/aat/v1/import)
            'error'         => $errors ? implode( ' | ', $errors ) : '',
        ) );
    }

    // ── Browser import (REST) ─────────────────────────────────────────────────

    /**
     * POST /wp-json/aat/v1/import — the daily browser refresh. A real browser on a club volunteer's PC
     * fetches the Full-Time pages (which Cloudflare lets through) and posts them here; we parse them
     * exactly as sync_slot() would. The body is JSON sent as text/plain (a "simple" CORS request, so no
     * preflight from the fulltime.thefa.com tab) and every page is gzip + base64, so the host firewall
     * never sees raw HTML in a POST body.
     *
     *   { "key": "…", "action": "plan" }
     *       → { slots: [ { slot, label, team_id, team, fixtures, results, division } ] }   URLs to fetch
     *   { "key": "…", "action": "import", "slot": 1, "urls": { fixtures, results, division },
     *     "pages": { team, fixtures, results, division } }
     *       → { ok, slot, team, fixtures, results, table, links_changed, links, error }
     *
     * If the team page shows a new season / division the posted list pages are for the old one, so they
     * are ignored (links_changed: true, with the new links) and the caller fetches those and posts again.
     *
     * Actions starting "pitch_" belong to the groundsman's pitch schedule (Allstars_Pitches::rest).
     */
    public static function register_rest() {
        register_rest_route( 'aat/v1', '/import', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'rest_import' ),
            'permission_callback' => array( __CLASS__, 'rest_import_allowed' ),
        ) );
    }

    /** Body as an array (text/plain JSON or application/json). */
    private static function rest_body( WP_REST_Request $req ) {
        $b = json_decode( (string) $req->get_body(), true );
        return is_array( $b ) ? $b : array();
    }

    public static function rest_import_allowed( WP_REST_Request $req ) {
        $hash = (string) get_option( self::OPT_IMPORTKEY, '' );
        $key  = (string) ( self::rest_body( $req )['key'] ?? '' );
        $fails = (int) get_transient( 'aat_import_fails' );
        if ( $fails >= 20 ) return new WP_Error( 'aat_locked', 'Too many bad keys — try again in an hour.', array( 'status' => 429 ) );
        if ( $hash && strlen( $key ) >= 32 && hash_equals( $hash, hash( 'sha256', $key ) ) ) return true;
        set_transient( 'aat_import_fails', $fails + 1, HOUR_IN_SECONDS );
        return new WP_Error( 'aat_key', 'Bad or missing import key.', array( 'status' => 401 ) );
    }

    /** Make (or replace) the import key. Returns the plain key once; only its hash is stored. */
    public static function new_import_key() {
        $key = wp_generate_password( 48, false, false );
        update_option( self::OPT_IMPORTKEY, hash( 'sha256', $key ), false );
        return $key;
    }

    /** gzip + base64 page → HTML, or null. Capped at 8 MB unpacked. */
    public static function unpack_page( $b64 ) {
        if ( ! is_string( $b64 ) || $b64 === '' ) return null;
        $gz = base64_decode( $b64, true );
        if ( $gz === false ) return null;
        $html = @gzdecode( $gz, 8 * MB_IN_BYTES );
        return is_string( $html ) && strlen( $html ) >= 5000 ? $html : null;
    }

    public static function rest_import( WP_REST_Request $req ) {
        $b     = self::rest_body( $req );
        $slots = self::get_slots();

        if ( strpos( (string) ( $b['action'] ?? '' ), 'pitch_' ) === 0 ) {
            return Allstars_Pitches::rest( (string) $b['action'], $b );
        }

        if ( ( $b['action'] ?? '' ) === 'plan' ) {
            $data = self::get_data();
            $out  = array();
            foreach ( $slots as $n => $s ) {
                $l = $data[ $n ]['links'] ?? array();
                $out[] = array(
                    'slot'     => (int) $n,
                    'label'    => $s['label'],
                    'team_id'  => $s['team_id'],
                    'team'     => $s['url'],
                    'fixtures' => $l['fixtures'] ?? '',
                    'results'  => $l['results'] ?? '',
                    'division' => $l['division'] ?? '',
                );
            }
            return rest_ensure_response( array( 'slots' => $out ) );
        }

        if ( ( $b['action'] ?? '' ) !== 'import' ) {
            return new WP_Error( 'aat_action', 'Unknown action.', array( 'status' => 400 ) );
        }
        $n = (int) ( $b['slot'] ?? 0 );
        if ( ! isset( $slots[ $n ] ) ) return new WP_Error( 'aat_slot', 'No such slot.', array( 'status' => 400 ) );
        $s     = $slots[ $n ];
        $pages = is_array( $b['pages'] ?? null ) ? $b['pages'] : array();
        $urls  = is_array( $b['urls'] ?? null ) ? $b['urls'] : array();

        $team_html = self::unpack_page( $pages['team'] ?? '' );
        if ( ! $team_html ) return new WP_Error( 'aat_team', 'Team page missing or unreadable.', array( 'status' => 400 ) );
        if ( strpos( $team_html, (string) $s['team_id'] ) === false ) {
            return new WP_Error( 'aat_team', 'That team page is not slot ' . $n . '\'s team.', array( 'status' => 400 ) );
        }
        $tp = self::parse_team_page( $team_html );
        if ( ! $tp['team_name'] || empty( $tp['ids']['league'] ) ) {
            return new WP_Error( 'aat_team', 'Could not read the team page — Full-Time may have changed its layout.', array( 'status' => 422 ) );
        }

        // Only use list pages fetched from the URLs this team page points at (a new season changes them).
        $links   = self::links_for( $s, $tp['ids'] );
        $use     = array();
        $changed = false;
        foreach ( array( 'fixtures', 'results', 'division' ) as $k ) {
            if ( empty( $links[ $k ] ) ) continue;
            if ( ( $urls[ $k ] ?? '' ) !== $links[ $k ] ) { $changed = true; continue; }
            $html = self::unpack_page( $pages[ $k ] ?? '' );
            if ( $html !== null ) $use[ $k ] = $html;
        }

        $lock = 'aat_import_lock_' . $n;
        if ( get_transient( $lock ) ) return new WP_Error( 'aat_busy', 'An import for this slot is already running.', array( 'status' => 409 ) );
        set_transient( $lock, 1, MINUTE_IN_SECONDS );

        $data  = self::get_data();
        $entry = isset( $data[ $n ] ) && is_array( $data[ $n ] ) ? $data[ $n ] : array();
        $entry['last_attempt'] = time();
        $entry = self::build_entry( $entry, $tp, $links, $use, 'browser' );
        $data[ $n ] = $entry;
        update_option( self::OPT_DATA, $data, false );
        self::purge_page_cache();
        delete_transient( $lock );

        return rest_ensure_response( array(
            'ok'            => true,
            'slot'          => $n,
            'team'          => $entry['team_name'],
            'division'      => $entry['division_name'],
            'fixtures'      => count( $entry['fixtures'] ),
            'results'       => count( $entry['results'] ),
            'table'         => count( $entry['table'] ),
            'used'          => array_keys( $use ),
            'links_changed' => $changed,
            'links'         => array(
                'fixtures' => $links['fixtures'],
                'results'  => $links['results'],
                'division' => $links['division'] ?? '',
            ),
            'fetched_at'    => $entry['fetched_at'],
            'error'         => $entry['error'],
        ) );
    }

    /** Collect name => crest URL from parsed rows (only real images, never Full-Time's placeholder icon). */
    private static function harvest_crests( array $rows, array &$crests ) {
        foreach ( $rows as $r ) {
            if ( ! empty( $r['home_logo'] ) ) $crests[ self::norm( $r['home'] ) ] = $r['home_logo'];
            if ( ! empty( $r['away_logo'] ) ) $crests[ self::norm( $r['away'] ) ] = $r['away_logo'];
        }
    }

    // ── Parsing ───────────────────────────────────────────────────────────────

    public static function dom( $html ) {
        $doc = new DOMDocument();
        libxml_use_internal_errors( true );
        $doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
        libxml_clear_errors();
        return new DOMXPath( $doc );
    }

    public static function txt( $node ) {
        return $node ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : '';
    }

    public static function norm( $s ) {
        return strtolower( trim( preg_replace( '/\s+/u', ' ', html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ) ) );
    }

    /** Only keep real, absolute crest images — Full-Time uses /assets/images/icons/icon-club.svg as a "no crest" placeholder. */
    private static function clean_logo( $src ) {
        $src = trim( (string) $src );
        if ( $src === '' ) return '';
        if ( strpos( $src, '//' ) === 0 ) $src = 'https:' . $src;
        if ( ! preg_match( '~^https?://~i', $src ) ) return '';
        if ( stripos( $src, '/assets/images/icons/' ) !== false || stripos( $src, 'icon-club' ) !== false ) return '';
        return $src;
    }

    /** "Table | Division One | West Midlands (Regional) League" → part N. */
    public static function title_part( $html, $n ) {
        if ( preg_match( '/<title>([^<]*)<\/title>/i', $html, $m ) ) {
            $parts = array_map( 'trim', explode( '|', html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) ) );
            return $parts[ $n ] ?? '';
        }
        return '';
    }

    /**
     * Status-notes cell → a real status ("Postponed") or ''. The Sunday league fills it with admin
     * match numbers ("Match Notes: Match No 67"), which must not read as a called-off game.
     */
    private static function clean_status( $text ) {
        $text = trim( preg_replace( '/^match notes\s*:?\s*/i', '', (string) $text ) );
        if ( preg_match( '/^match\s*(no|num|number)?\.?\s*#?\s*\d+$/i', $text ) ) return '';
        return $text;
    }

    /** Division home page: first <h1> is the league, the second the division ("Oakley Division 1"). */
    private static function division_heading( $html ) {
        $h1 = self::dom( $html )->query( '//h1' );
        return $h1->length > 1 ? self::txt( $h1->item( 1 ) ) : '';
    }

    /** "29/08/26 15:00" → array( ts, date, time ) in UK time, or null. */
    private static function parse_datetime( $text ) {
        if ( ! preg_match( '~(\d{2})/(\d{2})/(\d{2}|\d{4})(?:\s+(\d{2}):(\d{2}))?~', $text, $m ) ) return null;
        $year = strlen( $m[3] ) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
        $hour = isset( $m[4] ) ? (int) $m[4] : 0;
        $min  = isset( $m[5] ) ? (int) $m[5] : 0;
        try {
            $dt = new DateTime( 'now', new DateTimeZone( self::TZ ) );
            $dt->setDate( $year, (int) $m[2], (int) $m[1] )->setTime( $hour, $min, 0 );
        } catch ( Exception $e ) {
            return null;
        }
        return array(
            'ts'   => $dt->getTimestamp(),
            'date' => $dt->format( 'Y-m-d' ),
            'time' => isset( $m[4] ) ? $dt->format( 'H:i' ) : '',
        );
    }

    /** Normalised row shared by every parser. */
    private static function make_row( $id, $type, array $dt, $home, $away, $score_text, $venue, $competition, $status, $home_logo = '', $away_logo = '' ) {
        $played = false; $hs = null; $as = null; $ht = '';
        if ( preg_match( '/^(\d+)\s*-\s*(\d+)/', $score_text, $m ) ) {
            $played = true; $hs = (int) $m[1]; $as = (int) $m[2];
            if ( preg_match( '/HT\s*(\d+)\s*-\s*(\d+)/i', $score_text, $h ) ) $ht = $h[1] . '-' . $h[2];
        }
        if ( ! $status && ! $played && $score_text && strcasecmp( $score_text, 'VS' ) !== 0 && strcasecmp( $score_text, 'V' ) !== 0 ) {
            $status = $score_text; // e.g. "P - P" / "H - W" placeholders Full-Time shows instead of a score
        }
        $id = preg_replace( '/\D/', '', (string) $id );
        return array(
            'id'          => $id,
            'key'         => $id ?: md5( $dt['date'] . '|' . self::norm( $home ) . '|' . self::norm( $away ) ),
            'type'        => $type,
            'ts'          => $dt['ts'],
            'date'        => $dt['date'],
            'time'        => $dt['time'],
            'home'        => $home,
            'away'        => $away,
            'home_logo'   => self::clean_logo( $home_logo ),
            'away_logo'   => self::clean_logo( $away_logo ),
            'venue'       => $venue,
            'competition' => $competition,
            'status'      => $status,
            'played'      => $played,
            'hs'          => $hs,
            'as'          => $as,
            'ht'          => $ht,
            'score_text'  => $score_text,
            'url'         => $id ? self::BASE . '/displayFixture.html?id=' . $id : '',
        );
    }

    /**
     * Team page: title gives "Team | League"; the "All fixtures" link carries the
     * league/season/division/group IDs; the two tables are latest results + upcoming.
     */
    private static function parse_team_page( $html ) {
        $x   = self::dom( $html );
        $out = array( 'team_name' => '', 'league_name' => '', 'logo' => '', 'ids' => array(), 'results' => array(), 'fixtures' => array() );

        $out['team_name']   = self::title_part( $html, 0 );
        $out['league_name'] = self::title_part( $html, 1 );

        foreach ( $x->query( '//a[contains(@href,"fixtures.html") and contains(@href,"selectedSeason")]' ) as $a ) {
            $q = array();
            parse_str( html_entity_decode( (string) wp_parse_url( $a->getAttribute( 'href' ), PHP_URL_QUERY ), ENT_QUOTES, 'UTF-8' ), $q );
            if ( ! empty( $q['league'] ) ) {
                $out['ids'] = array(
                    'league'   => preg_replace( '/\D/', '', $q['league'] ),
                    'season'   => preg_replace( '/\D/', '', $q['selectedSeason'] ?? '' ),
                    'division' => preg_replace( '/\D/', '', $q['selectedDivision'] ?? '' ),
                    'group'    => preg_replace( '/[^\d_]/', '', $q['selectedFixtureGroupKey'] ?? '' ),
                );
                break;
            }
        }

        if ( $out['team_name'] ) {
            foreach ( $x->query( '//img[@alt]' ) as $img ) {
                if ( self::norm( $img->getAttribute( 'alt' ) ) === self::norm( $out['team_name'] ) ) {
                    $out['logo'] = self::clean_logo( $img->getAttribute( 'src' ) );
                    if ( $out['logo'] ) break;
                }
            }
        }

        foreach ( self::parse_fixture_rows_from( $x ) as $row ) {
            if ( $row['played'] ) $out['results'][] = $row; else $out['fixtures'][] = $row;
        }
        return $out;
    }

    /** Table-based rows (team page + league fixtures page): <tr> with td.home-team / td.road-team. */
    public static function parse_fixture_rows( $html ) {
        return self::parse_fixture_rows_from( self::dom( $html ) );
    }

    private static function parse_fixture_rows_from( DOMXPath $x ) {
        $rows = array();
        foreach ( $x->query( '//table//tr[td[contains(@class,"home-team")]]' ) as $tr ) {
            $tds = $x->query( './td', $tr );
            if ( $tds->length < 5 ) continue;

            $home = $away = $score = null; $i_away = -1; $home_logo = $away_logo = ''; $status = '';
            $pending_logo = '';
            foreach ( $tds as $i => $td ) {
                $c = ' ' . $td->getAttribute( 'class' ) . ' ';
                if ( strpos( $c, ' home-team ' ) !== false )       { $home = $td; }
                elseif ( strpos( $c, ' road-team ' ) !== false )   { $away = $td; $i_away = $i; $away_logo = $pending_logo; $pending_logo = ''; }
                elseif ( strpos( $c, ' score ' ) !== false )       { $score = $td; $home_logo = $pending_logo; $pending_logo = ''; }
                elseif ( strpos( $c, ' team-logo ' ) !== false )   { $img = $x->query( './/img', $td )->item( 0 ); $pending_logo = $img ? $img->getAttribute( 'src' ) : ''; }
                elseif ( strpos( $c, ' status-notes ' ) !== false ){ $status = self::clean_status( self::txt( $td ) ); }
            }
            if ( ! $home || ! $away ) continue;

            $dt = self::parse_datetime( self::txt( $tds->item( 1 ) ) );
            if ( ! $dt ) continue;

            // Cells after the away team: venue, then competition (league list) — the
            // team page has venue only (fixtures) or an empty cell (results).
            $after = array();
            for ( $i = $i_away + 1; $i < $tds->length; $i++ ) {
                $td = $tds->item( $i );
                if ( strpos( ' ' . $td->getAttribute( 'class' ) . ' ', ' status-notes ' ) !== false ) continue;
                $after[] = self::txt( $td );
            }
            $venue       = $after[0] ?? '';
            $competition = $after[1] ?? '';

            $a  = $x->query( './/a[contains(@href,"displayFixture")]', $tr )->item( 0 );
            $id = '';
            if ( $a && preg_match( '/id=(\d+)/', $a->getAttribute( 'href' ), $m ) ) $id = $m[1];

            $rows[] = self::make_row( $id, self::txt( $tds->item( 0 ) ), $dt, self::txt( $home ), self::txt( $away ), $score ? self::txt( $score ) : '', $venue, $competition, $status, $home_logo, $away_logo );
        }
        return $rows;
    }

    /** League results page is div-based: div#fixture-NNN with .type-col/.datetime-col/.home-team-col/.score-col/.road-team-col/.fg-col. */
    private static function parse_results_page( $html ) {
        $x    = self::dom( $html );
        $rows = array();
        foreach ( $x->query( '//div[starts-with(@id,"fixture-")]' ) as $d ) {
            if ( ! preg_match( '/^fixture-(\d+)$/', $d->getAttribute( 'id' ), $m ) ) continue;
            $dt = self::parse_datetime( self::txt( $x->query( './/div[contains(@class,"datetime-col")]', $d )->item( 0 ) ) );
            if ( ! $dt ) continue;
            $home = self::txt( $x->query( './/div[contains(@class,"home-team-col")]//div[contains(@class,"team-name")]', $d )->item( 0 ) );
            $away = self::txt( $x->query( './/div[contains(@class,"road-team-col")]//div[contains(@class,"team-name")]', $d )->item( 0 ) );
            if ( ! $home || ! $away ) continue;
            $hl = $x->query( './/div[contains(@class,"home-team-col")]//img', $d )->item( 0 );
            $al = $x->query( './/div[contains(@class,"road-team-col")]//img', $d )->item( 0 );
            $rows[] = self::make_row(
                $m[1],
                self::txt( $x->query( './/div[contains(@class,"type-col")]', $d )->item( 0 ) ),
                $dt, $home, $away,
                self::txt( $x->query( './/div[contains(@class,"score-col")]', $d )->item( 0 ) ),
                '',
                self::txt( $x->query( './/div[contains(@class,"fg-col")]', $d )->item( 0 ) ),
                '',
                $hl ? $hl->getAttribute( 'src' ) : '',
                $al ? $al->getAttribute( 'src' ) : ''
            );
        }
        return $rows;
    }

    /**
     * First table with POS/TEAM/P/W/D/L/PTS headers exactly once each (skips the home/away
     * split table, whose W/D/L repeat). F, A and GD are optional — some leagues omit them.
     */
    private static function parse_table_page( $html ) {
        $x = self::dom( $html );
        foreach ( $x->query( '//table' ) as $t ) {
            $hdr = array();
            foreach ( $x->query( './/thead//th | ./tr[1]/th | ./tbody/tr[1]/th', $t ) as $th ) $hdr[] = strtoupper( self::txt( $th ) );
            if ( ! $hdr ) continue;
            $idx = array();
            foreach ( array( 'POS', 'TEAM', 'P', 'W', 'D', 'L', 'PTS' ) as $k ) {
                if ( count( array_keys( $hdr, $k, true ) ) !== 1 ) continue 2;
                $idx[ $k ] = array_search( $k, $hdr, true );
            }
            foreach ( array( 'F', 'A', 'GD' ) as $k ) {
                if ( count( array_keys( $hdr, $k, true ) ) === 1 ) $idx[ $k ] = array_search( $k, $hdr, true );
            }
            $rows = array();
            foreach ( $x->query( './/tbody/tr', $t ) as $tr ) {
                $tds = $x->query( './td', $tr );
                if ( $tds->length <= max( $idx ) ) continue;
                $cell = function( $k ) use ( $tds, $idx ) { return isset( $idx[ $k ] ) ? self::txt( $tds->item( $idx[ $k ] ) ) : null; };
                $team = $cell( 'TEAM' );
                if ( ! $team ) continue;
                $f = $cell( 'F' ); $a = $cell( 'A' ); $gd = $cell( 'GD' );
                $rows[] = array(
                    'pos'  => (int) $cell( 'POS' ),
                    'team' => $team,
                    'p'    => (int) $cell( 'P' ),
                    'w'    => (int) $cell( 'W' ),
                    'd'    => (int) $cell( 'D' ),
                    'l'    => (int) $cell( 'L' ),
                    'f'    => $f === null ? null : (int) $f,
                    'a'    => $a === null ? null : (int) $a,
                    'gd'   => $gd !== null ? (int) $gd : ( $f !== null && $a !== null ? (int) $f - (int) $a : 0 ),
                    'pts'  => (int) $cell( 'PTS' ),
                );
            }
            if ( $rows ) return $rows;
        }
        return array();
    }

    private static function filter_team( array $rows, $team_name ) {
        $n = self::norm( $team_name );
        return array_values( array_filter( $rows, function( $r ) use ( $n ) {
            return self::norm( $r['home'] ) === $n || self::norm( $r['away'] ) === $n;
        } ) );
    }

    /** De-duplicate on fixture key; later rows fill in blanks (league list is richer than the team page). */
    private static function merge_rows( array $rows ) {
        $by = array();
        foreach ( $rows as $r ) {
            $k = $r['key'];
            if ( ! isset( $by[ $k ] ) ) { $by[ $k ] = $r; continue; }
            foreach ( $r as $f => $v ) {
                if ( $v !== '' && $v !== null && $v !== false ) $by[ $k ][ $f ] = $v;
            }
        }
        return $by;
    }

    // ── View helpers ──────────────────────────────────────────────────────────

    /** Which side were we, and how did it go? Returns array( 'home'|'away', 'W'|'D'|'L'|'' ). */
    public static function perspective( array $row, $team_name ) {
        $n    = self::norm( $team_name );
        $side = self::norm( $row['home'] ) === $n ? 'home' : 'away';
        $res  = '';
        if ( $row['played'] ) {
            $us   = $side === 'home' ? $row['hs'] : $row['as'];
            $them = $side === 'home' ? $row['as'] : $row['hs'];
            if ( $us === $them && ( $p = self::pens( $row, $side ) ) ) { $us = $p[0]; $them = $p[1]; } // cup tie settled on penalties
            $res  = $us > $them ? 'W' : ( $us < $them ? 'L' : 'D' );
        } elseif ( $wo = self::walkover_side( $row ) ) {
            $res = $wo === $side ? 'W' : 'L';
        }
        return array( $side, $res );
    }

    /** Shoot-out score from "2 - 2 (Pens 4-2, HT 1-0)" as array( us, them ) for $side, or null. */
    public static function pens( array $row, $side ) {
        if ( ! preg_match( '/Pens\s*(\d+)\s*-\s*(\d+)/i', (string) ( $row['score_text'] ?? '' ), $m ) ) return null;
        return $side === 'home' ? array( (int) $m[1], (int) $m[2] ) : array( (int) $m[2], (int) $m[1] );
    }

    /** Full-Time shows "H - W" / "Home Walkover" etc. instead of a score for an awarded game. Returns who was awarded it: 'home'|'away'|''. */
    public static function walkover_side( array $row ) {
        if ( ! empty( $row['played'] ) || ! preg_match( '/\b(H|A|Home|Away)(?:\s*-\s*W|\s+Walkover)\b/i', (string) ( $row['status'] ?? '' ), $m ) ) return '';
        return strtoupper( $m[1][0] ) === 'H' ? 'home' : 'away';
    }

    public static function ordinal( $n ) {
        $n = (int) $n;
        if ( $n % 100 >= 11 && $n % 100 <= 13 ) return $n . 'th';
        switch ( $n % 10 ) {
            case 1:  return $n . 'st';
            case 2:  return $n . 'nd';
            case 3:  return $n . 'rd';
            default: return $n . 'th';
        }
    }

    /** "Sat 29 Aug" / "Saturday 29 August" in UK time. */
    public static function fmt_date( $ts, $format = 'D j M' ) {
        $dt = new DateTime( '@' . (int) $ts );
        $dt->setTimezone( new DateTimeZone( self::TZ ) );
        return $dt->format( $format );
    }

    /** Shared per-slot view model: next game, upcoming list, results, table, our row, form. */
    private static function view_model( array $s, $d, $limit, $now ) {
        $b = array( 'slot' => $s, 'data' => $d, 'next' => null, 'upcoming' => array(), 'results' => array(), 'last' => null, 'table' => array(), 'our_row' => null, 'form' => array(), 'link' => '' );
        $b['link'] = ! empty( $s['page_url'] ) ? $s['page_url'] : ( $d['links']['team'] ?? $s['url'] );
        if ( ! $d ) return $b;

        $name   = $d['team_name'];
        $today0 = strtotime( 'today', $now ) - 12 * HOUR_IN_SECONDS;
        // Full-Time files walkovers in either list; go by date instead — still to come stays in "Up later"
        // (tagged Walkover), already past counts as a result.
        $all = $results = array();
        foreach ( $d['fixtures'] as $f ) {
            if ( self::walkover_side( $f ) && $f['ts'] < $today0 ) $results[ $f['key'] ] = $f; else $all[ $f['key'] ] = $f;
        }
        foreach ( $d['results'] as $f ) {
            if ( self::walkover_side( $f ) && $f['ts'] >= $today0 ) $all[ $f['key'] ] = $f; else $results[ $f['key'] ] = $f;
        }
        $all     = array_values( array_diff_key( $all, $results ) );
        $results = array_values( $results );
        usort( $all, function( $a, $b ) { return $a['ts'] <=> $b['ts']; } );
        usort( $results, function( $a, $b ) { return $b['ts'] <=> $a['ts']; } );
        // "Up next" = first non-postponed game not yet finished (a game kicking off today stays "next" until ~3h after KO)
        foreach ( $all as $i => $f ) {
            if ( $f['status'] || $f['ts'] < $now - 3 * HOUR_IN_SECONDS ) continue;
            $b['next'] = $f;
            unset( $all[ $i ] );
            break;
        }
        // "Up later" = everything from today onward (postponed games keep their slot so people see the change)
        $b['upcoming'] = array_slice( array_values( array_filter( $all, function( $f ) use ( $today0 ) { return $f['ts'] >= $today0 || $f['status']; } ) ), 0, $limit );
        $b['results']  = array_slice( $results, 0, $limit );
        $b['last']     = $results[0] ?? null;
        $b['table']    = $d['table'];
        foreach ( $d['table'] as $row ) {
            if ( self::norm( $row['team'] ) === self::norm( $name ) ) { $b['our_row'] = $row; break; }
        }
        foreach ( array_slice( $results, 0, 5 ) as $r ) {
            list( , $res ) = self::perspective( $r, $name );
            if ( $res ) $b['form'][] = $res;
        }
        $b['form'] = array_reverse( $b['form'] ); // oldest → newest, reads left to right
        return $b;
    }

    /** Queue a one-off sync for a slot that has never been fetched (just added, cron not run yet). */
    private static function kick_if_unfetched( array $slots, array $data, $now ) {
        foreach ( $slots as $s ) {
            if ( empty( $data[ $s['slot'] ]['fetched_at'] ) && ! get_transient( 'aat_kick' ) ) {
                set_transient( 'aat_kick', 1, 10 * MINUTE_IN_SECONDS );
                wp_schedule_single_event( $now + 5, self::CRON_HOOK );
                break;
            }
        }
    }

    private static function admin_note( $msg ) {
        if ( ! current_user_can( 'manage_options' ) ) return '';
        return '<div class="aat"><p class="aat-empty">' . $msg . ' (Only admins see this note.)</p></div>';
    }

    // ── Shortcodes ────────────────────────────────────────────────────────────

    /** [adult-team-info] — every configured slot (or slot="2" for one). */
    public static function shortcode_all( $atts = array() ) {
        $atts = is_array( $atts ) ? $atts : array();
        return self::render( ! empty( $atts['slot'] ) ? (int) $atts['slot'] : 0, $atts );
    }

    /** [adult-team-widget slot="1"] */
    public static function shortcode_widget( $atts = array() ) {
        $atts = is_array( $atts ) ? $atts : array();
        return self::render_widget( ! empty( $atts['slot'] ) ? (int) $atts['slot'] : 0, $atts );
    }

    /**
     * Full section for one slot (or all when $slot = 0).
     * Attributes: show="next,fixtures,results,table"  limit="6"
     */
    public static function render( $slot, $atts = array() ) {
        $atts = shortcode_atts( array(
            'show'  => 'next,fixtures,results,table',
            'limit' => 6,
        ), is_array( $atts ) ? $atts : array(), 'adult-team-info' );

        wp_enqueue_style( 'allstars-adult-teams' );

        $slots = self::get_slots();
        $data  = self::get_data();
        $show  = array_map( 'trim', explode( ',', strtolower( $atts['show'] ) ) );
        $limit = max( 1, (int) $atts['limit'] );
        $now   = time();

        if ( $slot > 0 ) {
            if ( ! isset( $slots[ $slot ] ) ) {
                return self::admin_note( '[adult-team-info-' . (int) $slot . '] — no team is set up in slot ' . (int) $slot . ' yet. Add one under <strong>Adult Teams</strong> in the dashboard.' );
            }
            $slots = array( $slot => $slots[ $slot ] );
        }
        self::kick_if_unfetched( $slots, $data, $now );

        $blocks = array();
        foreach ( $slots as $s ) {
            $d = isset( $data[ $s['slot'] ] ) && ! empty( $data[ $s['slot'] ]['fetched_at'] ) ? $data[ $s['slot'] ] : null;
            $blocks[] = self::view_model( $s, $d, $limit, $now );
        }

        ob_start();
        include AAT_DIR . 'templates/team-info.php';
        return ob_get_clean();
    }

    /**
     * Compact card for the front page / sidebars: up next, latest result, league position.
     * Attributes: title="…" (defaults to the slot name)  link="…" (defaults to the slot's page)
     */
    public static function render_widget( $slot, $atts = array() ) {
        $atts = shortcode_atts( array(
            'title' => '',
            'link'  => '',
        ), is_array( $atts ) ? $atts : array(), 'adult-team-widget' );

        wp_enqueue_style( 'allstars-adult-teams' );

        $slots = self::get_slots();
        $data  = self::get_data();
        $now   = time();

        if ( $slot > 0 ) {
            if ( ! isset( $slots[ $slot ] ) ) {
                return self::admin_note( '[adult-team-widget-' . (int) $slot . '] — no team is set up in slot ' . (int) $slot . ' yet. Add one under <strong>Adult Teams</strong> in the dashboard.' );
            }
            $slots = array( $slot => $slots[ $slot ] );
        }
        self::kick_if_unfetched( $slots, $data, $now );

        $cards = array();
        foreach ( $slots as $s ) {
            $d = isset( $data[ $s['slot'] ] ) && ! empty( $data[ $s['slot'] ]['fetched_at'] ) ? $data[ $s['slot'] ] : null;
            $c = self::view_model( $s, $d, 1, $now );
            if ( $atts['title'] ) $c['slot']['label'] = $atts['title'];
            if ( $atts['link'] )  $c['link'] = $atts['link'];
            $cards[] = $c;
        }

        ob_start();
        include AAT_DIR . 'templates/team-widget.php';
        return ob_get_clean();
    }
}
