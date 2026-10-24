<?php defined( 'ABSPATH' ) || exit;
$is_signed = ( $player->participation_type === 'signed' );
$g_first   = $guardian ? esc_attr( $guardian->first_name . ( $guardian->last_name ? ' ' . $guardian->last_name : '' ) ) : '';
$g_email   = $guardian ? esc_attr( $guardian->email )        : '';
$g_tel     = $guardian ? esc_attr( $guardian->telephone )    : '';
$g_addr    = $guardian ? esc_attr( $guardian->address_line1 ): '';
$g_post    = $guardian ? esc_attr( $guardian->postcode )     : '';
?>
<div class="was-wrap">
    <h2 class="was-heading">Registration Form &mdash; <?php echo esc_html( $team->name ); ?></h2>

    <?php echo was_steps_html( $is_signed ? 3 : 2, $is_signed ? 'signed' : 'train' ); ?>

    <?php if ( $is_signed ) : ?>
    <div class="was-info-box was-info-box--success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <p>Your Direct Debit mandate has been set up successfully. Please complete the details below to finish registration.</p>
    </div>
    <?php endif; ?>

    <form id="was-reg-form" novalidate>
        <input type="hidden" name="was_token" value="<?php echo esc_attr( $player->registration_token ); ?>">

        <div class="was-card">
            <h3 class="was-section-heading">Player Information</h3>
            <div class="was-field-row">
                <div class="was-field">
                    <label for="was-first-name" class="was-label was-label--required">First name</label>
                    <input type="text" id="was-first-name" name="first_name" class="was-input" required autocomplete="off">
                </div>
                <div class="was-field">
                    <label for="was-last-name" class="was-label was-label--required">Last name</label>
                    <input type="text" id="was-last-name" name="last_name" class="was-input" required autocomplete="off">
                </div>
            </div>
            <div class="was-field was-field--half">
                <label for="was-dob" class="was-label was-label--required">Date of birth</label>
                <input type="date" id="was-dob" name="dob" class="was-input" required max="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
            </div>
            <div class="was-field was-field--readonly">
                <label class="was-label">Team</label>
                <p class="was-readonly-value"><?php echo esc_html( $team->name ); ?><?php if ( $team->age_group ) echo ' (' . esc_html( $team->age_group ) . ')'; ?></p>
            </div>
            <div class="was-field was-field--readonly">
                <label class="was-label">Participation type</label>
                <p class="was-readonly-value"><?php echo $is_signed ? 'Signed Player' : 'Train Only'; ?></p>
            </div>

            <div class="was-field">
                <label class="was-label was-label--required">Player photo</label>
                <div class="was-photo-upload">
                    <div class="was-photo-preview" id="was-photo-preview">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                    </div>
                    <div class="was-photo-instructions">
                        <p><strong>Photo requirements:</strong></p>
                        <ul>
                            <li>Head and shoulders only, against a plain light background</li>
                            <li>Looking directly at the camera with a neutral expression &mdash; no smiling</li>
                            <li>No hats, sunglasses or hoods</li>
                            <li>JPG or PNG format, maximum 8MB</li>
                        </ul>
                        <label for="was-player-photo" class="was-btn was-btn--secondary was-btn--sm" style="cursor:pointer;">Choose photo</label>
                        <span id="was-photo-name" class="was-help"></span>
                    </div>
                </div>
                <input type="file" id="was-player-photo" name="player_photo" accept="image/jpeg,image/png" class="was-file-hidden">
            </div>
        </div>

        <div class="was-card">
            <h3 class="was-section-heading">School</h3>
            <?php
            $schools = WAS_Database::get_schools();
            ?>
            <div class="was-field">
                <label for="was-school-select" class="was-label was-label--required">School your child attends</label>
                <select id="was-school-select" name="school_id" class="was-select" required>
                    <option value="">— Please select —</option>
                    <?php foreach ( $schools as $sch ) : ?>
                    <option value="<?php echo esc_attr($sch->id); ?>"><?php echo esc_html($sch->name); ?></option>
                    <?php endforeach; ?>
                    <option value="other">My child&rsquo;s school isn&rsquo;t listed</option>
                </select>
            </div>
            <div class="was-field" id="was-school-other-wrap" style="display:none;">
                <label for="was-school-other" class="was-label was-label--required">Please specify school</label>
                <input type="text" id="was-school-other" name="school_other" class="was-input" placeholder="School name and town">
            </div>
        </div>

        <div class="was-card">
            <h3 class="was-section-heading">Parent / Guardian Contact Details</h3>
            <p class="was-card-desc">These details are taken from your account. <a href="<?php echo esc_url( home_url('/account-portal/') ); ?>" class="was-link">Update them in your account</a> if anything has changed.</p>
            <?php /* Hidden fields — values come from guardian record */ ?>
            <input type="hidden" name="guardian_name" value="<?php echo esc_attr( trim( ($guardian ? $guardian->first_name . ' ' . $guardian->last_name : '') ) ); ?>">
            <input type="hidden" name="telephone"     value="<?php echo esc_attr( $g_tel ); ?>">
            <input type="hidden" name="email"         value="<?php echo esc_attr( $g_email ); ?>">
            <input type="hidden" name="address"       value="<?php echo esc_attr( $g_addr ); ?>">
            <input type="hidden" name="postcode"      value="<?php echo esc_attr( $g_post ); ?>">
            <div class="was-field-row">
                <div class="was-field was-field--readonly">
                    <label class="was-label">Name</label>
                    <p class="was-readonly-value"><?php echo esc_html( trim( ($guardian ? $guardian->first_name . ' ' . $guardian->last_name : '') ) ); ?></p>
                </div>
                <div class="was-field was-field--readonly">
                    <label class="was-label">Telephone</label>
                    <p class="was-readonly-value"><?php echo esc_html( $g_tel ); ?></p>
                </div>
            </div>
            <div class="was-field-row">
                <div class="was-field was-field--readonly">
                    <label class="was-label">Email</label>
                    <p class="was-readonly-value"><?php echo esc_html( $g_email ); ?></p>
                </div>
                <div class="was-field was-field--readonly">
                    <label class="was-label">Postcode</label>
                    <p class="was-readonly-value"><?php echo esc_html( $g_post ); ?></p>
                </div>
            </div>
        </div>

        <div class="was-card">
            <h3 class="was-section-heading">Alternative Emergency Contact</h3>
            <p class="was-card-desc">Should the parent / carer above not be available at the time of an emergency, this contact will be used.</p>
            <div class="was-field">
                <label for="was-ec-name" class="was-label was-label--required">Alternative contact name</label>
                <input type="text" id="was-ec-name" name="emergency_name" class="was-input" required autocomplete="off">
            </div>
            <div class="was-field">
                <label for="was-ec-tel" class="was-label was-label--required">Alternative contact telephone</label>
                <input type="tel" id="was-ec-tel" name="emergency_telephone" class="was-input" required autocomplete="off">
            </div>
            <div class="was-field">
                <label for="was-ec-occ" class="was-label">Their occupation <span class="was-label-hint">Optional</span></label>
                <input type="text" id="was-ec-occ" name="emergency_occupation" class="was-input" autocomplete="off" placeholder="e.g. Electrician, Teacher, Nurse">
            </div>
            <div class="was-field">
                <label for="was-ec-emp" class="was-label">Company / employer <span class="was-label-hint">Optional &mdash; helps us explore local sponsors</span></label>
                <input type="text" id="was-ec-emp" name="emergency_employer" class="was-input" autocomplete="off" placeholder="e.g. Smith Electrical, Wolverhampton">
            </div>
        </div>

        <div class="was-card">
            <h3 class="was-section-heading">Consent for Photography &amp; Social Media</h3>

            <div class="was-yn-block">
                <p class="was-yn-text">I agree to the club being able to take photos and videos during this season of my child, and that these may be used to promote and celebrate the club&rsquo;s activities on social media platforms. I understand I can withdraw this consent at any time by notifying the club committee.</p>
                <div class="was-yn-choice">
                    <label class="was-yn-label was-yn-yes"><input type="radio" name="consent_photography" value="agree" class="was-yn-radio" required> Yes</label>
                    <label class="was-yn-label was-yn-no"><input type="radio" name="consent_photography" value="disagree" class="was-yn-radio"> No</label>
                </div>
            </div>

            <div class="was-yn-block">
                <p class="was-yn-text"><strong>Is your child subject to any family, care or legal proceedings</strong> (for example a court order or a social care plan) that mean they should not be photographed or filmed, or that sharing their photo could put them at risk?</p>
                <p class="was-yn-text" style="margin-top:-0.4rem;font-size:0.85rem;color:var(--was-text-muted);">Most families answer <strong>No</strong>. If you answer <strong>Yes</strong>, we&rsquo;ll flag your child to the club&rsquo;s media team so they are not photographed or shown on club media.</p>
                <div class="was-yn-choice">
                    <?php // Stored values are unchanged: "agree" = no proceedings (fine for club media), "disagree" = proceedings declared. Yes is styled as the warning answer, No as the all-clear. ?>
                    <label class="was-yn-label was-yn-no"><input type="radio" name="consent_legal" value="disagree" class="was-yn-radio" required> Yes</label>
                    <label class="was-yn-label was-yn-yes"><input type="radio" name="consent_legal" value="agree" class="was-yn-radio"> No</label>
                </div>
            </div>
        </div>

        <div class="was-card">
            <h3 class="was-section-heading">Medical &amp; Personal Data</h3>
            <p class="was-card-desc">Please answer each question. Select <strong>Yes</strong> where something applies and provide details in the box that appears. If it does not apply, select <strong>No</strong>. Should anything change during the season please inform your coach or the club immediately.</p>

            <div class="was-field" style="margin-bottom:1.25rem;">
                <label class="was-checkbox-label">
                    <input type="checkbox" name="consent_medical" value="agree" id="was-medical-consent" required data-label="Medical data consent">
                    <span>I agree to the club holding health, medical and/or medication data I provide below to ensure my child&rsquo;s safety and to take appropriate action to ensure their wellbeing. I understand I can withdraw consent at any time by informing a committee member, though this may result in my child being unable to participate. <strong>*</strong></span>
                </label>
            </div>

            <?php
            $mqs = array(
                array( 'key' => 'health_needs',      'q' => 'Does your child have any known health needs?',                                            'hint' => 'e.g. diabetes, asthma, epilepsy, allergies' ),
                array( 'key' => 'medication',         'q' => 'Does your child take any medication or require specific action from club members?',       'hint' => 'e.g. administer planned medication, call ambulance, give snacks. Please be specific.' ),
                array( 'key' => 'medical_training',   'q' => 'Do club members need any medical training other than First Aid to care for your child?',  'hint' => '' ),
                array( 'key' => 'access_needs',       'q' => 'Does your child have any access needs?',                                                 'hint' => '' ),
                array( 'key' => 'communication_needs','q' => 'Does your child have any communication needs?',                                           'hint' => 'e.g. non-English speaker, hearing impairment, sign language user, dyslexia' ),
            );
            foreach ( $mqs as $mq ) : ?>
            <div class="was-med-question">
                <div class="was-yn-block was-yn-block--sub">
                    <p class="was-yn-text"><?php echo esc_html( $mq['q'] ); ?></p>
                    <div class="was-yn-choice" data-required="true">
                        <label class="was-yn-label was-yn-yes"><input type="radio" name="yn_<?php echo $mq['key']; ?>" value="yes" class="was-yn-radio was-yn-med-toggle"> Yes</label>
                        <label class="was-yn-label was-yn-no"><input type="radio" name="yn_<?php echo $mq['key']; ?>" value="no" class="was-yn-radio was-yn-med-toggle"> No</label>
                    </div>
                </div>
                <div class="was-med-detail" style="display:none;">
                    <label class="was-label">Please provide details<?php if($mq['hint']) echo ' <small>' . esc_html($mq['hint']) . '</small>'; ?></label>
                    <textarea name="<?php echo $mq['key']; ?>" class="was-textarea" rows="2"></textarea>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="was-card">
            <div class="was-field">
                <label class="was-checkbox-label">
                    <input type="checkbox" name="consent_privacy" value="agree" id="was-privacy" required data-label="Privacy Policy agreement">
                    <span>I have read the <a href="/privacy-policy/" target="_blank">Privacy Policy</a> and understand my rights over the data provided in this form and elsewhere. <strong>*</strong></span>
                </label>
            </div>
        </div>

        <div id="was-form-error" class="was-alert was-alert--error" style="display:none;"></div>

        <button type="submit" id="was-submit-btn" class="was-btn was-btn--primary was-btn--large">
            <span class="was-btn-label">Complete Registration</span>
            <span class="was-btn-spinner" style="display:none;">Submitting&hellip;</span>
        </button>
    </form>
</div>
