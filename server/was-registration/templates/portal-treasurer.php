<?php defined( 'ABSPATH' ) || exit; ?>
<div id="was-section-treasurer" class="was-portal-section">

    <div class="was-portal-section__header">
        <h3 class="was-portal-section__title">Payments &mdash; <?php echo esc_html( was_season() ); ?></h3>
        <button type="button" id="was-gc-sync-btn" class="was-btn was-btn--secondary was-btn--sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            Sync from GoCardless
        </button>
    </div>

    <div id="was-sync-result" class="was-alert" style="display:none; margin-bottom:1rem;"></div>

    <!-- Stats row -->
    <div class="was-treas-controls">
        <div class="was-treas-filter-row">
            <label class="was-label" for="was-treas-team-filter">Filter by team</label>
            <select id="was-treas-team-filter" class="was-select was-select--inline">
                <option value="">All teams</option>
                <?php foreach ( $teams as $t ) : ?>
                <option value="<?php echo esc_attr($t->id); ?>">
                    <?php echo esc_html($t->name . ($t->age_group ? ' (' . $t->age_group . ')' : '')); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="was-treas-clear-filter" class="was-btn was-btn--sm was-btn--outline" style="display:none;">Clear filter</button>
        </div>
    </div>

    <div class="was-treas-stats">
        <div class="was-treas-stat" data-filter="all" title="Show everything">
            <span class="was-treas-stat__num"><?php echo $stats['total_active']; ?></span>
            <span class="was-treas-stat__label">Signed players</span>
        </div>
        <div class="was-treas-stat" data-filter="intransit" title="Payments collected and clearing through Bacs — they confirm a few working days after the charge date">
            <span class="was-treas-stat__num"><?php echo $stats['in_transit']; ?></span>
            <span class="was-treas-stat__label">In transit</span>
        </div>
        <div class="was-treas-stat" data-filter="confirmed" title="Show confirmed payments">
            <span class="was-treas-stat__num"><?php echo $stats['confirmed']; ?></span>
            <span class="was-treas-stat__label">Confirmed payments</span>
        </div>
        <div class="was-treas-stat <?php echo $stats['overdue_count'] > 0 ? 'was-treas-stat--warn' : ''; ?>" data-filter="overdue" title="Signed players with no payment confirmed OR in transit (exempt players excluded)">
            <span class="was-treas-stat__num"><?php echo $stats['overdue_count']; ?></span>
            <span class="was-treas-stat__label">Awaiting first payment</span>
        </div>
        <div class="was-treas-stat <?php echo $stats['failed'] > 0 ? 'was-treas-stat--danger' : ''; ?>" data-filter="failed" title="Show failed payments">
            <span class="was-treas-stat__num"><?php echo $stats['failed']; ?></span>
            <span class="was-treas-stat__label">Failed payments</span>
        </div>
        <div class="was-treas-stat <?php echo $stats['arrears'] > 0 ? 'was-treas-stat--danger' : ''; ?>" data-filter="arrears" title="Players with failed collections that have not been recovered yet (retry pending, on hold, or catch-up in flight)">
            <span class="was-treas-stat__num"><?php echo $stats['arrears']; ?></span>
            <span class="was-treas-stat__label">In arrears &middot; &pound;<?php echo number_format( (float) $stats['arrears_total'], 2 ); ?></span>
        </div>
    </div>
    <?php if ( $stats['exempt'] > 0 ) : ?>
    <p class="was-treas-exempt-note"><?php echo $stats['exempt']; ?> exempt player<?php echo $stats['exempt'] !== 1 ? 's' : ''; ?> (club-arranged, no Direct Debit needed) <?php echo $stats['exempt'] !== 1 ? 'are' : 'is'; ?> not counted in &ldquo;Awaiting first payment&rdquo;.</p>
    <?php endif; ?>

    <!-- Overdue players -->
    <?php if ( ! empty( $overdue ) ) : ?>
    <div class="was-treas-block">
        <h4 class="was-treas-block__title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Awaiting first payment &mdash; nothing confirmed or in transit (<?php echo count($overdue); ?>)
        </h4>
        <table class="was-treas-table">
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Team</th>
                    <th>Parent / Guardian</th>
                    <th>Email</th>
                    <th>Mandate</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $overdue as $p ) : ?>
            <tr data-team-id="<?php echo esc_attr($p->team_id); ?>" data-status="overdue">
                <td><strong><?php echo esc_html( trim( $p->first_name . ' ' . $p->last_name ) ); ?></strong></td>
                <td><?php echo esc_html( $p->team_name ); ?></td>
                <td><?php echo esc_html( trim( $p->g_first . ' ' . $p->g_last ) ); ?></td>
                <td><a href="mailto:<?php echo esc_attr( $p->g_email ); ?>" class="was-link"><?php echo esc_html( $p->g_email ); ?></a></td>
                <td>
                    <?php if ( $p->gc_mandate_id ) : ?>
                        <span class="was-badge was-badge--green">Set up</span>
                    <?php else : ?>
                        <span class="was-badge was-badge--red">Missing</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="was-btn was-btn--ghost was-btn--sm was-portal-reminder"
                            data-player-id="<?php echo esc_attr( $p->id ); ?>"
                            data-player-name="<?php echo esc_attr( $p->first_name ); ?>"
                            data-email="<?php echo esc_attr( $p->g_email ); ?>">
                        Send reminder
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- In arrears: one row per player with unrecovered failed collections -->
    <?php if ( ! empty( $arrears_players ) ) : ?>
    <div class="was-treas-block">
        <h4 class="was-treas-block__title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            In arrears (<?php echo count( $arrears_players ); ?>) &mdash; &pound;<?php echo number_format( (float) $stats['arrears_total'], 2 ); ?> outstanding
        </h4>
        <div class="was-treas-scroll">
        <table class="was-treas-table was-treas-table--arrears">
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Parent</th>
                    <th>Owed</th>
                    <th>Unpaid</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $arrears_players as $ap ) :
                $dates = array_filter( explode( ',', (string) $ap->failed_dates ) );
                $dates = array_map( function ( $d ) { return date_i18n( 'j M', strtotime( $d ) ); }, $dates );
                if ( $ap->inflight ) {
                    $badge = array( 'blue', 'Catch-up in flight' );
                    $sub   = '&pound;' . number_format( (float) $ap->inflight->amount, 2 ) . ' due ' . esc_html( date_i18n( 'j M', strtotime( $ap->inflight->charge_date ) ) );
                } elseif ( ! empty( $ap->payment_hold_since ) ) {
                    $badge = array( 'red', 'On hold' );
                    $sub   = 'since ' . esc_html( date_i18n( 'j M', strtotime( $ap->payment_hold_since ) ) );
                } elseif ( (int) $ap->retried > 0 ) {
                    $badge = array( 'red', 'Retry failed' );
                    $sub   = '';
                } else {
                    $badge = array( 'yellow', 'Awaiting retry' );
                    $sub   = '';
                }
                if ( $ap->registration_status === 'inactive' ) {
                    $sub .= ( $sub ? ' &middot; ' : '' ) . 'no Direct Debit';
                }
                $can_collect = ! $ap->inflight && $ap->registration_status === 'active' && ! empty( $ap->gc_mandate_id );
            ?>
            <tr data-team-id="<?php echo esc_attr( $ap->team_id ); ?>" data-status="arrears" data-player-id="<?php echo esc_attr( $ap->id ); ?>">
                <td><strong><?php echo esc_html( trim( $ap->first_name . ' ' . $ap->last_name ) ); ?></strong><span class="was-treas-sub"><?php echo esc_html( $ap->team_name ); ?></span></td>
                <td><?php echo esc_html( trim( $ap->g_first . ' ' . $ap->g_last ) ); ?><span class="was-treas-sub"><a href="mailto:<?php echo esc_attr( $ap->g_email ); ?>" class="was-link"><?php echo esc_html( $ap->g_email ); ?></a></span></td>
                <td><strong>&pound;<?php echo number_format( (float) $ap->arrears, 2 ); ?></strong></td>
                <td><?php echo esc_html( implode( ', ', $dates ) ); ?></td>
                <td><span class="was-badge was-badge--<?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span><?php if ( $sub ) : ?><span class="was-treas-sub"><?php echo $sub; ?></span><?php endif; ?></td>
                <td class="was-treas-actions">
                    <?php if ( $can_collect ) : ?>
                    <button class="was-btn was-btn--primary was-btn--sm was-treas-collect" data-player-id="<?php echo esc_attr( $ap->id ); ?>" data-player-name="<?php echo esc_attr( $ap->first_name ); ?>" data-amount="<?php echo esc_attr( number_format( (float) $ap->arrears, 2 ) ); ?>" title="Take the outstanding amount now as a one-off Direct Debit collection. The parent is emailed.">Collect now</button>
                    <?php endif; ?>
                    <span class="was-treas-actions__links">
                        <button type="button" class="was-linkbtn was-treas-clear-hold" data-player-id="<?php echo esc_attr( $ap->id ); ?>" data-player-name="<?php echo esc_attr( $ap->first_name ); ?>" title="Money received another way (cash, bank transfer, agreed write-off): mark it settled and lift the hold. The parent is emailed.">Clear hold</button>
                        <button type="button" class="was-linkbtn was-portal-reminder" data-player-id="<?php echo esc_attr( $ap->id ); ?>" data-player-name="<?php echo esc_attr( $ap->first_name ); ?>" data-email="<?php echo esc_attr( $ap->g_email ); ?>">Send reminder</button>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="was-treas-help">Returned collections are retried once automatically. If that fails the player goes on hold and the parent gets a one-click &ldquo;Pay now&rdquo; email. <strong>Collect now</strong> takes the same one-off Direct Debit on their behalf.</p>
        <div id="was-arrears-result" class="was-alert" style="display:none; margin:0 1.25rem 1rem;"></div>
    </div>
    <?php endif; ?>

    <!-- Failed payments (ALL of them — the Recent list below only shows the latest 50) -->
    <?php if ( ! empty( $failed_payments ) ) : ?>
    <div class="was-treas-block">
        <h4 class="was-treas-block__title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            Failed payments (<?php echo count( $failed_payments ); ?>)
        </h4>
        <div class="was-treas-scroll">
        <table class="was-treas-table">
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Parent</th>
                    <th>Amount</th>
                    <th>Charge date</th>
                    <th>Outcome</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $failed_payments as $fp ) :
                if ( $fp->recovered_by === 'manual' ) {
                    $badge = array( 'grey', 'Settled manually' );
                } elseif ( ! empty( $fp->recovered_by ) ) {
                    $badge = array( 'blue', 'Catch-up scheduled' );
                } elseif ( ! empty( $fp->payment_hold_since ) ) {
                    $badge = array( 'red', 'On hold' );
                } elseif ( (int) $fp->retry_count > 0 ) {
                    $badge = array( 'red', 'Retry also failed' );
                } elseif ( ! WAS_Arrears::is_retryable_cause( $fp->failure_reason ) ) {
                    $badge = array( 'red', 'Not retryable' );
                } else {
                    $badge = array( 'yellow', 'Awaiting retry' );
                }
                $reason = $fp->failure_reason ? ucfirst( str_replace( '_', ' ', $fp->failure_reason ) ) : '';
            ?>
            <tr data-team-id="<?php echo esc_attr( $fp->team_id ); ?>" data-status="failed">
                <td><strong><?php echo esc_html( trim( $fp->p_first . ' ' . $fp->p_last ) ); ?></strong><?php if ( $fp->kind === 'catchup' ) : ?> <span class="was-reg-muted">(catch-up)</span><?php endif; ?><span class="was-treas-sub"><?php echo esc_html( $fp->team_name ); ?></span></td>
                <td><?php echo esc_html( trim( $fp->g_first . ' ' . $fp->g_last ) ); ?><span class="was-treas-sub"><a href="mailto:<?php echo esc_attr( $fp->g_email ); ?>" class="was-link"><?php echo esc_html( $fp->g_email ); ?></a></span></td>
                <td>&pound;<?php echo number_format( (float) $fp->amount, 2 ); ?></td>
                <td><?php echo esc_html( date( 'd M Y', strtotime( $fp->charge_date ) ) ); ?></td>
                <td><span class="was-badge was-badge--<?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span><?php if ( $reason ) : ?><span class="was-treas-sub"><?php echo esc_html( $reason ); ?></span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="was-treas-help">Full history of returned collections. Anything still owed is actioned from &ldquo;In arrears&rdquo; above.</p>
    </div>
    <?php endif; ?>

    <!-- Recent payments -->
    <div class="was-treas-block">
        <h4 class="was-treas-block__title">Recent payments</h4>
        <?php if ( empty( $recent_payments ) ) : ?>
            <p class="was-empty-note">No payment records yet. Use the sync button above to pull data from GoCardless.</p>
        <?php else : ?>
        <table class="was-treas-table">
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Team</th>
                    <th>Parent</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $status_map = array(
                'confirmed'          => array( 'green',  'Confirmed' ),
                'failed'             => array( 'red',    'Failed' ),
                'submitted'          => array( 'blue',   'Submitted' ),
                'pending_submission' => array( 'yellow', 'Pending' ),
                'cancelled'          => array( 'grey',   'Cancelled' ),
                'refunded'           => array( 'grey',   'Refunded' ),
            );
            foreach ( $recent_payments as $pay ) :
                list( $cls, $lbl ) = $status_map[ $pay->status ] ?? array( 'grey', ucfirst( $pay->status ) );
                if ( in_array( $pay->status, array( 'submitted', 'pending_submission' ), true ) && (int) $pay->retry_count > 0 ) $lbl = 'Retrying';
                if ( $pay->status === 'failed' && ! empty( $pay->recovered_by ) ) { $cls = 'grey'; $lbl = ( $pay->recovered_by === 'manual' ) ? 'Failed — settled' : 'Failed — catch-up scheduled'; }
                if ( $pay->kind === 'catchup' ) $lbl .= ' (catch-up)';
                // Filter key: submitted + pending_submission both answer to the "In transit" tile.
                $filter_status = in_array( $pay->status, array( 'submitted', 'pending_submission' ), true ) ? 'intransit' : $pay->status;
            ?>
            <tr data-status="<?php echo esc_attr( $filter_status ); ?>" data-team-id="<?php echo esc_attr( $pay->team_id ); ?>">
                <td><strong><?php echo esc_html( trim( $pay->p_first . ' ' . $pay->p_last ) ); ?></strong></td>
                <td><?php echo esc_html( $pay->team_name ); ?></td>
                <td><?php echo esc_html( trim( $pay->g_first . ' ' . $pay->g_last ) ); ?></td>
                <td>£<?php echo number_format( $pay->amount, 2 ); ?></td>
                <td><?php echo $pay->charge_date ? esc_html( date( 'd M Y', strtotime( $pay->charge_date ) ) ) : '—'; ?></td>
                <td><span class="was-badge was-badge--<?php echo $cls; ?>"><?php echo $lbl; ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div id="was-reminder-msg" class="was-alert" style="display:none; margin-top:1rem;"></div>

</div>
