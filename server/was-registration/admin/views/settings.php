<?php defined( 'ABSPATH' ) || exit;
$notice = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_settings_nonce'] ) ) {
    check_admin_referer( 'was_save_settings', 'was_settings_nonce' );
    update_option( 'was_gc_sandbox',                (int)  ( $_POST['gc_sandbox']                ?? 1 ) );
    update_option( 'was_gc_sandbox_api_key',        sanitize_text_field( wp_unslash( $_POST['gc_sandbox_api_key']        ?? '' ) ) );
    update_option( 'was_gc_sandbox_webhook_secret', sanitize_text_field( wp_unslash( $_POST['gc_sandbox_webhook_secret'] ?? '' ) ) );
    update_option( 'was_gc_live_api_key',           sanitize_text_field( wp_unslash( $_POST['gc_live_api_key']           ?? '' ) ) );
    update_option( 'was_gc_live_webhook_secret',    sanitize_text_field( wp_unslash( $_POST['gc_live_webhook_secret']    ?? '' ) ) );
    update_option( 'was_current_season',            sanitize_text_field( $_POST['current_season']            ?? '' ) );
    update_option( 'was_season_start_date',         sanitize_text_field( $_POST['season_start_date']         ?? '' ) );
    update_option( 'was_admin_email',               sanitize_email( $_POST['admin_email']               ?? '' ) );
    update_option( 'was_safeguarding_email',       sanitize_email( $_POST['safeguarding_email']       ?? '' ) );
    update_option( 'was_support_email',            sanitize_email( $_POST['support_email']            ?? '' ) );
    update_option( 'was_portal_logo_url',          esc_url_raw(    $_POST['portal_logo_url']          ?? '' ) );
    update_option( 'was_portal_logo_dark_url',     esc_url_raw(    $_POST['portal_logo_dark_url']     ?? '' ) );
    update_option( 'was_fb_page_url',              esc_url_raw(    $_POST['fb_page_url']              ?? '' ) );
    update_option( 'was_fb_api_enabled',           (int)  ( $_POST['fb_api_enabled'] ?? 0 ) );
    update_option( 'was_fb_page_id',               sanitize_text_field( wp_unslash( $_POST['fb_page_id']    ?? '' ) ) );
    update_option( 'was_fb_page_token',            sanitize_text_field( wp_unslash( $_POST['fb_page_token'] ?? '' ) ) );
    $notice = 'Settings saved.';
}

