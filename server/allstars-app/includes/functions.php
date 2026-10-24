<?php
/**
 * Shared helpers: the on/off gate, option readers, dates, errors and the push entry point.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The app switch: 'off' (default) | 'testers' | 'on'. Anything unexpected counts as off.
 */
function wa_app_mode(): string {
    $mode = get_option( 'wa_app_mode', 'off' );
    return in_array( $mode, array( 'off', 'testers', 'on' ), true ) ? $mode : 'off';
}

/**
 * Tester email addresses, lowercase and de-duplicated. Accepts the stored array, or a
 * newline/comma separated string in case the option was set by hand (WP-CLI).
 */
function wa_app_testers(): array {
    $raw = get_option( 'wa_app_testers', array() );
    if ( is_string( $raw ) ) {
        $raw = preg_split( '/[\s,;]+/', $raw );
    }
    if ( ! is_array( $raw ) ) {
        return array();
    }
    $out = array();
    foreach ( $raw as $email ) {
        if ( ! is_scalar( $email ) ) {
            continue;
        }
        $email = strtolower( trim( (string) $email ) );
        if ( '' !== $email && is_email( $email ) ) {
            $out[ $email ] = $email;
        }
    }
    return array_values( $out );
}

/**
 * May this account use the app (and ASTRO's was/v1 API)?
 * off → nobody; testers → accounts whose email is on the tester list; on → anyone with an account.
 */
function wa_app_user_allowed( int $user_id ): bool {
    if ( $user_id <= 0 ) {
        return false;
    }
    $mode = wa_app_mode();
    if ( 'off' === $mode ) {
        return false;
    }
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return false;
    }
    $allowed = 'on' === $mode || in_array( strtolower( (string) $user->user_email ), wa_app_testers(), true );

    /**
     * Last word on whether a user may use the app (e.g. to block one account). Not consulted when the app is off.
     *
     * @param bool   $allowed
     * @param int    $user_id
     * @param string $mode    'testers' | 'on'
     */
    return (bool) apply_filters( 'wa_app_user_allowed', $allowed, $user_id, $mode );
}

/**
 * ASTRO's was/v1 routes ask this filter before serving anyone, so its API stays inert
 * unless this plugin is active and lets the user in.
 */
function wa_app_filter_was_rest_user_allowed( $allowed, $user_id = 0 ): bool {
    return wa_app_user_allowed( (int) $user_id );
}
add_filter( 'was_rest_user_allowed', 'wa_app_filter_was_rest_user_allowed', 10, 2 );

// ── Errors ───────────────────────────────────────────────────────────────────

/** REST error with an HTTP status. Messages are shown to parents by the app, so keep them plain. */
function wa_app_error( string $code, string $message, int $status ): WP_Error {
    return new WP_Error( $code, $message, array( 'status' => $status ) );
}

function wa_app_closed_error(): WP_Error {
    return wa_app_error( 'wa_app_closed', "The Allstars app isn't open yet.", 403 );
}

// ── Dates (stored in UTC, sent with the site's offset) ──────────────────────

/** Now as a UTC MySQL datetime. */
function wa_app_now(): string {
    return gmdate( 'Y-m-d H:i:s' );
}

/** A Unix timestamp as a UTC MySQL datetime. */
function wa_app_gmt( int $ts ): string {
    return gmdate( 'Y-m-d H:i:s', $ts );
}

/** UTC MySQL datetime → ISO 8601 with the site's offset (2026-10-24T16:05:00+01:00), or null. */
function wa_app_iso_from_gmt( $gmt ): ?string {
    if ( ! is_string( $gmt ) || '' === $gmt || 0 === strpos( $gmt, '0000-00-00' ) ) {
        return null;
    }
    $dt = date_create_immutable( $gmt, new DateTimeZone( 'UTC' ) );
    if ( ! $dt ) {
        return null;
    }
    return $dt->setTimezone( wp_timezone() )->format( 'c' );
}

/** Unix timestamp → ISO 8601 with the site's offset. */
function wa_app_iso_from_ts( int $ts ): string {
    $dt = new DateTimeImmutable( '@' . $ts );
    return $dt->setTimezone( wp_timezone() )->format( 'c' );
}

/** UTC MySQL datetime → Unix timestamp (0 if unreadable). */
function wa_app_ts_from_gmt( $gmt ): int {
    if ( ! is_string( $gmt ) || '' === $gmt ) {
        return 0;
    }
    $ts = strtotime( $gmt . ' UTC' );
    return false === $ts ? 0 : (int) $ts;
}

// ── Tokens ───────────────────────────────────────────────────────────────────

/** 32 random bytes, base64url without padding (43 characters). */
function wa_app_random_token(): string {
    return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
}

/** Shape check before any lookup, so junk never reaches the database. */
function wa_app_token_looks_valid( $token ): bool {
    return is_string( $token ) && 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/', $token );
}

// ── Push ─────────────────────────────────────────────────────────────────────

/**
 * Queue a push notification for these users' enabled devices (only users the app lets in).
 * Text must stay generic: never children's names, medical or money details.
 *
 * @param int[]  $user_ids
 * @param string $title
 * @param string $body
 * @param array  $data     Sent with the push for deep linking, e.g. [ 'type' => 'club_message', 'ref_id' => 12 ].
 * @return int Number of pushes queued.
 */
function wa_queue_push( $user_ids, string $title, string $body, array $data = array() ): int {
    return WA_App_Push::queue( (array) $user_ids, $title, $body, $data );
}
