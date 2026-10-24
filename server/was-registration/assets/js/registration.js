/* global wasAjax, jQuery */
(function ($) {
    'use strict';

    // ── Start page: team/type selection and GoCardless handoff ───────────────

    if ($('#was-proceed-btn').length) {

        var $typeRadios     = $('input[name="participation_type"]');
        var $gcNotice       = $('#was-gc-notice');
        var $siblingSection = $('#was-sibling-section');
        var $subsLabel      = $('#was-subs-label');
        var $btn            = $('#was-proceed-btn');
        var $teamSelect     = $('#was-team-select');
        var $errBox         = $('#was-start-error');

        function updateUI() {
            var type     = $typeRadios.filter(':checked').val();
            var isSigned = (type === 'signed');

            $gcNotice.toggle(isSigned);
            if ($siblingSection.length) $siblingSection.toggle(isSigned);
            $btn.find('.was-btn-label-signed').toggle(isSigned);
            $btn.find('.was-btn-label-train').toggle(!isSigned);

            var $opt    = $teamSelect.find(':selected');
            var subs    = parseFloat($opt.data('subs') || 0);
            var sibling = parseFloat($opt.data('sibling') || 0);

            if (isSigned && subs > 0) {
                var txt = '£' + subs.toFixed(2) + '/month';
                if (sibling > 0) txt += ' (sibling rate: £' + sibling.toFixed(2) + '/mo)';
                $subsLabel.text(txt).show();
            } else {
                $subsLabel.hide();
            }

            // Auto sibling check for signed players if guardian is logged in
            if (isSigned && $teamSelect.val()) {
                checkSiblingAuto();
            } else {
                clearSiblingNotices();
            }
        }

        function clearSiblingNotices() {
            $('#was-sibling-match').hide();
            $('#was-sibling-no-match').hide();
        }

        function checkSiblingAuto() {
            var teamId = $teamSelect.val();
            if (!teamId) { clearSiblingNotices(); return; }

            $.post(wasAjax.ajaxurl, {
                action:  'was_check_sibling',
                nonce:   wasAjax.nonce,
                email:   '',   // empty — server uses logged-in user
                team_id: teamId,
            }, function(res) {
                if (!res.success) return;
                if (res.data.match) {
                    var names = res.data.names.join(', ');
                    var rate  = '£' + parseFloat(res.data.sibling_rate).toFixed(2);
                    var full  = '£' + parseFloat(res.data.full_rate).toFixed(2);
                    $('#was-sibling-match-title').text('Sibling rate applies — ' + rate + '/month');
                    $('#was-sibling-match-detail').html(
                        'We found ' + res.data.count + ' existing registration' +
                        (res.data.count > 1 ? 's' : '') + ' under your account (' + names + '). ' +
                        'Instead of the full rate of ' + full + '/month, your Direct Debit will be ' +
                        'set up at the sibling rate of <span class="was-rate-highlight">' + rate + '/month</span>.'
                    );
                    $('#was-sibling-match').show();
                    $('#was-sibling-no-match').hide();
                    $subsLabel.text(rate + '/month (sibling rate ✓)');
                } else {
                    clearSiblingNotices();
                }
            });
        }

        $typeRadios.on('change', updateUI);
        $teamSelect.on('change', updateUI);
        updateUI();

        $btn.on('click', function () {
            $errBox.hide();

            var teamId = $teamSelect.val();
            var type   = $typeRadios.filter(':checked').val();

            if (!teamId) {
                $errBox.text('Please select a team before continuing.').show();
                $teamSelect.focus();
                return;
            }

            $btn.prop('disabled', true);
            $btn.find('.was-btn-label-signed, .was-btn-label-train').hide();
            $btn.find('.was-btn-spinner').show();

            $.post(wasAjax.ajaxurl, {
                action:             'was_start_gc',
                nonce:              wasAjax.nonce,
                team_id:            teamId,
                participation_type: type,
                guardian_email:     '',
                guardian_name:      '',
                current_url:        window.location.href
            }, function (res) {
                if (res.success) {
                    if (res.data.gc_url) {
                        window.location.href = res.data.gc_url;
                    } else if (res.data.redirect) {
                        window.location.href = res.data.redirect;
                    }
                } else {
                    $errBox.html(res.data.message || 'Something went wrong. Please try again.').show();
                    resetBtn();
                }
            }).fail(function () {
                $errBox.text('A network error occurred. Please try again.').show();
                resetBtn();
            });

            function resetBtn() {
                $btn.prop('disabled', false);
                $btn.find('.was-btn-spinner').hide();
                updateUI();
            }
        });
    }

    // ── Registration form submission ─────────────────────────────────────────

    if ($('#was-reg-form').length) {
        var $form    = $('#was-reg-form');
        var $submit  = $('#was-submit-btn');
        var $formErr = $('#was-form-error');

        // ── School "Other" field toggle ────────────────────────────────────
        $(document).on('change', '#was-school-select', function () {
            var isOther = $(this).val() === 'other';
            $('#was-school-other-wrap').toggle(isOther);
            $('#was-school-other').prop('required', isOther);
            if (!isOther) $('#was-school-other').val('');
        });

        // ── Individual medical yes/no fields ───────────────────────────────
        // Show the details box only for "Yes"; hide AND clear it for "No" so a
        // "No" answer never submits leftover text. (slideToggle flipped it on every
        // change, so "No" was opening the box too.)
        $(document).on('change', '.was-yn-med-toggle', function () {
            var $detail = $(this).closest('.was-med-question').find('.was-med-detail');
            if ($(this).val() === 'yes') {
                $detail.slideDown(200);
            } else {
                $detail.slideUp(100);
                $detail.find('textarea').val('');
            }
        });

        // ── Photo choose → pan/zoom crop → preview ─────────────────────────
        // Uses the shared cropper from portal.js (window.wasCropPhoto). The
        // cropped 512x512 JPEG replaces the picked file at submit time.
        var croppedPhoto = null;

        function showPhotoPreview(source) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#was-photo-preview').html('<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">');
            };
            reader.readAsDataURL(source);
        }

        $('#was-player-photo').on('change', function () {
            var $input = $(this);
            var file   = this.files[0];
            if (!file) return;
            if (file.size > 8 * 1024 * 1024) {
                $('#was-form-error').text('Photo must be under 8MB.').show();
                $input.val('');
                return;
            }
            $('#was-form-error').hide();

            if (window.wasCropPhoto) {
                window.wasCropPhoto(file, function (blob) {
                    croppedPhoto = (blob && blob !== file) ? blob : null;
                    $('#was-photo-name').text(croppedPhoto ? file.name + ' (cropped)' : file.name);
                    showPhotoPreview(croppedPhoto || file);
                }, function () {
                    // cancelled — reset so the same file can be chosen again
                    croppedPhoto = null;
                    $input.val('');
                    $('#was-photo-name').text('');
                    $('#was-photo-preview').empty();
                });
            } else {
                croppedPhoto = null;
                $('#was-photo-name').text(file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + 'MB)');
                showPhotoPreview(file);
            }
        });

        // ── Form submit ────────────────────────────────────────────────────
        $form.on('submit', function (e) {
            e.preventDefault();
            $formErr.hide();

            // Validate required fields
            var errors = [];

            // Standard required inputs
            $form.find('input[required], select[required], textarea[required]').each(function () {
                var $el = $(this);
                if ($el.is(':checkbox') && !$el.prop('checked')) {
                    var cbLabel = $el.data('label') || $el.closest('.was-field').find('.was-label').first().text().replace('*','').trim();
                    errors.push(cbLabel + ' is required.');
                } else if ($el.is('select') && !$el.val()) {
                    errors.push($el.closest('.was-field').find('.was-label').first().text().replace('*','').trim() + ' is required.');
                } else if (!$el.is(':checkbox') && !$el.is(':radio') && !$el.val().trim()) {
                    errors.push($el.closest('.was-field').find('.was-label').first().text().replace('*','').trim() + ' is required.');
                }
            });

            // Consent radios — each pair must have a selection
            // (consent_medical is a mandatory checkbox, caught by the required loop above)
            var consentFields = ['consent_photography', 'consent_legal'];
            consentFields.forEach(function(name) {
                if (!$form.find('input[name="' + name + '"]:checked').length) {
                    errors.push('Please answer all consent questions.');
                }
            });

            // Medical questions — each must have yes or no selected
            if ($form.find('input[name="consent_medical"]').prop('checked')) {
                var medFields = ['yn_health_needs', 'yn_medication', 'yn_medical_training', 'yn_access_needs', 'yn_communication_needs'];
                var allAnswered = true;
                medFields.forEach(function(name) {
                    if (!$form.find('input[name="' + name + '"]:checked').length) {
                        allAnswered = false;
                    }
                });
                if (!allAnswered) {
                    errors.push('Please answer all medical questions.');
                }
            }

            // School
            if ($('#was-school-select').val() === '' || $('#was-school-select').val() === null) {
                errors.push("Please select your child's school.");
            }
            if ($('#was-school-select').val() === 'other' && !$('#was-school-other').val().trim()) {
                errors.push("Please enter your child's school name.");
            }

            // Photo
            if (!$('#was-player-photo')[0].files.length && !$form.find('input[name="photo_exists"]').val()) {
                errors.push('A player photo is required.');
            }

            if (errors.length) {
                // Deduplicate
                var unique = errors.filter(function(v, i, a) { return a.indexOf(v) === i; });
                $formErr.html(unique.join('<br>')).show();
                $('html, body').animate({ scrollTop: $formErr.offset().top - 80 }, 300);
                return;
            }

            $submit.prop('disabled', true);
            $submit.find('.was-btn-label').hide();
            $submit.find('.was-btn-spinner').show();

            var formData = new FormData($form[0]);
            if (croppedPhoto && formData.delete) {
                formData.delete('player_photo');
                formData.append('player_photo', croppedPhoto, 'photo.jpg');
            }
            formData.append('action',      'was_submit_form');
            formData.append('nonce',       wasAjax.nonce);
            formData.append('current_url', window.location.href);

            $.ajax({
                url:         wasAjax.ajaxurl,
                type:        'POST',
                data:        formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    if (res.success && res.data.redirect) {
                        window.location.href = res.data.redirect;
                    } else {
                        $formErr.html(res.data.message || 'Something went wrong. Please try again.').show();
                        $('html, body').animate({ scrollTop: $formErr.offset().top - 80 }, 300);
                        $submit.prop('disabled', false);
                        $submit.find('.was-btn-spinner').hide();
                        $submit.find('.was-btn-label').show();
                    }
                },
                error: function () {
                    $formErr.text('A network error occurred. Please check your connection and try again.').show();
                    $submit.prop('disabled', false);
                    $submit.find('.was-btn-spinner').hide();
                    $submit.find('.was-btn-label').show();
                }
            });
        });
    }

    // ── Registration: request to add an already-registered child to another team ──

    $(document).on('click', '.was-request-team', function () {
        var $b     = $(this);
        var child  = $b.data('child');
        var teamId = $b.data('team-id');
        $b.prop('disabled', true).text('Sending request…');
        $.post(wasAjax.ajaxurl, {
            action:  'was_request_second_team',
            nonce:   wasAjax.nonce,
            child:   child,
            team_id: teamId
        }, function (res) {
            if (res.success) {
                $b.replaceWith($('<p>').css({ color: '#2e7d32', 'font-weight': 700, 'margin-top': '12px' }).text((res.data && res.data.message) || 'Request sent.'));
            } else {
                $b.prop('disabled', false).text('Request to add to another team');
                alert((res.data && res.data.message) || 'Could not send request.');
            }
        }).fail(function () {
            $b.prop('disabled', false).text('Request to add to another team');
            alert('Network error — please try again.');
        });
    });

    // ── Show/hide password toggle ──────────────────────────────────────────────

    $(document).on('click', '.was-input-toggle', function () {
        var targetId = $(this).data('target');
        var $input   = $('#' + targetId);
        $input.attr('type', $input.attr('type') === 'password' ? 'text' : 'password');
    });

    // ── Admin: send overdue reminder ─────────────────────────────────────────

    $(document).on('click', '.was-send-reminder', function () {
        var $btn     = $(this);
        var playerId = $btn.data('player-id');
        var name     = $btn.data('player-name');
        var email    = $btn.data('email');

        if (!confirm('Send a payment reminder email to ' + name + "'s parent (" + email + ')?')) return;

        $btn.prop('disabled', true).text('Sending…');

        $.post(wasAjax.ajaxurl, {
            action:    'was_send_reminder',
            nonce:     wasAjax.nonce,
            player_id: playerId
        }, function (res) {
            var $result = $('#was-reminder-result');
            if (res.success) {
                $result.removeClass('was-alert--error').addClass('was-alert was-alert--success').text(res.data.message).show();
                $btn.text('Sent ✓');
            } else {
                $result.removeClass('was-alert--success').addClass('was-alert was-alert--error').text(res.data.message || 'Failed to send reminder.').show();
                $btn.prop('disabled', false).text('Send reminder');
            }
        });
    });

})(jQuery);