$sandbox    = (int) get_option( 'was_gc_sandbox', 1 );
$sb_key     = esc_attr( wp_unslash( get_option( 'was_gc_sandbox_api_key',        '' ) ) );
$sb_secret  = esc_attr( wp_unslash( get_option( 'was_gc_sandbox_webhook_secret', '' ) ) );
$live_key   = esc_attr( wp_unslash( get_option( 'was_gc_live_api_key',           '' ) ) );
$live_secret= esc_attr( wp_unslash( get_option( 'was_gc_live_webhook_secret',    '' ) ) );
$season     = esc_attr( get_option( 'was_current_season', '2024-25' ) );
$season_start = esc_attr( get_option( 'was_season_start_date', '' ) );
$adm_email          = esc_attr( get_option( 'was_admin_email', get_option('admin_email') ) );
$safeguarding_email = esc_attr( get_option( 'was_safeguarding_email', '' ) );
$support_email      = esc_attr( get_option( 'was_support_email', 'jon@wombourneallstars.co.uk' ) );
$portal_logo_url    = esc_attr( get_option( 'was_portal_logo_url', '' ) );
$portal_logo_dark_url = esc_attr( get_option( 'was_portal_logo_dark_url', '' ) );
$fb_page_url        = esc_attr( get_option( 'was_fb_page_url', '' ) );
$fb_api_enabled     = (int) get_option( 'was_fb_api_enabled', 0 );
$fb_page_id         = esc_attr( wp_unslash( get_option( 'was_fb_page_id', '' ) ) );
$fb_page_token      = esc_attr( wp_unslash( get_option( 'was_fb_page_token', '' ) ) );
?>
<div class="wrap was-admin-wrap">
    <h1>Registration Settings</h1>

    <?php if ($notice): ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'was_save_settings', 'was_settings_nonce' ); ?>

        <h2>Season</h2>
        <table class="form-table">
            <tr>
                <th><label for="current_season">Current Season</label></th>
                <td>
                    <input type="text" id="current_season" name="current_season" class="small-text" value="<?php echo $season; ?>">
                    <p class="description">e.g. 2024-25. Applied to all new registrations and payment records.</p>
                </td>
            </tr>
            <tr>
                <th><label for="season_start_date">First payment date</label></th>
                <td>
                    <input type="date" id="season_start_date" name="season_start_date" value="<?php echo $season_start; ?>">
                    <?php if ( $season_start && strtotime( $season_start ) ) : ?>
                    <p class="description" style="margin-top:6px;font-weight:600;color:#0F6E56;">
                        &#10148; First payment will be collected on <?php echo esc_html( date_i18n( 'l j F Y', strtotime( $season_start ) ) ); ?>
                        <span style="color:#666;font-weight:400;">(stored as <?php echo esc_html( $season_start ); ?> &mdash; day is always the 1st here, never month-first)</span>
                    </p>
                    <?php endif; ?>
                    <p class="description">Optional. If set to a <strong>future</strong> date, new signed registrations set up the Direct Debit straight away but the <strong>first collection is deferred to this date</strong> (monthly thereafter). Parents who sign up on or after this date are charged on GoCardless&rsquo;s normal schedule. Leave blank (or clear it once the season has started) to collect immediately. Tip: the picker may <em>display</em> US format (mm/dd/yyyy) depending on your browser, but the date is stored unambiguously &mdash; the confirmation line above shows exactly what will be used.</p>
                </td>
            </tr>
        </table>

        <h2>GoCardless Environment</h2>
        <table class="form-table">
            <tr>
                <th>Active environment</th>
                <td>
                    <label style="margin-right:20px;">
                        <input type="radio" name="gc_sandbox" value="1" <?php checked($sandbox,1); ?>>
                        <strong>Sandbox</strong> &mdash; testing only, no real payments
                    </label>
                    <label>
                        <input type="radio" name="gc_sandbox" value="0" <?php checked($sandbox,0); ?>>
                        <strong>Live</strong> &mdash; real Direct Debits
                    </label>
                    <p class="description" style="color:<?php echo $sandbox ? '#2e7d32' : '#c62828'; ?>; font-weight:600;">
                        Currently using: <?php echo $sandbox ? 'Sandbox (testing)' : 'LIVE'; ?>
                    </p>
                </td>
            </tr>
        </table>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; margin-top:1rem;">

            <div style="border:2px solid #9FE1CB; border-radius:8px; padding:1.5rem; background:#E1F5EE;">
                <h3 style="margin:0 0 1rem; color:#0F6E56;">&#x1F9EA; Sandbox Credentials</h3>
                <p class="description" style="margin-bottom:1rem;">For testing. Log in at <a href="https://manage-sandbox.gocardless.com" target="_blank">manage-sandbox.gocardless.com</a> to get these.</p>
                <table class="form-table" style="background:transparent;">
                    <tr>
                        <th style="width:160px;"><label for="gc_sandbox_api_key">Access Token</label></th>
                        <td><input type="text" id="gc_sandbox_api_key" name="gc_sandbox_api_key" class="regular-text" value="<?php echo $sb_key; ?>" autocomplete="off" placeholder="sandbox_..."></td>
                    </tr>
                    <tr>
                        <th><label for="gc_sandbox_webhook_secret">Webhook Secret</label></th>
                        <td><input type="text" id="gc_sandbox_webhook_secret" name="gc_sandbox_webhook_secret" class="regular-text" value="<?php echo $sb_secret; ?>" autocomplete="off"></td>
                    </tr>
                </table>
            </div>

            <div style="border:2px solid <?php echo $sandbox ? '#dde1e7' : '#f5c6c2'; ?>; border-radius:8px; padding:1.5rem; background:<?php echo $sandbox ? '#f7f8fa' : '#fdecea'; ?>;">
                <h3 style="margin:0 0 1rem; color:<?php echo $sandbox ? '#1a3a5c' : '#c62828'; ?>;">&#x1F4B3; Live Credentials <?php echo $sandbox ? '' : '&mdash; ACTIVE'; ?></h3>
                <p class="description" style="margin-bottom:1rem;">For real payments. Log in at <a href="https://manage.gocardless.com" target="_blank">manage.gocardless.com</a> to get these.</p>
                <table class="form-table" style="background:transparent;">
                    <tr>
                        <th style="width:160px;"><label for="gc_live_api_key">Access Token</label></th>
                        <td><input type="text" id="gc_live_api_key" name="gc_live_api_key" class="regular-text" value="<?php echo $live_key; ?>" autocomplete="off" placeholder="live_..."></td>
                    </tr>
                    <tr>
                        <th><label for="gc_live_webhook_secret">Webhook Secret</label></th>
                        <td><input type="text" id="gc_live_webhook_secret" name="gc_live_webhook_secret" class="regular-text" value="<?php echo $live_secret; ?>" autocomplete="off"></td>
                    </tr>
                </table>
            </div>

        </div>

        <h2 style="margin-top:2rem;">Webhook URL</h2>
        <table class="form-table">
            <tr>
                <th>Endpoint</th>
                <td>
                    <code><?php echo esc_url( rest_url('was/v1/webhook/gocardless') ); ?></code>
                    <p class="description">Add this in GoCardless (Developers &rarr; Webhooks) for both sandbox and live environments. Each environment has its own webhook secret.</p>
                </td>
            </tr>
        </table>

        <h2>Notifications</h2>
        <table class="form-table">
            <tr>
                <th><label for="admin_email">Admin / Treasurer Email</label></th>
                <td>
                    <input type="email" id="admin_email" name="admin_email" class="regular-text" value="<?php echo $adm_email; ?>">
                    <p class="description">Registration confirmations and payment failure alerts are sent here.</p>
                </td>
            </tr>
            <tr>
                <th><label for="safeguarding_email">Safeguarding Email</label></th>
                <td>
                    <input type="email" id="safeguarding_email" name="safeguarding_email" class="regular-text" value="<?php echo $safeguarding_email; ?>">
                    <p class="description">Notified immediately when a parent changes consent settings. Leave blank to disable.</p>
                </td>
            </tr>
            <tr>
                <th><label for="support_email">Support / Contact Email</label></th>
                <td>
                    <input type="email" id="support_email" name="support_email" class="regular-text" value="<?php echo $support_email; ?>">
                    <p class="description">Shown as a support contact link in the ASTRO portal header.</p>
                </td>
            </tr>
            <tr>
                <th><label>Portal Logo</label></th>
                <td>
                    <?php if ( $portal_logo_url ) : ?>
                    <div style="margin-bottom:8px;background:#1a3a5c;padding:8px;border-radius:6px;display:inline-block;">
                        <img src="<?php echo $portal_logo_url; ?>" style="max-height:50px;display:block;">
                    </div><br>
                    <small>Upload a new image to replace.</small><br>
                    <?php else : ?>
                    <div style="margin-bottom:8px;background:#f0f4ff;padding:8px;border-radius:6px;display:inline-block;">
                        <img src="<?php echo esc_url( WAS_URL . 'assets/img/astro-logo-default.gif' ); ?>" style="max-height:50px;display:block;">
                    </div><br>
                    <small>Currently using the default ASTRO logo.</small><br>
                    <?php endif; ?>
                    <input type="url" name="portal_logo_url" id="portal_logo_url" class="regular-text" value="<?php echo $portal_logo_url; ?>" placeholder="https://...">
                    <button type="button" class="button" id="was-logo-picker">Choose from Media Library</button>
                    <p class="description">Upload your logo to the Media Library and paste or select the URL here. Recommended: PNG or GIF with white or transparent background, landscape format.</p>
                </td>
            </tr>
            <tr>
                <th><label>Portal Logo (dark version)</label></th>
                <td>
                    <div style="margin-bottom:8px;background:#f0f4ff;padding:8px;border-radius:6px;display:inline-block;">
                        <img src="<?php echo $portal_logo_dark_url ? $portal_logo_dark_url : esc_url( WAS_URL . 'assets/img/astro-logo-dark.png' ); ?>" style="max-height:50px;display:block;">
                    </div><br>
                    <input type="url" name="portal_logo_dark_url" id="portal_logo_dark_url" class="regular-text" value="<?php echo $portal_logo_dark_url; ?>" placeholder="https://...">
                    <p class="description">Shown on light backgrounds (sign-in, registration and interest forms). Leave blank to use the bundled dark ASTRO wordmark.</p>
                </td>
            </tr>
        </table>

        <h2>Facebook (Team News)</h2>
        <table class="form-table">
            <tr>
                <th><label for="fb_page_url">Club Facebook page URL</label></th>
                <td>
                    <input type="url" id="fb_page_url" name="fb_page_url" class="regular-text" value="<?php echo $fb_page_url; ?>" placeholder="https://www.facebook.com/yourclubpage">
                    <p class="description">Used for the &ldquo;Open Facebook page&rdquo; button in the portal&rsquo;s Team News review section (manual posting flow).</p>
                </td>
            </tr>
            <tr>
                <th>API posting</th>
                <td>
                    <label>
                        <input type="checkbox" name="fb_api_enabled" value="1" <?php checked( $fb_api_enabled, 1 ); ?>>
                        Enable one-click &ldquo;Push to Facebook&rdquo; via the Graph API
                    </label>
                    <p class="description">Leave off until the Page ID and access token below are set up. While off, reviewers use the manual flow (copy text + download photos).</p>
                </td>
            </tr>
            <tr>
                <th><label for="fb_page_id">Facebook Page ID</label></th>
                <td>
                    <input type="text" id="fb_page_id" name="fb_page_id" class="regular-text" value="<?php echo $fb_page_id; ?>" autocomplete="off">
                    <p class="description">The numeric ID of the club&rsquo;s Facebook Page.</p>
                </td>
            </tr>
            <tr>
                <th><label for="fb_page_token">Page Access Token</label></th>
                <td>
                    <input type="password" id="fb_page_token" name="fb_page_token" class="regular-text" value="<?php echo $fb_page_token; ?>" autocomplete="off">
                    <p class="description">A <strong>long-lived Page access token</strong> with the <code>pages_manage_posts</code> and <code>pages_read_engagement</code> permissions, generated from a Facebook App by an admin of the club page (e.g. via the Graph API Explorer). Keep this secret.</p>
                </td>
            </tr>
        </table>

        <?php submit_button('Save Settings'); ?>
    </form>
</div>
