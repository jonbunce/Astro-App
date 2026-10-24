<?php defined( 'ABSPATH' ) || exit; ?>
<div id="was-section-parent" class="was-portal-section">

    <div class="was-portal-section__header">
        <h3 class="was-portal-section__title">My children &mdash; <?php echo esc_html( was_season() ); ?></h3>
        <a href="<?php echo esc_url( home_url( '/registrations-unified/' ) ); ?>" class="was-btn was-btn--primary was-btn--sm">
            + Register a child
        </a>
    </div>

    <?php if ( ! $guardian ) : ?>
    <div class="was-info-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div>
            <strong>No registrations linked yet</strong>
            <p>Once you complete a player registration, your children will appear here. If you&rsquo;ve already registered a child this season, use the button above to register your child for this season.</p>
        </div>
    </div>

    <?php elseif ( empty( $players ) ) : ?>
    <div class="was-info-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div>
            <strong>No registrations for <?php echo esc_html( was_season() ); ?></strong>
            <p>Use the button above to register your child for this season.</p>
        </div>
    </div>

    <?php else : ?>

    <?php foreach ( $players as $player ) :
        $payments      = $payment_map[ $player->id ] ?? array();
        $arrears       = $arrears_map[ $player->id ] ?? array( 'rows' => array(), 'total' => 0.0, 'inflight' => null, 'hold_since' => null );
        $last_confirmed = null;
        // "Failed" only counts collections that are still outstanding — a failed payment
        // that has since been retried or covered by a catch-up is not a live problem.
        $has_failed     = ( (float) $arrears['total'] > 0 );
        foreach ( $payments as $pay ) {
            if ( in_array( $pay->status, array('confirmed','paid_out'), true ) && ! $last_confirmed ) $last_confirmed = $pay;
        }
    ?>
    <div class="was-player-card">
        <div class="was-player-card__header">
            <div class="was-player-card__name">
                <?php
                $photo_nonce  = wp_create_nonce('was_photo_nonce');
                $thumb_src    = $player->photo_path
                    ? admin_url('admin-ajax.php') . '?action=was_serve_photo&player_id=' . $player->id . '&nonce=' . $photo_nonce
                    : '';
                ?>
                <div class="was-player-thumb-wrap">
                    <?php if ( $thumb_src ) : ?>
                    <img src="<?php echo esc_url($thumb_src); ?>" class="was-player-avatar-img" alt="" id="was-thumb-<?php echo $player->id; ?>">
                    <?php else : ?>
                    <span class="was-player-avatar" id="was-thumb-<?php echo $player->id; ?>"><?php echo esc_html( strtoupper( substr( $player->first_name, 0, 1 ) ) ); ?></span>
                    <?php endif; ?>
                    <label class="was-thumb-change-btn" for="was-photo-input-<?php echo $player->id; ?>" title="Change photo">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    </label>
                    <input type="file" id="was-photo-input-<?php echo $player->id; ?>" class="was-photo-file-input" data-player-id="<?php echo $player->id; ?>" accept="image/*" style="display:none;">
                </div>
                <div>
                    <strong><?php echo esc_html( trim( $player->first_name . ' ' . $player->last_name ) ); ?></strong>
                    <span class="was-player-team">
                        <?php if ( ! empty( $player->team_logo ) ) : ?>
                        <img src="<?php echo esc_url( was_logo_url( $player->team_logo ) ); ?>" class="was-player-team-logo" alt="">
                        <?php endif; ?>
                        <?php echo esc_html( $player->team_name ); ?>
                    </span>
                </div>
            </div>
            <div class="was-player-card__actions">
                <?php if ( $player->participation_type === 'signed' ) : ?>
                    <span class="was-chip was-chip--signed" title="Signed player &mdash; FA registered, subscription by Direct Debit">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                        <i>Signed</i>
                    </span>
                <?php else : ?>
                    <span class="was-chip was-chip--train" title="Train only &mdash; subscription paid directly to the team">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 3 4 21h16L12 3z"/><line x1="8.7" y1="14" x2="15.3" y2="14"/></svg>
                        <i>Train only</i>
                    </span>
                <?php endif; ?>

                <?php if ( $player->registration_status === 'active' ) : ?>
                    <span class="was-chip was-chip--ok" title="Registered for <?php echo esc_attr( was_season() ); ?>">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="8 12.5 11 15.5 16 9.5"/></svg>
                        <i>Registered</i>
                    </span>
                <?php else : ?>
                    <span class="was-chip was-chip--pending" title="Registration status">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 7 12 12 15 14"/></svg>
                        <i><?php echo esc_html( ucfirst( $player->registration_status ) ); ?></i>
                    </span>
                <?php endif; ?>

                <button type="button" class="was-iconbtn was-addteam-toggle" data-player-id="<?php echo $player->id; ?>" title="Request to add another team" aria-label="Request to add another team">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    <i>Add team</i>
                </button>
                <button type="button" class="was-iconbtn was-child-edit-toggle" data-player-id="<?php echo $player->id; ?>" title="Edit details" aria-label="Edit details">
                    <svg class="icon-open" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                    <svg class="icon-close" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    <i class="label-open">Edit</i>
                    <i class="label-close">Close</i>
                </button>
            </div>
        </div>

        <?php
        // "Add another team" request panel — teams this child isn't already on.
        $taken_ids = array( (int) $player->team_id );
        foreach ( WAS_Database::get_player_extra_teams( $player->id ) as $pt ) {
            $taken_ids[] = (int) $pt->team_id;
        }
        $addable = array_filter( $all_teams ?? array(), function ( $t ) use ( $taken_ids ) {
            return ! in_array( (int) $t->id, $taken_ids, true );
        } );
        ?>
        <div class="was-addteam-panel" id="was-addteam-<?php echo $player->id; ?>" style="display:none;">
            <div class="was-addteam-form">
                <p class="was-addteam-intro">Does <?php echo esc_html( $player->first_name ); ?> also play for another team? Choose it below and we'll add it to their existing registration and adjust the Direct Debit &mdash; <strong>no need to register them again</strong>.</p>
                <div class="was-addteam-row">
                    <select class="was-select was-addteam-select">
                        <option value="">&mdash; Choose a team &mdash;</option>
                        <?php foreach ( $addable as $t ) : ?>
                        <option value="<?php echo esc_attr( $t->id ); ?>"><?php echo esc_html( $t->name . ( $t->age_group ? ' (' . $t->age_group . ')' : '' ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="was-btn was-btn--primary was-btn--sm was-addteam-send" data-player-id="<?php echo $player->id; ?>" data-child="<?php echo esc_attr( trim( $player->first_name . ' ' . $player->last_name ) ); ?>">Send request</button>
                </div>
            </div>
            <div class="was-alert was-addteam-msg" style="display:none;margin-top:.6rem;"></div>
        </div>

        <?php if ( $player->participation_type === 'signed' && $player->registration_status === 'inactive' ) : ?>
        <div class="was-rejoin-banner">
            <div class="was-rejoin-banner__text">
                <strong>Direct Debit cancelled &mdash; <?php echo esc_html( $player->first_name ); ?>&rsquo;s place is paused.</strong>
                <p>Set up a new Direct Debit to reactivate their registration &mdash; it takes about two minutes and all their details are kept.</p>
            </div>
            <button type="button" class="was-btn was-btn--primary was-btn--sm was-rejoin-btn" data-player-id="<?php echo $player->id; ?>">Set up a new Direct Debit</button>
        </div>
        <?php endif; ?>

        <?php if ( $player->participation_type === 'signed' && (float) $arrears['total'] > 0 ) :
            $ar_total  = number_format( (float) $arrears['total'], 2 );
            $ar_count  = count( $arrears['rows'] );
            $on_hold   = ! empty( $arrears['hold_since'] );
            $inflight  = $arrears['inflight'];
            $can_pay   = ! $inflight && $player->registration_status === 'active' && ! empty( $player->gc_mandate_id );
        ?>
        <div class="was-arrears-banner<?php echo $on_hold ? ' was-arrears-banner--hold' : ''; ?>" id="was-arrears-<?php echo $player->id; ?>">
            <div class="was-arrears-banner__text">
                <strong>&pound;<?php echo $ar_total; ?> outstanding &mdash; <?php echo $ar_count; ?> collection<?php echo $ar_count !== 1 ? 's' : ''; ?> returned unpaid<?php if ( $on_hold ) : ?>. <?php echo esc_html( $player->first_name ); ?>&rsquo;s place is on hold until this is paid.<?php endif; ?></strong>
                <ul class="was-arrears-list">
                    <?php foreach ( $arrears['rows'] as $ar ) : ?>
                    <li><?php echo esc_html( date_i18n( 'j F Y', strtotime( $ar->charge_date ) ) ); ?>: &pound;<?php echo number_format( (float) $ar->amount, 2 ); ?><?php if ( ! empty( $ar->failure_reason ) ) : ?> <span class="was-schedule-note">(<?php echo esc_html( WAS_Arrears::reason_text( $ar->failure_reason ) ); ?>)</span><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ( $inflight ) : ?>
                <p>A catch-up collection of <strong>&pound;<?php echo number_format( (float) $inflight->amount, 2 ); ?></strong> is on its way and should leave your account on or around <strong><?php echo esc_html( date_i18n( 'j F Y', strtotime( $inflight->charge_date ) ) ); ?></strong>. Please make sure the funds are available. <?php if ( $on_hold ) : ?>The hold lifts automatically once it clears.<?php endif; ?></p>
                <?php elseif ( $can_pay ) : ?>
                <p>Pay it now in one click. This takes a single one-off collection of &pound;<?php echo $ar_total; ?> from your existing Direct Debit &mdash; no card details needed &mdash; and clears everything above.<?php if ( $on_hold ) : ?> The hold is lifted automatically as soon as it clears.<?php endif; ?> Or email <a class="was-link" href="mailto:<?php echo esc_attr( WAS_Notifications::finance_email() ); ?>"><?php echo esc_html( WAS_Notifications::finance_email() ); ?></a> to arrange it another way.</p>
                <?php elseif ( $player->registration_status === 'inactive' ) : ?>
                <p>Set up a new Direct Debit using the button above first, then come back here to pay the outstanding amount in one click. Or email <a class="was-link" href="mailto:<?php echo esc_attr( WAS_Notifications::finance_email() ); ?>"><?php echo esc_html( WAS_Notifications::finance_email() ); ?></a> to arrange it another way.</p>
                <?php else : ?>
                <p>Please email <a class="was-link" href="mailto:<?php echo esc_attr( WAS_Notifications::finance_email() ); ?>"><?php echo esc_html( WAS_Notifications::finance_email() ); ?></a> to arrange payment.</p>
                <?php endif; ?>
                <div class="was-alert was-arrears-msg" style="display:none;margin-top:.6rem;"></div>
            </div>
            <?php if ( $can_pay ) : ?>
            <button type="button" class="was-btn was-btn--primary was-btn--sm was-pay-arrears-btn" data-player-id="<?php echo $player->id; ?>" data-player-name="<?php echo esc_attr( $player->first_name ); ?>" data-amount="<?php echo esc_attr( $ar_total ); ?>">Pay &pound;<?php echo $ar_total; ?> now</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( $player->participation_type === 'signed' && $player->registration_status === 'active' ) : ?>
        <?php $bd = was_player_payment_breakdown( $player ); ?>
        <?php if ( ! $bd['billable'] && isset( $player->subs_override ) && $player->subs_override !== null && (float) $player->subs_override === 0.0 ) : ?>
        <div class="was-player-card__payment">
            <p class="was-schedule-foot was-m-0">This place is club-arranged &mdash; <strong>no subscription or Direct Debit is needed</strong>.</p>
        </div>
        <?php else : ?>
        <div class="was-player-card__payment">

            <?php if ( ! empty( $bd['items'] ) ) : ?>
            <details class="was-payment-schedule">
                <summary>Payment schedule &mdash; <strong>&pound;<?php echo number_format( (float) $bd['total'], 2 ); ?> / month</strong></summary>
                <table class="was-mini-table was-schedule-table">
                    <tbody>
                    <?php foreach ( $bd['items'] as $it ) : ?>
                    <tr>
                        <td><?php echo esc_html( $it['label'] ); ?><?php echo $it['note'] ? ' <span class="was-schedule-note">(' . esc_html( $it['note'] ) . ')</span>' : ''; ?></td>
                        <td class="was-schedule-amt">&pound;<?php echo number_format( (float) $it['amount'], 2 ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="was-schedule-total">
                        <td>Total each month</td>
                        <td class="was-schedule-amt">&pound;<?php echo number_format( (float) $bd['total'], 2 ); ?></td>
                    </tr>
                    </tbody>
                </table>
                <p class="was-schedule-foot">Collected by Direct Debit via GoCardless on the <?php echo esc_html( was_ordinal( was_payment_day( $player ) ) ); ?> of each month<?php if ( was_first_payment_date() ) : ?>, starting <?php echo esc_html( date_i18n( 'j F Y', strtotime( was_first_payment_date() ) ) ); ?><?php endif; ?>.</p>
            </details>
            <?php endif; ?>

            <div class="was-payment-status">
                <?php if ( $has_failed ) : ?>
                    <span class="was-payment-icon was-payment-icon--failed">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </span>
                    <span class="was-payment-text was-payment-text--failed">A collection was returned unpaid &mdash; see the outstanding amount above.</span>
                <?php elseif ( $last_confirmed ) : ?>
                    <span class="was-payment-icon was-payment-icon--ok">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="was-payment-text">Last payment confirmed <?php echo esc_html( date( 'd M Y', strtotime( $last_confirmed->charge_date ) ) ); ?></span>
                <?php else : ?>
                    <span class="was-payment-icon was-payment-icon--pending">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <span class="was-payment-text">No confirmed payment yet this season.</span>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $payments ) ) : ?>
            <details class="was-payment-history">
                <summary>Payment history (<?php echo count( $payments ); ?>)</summary>
                <table class="was-mini-table">
                    <thead><tr><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ( $payments as $pay ) :
                        $status_class = in_array( $pay->status, array('confirmed','paid_out'), true ) ? 'green' : ( $pay->status === 'failed' ? 'red' : 'yellow' );
                        $status_label = ucwords( str_replace( '_', ' ', $pay->status ) );
                        if ( $pay->status === 'failed' && ! empty( $pay->recovered_by ) ) {
                            $status_label = ( $pay->recovered_by === 'manual' ) ? 'Failed — settled' : 'Failed — catch-up scheduled';
                            $status_class = 'grey';
                        } elseif ( in_array( $pay->status, array( 'pending_submission', 'submitted' ), true ) && (int) ( $pay->retry_count ?? 0 ) > 0 ) {
                            $status_label = 'Retrying';
                        }
                        $row_label = ( ( $pay->kind ?? '' ) === 'catchup' ) ? ' <span class="was-schedule-note">(catch-up)</span>' : '';
                    ?>
                    <tr>
                        <td><?php echo $pay->charge_date ? esc_html( date( 'd M Y', strtotime( $pay->charge_date ) ) ) : '—'; ?><?php echo $row_label; ?></td>
                        <td>£<?php echo number_format( $pay->amount, 2 ); ?></td>
                        <td><span class="was-badge was-badge--<?php echo $status_class; ?>"><?php echo esc_html( $status_label ); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
            <?php endif; ?>
        </div>
        <?php endif; // billable vs exempt ?>
        <?php endif; // signed ?>

    </div>
        <?php
        // Child edit form data
        global $wpdb;
        $consents   = WAS_Database::get_consents( $player->id );
        $photo_cons = $consents['photography']       ?? 'agree';
        $legal_cons = $consents['legal_proceedings'] ?? 'agree';
        $ec         = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_emergency_contacts WHERE player_id = %d", $player->id
        ) );
        $med        = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_medical WHERE player_id = %d", $player->id
        ) );
        $schools    = WAS_Database::get_schools();
        ?>
        <div class="was-child-edit-form" id="was-edit-<?php echo $player->id; ?>" style="display:none;">
            <div id="was-child-edit-msg-<?php echo $player->id; ?>" class="was-alert" style="display:none;margin-bottom:1rem;"></div>
            <h4 class="was-child-edit-section">Player details</h4>
            <div class="was-field-row">
                <div class="was-field">
                    <label class="was-label was-label--required">First name</label>
                    <input type="text" class="was-input" name="first_name" value="<?php echo esc_attr($player->first_name); ?>">
                </div>
                <div class="was-field">
                    <label class="was-label was-label--required">Last name</label>
                    <input type="text" class="was-input" name="last_name" value="<?php echo esc_attr($player->last_name); ?>">
                </div>
            </div>
            <div class="was-field-row">
                <div class="was-field">
                    <label class="was-label was-label--required">Date of birth</label>
                    <input type="date" class="was-input" name="dob" value="<?php echo esc_attr($player->dob); ?>" max="<?php echo esc_attr( date('Y-m-d') ); ?>">
                </div>
                <div class="was-field"></div>
            </div>
            <div class="was-field">
                <label class="was-label was-label--required">School</label>
                <select class="was-select was-school-select-edit" data-wrap="was-school-other-edit-<?php echo $player->id; ?>" name="school_id">
                    <option value="">— Please select —</option>
                    <?php foreach ( $schools as $s ) : ?>
                    <option value="<?php echo $s->id; ?>" <?php selected($player->school_id, $s->id); ?>><?php echo esc_html($s->name); ?></option>
                    <?php endforeach; ?>
                    <option value="other" <?php echo (!$player->school_id && $player->school_other) ? "selected" : ""; ?>>My child&rsquo;s school isn&rsquo;t listed</option>
                </select>
            </div>
            <div class="was-field" id="was-school-other-edit-<?php echo $player->id; ?>" style="<?php echo (!$player->school_id && $player->school_other) ? "" : "display:none;"; ?>">
                <label class="was-label">School name</label>
                <input type="text" class="was-input" name="school_other" value="<?php echo esc_attr($player->school_other); ?>">
            </div>
            <h4 class="was-child-edit-section">Emergency contact</h4>
            <div class="was-field-row">
                <div class="was-field">
                    <label class="was-label was-label--required">Contact name</label>
                    <input type="text" class="was-input" name="emergency_name" value="<?php echo esc_attr($ec->contact_name ?? ""); ?>">
                </div>
                <div class="was-field">
                    <label class="was-label was-label--required">Contact telephone</label>
                    <input type="tel" class="was-input" name="emergency_telephone" value="<?php echo esc_attr($ec->contact_telephone ?? ""); ?>">
                </div>
            </div>
            <h4 class="was-child-edit-section">Medical information</h4>
            <?php
            // Medical fields map: form key => DB column => question
            $med_qs = array(
                'health_needs'        => array(
                    'col'      => 'health_needs',
                    'question' => 'Does your child have any health, physical or mental health needs that may affect participation?',
                    'detail_label' => 'Please give details',
                ),
                'medication'          => array(
                    'col'      => 'current_medication',
                    'question' => 'Does your child take any regular medication coaches should be aware of?',
                    'detail_label' => 'Medication details',
                ),
                'medical_training'    => array(
                    'col'      => 'medical_training_needs',
                    'question' => 'Does your child have a condition that may require first aid during activity?',
                    'detail_label' => 'Please give details',
                ),
                'access_needs'        => array(
                    'col'      => 'access_needs',
                    'question' => 'Does your child have any access or mobility requirements?',
                    'detail_label' => 'Please give details',
                ),
                'communication_needs' => array(
                    'col'      => 'communication_needs',
                    'question' => 'Does your child have any communication or learning needs we should know about?',
                    'detail_label' => 'Please give details',
                ),
            );
            foreach ( $med_qs as $key => $info ) :
                $db_val  = $med ? ( $med->{$info['col']} ?? '' ) : '';
                $is_yes  = ! empty( $db_val );
            ?>
            <div class="was-med-row was-med-row--compact">
                <p class="was-med-q"><?php echo esc_html($info['question']); ?></p>
                <div class="was-yn-choice">
                    <label class="was-yn-label was-yn-yes"><input type="radio" name="yn_<?php echo $key; ?>" value="yes" <?php checked($is_yes, true); ?> class="was-yn-radio was-med-yn-toggle" data-detail="med-detail-<?php echo $player->id . '-' . $key; ?>"> Yes</label>
                    <label class="was-yn-label was-yn-no"><input type="radio"  name="yn_<?php echo $key; ?>" value="no"  <?php checked($is_yes, false); ?> class="was-yn-radio was-med-yn-toggle" data-detail="med-detail-<?php echo $player->id . '-' . $key; ?>"> No</label>
                </div>
            </div>
            <div id="med-detail-<?php echo $player->id . '-' . $key; ?>" style="<?php echo $is_yes ? '' : 'display:none;'; ?>padding:0 0 0.75rem 0;">
                <label class="was-label was-hint"><?php echo esc_html($info['detail_label']); ?></label>
                <textarea class="was-input" name="med_detail_<?php echo $key; ?>" rows="2"><?php echo esc_textarea($db_val); ?></textarea>
            </div>
            <?php endforeach; ?>
            <h4 class="was-child-edit-section">Consent settings</h4>
            <p class="was-hint was-mb-1">Changes to consent are reported immediately to the club safeguarding team.</p>
            <div class="was-consent-row">
                <span>Photography &amp; Social Media</span>
                <div class="was-yn-choice">
                    <label class="was-yn-label was-yn-yes"><input type="radio" name="consent_photography" value="agree"    class="was-consent-radio" data-player-id="<?php echo $player->id; ?>" data-type="photography" <?php checked($photo_cons,"agree"); ?>> Yes</label>
                    <label class="was-yn-label was-yn-no"><input type="radio"  name="consent_photography" value="disagree" class="was-consent-radio" data-player-id="<?php echo $player->id; ?>" data-type="photography" <?php checked($photo_cons,"disagree"); ?>> No</label>
                </div>
            </div>
            <div class="was-consent-row">
                <span>Any family, care or legal proceedings that mean they shouldn&rsquo;t be photographed?</span>
                <div class="was-yn-choice">
                    <?php // Same stored values as sign-up: "disagree" = proceedings declared (Yes), "agree" = none (No). ?>
                    <label class="was-yn-label was-yn-no"><input type="radio"  name="consent_legal" value="disagree" class="was-consent-radio" data-player-id="<?php echo $player->id; ?>" data-type="legal_proceedings" <?php checked($legal_cons,"disagree"); ?>> Yes</label>
                    <label class="was-yn-label was-yn-yes"><input type="radio" name="consent_legal" value="agree"    class="was-consent-radio" data-player-id="<?php echo $player->id; ?>" data-type="legal_proceedings" <?php checked($legal_cons,"agree"); ?>> No</label>
                </div>
            </div>
            <div class="was-btn-row">
                <button type="button" class="was-btn was-btn--primary was-save-child-btn" data-player-id="<?php echo $player->id; ?>">
                    <span class="was-btn-label">Save changes</span>
                    <span class="was-btn-spinner" style="display:none;">Saving&hellip;</span>
                </button>
                <button type="button" class="was-btn was-btn--outline was-child-edit-toggle" data-player-id="<?php echo $player->id; ?>">Cancel</button>
            </div>
        </div><!-- .was-child-edit-form -->
    <?php endforeach; ?>

    <?php endif; ?>

    <div class="was-contact-note">
        <p>If your child&rsquo;s medical information has changed during the season, please let your coach or the club know as soon as possible.</p>
    </div>

