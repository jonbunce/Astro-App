<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Allstars_Pitches — the groundsman's pitch schedule ([pitch-schedule]).
 *
 * Every home game on the club's pitches this weekend and next, grouped by day → ground → pitch, built
 * from FA Full-Time. Full-Time lists every club side on one club page; each league then has a fixture
 * list that can be filtered to our club, and its Venue column carries the pitch
 * ("WOMBOURNE LEISURE CENTRE 9v9 Pitch C&D - 10:45 3G", "BRICKBRIDGE PLAYING FIELDS 3 11").
 *
 * The server can't reach Full-Time (Cloudflare), so the browser refresh that feeds the adult teams also
 * fetches these pages (pitches.js) and posts them to /wp-json/aat/v1/import:
 *
 *   { action: "pitch_plan" }                                → { urls }        what to fetch first
 *   { action: "pitch_import", pages: { url: gz }, failed: { url: msg } }
 *                                                           → { ok, next, imported }
 *   { action: "pitch_done", fetched, failed, blocked }      → { ok, days, check, away }   run summary
 *
 * `next` simply lists every URL the data still needs; the browser keeps a "seen" set so nothing is
 * fetched twice in one run. Three kinds of page:
 *   club page    which leagues our sides play in            re-read weekly
 *   discovery    our club's ID in one league's club filter   re-read monthly (weekly while we have no side there)
 *   fixture list next 30 days for one league + club ID       every run
 * Full-Time defaults to the current season when none is given, so a new season needs no special handling.
 */
class Allstars_Pitches {

    const OPT_CFG      = 'aat_pitch_cfg';       // leagues: id => { name, clubs[], discovered_at, seen_at }, club_pages_at
    const OPT_DATA     = 'aat_pitch_data';      // lists: "league:club" => { league, club, rows[], fetched_at, error }, last_run
    const OPT_SETTINGS = 'aat_pitch_settings';  // grounds, club_pages (one per line)
    const SHORTCODE    = 'pitch-schedule';

    const DEFAULT_CLUB_PAGES = "https://fulltime.thefa.com/home/club/167584062.html\nhttps://fulltime.thefa.com/home/club/370317017.html\nhttps://fulltime.thefa.com/home/club/standalone/894096104.html";

    // "Name shown | other spellings Full-Time uses" — matched anywhere in the venue text, ignoring case.
    const DEFAULT_GROUNDS = "Wombourne Leisure Centre | Wombourne Leisure\nBrickbridge Playing Fields | Brickbridge Lane | Brickbridge\nWombourne High School | Wombourne High\nWestfield Primary School | Westfield School | Westfield Primary\nBlakeley Heath Primary School | Blakeley Heath";

    const MAX_PAGES_PER_POST = 12;

    // ── Boot ──────────────────────────────────────────────────────────────────

