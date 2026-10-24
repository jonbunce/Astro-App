<?php defined( 'ABSPATH' ) || exit;
$occ_cats = WAS_Accounts::occupation_categories();
?>
<div class="was-wrap was-account-wrap">
    <div class="was-account-card was-account-card--wide">

        <div class="astro-login-logo-wrap">
            <img src="<?php echo esc_url( was_portal_logo_dark_url() ); ?>" class="astro-account-logo" alt="ASTRO">
        </div>

        <p class="was-account-intro">Create your account to register your child and manage your family&rsquo;s membership. You only need one account even if you have multiple children in the club.</p>

        <div id="was-reg-error"   class="was-alert was-alert--error"   style="display:none;"></div>
        <div id="was-reg-success" class="was-alert was-alert--success" style="display:none;"></div>

        <?php /* ── Account details ──────────────────────────────────────────── */ ?>
        <h3 class="was-account-section-heading">Your account</h3>

        <div class="was-field-row">
            <div class="was-field">
                <label for="was-reg-first" class="was-label was-label--required">First name</label>
                <input type="text" id="was-reg-first" class="was-input" autocomplete="given-name">
            </div>
            <div class="was-field">
                <label for="was-reg-last" class="was-label was-label--required">Last name</label>
                <input type="text" id="was-reg-last" class="was-input" autocomplete="family-name">
            </div>
        </div>

        <div class="was-field">
            <label for="was-reg-email" class="was-label was-label--required">Email address</label>
            <input type="email" id="was-reg-email" class="was-input" autocomplete="email" placeholder="your@email.com">
        </div>

        <div class="was-field">
            <label for="was-reg-password" class="was-label was-label--required">Password</label>
            <div class="was-input-wrap">
                <input type="password" id="was-reg-password" class="was-input" autocomplete="new-password" placeholder="Minimum 8 characters">
                <button type="button" class="was-input-toggle" data-target="was-reg-password" aria-label="Show password">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <div class="was-password-strength" id="was-pw-strength" style="display:none;">
                <div class="was-pw-bar"><div class="was-pw-fill" id="was-pw-fill"></div></div>
                <span id="was-pw-label"></span>
            </div>
        </div>

        <div class="was-field">
            <label for="was-reg-confirm" class="was-label was-label--required">Confirm password</label>
            <div class="was-input-wrap">
                <input type="password" id="was-reg-confirm" class="was-input" autocomplete="new-password">
                <button type="button" class="was-input-toggle" data-target="was-reg-confirm" aria-label="Show password">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <?php /* ── Contact details ───────────────────────────────────────────── */ ?>
        <h3 class="was-account-section-heading">Contact details</h3>

        <div class="was-info-box" style="margin-bottom:1rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.7 2.34a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.74-1.28a2 2 0 0 1 2.11-.45c.74.34 1.53.57 2.34.7A2 2 0 0 1 22 16.92z"/></svg>
            <div>
                <strong>You&rsquo;ll be the first emergency contact</strong>
                <p>The name and number on this account are used as the <strong>first point of contact in an emergency</strong>. When you register each child you&rsquo;ll be able to add a <strong>second, alternative contact</strong> as well &mdash; useful if a different parent or carer should be reached (for example in blended families).</p>
            </div>
        </div>

        <div class="was-field">
            <label for="was-reg-tel" class="was-label was-label--required">Telephone number</label>
            <input type="tel" id="was-reg-tel" class="was-input" autocomplete="tel" placeholder="Used as the first emergency contact number">
        </div>

        <div class="was-field">
            <label for="was-reg-address" class="was-label was-label--required">First line of home address</label>
            <input type="text" id="was-reg-address" class="was-input" autocomplete="address-line1">
        </div>

        <div class="was-field was-field--half">
            <label for="was-reg-postcode" class="was-label was-label--required">Postcode</label>
            <input type="text" id="was-reg-postcode" class="was-input" autocomplete="postal-code" style="text-transform:uppercase;">
        </div>

        <?php /* ── Occupation ───────────────────────────────────────────────── */ ?>
        <h3 class="was-account-section-heading">Occupation <span class="was-heading-optional">(FA Inclusion Data)</span></h3>

        <div class="was-info-box" style="margin-bottom:1rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <p>The FA advises all accredited clubs to provide high-level data on the backgrounds of our member families. This helps the national game stay inclusive. Your response is confidential, stored securely under GDPR guidelines, and will never be shared with third parties or employers. If you&rsquo;d prefer not to say, please select &ldquo;Decline to state&rdquo;.</p>
            </div>
        </div>

        <div class="was-field">
            <label for="was-reg-occ" class="was-label was-label--required">Occupation category</label>
            <select id="was-reg-occ" class="was-select">
                <?php foreach ( $occ_cats as $val => $label ) : ?>
                <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="was-field">
            <label for="was-reg-employer" class="was-label">
                Employer / business name
                <span class="was-label-hint">Optional, but appreciated — especially if you&rsquo;re local or run your own business. We&rsquo;re always keen to explore sponsorship opportunities that work for both sides.</span>
            </label>
            <input type="text" id="was-reg-employer" class="was-input" placeholder="e.g. Smith Plumbing, Wolverhampton" autocomplete="organization">
        </div>

        <button type="button" id="was-create-account-btn" class="was-btn was-btn--primary was-btn--full">
            <span class="was-btn-label">Create account &amp; continue</span>
            <span class="was-btn-spinner" style="display:none;">Creating account&hellip;</span>
        </button>

        <p class="was-account-switch">Already have an account? <a href="<?php echo esc_url( home_url( '/account-login/' ) ); ?>" class="was-link">Sign in</a></p>

    </div>
</div>
