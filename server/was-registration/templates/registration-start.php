<?php defined( 'ABSPATH' ) || exit; ?>
<div class="was-wrap">
    <div style="text-align:center;margin-bottom:1.5rem;">
        <img src="<?php echo esc_url( was_portal_logo_dark_url() ); ?>" class="astro-account-logo" alt="ASTRO Portal">
    </div>
    <h2 class="was-heading">Player Registration &mdash; <?php echo esc_html( was_season() ); ?></h2>

    <?php echo was_steps_html( 1 ); ?>

    <?php if ( ! empty( $resume ) ) :
        $r_team   = $resume['team'] ? $resume['team']->name : 'your child\'s team';
        $r_signed = ( $resume['player']->participation_type === 'signed' );
    ?>
    <div class="was-resume-banner">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>
        <div class="was-resume-banner__body">
            <strong>Pick up where you left off</strong>
            <p>
                You started registering for <strong><?php echo esc_html( $r_team ); ?></strong> but haven&rsquo;t finished yet.
                <?php if ( $r_signed ) : ?>Your Direct Debit is already set up &mdash; you just need to add your child&rsquo;s details to complete it. There&rsquo;s no need to set up the Direct Debit again.<?php else : ?>You just need to add your child&rsquo;s details to complete it.<?php endif; ?>
            </p>
            <a href="<?php echo esc_url( $resume['url'] ); ?>" class="was-btn was-btn--primary was-resume-banner__btn">Resume registration &rarr;</a>
        </div>
    </div>
    <p class="was-resume-or">Registering a different child? Start a new registration below.</p>
    <?php endif; ?>

    <div class="was-card was-start-card">
        <p class="was-intro">Complete this form to register your child with <?php echo esc_html( get_bloginfo('name') ); ?> for the <?php echo esc_html( was_season() ); ?> season. If you have more than one child in the club, please complete a separate registration for each.</p>

        <div class="was-field">
            <label for="was-team-select" class="was-label was-label--required">Your child&rsquo;s team</label>
            <select id="was-team-select" class="was-select">
                <option value="">— Please select —</option>
                <?php foreach ( $teams as $team ) : ?>
                <option value="<?php echo esc_attr( $team->id ); ?>"
                        data-subs="<?php echo esc_attr( $team->subs_amount ); ?>"
                        data-sibling="<?php echo esc_attr( $team->sibling_amount ); ?>">
                    <?php echo esc_html( $team->name ); ?>
                    <?php if ( $team->age_group ) : ?>(<?php echo esc_html( $team->age_group ); ?>)<?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="was-field">
            <label class="was-label was-label--required">Participation type</label>
            <div class="was-radio-group">
                <label class="was-radio-card" id="was-type-signed-card">
                    <input type="radio" name="participation_type" value="signed" class="was-radio" checked>
                    <div class="was-radio-card__inner">
                        <strong>Signed Player</strong>
                        <span>Registered with the FA. Monthly subscription collected by Direct Debit via GoCardless.</span>
                        <span class="was-subs-amount" id="was-subs-label"></span>
                    </div>
                </label>
                <label class="was-radio-card" id="was-type-train-card">
                    <input type="radio" name="participation_type" value="train_only" class="was-radio">
                    <div class="was-radio-card__inner">
                        <strong>Train Only</strong>
                        <span>Training sessions only. Subscription paid directly to the team &mdash; not collected by the club.</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="was-info-box was-gc-notice" id="was-gc-notice">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <strong>Direct Debit setup</strong>
                <p>Clicking Continue will take you to GoCardless to set up your Direct Debit mandate. Once complete, you&rsquo;ll be returned here to fill in your child&rsquo;s details. Your bank details are handled securely by GoCardless &mdash; we never see them.</p>
                <?php if ( was_first_payment_date() ) : ?>
                <p style="margin-top:8px;font-weight:700;color:#0F6E56;">Good news &mdash; if you sign up now, your first payment won&rsquo;t be collected until <?php echo esc_html( date_i18n( 'j F Y', strtotime( was_first_payment_date() ) ) ); ?>. Nothing is taken from your account before then.</p>
                <?php else : ?>
                <p style="margin-top:8px;">Subs are collected on the 1st of each month. If you join part-way through a month, you&rsquo;ll pay a one-off part-month amount for the days up to your first 1st-of-the-month collection.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sibling rate notice — shown automatically if siblings detected -->
        <div id="was-sibling-section" style="display:none;">
            <div id="was-sibling-match" class="was-sibling-notice was-sibling-notice--match" style="display:none;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <div>
                    <strong id="was-sibling-match-title"></strong>
                    <p id="was-sibling-match-detail"></p>
                </div>
            </div>
        </div>

        <div id="was-start-error" class="was-alert was-alert--error" style="display:none;"></div>

        <button type="button" id="was-proceed-btn" class="was-btn was-btn--primary">
            <span class="was-btn-label-signed">Continue to Direct Debit setup &rarr;</span>
            <span class="was-btn-label-train" style="display:none;">Continue to registration form &rarr;</span>
            <span class="was-btn-spinner" style="display:none;">Please wait&hellip;</span>
        </button>
    </div>
</div>

<script>
/* Dim the "Direct Debit" step when Train Only is chosen (it isn't collected by the club). */
(function () {
    function update() {
        var picked = document.querySelector('input[name="participation_type"]:checked');
        var ddStep = document.querySelector('.was-steps .was-step--dd');
        if (!picked || !ddStep) return;
        ddStep.classList.toggle('is-skipped', picked.value === 'train_only');
    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'participation_type') update();
    });
    update();
})();
</script>
