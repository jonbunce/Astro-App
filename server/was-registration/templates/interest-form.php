<?php
/**
 * [was_interest_form] — public register-your-interest form.
 * Age group is previewed live in JS and recomputed authoritatively on submit.
 */
defined( 'ABSPATH' ) || exit;
$season      = was_season();
$cutoff_year = (int) substr( $season, 0, 4 );
?>
<div class="was-wrap was-account-wrap">
	<div class="was-account-card was-account-card--wide" id="was-interest-card">

		<div class="astro-login-logo-wrap">
			<img src="<?php echo esc_url( was_portal_logo_dark_url() ); ?>" class="astro-account-logo" alt="ASTRO">
		</div>

		<p class="was-account-intro">Our teams are close to full, but demand tells us Wombourne needs more football &mdash; and every name on this list strengthens our case for more pitches. Tell us about your player and we&rsquo;ll be in touch the moment a suitable place opens up.</p>

		<div id="was-int-error"   class="was-alert was-alert--error"   style="display:none;"></div>

		<div id="was-int-form">

			<h3 class="was-account-section-heading">About you</h3>

			<div class="was-field-row">
				<div class="was-field">
					<label for="was-int-parent" class="was-label was-label--required">Your name</label>
					<input type="text" id="was-int-parent" class="was-input" autocomplete="name">
				</div>
				<div class="was-field">
					<label for="was-int-rel" class="was-label">You are the player&rsquo;s&hellip;</label>
					<select id="was-int-rel" class="was-select">
						<option value="Parent / guardian">Parent / guardian</option>
						<option value="I am the player">I am the player</option>
						<option value="Other family member">Other family member</option>
						<option value="Other">Other</option>
					</select>
				</div>
			</div>

			<div class="was-field-row">
				<div class="was-field">
					<label for="was-int-email" class="was-label was-label--required">Email address</label>
					<input type="email" id="was-int-email" class="was-input" autocomplete="email">
				</div>
				<div class="was-field">
					<label for="was-int-phone" class="was-label was-label--required">Telephone number</label>
					<input type="tel" id="was-int-phone" class="was-input" autocomplete="tel">
				</div>
			</div>

			<h3 class="was-account-section-heading">About the player</h3>

			<div class="was-field-row">
				<div class="was-field">
					<label for="was-int-first" class="was-label was-label--required">Player first name</label>
					<input type="text" id="was-int-first" class="was-input">
				</div>
				<div class="was-field">
					<label for="was-int-last" class="was-label was-label--required">Player last name</label>
					<input type="text" id="was-int-last" class="was-input">
				</div>
			</div>

			<div class="was-field-row">
				<div class="was-field">
					<label for="was-int-dob" class="was-label was-label--required">Date of birth</label>
					<input type="date" id="was-int-dob" class="was-input" max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
				</div>
				<div class="was-field">
					<label for="was-int-postcode" class="was-label was-label--required">
						Postcode
						<span class="was-label-hint">Helps us show the council where demand for pitches is coming from.</span>
					</label>
					<input type="text" id="was-int-postcode" class="was-input" autocomplete="postal-code" style="text-transform:uppercase;">
				</div>
			</div>

			<div id="was-int-agebox" class="was-info-box was-int-agebox" style="display:none;margin-bottom:1rem;">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
				<div><strong>Age group for <?php echo esc_html( $season ); ?>:</strong> <span id="was-int-agegroup"></span></div>
			</div>

			<div class="was-field" id="was-int-playup-row" style="display:none;">
				<label class="was-checkbox-label">
					<input type="checkbox" id="was-int-playup">
					<span>I think this player could potentially play <strong>one age group up</strong> (ability and physicality considered)</span>
				</label>
			</div>

			<div class="was-field">
				<label for="was-int-exp" class="was-label">
					Previous football experience
					<span class="was-label-hint">Optional &mdash; e.g. seasons at another club, school team, or complete beginner. Everyone is welcome.</span>
				</label>
				<textarea id="was-int-exp" class="was-textarea" rows="3"></textarea>
			</div>

			<div class="was-field">
				<label for="was-int-needs" class="was-label">
					Disabilities or additional needs
					<span class="was-label-hint">Optional. Telling us helps us find the right environment and support for the player. This information is confidential, stored securely, and only used to help us plan.</span>
				</label>
				<textarea id="was-int-needs" class="was-textarea" rows="3"></textarea>
			</div>

			<?php /* honeypot — humans never see or fill this */ ?>
			<div style="position:absolute;left:-9999px;" aria-hidden="true">
				<label>Website<input type="text" id="was-int-website" tabindex="-1" autocomplete="off"></label>
			</div>

			<div class="was-consent-block">
				<label class="was-checkbox-label">
					<input type="checkbox" id="was-int-consent">
					<span>I agree to Wombourne Allstars FC keeping these details on its waiting list and contacting me about places, in line with the club&rsquo;s <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>" target="_blank" rel="noopener">privacy policy</a>. Anonymised numbers (never names) may be used to evidence local demand for pitches.</span>
				</label>
			</div>

			<button type="button" id="was-int-submit" class="was-btn was-btn--primary was-btn--full">
				<span class="was-btn-label">Register interest</span>
				<span class="was-btn-spinner" style="display:none;">Sending&hellip;</span>
			</button>

		</div>

		<div id="was-int-done" class="was-alert was-alert--success" style="display:none;"></div>

	</div>
