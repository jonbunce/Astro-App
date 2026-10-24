<?php defined( 'ABSPATH' ) || exit;

global $wpdb;
$player_id = absint( $_GET['player_id'] ?? 0 );
$is_new    = ( $player_id === 0 || $_GET['player_id'] === 'new' );
$notice    = '';
$error     = '';

$teams = WAS_Database::get_teams();

// ── Save handler ──────────────────────────────────────────────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_edit_nonce'] ) ) {
    check_admin_referer( 'was_edit_player', 'was_edit_nonce' );
    $_POST = wp_unslash( $_POST ); // strip WordPress' added slashes before sanitising

    $dob_raw = sanitize_text_field( $_POST['dob'] ?? '' );
    $dob     = ( $dob_raw && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dob_raw ) ) ? $dob_raw : null;

    $data = array(
        'first_name'          => sanitize_text_field( $_POST['first_name'] ?? '' ),
        'last_name'           => sanitize_text_field( $_POST['last_name']  ?? '' ),
        'dob'                 => $dob,
        'team_id'             => absint( $_POST['team_id'] ?? 0 ),
        'participation_type'  => sanitize_key( $_POST['participation_type'] ?? 'signed' ),
        'payment_type'        => sanitize_key( $_POST['payment_type'] ?? 'gocardless' ),
        'registration_status' => sanitize_key( $_POST['registration_status'] ?? 'active' ),
        'notes'               => sanitize_textarea_field( $_POST['notes'] ?? '' ),
    );

    // GoCardless IDs: only save a value the admin actually changed on this form. The
    // form can be stale — adding/removing a team on this page replaces the Direct Debit
    // via AJAX, and saving afterwards must not put the old (cancelled) subscription ID
    // back, or ASTRO loses track of the live one.
    foreach ( array( 'gc_mandate_id', 'gc_subscription_id', 'gc_customer_id' ) as $gc_field ) {
        $posted = sanitize_text_field( $_POST[ $gc_field ] ?? '' );
        if ( $is_new || $posted !== sanitize_text_field( $_POST[ $gc_field . '_orig' ] ?? '' ) ) {
            $data[ $gc_field ] = $posted;
        }
    }

    // Handle photo upload (real JPG / PNG / WebP only, re-encoded so no EXIF/GPS data is kept)
    if ( ! empty( $_FILES['player_photo']['tmp_name'] ) ) {
        $file = $_FILES['player_photo'];
        if ( $file['size'] <= 8 * 1024 * 1024 ) {
            $saved = was_save_uploaded_image( $file, was_photo_dir(), 'player-' . ( $is_new ? 'new-' . time() : $player_id ) . '-' . time() );
            if ( is_wp_error( $saved ) ) {
                $error = $saved->get_error_message();
            } else {
                // Remove old photo
                if ( ! $is_new && ! empty( $existing_player->photo_path ) ) {
                    $old = was_photo_dir() . basename( $existing_player->photo_path );
                    if ( file_exists( $old ) ) @unlink( $old );
                }
                $data['photo_path'] = $saved['file'];
            }
        } else {
            $error = 'Photo must be JPG, PNG or WebP under 8MB.';
        }
    }

    if ( ! $error ) {
        if ( $is_new ) {
            $data['season'] = was_season();
            $data['registration_token'] = '';
            $wpdb->insert( "{$wpdb->prefix}was_players", $data );
            $player_id = $wpdb->insert_id;
            $is_new    = false;
            $notice    = 'Player created.';
        } else {
            $before = WAS_Database::get_player( $player_id );
            $wpdb->update( "{$wpdb->prefix}was_players", $data, array( 'id' => $player_id ) );
            $notice = 'Player updated.';

            // Billing follows the record: once the club no longer collects for this child
            // (Train Only, not Active, or Exempt / Team Direct), cancel their live Direct
            // Debit subscription so GoCardless stops collecting. The mandate is kept, so a
            // new subscription can be set up later without the parent signing up again.
            $sub_id   = $data['gc_subscription_id'] ?? ( $before->gc_subscription_id ?? '' );
            $collects = ( $data['participation_type'] === 'signed'
                && $data['registration_status'] === 'active'
                && in_array( $data['payment_type'], array( 'gocardless', 'sibling_rate' ), true ) );
            if ( ! $collects && $sub_id ) {
                $live = WAS_GoCardless::get_subscription( $sub_id );
                if ( ! is_array( $live ) ) {
                    $error = 'The club no longer collects for this child, but ASTRO could not check their Direct Debit subscription (' . $sub_id . ') with GoCardless. Please check it in GoCardless and cancel it if it is still active.';
                } elseif ( in_array( $live['status'] ?? '', array( 'active', 'paused', 'pending_customer_approval' ), true ) ) {
                    if ( WAS_GoCardless::cancel_subscription( $sub_id ) ) {
                        $wpdb->update( "{$wpdb->prefix}was_players", array( 'gc_subscription_id' => '' ), array( 'id' => $player_id ) );
                        $notice .= ' The club no longer collects for this child, so their Direct Debit subscription (£' . number_format( ( $live['amount'] ?? 0 ) / 100, 2 ) . '/month) was cancelled. The mandate was kept. GoCardless will email the parent to confirm.';
                    } else {
                        $error = 'The club no longer collects for this child, but their Direct Debit subscription (' . $sub_id . ') could not be cancelled automatically. Please cancel it in GoCardless to stop the next collection.';
                    }
                }
            }
        }

        // Guardian
        $g_name  = sanitize_text_field( $_POST['g_name'] ?? '' );
        $g_parts = explode( ' ', $g_name, 2 );
        $g_data  = array(
            'first_name'          => $g_parts[0],
            'last_name'           => $g_parts[1] ?? '',
            'email'               => sanitize_email( $_POST['g_email'] ?? '' ),
            'telephone'           => sanitize_text_field( $_POST['g_telephone'] ?? '' ),
            'address_line1'       => sanitize_text_field( $_POST['g_address'] ?? '' ),
            'postcode'            => strtoupper( sanitize_text_field( $_POST['g_postcode'] ?? '' ) ),
            'occupation_category' => sanitize_text_field( $_POST['g_occupation'] ?? '' ),
            'employer_name'       => sanitize_text_field( $_POST['g_employer'] ?? '' ),
        );
        $guardian_id = absint( $_POST['guardian_id'] ?? 0 );
        if ( $guardian_id ) {
            $wpdb->update( "{$wpdb->prefix}was_guardians", $g_data, array('id'=>$guardian_id) );
        } elseif ( $g_data['email'] ) {
            $existing_g = WAS_Database::get_guardian_by_email( $g_data['email'] );
            if ( $existing_g ) {
                $wpdb->update( "{$wpdb->prefix}was_guardians", $g_data, array('id'=>$existing_g->id) );
                $guardian_id = $existing_g->id;
            } else {
                $wpdb->insert( "{$wpdb->prefix}was_guardians", $g_data );
                $guardian_id = $wpdb->insert_id;
            }
            $wpdb->update( "{$wpdb->prefix}was_players", array('primary_guardian_id'=>$guardian_id), array('id'=>$player_id) );
        }

        // Emergency contact
        $ec_name = sanitize_text_field( $_POST['ec_name'] ?? '' );
        $ec_tel  = sanitize_text_field( $_POST['ec_tel']  ?? '' );
        $ec_exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$wpdb->prefix}was_emergency_contacts WHERE player_id=%d", $player_id) );
        if ( $ec_name ) {
            $ec_data = array(
                'contact_name'       => $ec_name,
                'contact_telephone'  => $ec_tel,
                'contact_occupation' => sanitize_text_field( $_POST['ec_occupation'] ?? '' ),
                'contact_employer'   => sanitize_text_field( $_POST['ec_employer'] ?? '' ),
            );
            if ($ec_exists) $wpdb->update("{$wpdb->prefix}was_emergency_contacts",$ec_data,array('player_id'=>$player_id));
            else $wpdb->insert("{$wpdb->prefix}was_emergency_contacts",array_merge(array('player_id'=>$player_id),$ec_data));
        }

        // Medical
        $med = array(
            'consent_given'          => isset($_POST['med_consent']) ? 1 : 0,
            'health_needs'           => sanitize_textarea_field($_POST['health_needs']           ?? ''),
            'current_medication'     => sanitize_textarea_field($_POST['medication']             ?? ''),
            'medical_training_needs' => sanitize_textarea_field($_POST['medical_training']       ?? ''),
            'access_needs'           => sanitize_textarea_field($_POST['access_needs']           ?? ''),
            'communication_needs'    => sanitize_textarea_field($_POST['communication_needs']    ?? ''),
        );
        $has_med = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}was_medical WHERE player_id=%d",$player_id));
        if ($has_med) $wpdb->update("{$wpdb->prefix}was_medical",$med,array('player_id'=>$player_id));
        else $wpdb->insert("{$wpdb->prefix}was_medical",array_merge(array('player_id'=>$player_id),$med));

        // Consents
        foreach ( array('photography','legal_proceedings','medical_data','privacy_policy') as $ct ) {
            $val = sanitize_key( $_POST['consent_' . $ct] ?? 'disagree' );
            $wpdb->delete("{$wpdb->prefix}was_consents",array('player_id'=>$player_id,'consent_type'=>$ct));
            $wpdb->insert("{$wpdb->prefix}was_consents",array('player_id'=>$player_id,'consent_type'=>$ct,'consent_value'=>$val,'ip_address'=>'admin'));
        }
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$player   = $is_new ? null : WAS_Database::get_player( $player_id );
$guardian = ( $player && $player->primary_guardian_id ) ? WAS_Database::get_guardian( $player->primary_guardian_id ) : null;
$medical  = $player ? WAS_Database::get_medical( $player_id ) : null;
$consents = $player ? WAS_Database::get_consents( $player_id ) : array();
$ec       = $player ? $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}was_emergency_contacts WHERE player_id=%d",$player_id) ) : null;
$existing_player = $player;

