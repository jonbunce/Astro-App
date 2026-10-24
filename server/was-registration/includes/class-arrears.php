<?php
defined( 'ABSPATH' ) || exit;

/**
 * Arrears handling (v2.21.0).
 *
 * Lifecycle of a failed monthly collection:
 *
 *   1. GoCardless reports the payment as failed (webhook, or the nightly sync).
 *   2. If the failure is retryable (insufficient funds etc.) and this payment has
 *      not been retried before, we ask GoCardless to retry it once. The same
 *      payment is re-presented on the next possible charge date (typically 5–7
 *      working days later), so the missed month is recovered without touching the
 *      monthly subscription. Parent + treasurer are emailed explaining exactly that.
 *   3. If the retry also fails, or the failure cannot be retried (mandate cancelled,
 *      account closed, chargeback…), the player is put on a PAYMENT HOLD. The parent
 *      is told the amount outstanding and how to clear it: the one-click "Pay now"
 *      button in the portal, or by contacting the finance email.
 *   4. "Pay now" (parent) / "Collect now" (treasurer) creates a ONE-OFF GoCardless
 *      payment against the existing mandate for the whole outstanding amount and
 *      marks the failed rows as covered by it (recovered_by).
 *   5. When that catch-up — or a retried payment — confirms, and nothing else is
 *      outstanding, the hold is lifted automatically and everyone is told.
 *
 * Arrears for a player = failed subscription rows that are not yet covered by a
 * catch-up (recovered_by = '').
 */
class WAS_Arrears {

    const KIND_SUB     = 'subscription';
    const KIND_CATCHUP = 'catchup';

    /** Failure causes that must not be retried automatically. */
    private static $non_retryable = array(
        'mandate_cancelled', 'mandate_expired', 'mandate_not_found',
        'bank_account_closed', 'bank_account_transferred',
        'direct_debit_not_enabled', 'authorisation_disputed', 'invalid_bank_details',
        'customer_approval_denied', 'chargeback_raised', 'charged_back',
        'payment_cancelled_by_customer', 'refund_requested',
    );

    public static function init() {
        add_action( 'wp_ajax_was_pay_arrears',           array( __CLASS__, 'ajax_pay_arrears' ) );
        add_action( 'wp_ajax_was_treas_collect_arrears', array( __CLASS__, 'ajax_treas_collect' ) );
        add_action( 'wp_ajax_was_treas_clear_hold',      array( __CLASS__, 'ajax_treas_clear_hold' ) );
    }

    // ── Plain-English failure reasons ──────────────────────────────────────────

    public static function reason_text( $cause ) {
        $map = array(
            'insufficient_funds'            => 'there were insufficient funds in the account',
            'refer_to_payer'                => 'the bank declined the collection ("refer to payer", which usually means insufficient funds)',
            'bank_account_closed'           => 'the bank account has been closed',
            'bank_account_transferred'      => 'the bank account has moved to another bank',
            'mandate_cancelled'             => 'the Direct Debit instruction has been cancelled',
            'mandate_expired'               => 'the Direct Debit instruction has expired',
            'direct_debit_not_enabled'      => 'the account does not allow Direct Debits',
            'authorisation_disputed'        => 'the account holder disputed the Direct Debit authorisation',
            'invalid_bank_details'          => 'the bank details on the Direct Debit are not valid',
            'customer_approval_denied'      => 'the account holder declined to approve the Direct Debit',
            'chargeback_raised'             => 'the payment was reclaimed under the Direct Debit Guarantee',
            'charged_back'                  => 'the payment was reclaimed under the Direct Debit Guarantee',
            'payment_cancelled_by_customer' => 'the payment was cancelled by the account holder',
        );
        return $map[ $cause ] ?? 'the bank did not accept the collection';
    }

    public static function is_retryable_cause( $cause ) {
        return ! in_array( (string) $cause, self::$non_retryable, true );
    }

    // ── Queries ────────────────────────────────────────────────────────────────

