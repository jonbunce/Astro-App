<?php defined( 'ABSPATH' ) || exit;
$portal_url = home_url( '/account-portal/' );
?>
<div class="was-wrap">
    <div class="was-card was-card--success" style="text-align:center;max-width:580px;margin:2rem auto;">
        <div class="was-success-icon">
            <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>
        </div>
        <h2 class="was-heading">Registration Complete! 🚀</h2>

        <?php echo was_steps_html( 'done' ); ?>

        <p>Thank you &mdash; your child&rsquo;s registration has been received for the <?php echo esc_html( was_season() ); ?> season.</p>
        <p>A confirmation email has been sent to you. If you don&rsquo;t receive it within a few minutes, please check your junk folder or contact the club.</p>
        <p>You can update your child&rsquo;s details, medical information and contact information at any time from your ASTRO Portal.</p>

        <?php
        $late = get_transient( 'was_late_signup_' . get_current_user_id() );
        if ( $late ) {
            delete_transient( 'was_late_signup_' . get_current_user_id() );
            $late_amt  = '£' . number_format( (float) ( $late['amount'] ?? 0 ), 2 );
            $late_subd = ! empty( $late['sub_start'] )    ? date_i18n( 'j F Y', strtotime( $late['sub_start'] ) )    : '';
            $late_offd = ! empty( $late['one_off_date'] ) ? date_i18n( 'j F Y', strtotime( $late['one_off_date'] ) ) : '';
            ?>
            <div class="was-info-box" style="text-align:left;margin:1.25rem 0;border-left:4px solid #C8102E;background:#fff5f6;">
                <strong>Your payment schedule</strong>
                <?php if ( ! empty( $late['prorata'] ) ) :
                    $pro_amt  = '£' . number_format( (float) ( $late['one_off_amount'] ?? 0 ), 2 );
                    $pro_from = ! empty( $late['prorata_from'] ) ? date_i18n( 'j F', strtotime( $late['prorata_from'] ) ) : '';
                    $pro_to   = ! empty( $late['sub_start'] ) ? date_i18n( 'j F', strtotime( $late['sub_start'] . ' -1 day' ) ) : '';
                    $pro_span = ( $pro_from && $pro_to ) ? ' (' . $pro_from . ' to ' . $pro_to . ')' : '';
                    ?>
                <p style="margin:.4rem 0 0;">Subs are collected on the 1st of each month. As you&rsquo;ve joined part-way through the month:</p>
                <ul style="margin:.4rem 0 0;padding-left:1.2rem;">
                    <?php if ( ! empty( $late['one_off_waived'] ) ) : ?>
                    <li>There is nothing to pay for the rest of this month.</li>
                    <?php elseif ( $late_offd ) : ?>
                    <li>A one-off part-month payment of <strong><?php echo esc_html( $pro_amt ); ?></strong><?php echo esc_html( $pro_span ); ?> will be collected on <strong><?php echo esc_html( $late_offd ); ?></strong>.</li>
                    <?php else : ?>
                    <li>The club will be in touch about a part-month payment of <strong><?php echo esc_html( $pro_amt ); ?></strong><?php echo esc_html( $pro_span ); ?>.</li>
                    <?php endif; ?>
                    <li>Your monthly Direct Debit of <strong><?php echo esc_html( $late_amt ); ?></strong> then starts on <strong><?php echo esc_html( $late_subd ); ?></strong>.</li>
                </ul>
                <p style="margin:.5rem 0 0;">Nothing else is needed. We&rsquo;ve emailed these details to you too.</p>
                <?php elseif ( $late_offd ) : ?>
                <p style="margin:.4rem 0 0;">As you signed up after this month&rsquo;s Direct Debit cut-off:</p>
                <ul style="margin:.4rem 0 0;padding-left:1.2rem;">
                    <li>A one-off first payment of <strong><?php echo esc_html( $late_amt ); ?></strong> will be collected on <strong><?php echo esc_html( $late_offd ); ?></strong>.</li>
                    <li>Your monthly Direct Debit of <strong><?php echo esc_html( $late_amt ); ?></strong> then starts on <strong><?php echo esc_html( $late_subd ); ?></strong>.</li>
                </ul>
                <p style="margin:.5rem 0 0;">This keeps you fully up to date &mdash; nothing else is needed. We&rsquo;ve emailed these details to you too.</p>
                <?php else : ?>
                <p style="margin:.4rem 0 0;">Your monthly Direct Debit of <strong><?php echo esc_html( $late_amt ); ?></strong> will start on <strong><?php echo esc_html( $late_subd ); ?></strong>. The club will be in touch about this month&rsquo;s payment.</p>
                <?php endif; ?>
            </div>
            <?php
        }
        ?>

        <div class="was-complete-redirect">
            <p class="was-redirect-msg">Taking you to your portal in <strong id="was-countdown">8</strong> seconds&hellip;</p>
            <div class="was-redirect-bar-wrap">
                <div class="was-redirect-bar" id="was-redirect-bar"></div>
            </div>
        </div>

        <div style="display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap;margin-top:1.5rem;">
            <a href="<?php echo esc_url( $portal_url ); ?>" class="was-btn was-btn--primary" id="was-portal-link">
                Go to ASTRO Portal now &rarr;
            </a>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="was-btn was-btn--outline">
                Return to website
            </a>
        </div>
    </div>
</div>

<style>
.was-complete-redirect { margin: 1.25rem 0 0.5rem; }
.was-redirect-msg { font-size: 0.88rem; color: #666; margin-bottom: 0.5rem; }
.was-redirect-bar-wrap { background: #eee; border-radius: 4px; height: 4px; overflow: hidden; max-width: 300px; margin: 0 auto; }
.was-redirect-bar { height: 4px; background: var(--was-accent-grad, #14161a); width: 100%; transform-origin: left; animation: was-drain 8s linear forwards; }
@keyframes was-drain { from { width: 100%; } to { width: 0%; } }
</style>

<script>
(function() {
    var secs = 8;
    var el   = document.getElementById('was-countdown');
    var dest = <?php echo json_encode( $portal_url ); ?>;
    var t = setInterval(function() {
        secs--;
        if (el) el.textContent = secs;
        if (secs <= 0) {
            clearInterval(t);
            window.location.href = dest;
        }
    }, 1000);

    // Cancel redirect if they click "Return to website"
    document.querySelector('a[href="<?php echo esc_js( home_url('/') ); ?>"]')
        ?.addEventListener('click', function() { clearInterval(t); });
})();
</script>
