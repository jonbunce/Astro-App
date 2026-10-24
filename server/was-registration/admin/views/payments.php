<?php defined( 'ABSPATH' ) || exit;

$status_labels = array(
    'confirmed'          => '<span class="was-badge was-badge--green">Confirmed</span>',
    'submitted'          => '<span class="was-badge was-badge--blue">Submitted</span>',
    'pending_submission' => '<span class="was-badge was-badge--yellow">Pending</span>',
    'failed'             => '<span class="was-badge was-badge--red">Failed</span>',
    'cancelled'          => '<span class="was-badge was-badge--grey">Cancelled</span>',
    'refunded'           => '<span class="was-badge was-badge--grey">Refunded</span>',
    'paid_out'           => '<span class="was-badge was-badge--green">Paid Out</span>',
);
?>
<div class="wrap was-admin-wrap">
    <h1>Payments &mdash; <?php echo esc_html( was_season() ); ?></h1>

    <?php if ( $notice ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
    <?php endif; ?>

    <form method="post" style="display:inline-block; margin-bottom:1em;">
        <?php wp_nonce_field( 'was_sync_payments' ); ?>
        <button type="submit" name="sync_payments" value="1" class="button button-primary">
            ↻ Sync from GoCardless
        </button>
    </form>
    <p class="description" style="margin-bottom:1.5em;">This fetches the latest payment data from GoCardless and updates the records below. The webhook handles updates automatically, but use this if you think anything is out of date.</p>

    <?php /* ── OUTSTANDING PAYMENTS ─────────────────────────────────────────── */ ?>
    <h2>Players with no confirmed payment this season (<?php echo count( $no_pay ); ?>)</h2>

    <?php if ( empty( $no_pay ) ) : ?>
    <p><em>All signed players have at least one confirmed payment. ✓</em></p>
    <?php else : ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Player</th>
                <th>Team</th>
                <th>Parent / Guardian</th>
                <th>Email</th>
                <th>GC Mandate</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $no_pay as $p ) : ?>
        <tr>
            <td><strong><?php echo esc_html( trim( $p->first_name . ' ' . $p->last_name ) ); ?></strong></td>
            <td><?php echo esc_html( $p->team_name ); ?></td>
            <td><?php echo esc_html( trim( $p->g_first . ' ' . $p->g_last ) ); ?></td>
            <td><?php if ( $p->g_email ) : ?><a href="mailto:<?php echo esc_attr( $p->g_email ); ?>"><?php echo esc_html( $p->g_email ); ?></a><?php endif; ?></td>
            <td>
                <?php if ( $p->gc_mandate_id ) : ?>
                    <code><?php echo esc_html( $p->gc_mandate_id ); ?></code>
                <?php elseif ( $p->participation_type === 'signed' ) : ?>
                    <span class="was-badge was-badge--red">No mandate</span>
                <?php else : ?>
                    <span class="was-badge was-badge--grey">Train only</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ( $p->g_email && $p->participation_type === 'signed' ) : ?>
                <button class="button button-small was-send-reminder"
                        data-player-id="<?php echo esc_attr( $p->id ); ?>"
                        data-player-name="<?php echo esc_attr( $p->first_name ); ?>"
                        data-email="<?php echo esc_attr( $p->g_email ); ?>">
                    Send reminder
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php /* ── FULL PAYMENT LOG ──────────────────────────────────────────────── */ ?>
    <h2 style="margin-top:2em;">Full Payment Log</h2>

    <form method="get" class="was-filter-bar">
        <input type="hidden" name="page" value="was-payments">
        <select name="team">
            <option value="">All teams</option>
            <?php foreach ( $teams as $t ) : ?>
            <option value="<?php echo esc_attr( $t->id ); ?>" <?php selected( $team_id, $t->id ); ?>><?php echo esc_html( $t->name ); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <option value="confirmed"  <?php selected( $status_filter, 'confirmed' ); ?>>Confirmed</option>
            <option value="failed"     <?php selected( $status_filter, 'failed' ); ?>>Failed</option>
            <option value="submitted"  <?php selected( $status_filter, 'submitted' ); ?>>Submitted</option>
            <option value="cancelled"  <?php selected( $status_filter, 'cancelled' ); ?>>Cancelled</option>
        </select>
        <button type="submit" class="button">Filter</button>
    </form>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Player</th>
                <th>Team</th>
                <th>Parent</th>
                <th>Amount</th>
                <th>Charge date</th>
                <th>Confirmed</th>
                <th>Status</th>
                <th>GC Payment ID</th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $payments ) ) : ?>
            <tr><td colspan="8">No payment records found.</td></tr>
        <?php else : ?>
            <?php foreach ( $payments as $pay ) : ?>
            <tr>
                <td><strong><?php echo esc_html( trim( $pay->p_first . ' ' . $pay->p_last ) ); ?></strong></td>
                <td><?php echo esc_html( $pay->team_name ); ?></td>
                <td>
                    <?php echo esc_html( trim( $pay->g_first . ' ' . $pay->g_last ) ); ?>
                    <?php if ( $pay->g_email ) : ?><br><small><a href="mailto:<?php echo esc_attr( $pay->g_email ); ?>"><?php echo esc_html( $pay->g_email ); ?></a></small><?php endif; ?>
                </td>
                <td>£<?php echo number_format( $pay->amount, 2 ); ?></td>
                <td><?php echo $pay->charge_date ? esc_html( date( 'd M Y', strtotime( $pay->charge_date ) ) ) : '—'; ?></td>
                <td><?php echo $pay->payment_date ? esc_html( date( 'd M Y', strtotime( $pay->payment_date ) ) ) : '—'; ?></td>
                <td><?php echo $status_labels[ $pay->status ] ?? esc_html( $pay->status ); ?></td>
                <td><small><code><?php echo esc_html( $pay->gc_payment_id ); ?></code></small></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div id="was-reminder-result" class="was-alert" style="display:none; margin-top:1em;"></div>
</div>