    /** Failed subscription rows not yet covered by a catch-up. */
    public static function outstanding_rows( $player_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_payments
             WHERE player_id = %d AND status = 'failed' AND kind = %s AND recovered_by = ''
             ORDER BY charge_date ASC, created_at ASC",
            $player_id, self::KIND_SUB
        ) );
    }

    public static function arrears_total( $player_id ) {
        $total = 0.0;
        foreach ( self::outstanding_rows( $player_id ) as $r ) $total += (float) $r->amount;
        return round( $total, 2 );
    }

    /** A catch-up payment that has been created but not yet confirmed/failed. */
    public static function inflight_catchup( $player_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_payments
             WHERE player_id = %d AND kind = %s AND status IN ('pending_submission','submitted')
             ORDER BY created_at DESC LIMIT 1",
            $player_id, self::KIND_CATCHUP
        ) );
    }

    /** Everything a template needs for one player's arrears state. */
    public static function summary( $player ) {
        $rows  = self::outstanding_rows( $player->id );
        $total = 0.0;
        foreach ( $rows as $r ) $total += (float) $r->amount;
        return array(
            'rows'       => $rows,
            'total'      => round( $total, 2 ),
            'inflight'   => self::inflight_catchup( $player->id ),
            'hold_since' => $player->payment_hold_since ?? null,
        );
    }

    /** One row per player in arrears this season — treasurer view. */
    public static function season_arrears( $season ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.id, p.first_name, p.last_name, p.team_id, p.registration_status,
                    p.gc_mandate_id, p.payment_hold_since, p.payment_hold_reason,
                    t.name AS team_name,
                    g.first_name AS g_first, g.last_name AS g_last, g.email AS g_email,
                    SUM(pay.amount) AS arrears,
                    COUNT(*) AS failed_count,
                    GROUP_CONCAT(pay.charge_date ORDER BY pay.charge_date SEPARATOR ',') AS failed_dates,
                    MAX(pay.retry_count) AS retried
             FROM {$wpdb->prefix}was_payments pay
             JOIN {$wpdb->prefix}was_players p        ON pay.player_id = p.id
             LEFT JOIN {$wpdb->prefix}was_teams t     ON p.team_id = t.id
             LEFT JOIN {$wpdb->prefix}was_guardians g ON p.primary_guardian_id = g.id
             WHERE pay.season = %s AND pay.status = 'failed' AND pay.kind = %s AND pay.recovered_by = ''
             GROUP BY p.id
             ORDER BY arrears DESC, t.name, p.first_name",
            $season, self::KIND_SUB
        ) );
        foreach ( $rows as $r ) {
            $r->inflight = self::inflight_catchup( $r->id );
        }
        return $rows;
    }

    // ── Event handlers (called from the webhook + nightly sync) ────────────────

    /**
     * A payment has just moved to failed.
     * $action is the GC event action ('failed', 'chargeback_raised', 'customer_approval_denied').
     */
    public static function on_payment_failed( $gc_payment_id, $cause = '', $action = 'failed', $source = 'webhook' ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_payments WHERE gc_payment_id = %s", $gc_payment_id
        ) );
        if ( ! $row ) return;

        $player   = WAS_Database::get_player( $row->player_id );
        $guardian = $player ? WAS_Database::get_guardian( $player->primary_guardian_id ) : null;
        if ( ! $player || ! $guardian ) return;

        // Handle each failure ONCE. The webhook and the nightly sync can both report the
        // same failure (on 8 Oct 2026 they did, 18 minutes apart, and every family and
        // treasurer got the emails twice). Claim the row atomically; whoever loses skips.
        // The claim is released when a retry is accepted, so a failed retry is handled too.
        $claimed = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}was_payments SET failure_handled_at = %s WHERE id = %d AND failure_handled_at IS NULL",
            current_time( 'mysql' ), $row->id
        ) );
        if ( ! $claimed ) {
            error_log( "[WAS] Arrears: failure of {$gc_payment_id} already handled — skipping duplicate ({$source})" );
            return;
        }

        $cause = (string) $cause;
        if ( $action === 'chargeback_raised' && ! $cause )         $cause = 'chargeback_raised';
        if ( $action === 'customer_approval_denied' && ! $cause ) $cause = 'customer_approval_denied';
        if ( $cause && $cause !== $row->failure_reason ) {
            $wpdb->update( "{$wpdb->prefix}was_payments", array( 'failure_reason' => $cause ), array( 'id' => $row->id ), array( '%s' ), array( '%d' ) );
            $row->failure_reason = $cause;
        }

        // A catch-up payment bounced: the rows it covered are outstanding again.
        if ( $row->kind === self::KIND_CATCHUP ) {
            $wpdb->update( "{$wpdb->prefix}was_payments", array( 'recovered_by' => '' ), array( 'recovered_by' => $row->gc_payment_id ), array( '%s' ), array( '%s' ) );
            self::escalate( $player, $guardian, 'catchup_failed', $row );
            return;
        }

        // Rows already handled outside the system (e.g. a manual arrangement) are left alone.
        if ( $row->recovered_by !== '' ) return;

        $can_retry = ( $action === 'failed' )
            && self::is_retryable_cause( $cause )
            && (int) $row->retry_count === 0
            && $player->registration_status === 'active'
            && ! empty( $player->gc_mandate_id );

        if ( $can_retry ) {
            $retry = WAS_GoCardless::retry_payment( $row->gc_payment_id );
            if ( is_array( $retry ) && ! empty( $retry['id'] ) ) {
                // Re-presented: release the claim so a failure of the retry is handled too.
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}was_payments SET retry_count = 1, retried_at = %s, status = 'pending_submission', failure_handled_at = NULL WHERE id = %d",
                    current_time( 'mysql' ), $row->id
                ) );
                $retry_date = $retry['charge_date'] ?? '';
                error_log( "[WAS] Arrears: retried payment {$row->gc_payment_id} for player {$player->id} (cause: {$cause}, via {$source}); charge {$retry_date}" );
                WAS_Notifications::payment_failed_retrying( $player, $guardian, $row, self::reason_text( $cause ), $retry_date );
                return;
            }
            $err = WAS_GoCardless::last_error_message();
            // GoCardless is still deciding whether it will retry this payment itself; a
            // retry can't be requested yet. Not a reason to put the family on hold: release
            // the claim and let the "failed" webhook (sent once GoCardless has decided)
            // handle it. This is what wrongly put Noah Hodgetts on hold on 8 Oct 2026.
            if ( stripos( $err, 'retried automatically' ) !== false ) {
                $wpdb->update( "{$wpdb->prefix}was_payments", array( 'failure_handled_at' => null ), array( 'id' => $row->id ) );
                error_log( "[WAS] Arrears: GoCardless is still processing the failure of {$row->gc_payment_id} ({$err}) — waiting for its failed webhook ({$source})" );
                return;
            }
            error_log( "[WAS] Arrears: retry of {$row->gc_payment_id} was refused by GoCardless — escalating. " . $err );
        }

        $context = ( (int) $row->retry_count > 0 ) ? 'retry_failed' : 'not_retryable';
        self::escalate( $player, $guardian, $context, $row );
    }

    /** A payment has just moved to confirmed (or paid out). */
    public static function on_payment_confirmed( $gc_payment_id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_payments WHERE gc_payment_id = %s", $gc_payment_id
        ) );
        if ( ! $row ) return;
        $player = WAS_Database::get_player( $row->player_id );
        if ( ! $player || empty( $player->payment_hold_since ) ) return;

        if ( self::arrears_total( $player->id ) <= 0 ) {
            self::clear_hold( $player, 'paid', '' );
        }
    }

    // ── Hold / escalation ──────────────────────────────────────────────────────

    private static function escalate( $player, $guardian, $context, $row ) {
        global $wpdb;
        if ( empty( $player->payment_hold_since ) ) {
            $wpdb->update(
                "{$wpdb->prefix}was_players",
                array( 'payment_hold_since' => current_time( 'mysql' ), 'payment_hold_reason' => $context ),
                array( 'id' => $player->id ),
                array( '%s', '%s' ),
                array( '%d' )
            );
            $player->payment_hold_since  = current_time( 'mysql' );
            $player->payment_hold_reason = $context;
        }
        error_log( "[WAS] Arrears: player {$player->id} on payment hold ({$context}), payment {$row->gc_payment_id}" );
        WAS_Notifications::payment_arrears( $player, $guardian, self::summary( $player ), $context, $row );
    }

    /**
     * Lift the hold. $how = 'paid' (collected via GoCardless) or 'manual' (treasurer
     * cleared it — cash, bank transfer, agreed write-off…).
     */
    public static function clear_hold( $player, $how = 'paid', $note = '' ) {
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}was_players",
            array( 'payment_hold_since' => null, 'payment_hold_reason' => '' ),
            array( 'id' => $player->id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
        $player->payment_hold_since  = null;
        $player->payment_hold_reason = '';
        $guardian = WAS_Database::get_guardian( $player->primary_guardian_id );
        if ( $guardian ) {
            WAS_Notifications::arrears_cleared( $player, $guardian, $how, $note );
        }
    }

    // ── Catch-up collection ────────────────────────────────────────────────────

    /**
     * Create a one-off GoCardless payment for everything outstanding.
     * Returns array( 'ok' => bool, 'message' => string, 'amount' => float, 'charge_date' => string ).
     */
    public static function create_catchup( $player, $initiated_by = 'parent' ) {
        global $wpdb;

        $rows = self::outstanding_rows( $player->id );
        if ( empty( $rows ) ) {
            return array( 'ok' => false, 'message' => 'There is nothing outstanding for ' . $player->first_name . '.' );
        }
        if ( self::inflight_catchup( $player->id ) ) {
            return array( 'ok' => false, 'message' => 'A catch-up payment is already being collected for ' . $player->first_name . '. Please wait for it to clear.' );
        }
        if ( empty( $player->gc_mandate_id ) ) {
            return array( 'ok' => false, 'message' => 'There is no Direct Debit on file for ' . $player->first_name . '. Set one up first, then pay the outstanding amount.' );
        }

        $mandate = WAS_GoCardless::get_mandate( $player->gc_mandate_id );
        $mstatus = is_array( $mandate ) ? ( $mandate['status'] ?? '' ) : '';
        if ( ! in_array( $mstatus, array( 'active', 'pending_submission', 'submitted', 'pending_customer_approval' ), true ) ) {
            return array( 'ok' => false, 'message' => 'The Direct Debit for ' . $player->first_name . ' is ' . ( $mstatus ? str_replace( '_', ' ', $mstatus ) : 'not available' ) . ', so a payment cannot be taken. Please set up a new Direct Debit first.' );
        }

        $total = 0.0;
        foreach ( $rows as $r ) $total += (float) $r->amount;
        $total = round( $total, 2 );
        $pence = (int) round( $total * 100 );
        if ( $pence <= 0 ) {
            return array( 'ok' => false, 'message' => 'Nothing to collect.' );
        }

        $club = get_bloginfo( 'name' );
        $desc = mb_substr( $club . ' — catch-up: ' . trim( $player->first_name . ' ' . $player->last_name ), 0, 100 );

        $payment = WAS_GoCardless::create_payment( $player->gc_mandate_id, $pence, $desc );
        if ( ! is_array( $payment ) || empty( $payment['id'] ) ) {
            $err = WAS_GoCardless::last_error_message();
            return array( 'ok' => false, 'message' => 'GoCardless could not schedule the payment' . ( $err ? ': ' . $err : '.' ) . ' Please try again or contact the club.' );
        }

        $charge_date = $payment['charge_date'] ?? null;
        $wpdb->insert(
            "{$wpdb->prefix}was_payments",
            array(
                'player_id'     => $player->id,
                'guardian_id'   => $player->primary_guardian_id,
                'gc_payment_id' => $payment['id'],
                'gc_mandate_id' => $player->gc_mandate_id,
                'amount'        => $total,
                'currency'      => 'GBP',
                'charge_date'   => $charge_date,
                'status'        => 'pending_submission',
                'season'        => $player->season,
                'description'   => $desc,
                'kind'          => self::KIND_CATCHUP,
            ),
            array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        $ids = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
        $wpdb->query(
            "UPDATE {$wpdb->prefix}was_payments SET recovered_by = '" . esc_sql( $payment['id'] ) . "'
             WHERE id IN (" . implode( ',', $ids ) . ")"
        );

        error_log( "[WAS] Arrears: catch-up {$payment['id']} of £{$total} created for player {$player->id} by {$initiated_by}, charge {$charge_date}" );

        $guardian = WAS_Database::get_guardian( $player->primary_guardian_id );
        if ( $guardian ) {
            WAS_Notifications::catchup_scheduled( $player, $guardian, $total, $charge_date, $rows, $initiated_by );
        }

        return array( 'ok' => true, 'amount' => $total, 'charge_date' => $charge_date,
            'message' => '£' . number_format( $total, 2 ) . ' will be collected by Direct Debit' . ( $charge_date ? ' on ' . date_i18n( 'j F Y', strtotime( $charge_date ) ) : ' shortly' ) . '.' );
    }

    // ── AJAX ───────────────────────────────────────────────────────────────────

    /** Parent: "Pay £X now" on their child's card. */
    public static function ajax_pay_arrears() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Please log in first.' ) );
        }
        $player_id = absint( $_POST['player_id'] ?? 0 );
        $guardian  = WAS_Accounts::current_guardian();
        $player    = $player_id ? WAS_Database::get_player( $player_id ) : null;

        if ( ! $guardian || ! $player || (int) $player->primary_guardian_id !== (int) $guardian->id ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }
        $res = self::create_catchup( $player, 'parent' );
        if ( $res['ok'] ) {
            wp_send_json_success( array( 'message' => 'Thank you. ' . $res['message'] . ' We have emailed you the details, and ' . $player->first_name . "'s hold will be lifted automatically once it clears." ) );
        }
        wp_send_json_error( array( 'message' => $res['message'] ) );
    }

    private static function treasurer_gate() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! was_has_role( 'treasurer' ) && ! was_has_role( 'committee' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
        $player = WAS_Database::get_player( absint( $_POST['player_id'] ?? 0 ) );
        if ( ! $player ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }
        return $player;
    }

    /** Treasurer: "Collect now" — same one-off catch-up, initiated by the club. */
    public static function ajax_treas_collect() {
        $player = self::treasurer_gate();
        $res    = self::create_catchup( $player, 'treasurer' );
        if ( $res['ok'] ) {
            wp_send_json_success( array( 'message' => trim( $player->first_name . ' ' . $player->last_name ) . ': ' . $res['message'] . ' The parent has been emailed.' ) );
        }
        wp_send_json_error( array( 'message' => $res['message'] ) );
    }

    /** Treasurer: "Clear hold" — arrears settled outside GoCardless (or written off). */
    public static function ajax_treas_clear_hold() {
        global $wpdb;
        $player = self::treasurer_gate();
        $note   = sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) );

        $wpdb->update(
            "{$wpdb->prefix}was_payments",
            array( 'recovered_by' => 'manual' ),
            array( 'player_id' => $player->id, 'status' => 'failed', 'kind' => self::KIND_SUB, 'recovered_by' => '' ),
            array( '%s' ),
            array( '%d', '%s', '%s', '%s' )
        );
        self::clear_hold( $player, 'manual', $note );
        wp_send_json_success( array( 'message' => trim( $player->first_name . ' ' . $player->last_name ) . ' is no longer on hold and the outstanding amounts have been marked as settled. The parent has been emailed.' ) );
    }
}
