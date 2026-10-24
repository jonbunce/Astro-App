<?php defined( 'ABSPATH' ) || exit; ?>
<div id="was-section-coach" class="was-portal-section">

    <div class="was-portal-section__header">
        <h3 class="was-portal-section__title">My teams &mdash; <?php echo esc_html( was_season() ); ?></h3>
        <span class="was-readonly-note">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Read only &mdash; health &amp; safeguarding
        </span>
    </div>

    <?php if ( ! empty( $team_data ) ) : ?>
    <a href="<?php echo esc_url( home_url( '/submit-sponsor/' ) ); ?>" class="was-btn was-btn--ghost was-btn--sm was-mb-1">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.4 6.6L21 9.3l-5 4.6L17.4 21 12 17.3 6.6 21 8 13.9 3 9.3l6.6-.7z"/></svg>
        Submit a Team Sponsor
    </a>
    <?php endif; ?>

    <?php if ( empty( $team_data ) ) : ?>
    <div class="was-info-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div>
            <strong>No teams assigned</strong>
            <p>Contact the club administrator to have your teams linked to your account.</p>
        </div>
    </div>

    <?php else : ?>

    <?php foreach ( $team_data as $td ) :
        $team    = $td['team'];
        $players = $td['players'];
    ?>
    <div class="was-team-block">
        <?php /* Wraps header + profile form so the in-header "Edit team profile" toggle
                 and the form share one data-team-id container (portal.js closest() logic). */ ?>
        <div class="was-team-profile-edit" data-team-id="<?php echo esc_attr( $team->id ); ?>">
        <div class="was-team-block__header">
            <?php /* Hover-to-update logo */ ?>
            <label class="was-team-logo-hover-wrap was-logo-upload-btn" data-team-id="<?php echo esc_attr($team->id); ?>" title="Click to update logo">
                <?php if ( $team->logo_path ) : ?>
                <img src="<?php echo esc_url( was_logo_url( $team->logo_path ) ); ?>" class="was-team-logo" alt="" id="was-logo-img-<?php echo $team->id; ?>">
                <?php else : ?>
                <div class="was-team-logo-placeholder" id="was-logo-img-<?php echo $team->id; ?>">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <?php endif; ?>
                <div class="was-team-logo-hover-overlay">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    <span>Update</span>
                </div>
                <input type="file" class="was-logo-file-input" data-team-id="<?php echo esc_attr($team->id); ?>" accept="image/*" style="display:none;">
            </label>

            <div class="was-team-block__titlewrap">
                <h4 class="was-team-block__name"><?php echo esc_html( $team->name ); ?></h4>
                <button type="button" class="was-profile-toggle-btn was-team-profile-link">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    Edit team profile
                </button>
                <span class="was-profile-save-status" style="display:none;"></span>
            </div>

            <?php /* Squad count */ ?>
            <div class="was-team-player-count">
                <span class="was-team-player-count__num"><?php echo count( $players ); ?></span>
                <span class="was-team-player-count__label">player<?php echo count( $players ) !== 1 ? 's' : ''; ?></span>
            </div>

            <span class="was-logo-upload-status" data-team-id="<?php echo esc_attr($team->id); ?>" style="display:none;"></span>
        </div>

        <?php /* Team profile (league + description) — shown on the public Team Profiles page */ ?>
            <form class="was-profile-form" style="display:none;">
                <div class="was-field">
                    <label class="was-label">League</label>
                    <input type="text" class="was-input was-profile-league-input" maxlength="150" value="<?php echo esc_attr( $team->league ?? '' ); ?>" placeholder="e.g. Bilston &amp; District Youth League">
                </div>
                <div class="was-field">
                    <label class="was-label">Team description</label>
                    <textarea class="was-input was-profile-desc-input" rows="4" placeholder="A short introduction to your team for the public Team Profiles page&hellip;"><?php echo esc_textarea( $team->description ?? '' ); ?></textarea>
                </div>
                <div class="was-field-row">
                    <div class="was-field">
                        <label class="was-label">Training times &amp; venue</label>
                        <input type="text" class="was-input was-profile-training-input" maxlength="255" value="<?php echo esc_attr( $team->training_info ?? '' ); ?>" placeholder="e.g. Tuesdays 6&ndash;7pm, Wombourne Leisure Centre">
                    </div>
                    <div class="was-field">
                        <label class="was-label">Coach name(s)</label>
                        <input type="text" class="was-input was-profile-coaches-input" maxlength="200" value="<?php echo esc_attr( $team->coach_names ?? '' ); ?>" placeholder="e.g. Sam Smith &amp; Jo Jones">
                    </div>
                </div>
                <div class="was-field-row">
                    <div class="was-field">
                        <label class="was-label">Public contact <span class="was-hint">(optional — shown on the public page)</span></label>
                        <input type="text" class="was-input was-profile-contact-input" maxlength="200" value="<?php echo esc_attr( $team->coach_contact ?? '' ); ?>" placeholder="e.g. bears@wombourneallstars.co.uk">
                    </div>
                    <div class="was-field">
                        <label class="was-label">Team sponsor</label>
                        <input type="text" class="was-input was-profile-sponsor-input" maxlength="255" value="<?php echo esc_attr( $team->sponsor_line ?? '' ); ?>" placeholder="e.g. Proudly sponsored by Smith Plumbing">
                    </div>
                </div>
                <div class="was-field">
                    <label class="was-label">Honours / achievements</label>
                    <textarea class="was-input was-profile-honours-input" rows="2" placeholder="e.g. League champions 2024&ndash;25 &middot; Cup runners-up 2023&ndash;24"><?php echo esc_textarea( $team->honours ?? '' ); ?></textarea>
                </div>
                <p class="was-empty-note" style="margin:0 0 0.6rem;">Only filled-in fields appear on your public team page.</p>
                <button type="submit" class="was-btn was-btn--primary was-btn--sm">Save profile</button>
                <button type="button" class="was-btn was-btn--ghost was-btn--sm was-profile-cancel-btn">Cancel</button>
            </form>
        </div><!-- .was-team-profile-edit -->

        <?php if ( empty( $players ) ) : ?>
            <p class="was-empty-note">No active players registered for this team yet.</p>
        <?php else : ?>

        <div class="was-coach-player-list">
        <?php foreach ( $players as $p ) :
            $med      = $p->medical;
            $consents = $p->consents;
            $ec       = $p->emergency;
            $has_medical = $med && (
                $med->health_needs || $med->current_medication ||
                $med->medical_training_needs || $med->access_needs || $med->communication_needs
            );
        ?>
        <details class="was-coach-player">
            <summary class="was-coach-player__summary">
                <span class="was-player-avatar was-player-avatar--sm"><?php echo esc_html( strtoupper( substr( $p->first_name, 0, 1 ) ) ); ?></span>
                <span class="was-coach-player__idwrap">
                <span class="was-coach-player__name"><?php echo esc_html( trim( $p->first_name . ' ' . $p->last_name ) ); ?><?php $cage = was_age( $p->dob ?? '' ); if ( $cage !== '' ) echo ' <span style="color:var(--was-text-muted);font-weight:400;">(' . esc_html( $cage ) . ' yrs)</span>'; ?></span>
                <span class="was-coach-player__badges">
                    <?php if ( $p->participation_type === 'signed' ) : ?>
                        <span class="was-sbadge was-sbadge--signed" title="Signed — FA registered player">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                            Signed
                        </span>
                    <?php else : ?>
                        <span class="was-sbadge was-sbadge--train" title="Train only — not FA registered">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 3 4 21h16L12 3z"/><line x1="8.7" y1="14" x2="15.3" y2="14"/></svg>
                            Train only
                        </span>
                    <?php endif; ?>
                    <?php if ( ! empty( $p->payment_hold_since ) ) : ?>
                        <span class="was-sbadge was-sbadge--hold" title="Payment hold — subscription in arrears. This player should not train or play until the club confirms it is cleared.">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            Payment hold
                        </span>
                    <?php endif; ?>
                    <?php if ( ( $consents['photography'] ?? '' ) === 'disagree' ) : ?>
                        <span class="was-sbadge was-sbadge--nophoto" title="No photography consent — do not photograph this player">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h3"/><path d="M9.5 4h5l2 3h4a2 2 0 0 1 2 2v7"/><circle cx="12" cy="13" r="3.2"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            No photos
                        </span>
                    <?php endif; ?>
                    <?php if ( $has_medical ) : ?>
                        <span class="was-sbadge was-sbadge--medical" title="Has medical / health information recorded">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            Health Notes
                        </span>
                    <?php endif; ?>
                </span>
                </span><!-- .was-coach-player__idwrap -->
                <svg class="was-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </summary>

            <div class="was-coach-player__detail">

                <div class="was-detail-grid">

                    <div class="was-detail-section">
                        <h5 class="was-detail-heading">Emergency contact</h5>
                        <?php if ( $ec && $ec->contact_name ) : ?>
                            <p class="was-detail-value"><strong><?php echo esc_html( $ec->contact_name ); ?></strong></p>
                            <p class="was-detail-value"><?php echo esc_html( $ec->contact_telephone ); ?></p>
                        <?php else : ?>
                            <p class="was-detail-empty">Not recorded</p>
                        <?php endif; ?>

                        <h5 class="was-detail-heading was-mt-1">Parent / carer</h5>
                        <p class="was-detail-value"><?php echo esc_html( $p->g_first . ' ' . $p->g_last ); ?></p>
                        <p class="was-detail-value"><?php echo esc_html( $p->g_tel ); ?></p>
                    </div>

                    <div class="was-detail-section">
                        <h5 class="was-detail-heading">Photo consent</h5>
                        <?php $photo = $consents['photography'] ?? 'not recorded'; ?>
                        <span class="was-badge was-badge--<?php echo $photo === 'agree' ? 'green' : ( $photo === 'disagree' ? 'red' : 'grey' ); ?>">
                            <?php echo $photo === 'agree' ? 'Consent given' : ( $photo === 'disagree' ? 'No consent' : 'Not recorded' ); ?>
                        </span>

                        <h5 class="was-detail-heading was-mt-1">Medical data consent</h5>
                        <?php $med_consent = $consents['medical_data'] ?? 'not recorded'; ?>
                        <span class="was-badge was-badge--<?php echo $med_consent === 'agree' ? 'green' : ( $med_consent === 'disagree' ? 'red' : 'grey' ); ?>">
                            <?php echo $med_consent === 'agree' ? 'Consent given' : ( $med_consent === 'disagree' ? 'Withdrawn' : 'Not recorded' ); ?>
                        </span>
                    </div>

                </div>

                <?php if ( $has_medical ) : ?>
                <div class="was-medical-block">
                    <h5 class="was-detail-heading was-detail-heading--medical">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        Medical &amp; health information
                    </h5>

                    <?php if ( $med->health_needs ) : ?>
                    <div class="was-medical-row">
                        <span class="was-medical-label">Health needs</span>
                        <span class="was-medical-value"><?php echo nl2br( esc_html( $med->health_needs ) ); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ( $med->current_medication ) : ?>
                    <div class="was-medical-row was-medical-row--alert">
                        <span class="was-medical-label">Medication / action required</span>
                        <span class="was-medical-value"><?php echo nl2br( esc_html( $med->current_medication ) ); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ( $med->medical_training_needs ) : ?>
                    <div class="was-medical-row was-medical-row--alert">
                        <span class="was-medical-label">Special training needed</span>
                        <span class="was-medical-value"><?php echo nl2br( esc_html( $med->medical_training_needs ) ); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ( $med->access_needs ) : ?>
                    <div class="was-medical-row">
                        <span class="was-medical-label">Access needs</span>
                        <span class="was-medical-value"><?php echo nl2br( esc_html( $med->access_needs ) ); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ( $med->communication_needs ) : ?>
                    <div class="was-medical-row">
                        <span class="was-medical-label">Communication needs</span>
                        <span class="was-medical-value"><?php echo nl2br( esc_html( $med->communication_needs ) ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else : ?>
                <p class="was-no-medical">No medical information recorded for this player.</p>
                <?php endif; ?>

            </div><!-- .was-coach-player__detail -->
        </details>
        <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

</div>