</div>

<script>
(function () {
	'use strict';
	var CUTOFF_YEAR = <?php echo (int) $cutoff_year; ?>;
	var $ = function (id) { return document.getElementById(id); };

	function ageGroup(dobStr) {
		if (!dobStr) return null;
		var d = new Date(dobStr + 'T00:00:00');
		if (isNaN(d.getTime()) || d.getFullYear() < 1920) return null;
		var cutoff = new Date(CUTOFF_YEAR, 7, 31, 23, 59, 59); // 31 August
		if (d > cutoff) return null;
		var age = cutoff.getFullYear() - d.getFullYear();
		var m = new Date(d); m.setFullYear(cutoff.getFullYear());
		if (m > cutoff) age--;
		var u = age + 1;
		if (u >= 19) return { code: 'Adult', label: 'Adult football — our pathway continues past 18', band: 'adult' };
		if (u < 7)   return { code: 'U' + u, label: 'U' + u + ' — below league age, but register anyway and we’ll let you know about our youngest offerings', band: 'pre' };
		return { code: 'U' + u, label: 'U' + u, band: 'youth' };
	}

	$('was-int-dob').addEventListener('change', function () {
		var g = ageGroup(this.value);
		var box = $('was-int-agebox'), row = $('was-int-playup-row');
		if (!g) { box.style.display = 'none'; row.style.display = 'none'; return; }
		$('was-int-agegroup').textContent = g.label;
		box.style.display = '';
		row.style.display = (g.band === 'youth') ? '' : 'none';
		if (g.band !== 'youth') $('was-int-playup').checked = false;
	});

	$('was-int-submit').addEventListener('click', function () {
		var btn = this, err = $('was-int-error');
		err.style.display = 'none';
		btn.disabled = true;
		btn.querySelector('.was-btn-label').style.display = 'none';
		btn.querySelector('.was-btn-spinner').style.display = '';

		var data = new FormData();
		data.append('action', 'was_interest_submit');
		data.append('nonce', window.wasAjax ? wasAjax.nonce : '');
		data.append('parent_name',  $('was-int-parent').value);
		data.append('relationship', $('was-int-rel').value);
		data.append('email',        $('was-int-email').value);
		data.append('phone',        $('was-int-phone').value);
		data.append('player_first', $('was-int-first').value);
		data.append('player_last',  $('was-int-last').value);
		data.append('dob',          $('was-int-dob').value);
		data.append('postcode',     $('was-int-postcode').value);
		data.append('play_up',      $('was-int-playup').checked ? '1' : '');
		data.append('experience',   $('was-int-exp').value);
		data.append('disabilities', $('was-int-needs').value);
		data.append('consent',      $('was-int-consent').checked ? '1' : '');
		data.append('website',      $('was-int-website').value);

		fetch(window.wasAjax ? wasAjax.ajaxurl : '/wp-admin/admin-ajax.php', { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success) {
					$('was-int-form').style.display = 'none';
					var done = $('was-int-done');
					done.textContent = res.data.message;
					done.style.display = '';
					done.scrollIntoView({ behavior: 'smooth', block: 'center' });
				} else {
					err.textContent = (res && res.data && res.data.message) ? res.data.message : 'Sorry, something went wrong. Please try again.';
					err.style.display = '';
					err.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			})
			.catch(function () {
				err.textContent = 'A network error occurred — please try again.';
				err.style.display = '';
			})
			.finally(function () {
				btn.disabled = false;
				btn.querySelector('.was-btn-label').style.display = '';
				btn.querySelector('.was-btn-spinner').style.display = 'none';
			});
	});
})();
</script>
