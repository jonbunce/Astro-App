<?php
defined( 'ABSPATH' ) || exit;

class WAS_Accounts {

    public static function init() {
        add_shortcode( 'was_login',            array( __CLASS__, 'render_login' ) );
        add_shortcode( 'was_account_register', array( __CLASS__, 'render_register' ) );
        add_shortcode( 'was_password_reset',   array( __CLASS__, 'render_password_reset' ) );

        add_action( 'wp_ajax_nopriv_was_do_login',    array( __CLASS__, 'ajax_login' ) );
        add_action( 'wp_ajax_nopriv_was_do_register', array( __CLASS__, 'ajax_register' ) );
        add_action( 'wp_ajax_nopriv_was_do_forgot',   array( __CLASS__, 'ajax_forgot' ) );
        add_action( 'wp_ajax_was_do_forgot',          array( __CLASS__, 'ajax_forgot' ) );
        add_action( 'wp_ajax_nopriv_was_do_reset',    array( __CLASS__, 'ajax_reset' ) );
        add_action( 'wp_ajax_was_do_reset',           array( __CLASS__, 'ajax_reset' ) );
        add_action( 'wp_ajax_was_do_logout',          array( __CLASS__, 'ajax_logout' ) );
        add_action( 'wp_ajax_was_update_profile',     array( __CLASS__, 'ajax_update_profile' ) );

        // Email confirmation: the emailed link works signed in or out (admin-ajax is
        // never page-cached); "send it again" is for signed-in accounts only.
        add_action( 'wp_ajax_nopriv_was_verify_email', array( __CLASS__, 'ajax_verify_email' ) );
        add_action( 'wp_ajax_was_verify_email',        array( __CLASS__, 'ajax_verify_email' ) );
        add_action( 'wp_ajax_was_resend_verification', array( __CLASS__, 'ajax_resend_verification' ) );

        // Point every "lost password" link (ours, the theme's, WooCommerce's) at the
        // branded ASTRO reset page rather than the WordPress / WooCommerce screens.
        add_filter( 'lostpassword_url', array( __CLASS__, 'filter_lostpassword_url' ), 999 );

        add_action( 'template_redirect', array( __CLASS__, 'redirect_if_logged_in' ) );
        add_action( 'template_redirect', array( __CLASS__, 'redirect_woo_account' ) );
    }

    public static function filter_lostpassword_url( $url ) {
        return home_url( '/account-reset/' );
    }

    /**
     * "One front door": send logged-out visitors who land on the WooCommerce account
     * login (/my-account/) into the branded ASTRO login, and the WooCommerce
     * lost-password screen into the ASTRO reset. Logged-in customers and every other
     * WooCommerce endpoint (orders, downloads, addresses) — and checkout — are left
     * untouched so the shop keeps working. Signing in via ASTRO logs the user into the
     * whole site, so their order history stays reachable afterwards.
     */
    public static function redirect_woo_account() {
        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }

