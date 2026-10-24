<?php
defined( 'ABSPATH' ) || exit;

class WAS_Notifications {

    // ── Template defaults ────────────────────────────────────────────────────

    public static function default_templates() {
        $club = get_bloginfo( 'name' );
        return array(
            'registration_confirmed' => array(
                'label'   => 'Registration Confirmation',
                'subject' => '{club_name} — Registration confirmed for {player_name}',
                'body'    => "Hi {guardian_name},\n\nThank you — {player_name}'s registration has been received for the {season} season with {club_name}.\n\nYou can view and update your child's details, medical information and consents at any time from your ASTRO Portal:\n{portal_url}\n\nIf you have any questions, please don't hesitate to get in touch.\n\nThanks,\n{club_name}",
                'placeholders' => array( '{guardian_name}', '{player_name}', '{season}', '{club_name}', '{portal_url}' ),
            ),
            'payment_failed' => array(
                'label'   => 'Payment Failed — Automatic Retry (to Parent)',
                'subject' => '{club_name} — Payment of {amount} for {player_name} was not collected (we will retry on {retry_date})',
                'body'    => "Hi {guardian_name},\n\nThe Direct Debit collection of {amount} for {player_name} ({team_name}), due on {charge_date}, was returned unpaid. Your bank told us {reason}.\n\nWhat happens next:\nWe will automatically try to collect this same payment once more on {retry_date}. Please make sure the funds are available in your account by then — there is nothing else you need to do. GoCardless will send you its usual advance notice before the collection.\n\nIf the second attempt is also returned unpaid, {player_name}'s place will be put on hold until the outstanding amount is paid. We'll email you straight away if that happens, with a one-click option to pay in your ASTRO Portal.\n\nYou can see {player_name}'s payment history at any time in your ASTRO Portal:\n{portal_url}\n\nIf you have any questions, or would like to discuss a payment arrangement, reply to this email or contact {finance_email}.\n\nThanks,\n{club_name}",
                'placeholders' => array( '{guardian_name}', '{player_name}', '{team_name}', '{amount}', '{charge_date}', '{reason}', '{retry_date}', '{portal_url}', '{finance_email}', '{club_name}' ),
            ),
            'payment_failed_internal' => array(
                'label'   => 'Payment Failed — Automatic Retry (Internal — Treasurer Alert)',
                'subject' => '[{club_name}] Payment failed, retry scheduled — {player_name}',
                'body'    => "A subscription collection was returned unpaid and ASTRO has asked GoCardless to retry it once.\n\nPlayer:      {player_name}\nGuardian:    {guardian_name} <{guardian_email}>\nTeam:        {team_name}\nAmount:      {amount}\nDue date:    {charge_date}\nBank reason: {reason}\nRetry date:  {retry_date}\n\nThe parent has been emailed and told to make sure funds are available. If the retry also fails the player will be put on payment hold automatically and both of you will be emailed again. No action is needed from you at this stage.\n\nASTRO Portal > Payments:\n{portal_url}",
                'placeholders' => array( '{player_name}', '{guardian_name}', '{guardian_email}', '{team_name}', '{amount}', '{charge_date}', '{reason}', '{retry_date}', '{club_name}', '{portal_url}' ),
            ),
            'payment_arrears' => array(
                'label'   => 'Payment Hold — Amount Outstanding (to Parent)',
                'subject' => '{club_name} — Action needed: {arrears_total} outstanding for {player_name}',
                'body'    => "Hi {guardian_name},\n\n{intro}\n\n{player_name}'s registration with {club_name} has been placed on hold. Until the outstanding amount is settled, {player_name} will not be able to train or play with the club.\n\nAmount outstanding: {arrears_total}\n{arrears_detail}\n\nHow to get {player_name} back playing — choose whichever is easiest:\n\n{next_steps}\n\nYour regular monthly subscription continues as normal alongside this.\n\nThanks,\n{club_name}",
                'placeholders' => array( '{guardian_name}', '{player_name}', '{team_name}', '{intro}', '{arrears_total}', '{arrears_detail}', '{next_steps}', '{portal_url}', '{finance_email}', '{club_name}' ),
            ),
            'payment_arrears_internal' => array(
                'label'   => 'Payment Hold — Amount Outstanding (Internal — Treasurer Alert)',
                'subject' => '[{club_name}] Payment hold — {player_name} ({arrears_total} outstanding)',
                'body'    => "A player has been placed on payment hold.\n\nPlayer:      {player_name}\nGuardian:    {guardian_name} <{guardian_email}>\nTeam:        {team_name}\nOutstanding: {arrears_total}\n{arrears_detail}\nWhy:         {intro}\n\nThe parent has been emailed with two options: pay the outstanding amount in one click from the ASTRO Portal (a one-off Direct Debit collection), or contact {finance_email} to arrange payment another way.\n\nWhat you can do from ASTRO Portal > Payments > In arrears:\n- Collect now: take the outstanding amount by one-off Direct Debit on the parent's behalf.\n- Clear hold: mark it settled (cash / bank transfer / agreed write-off) and reinstate the player.\n{portal_url}",
                'placeholders' => array( '{player_name}', '{guardian_name}', '{guardian_email}', '{team_name}', '{intro}', '{arrears_total}', '{arrears_detail}', '{finance_email}', '{club_name}', '{portal_url}' ),
            ),
            'catchup_scheduled' => array(
                'label'   => 'Catch-up Payment Scheduled (to Parent)',
                'subject' => '{club_name} — Catch-up payment of {amount} for {player_name} scheduled for {charge_date}',
                'body'    => "Hi {guardian_name},\n\nThank you. A one-off Direct Debit collection of {amount} for {player_name} has been scheduled with GoCardless{initiated_line} and will leave your account on or around {charge_date}. It covers:\n{arrears_detail}\n\nGoCardless will email you its usual advance notice. Once the payment is confirmed (normally 3–5 working days after the collection date) {player_name}'s hold is lifted automatically and we'll email you to confirm. Nothing else is needed from you.\n\nIf the collection is returned unpaid, we'll let you know straight away and you'll be able to try again from your ASTRO Portal or contact {finance_email}.\n\nYour regular monthly subscription continues as normal.\n\nThanks,\n{club_name}",
                'placeholders' => array( '{guardian_name}', '{player_name}', '{team_name}', '{amount}', '{charge_date}', '{arrears_detail}', '{initiated_line}', '{portal_url}', '{finance_email}', '{club_name}' ),
            ),
            'catchup_scheduled_internal' => array(
                'label'   => 'Catch-up Payment Scheduled (Internal — Treasurer Alert)',
                'subject' => '[{club_name}] Catch-up payment scheduled — {player_name} ({amount})',
                'body'    => "A one-off catch-up collection has been created in GoCardless{initiated_line}.\n\nPlayer:      {player_name}\nGuardian:    {guardian_name} <{guardian_email}>\nTeam:        {team_name}\nAmount:      {amount}\nCharge date: {charge_date}\nCovers:\n{arrears_detail}\n\nWhen GoCardless confirms it, the player's hold is lifted automatically and the parent is emailed. If it fails, the amounts go back to outstanding and both of you are emailed.\n{portal_url}",
                'placeholders' => array( '{player_name}', '{guardian_name}', '{guardian_email}', '{team_name}', '{amount}', '{charge_date}', '{arrears_detail}', '{initiated_line}', '{club_name}', '{portal_url}' ),
            ),
            'arrears_cleared' => array(
                'label'   => 'Payment Hold Lifted (to Parent)',
                'subject' => '{club_name} — {player_name} is back up to date',
                'body'    => "Hi {guardian_name},\n\nGood news — {how_line} {player_name}'s registration with {club_name} is fully up to date and the hold has been lifted, so {player_name} can train and play as normal.{note_line}\n\nYour regular monthly subscription continues as normal. You can see {player_name}'s payment history at any time in your ASTRO Portal:\n{portal_url}\n\nThanks for sorting this so quickly.\n\n{club_name}",
                'placeholders' => array( '{guardian_name}', '{player_name}', '{team_name}', '{how_line}', '{note_line}', '{portal_url}', '{club_name}' ),
            ),
            'arrears_cleared_internal' => array(
                'label'   => 'Payment Hold Lifted (Internal — Treasurer Alert)',
                'subject' => '[{club_name}] Payment hold lifted — {player_name}',
                'body'    => "A payment hold has been lifted.\n\nPlayer:   {player_name}\nGuardian: {guardian_name} <{guardian_email}>\nTeam:     {team_name}\nHow:      {how_line}{note_line}\n\nThe parent has been emailed to confirm the player can train and play again.\n{portal_url}",
                'placeholders' => array( '{player_name}', '{guardian_name}', '{guardian_email}', '{team_name}', '{how_line}', '{note_line}', '{club_name}', '{portal_url}' ),
            ),
            'payment_overdue' => array(
                'label'   => 'Payment Overdue Reminder (to Parent)',
                'subject' => '{club_name} — Reminder: subscription payment outstanding for {player_name}',
                'body'    => "Hi {guardian_name},\n\nThis is a friendly reminder that we have not yet received a confirmed subscription payment for {player_name} ({team_name}) this season.\n\nMonthly subscription: {amount}\n\nWhat to do next:\n{next_steps}\n\nYou can check {player_name}'s registration and payment details at any time in your ASTRO Portal:\n{portal_url}\n\nIf you believe this is an error, or if you'd like to discuss your payment arrangement, please reply to this email or speak to the club treasurer.\n\nThanks,\n{club_name}",
                'placeholders' => array( '{guardian_name}', '{player_name}', '{team_name}', '{amount}', '{next_steps}', '{portal_url}', '{club_name}' ),
            ),
            'mandate_cancelled' => array(
                'label'   => 'Mandate Cancelled (Internal Alert)',
                'subject' => '[{club_name}] Direct Debit cancelled — {player_name}',
                'body'    => "A GoCardless Direct Debit mandate has been cancelled.\n\nPlayer:   {player_name}\nGuardian: {guardian_name} <{guardian_email}>\nTeam:     {team_name}\n\nThe player has been set to inactive. Please log in to the ASTRO Portal to review.\n{portal_url}",
                'placeholders' => array( '{player_name}', '{guardian_name}', '{guardian_email}', '{team_name}', '{club_name}', '{portal_url}' ),
            ),
            'mandate_cancelled_parent' => array(
                'label'   => 'Mandate Cancelled (to Parent)',
                'subject' => '{club_name} — your Direct Debit for {player_name} has been cancelled',
                'body'    => "Hi {guardian_name},\n\nWe've been notified by GoCardless that the Direct Debit for {player_name}'s subscription has been cancelled, so {player_name}'s registration has been paused.\n\nIf you didn't mean to cancel, you can set up a new Direct Debit yourself in a couple of minutes — sign in to your ASTRO Portal and use the \"Set up a new Direct Debit\" button on {player_name}'s card:\n{portal_url}\n\nAny questions, just contact the club and we'll get it sorted.\n\nThanks,\n{club_name}",
                'placeholders' => array( '{guardian_name}', '{player_name}', '{team_name}', '{club_name}', '{portal_url}' ),
            ),
            'dd_reactivated' => array(
                'label'   => 'Direct Debit Reactivated (to Parent)',
                'subject' => '{club_name} — Direct Debit set up, {player_name} is active again',
                'body'    => "Hi {guardian_name},\n\nGreat news — your new Direct Debit for {player_name} is set up and their registration with {club_name} is active again.\n\nMonthly collections resume from the next collection date. If anything is owed for a missed period, the club will be in touch about it separately.\n\nThanks,\n{club_name}",
                'placeholders' => array( '{guardian_name}', '{player_name}', '{team_name}', '{club_name}', '{portal_url}' ),
            ),
            'dd_reactivated_internal' => array(
                'label'   => 'Direct Debit Reactivated (Internal Alert)',
                'subject' => '[{club_name}] Direct Debit reactivated — {player_name}',
                'body'    => "A lapsed Direct Debit has been set back up from the parent portal.\n\nPlayer:   {player_name}\nGuardian: {guardian_name} <{guardian_email}>\nTeam:     {team_name}\nLast confirmed payment before the lapse: {last_payment}\n\nThe player is active again and monthly collections resume from the next cycle. If the family owes for a missed period, collect it manually via GoCardless.\n{portal_url}",
                'placeholders' => array( '{player_name}', '{guardian_name}', '{guardian_email}', '{team_name}', '{last_payment}', '{club_name}', '{portal_url}' ),
            ),
            'consent_changed_safeguarding' => array(
                'label'   => 'Consent Changed (Safeguarding)',
                'subject' => '[{club_name}] Consent change — {player_name}',
                'body'    => "A parent has changed a consent setting in the ASTRO Portal.\n\nPlayer:       {player_name}\nGuardian:     {guardian_name} <{guardian_email}>\nConsent type: {consent_type}\nChanged from: {old_value}\nChanged to:   {new_value}\nDate/time:    {changed_at}\n\nPlease review this change in the ASTRO Portal admin.\n{portal_url}",
                'placeholders' => array( '{player_name}', '{guardian_name}', '{guardian_email}', '{consent_type}', '{old_value}', '{new_value}', '{changed_at}', '{club_name}', '{portal_url}' ),
            ),
            'consent_changed_coach' => array(
                'label'   => 'Consent Changed (Coach Alert)',
                'subject' => '[{club_name}] Consent update for {player_name}',
                'body'    => "Hi {coach_name},\n\nA parent has updated consent settings for a player in your team.\n\nPlayer:       {player_name}\nTeam:         {team_name}\nConsent type: {consent_type}\nNew setting:  {new_value}\n\nPlease ensure this is reflected in any photography or media activity involving this player.\n\n{club_name} ASTRO Portal",
                'placeholders' => array( '{coach_name}', '{player_name}', '{team_name}', '{consent_type}', '{new_value}', '{club_name}' ),
            ),
            'consent_changed_social_media' => array(
                'label'   => 'Consent Changed (Social Media Officer Alert)',
                'subject' => '[{club_name}] Photo/media consent update — {player_name}',
                'body'    => "Hi,\n\nA parent has updated consent settings for a player. Please review the Photo & Media tab in the ASTRO Portal.\n\nPlayer:       {player_name}\nTeam:         {team_name}\nConsent type: {consent_type}\nNew setting:  {new_value}\n\nPlease ensure this player is handled appropriately in all social media and photography.\n\n{club_name} ASTRO Portal\n{portal_url}",
                'placeholders' => array( '{player_name}', '{team_name}', '{consent_type}', '{new_value}', '{club_name}', '{portal_url}' ),
            ),
            'team_news_submitted' => array(
                'label'   => 'Team News Submitted (Reviewer Alert)',
                'subject' => '[{club_name}] Team news awaiting review — {team_name}',
                'body'    => "A coach has submitted a team news post for review.\n\nTeam:  {team_name}\nCoach: {coach_name}\nTitle: {news_title}\n\nReview, edit and publish it from the Team News section of the ASTRO Portal:\n{portal_url}",
                'placeholders' => array( '{coach_name}', '{team_name}', '{news_title}', '{club_name}', '{portal_url}' ),
            ),
            'team_news_decision' => array(
                'label'   => 'Team News Decision (to Coach)',
                'subject' => '{club_name} — your team news post has been {decision}',
                'body'    => "Hi {coach_name},\n\nYour team news post \"{news_title}\" for {team_name} has been {decision}.\n\nReviewer note: {reject_reason}\n\n{post_url}\n\nThanks,\n{club_name}",
                'placeholders' => array( '{coach_name}', '{news_title}', '{team_name}', '{decision}', '{reject_reason}', '{post_url}', '{club_name}' ),
            ),
        );
    }

