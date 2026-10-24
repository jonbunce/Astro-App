<?php defined( 'ABSPATH' ) || exit;
/* Rendered by WAS_Accounts::render_password_reset() when no valid reset key is present.
   $reset_expired (bool) may be set if the user arrived via an expired/invalid link. */
?>
<div class="was-wrap was-account-wrap">
    <div class="was-account-card">

        <div class="astro-login-logo-wrap">
            <img src="<?php echo esc_url( was_portal_logo_dark_url() ); ?>" class="astro-account-logo" alt="ASTRO">
        </div>

        <h2 class="was-account-title" style="text-align:center;font-size:1.4rem;font-weight:800;color:#14161a;margin:0 0 .3rem;">Reset your password</h2>
        <p class="was-account-intro">Enter the email address you registered with and we&rsquo;ll send you a secure link to set a new password.</p>

        <?php if ( ! empty( $reset_expired ) ) : ?>
        <div class="was-alert was-alert--error" style="display:block;">That reset link has expired or already been used. Please request a new one below.</div>
        <?php endif; ?>

        <div id="was-forgot-error"   class="was-alert was-alert--error"   style="display:none;"></div>
        <div id="was-forgot-success" class="was-alert was-alert--success" style="display:none;"></div>

        <div id="was-forgot-fields">
            <div class="was-field">
                <label for="was-forgot-email" class="was-label was-label--required">Email address</label>
                <input type="email" id="was-forgot-email" class="was-input" autocomplete="email" placeholder="your@email.com">
            </div>

            <button type="button" id="was-forgot-btn" class="was-btn was-btn--primary was-btn--full">
                <span class="was-btn-label">Send reset link</span>
                <span class="was-btn-spinner" style="display:none;">Sending&hellip;</span>
            </button>
        </div>

        <p class="was-account-switch"><a href="<?php echo esc_url( home_url( '/account-login/' ) ); ?>" class="was-link">&larr; Back to sign in</a></p>

    </div>
</div>