        // WooCommerce lost-password screen → branded ASTRO reset (carry any key/login).
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'lost-password' ) ) {
            $args = array();
            if ( ! empty( $_GET['key'] ) )   $args['key']   = sanitize_text_field( wp_unslash( $_GET['key'] ) );
            if ( ! empty( $_GET['login'] ) ) $args['login'] = sanitize_text_field( wp_unslash( $_GET['login'] ) );
            $url = $args ? add_query_arg( $args, home_url( '/account-reset/' ) ) : home_url( '/account-reset/' );
            wp_safe_redirect( $url );
            exit;
        }

        // The my-account root (the login form) when logged OUT → ASTRO login. Any other
        // WooCommerce endpoint (orders, view-order, downloads, edit-address …) is skipped.
        $on_endpoint = function_exists( 'is_wc_endpoint_url' ) ? is_wc_endpoint_url() : false;
        if ( ! is_user_logged_in() && ! $on_endpoint ) {
            wp_safe_redirect( home_url( '/account-login/' ) );
            exit;
        }
    }

    public static function redirect_if_logged_in() {
        if ( ! is_user_logged_in() ) return;
        global $post;
        if ( ! $post ) return;
        $content = $post->post_content;
        if ( has_shortcode( $content, 'was_login' ) || has_shortcode( $content, 'was_account_register' ) ) {
            $redirect = sanitize_url( wp_unslash( $_GET['redirect_to'] ?? '' ) );
            wp_safe_redirect( $redirect ?: home_url( '/account-portal/' ) );
            exit;
        }
    }

    public static function render_login( $atts ) {
        ob_start();
        $redirect = sanitize_url( $_GET['redirect_to'] ?? home_url( '/account-portal/' ) );
        // Back from an email-confirmation link while signed out (see ajax_verify_email).
        $verify_notice = ! empty( $_GET['was_verified'] ) ? 'ok' : ( ! empty( $_GET['was_verify_error'] ) ? 'invalid' : '' );
        include WAS_PATH . 'templates/account-login.php';
        return ob_get_clean();
    }

    public static function render_register( $atts ) {
        ob_start();
        include WAS_PATH . 'templates/account-register.php';
        return ob_get_clean();
    }

    // ── Branded password reset ───────────────────────────────────────────────

    /**
     * One page, two states: with a valid ?key/&login it shows the "set a new
     * password" form; otherwise it shows the "request a reset link" form.
     */
    public static function render_password_reset( $atts ) {
        ob_start();
        $key   = isset( $_GET['key'] )   ? sanitize_text_field( wp_unslash( $_GET['key'] ) )   : '';
        $login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

        if ( $key && $login ) {
            $user = check_password_reset_key( $key, $login );
            if ( ! is_wp_error( $user ) ) {
                $reset_key   = $key;
                $reset_login = $login;
                include WAS_PATH . 'templates/account-reset.php';
                return ob_get_clean();
            }
            $reset_expired = true; // fall through to the request form with a notice
        }
        include WAS_PATH . 'templates/account-forgot.php';
        return ob_get_clean();
    }

    /** Step 1 — email a branded reset link. Never reveals whether an account exists. */
    public static function ajax_forgot() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
        }

        $user = get_user_by( 'email', $email );
        if ( $user && ! self::login_disabled( $user->ID ) ) {
            $key = get_password_reset_key( $user );
            if ( ! is_wp_error( $key ) ) {
                self::send_reset_email( $user, $key );
            }
        }

        // Same response whether or not the account exists (no account enumeration).
        wp_send_json_success( array(
            'message' => 'If an account exists for that email address, we&rsquo;ve sent a link to reset your password. Please check your inbox &mdash; and your spam / junk folder, just in case.',
        ) );
    }

    /** Step 2 — validate the key and set the new password, then sign the user in. */
    public static function ajax_reset() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        $key     = sanitize_text_field( $_POST['key']   ?? '' );
        $login   = sanitize_text_field( $_POST['login'] ?? '' );
        $pass    = $_POST['password']         ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if ( strlen( $pass ) < 8 ) {
            wp_send_json_error( array( 'message' => 'Password must be at least 8 characters.' ) );
        }
        if ( $pass !== $confirm ) {
            wp_send_json_error( array( 'message' => 'The two passwords don&rsquo;t match.' ) );
        }

        $user = check_password_reset_key( $key, $login );
        if ( is_wp_error( $user ) ) {
            wp_send_json_error( array( 'message' => 'This reset link has expired or has already been used. Please <a href="' . esc_url( home_url( '/account-reset/' ) ) . '">request a new one</a>.' ) );
        }

        // A link sent before the account was closed must not sign it back in.
        if ( self::login_disabled( $user->ID ) ) {
            wp_send_json_error( array( 'message' => esc_html( self::CLOSED_MESSAGE ) ) );
        }

        reset_password( $user, $pass );

        // The reset link was emailed, so using it proves this person owns the inbox —
        // the account can now be linked to a family record with the same email address.
        self::mark_email_verified( $user );

        wp_set_auth_cookie( $user->ID, true );

        wp_send_json_success( array(
            'message'  => 'Password updated &mdash; signing you in&hellip;',
            'redirect' => home_url( '/account-portal/' ),
        ) );
    }

    public static function html_content_type() { return 'text/html'; }

    private static function send_reset_email( $user, $key ) {
        $reset_url = add_query_arg( array(
            'key'   => rawurlencode( $key ),
            'login' => rawurlencode( $user->user_login ),
        ), home_url( '/account-reset/' ) );

        $club = get_bloginfo( 'name' );
        $name = $user->first_name ? $user->first_name : $user->display_name;

        $subject = $club . ' — reset your ASTRO password';

        ob_start(); ?>
<div style="background:#f4f5f7;padding:24px 12px;font-family:'Segoe UI',Arial,sans-serif;">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e6e8ec;">
    <div style="background:#14161a;padding:20px 24px;">
      <div style="font-size:22px;font-weight:800;letter-spacing:1px;color:#ffffff;">
        <span style="color:#E39A00;">&#9733;</span> <span style="color:#C8102E;">A</span>STRO
      </div>
      <div style="color:#c9ccd2;font-size:12px;margin-top:3px;"><?php echo esc_html( $club ); ?></div>
    </div>
    <div style="padding:24px;color:#2a2d33;font-size:15px;line-height:1.55;">
      <p style="margin:0 0 12px;">Hi <?php echo esc_html( $name ); ?>,</p>
      <p style="margin:0 0 16px;">We received a request to reset your ASTRO password. Click the button below to choose a new one. For your security this link expires after a short while.</p>
      <p style="text-align:center;margin:22px 0;">
        <a href="<?php echo esc_url( $reset_url ); ?>" style="display:inline-block;background:#C8102E;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 28px;border-radius:8px;">Reset my password</a>
      </p>
      <p style="margin:0 0 6px;font-size:13px;color:#5c6470;">If the button doesn&rsquo;t work, copy and paste this link into your browser:</p>
      <p style="margin:0 0 16px;font-size:12px;color:#1f6fb2;word-break:break-all;"><?php echo esc_url( $reset_url ); ?></p>
      <p style="margin:16px 0 0;font-size:13px;color:#5c6470;">If you didn&rsquo;t ask to reset your password you can safely ignore this email &mdash; your password won&rsquo;t change.</p>
    </div>
    <div style="background:#fafbfc;padding:14px 24px;border-top:1px solid #eef0f3;color:#8a909a;font-size:11px;">
      <?php echo esc_html( $club ); ?> &middot; Built for our stars.
    </div>
  </div>
</div>
        <?php
        $body = ob_get_clean();

        add_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );
        wp_mail( $user->user_email, $subject, $body );
        remove_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );
    }

    private static function send_welcome_email( $email, $first_name, $verify_url = '' ) {
        $club     = get_bloginfo( 'name' );
        $portal   = home_url( '/account-portal/' );
        $register = home_url( '/registrations-unified/' );

        $subject = $club . ' — welcome to ASTRO';

        ob_start(); ?>
<div style="background:#f4f5f7;padding:24px 12px;font-family:'Segoe UI',Arial,sans-serif;">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e6e8ec;">
    <div style="background:#14161a;padding:20px 24px;">
      <div style="font-size:22px;font-weight:800;letter-spacing:1px;color:#ffffff;">
        <span style="color:#E39A00;">&#9733;</span> <span style="color:#C8102E;">A</span>STRO
      </div>
      <div style="color:#c9ccd2;font-size:12px;margin-top:3px;"><?php echo esc_html( $club ); ?></div>
    </div>
    <div style="padding:24px;color:#2a2d33;font-size:15px;line-height:1.55;">
      <p style="margin:0 0 12px;">Hi <?php echo esc_html( $first_name ); ?>,</p>
      <p style="margin:0 0 16px;">Welcome to <?php echo esc_html( $club ); ?>! Your ASTRO account has been created and you're all set. One account covers your whole family &mdash; from your portal you can:</p>
      <ul style="margin:0 0 16px;padding-left:20px;">
        <li style="margin-bottom:6px;"><a href="<?php echo esc_url( $register ); ?>" style="color:#C8102E;font-weight:600;">Register your child</a> with their team</li>
        <li style="margin-bottom:6px;">Update contact, medical and consent details any time</li>
        <li>See your payment schedule and history</li>
      </ul>
      <p style="text-align:center;margin:22px 0;">
        <a href="<?php echo esc_url( $portal ); ?>" style="display:inline-block;background:#C8102E;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 28px;border-radius:8px;">Go to my ASTRO Portal</a>
      </p>
      <?php if ( $verify_url ) : ?>
      <p style="margin:0 0 10px;">Please also take a moment to confirm your email address &mdash; it helps us keep your family&rsquo;s details safe:</p>
      <p style="text-align:center;margin:0 0 22px;">
        <a href="<?php echo esc_url( $verify_url ); ?>" style="display:inline-block;background:#ffffff;color:#C8102E;text-decoration:none;font-weight:700;padding:10px 24px;border-radius:8px;border:2px solid #C8102E;">Confirm my email address</a>
      </p>
      <?php endif; ?>
      <p style="margin:16px 0 0;font-size:13px;color:#5c6470;">The name and phone number on your account will be used as your child's first emergency contact &mdash; you can add a second, alternative contact when you register each child.</p>
    </div>
    <div style="background:#fafbfc;padding:14px 24px;border-top:1px solid #eef0f3;color:#8a909a;font-size:11px;">
      <?php echo esc_html( $club ); ?> &middot; Built for our stars.
    </div>
  </div>
</div>
        <?php
        $body = ob_get_clean();

        add_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );
        wp_mail( $email, $subject, $body );
        remove_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );
    }

    /** Email a fresh confirmation link (see "Email confirmation" below). */
    private static function send_verification_email( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $verify_url = self::new_verification_link( $user );

        $club = get_bloginfo( 'name' );
        $name = $user->first_name ? $user->first_name : $user->display_name;

        $subject = $club . ' — please confirm your email address';

        ob_start(); ?>
<div style="background:#f4f5f7;padding:24px 12px;font-family:'Segoe UI',Arial,sans-serif;">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e6e8ec;">
    <div style="background:#14161a;padding:20px 24px;">
      <div style="font-size:22px;font-weight:800;letter-spacing:1px;color:#ffffff;">
        <span style="color:#E39A00;">&#9733;</span> <span style="color:#C8102E;">A</span>STRO
      </div>
      <div style="color:#c9ccd2;font-size:12px;margin-top:3px;"><?php echo esc_html( $club ); ?></div>
    </div>
    <div style="padding:24px;color:#2a2d33;font-size:15px;line-height:1.55;">
      <p style="margin:0 0 12px;">Hi <?php echo esc_html( $name ); ?>,</p>
      <p style="margin:0 0 16px;">Please confirm that this is your email address so we can link your family&rsquo;s club registrations to your ASTRO account. We ask for this to keep children&rsquo;s details safe.</p>
      <p style="text-align:center;margin:22px 0;">
        <a href="<?php echo esc_url( $verify_url ); ?>" style="display:inline-block;background:#C8102E;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 28px;border-radius:8px;">Confirm my email address</a>
      </p>
      <p style="margin:0 0 6px;font-size:13px;color:#5c6470;">If the button doesn&rsquo;t work, copy and paste this link into your browser:</p>
      <p style="margin:0 0 16px;font-size:12px;color:#1f6fb2;word-break:break-all;"><?php echo esc_url( $verify_url ); ?></p>
      <p style="margin:16px 0 0;font-size:13px;color:#5c6470;">This link works for 48 hours. If you didn&rsquo;t create an ASTRO account you can safely ignore this email &mdash; nothing will be linked and no details will be shared.</p>
    </div>
    <div style="background:#fafbfc;padding:14px 24px;border-top:1px solid #eef0f3;color:#8a909a;font-size:11px;">
      <?php echo esc_html( $club ); ?> &middot; Built for our stars.
    </div>
  </div>
</div>
        <?php
        $body = ob_get_clean();

        add_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );
        wp_mail( $user->user_email, $subject, $body );
        remove_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );
    }

    public static function ajax_login() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        $login    = sanitize_text_field( $_POST['login']    ?? '' );
        $password = $_POST['password']                      ?? '';
        $remember = ! empty( $_POST['remember'] );

        // Only ever send people somewhere on this site after signing in (no open redirect).
        $portal   = home_url( '/account-portal/' );
        $redirect = wp_validate_redirect( sanitize_url( wp_unslash( $_POST['redirect_to'] ?? '' ) ), $portal );
        if ( ! $redirect ) $redirect = $portal;

        if ( empty( $login ) || empty( $password ) ) {
            wp_send_json_error( array( 'message' => 'Please enter your email address and password.' ) );
        }

        // Brute-force brake: after 5 wrong passwords for the same email from the same IP
        // address within 15 minutes, stop trying that pair until the 15 minutes are up.
        $throttle_key = 'was_login_fail_' . md5( ( $_SERVER['REMOTE_ADDR'] ?? '' ) . '|' . strtolower( $login ) );
        $failures     = get_transient( $throttle_key );
        if ( is_array( $failures ) && (int) ( $failures['count'] ?? 0 ) >= 5 ) {
            wp_send_json_error( array( 'message' => 'Too many unsuccessful sign-in attempts. Please try again in 15 minutes, or <a href="' . esc_url( home_url( '/account-reset/' ) ) . '">reset your password</a>.' ) );
        }

        $user = wp_authenticate( $login, $password );
        if ( is_wp_error( $user ) ) {
            // The right password, but the owner has asked for the account to be closed.
            if ( $user->get_error_code() === 'was_login_disabled' ) {
                wp_send_json_error( array( 'message' => esc_html( self::CLOSED_MESSAGE ) ) );
            }
            if ( ! is_array( $failures ) ) {
                $failures = array( 'count' => 0, 'start' => time() );
            }
            $failures['count'] = (int) ( $failures['count'] ?? 0 ) + 1;
            // Keep the original 15-minute window rather than extending it on every attempt.
            $ttl = (int) ( $failures['start'] ?? time() ) + 15 * MINUTE_IN_SECONDS - time();
            set_transient( $throttle_key, $failures, max( MINUTE_IN_SECONDS, $ttl ) );
            wp_send_json_error( array( 'message' => 'Email address or password is incorrect. Please try again.' ) );
        }
        delete_transient( $throttle_key );
        wp_set_auth_cookie( $user->ID, $remember );
        wp_send_json_success( array( 'redirect' => $redirect ) );
    }

    public static function ajax_register() {
        check_ajax_referer( 'was_nonce', 'nonce' );

        // wp_unslash on the free-text fields so apostrophes aren't stored with backslashes.
        // Password/confirm are left raw (never unslash a password — it may contain a backslash).
        $first    = sanitize_text_field( wp_unslash( $_POST['first_name']       ?? '' ) );
        $last     = sanitize_text_field( wp_unslash( $_POST['last_name']        ?? '' ) );
        $email    = sanitize_email(      wp_unslash( $_POST['email']            ?? '' ) );
        $password = $_POST['password']                              ?? '';
        $confirm  = $_POST['password_confirm']                      ?? '';
        $tel      = sanitize_text_field( wp_unslash( $_POST['telephone']        ?? '' ) );
        $addr     = sanitize_text_field( wp_unslash( $_POST['address']          ?? '' ) );
        $postcode = strtoupper( sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) ) );
        $occ_cat  = sanitize_text_field( wp_unslash( $_POST['occupation_category'] ?? '' ) );
        $employer = sanitize_text_field( wp_unslash( $_POST['employer_name']    ?? '' ) );

        // Validate
        $errors = array();
        if ( empty( $first ) )    $errors[] = 'First name is required.';
        if ( empty( $last ) )     $errors[] = 'Last name is required.';
        if ( ! is_email( $email ) ) $errors[] = 'A valid email address is required.';
        if ( strlen( $password ) < 8 ) $errors[] = 'Password must be at least 8 characters.';
        if ( $password !== $confirm ) $errors[] = 'Passwords do not match.';
        if ( empty( $tel ) )      $errors[] = 'Telephone number is required.';
        if ( empty( $addr ) )     $errors[] = 'Home address is required.';
        if ( empty( $postcode ) ) $errors[] = 'Postcode is required.';
        if ( empty( $occ_cat ) )  $errors[] = 'Please select an occupation category.';

        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 'message' => implode( '<br>', $errors ) ) );
        }

        if ( email_exists( $email ) ) {
            wp_send_json_error( array( 'message' => 'An account with that email address already exists. <a href="' . esc_url( home_url( '/account-login/' ) ) . '">Sign in instead?</a>' ) );
        }

        $user_id = wp_create_user( $email, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Could not create your account. Please try again.' ) );
        }

        wp_update_user( array(
            'ID'           => $user_id,
            'first_name'   => $first,
            'last_name'    => $last,
            'display_name' => $first . ' ' . $last,
        ) );

        was_add_role( $user_id, 'parent' );

        global $wpdb;
        $existing = self::unlinked_guardian_by_email( $email );
        if ( $existing ) {
            // A family record (children, medical details, Direct Debits) already uses this
            // email address. Don't link or change it until this new account proves it owns
            // the inbox — otherwise anyone could sign up with a parent's email address and
            // inherit their children's details. Keep the sign-up details aside (they only
            // fill gaps in the record once confirmed) and email a confirmation link.
            update_user_meta( $user_id, 'was_signup_details', array(
                'first_name'          => $first,
                'last_name'           => $last,
                'telephone'           => $tel,
                'address_line1'       => $addr,
                'postcode'            => $postcode,
                'occupation_category' => $occ_cat,
                'employer_name'       => $employer,
            ) );
            self::send_verification_email( $user_id );

            // Not signed in yet — the confirmation link comes first.
            wp_send_json_success( array(
                'message' => '<strong>Nearly there &mdash; please check your email.</strong> To keep your family&rsquo;s details safe we&rsquo;ve sent a link to <strong>' . esc_html( $email ) . '</strong> to confirm it&rsquo;s really you. Click it, then sign in and your account is ready. The link works for 48 hours &mdash; if it hasn&rsquo;t arrived in a few minutes, please check your spam / junk folder.',
            ) );
        }

        // Create guardian record immediately at signup
        $wpdb->insert(
            "{$wpdb->prefix}was_guardians",
            array(
                'wp_user_id'          => $user_id,
                'first_name'          => $first,
                'last_name'           => $last,
                'email'               => $email,
                'telephone'           => $tel,
                'address_line1'       => $addr,
                'postcode'            => $postcode,
                'occupation_category' => $occ_cat,
                'employer_name'       => $employer,
            )
        );

        // A brand-new family doesn't have to wait, but is still asked to confirm the address
        // (the button is in the welcome email) so a record the club later adds under this
        // email can be linked safely.
        self::send_welcome_email( $email, $first, self::new_verification_link( get_userdata( $user_id ) ) );

        wp_set_auth_cookie( $user_id, false );
        wp_send_json_success( array(
            'redirect' => home_url( '/account-portal/' ),
            'message'  => 'Account created successfully.',
        ) );
    }

    public static function ajax_logout() {
        // Nonce so another site can't sign people out behind their backs.
        if ( ! check_ajax_referer( 'was_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Your session has expired. Please refresh the page and try signing out again.' ) );
        }
        wp_logout();
        wp_send_json_success( array( 'redirect' => home_url( '/account-login/' ) ) );
    }

    public static function ajax_update_profile() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        global $wpdb;
        $user_id  = get_current_user_id();
        $_POST    = wp_unslash( $_POST ); // strip WordPress' added slashes before sanitising

        // Sanitise + validate server-side. The onboarding form and the profile
        // editor both gate these client-side, but validate here too so a crafted
        // POST cannot create a blank guardian row or drive-by self-grant a role.
        $first    = sanitize_text_field( $_POST['first_name'] ?? "" );
        $last     = sanitize_text_field( $_POST['last_name']  ?? "" );
        $tel      = sanitize_text_field( $_POST['telephone']  ?? "" );
        $addr     = sanitize_text_field( $_POST['address']    ?? "" );
        $postcode = strtoupper( sanitize_text_field( $_POST['postcode'] ?? "" ) );
        $occ      = sanitize_text_field( $_POST['occupation_category'] ?? "" );
        $employer = sanitize_text_field( $_POST['employer_name']       ?? "" );

        if ( empty( $first ) || empty( $last ) || empty( $tel ) || empty( $addr ) || empty( $postcode ) ) {
            wp_send_json_error( array( 'message' => 'Please complete all required fields.' ) );
        }

        $guardian = self::current_guardian();

        // An unconfirmed account whose email matches an existing family record: that record
        // is linked once the email is confirmed, so don't create a duplicate one now.
        if ( ! $guardian && self::verification_pending( $user_id ) ) {
            self::maybe_send_verification_email( $user_id, 2 * DAY_IN_SECONDS );
            wp_send_json_error( array( 'message' => "Please confirm your email address first — click the link we've emailed you. Your family's details will then appear here." ) );
        }

        // If no guardian found, create one linked to this user
        if ( ! $guardian ) {
            $user = get_userdata( $user_id );
            $wpdb->insert(
                "{$wpdb->prefix}was_guardians",
                array(
                    'wp_user_id'          => $user_id,
                    'first_name'          => $first,
                    'last_name'           => $last,
                    'email'               => $user->user_email,
                    'telephone'           => $tel,
                    'address_line1'       => $addr,
                    'postcode'            => $postcode,
                    'occupation_category' => $occ,
                    'employer_name'       => $employer,
                )
            );
            was_add_role( $user_id, 'parent' );
            wp_send_json_success( array( 'message' => 'Profile created and saved.' ) );
            return;
        }

        // Ensure wp_user_id is linked
        if ( ! $guardian->wp_user_id ) {
            $wpdb->update( "{$wpdb->prefix}was_guardians", array( 'wp_user_id' => $user_id ), array( 'id' => $guardian->id ) );
        }

        $wpdb->update(
            "{$wpdb->prefix}was_guardians",
            array(
                'first_name'          => $first,
                'last_name'           => $last,
                'telephone'           => $tel,
                'address_line1'       => $addr,
                'postcode'            => $postcode,
                'occupation_category' => $occ,
                'employer_name'       => $employer,
            ),
            array( 'id' => $guardian->id )
        );

        // Ensure parent role is set — handles existing WooCommerce customers
        was_add_role( $user_id, 'parent' );

        wp_send_json_success( array( 'message' => 'Profile updated.' ) );
    }

    public static function current_guardian() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return null;
        global $wpdb;
        $guardian = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_guardians WHERE wp_user_id = %d",
            $user_id
        ) );
        if ( ! $guardian ) {
            // Auto-link an unclaimed family record with the same email address — but only
            // once this account has proved it owns that inbox (confirmation link or password
            // reset). Otherwise anyone could sign up with a parent's email address and see
            // their children's details.
            $user = get_userdata( $user_id );
            if ( $user && self::is_email_verified( $user_id ) ) {
                $guardian = self::unlinked_guardian_by_email( $user->user_email );
                if ( $guardian ) {
                    $wpdb->update( "{$wpdb->prefix}was_guardians", array( 'wp_user_id' => $user_id ), array( 'id' => $guardian->id ) );
                    $guardian->wp_user_id = $user_id;
                }
            }
        }
        return $guardian;
    }

    // ── Email confirmation ──────────────────────────────────────────────────────
    //
    // Linking an account to an existing family record (children, medical details,
    // payments) by email address is only safe once the account has proved it owns that
    // inbox: by clicking the emailed confirmation link, or by completing a password reset.
    // The confirmed address itself is stored (not just a flag), so changing the account's
    // email later doesn't carry the proof over to an address that was never checked.

    /** True once this account has confirmed it owns its current email address. */
    public static function is_email_verified( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user || ! $user->user_email ) return false;
        $verified = (string) get_user_meta( $user->ID, 'was_email_verified', true );
        return $verified !== '' && $verified === strtolower( $user->user_email );
    }

    /** The oldest family record with this email address that no account has claimed yet. */
    private static function unlinked_guardian_by_email( $email ) {
        global $wpdb;
        $email = sanitize_email( $email );
        if ( ! $email ) return null;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_guardians
             WHERE email = %s AND ( wp_user_id IS NULL OR wp_user_id = 0 )
             ORDER BY id ASC LIMIT 1",
            $email
        ) );
    }

    /**
     * An account that hasn't confirmed its email, has no family record of its own, and
     * whose email matches an unclaimed record. It sees a "please confirm your email"
     * notice in the portal and can't create a second record for the same family.
     */
    public static function verification_pending( $user_id ) {
        global $wpdb;
        $user = get_userdata( $user_id );
        if ( ! $user || self::is_email_verified( $user->ID ) ) return false;
        $linked = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}was_guardians WHERE wp_user_id = %d LIMIT 1",
            $user->ID
        ) );
        if ( $linked ) return false;
        return (bool) self::unlinked_guardian_by_email( $user->user_email );
    }

    /** A new single-use confirmation link, valid for 48 hours. Only a hash of it is stored. */
    private static function new_verification_link( $user ) {
        $token = wp_generate_password( 32, false );
        update_user_meta( $user->ID, 'was_email_verify', array(
            'hash'    => hash( 'sha256', $token ),
            'email'   => strtolower( $user->user_email ),
            'expires' => time() + 2 * DAY_IN_SECONDS,
            'sent'    => time(),
        ) );
        return add_query_arg( array(
            'action' => 'was_verify_email',
            'u'      => $user->ID,
            'k'      => $token,
        ), admin_url( 'admin-ajax.php' ) );
    }

    /**
     * Send a confirmation link unless one sent less than $min_gap seconds ago is still
     * valid for the account's current address. Returns true if an email went out.
     */
    public static function maybe_send_verification_email( $user_id, $min_gap ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;
        $pending = get_user_meta( $user->ID, 'was_email_verify', true );
        if ( is_array( $pending )
            && (int) ( $pending['expires'] ?? 0 ) > time()
            && (int) ( $pending['sent'] ?? 0 ) > time() - $min_gap
            && ( $pending['email'] ?? '' ) === strtolower( $user->user_email ) ) {
            return false;
        }
        self::send_verification_email( $user->ID );
        return true;
    }

    /** Record that this account owns its email address, then claim the family record. */
    private static function mark_email_verified( $user ) {
        update_user_meta( $user->ID, 'was_email_verified', strtolower( $user->user_email ) );
        delete_user_meta( $user->ID, 'was_email_verify' );
        self::link_guardian_by_email( $user );
    }

    /**
     * Link a confirmed account to the unclaimed family record with its email address.
     * What the club already holds (name, phone, address…) is kept; the sign-up form only
     * fills fields that are empty.
     */
    private static function link_guardian_by_email( $user ) {
        global $wpdb;
        $already = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}was_guardians WHERE wp_user_id = %d LIMIT 1",
            $user->ID
        ) );
        $guardian = $already ? null : self::unlinked_guardian_by_email( $user->user_email );
        if ( $guardian ) {
            $data   = array( 'wp_user_id' => $user->ID );
            $signup = get_user_meta( $user->ID, 'was_signup_details', true );
            if ( is_array( $signup ) ) {
                foreach ( array( 'first_name', 'last_name', 'telephone', 'address_line1', 'postcode', 'occupation_category', 'employer_name' ) as $col ) {
                    if ( empty( $guardian->$col ) && ! empty( $signup[ $col ] ) ) {
                        $data[ $col ] = $signup[ $col ];
                    }
                }
            }
            $wpdb->update( "{$wpdb->prefix}was_guardians", $data, array( 'id' => $guardian->id ) );
        }
        delete_user_meta( $user->ID, 'was_signup_details' );
    }

    /**
     * The link in the confirmation email (GET, signed in or out). Confirms the address,
     * links the family record, then shows the portal — or the sign-in page, with a
     * notice, if this browser isn't signed in to that account.
     */
    public static function ajax_verify_email() {
        $user_id = absint( $_GET['u'] ?? 0 );
        $token   = sanitize_text_field( wp_unslash( $_GET['k'] ?? '' ) );
        $user    = $user_id ? get_userdata( $user_id ) : false;
        $ok      = false;

        if ( $user && $token !== '' ) {
            $pending = get_user_meta( $user->ID, 'was_email_verify', true );
            if ( is_array( $pending )
                && ! empty( $pending['hash'] )
                && hash_equals( (string) $pending['hash'], hash( 'sha256', $token ) )
                && (int) ( $pending['expires'] ?? 0 ) >= time()
                && ( $pending['email'] ?? '' ) === strtolower( $user->user_email ) ) {
                self::mark_email_verified( $user );
                $ok = true;
            } elseif ( self::is_email_verified( $user->ID ) ) {
                // Already confirmed — the link was opened twice, or a mail scanner got there first.
                $ok = true;
            }
        }

        $flag = $ok ? 'was_verified' : 'was_verify_error';
        if ( $user && get_current_user_id() === (int) $user->ID ) {
            wp_safe_redirect( add_query_arg( $flag, '1', home_url( '/account-portal/' ) ) );
        } else {
            wp_safe_redirect( add_query_arg( $flag, '1', home_url( '/account-login/' ) ) );
        }
        exit;
    }

    /** "Send the link again" from the portal notice (GET, nonce-protected). */
    public static function ajax_resend_verification() {
        $portal = home_url( '/account-portal/' );
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'was_resend_verification' ) ) {
            wp_safe_redirect( $portal );
            exit;
        }
        $user_id = get_current_user_id();
        if ( self::verification_pending( $user_id ) ) {
            self::maybe_send_verification_email( $user_id, MINUTE_IN_SECONDS );
        }
        wp_safe_redirect( add_query_arg( 'was_verify_sent', '1', $portal ) );
        exit;
    }

    public static function resend_verification_url() {
        return add_query_arg( array(
            'action'   => 'was_resend_verification',
            '_wpnonce' => wp_create_nonce( 'was_resend_verification' ),
        ), admin_url( 'admin-ajax.php' ) );
    }

    // ── Closed accounts ─────────────────────────────────────────────────────────
    //
    // A member can ask, in the Allstars app, for their account to be deleted. The account
    // is closed at once — no signing in anywhere (website, shop, wp-admin, app) — while
    // the club works through the request by hand. Nothing is deleted automatically.

    const CLOSED_MESSAGE = 'This account has been closed at your request, so it can no longer be used to sign in. If this is a mistake, please contact the club.';

    /** Hooks that keep a closed account out. Added as soon as the plugin loads. */
    public static function init_login_guards() {
        add_filter( 'authenticate',         array( __CLASS__, 'block_closed_login' ), 99 );
        add_filter( 'allow_password_reset', array( __CLASS__, 'block_closed_reset' ), 10, 2 );
        add_filter( 'wp_is_application_passwords_available_for_user', array( __CLASS__, 'block_closed_app_passwords' ), 10, 2 );
    }

    /** True once the account's owner has asked for it to be deleted. */
    public static function login_disabled( $user_id ) {
        return (int) $user_id > 0 && (bool) get_user_meta( (int) $user_id, 'was_login_disabled', true );
    }

    /**
     * Every username/password sign-in (wp-login, WooCommerce, ASTRO, the app). Only a
     * correct password reaches here as a WP_User, so a wrong one still gets the usual error.
     */
    public static function block_closed_login( $user ) {
        if ( $user instanceof WP_User && self::login_disabled( $user->ID ) ) {
            return new WP_Error( 'was_login_disabled', self::CLOSED_MESSAGE );
        }
        return $user;
    }

    public static function block_closed_reset( $allow, $user_id ) {
        return self::login_disabled( $user_id ) ? false : $allow;
    }

    public static function block_closed_app_passwords( $available, $user ) {
        return ( $user instanceof WP_User && self::login_disabled( $user->ID ) ) ? false : $available;
    }

    /**
     * The member has confirmed (with their password) that they want their account deleted.
     * Close it, sign it out everywhere, tell the app plugin, and email the people who
     * handle it a checklist.
     */
    public static function request_deletion( $user_id ) {
        global $wpdb;
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        update_user_meta( $user->ID, 'was_deletion_requested_at', current_time( 'mysql', true ) ); // UTC
        update_user_meta( $user->ID, 'was_login_disabled', 1 );

        // What the club will need to find: the children on this account (any season), plus
        // any registration this account started but never finished.
        $guardian_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}was_guardians WHERE wp_user_id = %d ORDER BY id ASC LIMIT 1",
            $user->ID
        ) );
        $children = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.first_name, p.season, p.registration_status, p.gc_mandate_id, p.gc_subscription_id,
                    t.name AS team_name
             FROM {$wpdb->prefix}was_players p
             LEFT JOIN {$wpdb->prefix}was_teams t ON p.team_id = t.id
             WHERE p.primary_guardian_id = %d
                OR ( p.primary_guardian_id = 0 AND p.created_by_user = %d )
             ORDER BY p.season DESC, p.first_name ASC",
            $guardian_id ?: -1, $user->ID
        ) );

        WP_Session_Tokens::get_instance( $user->ID )->destroy_all();

        try {
            do_action( 'was_account_deletion_requested', (int) $user->ID );
        } catch ( Throwable $e ) {
            error_log( '[WAS] Account deletion request: a listener failed — ' . $e->getMessage() );
        }

        WAS_Notifications::account_deletion_requested( $user, $children, (bool) $guardian_id );
    }

    public static function get_coach_teams( $user_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT t.* FROM {$wpdb->prefix}was_teams t
             INNER JOIN {$wpdb->prefix}was_coach_teams ct ON t.id = ct.team_id
             WHERE ct.user_id = %d AND t.active = 1
             ORDER BY t.name ASC",
            $user_id
        ) );
    }

    public static function occupation_categories() {
        return array(
            ''                              => '— Please select —',
            'Management / Senior Professional' => 'Management / Senior Professional',
            'Professional / Technical'      => 'Professional / Technical',
            'Administrative / Clerical'     => 'Administrative / Clerical',
            'Skilled Trades'                => 'Skilled Trades',
            'Service / Sales'               => 'Service / Sales',
            'Operative / Semi-skilled'      => 'Operative / Semi-skilled',
            'Unskilled / Labouring'         => 'Unskilled / Labouring',
            'Self-employed'                 => 'Self-employed',
            'Student'                       => 'Student',
            'Not currently employed'        => 'Not currently employed',
            'Retired'                       => 'Retired',
            'Decline to state'              => 'Decline to state',
        );
    }
}