    // ── Template getter ──────────────────────────────────────────────────────

    public static function get_template( $key ) {
        $defaults = self::default_templates();
        if ( ! isset( $defaults[ $key ] ) ) return null;

        $saved_subject = get_option( 'was_email_subject_' . $key, '' );
        $saved_body    = get_option( 'was_email_body_'    . $key, '' );

        return array(
            'label'        => $defaults[ $key ]['label'],
            'subject'      => $saved_subject ?: $defaults[ $key ]['subject'],
            'body'         => $saved_body    ?: $defaults[ $key ]['body'],
            'placeholders' => $defaults[ $key ]['placeholders'],
        );
    }

    // ── Merge placeholders ───────────────────────────────────────────────────

    private static function merge( $text, $vars ) {
        foreach ( $vars as $key => $val ) {
            $text = str_replace( '{' . $key . '}', $val, $text );
        }
        return $text;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function club_name() {
        return get_bloginfo( 'name' );
    }

    private static function admin_email() {
        return get_option( 'was_admin_email', get_option( 'admin_email' ) );
    }

    /** The address parents are told to contact about money (the ASTRO admin/finance email). */
    public static function finance_email() {
        return self::admin_email();
    }

    /** The club's general inbox: Messages tab "send as the club" uses it as From and Reply-To. */
    public static function club_contact_email() {
        return get_option( 'was_club_contact_email', 'contact@wombourneallstars.co.uk' );
    }

    /** Admin/finance address + every treasurer, de-duplicated. */
    private static function internal_recipients() {
        return array_filter( array_unique( array_merge( array( self::admin_email() ), self::treasurer_emails() ) ) );
    }

    /**
     * The club has paused automatic payment emails to this family (admin player page),
     * e.g. while a hardship or manual arrangement is being handled. Parent copies of
     * payment emails are then not sent; treasurer/admin copies still are, flagged.
     */
    public static function parent_emails_paused( $player ) {
        return ! empty( $player->payment_emails_paused );
    }

    const PAUSED_PREFIX = '[Parent NOT emailed - payment emails paused] ';

    private static function send_internal( $tpl_key, $vars, $subject_prefix = '' ) {
        $tpl = self::get_template( $tpl_key );
        if ( ! $tpl ) return;
        $subject = $subject_prefix . self::merge( $tpl['subject'], $vars );
        $body    = self::merge( $tpl['body'],    $vars );
        foreach ( self::internal_recipients() as $email ) {
            wp_mail( $email, $subject, $body );
        }
    }

    private static function send_parent( $tpl_key, $to, $vars ) {
        $tpl = self::get_template( $tpl_key );
        if ( ! $tpl || ! $to ) return;
        wp_mail( $to, self::merge( $tpl['subject'], $vars ), self::merge( $tpl['body'], $vars ) );
    }

    private static function fmt_date( $ymd ) {
        return $ymd ? date_i18n( 'j F Y', strtotime( $ymd ) ) : 'the next available date';
    }

    private static function fmt_money( $amount ) {
        return '£' . number_format( (float) $amount, 2 );
    }

    /** "  - 1 August 2026: £30.00 (insufficient funds)" lines for a set of failed rows. */
    private static function arrears_detail( $rows ) {
        $lines = array();
        foreach ( (array) $rows as $r ) {
            $why = ! empty( $r->failure_reason ) ? ' — ' . WAS_Arrears::reason_text( $r->failure_reason ) : '';
            $lines[] = '  - ' . self::fmt_date( $r->charge_date ) . ': ' . self::fmt_money( $r->amount ) . $why;
        }
        return $lines ? implode( "\n", $lines ) : '  (no itemised breakdown available)';
    }

    private static function portal_url() {
        return home_url( '/account-portal/' );
    }

    private static function treasurer_emails() {
        $emails = array();
        $users  = get_users( array( 'meta_key' => 'was_roles', 'meta_compare' => 'EXISTS' ) );
        foreach ( $users as $u ) {
            $roles = get_user_meta( $u->ID, 'was_roles', true );
            if ( is_array( $roles ) && in_array( 'treasurer', $roles, true ) ) {
                $emails[] = $u->user_email;
            }
        }
        return array_unique( $emails );
    }

    private static function social_media_emails() {
        $emails = array();
        $users  = get_users( array( 'meta_key' => 'was_roles', 'meta_compare' => 'EXISTS' ) );
        foreach ( $users as $u ) {
            $roles = get_user_meta( $u->ID, 'was_roles', true );
            if ( is_array( $roles ) && in_array( 'social_media', $roles, true ) ) {
                $emails[] = $u->user_email;
            }
        }
        return array_unique( $emails );
    }

    /**
     * Emails of every user holding ANY of the given ASTRO roles. Public — used
     * by the Messages tab audience builder.
     */
    public static function role_emails( array $wanted_roles ) {
        $emails = array();
        $users  = get_users( array( 'meta_key' => 'was_roles', 'meta_compare' => 'EXISTS' ) );
        foreach ( $users as $u ) {
            $roles = get_user_meta( $u->ID, 'was_roles', true );
            if ( is_array( $roles ) && array_intersect( $wanted_roles, $roles ) ) {
                $emails[] = strtolower( trim( $u->user_email ) );
            }
        }
        return array_values( array_unique( array_filter( $emails ) ) );
    }

    /**
     * Send one branded club-message email (Messages tab). Plain-text body is
     * escaped, nl2br'd and its web addresses linked, into the standard ASTRO HTML wrapper.
     * $from_email overrides the site-wide From address (WP Mail SMTP forces noreply@) for this one send.
     */
    public static function send_club_message( $to, $subject, $body_text, $reply_to = '', $from_email = '' ) {
        $club = self::club_name();
        // The same ASTRO logo as the portal's dark header (white wordmark), over https so
        // mail clients will load it. Alt text keeps "ASTRO" visible if images are blocked.
        $logo = set_url_scheme( was_portal_logo_url(), 'https' );
        ob_start(); ?>
<div style="background:#f4f5f7;padding:24px 12px;font-family:'Segoe UI',Arial,sans-serif;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e6e8ec;">
    <div style="background:#14161a;padding:16px 24px;">
      <img src="<?php echo esc_url( $logo ); ?>" width="160" alt="ASTRO" style="display:block;width:160px;max-width:160px;height:auto;border:0;outline:none;text-decoration:none;color:#ffffff;font-size:22px;font-weight:800;letter-spacing:1px;">
      <div style="color:#c9ccd2;font-size:12px;margin-top:8px;"><?php echo esc_html( $club ); ?></div>
    </div>
    <div style="padding:24px;color:#2a2d33;font-size:15px;line-height:1.6;overflow-wrap:break-word;word-break:break-word;">
      <?php echo make_clickable( nl2br( esc_html( $body_text ) ) ); ?>
    </div>
    <div style="background:#fafbfc;padding:14px 24px;border-top:1px solid #eef0f3;color:#8a909a;font-size:11px;">
      <?php echo esc_html( $club ); ?> &middot; Built for our stars. &middot; Sent via the ASTRO Portal<?php if ( $reply_to ) : ?> &middot; reply goes to the sender<?php endif; ?>
    </div>
  </div>
</div>
        <?php
        $html    = ob_get_clean();
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        if ( $reply_to && is_email( $reply_to ) ) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        if ( ! $from_email || ! is_email( $from_email ) ) {
            return wp_mail( $to, $subject, $html, $headers );
        }
        // WP Mail SMTP forces From in the wp_mail_from filter, so set it on PHPMailer after that has run.
        $set_from = function ( $phpmailer ) use ( $from_email ) {
            $phpmailer->setFrom( $from_email, $phpmailer->FromName, false );
        };
        add_action( 'phpmailer_init', $set_from, PHP_INT_MAX );
        $sent = wp_mail( $to, $subject, $html, $headers );
        remove_action( 'phpmailer_init', $set_from, PHP_INT_MAX );
        return $sent;
    }

    private static function coach_emails_for_team( $team_id ) {
        global $wpdb;
        $user_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}was_coach_teams WHERE team_id = %d",
            $team_id
        ) );
        $emails = array();
        foreach ( $user_ids as $uid ) {
            $user = get_userdata( $uid );
            if ( $user ) {
                $emails[ $uid ] = array(
                    'email' => $user->user_email,
                    'name'  => $user->display_name,
                );
            }
        }
        return $emails;
    }

    // ── Registration confirmed ───────────────────────────────────────────────

    public static function registration_confirmed( $player, $guardian ) {
        $tpl = self::get_template( 'registration_confirmed' );
        if ( ! $tpl || ! $guardian->email ) return;

        $vars = array(
            'guardian_name' => $guardian->first_name,
            'player_name'   => trim( $player->first_name . ' ' . $player->last_name ),
            'season'        => was_season(),
            'club_name'     => self::club_name(),
            'portal_url'    => self::portal_url(),
        );

        wp_mail(
            $guardian->email,
            self::merge( $tpl['subject'], $vars ),
            self::merge( $tpl['body'],    $vars )
        );
    }

    // ── Payment failed ───────────────────────────────────────────────────────

    /** Shared placeholder set for the arrears family of emails. */
    private static function player_vars( $player, $guardian ) {
        $team = WAS_Database::get_team( $player->team_id );
        return array(
            'guardian_name'  => $guardian->first_name ?? '',
            'guardian_email' => $guardian->email ?? '',
            'player_name'    => trim( $player->first_name . ' ' . $player->last_name ),
            'team_name'      => $team->name ?? '',
            'club_name'      => self::club_name(),
            'portal_url'     => self::portal_url(),
            'finance_email'  => self::finance_email(),
        );
    }

    /**
     * A collection bounced and GoCardless has accepted our single automatic retry.
     * Parent: what happened, when the retry lands, what happens if that fails too.
     */
    public static function payment_failed_retrying( $player, $guardian, $payment_row, $reason_text, $retry_date ) {
        $vars = self::player_vars( $player, $guardian ) + array(
            'amount'      => self::fmt_money( $payment_row->amount ),
            'charge_date' => self::fmt_date( $payment_row->charge_date ),
            'reason'      => $reason_text,
            'retry_date'  => self::fmt_date( $retry_date ),
        );
        $paused = self::parent_emails_paused( $player );
        if ( ! $paused ) self::send_parent( 'payment_failed', $guardian->email ?? '', $vars );
        self::send_internal( 'payment_failed_internal', $vars, $paused ? self::PAUSED_PREFIX : '' );
    }

    /**
     * The player is now on payment hold. $context: retry_failed | not_retryable | catchup_failed.
     * $summary comes from WAS_Arrears::summary(). The "next steps" are built here so they
     * are always true for this family's situation (mandate present or not).
     */
    public static function payment_arrears( $player, $guardian, $summary, $context, $payment_row ) {
        $first  = $player->first_name;
        $amount = self::fmt_money( $payment_row->amount );
        $due    = self::fmt_date( $payment_row->charge_date );
        $reason = WAS_Arrears::reason_text( $payment_row->failure_reason ?? '' );

        switch ( $context ) {
            case 'retry_failed':
                $intro = "We tried twice to collect the {$amount} subscription for {$first} due on {$due}, and both attempts were returned unpaid by your bank ({$reason}).";
                break;
            case 'catchup_failed':
                $intro = "The catch-up payment of {$amount} for {$first} was returned unpaid by your bank ({$reason}), so the amount it was covering is outstanding again.";
                break;
            default: // not_retryable
                $intro = "The {$amount} subscription for {$first} due on {$due} was returned unpaid by your bank ({$reason}). This is not something we are able to retry automatically.";
        }

        $total_txt = self::fmt_money( $summary['total'] );
        $portal    = self::portal_url();
        $finance   = self::finance_email();

        $dd_missing = empty( $player->gc_mandate_id ) || $player->registration_status !== 'active'
                   || in_array( (string) ( $payment_row->failure_reason ?? '' ), array( 'mandate_cancelled', 'mandate_expired', 'bank_account_closed', 'bank_account_transferred', 'invalid_bank_details', 'direct_debit_not_enabled', 'authorisation_disputed' ), true );

        if ( $dd_missing ) {
            $steps = "1. Set up a new Direct Debit, then pay online: sign in to your ASTRO Portal, press \"Set up a new Direct Debit\" on {$first}'s card (it takes about two minutes), then press \"Pay {$total_txt} now\" on the same card. That takes a one-off collection from the new Direct Debit — no card details needed — and the hold is lifted automatically as soon as it clears.\n{$portal}\n\n"
                   . "2. Or contact the club: email {$finance} to arrange payment another way or to agree a payment plan, and we will reinstate {$first} once it's sorted.";
        } else {
            $steps = "1. Pay online in one click: sign in to your ASTRO Portal and press \"Pay {$total_txt} now\" on {$first}'s card. This takes a one-off collection from your existing Direct Debit — no card details needed — and the hold is lifted automatically as soon as it clears (normally 3–5 working days after the collection).\n{$portal}\n\n"
                   . "2. Or contact the club: email {$finance} to arrange payment another way or to agree a payment plan, and we will reinstate {$first} once it's sorted.";
        }

        $vars = self::player_vars( $player, $guardian ) + array(
            'intro'          => $intro,
            'arrears_total'  => $total_txt,
            'arrears_detail' => self::arrears_detail( $summary['rows'] ),
            'next_steps'     => $steps,
        );
        $paused = self::parent_emails_paused( $player );
        if ( ! $paused ) self::send_parent( 'payment_arrears', $guardian->email ?? '', $vars );
        self::send_internal( 'payment_arrears_internal', $vars, $paused ? self::PAUSED_PREFIX : '' );
    }

    /** A one-off catch-up payment has been created in GoCardless. */
    public static function catchup_scheduled( $player, $guardian, $total, $charge_date, $rows, $initiated_by = 'parent' ) {
        $vars = self::player_vars( $player, $guardian ) + array(
            'amount'         => self::fmt_money( $total ),
            'charge_date'    => self::fmt_date( $charge_date ),
            'arrears_detail' => self::arrears_detail( $rows ),
            'initiated_line' => ( $initiated_by === 'treasurer' ) ? ' by the club treasurer' : '',
        );
        // A parent who pressed "Pay now" themselves still gets their confirmation.
        $paused = ( $initiated_by !== 'parent' ) && self::parent_emails_paused( $player );
        if ( ! $paused ) self::send_parent( 'catchup_scheduled', $guardian->email ?? '', $vars );
        self::send_internal( 'catchup_scheduled_internal', $vars, $paused ? self::PAUSED_PREFIX : '' );
    }

    /** Hold lifted. $how: paid (collected via GoCardless) | manual (treasurer cleared it). */
    public static function arrears_cleared( $player, $guardian, $how = 'paid', $note = '' ) {
        $how_line = ( $how === 'manual' )
            ? 'the club treasurer has confirmed the outstanding subscription for ' . $player->first_name . ' is settled.'
            : 'the outstanding subscription payment for ' . $player->first_name . ' has been received.';
        $vars = self::player_vars( $player, $guardian ) + array(
            'how_line'  => $how_line,
            'note_line' => $note ? "\n\nNote from the treasurer: " . $note : '',
        );
        $paused = self::parent_emails_paused( $player );
        if ( ! $paused ) self::send_parent( 'arrears_cleared', $guardian->email ?? '', $vars );
        self::send_internal( 'arrears_cleared_internal', $vars, $paused ? self::PAUSED_PREFIX : '' );
    }

    // ── Payment overdue reminder (manual, from treasurer dashboard) ──────────

    public static function payment_overdue_reminder( $player, $guardian ) {
        $tpl = self::get_template( 'payment_overdue' );
        if ( ! $tpl || ! $guardian->email ) return;

        $team   = WAS_Database::get_team( $player->team_id );
        $first  = $player->first_name;
        $amount = function_exists( 'was_player_monthly_amount' ) ? (float) was_player_monthly_amount( $player ) : 0.0;

        // Tell the parent exactly how to put it right, based on what we hold for them.
        // Overdue players are still 'active', so the portal's "Set up a new Direct Debit"
        // button (inactive players only) is not available to them — route them to the treasurer.
        $arrears = class_exists( 'WAS_Arrears' ) ? WAS_Arrears::summary( $player ) : array( 'total' => 0, 'rows' => array(), 'inflight' => null );
        if ( empty( $player->gc_mandate_id ) ) {
            $next_steps = "We don't have a Direct Debit set up for {$first}, so no payment can be collected yet. "
                        . "Please reply to this email or contact " . self::finance_email() . " and we'll send you a secure GoCardless link to set one up — it takes about two minutes.";
        } elseif ( $arrears['total'] > 0 && $arrears['inflight'] ) {
            $next_steps = "A catch-up collection of " . self::fmt_money( $arrears['inflight']->amount ) . " is already on its way and should leave your account on or around "
                        . self::fmt_date( $arrears['inflight']->charge_date ) . ". Please make sure the funds are available — nothing else is needed.";
        } elseif ( $arrears['total'] > 0 ) {
            $next_steps = "The following collections were returned unpaid by your bank:\n" . self::arrears_detail( $arrears['rows'] ) . "\n"
                        . "Pay the outstanding " . self::fmt_money( $arrears['total'] ) . " in one click: sign in to your ASTRO Portal and press \"Pay now\" on {$first}'s card. "
                        . "That takes a one-off collection from your existing Direct Debit — no card details needed. Or contact " . self::finance_email() . " to arrange it another way.";
        } else {
            $next_steps = "Your Direct Debit for {$first} is set up with GoCardless, but no payment has been confirmed yet. "
                        . "Please check the Direct Debit hasn't been cancelled with your bank and that funds are available for the next collection date. "
                        . "If you've recently cancelled it, reply to this email and we'll help you set up a new one.";
        }

        $vars = array(
            'guardian_name' => $guardian->first_name,
            'player_name'   => trim( $player->first_name . ' ' . $player->last_name ),
            'team_name'     => $team->name ?? '',
            'amount'        => '£' . number_format( $amount, 2 ) . ' per month',
            'next_steps'    => $next_steps,
            'portal_url'    => self::portal_url(),
            'club_name'     => self::club_name(),
        );

        wp_mail( $guardian->email, self::merge( $tpl['subject'], $vars ), self::merge( $tpl['body'], $vars ) );
    }

    // ── Second-team request (parent asks to add an existing child to another team) ──

    public static function second_team_request( $guardian, $child_name, $team ) {
        $club  = self::club_name();
        $gname = trim( ( $guardian->first_name ?? '' ) . ' ' . ( $guardian->last_name ?? '' ) );

        $subject = '[' . $club . '] Second-team request — ' . $child_name;
        $body = "A parent has requested adding an already-registered child to another team.\n\n"
              . "Child:   " . $child_name . "\n"
              . "Team:    " . ( $team->name ?? '' ) . "\n"
              . "Parent:  " . $gname . " <" . ( $guardian->email ?? '' ) . ">\n\n"
              . "Add the child to this team via ASTRO > Players > edit the child > Additional Teams. "
              . "Do NOT create a second registration — that would set up a duplicate Direct Debit.\n\n"
              . self::portal_url();

        $recipients = array_unique( array_merge( array( self::admin_email() ), self::treasurer_emails() ) );
        foreach ( $recipients as $email ) {
            if ( $email ) wp_mail( $email, $subject, $body );
        }
    }

    // ── Late sign-up (DD rolled + one-off taken) ─────────────────────────────

    /**
     * The parent registered after the Bacs cut-off for the target first-payment date,
     * so their monthly Direct Debit was rolled forward and a one-off for the missed
     * month was taken. Emails the parent their schedule and alerts treasurer + admin.
     *
     * $info = array( 'sub_start', 'one_off_date', 'one_off_ok', 'amount' )
     * plus, for a mid-season sign-up, 'prorata' => true with 'prorata_from',
     * 'one_off_amount' and 'one_off_waived' (see midseason_signup_alert()).
     */
    public static function late_signup_alert( $player, $guardian, $info ) {
        if ( ! empty( $info['prorata'] ) ) {
            self::midseason_signup_alert( $player, $guardian, $info );
            return;
        }
        $club  = self::club_name();
        $child = trim( ( $player->first_name ?? '' ) . ' ' . ( $player->last_name ?? '' ) );
        $amt   = '£' . number_format( (float) ( $info['amount'] ?? 0 ), 2 );
        $subd  = ! empty( $info['sub_start'] )    ? date_i18n( 'j F Y', strtotime( $info['sub_start'] ) )    : '';
        $offd  = ! empty( $info['one_off_date'] ) ? date_i18n( 'j F Y', strtotime( $info['one_off_date'] ) ) : '';
        $ok    = ! empty( $info['one_off_ok'] ) && $offd !== '';

        // ── Parent ──
        if ( ! empty( $guardian->email ) ) {
            $gname = $guardian->first_name ?? '';
            if ( $ok ) {
                $pbody = "Hi {$gname},\n\n"
                    . "Thank you for registering {$child}. Because you signed up after this month's Direct Debit cut-off, here is exactly how your payments will work:\n\n"
                    . "  - A one-off first payment of {$amt} will be collected on {$offd}.\n"
                    . "  - Your regular monthly Direct Debit of {$amt} will then start on {$subd}, and monthly after that.\n\n"
                    . "That keeps you fully up to date - there is nothing else you need to do. If you have any questions, just reply to this email.\n\nThanks,\n{$club}";
            } else {
                $pbody = "Hi {$gname},\n\n"
                    . "Thank you for registering {$child}. Because you signed up after this month's Direct Debit cut-off, your regular monthly Direct Debit of {$amt} will start on {$subd}, and monthly after that.\n\n"
                    . "The club will be in touch shortly about collecting this month's payment. If you have any questions, just reply to this email.\n\nThanks,\n{$club}";
            }
            wp_mail( $guardian->email, $club . ' - your first payment for ' . $child, $pbody );
        }

        // ── Treasurer + admin ──
        $gfull = trim( ( $guardian->first_name ?? '' ) . ' ' . ( $guardian->last_name ?? '' ) );
        if ( $ok ) {
            $ibody = "A parent registered after the Direct Debit cut-off for the target first-payment date. Handled automatically:\n\n"
                . "Child:                 {$child}\n"
                . "Parent:                {$gfull} <" . ( $guardian->email ?? '' ) . ">\n"
                . "One-off first payment: {$amt} on {$offd}\n"
                . "Monthly DD starts:     {$subd}\n\n"
                . "Both were set up via GoCardless - no action needed unless a payment fails.\n" . self::portal_url();
        } else {
            $ibody = "A parent registered after the Direct Debit cut-off for the target first-payment date. ACTION NEEDED:\n\n"
                . "Child:             {$child}\n"
                . "Parent:            {$gfull} <" . ( $guardian->email ?? '' ) . ">\n"
                . "Monthly DD starts: {$subd}\n"
                . "One-off first payment of {$amt}: AUTOMATIC COLLECTION FAILED - please take this manually (GoCardless or Square).\n\n" . self::portal_url();
        }
        $recipients = array_unique( array_merge( array( self::admin_email() ), self::treasurer_emails() ) );
        foreach ( $recipients as $email ) {
            if ( $email ) wp_mail( $email, '[' . $club . '] Late sign-up - ' . $child, $ibody );
        }
    }

    /**
     * Mid-season sign-up: a one-off pro-rata payment from the sign-up date to the 1st
     * the monthly Direct Debit starts on. Emails the parent their schedule and tells
     * treasurer + admin (who must act only if the one-off could not be set up).
     */
    public static function midseason_signup_alert( $player, $guardian, $info ) {
        $club  = self::club_name();
        $child = trim( ( $player->first_name ?? '' ) . ' ' . ( $player->last_name ?? '' ) );
        $mamt  = '£' . number_format( (float) ( $info['amount'] ?? 0 ), 2 );
        $pamt  = '£' . number_format( (float) ( $info['one_off_amount'] ?? 0 ), 2 );
        $subd  = ! empty( $info['sub_start'] )    ? date_i18n( 'j F Y', strtotime( $info['sub_start'] ) )    : '';
        $offd  = ! empty( $info['one_off_date'] ) ? date_i18n( 'j F Y', strtotime( $info['one_off_date'] ) ) : '';
        $from  = ! empty( $info['prorata_from'] ) ? date_i18n( 'j F', strtotime( $info['prorata_from'] ) )   : '';
        $to    = ! empty( $info['sub_start'] )    ? date_i18n( 'j F', strtotime( $info['sub_start'] . ' -1 day' ) ) : '';
        $span  = ( $from && $to ) ? " (covering {$from} to {$to})" : '';
        $state = ! empty( $info['one_off_waived'] ) ? 'waived' : ( ( ! empty( $info['one_off_ok'] ) && $offd !== '' ) ? 'ok' : 'failed' );

        // ── Parent ──
        if ( ! empty( $guardian->email ) ) {
            $gname = $guardian->first_name ?? '';
            if ( $state === 'ok' ) {
                $first = "  - A one-off part-month payment of {$pamt}{$span} will be collected on {$offd}.\n";
            } elseif ( $state === 'waived' ) {
                $first = "  - There is nothing to pay for the rest of this month.\n";
            } else {
                $first = "  - The club will be in touch about a part-month payment of {$pamt}{$span}.\n";
            }
            $pbody = "Hi {$gname},\n\n"
                . "Thank you for registering {$child}. Club subs are collected by Direct Debit on the 1st of each month. As {$child} has joined part-way through the month, here is exactly how your payments will work:\n\n"
                . $first
                . "  - Your monthly Direct Debit of {$mamt} then starts on {$subd}, and is collected on the 1st of every month after that.\n\n"
                . "There is nothing else you need to do. If you have any questions, just reply to this email.\n\nThanks,\n{$club}";
            wp_mail( $guardian->email, $club . ' - your first payment for ' . $child, $pbody );
        }

        // ── Treasurer + admin ──
        $gfull = trim( ( $guardian->first_name ?? '' ) . ' ' . ( $guardian->last_name ?? '' ) );
        if ( $state === 'ok' ) {
            $line = "Pro-rata payment:  {$pamt}{$span} on {$offd}\n";
            $tail = "Both were set up via GoCardless - no action needed unless a payment fails.\n";
        } elseif ( $state === 'waived' ) {
            $line = "Pro-rata payment:  {$pamt}{$span} - waived (under £1)\n";
            $tail = "No action needed.\n";
        } else {
            $line = "Pro-rata payment:  {$pamt}{$span} - AUTOMATIC COLLECTION FAILED, please take this manually (GoCardless or Square).\n";
            $tail = "ACTION NEEDED for the pro-rata payment above.\n";
        }
        $ibody = "A parent signed up mid-season.\n\n"
            . "Child:             {$child}\n"
            . "Parent:            {$gfull} <" . ( $guardian->email ?? '' ) . ">\n"
            . $line
            . "Monthly DD:        {$mamt} from {$subd} (1st of each month)\n\n"
            . $tail . self::portal_url();
        $recipients = array_unique( array_merge( array( self::admin_email() ), self::treasurer_emails() ) );
        foreach ( $recipients as $email ) {
            if ( $email ) wp_mail( $email, '[' . $club . '] Mid-season sign-up - ' . $child, $ibody );
        }
    }

    // ── Mandate cancelled (webhook) ──────────────────────────────────────────

    public static function mandate_cancelled( $player, $guardian ) {
        $tpl = self::get_template( 'mandate_cancelled' );
        if ( ! $tpl ) return;

        global $wpdb;
        $team = WAS_Database::get_team( $player->team_id );

        $vars = array(
            'player_name'    => trim( $player->first_name . ' ' . $player->last_name ),
            'guardian_name'  => trim( ( $guardian->first_name ?? '' ) . ' ' . ( $guardian->last_name ?? '' ) ),
            'guardian_email' => $guardian->email ?? '',
            'team_name'      => $team->name ?? '',
            'club_name'      => self::club_name(),
            'portal_url'     => self::portal_url(),
        );

        $paused  = self::parent_emails_paused( $player );
        $subject = ( $paused ? self::PAUSED_PREFIX : '' ) . self::merge( $tpl['subject'], $vars );
        $body    = self::merge( $tpl['body'],    $vars );

        $recipients = array_unique( array_merge(
            array( self::admin_email() ),
            self::treasurer_emails()
        ) );
        foreach ( $recipients as $email ) {
            if ( $email ) wp_mail( $email, $subject, $body );
        }

        // Parent is told too (2.11.0 — previously internal-only)
        if ( ! empty( $guardian->email ) && ! $paused ) {
            $ptpl = self::get_template( 'mandate_cancelled_parent' );
            if ( $ptpl ) {
                wp_mail(
                    $guardian->email,
                    self::merge( $ptpl['subject'], $vars ),
                    self::merge( $ptpl['body'],    $vars )
                );
            }
        }
    }

    // ── Direct Debit updated (subscription replaced) ─────────────────────────

    /**
     * ASTRO replaced a child's GoCardless subscription (new amount and/or payment day).
     * GoCardless itself emails the parent that the OLD subscription "has been cancelled",
     * which reads like the Direct Debit has stopped — so explain that it's an update.
     * Not sent when payment emails are paused for the family.
     *
     * $info = array( 'old_pence', 'new_pence', 'day', 'first_charge' (YYYY-MM-DD),
     *                'bridge' => array( 'date', 'pence' ) | null )
     */
    public static function dd_updated( $player, $guardian, $info ) {
        if ( empty( $guardian->email ) || self::parent_emails_paused( $player ) ) return false;

        $child  = trim( $player->first_name . ' ' . $player->last_name );
        $first  = $player->first_name;
        $old    = self::fmt_money( ( $info['old_pence'] ?? 0 ) / 100 );
        $new    = self::fmt_money( ( $info['new_pence'] ?? 0 ) / 100 );
        $day    = was_ordinal( (int) ( $info['day'] ?? 1 ) );
        $amount_line = ( (int) ( $info['old_pence'] ?? 0 ) !== (int) ( $info['new_pence'] ?? 0 ) )
            ? "Monthly amount: {$new} (was {$old})."
            : "Monthly amount: {$new} (unchanged).";

        $lines = "  • {$amount_line}\n  • Payment date: the {$day} of each month.\n";
        if ( ! empty( $info['bridge'] ) ) {
            $lines .= '  • ' . date_i18n( 'F', strtotime( $info['bridge']['date'] ) ) . "'s payment of " . self::fmt_money( $info['bridge']['pence'] / 100 )
                . ' will still be collected on ' . date_i18n( 'l j F', strtotime( $info['bridge']['date'] ) ) . " (this may show as a one-off payment).\n";
        }
        if ( ! empty( $info['first_charge'] ) ) {
            $lines .= "  • First payment on the updated schedule: {$new} on " . date_i18n( 'l j F Y', strtotime( $info['first_charge'] ) ) . ".\n";
        }

        $body = 'Hi ' . ( $guardian->first_name ?? '' ) . ",\n\n"
            . "We've updated the Direct Debit for {$child}. You may get an email from GoCardless, our Direct Debit provider, saying your subscription of {$old} monthly has been cancelled. That only refers to the old payment schedule, which has been replaced by the one below. Your Direct Debit has not been cancelled, and there's nothing you need to do.\n\n"
            . "{$first}'s Direct Debit:\n" . $lines
            . "\nPlease don't cancel the Direct Debit with your bank. If anything looks wrong, just reply to this email.\n\n"
            . "Thanks,\n" . self::club_name();

        return self::send_club_message( $guardian->email, "{$child}'s Direct Debit has been updated (not cancelled)", $body, self::finance_email() );
    }

    // ── Direct Debit reactivated (portal re-subscribe) ───────────────────────

    public static function dd_reactivated( $player, $guardian ) {
        global $wpdb;
        $team = WAS_Database::get_team( $player->team_id );
        $last = $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(charge_date) FROM {$wpdb->prefix}was_payments
             WHERE player_id = %d AND status IN ('confirmed','paid_out')",
            $player->id
        ) );

        $vars = array(
            'player_name'    => trim( $player->first_name . ' ' . $player->last_name ),
            'guardian_name'  => trim( ( $guardian->first_name ?? '' ) . ' ' . ( $guardian->last_name ?? '' ) ),
            'guardian_email' => $guardian->email ?? '',
            'team_name'      => $team->name ?? '',
            'last_payment'   => $last ? date_i18n( 'j F Y', strtotime( $last ) ) : 'none recorded',
            'club_name'      => self::club_name(),
            'portal_url'     => self::portal_url(),
        );

        if ( ! empty( $guardian->email ) ) {
            $tpl = self::get_template( 'dd_reactivated' );
            if ( $tpl ) wp_mail( $guardian->email, self::merge( $tpl['subject'], $vars ), self::merge( $tpl['body'], $vars ) );
        }

        $itpl = self::get_template( 'dd_reactivated_internal' );
        if ( $itpl ) {
            $recipients = array_unique( array_merge( array( self::admin_email() ), self::treasurer_emails() ) );
            foreach ( $recipients as $email ) {
                if ( $email ) wp_mail( $email, self::merge( $itpl['subject'], $vars ), self::merge( $itpl['body'], $vars ) );
            }
        }
    }

    // ── Consent changed ──────────────────────────────────────────────────────

    public static function consent_changed( $player, $guardian, $consent_type, $old_val, $new_val ) {
        $team        = WAS_Database::get_team( $player->team_id );
        if ( $consent_type === 'photography' ) {
            $type_label = 'Photography & Social Media';
            $old_label  = $old_val === 'agree' ? 'Agreed' : 'Declined';
            $new_label  = $new_val === 'agree' ? 'Agreed' : 'Declined';
        } else {
            // "agree" = no family, care or legal proceedings declared; "disagree" = proceedings declared.
            $type_label = 'Family, Care or Legal Proceedings';
            $old_label  = $old_val === 'agree' ? 'None declared (OK for club media)' : 'Proceedings declared (do not photograph or share)';
            $new_label  = $new_val === 'agree' ? 'None declared (OK for club media)' : 'Proceedings declared (do not photograph or share)';
        }
        $changed_at  = current_time( 'D j M Y \a\t H:i' );

        $player_name   = trim( $player->first_name . ' ' . $player->last_name );
        $guardian_name = trim( ( $guardian->first_name ?? '' ) . ' ' . ( $guardian->last_name ?? '' ) );

        $vars = array(
            'player_name'    => $player_name,
            'guardian_name'  => $guardian_name,
            'guardian_email' => $guardian->email ?? '',
            'team_name'      => $team->name ?? '',
            'consent_type'   => $type_label,
            'old_value'      => $old_label,
            'new_value'      => $new_label,
            'changed_at'     => $changed_at,
            'club_name'      => self::club_name(),
            'portal_url'     => self::portal_url(),
        );

        // 1. Safeguarding email
        $safeguarding = get_option( 'was_safeguarding_email', '' );
        $tpl_safe = self::get_template( 'consent_changed_safeguarding' );
        if ( $tpl_safe && $safeguarding ) {
            wp_mail( $safeguarding, self::merge( $tpl_safe['subject'], $vars ), self::merge( $tpl_safe['body'], $vars ) );
        }

        // 2. Coach(es) for this player's team only
        $tpl_coach = self::get_template( 'consent_changed_coach' );
        if ( $tpl_coach && $player->team_id ) {
            $coaches = self::coach_emails_for_team( $player->team_id );
            foreach ( $coaches as $coach ) {
                $coach_vars = array_merge( $vars, array( 'coach_name' => $coach['name'] ) );
                wp_mail( $coach['email'], self::merge( $tpl_coach['subject'], $coach_vars ), self::merge( $tpl_coach['body'], $coach_vars ) );
            }
        }

        // 3. All social media officers
        $tpl_smo = self::get_template( 'consent_changed_social_media' );
        if ( $tpl_smo ) {
            $smo_emails = self::social_media_emails();
            foreach ( $smo_emails as $email ) {
                wp_mail( $email, self::merge( $tpl_smo['subject'], $vars ), self::merge( $tpl_smo['body'], $vars ) );
            }
        }
    }

    // ── Team news: coach submission alert (to reviewers) ─────────────────────

    public static function team_news_submitted( $post_id, $team ) {
        $tpl = self::get_template( 'team_news_submitted' );
        if ( ! $tpl ) return;

        $coach = get_userdata( (int) get_post_meta( $post_id, '_was_news_coach_id', true ) );

        $vars = array(
            'coach_name' => $coach ? $coach->display_name : '',
            'team_name'  => $team->name ?? '',
            'news_title' => get_the_title( $post_id ),
            'club_name'  => self::club_name(),
            'portal_url' => self::portal_url(),
        );

        $subject = self::merge( $tpl['subject'], $vars );
        $body    = self::merge( $tpl['body'],    $vars );

        $recipients = array_unique( array_merge(
            array( self::admin_email() ),
            self::social_media_emails(),
            self::treasurer_emails()
        ) );
        foreach ( $recipients as $email ) {
            if ( $email ) wp_mail( $email, $subject, $body );
        }
    }

    // ── Team news: approve/reject decision (to the submitting coach) ─────────

    public static function team_news_decision( $post_id, $decision, $reason = '' ) {
        $tpl = self::get_template( 'team_news_decision' );
        if ( ! $tpl ) return;

        $coach = get_userdata( (int) get_post_meta( $post_id, '_was_news_coach_id', true ) );
        if ( ! $coach || ! $coach->user_email ) return;

        $team_id  = (int) get_post_meta( $post_id, '_was_news_team_id', true );
        $team     = $team_id ? WAS_Database::get_team( $team_id ) : null;
        $approved = ( $decision === 'approved' );

        $vars = array(
            'coach_name'    => $coach->display_name,
            'news_title'    => get_the_title( $post_id ),
            'team_name'     => $team->name ?? '',
            'decision'      => $approved ? 'approved and published' : 'not approved',
            'reject_reason' => $reason !== '' ? $reason : '—',
            'post_url'      => $approved ? get_permalink( $post_id ) : self::portal_url(),
            'club_name'     => self::club_name(),
        );

        wp_mail(
            $coach->user_email,
            self::merge( $tpl['subject'], $vars ),
            self::merge( $tpl['body'],    $vars )
        );
    }

    // ── Account deletion requested (from the app) ─────────────────────────────

    /**
     * Tell the registration officers, treasurers and the club admin that a member has
     * asked for their account to be deleted, with what to do next. The account is already
     * closed; nothing has been deleted.
     *
     * @param WP_User $user         The account.
     * @param array   $children     Rows: first_name, season, registration_status, team_name, gc_mandate_id, gc_subscription_id.
     * @param bool    $has_family   Whether a family record is linked to the account.
     */
    public static function account_deletion_requested( $user, $children, $has_family = true ) {
        $club = self::club_name();
        $name = trim( $user->first_name . ' ' . $user->last_name );
        if ( $name === '' ) $name = $user->display_name;
        $when = wp_date( 'j F Y \a\t H:i', time(), new DateTimeZone( 'Europe/London' ) );

        $lines = array();
        foreach ( (array) $children as $c ) {
            $who  = trim( (string) $c->first_name ) !== '' ? trim( (string) $c->first_name ) : 'Unfinished registration';
            $team = trim( (string) $c->team_name ) !== '' ? trim( (string) $c->team_name ) : 'no team';
            $dd   = ( (string) $c->gc_mandate_id !== '' || (string) $c->gc_subscription_id !== '' ) ? ' — has a Direct Debit on file' : '';
            $lines[] = '  - ' . $who . ' — ' . $team . ' (' . $c->season . ', ' . $c->registration_status . ')' . $dd;
        }
        if ( ! $lines ) {
            $lines[] = $has_family ? '  (no children on this account)' : '  (no family record is linked to this account)';
        }

        $roles = array_values( array_diff( was_get_roles( $user->ID ), array( 'parent' ) ) );

        $subject = '[' . $club . '] Account deletion request — ' . $name;
        $body = "Hello,\n\n"
              . $name . ' (' . $user->user_email . ") has asked, in the Allstars app, for their ASTRO account to be closed and their personal data deleted. They asked on " . $when . " (UK time).\n\n"
              . "Already done:\n"
              . "  - The account can no longer be used to sign in to the website or the app, and it has been signed out everywhere.\n"
              . "  - Nothing has been deleted yet.\n\n"
              . "Children on this account:\n"
              . implode( "\n", $lines ) . "\n\n"
              . ( $roles ? 'Club roles on this account: ' . implode( ', ', $roles ) . "\n\n" : '' )
              . "What to do next:\n"
              . "  1. Cancel any Direct Debit for these children in GoCardless so no more payments are taken. Check first whether anything is still owed or due back.\n"
              . "  2. Check what the club has to keep. FA registration records and any safeguarding records may have to be kept for a set time - ask the club welfare officer before removing those.\n"
              . "  3. Then delete the rest of their personal data: the website account, the family record, the children's details, medical and emergency contact information, and photos.\n"
              . "  4. Email " . $user->user_email . " to confirm it is done, saying what (if anything) has been kept and why.\n\n"
              . 'Account ID: ' . $user->ID . " (WordPress > Users)\n\n"
              . "This email was sent automatically by ASTRO.";

        $recipients = array_merge(
            self::role_emails( array( 'registration_officer', 'treasurer' ) ),
            array( strtolower( trim( (string) self::admin_email() ) ), strtolower( trim( (string) get_option( 'admin_email' ) ) ) )
        );
        foreach ( array_unique( array_filter( $recipients, 'is_email' ) ) as $email ) {
            wp_mail( $email, $subject, $body );
        }
    }
}
