<?php
defined( 'ABSPATH' ) || exit;

class WAS_GoCardless {

    // ── Config helpers ─────────────────────────────────────────────────────────

    private static function api_key() {
        if ( self::is_sandbox() ) {
            return get_option( 'was_gc_sandbox_api_key', get_option( 'was_gc_api_key', '' ) );
        }
        return get_option( 'was_gc_live_api_key', '' );
    }

    private static function webhook_secret() {
        if ( self::is_sandbox() ) {
            $secret = get_option( 'was_gc_sandbox_webhook_secret', get_option( 'was_gc_webhook_secret', '' ) );
        } else {
            $secret = get_option( 'was_gc_live_webhook_secret', '' );
        }
        // Strip any slashes WordPress may have added when saving special characters
        return wp_unslash( stripslashes_deep( $secret ) );
    }

    public static function is_sandbox() {
        return (bool) get_option( 'was_gc_sandbox', 1 );
    }

    private static function base_url() {
        return self::is_sandbox()
            ? 'https://api-sandbox.gocardless.com'
            : 'https://api.gocardless.com';
    }

    // ── Core HTTP request ──────────────────────────────────────────────────────

    private static function request( $method, $endpoint, $data = null ) {
        // Release any active PHP session before making outbound HTTP requests.
        // Some plugins (WP Easy Pay, WooCommerce Square etc.) call session_start()
        // which blocks wp_remote_request() until the session lock is released.
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            session_write_close();
        }

        $args = array(
            'method'  => strtoupper( $method ),
            'headers' => array(
                'Authorization'      => 'Bearer ' . self::api_key(),
                'GoCardless-Version' => '2015-07-06',
                'Content-Type'       => 'application/json',
                'Accept'             => 'application/json',
            ),
            'timeout' => 30,
        );

        if ( $data !== null ) {
            $args['body'] = wp_json_encode( $data );
        }

        $response = wp_remote_request( self::base_url() . $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            $msg = $response->get_error_message();
            error_log( '[WAS] GoCardless request error: ' . $msg );
            set_transient( 'was_gc_last_error', array(
                'code'    => 'WP_Error',
                'message' => $msg,
                'body'    => '',
                'time'    => current_time( 'mysql' ),
            ), 300 );
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $err = $body['error']['message'] ?? wp_remote_retrieve_body( $response );
            error_log( "[WAS] GoCardless HTTP {$code}: {$err}" );
            // Store last error for debugging
            set_transient( 'was_gc_last_error', array(
                'code'    => $code,
                'message' => $err,
                'body'    => wp_remote_retrieve_body( $response ),
                'time'    => current_time( 'mysql' ),
            ), 300 );
            return null;
        }

