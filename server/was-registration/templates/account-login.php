<?php defined( 'ABSPATH' ) || exit; ?>
<div class="was-wrap was-account-wrap">

    <div class="was-account-card">

        <div class="astro-login-logo-wrap">
            <img src="<?php echo esc_url( was_portal_logo_dark_url() ); ?>" class="astro-account-logo" alt="ASTRO">
        </div>

        <?php if ( isset( $verify_notice ) && $verify_notice === 'ok' ) : ?>
        <div class="was-alert was-alert--success">Thanks &mdash; your email address is confirmed. Please sign in to continue.</div>
        <?php elseif ( isset( $verify_notice ) && $verify_notice === 'invalid' ) : ?>
        <div class="was-alert was-alert--error">That confirmation link has expired or has already been used. Sign in and we&rsquo;ll send you a new one.</div>
        <?php endif; ?>

        <div id="was-login-error" class="was-alert was-alert--error" style="display:none;"></div>

        <div class="was-field">
            <label for="was-login-email" class="was-label was-label--required">Email address</label>
            <input type="email" id="was-login-email" class="was-input" autocomplete="email" placeholder="your@email.com">
        </div>

        <div class="was-field">
            <label for="was-login-password" class="was-label was-label--required">Password</label>
            <div class="was-input-wrap">
                <input type="password" id="was-login-password" class="was-input" autocomplete="current-password">
                <button type="button" class="was-input-toggle" data-target="was-login-password" aria-label="Show password">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <div class="was-field was-field--inline">
            <label class="was-checkbox-label">
                <input type="checkbox" id="was-login-remember">
                <span>Keep me signed in</span>
            </label>
            <a href="<?php echo esc_url( home_url( '/account-reset/' ) ); ?>" class="was-link">Forgot password?</a>
        </div>

        <input type="hidden" id="was-login-redirect" value="<?php echo esc_attr( $redirect ); ?>">

        <button type="button" id="was-login-btn" class="was-btn was-btn--primary was-btn--full">
            <span class="was-btn-label">Sign in</span>
            <span class="was-btn-spinner" style="display:none;">Signing in&hellip;</span>
        </button>

        <div class="was-account-or"><span>New to Wombourne Allstars?</span></div>

        <a href="<?php echo esc_url( home_url( '/account-register/' ) ); ?>" class="was-btn was-btn--secondary was-btn--full was-btn--create">
            Create an account
        </a>

        <p class="was-account-switch">One account covers your whole family &mdash; you only need to do this once.</p>

    </div>

</div>