$photo_url = ( $player && $player->photo_path ) ? was_photo_url( $player_id ) : '';
?>
<div class="wrap was-admin-wrap">
    <h1><?php echo $is_new ? 'Add Player' : 'Edit Player: ' . esc_html( trim( ($player->first_name ?? '') . ' ' . ($player->last_name ?? '') ) ); ?></h1>
    <p><a href="<?php echo admin_url('admin.php?page=was-players'); ?>">&larr; Back to players</a></p>

    <?php if ($notice): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
    <?php if ($error):  ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('was_edit_player','was_edit_nonce'); ?>
        <input type="hidden" name="guardian_id" value="<?php echo esc_attr($guardian->id ?? 0); ?>">

        <div class="was-edit-grid">

            <!-- Left column -->
            <div class="was-edit-col">

                <div class="postbox">
                    <h2 class="hndle">Player Details</h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr><th>First name</th><td><input type="text" name="first_name" class="regular-text" value="<?php echo esc_attr($player->first_name ?? ''); ?>"></td></tr>
                            <tr><th>Last name</th><td><input type="text" name="last_name" class="regular-text" value="<?php echo esc_attr($player->last_name ?? ''); ?>"></td></tr>
                            <tr><th>Date of birth</th><td>
                                <input type="date" name="dob" value="<?php echo esc_attr($player->dob ?? ''); ?>" max="<?php echo esc_attr( date('Y-m-d') ); ?>">
                                <?php $pa = was_age( $player->dob ?? '' ); if ( $pa !== '' ) echo ' <span style="color:#555;">(age ' . esc_html( $pa ) . ')</span>'; ?>
                            </td></tr>
                            <tr>
                                <th>Team</th>
                                <td>
                                    <select name="team_id">
                                        <?php foreach($teams as $t): ?>
                                        <option value="<?php echo $t->id; ?>" <?php selected($player->team_id??0,$t->id); ?>><?php echo esc_html($t->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Participation type</th>
                                <td>
                                    <select name="participation_type">
                                        <option value="signed"     <?php selected($player->participation_type??'','signed'); ?>>Signed</option>
                                        <option value="train_only" <?php selected($player->participation_type??'','train_only'); ?>>Train Only</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Payment type</th>
                                <td>
                                    <select name="payment_type">
                                        <option value="gocardless"   <?php selected($player->payment_type??'','gocardless'); ?>>GoCardless (Direct Debit)</option>
                                        <option value="sibling_rate" <?php selected($player->payment_type??'','sibling_rate'); ?>>Sibling Rate</option>
                                        <option value="team_direct"  <?php selected($player->payment_type??'','team_direct'); ?>>Team Direct</option>
                                        <option value="exempt"       <?php selected($player->payment_type??'','exempt'); ?>>Exempt</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <select name="registration_status">
                                        <option value="active"    <?php selected($player->registration_status??'','active'); ?>>Active</option>
                                        <option value="pending"   <?php selected($player->registration_status??'','pending'); ?>>Pending</option>
                                        <option value="inactive"  <?php selected($player->registration_status??'','inactive'); ?>>Inactive</option>
                                        <option value="withdrawn" <?php selected($player->registration_status??'','withdrawn'); ?>>Withdrawn</option>
                                    </select>
                                </td>
                            </tr>
                            <tr><th>Notes</th><td><textarea name="notes" rows="3" class="large-text"><?php echo esc_textarea($player->notes??''); ?></textarea></td></tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle">Player Photo</h2>
                    <div class="inside">
                        <?php if ($photo_url): ?>
                        <div style="margin-bottom:12px;">
                            <img src="<?php echo esc_url($photo_url); ?>" style="max-width:160px; max-height:200px; border-radius:4px; border:1px solid #dde1e7;">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="player_photo" accept="image/jpeg,image/png">
                        <p class="description">JPG or PNG, max 8MB. Plain background, head &amp; shoulders, neutral expression.</p>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle">GoCardless (read reference only)</h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr><th>Mandate ID</th><td><input type="text" name="gc_mandate_id" class="regular-text" value="<?php echo esc_attr($player->gc_mandate_id??''); ?>"><input type="hidden" name="gc_mandate_id_orig" value="<?php echo esc_attr($player->gc_mandate_id??''); ?>"></td></tr>
                            <tr><th>Subscription ID</th><td><input type="text" name="gc_subscription_id" class="regular-text" value="<?php echo esc_attr($player->gc_subscription_id??''); ?>"><input type="hidden" name="gc_subscription_id_orig" value="<?php echo esc_attr($player->gc_subscription_id??''); ?>"></td></tr>
                            <tr><th>Customer ID</th><td><input type="text" name="gc_customer_id" class="regular-text" value="<?php echo esc_attr($player->gc_customer_id??''); ?>"><input type="hidden" name="gc_customer_id_orig" value="<?php echo esc_attr($player->gc_customer_id??''); ?>"></td></tr>
                        </table>
                        <?php if ($player && $player->gc_customer_id): ?>
                        <p><a href="https://<?php echo WAS_GoCardless::is_sandbox() ? 'manage-sandbox' : 'manage'; ?>.gocardless.com/customers/<?php echo esc_attr($player->gc_customer_id); ?>" target="_blank" class="button button-small">View in GoCardless &rarr;</a></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ( ! $is_new ) :
                    $team_obj     = WAS_Database::get_team( $player->team_id );
                    $has_override = ( $player->subs_override !== null && $player->subs_override !== '' );
                    // Payment day: ASTRO's setting, and what GoCardless is actually doing.
                    $pay_day  = was_payment_day( $player );
                    $live_sub = $player->gc_subscription_id ? WAS_GoCardless::get_subscription( $player->gc_subscription_id ) : null;
                    $live_on  = is_array( $live_sub ) && ( $live_sub['status'] ?? '' ) === 'active';
                    $live_day = $live_on ? WAS_GoCardless::subscription_day( $live_sub ) : 0;
                    $live_nxt = $live_on ? ( $live_sub['upcoming_payments'][0]['charge_date'] ?? '' ) : '';
                ?>
                <div class="postbox">
                    <h2 class="hndle">Subscription / Direct Debit</h2>
                    <div class="inside">
                        <?php $bd = was_player_payment_breakdown( $player ); ?>
                        <div style="background:#f6f7f9;border:1px solid #e2e6ea;border-radius:6px;padding:10px 12px;margin-bottom:14px;">
                            <strong style="display:block;margin-bottom:6px;">&#128179; Monthly payment schedule</strong>
                            <?php if ( empty( $bd['items'] ) ) : ?>
                                <p style="margin:0;color:#555;"><?php echo esc_html( $bd['summary'] ?: 'Nothing collected by the club.' ); ?></p>
                            <?php else : ?>
                                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                    <?php foreach ( $bd['items'] as $it ) : ?>
                                    <tr>
                                        <td style="padding:3px 0;"><?php echo esc_html( $it['label'] ); ?><?php echo $it['note'] ? ' <span style="color:#888;">(' . esc_html( $it['note'] ) . ')</span>' : ''; ?></td>
                                        <td style="padding:3px 0;text-align:right;white-space:nowrap;">&pound;<?php echo number_format( (float) $it['amount'], 2 ); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr style="border-top:2px solid #cfd4da;font-weight:700;">
                                        <td style="padding:6px 0 0;">Total per month</td>
                                        <td style="padding:6px 0 0;text-align:right;">&pound;<?php echo number_format( (float) $bd['total'], 2 ); ?></td>
                                    </tr>
                                </table>
                                <?php if ( ! $bd['billable'] ) : ?>
                                <p style="margin:8px 0 0;color:#8a6100;">Not currently collected (£0 total, or no active Direct Debit).</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <table class="form-table">
                            <tr><th>Team full rate</th><td>&pound;<?php echo number_format( (float) ( $team_obj->subs_amount ?? 0 ), 2 ); ?> / month</td></tr>
                            <tr><th>Team sibling rate</th><td>&pound;<?php echo number_format( (float) ( $team_obj->sibling_amount ?? 0 ), 2 ); ?> / month</td></tr>
                            <tr><th>Custom amount</th><td><?php echo $has_override ? '<strong>&pound;' . number_format( (float) $player->subs_override, 2 ) . ' / month</strong>' : '&mdash; (using standard rate)'; ?></td></tr>
                            <tr><th>Active subscription</th><td><?php echo $player->gc_subscription_id ? '<code>' . esc_html( $player->gc_subscription_id ) . '</code>' : 'none'; ?></td></tr>
                            <tr><th>Payment day</th><td>
                                <?php if ( $live_on ) : ?>
                                    <strong><?php echo esc_html( was_ordinal( $live_day ) ); ?> of each month</strong>
                                    <?php echo $pay_day === 1 ? '(default)' : '<span style="color:#8a6100;">(manual override)</span>'; ?>
                                    <?php if ( $live_nxt ) : ?><br><span style="color:#666;">Next new collection: <?php echo esc_html( date_i18n( 'j M Y', strtotime( $live_nxt ) ) ); ?></span><?php endif; ?>
                                    <?php if ( $live_day !== $pay_day ) : ?><br><span style="color:#b32d2e;">ASTRO&rsquo;s setting is the <?php echo esc_html( was_ordinal( $pay_day ) ); ?>, but GoCardless is collecting on the <?php echo esc_html( was_ordinal( $live_day ) ); ?>.</span><?php endif; ?>
                                <?php else : ?>
                                    <?php echo esc_html( was_ordinal( $pay_day ) ); ?> of each month <?php echo $pay_day === 1 ? '(default)' : '(manual override)'; ?> &mdash; <span style="color:#666;">no active Direct Debit</span>
                                <?php endif; ?>
                            </td></tr>
                        </table>
                        <?php $emails_paused = ! empty( $player->payment_emails_paused ); ?>
                        <div style="background:#fff8e5;border:1px solid #f0d68a;border-radius:6px;padding:8px 12px;margin:10px 0;">
                            <label style="display:block;"><input type="checkbox" id="was-notify-parent" checked> <strong>Email the parent</strong> when I change their Direct Debit on this page (amount, teams or payment date). It explains it&rsquo;s an update, not a cancellation. GoCardless also emails them either way.</label>
                            <label style="display:block;margin-top:6px;"><input type="checkbox" id="was-pause-emails" data-id="<?php echo (int) $player_id; ?>"<?php checked( $emails_paused ); ?>> <strong>Pause automatic payment emails to this family</strong> (failed payments, arrears, reminders, Direct Debit updates), e.g. for hardship or a manual arrangement. The treasurer still gets copies, marked &ldquo;Parent NOT emailed&rdquo;.</label>
                            <span id="was-pause-result" style="display:block;margin-top:4px;color:#2271b1;"><?php echo $emails_paused ? '&#9208; Payment emails are currently paused for this family.' : ''; ?></span>
                        </div>
                        <hr>
                        <p style="margin-bottom:6px;"><strong>Set a custom monthly amount</strong> for this child (for negotiated multi-child arrangements). Leave blank &amp; Apply to remove a custom amount; enter <code>0</code> to make the child exempt.</p>
                        <p>
                            &pound; <input type="number" step="0.01" min="0" id="was-custom-subs" value="<?php echo $has_override ? esc_attr( number_format( (float) $player->subs_override, 2, '.', '' ) ) : ''; ?>" style="width:90px;"> / month
                            &nbsp;<button type="button" class="button button-primary" id="was-apply-subs" data-id="<?php echo (int) $player_id; ?>">Apply</button>
                        </p>
                        <p class="description">If this child has an active Direct Debit, applying <strong>cancels it and creates a new one</strong> at the new amount, starting on their next charge date (no double charge).</p>
                        <div id="was-subs-result" class="notice" style="display:none; margin:8px 0 0; padding:8px 12px;"></div>
                        <hr>
                        <p style="margin-bottom:6px;"><strong>Change payment date</strong> &mdash; collect this child&rsquo;s Direct Debit on a different day of the month (e.g. to match a parent&rsquo;s payday). Everyone else is collected on the 1st.</p>
                        <p>
                            Collect on the
                            <select id="was-payment-day">
                                <?php $sel_day = $live_day ?: $pay_day; for ( $d = 1; $d <= 28; $d++ ) : ?>
                                <option value="<?php echo $d; ?>"<?php selected( $sel_day, $d ); ?>><?php echo esc_html( was_ordinal( $d ) . ( $d === 1 ? ' (default)' : '' ) ); ?></option>
                                <?php endfor; ?>
                            </select>
                            of each month
                            &nbsp;<button type="button" class="button" id="was-preview-payday" data-id="<?php echo (int) $player_id; ?>">Preview</button>
                        </p>
                        <div id="was-payday-preview" style="display:none;background:#f6f7f9;border:1px solid #e2e6ea;border-radius:6px;padding:10px 12px;margin:8px 0;">
                            <p id="was-payday-summary" style="margin:0 0 6px;font-weight:600;"></p>
                            <ul id="was-payday-lines" style="margin:0 0 10px 18px;list-style:disc;"></ul>
                            <button type="button" class="button button-primary" id="was-confirm-payday" data-id="<?php echo (int) $player_id; ?>">Confirm change</button>
                            <button type="button" class="button" id="was-cancel-payday">Cancel</button>
                        </div>
                        <p class="description">One collection per calendar month &mdash; never a double charge or a missed month. Payments already with the bank are still collected. If Payments already with the bank are still collected. GoCardless emails the parent the new schedule; ASTRO sends no email.</p>ldquo;Email the parentPayments already with the bank are still collected. GoCardless emails the parent the new schedule; ASTRO sends no email.</p>rdquo; is ticked above, ASTRO explains the change to them; GoCardless also emails them the new schedule.</p>
                        <div id="was-payday-result" class="notice" style="display:none; margin:8px 0 0; padding:8px 12px;"></div>
                        <hr>
                        <p style="margin-bottom:6px;"><strong>Monthly supplement</strong> &mdash; an itemised extra on top of the standard rate (shows on the parent&rsquo;s payment schedule with its label). Set to <code>0</code> to remove. Ignored while a custom amount is set.</p>
                        <p>
                            &pound; <input type="number" step="0.01" min="0" id="was-supplement-amount" value="<?php echo esc_attr( number_format( (float) ( $player->supplement_amount ?? 0 ), 2, '.', '' ) ); ?>" style="width:90px;">
                            &nbsp;label&nbsp; <input type="text" id="was-supplement-label" value="<?php echo esc_attr( $player->supplement_label ?? '' ); ?>" placeholder="e.g. JPL league supplement" style="width:220px;">
                            &nbsp;<button type="button" class="button button-primary" id="was-apply-supplement" data-id="<?php echo (int) $player_id; ?>">Apply</button>
                        </p>
                        <div id="was-supplement-result" class="notice" style="display:none; margin:8px 0 0; padding:8px 12px;"></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( ! $is_new ) :
                    $extra_teams = WAS_Database::get_player_extra_teams( $player_id );
                ?>
                <div class="postbox">
                    <h2 class="hndle">Additional Teams</h2>
                    <div class="inside">
                        <p class="description" style="margin-top:0;">The <strong>main team</strong> is set above. Add any <em>extra</em> teams this child also plays for here — each additional signed team adds its second-team rate to their single Direct Debit.</p>

                        <table class="widefat striped" style="margin-bottom:12px;">
                            <thead><tr><th>Team</th><th>Type</th><th>Adds</th><th></th></tr></thead>
                            <tbody>
                            <?php if ( empty( $extra_teams ) ) : ?>
                                <tr><td colspan="4"><em>No additional teams.</em></td></tr>
                            <?php else : foreach ( $extra_teams as $xt ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $xt->team_name ); ?></strong> <?php echo $xt->age_group ? '<span style="color:#888;">(' . esc_html( $xt->age_group ) . ')</span>' : ''; ?></td>
                                    <td><?php echo $xt->participation_type === 'signed' ? 'Signed' : 'Train only'; ?></td>
                                    <td>
                                        <?php
                                        if ( $xt->participation_type !== 'signed' ) {
                                            echo '&pound;0.00 <span style="color:#888;">(train only)</span>';
                                        } else {
                                            $xt_ov  = ( $xt->amount_override !== null && $xt->amount_override !== '' );
                                            $xt_amt = $xt_ov ? (float) $xt->amount_override : (float) ( $xt->second_team_amount ?? 0 );
                                            echo '&pound;' . number_format( $xt_amt, 2 );
                                            if ( $xt_ov && $xt_amt == 0.0 )      echo ' <span style="color:#1f8a54;font-weight:600;">(exempt)</span>';
                                            elseif ( $xt_ov )                    echo ' <span style="color:#888;">(custom)</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><button type="button" class="button button-small was-remove-team" data-player="<?php echo (int) $player_id; ?>" data-membership="<?php echo (int) $xt->id; ?>" style="color:#c62828;">Remove</button></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>

                        <p>
                            <select id="was-add-team-select" style="max-width:220px;">
                                <option value="">&mdash; Add to another team &mdash;</option>
                                <?php foreach ( $teams as $t ) : if ( (int) $t->id === (int) $player->team_id ) continue; ?>
                                <option value="<?php echo (int) $t->id; ?>"><?php echo esc_html( $t->name . ( $t->age_group ? ' (' . $t->age_group . ')' : '' ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="was-add-team-type">
                                <option value="signed">Signed (+ second-team rate)</option>
                                <option value="exempt">Signed &mdash; no charge (exempt, &pound;0)</option>
                                <option value="train_only">Train only (&pound;0)</option>
                            </select>
                            <button type="button" class="button button-primary was-add-team" data-player="<?php echo (int) $player_id; ?>">Add team</button>
                        </p>
                        <div id="was-team-result" class="notice" style="display:none; margin:8px 0 0; padding:8px 12px;"></div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- left col -->

            <!-- Right column -->
            <div class="was-edit-col">

                <div class="postbox">
                    <h2 class="hndle">Parent / Guardian</h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr><th>Full name</th><td><input type="text" name="g_name" class="regular-text" value="<?php echo esc_attr( trim( ($guardian->first_name??'') . ' ' . ($guardian->last_name??'') ) ); ?>"></td></tr>
                            <tr><th>Email</th><td><input type="email" name="g_email" class="regular-text" value="<?php echo esc_attr($guardian->email??''); ?>"></td></tr>
                            <tr><th>Telephone</th><td><input type="text" name="g_telephone" class="regular-text" value="<?php echo esc_attr($guardian->telephone??''); ?>"></td></tr>
                            <tr><th>Address</th><td><input type="text" name="g_address" class="regular-text" value="<?php echo esc_attr($guardian->address_line1??''); ?>"></td></tr>
                            <tr><th>Postcode</th><td><input type="text" name="g_postcode" class="small-text" value="<?php echo esc_attr($guardian->postcode??''); ?>"></td></tr>
                            <tr>
                                <th>Occupation</th>
                                <td>
                                    <select name="g_occupation">
                                        <?php foreach ( WAS_Accounts::occupation_categories() as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected( $guardian->occupation_category ?? '', $val ); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description" style="margin:4px 0 0;">FA inclusion data &mdash; confidential.</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Employer / business</th>
                                <td>
                                    <input type="text" name="g_employer" class="regular-text" value="<?php echo esc_attr($guardian->employer_name??''); ?>" placeholder="e.g. Smith Plumbing, Wolverhampton">
                                    <p class="description" style="margin:4px 0 0;">Optional &mdash; captured for possible sponsorship outreach.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle">Emergency Contact</h2>
                    <div class="inside">
                        <p class="description" style="margin-top:0;">The alternative (2nd) emergency contact captured during the child&rsquo;s registration. The parent/guardian above is the 1st contact.</p>
                        <table class="form-table">
                            <tr><th>Name</th><td><input type="text" name="ec_name" class="regular-text" value="<?php echo esc_attr($ec->contact_name??''); ?>"></td></tr>
                            <tr><th>Telephone</th><td><input type="text" name="ec_tel" class="regular-text" value="<?php echo esc_attr($ec->contact_telephone??''); ?>"></td></tr>
                            <tr><th>Occupation</th><td><input type="text" name="ec_occupation" class="regular-text" value="<?php echo esc_attr($ec->contact_occupation??''); ?>"></td></tr>
                            <tr><th>Company / employer</th><td><input type="text" name="ec_employer" class="regular-text" value="<?php echo esc_attr($ec->contact_employer??''); ?>"></td></tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle">Consents</h2>
                    <div class="inside">
                        <table class="form-table">
                            <?php
                            $consent_fields = array(
                                'photography'       => 'Photography &amp; social media',
                                'legal_proceedings' => 'Family, care or legal proceedings',
                                'medical_data'      => 'Medical data',
                                'privacy_policy'    => 'Privacy policy',
                            );
                            foreach ( $consent_fields as $ct => $label ):
                                $val = $consents[$ct] ?? 'disagree';
                            ?>
                            <tr>
                                <th><?php echo $label; ?></th>
                                <td>
                                    <select name="consent_<?php echo $ct; ?>">
                                        <?php if ( $ct === 'legal_proceedings' ) : // agree = none declared, disagree = proceedings declared ?>
                                        <option value="agree"    <?php selected($val,'agree'); ?>>None declared (OK for club media)</option>
                                        <option value="disagree" <?php selected($val,'disagree'); ?>>Proceedings declared (do not photograph)</option>
                                        <?php else : ?>
                                        <option value="agree"    <?php selected($val,'agree'); ?>>Agree / Yes</option>
                                        <option value="disagree" <?php selected($val,'disagree'); ?>>Disagree / No</option>
                                        <?php endif; ?>
                                    </select>
                                    <?php if ($val === 'agree'): ?>
                                        <span class="was-admin-tick">&#10003;</span>
                                    <?php else: ?>
                                        <span class="was-admin-cross">&#10007;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th>Medical data consent</th>
                                <td><label><input type="checkbox" name="med_consent" value="1" <?php checked($medical->consent_given??0,1); ?>> Given</label></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle">Medical Information</h2>
                    <div class="inside">
                        <?php
                        $mfields = array(
                            'health_needs'      => 'Health needs',
                            'medication'        => 'Medication / action required',
                            'medical_training'  => 'Special training needed',
                            'access_needs'      => 'Access needs',
                            'communication_needs'=> 'Communication needs',
                        );
                        $mmap = array(
                            'health_needs'       => $medical->health_needs           ?? '',
                            'medication'         => $medical->current_medication     ?? '',
                            'medical_training'   => $medical->medical_training_needs ?? '',
                            'access_needs'       => $medical->access_needs           ?? '',
                            'communication_needs'=> $medical->communication_needs    ?? '',
                        );
                        ?>
                        <table class="form-table">
                        <?php foreach($mfields as $fname => $flabel): ?>
                        <tr>
                            <th><?php echo $flabel; ?></th>
                            <td><textarea name="<?php echo $fname; ?>" rows="2" class="large-text"><?php echo esc_textarea($mmap[$fname]); ?></textarea></td>
                        </tr>
                        <?php endforeach; ?>
                        </table>
                    </div>
                </div>

            </div><!-- right col -->

        </div><!-- .was-edit-grid -->

        <?php submit_button( $is_new ? 'Create Player' : 'Save Changes' ); ?>

        <?php if ( ! $is_new ) : ?>
        <hr>
        <p>
            <strong>Soft delete:</strong>
            <a href="<?php echo esc_url( admin_url('admin.php?page=was-players&bulk_action=withdraw&player_ids[]=' . $player_id . '&was_bulk_nonce=' . wp_create_nonce('was_bulk_action') ) ); ?>"
               class="button"
               onclick="return confirm('Mark this player as withdrawn?')">
                Mark as withdrawn
            </a>
            &nbsp;
            <strong>Hard delete (GDPR):</strong>
            <a href="#" class="button was-hard-delete" data-id="<?php echo $player_id; ?>"
               style="color:#c62828; border-color:#c62828;">
                Delete all data permanently
            </a>
        </p>
        <?php endif; ?>
    </form>
</div>