        return $body;
    }

    // ── Redirect flows (mandate setup) ─────────────────────────────────────────

    /**
     * Create a redirect flow.
     * Returns the flow object (including redirect_url) or null on failure.
     *
     * @param int    $team_id       Used to pass back in the return URL.
     * @param string $session_token Random token we generate — GC echoes it back.
     * @param string $email         Optional prefill for GC form.
     * @param string $full_name     Optional prefill for GC form.
     * @param string $return_page   The WordPress page URL to return to.
     */
    public static function create_redirect_flow( $team_id, $session_token, $email = '', $full_name = '', $return_page = '' ) {
        if ( empty( $return_page ) ) {
            $return_page = home_url( '/register/' );
        }

        $success_url = add_query_arg( array(
            'was_gc_return'  => '1',
            'team_id'        => $team_id,
        ), $return_page );

        $prefill = array_filter( array(
            'email'       => sanitize_email( $email ),
            'given_name'  => trim( explode( ' ', $full_name )[0] ?? '' ),
            'family_name' => trim( substr( strstr( $full_name, ' ' ), 1 ) ),
        ) );

        $flow_data = array(
            'description'          => get_bloginfo( 'name' ) . ' — Club Subscriptions',
            'session_token'        => $session_token,
            'success_redirect_url' => $success_url,
        );

        // Only include prefilled_customer if we actually have values — GC rejects an empty object
        if ( ! empty( $prefill ) ) {
            $flow_data['prefilled_customer'] = $prefill;
        }

        $result = self::request( 'POST', '/redirect_flows', array(
            'redirect_flows' => $flow_data,
        ) );

        return $result['redirect_flows'] ?? null;
    }

    /**
     * Complete a redirect flow after the parent returns from GoCardless.
     * Returns the completed flow (contains mandate_id, customer_id) or null.
     */
    public static function complete_redirect_flow( $redirect_flow_id, $session_token ) {
        $result = self::request(
            'POST',
            "/redirect_flows/{$redirect_flow_id}/actions/complete",
            array( 'data' => array( 'session_token' => $session_token ) )
        );

        return $result['redirect_flows'] ?? null;
    }

    // ── Subscriptions ──────────────────────────────────────────────────────────

    /**
     * Create a monthly subscription against a mandate, collected on a fixed day of the
     * month: the 1st, or the child's manual payment-day override (was_payment_day()).
     *
     * GoCardless repeats a subscription on the day of month it starts on, so a start
     * date on any other day (e.g. a weekend-shifted charge date like 3 August, or a
     * mandate's next possible charge date) would put the child on that day for good.
     * Any start date passed in is therefore moved to the payment day on or after it,
     * and day_of_month is pinned. A payment day that is not a working day is collected
     * on the next working day by GoCardless, and the schedule stays on that day.
     *
     * @param string $mandate_id   GC mandate ID.
     * @param int    $amount_pence Amount in pence.
     * @param string $name         Description shown on bank statement.
     * @param string $start_date   Optional YYYY-MM-DD (should fall on $day).
     * @param int    $day          Day of month to collect on, 1-28 (default the 1st).
     */
    public static function create_subscription( $mandate_id, $amount_pence, $name, $start_date = '', $day = 1 ) {
        $day = ( (int) $day >= 1 && (int) $day <= 28 ) ? (int) $day : 1;
        $payload = array(
            'amount'        => (int) $amount_pence,
            'currency'      => 'GBP',
            'interval_unit' => 'monthly',
            'interval'      => 1,
            'day_of_month'  => $day,
            'name'          => $name,
            'links'         => array( 'mandate' => $mandate_id ),
        );

        if ( $start_date ) {
            $payload['start_date'] = self::day_on_or_after( $start_date, $day );
        }

        $result = self::request( 'POST', '/subscriptions', array( 'subscriptions' => $payload ) );
        return $result['subscriptions'] ?? null;
    }

    public static function cancel_subscription( $subscription_id ) {
        // The data envelope must be a JSON object. An empty PHP array() encodes to
        // [] which GoCardless rejects ("Root element must be an object"), so cast
        // to an object to produce {"data":{}}.
        return self::request( 'POST', "/subscriptions/{$subscription_id}/actions/cancel", array( 'data' => (object) array() ) );
    }

    public static function get_subscription( $subscription_id ) {
        $result = self::request( 'GET', "/subscriptions/{$subscription_id}" );
        return $result['subscriptions'] ?? null;
    }

    /**
     * Fetch a mandate object (status, next_possible_charge_date, etc.).
     * Returns the mandate array or null.
     */
    public static function get_mandate( $mandate_id ) {
        if ( empty( $mandate_id ) ) return null;
        $result = self::request( 'GET', '/mandates/' . urlencode( $mandate_id ) );
        return $result['mandates'] ?? null;
    }

    /** The earliest date GoCardless can charge this mandate (YYYY-MM-DD), or ''. */
    public static function next_possible_charge_date( $mandate_id ) {
        $mandate = self::get_mandate( $mandate_id );
        return is_array( $mandate ) ? (string) ( $mandate['next_possible_charge_date'] ?? '' ) : '';
    }

    // ── Payment-day schedule helpers ───────────────────────────────────────────

    /** The given day of the month ($day, 1-28) on or after a YYYY-MM-DD date. */
    public static function day_on_or_after( $ymd, $day = 1 ) {
        $ts = strtotime( $ymd );
        if ( ! $ts ) return $ymd;
        $same = date( 'Y-m-', $ts ) . sprintf( '%02d', $day );
        return ( strtotime( $same ) >= strtotime( date( 'Y-m-d', $ts ) ) )
            ? $same
            : date( 'Y-m-', strtotime( 'first day of next month', $ts ) ) . sprintf( '%02d', $day );
    }

    /** The 1st of the month on or after a YYYY-MM-DD date. */
    public static function first_on_or_after( $ymd ) {
        return self::day_on_or_after( $ymd, 1 );
    }

    /**
     * Where a replacement subscription should start so it takes over the old one's
     * next collection without a double charge or a missed month: the payment day ($day)
     * in the month of the old subscription's next (not yet created) payment. That
     * payment's charge date may have been moved for a weekend/bank holiday — we
     * deliberately do NOT start on that shifted date, or the schedule would drift to it.
     * With no existing subscription: the deferred first-payment date if one is set,
     * otherwise the first payment day the mandate can actually be charged on.
     */
    public static function aligned_start_date( $mandate_id, $existing_subscription_id = '', $day = 1 ) {
        $day = ( (int) $day >= 1 && (int) $day <= 28 ) ? (int) $day : 1;
        if ( $existing_subscription_id ) {
            $existing = self::get_subscription( $existing_subscription_id );
            if ( ! empty( $existing['upcoming_payments'][0]['charge_date'] ) ) {
                return date( 'Y-m-', strtotime( $existing['upcoming_payments'][0]['charge_date'] ) ) . sprintf( '%02d', $day );
            }
        }
        if ( was_first_payment_date() ) {
            return self::day_on_or_after( was_first_payment_date(), $day );
        }
        $npcd = self::next_possible_charge_date( $mandate_id );
        return self::day_on_or_after( $npcd ?: date( 'Y-m-d', strtotime( 'first day of next month' ) ), $day );
    }

    /** Day of month an existing GoCardless subscription collects on. */
    public static function subscription_day( $sub ) {
        if ( ! empty( $sub['day_of_month'] ) && (int) $sub['day_of_month'] > 0 ) return (int) $sub['day_of_month'];
        return ! empty( $sub['start_date'] ) ? (int) date( 'j', strtotime( $sub['start_date'] ) ) : 1;
    }

    /**
     * Work out (without changing anything) how moving a child's Direct Debit to a new
     * day of the month would play out. The rule is ONE collection per calendar month,
     * never a double charge or a missed month:
     *
     *   - Payments GoCardless has already created are untouched and still collected.
     *   - The new schedule takes over from the month of the old subscription's next
     *     (not yet created) payment, on the new day.
     *   - If that month's new date is too soon for Bacs (or already past), that month
     *     is collected on its CURRENT date as a one-off, and the new day starts the
     *     following month.
     *
     * A child with no active Direct Debit just has the preference stored, used when a
     * Direct Debit is next set up. Returns array( 'ok', 'message', ... plan ).
     */
    public static function plan_payment_day_change( $player, $new_day ) {
        $new_day = (int) $new_day;
        if ( $new_day < 1 || $new_day > 28 ) {
            return array( 'ok' => false, 'message' => 'Choose a day between the 1st and the 28th (GoCardless cannot collect on the 29th-31st).' );
        }
        $child = trim( $player->first_name . ' ' . $player->last_name );
        $plan  = array( 'ok' => true, 'new_day' => $new_day, 'store_only' => false, 'lines' => array() );

        $billable = ( $player->participation_type === 'signed' && $player->registration_status === 'active'
            && ! empty( $player->gc_mandate_id ) && ! empty( $player->gc_subscription_id ) );
        if ( ! $billable ) {
            $plan['store_only'] = true;
            $plan['message']    = $child . ' has no active Direct Debit, so nothing changes in GoCardless now. The ' . was_ordinal( $new_day ) . ' will be used when a Direct Debit is next set up.';
            return $plan;
        }

        $old = self::get_subscription( $player->gc_subscription_id );
        if ( ! is_array( $old ) || ( $old['status'] ?? '' ) !== 'active' ) {
            return array( 'ok' => false, 'message' => "ASTRO's subscription for {$child} (" . $player->gc_subscription_id . ') is ' . ( $old['status'] ?? 'not found' ) . ' in GoCardless. Check it before changing the payment date.' );
        }
        $cur_day = self::subscription_day( $old );
        if ( $cur_day === $new_day ) {
            return array( 'ok' => false, 'message' => $child . ' is already collected on the ' . was_ordinal( $new_day ) . ' of each month.' );
        }
        $next = $old['upcoming_payments'][0]['charge_date'] ?? '';
        if ( ! $next ) {
            return array( 'ok' => false, 'message' => 'GoCardless shows no upcoming payments on this subscription, so the date cannot be moved safely.' );
        }

        $npcd   = self::next_possible_charge_date( $player->gc_mandate_id );
        $floor  = max( strtotime( current_time( 'Y-m-d' ) . ' +1 day' ), $npcd ? strtotime( $npcd ) : 0 );
        $target = date( 'Y-m-', strtotime( $next ) ) . sprintf( '%02d', $new_day );
        $bridge = null;
        if ( strtotime( $target ) >= $floor ) {
            $start = $target;
        } else {
            $start  = date( 'Y-m-', strtotime( 'first day of next month', strtotime( $next ) ) ) . sprintf( '%02d', $new_day );
            $bridge = array( 'date' => $next, 'pence' => (int) $old['amount'] );
        }

        // Payments already created on the old subscription (still collected).
        $already = array();
        $res = self::request( 'GET', '/payments?subscription=' . urlencode( $old['id'] ) . '&limit=20' );
        foreach ( ( $res['payments'] ?? array() ) as $py ) {
            if ( in_array( $py['status'], array( 'pending_submission', 'pending_customer_approval', 'submitted' ), true ) ) {
                $already[] = array( 'date' => $py['charge_date'], 'pence' => (int) $py['amount'] );
            }
        }
        usort( $already, function ( $a, $b ) { return strcmp( $a['date'], $b['date'] ); } );

        $amt = '£' . number_format( $old['amount'] / 100, 2 );
        foreach ( $already as $a ) {
            $plan['lines'][] = '£' . number_format( $a['pence'] / 100, 2 ) . ' on ' . date_i18n( 'j M Y', strtotime( $a['date'] ) ) . ' — already with the bank, still collected';
        }
        if ( $bridge ) {
            $plan['lines'][] = $amt . ' on ' . date_i18n( 'j M Y', strtotime( $bridge['date'] ) ) . ' — ' . date_i18n( 'F', strtotime( $bridge['date'] ) ) . "'s payment, kept on its current date (too late to move this month)";
        }
        for ( $i = 0; $i < 3; $i++ ) {
            $d = strtotime( "+{$i} month", strtotime( $start ) );
            $plan['lines'][] = $amt . ' on ' . date_i18n( 'j M Y', $d ) . ( $i === 0 ? ' — first collection on the new day' : '' );
        }
        $plan['lines'][] = '…then the ' . was_ordinal( $new_day ) . ' of every month (the next working day if it falls on a weekend or bank holiday).';

        $plan += array(
            'old_id'  => $old['id'],
            'name'    => $old['name'],
            'pence'   => (int) $old['amount'],
            'cur_day' => $cur_day,
            'start'   => $start,
            'bridge'  => $bridge,
            'message' => "Move {$child} from the " . was_ordinal( $cur_day ) . ' to the ' . was_ordinal( $new_day ) . ' of each month. Next collections:',
        );
        return $plan;
    }

    /**
     * Carry out plan_payment_day_change(): new subscription on the new day first, then
     * the bridging one-off (if any), then cancel the old subscription — rolling back
     * on any failure so a child can never end up with two live subscriptions.
     */
    public static function change_payment_day( $player, $new_day, $notify = true ) {
        global $wpdb;
        $plan = self::plan_payment_day_change( $player, $new_day );
        if ( empty( $plan['ok'] ) ) return $plan;

        $store = ( (int) $new_day === 1 ) ? null : (int) $new_day;
        $child = trim( $player->first_name . ' ' . $player->last_name );

        if ( $plan['store_only'] ) {
            $wpdb->update( "{$wpdb->prefix}was_players", array( 'payment_day' => $store ), array( 'id' => $player->id ) );
            return array( 'ok' => true, 'message' => 'Saved. ' . $plan['message'] );
        }

        $new = self::create_subscription( $player->gc_mandate_id, $plan['pence'], $plan['name'], $plan['start'], $plan['new_day'] );
        if ( empty( $new['id'] ) ) {
            return array( 'ok' => false, 'message' => 'GoCardless could not create the new schedule (' . self::last_error_message() . '). Nothing was changed.' );
        }

        $oneoff = null;
        if ( $plan['bridge'] ) {
            $desc   = mb_substr( $plan['name'] . ' — ' . date( 'F Y', strtotime( $plan['bridge']['date'] ) ), 0, 100 );
            $oneoff = self::create_payment( $player->gc_mandate_id, $plan['bridge']['pence'], $desc, $plan['bridge']['date'] );
            if ( empty( $oneoff['id'] ) ) {
                $err = self::last_error_message();
                self::cancel_subscription( $new['id'] );
                return array( 'ok' => false, 'message' => 'GoCardless could not keep this month\'s payment (' . $err . '). Nothing was changed.' );
            }
        }

        if ( ! self::cancel_subscription( $plan['old_id'] ) ) {
            $err = self::last_error_message();
            self::cancel_subscription( $new['id'] );
            if ( $oneoff ) self::cancel_payment( $oneoff['id'] );
            return array( 'ok' => false, 'message' => 'GoCardless could not cancel the old schedule (' . $err . '). The change was rolled back; nothing was changed.' );
        }

        $wpdb->update( "{$wpdb->prefix}was_players", array( 'gc_subscription_id' => $new['id'], 'payment_day' => $store ), array( 'id' => $player->id ) );
        if ( $oneoff ) {
            WAS_Database::upsert_payment( $oneoff['id'], $player->id, $player->primary_guardian_id, $player->gc_mandate_id,
                $plan['bridge']['pence'] / 100, $oneoff['charge_date'] ?? $plan['bridge']['date'], 'pending_submission', $player->season, $desc );
        }
        error_log( "[WAS] Payment day: player {$player->id} moved from day {$plan['cur_day']} to day {$plan['new_day']} ({$plan['old_id']} -> {$new['id']}, first {$plan['start']})" . ( $oneoff ? ", bridging one-off {$oneoff['id']} on {$plan['bridge']['date']}" : '' ) );

        $emailed = false;
        if ( $notify ) {
            $player->payment_day = $store;
            $emailed = self::notify_dd_updated( $player, array( 'amount' => $plan['pence'] ), $new, $plan['bridge'] );
        }
        if ( $emailed ) {
            $told = ' We have emailed the parent to explain it is a date change, not a cancellation (GoCardless will also email them).';
        } elseif ( WAS_Notifications::parent_emails_paused( $player ) ) {
            $told = ' Payment emails are paused for this family, so ASTRO did not email them. GoCardless will still email them the new schedule.';
        } else {
            $told = ' ASTRO did not email the parent. GoCardless will still email them the new schedule.';
        }
        return array( 'ok' => true, 'lines' => $plan['lines'],
            'message' => "Done — {$child} is now collected on the " . was_ordinal( $plan['new_day'] ) . ' of each month.' . $told );
    }

    /**
     * Part-month amount for a mid-season sign-up: from the sign-up date (inclusive)
     * to the end of that month, pro rata by days, plus a full month for every month
     * before the subscription can start (when sign-up is too close to the next 1st for
     * the Bacs lead time). Returns pence.
     */
    public static function prorata_pence( $monthly_pence, $signup_date, $sub_start ) {
        $s_ts   = strtotime( $signup_date );
        $dim    = (int) date( 't', $s_ts );
        $left   = $dim - (int) date( 'j', $s_ts ) + 1;
        $pence  = (int) round( $monthly_pence * $left / $dim );
        $cursor = strtotime( 'first day of next month', $s_ts );
        $start  = strtotime( $sub_start );
        $guard  = 0;
        while ( $cursor < $start && $guard < 24 ) {
            $pence += (int) $monthly_pence;
            $cursor = strtotime( '+1 month', $cursor );
            $guard++;
        }
        return $pence;
    }

    /**
     * Create a one-off payment against a mandate (used to collect a late sign-up's
     * missed first month). $charge_date optional YYYY-MM-DD — omit to charge ASAP.
     * Returns the payment array or null.
     */
    public static function create_payment( $mandate_id, $amount_pence, $description = '', $charge_date = '' ) {
        $payload = array(
            'amount'   => (int) $amount_pence,
            'currency' => 'GBP',
            'links'    => array( 'mandate' => $mandate_id ),
        );
        if ( $description ) {
            $payload['description'] = $description;
        }
        if ( $charge_date ) {
            $payload['charge_date'] = $charge_date;
        }
        $result = self::request( 'POST', '/payments', array( 'payments' => $payload ) );
        return $result['payments'] ?? null;
    }

    /**
     * Ask GoCardless to re-present a FAILED payment (same payment id, same amount)
     * on the next possible charge date. Only works while the mandate is active.
     * Returns the payment array (with the new charge_date) or null.
     */
    public static function retry_payment( $gc_payment_id ) {
        if ( empty( $gc_payment_id ) ) return null;
        $result = self::request(
            'POST',
            '/payments/' . urlencode( $gc_payment_id ) . '/actions/retry',
            array( 'data' => (object) array() )
        );
        return $result['payments'] ?? null;
    }

    /** Cancel a one-off payment that has not yet been submitted to the bank. */
    public static function cancel_payment( $gc_payment_id ) {
        if ( empty( $gc_payment_id ) ) return null;
        return self::request( 'POST', '/payments/' . urlencode( $gc_payment_id ) . '/actions/cancel', array( 'data' => (object) array() ) );
    }

    public static function get_payment( $gc_payment_id ) {
        if ( empty( $gc_payment_id ) ) return null;
        $result = self::request( 'GET', '/payments/' . urlencode( $gc_payment_id ) );
        return $result['payments'] ?? null;
    }

    /** Human-readable message from the most recent API error (if any). */
    public static function last_error_message() {
        $err = get_transient( 'was_gc_last_error' );
        return ( is_array( $err ) && ! empty( $err['message'] ) ) ? (string) $err['message'] : '';
    }

    /**
     * Create the monthly registration subscription, honouring a deferred first-payment
     * date and handling late sign-ups.
     *
     * If $desired_start is set but falls before the mandate's earliest possible charge
     * date (Bacs lead time — a "late" sign-up), the subscription is rolled forward to the
     * next occurrence of that day-of-month that GoCardless can actually charge, and a
     * one-off payment for the (full) missed month is taken on the earliest possible date
     * so the parent stays fully paid up.
     *
     * With no deferred date (the season has started) this is a MID-SEASON sign-up: the
     * monthly DD starts on the first 1st GoCardless can charge, and a one-off pro-rata
     * payment covers the sign-up date up to that 1st (see prorata_pence()).
     *
     * Returns array( 'subscription_id', 'late', 'sub_start', 'one_off_date',
     *                'one_off_id', 'one_off_failed', 'prorata', 'one_off_amount',
     *                'prorata_from', 'one_off_waived' ).
     */
    public static function create_registration_subscription( $mandate_id, $amount_pence, $name, $desired_start = '' ) {
        $out = array(
            'subscription_id' => '',
            'late'            => false,
            'sub_start'       => '',
            'one_off_date'    => '',
            'one_off_id'      => '',
            'one_off_failed'  => false,
            'prorata'         => false,
            'one_off_amount'  => 0.0,
            'prorata_from'    => '',
            'one_off_waived'  => false,
        );

        if ( ! $desired_start ) {
            return self::create_midseason_subscription( $mandate_id, $amount_pence, $name, $out );
        }

        $start = $desired_start;

        if ( $desired_start ) {
            $mandate = self::get_mandate( $mandate_id );
            $npcd    = is_array( $mandate ) ? ( $mandate['next_possible_charge_date'] ?? '' ) : '';

            if ( $npcd && strtotime( $desired_start ) < strtotime( $npcd ) ) {
                // Late — roll the subscription forward to the next same-day-of-month date
                // GoCardless can charge, and take a one-off for the missed month now.
                $rolled = strtotime( $desired_start );
                $guard  = 0;
                while ( $rolled < strtotime( $npcd ) && $guard < 24 ) {
                    $rolled = strtotime( '+1 month', $rolled );
                    $guard++;
                }
                $start       = date( 'Y-m-d', $rolled );
                $out['late'] = true;

                $oneoff = self::create_payment( $mandate_id, $amount_pence, $name . ' — first month', $npcd );
                if ( is_array( $oneoff ) && ! empty( $oneoff['id'] ) ) {
                    $out['one_off_date'] = $npcd;
                    $out['one_off_id']   = $oneoff['id'];
                } else {
                    $out['one_off_failed'] = true;
                }
            }
        }

        $sub = self::create_subscription( $mandate_id, $amount_pence, $name, $start );
        $out['subscription_id'] = ( is_array( $sub ) && ! empty( $sub['id'] ) ) ? $sub['id'] : '';
        $out['sub_start']       = $start;
        $out['one_off_amount']  = $amount_pence / 100;

        return $out;
    }

    /**
     * Mid-season sign-up: monthly DD from the first chargeable 1st, plus a one-off
     * pro-rata payment from today up to that 1st, collected on the earliest possible
     * date. Part-month amounts under £1 are not worth a Direct Debit and are waived.
     */
    private static function create_midseason_subscription( $mandate_id, $amount_pence, $name, $out ) {
        $npcd   = self::next_possible_charge_date( $mandate_id );
        $signup = current_time( 'Y-m-d' );
        // Without a charge date from GoCardless, assume the usual Bacs lead time.
        $start  = self::first_on_or_after( $npcd ?: date( 'Y-m-d', strtotime( $signup . ' +7 days' ) ) );

        $sub = self::create_subscription( $mandate_id, $amount_pence, $name, $start );
        $out['subscription_id'] = ( is_array( $sub ) && ! empty( $sub['id'] ) ) ? $sub['id'] : '';
        $out['sub_start']       = $start;
        $out['late']            = true;
        $out['prorata']         = true;
        $out['prorata_from']    = $signup;

        $pence = self::prorata_pence( $amount_pence, $signup, $start );
        $out['one_off_amount'] = $pence / 100;
        if ( $pence < 100 ) {
            $out['one_off_waived'] = true;
            return $out;
        }

        $label  = $name . ' — to ' . date_i18n( 'j M', strtotime( $start . ' -1 day' ) );
        $oneoff = self::create_payment( $mandate_id, $pence, mb_substr( $label, 0, 100 ), $npcd );
        if ( is_array( $oneoff ) && ! empty( $oneoff['id'] ) ) {
            $out['one_off_date'] = $oneoff['charge_date'] ?? $npcd;
            $out['one_off_id']   = $oneoff['id'];
        } else {
            $out['one_off_failed'] = true;
        }
        error_log( "[WAS] Mid-season sign-up on mandate {$mandate_id}: DD from {$start}, pro-rata " . ( $pence / 100 ) . ( $out['one_off_failed'] ? ' (one-off FAILED)' : " on {$out['one_off_date']}" ) );
        return $out;
    }

    /**
     * Set a player's Direct Debit to a given monthly amount (in pence).
     * GC subscription amounts are immutable, so we create the NEW subscription first
     * (so a failure never leaves the child unsubscribed), then cancel the old one,
     * starting the new one on the 1st the old one's next payment falls in, so it takes
     * over that payment with no double charge (see aligned_start_date()).
     * £0 / negative = cancel only. Returns an array; the CALLER persists gc_subscription_id.
     */
    public static function set_player_subscription( $player, $amount_pence, $notify = true ) {
        $amount_pence = (int) $amount_pence;
        $out = array( 'ok' => true, 'sub_id' => $player->gc_subscription_id, 'cancel_ok' => true, 'start_date' => '', 'error' => '', 'action' => 'none', 'notified' => false );

        if ( $amount_pence <= 0 ) {
            if ( $player->gc_subscription_id ) {
                $out['cancel_ok'] = (bool) self::cancel_subscription( $player->gc_subscription_id );
            }
            $out['sub_id'] = '';
            $out['action'] = 'cancelled';
            return $out;
        }

        $old = $player->gc_subscription_id ? self::get_subscription( $player->gc_subscription_id ) : null;
        $start_date = self::aligned_start_date( $player->gc_mandate_id, $player->gc_subscription_id, was_payment_day( $player ) );
        $out['start_date'] = $start_date;

        $name = get_bloginfo( 'name' ) . ' — ' . trim( $player->first_name . ' ' . $player->last_name );
        $sub  = self::create_subscription( $player->gc_mandate_id, $amount_pence, $name, $start_date, was_payment_day( $player ) );
        if ( ! $sub || empty( $sub['id'] ) ) {
            $err = get_transient( 'was_gc_last_error' );
            $out['ok']    = false;
            $out['error'] = ( is_array( $err ) && ! empty( $err['message'] ) ) ? $err['message'] : 'subscription create failed';
            return $out; // old subscription left intact
        }

        if ( $player->gc_subscription_id ) {
            $out['cancel_ok'] = (bool) self::cancel_subscription( $player->gc_subscription_id );
        }
        $out['sub_id'] = $sub['id'];
        $out['action'] = 'replaced';

        // A live subscription was replaced: GoCardless tells the parent the old one "has
        // been cancelled", so explain it's only an update (unless the admin opted out).
        if ( $notify && is_array( $old ) && ( $old['status'] ?? '' ) === 'active' && $out['cancel_ok'] ) {
            $out['notified'] = self::notify_dd_updated( $player, $old, $sub );
        }
        return $out;
    }

    /** Send WAS_Notifications::dd_updated() for a replaced subscription. */
    public static function notify_dd_updated( $player, $old_sub, $new_sub, $bridge = null ) {
        $guardian = WAS_Database::get_guardian( $player->primary_guardian_id );
        if ( ! $guardian ) return false;
        return (bool) WAS_Notifications::dd_updated( $player, $guardian, array(
            'old_pence'    => (int) ( $old_sub['amount'] ?? 0 ),
            'new_pence'    => (int) ( $new_sub['amount'] ?? 0 ),
            'day'          => self::subscription_day( $new_sub ),
            'first_charge' => $new_sub['upcoming_payments'][0]['charge_date'] ?? ( $new_sub['start_date'] ?? '' ),
            'bridge'       => $bridge,
        ) );
    }

    // ── Payments ───────────────────────────────────────────────────────────────

    /**
     * Normalise a GoCardless payment *status* (from the payments API) to our
     * internal vocabulary, mirroring the webhook handler. Critically a settled
     * "paid_out" payment is recorded as "confirmed" so it counts as paid in the
     * portal/admin reports, which all key off status = 'confirmed'. Without this
     * the nightly sync overwrites the webhook's "confirmed" with raw "paid_out".
     */
    private static function normalize_payment_status( $gc_status ) {
        switch ( $gc_status ) {
            case 'confirmed':
            case 'paid_out':
                return 'confirmed';
            case 'failed':
            case 'charged_back':
            case 'customer_approval_denied':
                return 'failed';
            case 'cancelled':
                return 'cancelled';
            case 'pending_customer_approval':
            case 'pending_submission':
                return 'pending_submission';
            case 'submitted':
                return 'submitted';
            default:
                return $gc_status;
        }
    }

    /**
     * Pull all payments for a mandate from GC and sync to our payments table.
     * Call this from the treasurer's "Sync" button.
     */
    public static function sync_player_payments( $player ) {
        if ( empty( $player->gc_mandate_id ) ) {
            return;
        }

        // Fetch up to 200 payments — more than enough for a single player
        $result = self::request( 'GET', '/payments?mandate=' . urlencode( $player->gc_mandate_id ) . '&limit=200' );

        if ( empty( $result['payments'] ) ) {
            return;
        }

        $team = WAS_Database::get_team( $player->team_id );

        // Statuses we already hold, so we can spot payments that changed since the
        // last sync and run the same failure / confirmation handling the webhook
        // would (a backstop for webhooks that never arrived).
        global $wpdb;
        $known   = array();
        $handled = array();
        foreach ( $wpdb->get_results( $wpdb->prepare(
            "SELECT gc_payment_id, status, failure_handled_at FROM {$wpdb->prefix}was_payments WHERE player_id = %d", $player->id
        ) ) as $k ) {
            $known[ $k->gc_payment_id ]   = $k->status;
            $handled[ $k->gc_payment_id ] = ! empty( $k->failure_handled_at );
        }

        $transitions = array();
        $failures    = array();
        foreach ( $result['payments'] as $p ) {
            $status = self::normalize_payment_status( $p['status'] );
            WAS_Database::upsert_payment(
                $p['id'],
                $player->id,
                $player->primary_guardian_id,
                $player->gc_mandate_id,
                $p['amount'] / 100,
                $p['charge_date'] ?? null,
                $status,
                $player->season,
                $p['description'] ?? ( $team ? $team->name . ' subscription' : '' )
            );
            $before = $known[ $p['id'] ] ?? null;
            if ( $status === 'failed' && empty( $handled[ $p['id'] ] ) ) {
                $failures[] = $p;
            } elseif ( $status === 'confirmed' && $before !== null && $before !== 'confirmed' ) {
                $transitions[] = $p['id'];
            }
        }

        // Failures not yet handled. This is only a BACKSTOP for a webhook that never
        // arrived: GoCardless often shows a payment as failed before it has finished
        // processing it (and before it publishes the "failed" event / webhook). Acting
        // then caused duplicate emails and refused retries (8 Oct 2026), so wait until
        // the event exists. WAS_Arrears::on_payment_failed() claims each failure once,
        // so a webhook racing with this sync can't double-handle it either.
        $event_action = array( 'failed' => 'failed', 'charged_back' => 'chargeback_raised', 'customer_approval_denied' => 'customer_approval_denied' );
        foreach ( $failures as $p ) {
            $action = $event_action[ $p['status'] ] ?? 'failed';
            $event  = self::payment_event( $p['id'], $action === 'chargeback_raised' ? 'charged_back' : $action );
            if ( ! $event && $action === 'failed' ) {
                error_log( "[WAS] Sync: payment {$p['id']} shows failed but GoCardless hasn't published the failure yet — leaving it for the webhook / next sync." );
                continue;
            }
            WAS_Arrears::on_payment_failed( $p['id'], (string) ( $event['details']['cause'] ?? '' ), $action, 'sync' );
        }

        foreach ( $transitions as $id ) {
            WAS_Arrears::on_payment_confirmed( $id );
        }
    }

    /** The most recent event of a given action for a payment (array), or null if none yet. */
    public static function payment_event( $gc_payment_id, $action ) {
        $result = self::request( 'GET', '/events?payment=' . urlencode( $gc_payment_id ) . '&action=' . urlencode( $action ) . '&limit=1' );
        return $result['events'][0] ?? null;
    }

    /** The cause recorded on the most recent "failed" event for a payment ('' if unknown). */
    public static function payment_failure_cause( $gc_payment_id ) {
        $result = self::request( 'GET', '/events?payment=' . urlencode( $gc_payment_id ) . '&action=failed&limit=1' );
        return (string) ( $result['events'][0]['details']['cause'] ?? '' );
    }

    // ── Webhook handler ────────────────────────────────────────────────────────

    /**
     * REST callback for /wp-json/was/v1/webhook/gocardless
     * Verifies HMAC signature, logs each event, and dispatches to process_event().
     */
    public static function handle_webhook( WP_REST_Request $request ) {
        global $wpdb;

        $body      = $request->get_body();
        $signature = $request->get_header( 'Webhook-Signature' );
        $secret    = self::webhook_secret();

        // Every webhook must be signed. Without a secret anyone could post fake payment and
        // mandate events, so refuse them until one is set in ASTRO → Settings.
        if ( ! $secret ) {
            error_log( '[WAS] GoCardless webhook rejected: no webhook secret is configured for the ' . ( self::is_sandbox() ? 'sandbox' : 'live' ) . ' environment.' );
            return new WP_REST_Response( array( 'error' => 'Webhook secret not configured' ), 401 );
        }
        $expected = hash_hmac( 'sha256', $body, $secret );
        if ( ! hash_equals( $expected, (string) $signature ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 401 );
        }

        $payload = json_decode( $body, true );

        if ( empty( $payload['events'] ) ) {
            return new WP_REST_Response( array( 'error' => 'No events' ), 400 );
        }

        foreach ( $payload['events'] as $event ) {
            // Idempotency — skip if already processed
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}was_gc_events WHERE gc_event_id = %s",
                $event['id']
            ) );
            if ( $exists ) {
                continue;
            }

            // Log it
            $wpdb->insert(
                "{$wpdb->prefix}was_gc_events",
                array(
                    'gc_event_id'   => $event['id'],
                    'event_action'  => $event['action'],
                    'resource_type' => $event['resource_type'],
                    'resource_id'   => $event['links'][ $event['resource_type'] ] ?? '',
                    'payload'       => wp_json_encode( $event ),
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );

            self::process_event( $event );
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Update our database based on a single GC webhook event.
     */
    private static function process_event( $event ) {
        global $wpdb;

        $resource_type = $event['resource_type'];
        $action        = $event['action'];
        $links         = $event['links'] ?? array();

        switch ( $resource_type ) {

            case 'payments':
                $gc_payment_id = $links['payment'] ?? '';
                if ( ! $gc_payment_id ) break;

                $status_map = array(
                    'created'                  => 'pending_submission',
                    'resubmission_requested'   => 'pending_submission', // our retry was accepted
                    'submitted'                => 'submitted',
                    'confirmed'                => 'confirmed',
                    'paid_out'                 => 'confirmed',
                    'failed'                   => 'failed',
                    'cancelled'                => 'cancelled',
                    'customer_approval_denied' => 'failed',
                    'chargeback_raised'        => 'failed',
                    'refunded'                 => 'refunded',
                );

                $new_status = $status_map[ $action ] ?? null;
                if ( ! $new_status ) break;

                $prev_status = $wpdb->get_var( $wpdb->prepare(
                    "SELECT status FROM {$wpdb->prefix}was_payments WHERE gc_payment_id = %s", $gc_payment_id
                ) );

                WAS_Database::update_payment_status( $gc_payment_id, $new_status );

                // Failure: retry once if we can, otherwise put the player on hold and
                // tell the parent exactly what to do (see WAS_Arrears).
                if ( in_array( $action, array( 'failed', 'chargeback_raised', 'customer_approval_denied' ), true ) ) {
                    $cause = (string) ( $event['details']['cause'] ?? '' );
                    WAS_Arrears::on_payment_failed( $gc_payment_id, $cause, $action, 'webhook' );
                }

                // Confirmation of a retried payment or a catch-up may clear a hold.
                if ( in_array( $action, array( 'confirmed', 'paid_out' ), true ) && $prev_status !== 'confirmed' ) {
                    WAS_Arrears::on_payment_confirmed( $gc_payment_id );
                }
                break;

            case 'mandates':
                $mandate_id = $links['mandate'] ?? '';
                if ( ! $mandate_id ) break;

                if ( in_array( $action, array( 'cancelled', 'expired', 'failed' ), true ) ) {
                    // Find players on this mandate that are still active, so we only
                    // deactivate + alert on a genuine transition (not on repeat events,
                    // and without disturbing already-withdrawn players). Train Only
                    // children aren't paying, so a cancelled mandate doesn't affect them.
                    $affected = $wpdb->get_results( $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}was_players
                         WHERE gc_mandate_id = %s
                           AND participation_type = 'signed'
                           AND registration_status NOT IN ( 'inactive', 'withdrawn' )",
                        $mandate_id
                    ) );

                    if ( $affected ) {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE {$wpdb->prefix}was_players SET registration_status = 'inactive'
                             WHERE gc_mandate_id = %s
                               AND participation_type = 'signed'
                               AND registration_status NOT IN ( 'inactive', 'withdrawn' )",
                            $mandate_id
                        ) );

                        foreach ( $affected as $pl ) {
                            $guardian = WAS_Database::get_guardian( $pl->primary_guardian_id );
                            if ( $guardian ) {
                                WAS_Notifications::mandate_cancelled( $pl, $guardian );
                            }
                        }
                    }
                }
                break;

            case 'subscriptions':
                // Future: handle subscription paused / completed etc.
                break;
        }
    }
    /**
     * Check a single player's mandate status directly from GC API.
     * Called by daily cron to catch cancellations not delivered via webhook.
     */
    public static function sync_mandate_status( $player ) {
        global $wpdb;
        if ( empty( $player->gc_mandate_id ) ) return;

        $result = self::request( 'GET', '/mandates/' . urlencode( $player->gc_mandate_id ) );
        if ( empty( $result['mandates'] ) ) return;

        $status = $result['mandates']['status'] ?? '';

        if ( in_array( $status, array( 'cancelled', 'expired', 'failed' ), true ) ) {
            $current = $wpdb->get_var( $wpdb->prepare(
                "SELECT registration_status FROM {$wpdb->prefix}was_players WHERE id = %d",
                $player->id
            ) );
            // Only act on a fresh transition — skip players already inactive/withdrawn so
            // the cron backstop never re-sends an alert the webhook already delivered.
            if ( ! in_array( $current, array( 'inactive', 'withdrawn' ), true ) ) {
                $wpdb->update(
                    "{$wpdb->prefix}was_players",
                    array( 'registration_status' => 'inactive' ),
                    array( 'id' => $player->id ),
                    array( '%s' ),
                    array( '%d' )
                );
                error_log( "[WAS] Cron: Player {$player->id} set inactive — mandate {$player->gc_mandate_id} is {$status}" );

                $guardian = WAS_Database::get_guardian( $player->primary_guardian_id );
                if ( $guardian ) {
                    WAS_Notifications::mandate_cancelled( $player, $guardian );
                }
            }
        }
    }

}