<?php defined( 'ABSPATH' ) || exit; ?>
<div class="was-wrap">
    <div class="was-card" style="max-width:600px; margin:2rem auto; text-align:center; padding:2.5rem 2rem;">
        <div style="color:#e8a020; margin-bottom:1.25rem;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>
        <h2 class="was-heading" style="margin-bottom:0.75rem;">Complete your profile first</h2>
        <p style="color:var(--was-text-muted); line-height:1.6; margin-bottom:1.5rem;">
            Before registering a child, we need your contact details on file. Please update your account profile — it only takes a moment.
        </p>
        <a href="<?php echo esc_url( home_url( '/account-portal/' ) ); ?>#was-section-profile" class="was-btn was-btn--primary">
            Update my profile &rarr;
        </a>
        <p style="margin-top:1rem; font-size:0.85rem; color:var(--was-text-muted);">
            You&rsquo;ll need to provide: name, telephone number, home address and postcode.
        </p>
    </div>
</div>