    public static function init() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
        // The page sits behind a WordPress page password: never let SiteGround cache it, or the
        // password form would be served again after the right password is entered.
        add_filter( 'sgo_exclude_urls_from_cache', array( __CLASS__, 'exclude_from_cache' ) );
        add_action( 'template_redirect', array( __CLASS__, 'no_cache_headers' ) );
        add_action( 'save_post', function() { delete_transient( 'aat_pitch_pages' ); } );
    }

    /** [ id => relative path ] of published pages carrying the shortcode. */
    public static function pages() {
        $p = get_transient( 'aat_pitch_pages' );
        if ( is_array( $p ) ) return $p;
        global $wpdb;
        $p = array();
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('page','post') AND post_status = 'publish' AND post_content LIKE %s", '%[' . self::SHORTCODE . '%' ) );
        foreach ( $ids as $id ) $p[ (int) $id ] = wp_make_link_relative( get_permalink( (int) $id ) );
        set_transient( 'aat_pitch_pages', $p, 12 * HOUR_IN_SECONDS );
        return $p;
    }

    public static function exclude_from_cache( $parts ) {
        $parts = is_array( $parts ) ? $parts : array();
        foreach ( self::pages() as $path ) {
            if ( $path && $path !== '/' ) $parts[] = trailingslashit( $path ) . '*';
        }
        return $parts;
    }

    public static function no_cache_headers() {
        if ( is_singular() && isset( self::pages()[ (int) get_queried_object_id() ] ) ) nocache_headers();
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public static function settings() {
        $s = get_option( self::OPT_SETTINGS, array() );
        return wp_parse_args( is_array( $s ) ? $s : array(), array( 'grounds' => self::DEFAULT_GROUNDS, 'club_pages' => self::DEFAULT_CLUB_PAGES ) );
    }

    public static function save_settings( $grounds, $club_pages ) {
        $pages = array();
        foreach ( preg_split( '/\R/', (string) $club_pages ) as $l ) {
            $l = trim( $l );
            if ( preg_match( '~^https://fulltime\.thefa\.com/home/club/(standalone/)?\d+\.html$~i', $l ) ) $pages[] = $l;
        }
        update_option( self::OPT_SETTINGS, array(
            'grounds'    => trim( sanitize_textarea_field( (string) $grounds ) ) ?: self::DEFAULT_GROUNDS,
            'club_pages' => $pages ? implode( "\n", array_unique( $pages ) ) : self::DEFAULT_CLUB_PAGES,
        ), false );
    }

    public static function club_pages() {
        return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', self::settings()['club_pages'] ) ) ) );
    }

    /** [ [ name, aliases (normalised, longest first) ] ] in the order the admin listed them. */
    public static function grounds() {
        $out = array();
        foreach ( preg_split( '/\R/', self::settings()['grounds'] ) as $line ) {
            $parts = array_values( array_filter( array_map( 'trim', explode( '|', $line ) ), 'strlen' ) );
            if ( ! $parts ) continue;
            $aliases = array_values( array_unique( array_map( array( 'Allstars_Adult_Teams', 'norm' ), $parts ) ) );
            usort( $aliases, function( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
            $out[] = array( 'name' => $parts[0], 'aliases' => $aliases );
        }
        return $out;
    }

    public static function cfg() {
        $c = get_option( self::OPT_CFG, array() );
        return wp_parse_args( is_array( $c ) ? $c : array(), array( 'leagues' => array(), 'club_pages_at' => 0 ) );
    }

    public static function data() {
        $d = get_option( self::OPT_DATA, array() );
        return wp_parse_args( is_array( $d ) ? $d : array(), array( 'lists' => array(), 'last_run' => array() ) );
    }

    /** Forget the discovered leagues so the next run re-reads the club pages and every league's club filter. */
    public static function reset_discovery() {
        $cfg = self::cfg();
        $cfg['club_pages_at'] = 0;
        foreach ( $cfg['leagues'] as &$l ) $l['discovered_at'] = 0;
        unset( $l );
        update_option( self::OPT_CFG, $cfg, false );
    }

    // ── URLs ──────────────────────────────────────────────────────────────────

    private static function discovery_url( $league ) {
        return Allstars_Adult_Teams::BASE . '/fixtures/1/25.html?' . http_build_query( array(
            'league'                  => $league,
            'selectedFixtureGroupKey' => '',
            'selectedClub'            => '',
            'selectedTeam'            => '',
            'selectedDateCode'        => 'all',
        ) );
    }

    private static function list_url( $league, $club ) {
        return Allstars_Adult_Teams::BASE . '/fixtures/1/250.html?' . http_build_query( array(
            'league'                       => $league,
            'selectedFixtureGroupKey'      => '',
            'selectedRelatedFixtureOption' => 3,   // other groups + county cup ties
            'selectedClub'                 => $club,
            'selectedTeam'                 => '',
            'selectedDateCode'             => '30days',
        ) );
    }

    private static function discovery_due( array $l ) {
        $age = time() - (int) ( $l['discovered_at'] ?? 0 );
        return empty( $l['clubs'] ) ? $age >= 6 * DAY_IN_SECONDS : $age >= 30 * DAY_IN_SECONDS;
    }

    /** Every URL the data needs right now (club pages only when asked). */
    private static function needed_urls( array $cfg, $club_pages ) {
        $urls = $club_pages ? self::club_pages() : array();
        foreach ( $cfg['leagues'] as $id => $l ) {
            if ( self::discovery_due( $l ) ) { $urls[] = self::discovery_url( $id ); continue; }
            foreach ( (array) $l['clubs'] as $c ) $urls[] = self::list_url( $id, $c );
        }
        return $urls;
    }

    /** What kind of page a posted URL is: array( kind => club|disc|list, league, club ) or null. */
    private static function classify( $url ) {
        $p = wp_parse_url( $url );
        if ( ! $p || ( $p['host'] ?? '' ) !== 'fulltime.thefa.com' ) return null;
        $path = $p['path'] ?? '';
        if ( preg_match( '~^/home/club/(standalone/)?\d+\.html$~', $path ) ) {
            return in_array( $url, self::club_pages(), true ) ? array( 'kind' => 'club' ) : null;
        }
        $q = array();
        parse_str( $p['query'] ?? '', $q );
        $league = preg_replace( '/\D/', '', (string) ( $q['league'] ?? '' ) );
        if ( ! $league ) return null;
        if ( $path === '/fixtures/1/25.html' && ( $q['selectedClub'] ?? 'x' ) === '' ) return array( 'kind' => 'disc', 'league' => $league );
        $club = preg_replace( '/\D/', '', (string) ( $q['selectedClub'] ?? '' ) );
        if ( $path === '/fixtures/1/250.html' && $club ) return array( 'kind' => 'list', 'league' => $league, 'club' => $club );
        return null;
    }

    // ── Parsing ───────────────────────────────────────────────────────────────

    /** Club page → [ [ team, league, league_name ] ] from its DisplayTeam.do?teamID=…&league=… links. */
    private static function parse_club_page( $html ) {
        $x   = Allstars_Adult_Teams::dom( $html );
        $out = array();
        foreach ( $x->query( '//a[contains(@href,"DisplayTeam.do")]' ) as $a ) {
            if ( ! preg_match( '/league=(\d+)/i', $a->getAttribute( 'href' ), $m ) ) continue;
            $text = Allstars_Adult_Teams::txt( $a );
            $out[] = array(
                'team'        => trim( preg_replace( '/League Name:.*$/i', '', $text ) ),
                'league'      => $m[1],
                'league_name' => preg_match( '/League Name:\s*(.+)$/i', $text, $n ) ? trim( $n[1] ) : '',
            );
        }
        return $out;
    }

    /** Club IDs in a fixtures page's club filter that are ours ("Wombourne Allstars", "Wombourne All Stars Red Lion" …). */
    private static function our_clubs_in( $html ) {
        $x   = Allstars_Adult_Teams::dom( $html );
        $ids = array();
        foreach ( $x->query( '//select[@name="selectedClub"]/option' ) as $o ) {
            $v = preg_replace( '/\D/', '', $o->getAttribute( 'value' ) );
            if ( $v && Allstars_Adult_Teams::is_our_team( Allstars_Adult_Teams::txt( $o ) ) ) $ids[] = $v;
        }
        return array_values( array_unique( $ids ) );
    }

    /** null when the page has no club filter at all, else whether the selected club is ours. */
    private static function selected_club_is_ours( $html ) {
        $x   = Allstars_Adult_Teams::dom( $html );
        $sel = $x->query( '//select[@name="selectedClub"]' );
        if ( ! $sel->length ) return null;
        $o = $x->query( './option[@selected]', $sel->item( 0 ) )->item( 0 );
        return $o && Allstars_Adult_Teams::is_our_team( Allstars_Adult_Teams::txt( $o ) );
    }

    /** Which ground a venue is at: array( ground index or -1, venue text after the ground name ). */
    public static function match_ground( $venue, array $grounds ) {
        $orig = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( (string) $venue, ENT_QUOTES, 'UTF-8' ) ) );
        $v    = strtolower( $orig );   // same bytes as Allstars_Adult_Teams::norm(), so offsets line up
        foreach ( $grounds as $gi => $g ) {
            foreach ( $g['aliases'] as $a ) {
                $p = strpos( $v, $a );
                if ( $p === false ) continue;
                // Anything before the ground name ("SDYFL WOMBOURNE …") is a league code; ", Wombourne" after it is address.
                $rest = substr( $orig, $p + strlen( $a ) );
                return array( $gi, preg_replace( '/^\s*,.*$/u', '', $rest ) );
            }
        }
        return array( -1, '' );
    }

    /**
     * The pitch part of a venue: drop kick-off times and padding.
     * "9v9 Pitch C&D - 10:45 3G" → "9v9 Pitch C&D 3G" · "3 11" (pitch 3, 11:00 slot) → "Pitch 3" · "1 10.30" → "Pitch 1".
     */
    public static function pitch_label( $rest, $time ) {
        $s = ' ' . preg_replace( '/\s+/u', ' ', (string) $rest ) . ' ';
        $s = preg_replace( '/(?<=\s)[-–]?\s*\d{1,2}[:.]\d{2}(?=\s)/u', ' ', $s );
        $s = trim( preg_replace( '/\s+/u', ' ', $s ) );
        if ( preg_match( '/^(\d{1,2}):00$/', (string) $time, $m ) ) {
            $s = preg_replace( '/^(.*\S)\s+0?' . (int) $m[1] . '$/u', '$1', $s );   // trailing bare hour = the slot time
        }
        $s = preg_replace( '/\b(\d{1,2})v(\d{1,2})\b/i', '$1v$2', $s );
        $s = preg_replace( '/(^|\s)[-–,]+(?=\s|$)/u', ' ', $s );
        $s = trim( preg_replace( '/\s+/u', ' ', $s ), " -–," );
        if ( $s !== '' && preg_match( '/^(\d{1,2}[A-Z]?|[A-Z]{1,2})$/i', $s ) && ! preg_match( '/^\dG$/i', $s ) ) {
            $s = 'Pitch ' . strtoupper( $s );
        }
        return $s;
    }

    // ── Browser import (REST) ─────────────────────────────────────────────────

    public static function rest( $action, array $b ) {
        switch ( $action ) {
            case 'pitch_plan':
                $cfg = self::cfg();
                $due = empty( $cfg['leagues'] ) || time() - (int) $cfg['club_pages_at'] >= 6 * DAY_IN_SECONDS;
                return rest_ensure_response( array( 'urls' => self::needed_urls( $cfg, $due ) ) );
            case 'pitch_import':
                return self::rest_import( $b );
            case 'pitch_done':
                return self::rest_done( $b );
        }
        return new WP_Error( 'aat_action', 'Unknown action.', array( 'status' => 400 ) );
    }

    private static function rest_import( array $b ) {
        $pages  = is_array( $b['pages'] ?? null ) ? $b['pages'] : array();
        $failed = is_array( $b['failed'] ?? null ) ? $b['failed'] : array();
        if ( count( $pages ) > self::MAX_PAGES_PER_POST ) {
            return new WP_Error( 'aat_size', 'Too many pages in one post.', array( 'status' => 413 ) );
        }

        $cfg  = self::cfg();
        $data = self::data();
        $now  = time();
        $log  = array();

        // Club pages define the leagues, discovery the club IDs, lists the fixtures — so take them in that order.
        $by = array( 'club' => array(), 'disc' => array(), 'list' => array() );
        foreach ( $pages as $url => $b64 ) {
            $k = self::classify( (string) $url );
            if ( ! $k ) { $log[] = array( 'url' => $url, 'ok' => false, 'error' => 'Not a page this refresh asks for.' ); continue; }
            $by[ $k['kind'] ][] = array( (string) $url, $b64, $k );
        }

        $club_ok = 0;
        foreach ( $by['club'] as list( $url, $b64, $k ) ) {
            $html  = Allstars_Adult_Teams::unpack_page( $b64 );
            $teams = $html ? self::parse_club_page( $html ) : array();
            if ( ! $teams ) { $log[] = array( 'url' => $url, 'ok' => false, 'error' => 'Club page unreadable.' ); continue; }
            $club_ok++;
            foreach ( $teams as $t ) {
                $id = $t['league'];
                if ( ! isset( $cfg['leagues'][ $id ] ) ) $cfg['leagues'][ $id ] = array( 'name' => '', 'clubs' => array(), 'discovered_at' => 0 );
                if ( $t['league_name'] ) $cfg['leagues'][ $id ]['name'] = $t['league_name'];
                $cfg['leagues'][ $id ]['seen_at'] = $now;
            }
            $log[] = array( 'url' => $url, 'ok' => true, 'teams' => count( $teams ) );
        }
        if ( $club_ok ) {
            $cfg['club_pages_at'] = $now;
            // A league no club page has listed for two weekly checks has no side of ours any more.
            foreach ( $cfg['leagues'] as $id => $l ) {
                if ( (int) ( $l['seen_at'] ?? 0 ) < $now - 15 * DAY_IN_SECONDS ) unset( $cfg['leagues'][ $id ] );
            }
        }

        foreach ( $by['disc'] as list( $url, $b64, $k ) ) {
            $id = $k['league'];
            if ( ! isset( $cfg['leagues'][ $id ] ) ) continue;
            $html = Allstars_Adult_Teams::unpack_page( $b64 );
            if ( ! $html ) { $log[] = array( 'url' => $url, 'ok' => false, 'error' => 'Page unreadable.' ); continue; }
            $cfg['leagues'][ $id ]['clubs']         = self::our_clubs_in( $html );
            $cfg['leagues'][ $id ]['discovered_at'] = $now;
            $title = Allstars_Adult_Teams::title_part( $html, 1 );
            if ( $title ) $cfg['leagues'][ $id ]['name'] = $title;
            $log[] = array( 'url' => $url, 'ok' => true, 'clubs' => $cfg['leagues'][ $id ]['clubs'] );
        }

        foreach ( $by['list'] as list( $url, $b64, $k ) ) {
            $id  = $k['league'];
            $key = $id . ':' . $k['club'];
            if ( ! in_array( $k['club'], (array) ( $cfg['leagues'][ $id ]['clubs'] ?? array() ), true ) ) continue;
            $prev = $data['lists'][ $key ] ?? array();
            $html = Allstars_Adult_Teams::unpack_page( $b64 );
            if ( ! $html ) {
                $data['lists'][ $key ] = array_merge( $prev, array( 'league' => $id, 'club' => $k['club'], 'error' => 'Page unreadable.', 'last_attempt' => $now ) );
                $log[] = array( 'url' => $url, 'ok' => false, 'error' => 'Page unreadable.' );
                continue;
            }
            if ( self::selected_club_is_ours( $html ) === false ) {
                // Full-Time ignored our club filter (club re-registered?) — find the new ID next run.
                $cfg['leagues'][ $id ]['discovered_at'] = 0;
                $data['lists'][ $key ] = array_merge( $prev, array( 'league' => $id, 'club' => $k['club'], 'error' => 'Club filter not recognised — re-checking the league.', 'last_attempt' => $now ) );
                $log[] = array( 'url' => $url, 'ok' => false, 'error' => 'Club filter not recognised.' );
                continue;
            }
            $rows = array_values( array_filter( Allstars_Adult_Teams::parse_fixture_rows( $html ), function( $r ) {
                return Allstars_Adult_Teams::is_our_team( $r['home'] ) || Allstars_Adult_Teams::is_our_team( $r['away'] );
            } ) );
            $data['lists'][ $key ] = array( 'league' => $id, 'club' => $k['club'], 'rows' => $rows, 'fetched_at' => $now, 'last_attempt' => $now, 'error' => '' );
            $log[] = array( 'url' => $url, 'ok' => true, 'fixtures' => count( $rows ) );
        }

        foreach ( $failed as $url => $msg ) {
            $k = self::classify( (string) $url );
            if ( ! $k || $k['kind'] !== 'list' ) continue;
            $key = $k['league'] . ':' . $k['club'];
            $data['lists'][ $key ] = array_merge( $data['lists'][ $key ] ?? array(), array(
                'league' => $k['league'], 'club' => $k['club'], 'error' => sanitize_text_field( (string) $msg ), 'last_attempt' => $now,
            ) );
        }

        // Drop lists for leagues / club IDs we no longer follow.
        foreach ( $data['lists'] as $key => $l ) {
            list( $id, $club ) = array_pad( explode( ':', (string) $key, 2 ), 2, '' );
            if ( ! in_array( $club, (array) ( $cfg['leagues'][ $id ]['clubs'] ?? array() ), true ) ) unset( $data['lists'][ $key ] );
        }

        update_option( self::OPT_CFG, $cfg, false );
        update_option( self::OPT_DATA, $data, false );

        return rest_ensure_response( array(
            'ok'       => true,
            'imported' => $log,
            'next'     => self::needed_urls( $cfg, false ),
        ) );
    }

    private static function rest_done( array $b ) {
        $data = self::data();
        $data['last_run'] = array(
            'at'      => time(),
            'fetched' => (int) ( $b['fetched'] ?? 0 ),
            'failed'  => (int) ( $b['failed'] ?? 0 ),
            'blocked' => ! empty( $b['blocked'] ),
            'message' => sanitize_text_field( (string) ( $b['message'] ?? '' ) ),
        );
        update_option( self::OPT_DATA, $data, false );

        if ( function_exists( 'sg_cachepress_purge_url' ) ) {
            foreach ( self::pages() as $id => $path ) sg_cachepress_purge_url( get_permalink( $id ) );
        }

        $vm   = self::view_model();
        $days = array();
        foreach ( $vm['days'] as $d ) $days[ $d['date'] ] = $d['count'];
        return rest_ensure_response( array(
            'ok'      => true,
            'leagues' => count( array_filter( self::cfg()['leagues'], function( $l ) { return ! empty( $l['clubs'] ); } ) ),
            'days'    => $days,
            'check'   => $vm['check_count'],
            'away'    => $vm['away'],
        ) );
    }

    // ── View ──────────────────────────────────────────────────────────────────

    /** Artificial pitch? Full-Time venues say so as "3G" ("WOMBOURNE LEISURE CENTRE 9v9 Pitch C&D - 10:45 3G"). */
    public static function is_3g( $text ) {
        return (bool) preg_match( '/\b3G\b/i', (string) $text );
    }

    /** Saturday + Sunday dates → "17–18 Oct", or "31 Oct – 1 Nov" across a month end. */
    private static function range_label( array $dates, DateTimeZone $tz ) {
        $a = new DateTime( $dates[0], $tz );
        $b = new DateTime( $dates[1], $tz );
        return $a->format( 'M' ) === $b->format( 'M' ) ? $a->format( 'j' ) . '–' . $b->format( 'j M' ) : $a->format( 'j M' ) . ' – ' . $b->format( 'j M' );
    }

    /** "Postponed Match Notes: …" → "Postponed"; '' for a normal fixture. */
    private static function short_status( $status ) {
        $status = trim( (string) $status );
        if ( $status === '' ) return '';
        if ( preg_match( '/postpon|\bP\s*-\s*P\b/i', $status ) ) return 'Postponed';
        if ( preg_match( '/walkover|\b[HA]\s*-\s*W\b/i', $status ) ) return 'Walkover';
        if ( preg_match( '/abandon/i', $status ) ) return 'Abandoned';
        if ( preg_match( '/cancel|void|withdrawn/i', $status ) ) return 'Cancelled';
        return wp_html_excerpt( $status, 40, '…' );
    }

    /**
     * Days to show: this weekend and next (from Sunday 5pm the week ahead takes over, since that's when
     * the weekly refresh runs), plus any midweek day with a home game. Each day → grounds → pitches → games.
     */
    public static function view_model( $now = null ) {
        $now   = $now ?: time();
        $tz    = new DateTimeZone( Allstars_Adult_Teams::TZ );
        $clock = new DateTime( '@' . $now );
        $clock->setTimezone( $tz );
        $start = new DateTime( $clock->format( 'Y-m-d' ), $tz );
        if ( $clock->format( 'N' ) === '7' && (int) $clock->format( 'G' ) >= 17 ) $start->modify( '+1 day' );
        $dow  = (int) $start->format( 'N' );
        $sat1 = clone $start;
        if ( $dow === 7 ) $sat1->modify( '-1 day' ); elseif ( $dow !== 6 ) $sat1->modify( 'next saturday' );
        $first = $start->format( 'Y-m-d' );
        $wk1   = array( $sat1->format( 'Y-m-d' ), ( clone $sat1 )->modify( '+1 day' )->format( 'Y-m-d' ) );
        $wk2   = array( ( clone $sat1 )->modify( '+7 days' )->format( 'Y-m-d' ), ( clone $sat1 )->modify( '+8 days' )->format( 'Y-m-d' ) );
        $last  = $wk2[1];

        $grounds = self::grounds();
        $data    = self::data();
        $cfg     = self::cfg();

        $rows = array();
        $updated = 0; $oldest = 0; $behind = array();
        foreach ( $data['lists'] as $l ) {
            foreach ( (array) ( $l['rows'] ?? array() ) as $r ) $rows[ $r['key'] ] = $r;   // a cup tie can arrive via several leagues
            $f = (int) ( $l['fetched_at'] ?? 0 );
            $updated = max( $updated, $f );
            $oldest  = $oldest ? min( $oldest, $f ) : $f;
            if ( ! $f || $now - $f > 3 * DAY_IN_SECONDS ) $behind[] = $cfg['leagues'][ $l['league'] ]['name'] ?? $l['league'];
        }

        $days = array();
        foreach ( array_merge( $wk1, $wk2 ) as $d ) if ( $d >= $first ) $days[ $d ] = array();
        $away = 0;
        foreach ( $rows as $r ) {
            if ( $r['date'] < $first || $r['date'] > $last ) continue;
            if ( ! Allstars_Adult_Teams::is_our_team( $r['home'] ) ) { $away++; continue; }
            $days[ $r['date'] ][] = $r;
        }
        ksort( $days );

        $out = array(); $check_count = 0;
        foreach ( $days as $date => $list ) {
            $dt  = new DateTime( $date, $tz );
            $day = array(
                'date'    => $date,
                'label'   => $dt->format( 'l j F' ),
                'kicker'  => in_array( $date, $wk1, true ) ? 'This weekend' : ( in_array( $date, $wk2, true ) ? 'Next weekend' : 'Midweek' ),
                'count'   => count( $list ),
                'grounds' => array(),
                'check'   => array(),
            );
            foreach ( $list as $r ) {
                $status = self::short_status( $r['status'] );
                $ours   = trim( preg_replace( '/^wombourne\s*all\s*stars\s*/i', '', $r['home'] ) );
                $g = array(
                    'time'   => $r['time'],
                    'ours'   => $ours !== '' ? $ours : 'First Team',
                    'opp'    => $r['away'],
                    'comp'   => $r['competition'],
                    'status' => $status,
                    'off'    => in_array( $status, array( 'Postponed', 'Walkover', 'Abandoned', 'Cancelled' ), true ),
                    'url'    => $r['url'],
                    'venue'  => $r['venue'],
                    'clash'  => false,
                    'g3'     => self::is_3g( $r['venue'] ),
                );
                list( $gi, $rest ) = self::match_ground( $r['venue'], $grounds );
                if ( $gi < 0 ) { $day['check'][] = $g; $check_count++; continue; }
                $label   = self::pitch_label( $rest, $r['time'] );
                $pkey    = strtolower( preg_replace( '/\s+/', '', $label ) );
                $g['g3'] = self::is_3g( $label );   // the pitch part only — a ground name could mention 3G
                if ( ! isset( $day['grounds'][ $gi ] ) ) $day['grounds'][ $gi ] = array( 'name' => $grounds[ $gi ]['name'], 'count' => 0, 'pitches' => array() );
                if ( ! isset( $day['grounds'][ $gi ]['pitches'][ $pkey ] ) ) $day['grounds'][ $gi ]['pitches'][ $pkey ] = array( 'label' => $label, 'raw' => $r['venue'], 'g3' => $g['g3'], 'games' => array() );
                $day['grounds'][ $gi ]['pitches'][ $pkey ]['games'][] = $g;
                $day['grounds'][ $gi ]['count']++;
            }
            ksort( $day['grounds'] );
            $by_time = function( $a, $b ) { return strcmp( $a['time'] ?: '99', $b['time'] ?: '99' ); };
            foreach ( $day['grounds'] as &$gr ) {
                uksort( $gr['pitches'], function( $a, $b ) { return ( $a === '' ) - ( $b === '' ) ?: strnatcasecmp( $a, $b ); } );
                foreach ( $gr['pitches'] as &$p ) {
                    usort( $p['games'], $by_time );
                    // Two live games on the same pitch at the same time: flag both.
                    $seen = array();
                    foreach ( $p['games'] as $i => $g ) {
                        if ( $g['off'] || ! $g['time'] ) continue;
                        if ( isset( $seen[ $g['time'] ] ) ) { $p['games'][ $i ]['clash'] = true; $p['games'][ $seen[ $g['time'] ] ]['clash'] = true; }
                        else $seen[ $g['time'] ] = $i;
                    }
                }
                unset( $p );
            }
            unset( $gr );
            usort( $day['check'], $by_time );
            $out[] = $day;
        }

        // One tab per weekend; a midweek day goes with the weekend that follows it.
        $tabs = array(
            'this' => array( 'label' => 'This weekend', 'dates' => self::range_label( $wk1, $tz ), 'count' => 0, 'days' => array() ),
            'next' => array( 'label' => 'Next weekend', 'dates' => self::range_label( $wk2, $tz ), 'count' => 0, 'days' => array() ),
        );
        foreach ( $out as $day ) {
            $t = $day['date'] <= $wk1[1] ? 'this' : 'next';
            $tabs[ $t ]['days'][] = $day;
            $tabs[ $t ]['count'] += $day['count'];
        }

        return array(
            'tabs'        => $tabs,
            'days'        => $out,
            'away'        => $away,
            'check_count' => $check_count,
            'updated'     => $updated,
            'stale'       => ! $updated || $now - $updated > 3 * DAY_IN_SECONDS,
            'behind'      => $updated ? array_values( array_unique( $behind ) ) : array(),
            'has_data'    => ! empty( $data['lists'] ),
        );
    }

    public static function shortcode( $atts = array() ) {
        wp_enqueue_style( 'allstars-adult-teams' );
        wp_enqueue_script( 'aat-pitch-schedule', AAT_URL . 'assets/pitch-schedule.js', array(), AAT_VERSION, true );   // weekend tabs + "Hide 3G" filter
        $vm = self::view_model();
        ob_start();
        include AAT_DIR . 'templates/pitch-schedule.php';
        return ob_get_clean();
    }
}