</div>

<!-- Profile edit section -->
<div id="was-section-profile" class="was-portal-section">
    <div class="was-portal-section__header">
        <h3 class="was-portal-section__title">My Profile</h3>
    </div>

    <?php
    $occ_cats = WAS_Accounts::occupation_categories();
    ?>

    <div class="was-card">
        <div id="was-profile-success" class="was-alert was-alert--success" style="display:none;"></div>
        <div id="was-profile-error"   class="was-alert was-alert--error"   style="display:none;"></div>

        <div class="was-field-row">
            <div class="was-field">
                <label class="was-label was-label--required">First name</label>
                <input type="text" id="was-profile-first" class="was-input" value="<?php echo esc_attr($guardian->first_name ?? ''); ?>">
            </div>
            <div class="was-field">
                <label class="was-label was-label--required">Last name</label>
                <input type="text" id="was-profile-last" class="was-input" value="<?php echo esc_attr($guardian->last_name ?? ''); ?>">
            </div>
        </div>

        <div class="was-field">
            <label class="was-label was-label--required">Telephone number</label>
            <input type="tel" id="was-profile-tel" class="was-input" value="<?php echo esc_attr($guardian->telephone ?? ''); ?>">
        </div>

        <div class="was-field">
            <label class="was-label was-label--required">First line of home address</label>
            <input type="text" id="was-profile-address" class="was-input" value="<?php echo esc_attr($guardian->address_line1 ?? ''); ?>">
        </div>

        <div class="was-field was-field--half">
            <label class="was-label was-label--required">Postcode</label>
            <input type="text" id="was-profile-postcode" class="was-input was-upper" value="<?php echo esc_attr($guardian->postcode ?? ''); ?>">
        </div>

        <div class="was-field">
            <label class="was-label">Occupation category</label>
            <select id="was-profile-occ" class="was-select">
                <?php foreach ( $occ_cats as $val => $label ) : ?>
                <option value="<?php echo esc_attr($val); ?>" <?php selected($guardian->occupation_category ?? '', $val); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="was-field">
            <label class="was-label">
                Employer / business name
                <span class="was-label-hint">Optional — helps us explore sponsorship opportunities with local businesses.</span>
            </label>
            <input type="text" id="was-profile-employer" class="was-input" value="<?php echo esc_attr($guardian->employer_name ?? ''); ?>" placeholder="e.g. Smith Plumbing, Wolverhampton">
        </div>

        <button type="button" id="was-profile-save-btn" class="was-btn was-btn--primary">
            <span class="was-btn-label">Save changes</span>
            <span class="was-btn-spinner" style="display:none;">Saving&hellip;</span>
        </button>
    </div>

</div>
