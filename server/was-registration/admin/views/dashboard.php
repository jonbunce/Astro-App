<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap was-admin-wrap">
    <h1>ASTRO Dashboard &mdash; <?php echo esc_html( was_season() ); ?></h1>

    <div class="was-stats-grid">
        <div class="was-stat-card">
            <span class="was-stat-num"><?php echo $stats['active']; ?></span>
            <span class="was-stat-label">Active Players</span>
        </div>
        <div class="was-stat-card">
            <span class="was-stat-num"><?php echo $stats['signed']; ?></span>
            <span class="was-stat-label">Signed (Direct Debit)</span>
        </div>
        <div class="was-stat-card">
            <span class="was-stat-num"><?php echo $stats['train_only']; ?></span>
            <span class="was-stat-label">Train Only</span>
        </div>
        <div class="was-stat-card <?php echo $stats['pending'] > 0 ? 'was-stat-card--warn' : ''; ?>">
            <span class="was-stat-num"><?php echo $stats['pending']; ?></span>
            <span class="was-stat-label">Pending (incomplete)</span>
        </div>
        <div class="was-stat-card">
            <span class="was-stat-num"><?php echo $stats['payments_conf']; ?></span>
            <span class="was-stat-label">Confirmed Payments</span>
        </div>
        <div class="was-stat-card <?php echo $stats['payments_failed'] > 0 ? 'was-stat-card--danger' : ''; ?>">
            <span class="was-stat-num"><?php echo $stats['payments_failed']; ?></span>
            <span class="was-stat-label">Failed Payments</span>
        </div>
    </div>

    <h2>Recent Registrations</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Player</th>
                <th>Team</th>
                <th>Type</th>
                <th>Registered</th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $recent ) ) : ?>
            <tr><td colspan="4">No registrations yet this season.</td></tr>
        <?php else : ?>
            <?php foreach ( $recent as $r ) : ?>
            <tr>
                <td><?php echo esc_html( $r->first_name ); ?></td>
                <td><?php echo esc_html( $r->team_name ); ?></td>
                <td><?php echo $r->participation_type === 'signed' ? 'Signed' : 'Train Only'; ?></td>
                <td><?php echo esc_html( date( 'd M Y', strtotime( $r->created_at ) ) ); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top:1.5em; padding:1rem; background:#f7f8fa; border:1px solid #dde1e7; border-radius:8px;">
        <strong>Automatic GoCardless sync</strong><br>
        <small>Last sync: <strong><?php echo esc_html( get_option('was_last_gc_sync','Never') ); ?></strong></small>
        &nbsp;&nbsp;
        <small>Next scheduled: <strong><?php
            $next = wp_next_scheduled('was_daily_gc_sync');
            echo $next ? esc_html( date('d M Y H:i', $next) ) : 'Not scheduled — deactivate and reactivate plugin';
        ?></strong></small>
        &nbsp;&nbsp;
        <button type="button" id="was-manual-sync-btn" class="button button-small">Run sync now</button>
        <span id="was-sync-msg" style="margin-left:8px; color:#2e7d32;"></span>
    </div>

    <p style="margin-top:1.5em;">
        <strong>Webhook URL for GoCardless:</strong><br>
        <code><?php echo esc_url( rest_url( 'was/v1/webhook/gocardless' ) ); ?></code><br>
        <small>Add this to your GoCardless developer settings to keep payment data up to date automatically.</small>
    </p>
</div>
