<?php defined( 'ABSPATH' ) || exit; ?>
<div id="was-section-messages" class="was-portal-section">

    <div class="was-portal-section__header">
        <h3 class="was-portal-section__title">Club messages</h3>
    </div>

    <div class="was-card was-msg-compose">
        <h4 class="was-card-title">Send an email</h4>
        <p class="was-card-desc">Sent as a branded club email via ASTRO. Choose below whether replies come to you or to the club's general inbox.</p>

        <div class="was-field">
            <label class="was-label was-label--required">To</label>
            <div class="was-msg-audiences">
                <label class="was-checkbox-label"><input type="checkbox" class="was-msg-aud" value="parents"> <span><strong>Parents</strong> — every family with an active registration</span></label>
                <label class="was-checkbox-label"><input type="checkbox" class="was-msg-aud" value="coaches"> <span><strong>Coaches</strong> — everyone with the coach role</span></label>
                <label class="was-checkbox-label"><input type="checkbox" class="was-msg-aud" value="committee"> <span><strong>Committee</strong> — committee, treasurer &amp; social-media members</span></label>
            </div>
        </div>

        <div class="was-field was-field--half" id="was-msg-team-wrap" style="display:none;">
            <label class="was-label">Limit parents to one team</label>
            <select id="was-msg-team" class="was-select">
                <option value="0">All teams</option>
                <?php foreach ( $msg_teams as $t ) : ?>
                <option value="<?php echo esc_attr( $t->id ); ?>"><?php echo esc_html( $t->name . ( $t->age_group ? ' (' . $t->age_group . ')' : '' ) ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="was-field">
            <label class="was-label">Send as</label>
            <div class="was-msg-audiences">
                <label class="was-checkbox-label"><input type="radio" name="was-msg-from" value="me" checked> <span><strong>Me</strong> — replies go to <?php echo esc_html( $msg_me_email ); ?></span></label>
                <label class="was-checkbox-label"><input type="radio" name="was-msg-from" value="club" data-email="<?php echo esc_attr( $msg_club_email ); ?>"> <span><strong>The club</strong> — sent from, and replies go to, <?php echo esc_html( $msg_club_email ); ?></span></label>
            </div>
        </div>

        <div class="was-field">
            <label class="was-label was-label--required">Subject</label>
            <input type="text" id="was-msg-subject" class="was-input" maxlength="200" placeholder="e.g. Presentation day — Saturday 12 September">
        </div>

        <div class="was-field">
            <label class="was-label was-label--required">Message</label>
            <textarea id="was-msg-body" class="was-textarea" rows="8" placeholder="Write your message&hellip; plain text; line breaks are kept and web links become clickable."></textarea>
        </div>

        <div class="was-field">
            <label class="was-label" for="was-msg-test-to">Send a test first <span class="was-hint">— goes to this address only, marked [TEST], not logged</span></label>
            <div class="was-msg-test">
                <input type="email" id="was-msg-test-to" class="was-input" value="<?php echo esc_attr( $msg_me_email ); ?>" placeholder="you@example.com">
                <button type="button" id="was-msg-test" class="was-btn was-btn--ghost">
                    <span class="was-btn-label">Send test</span>
                    <span class="was-btn-spinner" style="display:none;">Sending&hellip;</span>
                </button>
            </div>
        </div>

        <div class="was-field">
            <label class="was-label">When</label>
            <div class="was-msg-audiences">
                <label class="was-checkbox-label"><input type="radio" name="was-msg-when" value="now" checked> <span><strong>Send now</strong></span></label>
                <label class="was-checkbox-label"><input type="radio" name="was-msg-when" value="later"> <span><strong>Schedule for later</strong></span></label>
            </div>
            <div id="was-msg-later-wrap" class="was-msg-later" style="display:none;">
                <input type="datetime-local" id="was-msg-send-at" class="was-input" step="300">
                <span class="was-hint">UK time. It goes out within a few minutes of this time, and the recipient list is worked out when it sends.</span>
            </div>
        </div>

        <div id="was-msg-alert" class="was-alert was-mb-1" style="display:none;"></div>

        <button type="button" id="was-msg-send" class="was-btn was-btn--primary">
            <span class="was-btn-label">Send message</span>
            <span class="was-btn-spinner" style="display:none;">Sending&hellip;</span>
        </button>
        <span id="was-msg-progress" class="was-msg-progress" style="display:none;"></span>
    </div>

    <div class="was-card was-mt-2">
        <h4 class="was-card-title">Recent messages</h4>
        <p class="was-hint">Times are UK time. Scheduled messages can be cancelled until they start sending.</p>
        <table class="was-treas-table" id="was-msg-history">
            <thead><tr><th>Date</th><th>Sent by</th><th>To</th><th>Subject</th><th>Recipients</th><th>Status</th></tr></thead>
            <tbody>
            <?php if ( empty( $history ) ) : ?>
                <tr class="was-msg-empty"><td colspan="6" class="was-empty-note">Nothing sent yet.</td></tr>
            <?php endif; ?>
            <?php foreach ( $history as $m ) echo WAS_Portal::msg_history_row( $m, $msg_teams ); ?>
            </tbody>
        </table>
    </div>

</div>
