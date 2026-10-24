<?php defined( 'ABSPATH' ) || exit;
/* Rendered by WAS_Accounts::render_password_reset() when a valid reset key is present.
   $reset_key and $reset_login are provided by the caller. */
?>
<div class="was-wrap was-account-wrap">
    <div class="was-account-card">

        <div class="astro-login-logo-wrap">
            <img src="<?php echo esc_url( was_portal_logo_dark_url() ); ?>" class="astro-account-logo" alt="ASTRO">
        </div>

        <h2 class="was-account-title" style="text-align:center;font-size:1.4rem;font-weight:800;color:#14161a;margin:0 0 .3rem;">Choose a new password</h2>
        <p class="was-account-intro">Almost done &mdash; set a new password for your ASTRO account and we&rsquo;ll sign you straight in.</p>

        <div id="was-reset-error"   class="was-alert was-alert--error"   style="display:none;"></div>
        <div id="was-reset-success" class="was-alert was-alert--success" style="display:none;"></div>

        <input type="hidden" id="was-reset-key"   value="<?php echo esc_attr( $reset_key ); ?>">
        <input type="hidden" id="was-reset-login" value="<?php echo esc_attr( $reset_login ); ?>">

        <div class="was-field">
            <label for="was-reset-password" class="was-label was-label--required">New password</label>
            <div class="was-input-wrap">
                <input type="password" id="was-reset-password" class="was-input" autocomplete="new-password" placeholder="Minimum 8 characters">
                <button type="button" class="was-input-toggle" data-target="was-reset-password" aria-label="Show password">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <div class="was-password-strength" id="was-reset-pw-strength" style="display:none;">
                <div class="was-pw-bar"><div class="was-pw-fill" id="was-reset-pw-fill"></div></div>
                <span id="was-reset-pw-label"></span>
            </div>
        </div>

        <div class="was-field">
            <label for="was-reset-confirm" class="was-label was-label--required">Confirm new password</label>
            <div class="was-input-wrap">
                <input type="password" id="was-reset-confirm" class="was-input" autocomplete="new-password">
                <button type="button" class="was-input-toggle" data-target="was-reset-confirm" aria-label="Show password">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <button type="button" id="was-reset-btn" class="was-btn was-btn--primary was-btn--full">
            <span class="was-btn-label">Set new password &amp; sign in</span>
            <span class="was-btn-spinner" style="display:none;">Saving&hellip;</span>
        </button>

        <p class="was-account-switch"><a href="<?php echo esc_url( home_url( '/account-login/' ) ); ?>" class="was-link">&larr; Back to sign in</a></p>

    </div>
</div>
