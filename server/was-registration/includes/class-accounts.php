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
        if ( $user ) {
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

        reset_password( $user, $pass );
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

    private static function send_welcome_email( $email, $first_name ) {
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

    public static function ajax_login() {
        check_ajax_referer( 'was_nonce', 'nonce' );
        $login    = sanitize_text_field( $_POST['login']    ?? '' );
        $password = $_POST['password']                      ?? '';
        $remember = ! empty( $_POST['remember'] );
        $redirect = sanitize_url( $_POST['redirect_to'] ?? home_url( '/account-portal/' ) );

        if ( empty( $login ) || empty( $password ) ) {
            wp_send_json_error( array( 'message' => 'Please enter your email address and password.' ) );
        }
        $user = wp_authenticate( $login, $password );
        if ( is_wp_error( $user ) ) {
            wp_send_json_error( array( 'message' => 'Email address or password is incorrect. Please try again.' ) );
        }
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

        // Create guardian record immediately at signup
        global $wpdb;
        $existing = WAS_Database::get_guardian_by_email( $email );
        if ( $existing ) {
            $wpdb->update(
                "{$wpdb->prefix}was_guardians",
                array(
                    'wp_user_id'          => $user_id,
                    'first_name'          => $first,
                    'last_name'           => $last,
                    'telephone'           => $tel,
                    'address_line1'       => $addr,
                    'postcode'            => $postcode,
                    'occupation_category' => $occ_cat,
                    'employer_name'       => $employer,
                ),
                array( 'id' => $existing->id )
            );
        } else {
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
        }

        self::send_welcome_email( $email, $first );

        wp_set_auth_cookie( $user_id, false );
        wp_send_json_success( array(
            'redirect' => home_url( '/account-portal/' ),
            'message'  => 'Account created successfully.',
        ) );
    }

    public static function ajax_logout() {
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
            $user     = get_userdata( $user_id );
            $guardian = WAS_Database::get_guardian_by_email( $user->user_email );
            if ( $guardian ) {
                $wpdb->update( "{$wpdb->prefix}was_guardians", array( 'wp_user_id' => $user_id ), array( 'id' => $guardian->id ) );
            }
        }
        return $guardian;
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
