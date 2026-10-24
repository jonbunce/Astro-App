/* global wasAjax, jQuery */
(function ($) {
    'use strict';

    // ── Login form ─────────────────────────────────────────────────────────────

    if ($('#was-login-btn').length) {
        $('#was-login-btn').on('click', function () {
            var $btn  = $(this);
            var $err  = $('#was-login-error');
            $err.hide();

            var login    = $('#was-login-email').val().trim();
            var password = $('#was-login-password').val();
            var remember = $('#was-login-remember').prop('checked');
            var redirect = $('#was-login-redirect').val();

            if (!login || !password) {
                $err.text('Please enter your email and password.').show();
                return;
            }

            $btn.prop('disabled', true).find('.was-btn-label').hide();
            $btn.find('.was-btn-spinner').show();

            $.post(wasAjax.ajaxurl, {
                action:      'was_do_login',
                nonce:       wasAjax.nonce,
                login:       login,
                password:    password,
                remember:    remember ? 1 : 0,
                redirect_to: redirect,
            }, function (res) {
                if (res.success) {
                    window.location.href = res.data.redirect;
                } else {
                    $err.html(res.data.message).show();
                    $btn.prop('disabled', false).find('.was-btn-label').show();
                    $btn.find('.was-btn-spinner').hide();
                }
            }).fail(function () {
                $err.text('Network error — please try again.').show();
                $btn.prop('disabled', false).find('.was-btn-label').show();
                $btn.find('.was-btn-spinner').hide();
            });
        });

        // Allow Enter key in password field to submit
        $('#was-login-password').on('keydown', function (e) {
            if (e.key === 'Enter') $('#was-login-btn').trigger('click');
        });
    }

    // ── Account registration form ──────────────────────────────────────────────

    if ($('#was-create-account-btn').length) {

        // Password strength meter
        $('#was-reg-password').on('input', function () {
            var pw  = $(this).val();
            var $strength = $('#was-pw-strength');
            var $fill     = $('#was-pw-fill');
            var $label    = $('#was-pw-label');

            if (!pw) { $strength.hide(); return; }
            $strength.show();

            var score = 0;
            if (pw.length >= 8)  score++;
            if (pw.length >= 12) score++;
            if (/[A-Z]/.test(pw)) score++;
            if (/[0-9]/.test(pw)) score++;
            if (/[^A-Za-z0-9]/.test(pw)) score++;

            var levels = [
                { cls: 'weak',   label: 'Weak',   pct: '20%'  },
                { cls: 'weak',   label: 'Weak',   pct: '20%'  },
                { cls: 'fair',   label: 'Fair',   pct: '45%'  },
                { cls: 'good',   label: 'Good',   pct: '70%'  },
                { cls: 'strong', label: 'Strong', pct: '100%' },
                { cls: 'strong', label: 'Strong', pct: '100%' },
            ];
            var l = levels[Math.min(score, 5)];
            $fill.css('width', l.pct)
                 .removeClass('was-pw-fill--weak was-pw-fill--fair was-pw-fill--good was-pw-fill--strong')
                 .addClass('was-pw-fill--' + l.cls);
            $label.text(l.label);
        });

        $('#was-create-account-btn').on('click', function () {
            var $btn = $(this);
            var $err = $('#was-reg-error');
            $err.hide();
            $('#was-reg-success').hide();

            $btn.prop('disabled', true).find('.was-btn-label').hide();
            $btn.find('.was-btn-spinner').show();

            $.post(wasAjax.ajaxurl, {
                action:              'was_do_register',
                nonce:               wasAjax.nonce,
                first_name:          $('#was-reg-first').val().trim(),
                last_name:           $('#was-reg-last').val().trim(),
                email:               $('#was-reg-email').val().trim(),
                password:            $('#was-reg-password').val(),
                password_confirm:    $('#was-reg-confirm').val(),
                telephone:           $('#was-reg-tel').val().trim(),
                address:             $('#was-reg-address').val().trim(),
                postcode:            $('#was-reg-postcode').val().trim(),
                occupation_category: $('#was-reg-occ').val(),
                employer_name:       $('#was-reg-employer').val().trim(),
            }, function (res) {
                if (res.success && res.data.redirect) {
                    window.location.href = res.data.redirect;
                } else if (res.success) {
                    // Account created, but the email address must be confirmed first:
                    // show the "check your email" message in place of the form.
                    $btn.closest('.was-account-card').children()
                        .not('.astro-login-logo-wrap, #was-reg-success, .was-account-switch').hide();
                    $('#was-reg-success').html(res.data.message).show();
                    $('html, body').animate({ scrollTop: $('#was-reg-success').offset().top - 80 }, 300);
                } else {
                    $err.html(res.data.message).show();
                    $btn.prop('disabled', false).find('.was-btn-label').show();
                    $btn.find('.was-btn-spinner').hide();
                }
            }).fail(function () {
                $err.text('Network error — please try again.').show();
                $btn.prop('disabled', false).find('.was-btn-label').show();
                $btn.find('.was-btn-spinner').hide();
            });
        });
    }

    // ── Forgot password (request a reset link) ─────────────────────────────────

    if ($('#was-forgot-btn').length) {
        $('#was-forgot-btn').on('click', function () {
            var $btn = $(this);
            var $err = $('#was-forgot-error');
            var $ok  = $('#was-forgot-success');
            $err.hide(); $ok.hide();

            var email = $('#was-forgot-email').val().trim();
            if (!email) { $err.text('Please enter your email address.').show(); return; }

            $btn.prop('disabled', true).find('.was-btn-label').hide();
            $btn.find('.was-btn-spinner').show();

            $.post(wasAjax.ajaxurl, {
                action: 'was_do_forgot',
                nonce:  wasAjax.nonce,
                email:  email
            }, function (res) {
                if (res.success) {
                    $ok.html(res.data.message).show();
                    $('#was-forgot-fields').slideUp();
                } else {
                    $err.html(res.data.message).show();
                    $btn.prop('disabled', false).find('.was-btn-label').show();
                    $btn.find('.was-btn-spinner').hide();
                }
            }).fail(function () {
                $err.text('Network error — please try again.').show();
                $btn.prop('disabled', false).find('.was-btn-label').show();
                $btn.find('.was-btn-spinner').hide();
            });
        });

        $('#was-forgot-email').on('keydown', function (e) {
            if (e.key === 'Enter') $('#was-forgot-btn').trigger('click');
        });
    }

    // ── Reset password (set a new one) ─────────────────────────────────────────

    if ($('#was-reset-btn').length) {
        $('#was-reset-password').on('input', function () {
            var pw = $(this).val();
            var $s = $('#was-reset-pw-strength'), $f = $('#was-reset-pw-fill'), $l = $('#was-reset-pw-label');
            if (!pw) { $s.hide(); return; }
            $s.show();
            var score = 0;
            if (pw.length >= 8)  score++;
            if (pw.length >= 12) score++;
            if (/[A-Z]/.test(pw)) score++;
            if (/[0-9]/.test(pw)) score++;
            if (/[^A-Za-z0-9]/.test(pw)) score++;
            var levels = [
                { cls: 'weak',   label: 'Weak',   pct: '20%'  },
                { cls: 'weak',   label: 'Weak',   pct: '20%'  },
                { cls: 'fair',   label: 'Fair',   pct: '45%'  },
                { cls: 'good',   label: 'Good',   pct: '70%'  },
                { cls: 'strong', label: 'Strong', pct: '100%' },
                { cls: 'strong', label: 'Strong', pct: '100%' }
            ];
            var lv = levels[Math.min(score, 5)];
            $f.css('width', lv.pct)
              .removeClass('was-pw-fill--weak was-pw-fill--fair was-pw-fill--good was-pw-fill--strong')
              .addClass('was-pw-fill--' + lv.cls);
            $l.text(lv.label);
        });

        $('#was-reset-btn').on('click', function () {
            var $btn = $(this);
            var $err = $('#was-reset-error');
            $err.hide();

            var pw = $('#was-reset-password').val();
            var cf = $('#was-reset-confirm').val();
            if (!pw || pw.length < 8) { $err.text('Password must be at least 8 characters.').show(); return; }
            if (pw !== cf) { $err.text('The two passwords don’t match.').show(); return; }

            $btn.prop('disabled', true).find('.was-btn-label').hide();
            $btn.find('.was-btn-spinner').show();

            $.post(wasAjax.ajaxurl, {
                action:           'was_do_reset',
                nonce:            wasAjax.nonce,
                key:              $('#was-reset-key').val(),
                login:            $('#was-reset-login').val(),
                password:         pw,
                password_confirm: cf
            }, function (res) {
                if (res.success) {
                    $('#was-reset-success').html(res.data.message).show();
                    setTimeout(function () { window.location.href = res.data.redirect; }, 900);
                } else {
                    $err.html(res.data.message).show();
                    $btn.prop('disabled', false).find('.was-btn-label').show();
                    $btn.find('.was-btn-spinner').hide();
                }
            }).fail(function () {
                $err.text('Network error — please try again.').show();
                $btn.prop('disabled', false).find('.was-btn-label').show();
                $btn.find('.was-btn-spinner').hide();
            });
        });

        $('#was-reset-confirm').on('keydown', function (e) {
            if (e.key === 'Enter') $('#was-reset-btn').trigger('click');
        });
    }

    // ── Show/hide password toggle ──────────────────────────────────────────────

    $(document).on('click', '.was-input-toggle', function () {
        var targetId = $(this).data('target');
        var $input   = $('#' + targetId);
        var isHidden = $input.attr('type') === 'password';
        $input.attr('type', isHidden ? 'text' : 'password');
    });

    // ── Portal tab navigation ──────────────────────────────────────────────────

    if ($('.was-portal-nav').length) {
        // Show the section matching the active tab, or default to the first tab
        var $firstTab = $('.was-portal-nav__tab.was-active');
        if (!$firstTab.length) {
            $firstTab = $('.was-portal-nav__tab:first');
            $firstTab.addClass('was-active');
        }
        var firstTarget = $firstTab.attr('href');
        if (firstTarget) $(firstTarget).show();

        // Check for hash in URL to deep-link to a section
        if (window.location.hash) {
            var $hashTab = $('.was-portal-nav__tab[href="' + window.location.hash + '"]');
            if ($hashTab.length) {
                $('.was-portal-section').hide();
                $('.was-portal-nav__tab').removeClass('was-active');
                $hashTab.addClass('was-active');
                $(window.location.hash).show();
            }
        }

        $('.was-portal-nav__tab').on('click', function (e) {
            e.preventDefault();
            var target = $(this).attr('href');
            $('.was-portal-nav__tab').removeClass('was-active');
            $(this).addClass('was-active');
            $('.was-portal-section').hide();
            $(target).show();
        });
    }

    // ── Logout ─────────────────────────────────────────────────────────────────

    $('#was-logout-btn').on('click', function () {
        $.post(wasAjax.ajaxurl, {
            action: 'was_do_logout',
            nonce:  wasAjax.nonce,
        }, function (res) {
            if (res.success) {
                window.location.href = res.data.redirect;
            }
        });
    });

    // ── Portal: send overdue reminder ─────────────────────────────────────────

    $(document).on('click', '.was-portal-reminder', function () {
        var $btn     = $(this);
        var playerId = $btn.data('player-id');
        var name     = $btn.data('player-name');
        var email    = $btn.data('email');

        if (!confirm('Send a payment reminder to ' + name + "'s parent (" + email + ')?')) {
            return;
        }

        $btn.prop('disabled', true).text('Sending…');

        $.post(wasAjax.ajaxurl, {
            action:    'was_portal_send_reminder',
            nonce:     wasAjax.nonce,
            player_id: playerId,
        }, function (res) {
            var $msg = $('#was-reminder-msg');
            if (res.success) {
                $msg.removeClass('was-alert--error').addClass('was-alert was-alert--success').text(res.data.message).show();
                $btn.text('Sent ✓');
            } else {
                $msg.removeClass('was-alert--success').addClass('was-alert was-alert--error').text(res.data.message || 'Could not send reminder.').show();
                $btn.prop('disabled', false).text('Send reminder');
            }
        });
    });

    // ── Parent: pay outstanding arrears (one-off Direct Debit catch-up) ────────

    $(document).on('click', '.was-pay-arrears-btn', function () {
        var $btn   = $(this);
        var name   = $btn.data('player-name');
        var amount = $btn.data('amount');
        var $msg   = $btn.closest('.was-arrears-banner').find('.was-arrears-msg');

        if (!confirm('Collect £' + amount + ' now for ' + name + '?\n\nThis takes a single one-off Direct Debit collection from your bank account using your existing mandate. GoCardless will email you before it is collected.')) {
            return;
        }

        $btn.prop('disabled', true).text('Scheduling…');
        $msg.hide();

        $.post(wasAjax.ajaxurl, {
            action:    'was_pay_arrears',
            nonce:     wasAjax.nonce,
            player_id: $btn.data('player-id'),
        }, function (res) {
            if (res.success) {
                $msg.removeClass('was-alert--error').addClass('was-alert was-alert--success').text(res.data.message).show();
                $btn.text('Scheduled ✓');
            } else {
                $msg.removeClass('was-alert--success').addClass('was-alert was-alert--error').text((res.data && res.data.message) || 'Could not schedule the payment. Please try again.').show();
                $btn.prop('disabled', false).text('Pay £' + amount + ' now');
            }
        }).fail(function () {
            $msg.removeClass('was-alert--success').addClass('was-alert was-alert--error').text('A network error occurred. Please try again.').show();
            $btn.prop('disabled', false).text('Pay £' + amount + ' now');
        });
    });

    // ── Treasurer: collect arrears now / clear hold ────────────────────────────

    function treasArrearsResult(ok, text) {
        var $r = $('#was-arrears-result');
        $r.removeClass('was-alert--success was-alert--error').addClass(ok ? 'was-alert--success' : 'was-alert--error').text(text).show();
    }

    $(document).on('click', '.was-treas-collect', function () {
        var $btn = $(this);
        var name = $btn.data('player-name');
        if (!confirm('Collect £' + $btn.data('amount') + ' from ' + name + "'s parent now as a one-off Direct Debit?\n\nThe parent will be emailed the amount and collection date.")) return;

        $btn.prop('disabled', true).text('Scheduling…');
        $.post(wasAjax.ajaxurl, {
            action:    'was_treas_collect_arrears',
            nonce:     wasAjax.nonce,
            player_id: $btn.data('player-id'),
        }, function (res) {
            if (res.success) {
                treasArrearsResult(true, res.data.message);
                $btn.text('Scheduled ✓');
            } else {
                treasArrearsResult(false, (res.data && res.data.message) || 'Could not schedule the collection.');
                $btn.prop('disabled', false).text('Collect now');
            }
        }).fail(function () {
            treasArrearsResult(false, 'A network error occurred. Please try again.');
            $btn.prop('disabled', false).text('Collect now');
        });
    });

    $(document).on('click', '.was-treas-clear-hold', function () {
        var $btn = $(this);
        var name = $btn.data('player-name');
        var note = prompt('Clear ' + name + "'s outstanding amount and lift the hold?\n\nUse this only when the money has been received another way (cash, bank transfer) or the club has agreed to write it off.\n\nOptional note for the parent (e.g. \"Paid by bank transfer on 5 Sept\"):", '');
        if (note === null) return;

        $btn.prop('disabled', true).text('Clearing…');
        $.post(wasAjax.ajaxurl, {
            action:    'was_treas_clear_hold',
            nonce:     wasAjax.nonce,
            player_id: $btn.data('player-id'),
            note:      note,
        }, function (res) {
            if (res.success) {
                treasArrearsResult(true, res.data.message);
                $btn.closest('tr').fadeOut(300, function () { $(this).remove(); applyTreasFilter(); });
            } else {
                treasArrearsResult(false, (res.data && res.data.message) || 'Could not clear the hold.');
                $btn.prop('disabled', false).text('Clear hold');
            }
        }).fail(function () {
            treasArrearsResult(false, 'A network error occurred. Please try again.');
            $btn.prop('disabled', false).text('Clear hold');
        });
    });

    // ── Treasurer: GoCardless sync ─────────────────────────────────────────────

    $('#was-gc-sync-btn').on('click', function () {
        var $btn    = $(this);
        var $result = $('#was-sync-result');

        $btn.prop('disabled', true).text('Syncing…');
        $result.hide();

        $.post(wasAjax.ajaxurl, {
            action: 'was_gc_sync',
            nonce:  wasAjax.nonce,
        }, function (res) {
            if (res.success) {
                $result.removeClass('was-alert--error').addClass('was-alert was-alert--success').text(res.data.message).show();
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                $result.removeClass('was-alert--success').addClass('was-alert was-alert--error').text(res.data.message || 'Sync failed.').show();
                $btn.prop('disabled', false).text('↻ Sync from GoCardless');
            }
        });
    });

    // ── Portal: save profile ─────────────────────────────────────────────────

    $('#was-profile-save-btn').on('click', function () {
        var $btn = $(this);
        var $ok  = $('#was-profile-success');
        var $err = $('#was-profile-error');
        $ok.hide(); $err.hide();

        var first    = $('#was-profile-first').val().trim();
        var last     = $('#was-profile-last').val().trim();
        var tel      = $('#was-profile-tel').val().trim();
        var address  = $('#was-profile-address').val().trim();
        var postcode = $('#was-profile-postcode').val().trim();

        if (!first || !last || !tel || !address || !postcode) {
            $err.text('Please complete all required fields.').show();
            return;
        }

        $btn.prop('disabled', true).find('.was-btn-label').hide();
        $btn.find('.was-btn-spinner').show();

        $.post(wasAjax.ajaxurl, {
            action:              'was_update_profile',
            nonce:               wasAjax.nonce,
            first_name:          first,
            last_name:           last,
            telephone:           tel,
            address:             address,
            postcode:            postcode,
            occupation_category: $('#was-profile-occ').val(),
            employer_name:       $('#was-profile-employer').val().trim(),
        }, function (res) {
            if (res.success) {
                $ok.text(res.data.message || 'Profile updated successfully.').show();
            } else {
                $err.text(res.data.message || 'Could not save profile.').show();
            }
            $btn.prop('disabled', false).find('.was-btn-label').show();
            $btn.find('.was-btn-spinner').hide();
        });
    });

    // ── Onboarding: complete profile (no-role users) ────────────────────────────
    // Shown to logged-in users with no ASTRO role (e.g. existing WooCommerce
    // customers). Posts to the same was_update_profile handler, which creates the
    // guardian record and assigns the 'parent' role, then reloads so the user
    // lands in their portal.

    $('#was-onboard-save-btn').on('click', function () {
        var $btn = $(this);
        var $ok  = $('#was-onboard-success');
        var $err = $('#was-onboard-error');
        $ok.hide(); $err.hide();

        var first    = $('#was-ob-first').val().trim();
        var last     = $('#was-ob-last').val().trim();
        var tel      = $('#was-ob-tel').val().trim();
        var address  = $('#was-ob-address').val().trim();
        var postcode = $('#was-ob-postcode').val().trim();
        var occ      = $('#was-ob-occ').val();

        if (!first || !last || !tel || !address || !postcode || !occ) {
            $err.text('Please complete all required fields.').show();
            return;
        }

        $btn.prop('disabled', true).find('.was-btn-label').hide();
        $btn.find('.was-btn-spinner').show();

        $.post(wasAjax.ajaxurl, {
            action:              'was_update_profile',
            nonce:               wasAjax.nonce,
            first_name:          first,
            last_name:           last,
            telephone:           tel,
            address:             address,
            postcode:            postcode,
            occupation_category: occ,
            employer_name:       $('#was-ob-employer').val().trim(),
        }, function (res) {
            if (res.success) {
                $ok.text('Profile saved — taking you to your portal…').show();
                setTimeout(function () { window.location.reload(); }, 800);
            } else {
                $err.text((res.data && res.data.message) ? res.data.message : 'Could not save your profile. Please try again.').show();
                $btn.prop('disabled', false).find('.was-btn-label').show();
                $btn.find('.was-btn-spinner').hide();
            }
        }).fail(function () {
            $err.text('Network error — please try again.').show();
            $btn.prop('disabled', false).find('.was-btn-label').show();
            $btn.find('.was-btn-spinner').hide();
        });
    });

    // ── Parent: toggle child edit form ──────────────────────────────────────────

    $(document).on('click', '.was-child-edit-toggle', function () {
        var playerId = $(this).data('player-id');
        var $form    = $('#was-edit-' + playerId);
        var opening  = !$form.is(':visible'); // state AFTER the toggle
        $form.slideToggle(200);
        // Swap the header icon (pencil <-> close); reflects both the header
        // button and the form's own Cancel button closing it.
        $('.was-iconbtn.was-child-edit-toggle[data-player-id="' + playerId + '"]')
            .toggleClass('is-open', opening)
            .attr('title', opening ? 'Close editor' : 'Edit details');
    });

    // ── Parent: "add another team" request panel ────────────────────────────────

    $(document).on('click', '.was-addteam-toggle', function () {
        var playerId = $(this).data('player-id');
        $('#was-addteam-' + playerId).slideToggle(150);
    });

    $(document).on('click', '.was-addteam-send', function () {
        var $btn   = $(this);
        var $panel = $('#was-addteam-' + $btn.data('player-id'));
        var teamId = $panel.find('.was-addteam-select').val();
        var $msg   = $panel.find('.was-addteam-msg');

        if (!teamId) {
            $msg.removeClass('was-alert--success').addClass('was-alert--error')
                .text('Please choose a team first.').show();
            return;
        }
        $btn.prop('disabled', true).text('Sending…');
        $msg.hide();

        $.post(wasAjax.ajaxurl, {
            action:    'was_request_second_team',
            nonce:     wasAjax.nonce,
            player_id: $btn.data('player-id'),
            team_id:   teamId
        }, function (res) {
            if (res.success) {
                $panel.find('.was-addteam-form').slideUp(150);
                $msg.removeClass('was-alert--error').addClass('was-alert--success')
                    .html(res.data.message).show();
            } else {
                $msg.removeClass('was-alert--success').addClass('was-alert--error')
                    .text((res.data && res.data.message) || 'Something went wrong. Please try again.').show();
                $btn.prop('disabled', false).text('Send request');
            }
        }).fail(function () {
            $msg.removeClass('was-alert--success').addClass('was-alert--error')
                .text('A network error occurred. Please try again.').show();
            $btn.prop('disabled', false).text('Send request');
        });
    });

    // ── Parent: medical yes/no detail toggle in edit form ───────────────────────

    $(document).on('change', '.was-med-yn-toggle', function () {
        var detailId = $(this).data('detail');
        var isYes    = $(this).val() === 'yes';
        $('#' + detailId).toggle(isYes);
    });

    // ── Parent: school Other toggle in edit form ──────────────────────────────

    $(document).on('change', '.was-school-select-edit', function () {
        var wrapId = $(this).data('wrap');
        $('#' + wrapId).toggle($(this).val() === 'other');
    });

    // ── Parent: save child info ───────────────────────────────────────────────

    $(document).on('click', '.was-save-child-btn', function () {
        var $btn      = $(this);
        var playerId  = $btn.data('player-id');
        var $form     = $('#was-edit-' + playerId);
        var $msg      = $('#was-child-edit-msg-' + playerId);
        $msg.hide().removeClass('was-alert--success was-alert--error');

        // Collect form data
        var data = { action: 'was_update_child', nonce: wasAjax.nonce, player_id: playerId };
        $form.find('input, select, textarea').each(function () {
            var el = $(this);
            if (el.is(':radio') && !el.prop('checked')) return;
            if (el.attr('type') === 'file') return;
            if (el.attr('class')?.includes('was-consent-radio')) return; // handled separately
            data[el.attr('name')] = el.val();
        });

        $btn.prop('disabled', true).find('.was-btn-label').hide();
        $btn.find('.was-btn-spinner').show();

        $.post(wasAjax.ajaxurl, data, function (res) {
            $msg.addClass(res.success ? 'was-alert--success' : 'was-alert--error')
                .text(res.data?.message || (res.success ? 'Saved.' : 'Error saving.')).show();
            $btn.prop('disabled', false).find('.was-btn-label').show();
            $btn.find('.was-btn-spinner').hide();
        });
    });

    // ── Parent: consent change → safeguarding alert ───────────────────────────

    $(document).on('change', '.was-consent-radio', function () {
        var $radio    = $(this);
        var playerId  = $radio.data('player-id');
        var type      = $radio.data('type');
        var value     = $radio.val();
        var $msg      = $('#was-child-edit-msg-' + playerId);

        $.post(wasAjax.ajaxurl, {
            action:        'was_update_consent',
            nonce:         wasAjax.nonce,
            player_id:     playerId,
            consent_type:  type,
            consent_value: value,
        }, function (res) {
            $msg.removeClass('was-alert--success was-alert--error')
                .addClass(res.success ? 'was-alert--success' : 'was-alert--error')
                .text(res.data?.message || (res.success ? 'Consent updated.' : 'Error.'))
                .show();
        });
    });

    // ── Parent: change child photo (native <label> opens the picker; then crop → upload) ──

    $(document).on('change', '.was-photo-file-input', function () {
        var $input   = $(this);
        var playerId = $input.data('player-id');
        var file     = this.files[0];
        if (!file) return;
        if (file.size > 8 * 1024 * 1024) {
            alert('Photo must be under 8MB.');
            $input.val('');
            return;
        }
        wasCropPhoto(file, function (blob) {
            wasUploadChildPhoto(playerId, blob, $input);
        }, function () {
            $input.val(''); // cancelled — reset so the same file can be chosen again
        });
    });

    function wasUploadChildPhoto(playerId, blob, $input) {
        var fd = new FormData();
        fd.append('action',    'was_update_child_photo');
        fd.append('nonce',     wasAjax.nonce);
        fd.append('player_id', playerId);
        fd.append('photo',     blob, 'photo.jpg');

        var $thumb = $('#was-thumb-' + playerId);
        $.ajax({
            url: wasAjax.ajaxurl, type: 'POST', data: fd,
            processData: false, contentType: false,
            success: function (res) {
                if (res.success) {
                    if ($thumb.is('img')) {
                        $thumb.attr('src', res.data.photo_url + '&t=' + Date.now());
                    } else {
                        $thumb.replaceWith('<img src="' + res.data.photo_url + '" class="was-player-avatar-img" id="was-thumb-' + playerId + '" alt="">');
                    }
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Could not update photo.');
                }
                if ($input) $input.val('');
            },
            error: function () {
                alert('Network error uploading photo.');
                if ($input) $input.val('');
            }
        });
    }

    // Square pan-and-zoom cropper. Calls onDone(blob) with a 512x512 JPEG, or onCancel().
    // iOS-robust: no MIME gate (iOS often reports an empty file.type for library/camera
    // photos), FileReader source, big photos downscaled to a safe working size, and
    // touch + mouse drag handlers.
    function wasCropPhoto(file, onDone, onCancel) {
        if (!window.FileReader || !document.createElement('canvas').getContext) { onDone(file); return; }

        var reader = new FileReader();
        reader.onerror = function () { onDone(file); };
        reader.onload = function (ev) {
            var img = new Image();
            img.onerror = function () { onDone(file); };
            img.onload = function () {
                var iw = img.naturalWidth, ih = img.naturalHeight;
                if (!iw || !ih) { onDone(file); return; }

                // Downscale very large photos — iOS silently subsamples big canvas sources.
                var MAXD = 1600, work = img, nw = iw, nh = ih;
                if (Math.max(iw, ih) > MAXD) {
                    var r = MAXD / Math.max(iw, ih);
                    nw = Math.round(iw * r); nh = Math.round(ih * r);
                    var wc = document.createElement('canvas');
                    wc.width = nw; wc.height = nh;
                    wc.getContext('2d').drawImage(img, 0, 0, nw, nh);
                    work = wc;
                }
                var displaySrc = (work === img) ? img.src : work.toDataURL('image/jpeg', 0.92);

                var V  = Math.max(180, Math.min(300, window.innerWidth - 72, window.innerHeight - 230));
                var baseScale = V / Math.min(nw, nh);
                var scale = baseScale;
                var tx = (V - nw * scale) / 2, ty = (V - nh * scale) / 2;

                var $ov = $(
                    '<div class="was-crop-overlay"><div class="was-crop-box">' +
                    '<div class="was-crop-title">Position &amp; zoom the photo</div>' +
                    '<div class="was-crop-viewport"><img class="was-crop-img" alt=""></div>' +
                    '<div class="was-crop-zoomrow"><span>&minus;</span>' +
                    '<input type="range" class="was-crop-zoom" min="1" max="4" step="0.01" value="1"><span>+</span></div>' +
                    '<div class="was-crop-actions">' +
                    '<button type="button" class="was-btn was-btn--secondary was-crop-cancel">Cancel</button>' +
                    '<button type="button" class="was-btn was-btn--primary was-crop-use">Use photo</button>' +
                    '</div></div></div>'
                );
                var $vp = $ov.find('.was-crop-viewport').css({ width: V, height: V });
                var $im = $ov.find('.was-crop-img').attr('src', displaySrc);
                $('body').addClass('was-crop-open').append($ov);

                function clamp() {
                    var w = nw * scale, h = nh * scale;
                    if (tx > 0) tx = 0; if (tx < V - w) tx = V - w;
                    if (ty > 0) ty = 0; if (ty < V - h) ty = V - h;
                }
                function render() { $im.css({ width: nw * scale, height: nh * scale, left: tx, top: ty }); }
                clamp(); render();

                var dragging = false, px = 0, py = 0, tx0 = 0, ty0 = 0;
                function pt(e) {
                    var o = e.originalEvent || e;
                    if (o.touches && o.touches[0]) return { x: o.touches[0].clientX, y: o.touches[0].clientY };
                    return { x: e.clientX, y: e.clientY };
                }
                function down(e) { var p = pt(e); dragging = true; px = p.x; py = p.y; tx0 = tx; ty0 = ty; e.preventDefault(); }
                function move(e) { if (!dragging) return; var p = pt(e); tx = tx0 + (p.x - px); ty = ty0 + (p.y - py); clamp(); render(); e.preventDefault(); }
                function up() { dragging = false; }
                $vp.on('touchstart', down).on('touchmove', move).on('touchend touchcancel', up);
                $vp.on('mousedown', down);
                $(document).on('mousemove.wascrop', move).on('mouseup.wascrop', up);

                $ov.find('.was-crop-zoom').on('input', function () {
                    var cx = V / 2, cy = V / 2;
                    var icx = (cx - tx) / scale, icy = (cy - ty) / scale;
                    scale = baseScale * parseFloat(this.value);
                    tx = cx - icx * scale; ty = cy - icy * scale;
                    clamp(); render();
                });

                function close() { $ov.remove(); $('body').removeClass('was-crop-open'); $(document).off('.wascrop'); }
                $ov.find('.was-crop-cancel').on('click', function () { close(); if (onCancel) onCancel(); });
                $ov.on('click', function (e) { if (e.target === $ov[0]) { close(); if (onCancel) onCancel(); } });

                $ov.find('.was-crop-use').on('click', function () {
                    var OUT = 512;
                    var canvas = document.createElement('canvas');
                    canvas.width = OUT; canvas.height = OUT;
                    canvas.getContext('2d').drawImage(work, -tx / scale, -ty / scale, V / scale, V / scale, 0, 0, OUT, OUT);
                    if (canvas.toBlob) { canvas.toBlob(function (blob) { close(); onDone(blob || file); }, 'image/jpeg', 0.9); }
                    else { close(); onDone(file); }
                });
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    }
    // Shared with the registration form (registration.js).
    window.wasCropPhoto = wasCropPhoto;

    // ── Coach: upload team logo (native <label> opens the picker) ─────────────

    $(document).on('change', '.was-logo-file-input', function () {
        var $input = $(this);
        var teamId = $input.data('team-id');
        var file   = this.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) { alert('Logo must be under 2MB.'); $input.val(''); return; }

        var fd = new FormData();
        fd.append('action',  'was_upload_team_logo');
        fd.append('nonce',   wasAjax.nonce);
        fd.append('team_id', teamId);
        fd.append('logo',    file);

        var $status = $('.was-logo-upload-status[data-team-id="' + teamId + '"]');
        $status.text('Uploading…').show();

        $.ajax({
            url: wasAjax.ajaxurl, type: 'POST', data: fd,
            processData: false, contentType: false,
            success: function (res) {
                if (res.success) {

                    $status.text('Logo updated ✓').css('color','#2e7d32');
                    // Update existing logo img or insert one
                    var teamId2 = $input.data('team-id');
                    var $wrap = $('.was-logo-upload-btn[data-team-id="' + teamId2 + '"]');
                    var $existing = $wrap.find('.was-team-logo');
                    if ($existing.length) {
                        $existing.attr('src', res.data.logo_url + '?t=' + Date.now());
                    } else {
                        $wrap.find('.was-team-logo-placeholder').replaceWith(
                            '<img src="' + res.data.logo_url + '" class="was-team-logo" alt="" id="was-logo-img-' + teamId2 + '">'
                        );
                    }
                } else {
                    $status.text(res.data?.message || 'Upload failed.').css('color','#c62828');

                }
                $input.val('');
            },
            error: function () { $status.text('Network error.').css('color','#c62828'); $btn.prop('disabled', false); }
        });
    });

    // ── Treasurer: stat tile filter + team filter ────────────────────────────────

    function applyTreasFilter() {
        var statusFilter = $('.was-treas-stat.was-treas-stat--active').data('filter') || 'all';
        var teamFilter   = $('#was-treas-team-filter').val();
        var $rows        = $('.was-treas-table tbody tr');

        $rows.each(function () {
            var $row     = $(this);
            var rowTeam  = $row.data('team-id') + '';
            var rowStatus = $row.data('status') + '';

            var statusMatch = statusFilter === 'all' || rowStatus === statusFilter;
            var teamMatch   = !teamFilter || rowTeam === teamFilter;

            $row.toggleClass('was-hidden', !(statusMatch && teamMatch));
        });

        // Update visible count
        var visible = $rows.filter(':not(.was-hidden)').length;
        $('#was-treas-row-count').text(visible + ' player' + (visible !== 1 ? 's' : ''));

        // Hide a whole table block if the active filter leaves it with no visible rows,
        // so we don't show a heading + empty table (e.g. Overdue while "Confirmed" is active).
        $('.was-treas-block').each(function () {
            var $block = $(this);
            if (!$block.find('.was-treas-table').length) return;
            $block.toggle($block.find('tbody tr:not(.was-hidden)').length > 0);
        });
    }

    $(document).on('click', '.was-treas-stat[data-filter]', function () {
        var $tile = $(this);
        if ($tile.hasClass('was-treas-stat--active')) {
            $tile.removeClass('was-treas-stat--active');
        } else {
            $('.was-treas-stat').removeClass('was-treas-stat--active');
            $tile.addClass('was-treas-stat--active');
        }
        $('#was-treas-clear-filter').toggle($tile.hasClass('was-treas-stat--active') || !!$('#was-treas-team-filter').val());
        applyTreasFilter();
    });

    $(document).on('change', '#was-treas-team-filter', function () {
        $('#was-treas-clear-filter').toggle(!!$(this).val() || $('.was-treas-stat--active').length > 0);
        applyTreasFilter();
    });

    $(document).on('click', '#was-treas-clear-filter', function () {
        $('.was-treas-stat').removeClass('was-treas-stat--active');
        $('#was-treas-team-filter').val('');
        $(this).hide();
        applyTreasFilter();
    });

    // ── Parent: lapsed-DD re-subscribe ─────────────────────────────────────────

    $(document).on('click', '.was-rejoin-btn', function () {
        var $b = $(this);
        $b.prop('disabled', true).text('Connecting to GoCardless…');
        $.post(wasAjax.ajaxurl, { action: 'was_rejoin_start', nonce: wasAjax.nonce, player_id: $b.data('player-id') }, function (res) {
            if (res.success && res.data.gc_url) {
                window.location.href = res.data.gc_url;
            } else {
                alert((res.data && res.data.message) || 'Something went wrong. Please try again.');
                $b.prop('disabled', false).text('Set up a new Direct Debit');
            }
        }).fail(function () {
            alert('A network error occurred. Please try again.');
            $b.prop('disabled', false).text('Set up a new Direct Debit');
        });
    });

    // Rejoin outcome notice (from the GoCardless return redirect)
    (function () {
        var m = window.location.search.match(/[?&]was_rejoined=([^&]+)/);
        if (m && $('#was-section-parent').length) {
            var name = decodeURIComponent(m[1].replace(/\+/g, ' '));
            $('<div class="was-alert was-alert--success" style="margin-bottom:1rem;"></div>')
                .text('Direct Debit set up — ' + name + ' is registered and active again 🎉')
                .prependTo('#was-section-parent');
        } else if (/[?&]was_rejoin_error=1/.test(window.location.search) && $('#was-section-parent').length) {
            $('<div class="was-alert was-alert--error" style="margin-bottom:1rem;"></div>')
                .text("The Direct Debit setup didn't complete. Nothing has changed — please try again or contact the club.")
                .prependTo('#was-section-parent');
        }
    })();

    // ── Messages tab (committee/admin): compose + batched send ────────────────

    $(document).on('change', '.was-msg-aud', function () {
        $('#was-msg-team-wrap').toggle($('.was-msg-aud[value="parents"]').prop('checked'));
    });

    function wasMsgFromMode() { return $('input[name="was-msg-from"]:checked').val() || 'me'; }

    // Schedule for later: show the picker (defaulting to the next whole hour) and relabel the button
    $(document).on('change', 'input[name="was-msg-when"]', function () {
        var later = $('input[name="was-msg-when"]:checked').val() === 'later';
        var $at   = $('#was-msg-send-at');
        $('#was-msg-later-wrap').toggle(later);
        $('#was-msg-send .was-btn-label').text(later ? 'Schedule message' : 'Send message');
        $('#was-msg-send .was-btn-spinner').text(later ? 'Scheduling…' : 'Sending…');
        if (later && !$at.val()) {
            var d   = new Date(Date.now() + 90 * 60000);
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            $at.val(d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':00');
        }
    });

    $(document).on('click', '.was-msg-cancel', function () {
        var $b = $(this);
        if (!window.confirm('Cancel this scheduled message? It will not be sent.')) return;
        $b.prop('disabled', true);
        $.post(wasAjax.ajaxurl, { action: 'was_msg_cancel', nonce: wasAjax.nonce, id: $b.data('id') }, function (res) {
            if (!res.success) { $b.prop('disabled', false); window.alert((res.data && res.data.message) || 'Something went wrong.'); return; }
            $b.closest('tr').replaceWith(res.data.row);
        }).fail(function () { $b.prop('disabled', false); window.alert('A network error occurred.'); });
    });

    $(document).on('click', '#was-msg-send', function () {
        var $btn   = $(this);
        var $alert = $('#was-msg-alert');
        var $prog  = $('#was-msg-progress');

        var audiences = $('.was-msg-aud:checked').map(function () { return this.value; }).get();
        var subject   = $('#was-msg-subject').val().trim();
        var body      = $('#was-msg-body').val().trim();

        function fail(msg) {
            $alert.removeClass('was-alert--success').addClass('was-alert--error')
                  .text(msg || 'Something went wrong.').show();
            reset();
        }
        function reset() {
            $btn.prop('disabled', false);
            $btn.find('.was-btn-spinner').hide();
            $btn.find('.was-btn-label').show();
        }

        $alert.hide();
        if (!audiences.length || !subject || !body) {
            fail('Choose an audience and fill in the subject and message.');
            return;
        }

        $btn.prop('disabled', true);
        $btn.find('.was-btn-label').hide();
        $btn.find('.was-btn-spinner').show();

        var fromMode = wasMsgFromMode();
        var asClub   = fromMode === 'club' ? ' as the club (' + $('input[name="was-msg-from"][value="club"]').data('email') + ')' : '';

        if ($('input[name="was-msg-when"]:checked').val() === 'later') {
            // Validate + count first (dry run), confirm, then save it to the queue
            var sched = {
                action: 'was_msg_schedule', nonce: wasAjax.nonce,
                audiences: audiences, team_id: $('#was-msg-team').val() || 0,
                subject: subject, body: body, from_mode: fromMode,
                send_at: $('#was-msg-send-at').val(), dry_run: 1
            };
            $.post(wasAjax.ajaxurl, sched, function (res) {
                if (!res.success) { fail(res.data && res.data.message); return; }
                var n = res.data.total;
                if (!window.confirm('Schedule this message' + asClub + ' for ' + res.data.when + '?\n\nRight now it would go to ' + n + ' recipient' + (n === 1 ? '' : 's') + '; the list is checked again when it sends.')) {
                    reset();
                    return;
                }
                sched.dry_run = 0;
                $.post(wasAjax.ajaxurl, sched, function (r2) {
                    if (!r2.success) { fail(r2.data && r2.data.message); return; }
                    $('#was-msg-history .was-msg-empty').remove();
                    $('#was-msg-history tbody').prepend(r2.data.row);
                    $alert.removeClass('was-alert--error').addClass('was-alert--success')
                          .text('Scheduled for ' + r2.data.when + ' ✓ You can cancel it under Recent messages until then.').show();
                    $('#was-msg-subject').val('');
                    $('#was-msg-body').val('');
                    reset();
                }).fail(function () { fail('A network error occurred. Check Recent messages before trying again.'); });
            }).fail(function () { fail('A network error occurred.'); });
            return;
        }

        $.post(wasAjax.ajaxurl, {
            action: 'was_msg_prepare', nonce: wasAjax.nonce,
            audiences: audiences, team_id: $('#was-msg-team').val() || 0,
            subject: subject, body: body, from_mode: fromMode
        }, function (res) {
            if (!res.success) { fail(res.data && res.data.message); return; }

            var total = res.data.total, token = res.data.token;
            if (!window.confirm('Send this message' + asClub + ' to ' + total + ' recipient' + (total === 1 ? '' : 's') + '?')) {
                reset();
                return;
            }
            $prog.text('Sending 0 / ' + total + '…').show();

            var retried = false;
            (function next() {
                $.post(wasAjax.ajaxurl, { action: 'was_msg_batch', nonce: wasAjax.nonce, token: token }, function (r2) {
                    if (!r2.success) { $prog.hide(); fail(r2.data && r2.data.message); return; }
                    retried = false;
                    $prog.text('Sending ' + r2.data.sent + ' / ' + r2.data.total + '…');
                    if (r2.data.done) {
                        $prog.hide();
                        $alert.removeClass('was-alert--error').addClass('was-alert--success')
                              .text('Message sent to ' + r2.data.total + ' recipients ✓').show();
                        $('#was-msg-subject').val('');
                        $('#was-msg-body').val('');
                        reset();
                    } else {
                        next();
                    }
                }).fail(function () {
                    if (!retried) { retried = true; setTimeout(next, 2500); return; } // one auto-retry per batch
                    $prog.hide();
                    fail('The send was interrupted part-way — some recipients have already received it. Please contact support before resending.');
                });
            })();
        }).fail(function () { fail('A network error occurred.'); });
    });

    $(document).on('click', '#was-msg-test', function () {
        var $btn   = $(this);
        var $alert = $('#was-msg-alert');

        var to      = $('#was-msg-test-to').val().trim();
        var subject = $('#was-msg-subject').val().trim();
        var body    = $('#was-msg-body').val().trim();

        function done(ok, msg) {
            $alert.removeClass('was-alert--success was-alert--error')
                  .addClass(ok ? 'was-alert--success' : 'was-alert--error')
                  .text(msg || 'Something went wrong.').show();
            $btn.prop('disabled', false);
            $btn.find('.was-btn-spinner').hide();
            $btn.find('.was-btn-label').show();
        }

        $alert.hide();
        if (!to || !subject || !body) {
            done(false, 'Fill in the subject, message and test address.');
            return;
        }

        $btn.prop('disabled', true);
        $btn.find('.was-btn-label').hide();
        $btn.find('.was-btn-spinner').show();

        $.post(wasAjax.ajaxurl, {
            action: 'was_msg_test', nonce: wasAjax.nonce,
            to: to, subject: subject, body: body, from_mode: wasMsgFromMode()
        }, function (res) {
            if (!res.success) { done(false, res.data && res.data.message); return; }
            done(true, 'Test sent to ' + res.data.to + ' ✓ Check it, then send for real.');
        }).fail(function () { done(false, 'A network error occurred.'); });
    });

    // ── Registration officer: Players panel search + team filter ─────────────

    function wasRegFilter() {
        var q    = ($('#was-reg-search').val() || '').toLowerCase().trim();
        var team = $('#was-reg-team-filter').val();
        var shown = 0;
        $('#was-reg-table tbody tr').each(function () {
            var $row = $(this);
            var textMatch = !q || $row.text().toLowerCase().indexOf(q) !== -1;
            var teamMatch = !team || ($row.attr('data-team-ids') || '').indexOf(',' + team + ',') !== -1;
            var hit = textMatch && teamMatch;
            $row.toggle(hit);
            if (hit) shown++;
        });
        $('#was-reg-shown').text(shown);
        $('#was-reg-empty').toggle(shown === 0);
        $('#was-reg-table').toggle(shown > 0);
    }
    $(document).on('input',  '#was-reg-search',      wasRegFilter);
    $(document).on('change', '#was-reg-team-filter', wasRegFilter);
    // Apply the server-preselected default team (first alphabetically) on first load
    if ($('#was-reg-table').length) wasRegFilter();

    // ── Committee: Interest List (waiting list) ───────────────────────────────

    // 'waiting' is the working view — everyone not yet placed or archived.
    var WAS_INT_WAITING = ['new', 'contacted', 'offered'];

    function wasIntFilter() {
        var q      = ($('#was-int-search').val() || '').toLowerCase().trim();
        var status = $('#was-int-status-filter').val();
        var $rows  = $('#was-int-table tbody tr');
        var shown  = 0;

        $rows.each(function () {
            var $row = $(this);
            var rowStatus = $row.attr('data-status') || '';
            var textMatch = !q || $row.text().toLowerCase().indexOf(q) !== -1;
            var statusMatch = !status
                || (status === 'waiting' ? WAS_INT_WAITING.indexOf(rowStatus) !== -1 : rowStatus === status);
            var hit = textMatch && statusMatch;
            $row.toggle(hit);
            if (hit) shown++;
        });

        $('#was-int-shown').text(shown);
        $('#was-int-table').toggle(shown > 0);
        $('#was-int-empty')
            .text($rows.length ? 'No entries match that filter.'
                               : 'Nothing here yet — entries from the Register Your Interest form appear here automatically.')
            .toggle(shown === 0);
    }
    $(document).on('input',  '#was-int-search',        wasIntFilter);
    $(document).on('change', '#was-int-status-filter', wasIntFilter);
    if ($('#was-int-table').length) wasIntFilter();

    function wasIntNotice(text, isError) {
        $('#was-int-msg')
            .removeClass('was-alert--success was-alert--error')
            .addClass(isError ? 'was-alert--error' : 'was-alert--success')
            .text(text).show();
    }

    $(document).on('click', '.was-int-save', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');

        $btn.prop('disabled', true).text('Saving…');
        $.post(wasAjax.ajaxurl, {
            action:   'was_interest_save',
            nonce:    wasAjax.nonce,
            entry_id: $row.data('entry-id'),
            status:   $row.find('.was-int-status').val(),
            notes:    $row.find('.was-int-notes').val(),
        }, function (res) {
            $btn.prop('disabled', false).text('Save');
            if (res.success) {
                // Keep the row's filter state in step with its new status.
                $row.attr('data-status', res.data.status);
                wasIntNotice('Saved.', false);
                wasIntFilter();
            } else {
                wasIntNotice((res.data && res.data.message) || 'Could not save that entry.', true);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Save');
            wasIntNotice('Could not reach the server — please try again.', true);
        });
    });

    $(document).on('click', '.was-int-delete', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var who  = ($row.find('td:eq(1) strong').text() || 'this entry').trim();

        if (!window.confirm('Permanently delete ' + who + ' from the interest list? This can’t be undone.')) return;

        $btn.prop('disabled', true).text('Deleting…');
        $.post(wasAjax.ajaxurl, {
            action:   'was_interest_delete',
            nonce:    wasAjax.nonce,
            entry_id: $row.data('entry-id'),
        }, function (res) {
            if (res.success) {
                $row.remove();
                wasIntNotice('Entry deleted.', false);
                wasIntFilter();
            } else {
                $btn.prop('disabled', false).text('Delete');
                wasIntNotice((res.data && res.data.message) || 'Could not delete that entry.', true);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Delete');
            wasIntNotice('Could not reach the server — please try again.', true);
        });
    });

    // ── Portal nav: scroll-hint arrows + edge fades (any width) ───────────────

    function wasNavArrows() {
        var $nav = $('.was-portal-nav');
        if (!$nav.length) return;
        var el = $nav[0];
        var canScroll = el.scrollWidth > el.clientWidth + 4;
        var moreLeft  = canScroll && el.scrollLeft > 4;
        var moreRight = canScroll && el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
        $('.was-nav-arrow--left').toggleClass('is-hidden', !moreLeft);
        $('.was-nav-arrow--right').toggleClass('is-hidden', !moreRight);
        // Edge fades mirror the arrows, so the bar reads as scrollable on desktop.
        $('.was-portal-nav-wrap')
            .toggleClass('has-more-left', moreLeft)
            .toggleClass('has-more-right', moreRight);
    }
    $(document).on('click', '.was-nav-arrow', function () {
        var $nav = $('.was-portal-nav');
        if (!$nav.length) return;
        var dir = $(this).hasClass('was-nav-arrow--left') ? -1 : 1;
        if ($nav[0].scrollBy) { $nav[0].scrollBy({ left: dir * 160, behavior: 'smooth' }); }
        else { $nav[0].scrollLeft += dir * 160; }
    });
    $('.was-portal-nav').on('scroll', wasNavArrows);
    $(window).on('resize', wasNavArrows);
    $(document).on('click', '.was-portal-nav__tab', function () { setTimeout(wasNavArrows, 0); });
    wasNavArrows();
    // Tab widths shift once the webfont swaps in, so measure again then.
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(wasNavArrows); }
    $(window).on('load', wasNavArrows);

    // ── Social Media Officer: team filter ────────────────────────────────────

    $('#was-smo-team-filter').on('change', function () {
        var teamId  = $(this).val();
        var $rows   = $('#was-smo-table tbody tr');
        var visible = 0;

        $rows.each(function () {
            var match = !teamId || $(this).data('team-id') == teamId;
            $(this).toggleClass('was-hidden', !match);
            if (match) visible++;
        });

        var noun = visible === 1 ? 'player' : 'players';
        $('#was-smo-count').text(visible + ' ' + noun + ' with consent restrictions');
    });

    // ── Social Media Officer: photo lightbox ──────────────────────────────────

    $(document).on('click', '.was-smo-thumb-link', function (e) {
        e.preventDefault();
        // The lightbox overlay ships with the SMO template — create it on demand for
        // pages (e.g. the registration officer's Players panel) that don't include it.
        if (!$('#was-photo-lightbox').length) {
            $('body').append(
                '<div id="was-photo-lightbox" class="was-lightbox" style="display:none;" role="dialog" aria-label="Player photo">' +
                '<div class="was-lightbox-backdrop"></div>' +
                '<div class="was-lightbox-content">' +
                '<button class="was-lightbox-close" id="was-lightbox-close" aria-label="Close">&times;</button>' +
                '<img id="was-lightbox-img" src="" alt="Player photo">' +
                '</div></div>'
            );
        }
        var src = $(this).attr('href');
        $('#was-lightbox-img').attr('src', src);
        $('#was-photo-lightbox').fadeIn(150);
    });

    $(document).on('click', '#was-lightbox-close, .was-lightbox-backdrop', function () {
        $('#was-photo-lightbox').fadeOut(150, function () {
            $('#was-lightbox-img').attr('src', '');
        });
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('#was-photo-lightbox').fadeOut(150, function () {
                $('#was-lightbox-img').attr('src', '');
            });
        }
    });

    // ── Coach: edit team profile (league + description) ──────────────────────

    $(document).on('click', '.was-profile-toggle-btn, .was-profile-cancel-btn', function () {
        var $wrap = $(this).closest('.was-team-profile-edit');
        $wrap.find('.was-profile-form').slideToggle(150);
        $wrap.find('.was-profile-save-status').hide();
    });

    $(document).on('submit', '.was-profile-form', function (e) {
        e.preventDefault();
        var $form   = $(this);
        var $wrap   = $form.closest('.was-team-profile-edit');
        var $status = $wrap.find('.was-profile-save-status');
        var $btns   = $form.find('.was-btn').prop('disabled', true);

        $.post(wasAjax.ajaxurl, {
            action:        'was_team_profile_save',
            nonce:         wasAjax.nonce,
            team_id:       $wrap.data('team-id'),
            league:        $form.find('.was-profile-league-input').val(),
            description:   $form.find('.was-profile-desc-input').val(),
            training_info: $form.find('.was-profile-training-input').val() || '',
            coach_names:   $form.find('.was-profile-coaches-input').val() || '',
            coach_contact: $form.find('.was-profile-contact-input').val() || '',
            honours:       $form.find('.was-profile-honours-input').val() || '',
            sponsor_line:  $form.find('.was-profile-sponsor-input').val() || ''
        }, function (res) {
            $btns.prop('disabled', false);
            if (res.success) {
                $form.slideUp(150);
                $status.text('Profile saved ✓').css('color', '#2e7d32').show();
            } else {
                $status.text((res.data && res.data.message) || 'Could not save.').css('color', '#c62828').show();
            }
        }).fail(function () {
            $btns.prop('disabled', false);
            $status.text('Network error — please try again.').css('color', '#c62828').show();
        });
    });

    // ── Team News: coach submission form ─────────────────────────────────────

    var WAS_NEWS_MAX = 5;
    var WAS_NEWS_MAX_MB = 8;

    function wasNewsToggleBanner() {
        var teamId = $('#was-news-team').val();
        $('.was-news-consent-banner').hide()
            .filter('[data-team-id="' + teamId + '"]').show();
    }
    $(document).on('change', '#was-news-team', wasNewsToggleBanner);
    if ($('#was-news-team').length) wasNewsToggleBanner();

    // Subject dropdown swaps the title/description labels + placeholders
    var WAS_NEWS_TYPES = {
        match_report: {
            titleLabel:   'Match headline',
            titlePh:      "e.g. Hard-fought win in Sunday's cup tie",
            contentLabel: 'Match report',
            contentPh:    'How did the game go? Highlights, effort, sportsmanship — only include the score if it was a competitive league (U12+) or cup match.'
        },
        general_news: {
            titleLabel:   'Title',
            titlePh:      'e.g. New training kit has arrived',
            contentLabel: "What's the news?",
            contentPh:    'Tell parents and supporters what’s happening…'
        },
        shoutout: {
            titleLabel:   "Who or what's the shoutout for?",
            titlePh:      'e.g. A huge thank-you to our matchday volunteers',
            contentLabel: 'Your shoutout',
            contentPh:    'Celebrate an achievement or say a thank-you — without naming children.'
        }
    };

    $(document).on('change', '#was-news-type', function () {
        var t = WAS_NEWS_TYPES[$(this).val()] || WAS_NEWS_TYPES.general_news;
        $('#was-news-title-label').text(t.titleLabel);
        $('#was-news-title').attr('placeholder', t.titlePh);
        $('#was-news-content-label').text(t.contentLabel);
        $('#was-news-content').attr('placeholder', t.contentPh);
    });

    // Validate a multi-photo <input>; returns an error string or '' if OK.
    function wasNewsCheckFiles(input, existingCount) {
        var files = input.files || [];
        if (files.length + (existingCount || 0) > WAS_NEWS_MAX) {
            return 'A post can have at most ' + WAS_NEWS_MAX + ' photos.';
        }
        for (var i = 0; i < files.length; i++) {
            if (files[i].size > WAS_NEWS_MAX_MB * 1024 * 1024) {
                return '"' + files[i].name + '" is over ' + WAS_NEWS_MAX_MB + 'MB — please choose a smaller photo.';
            }
        }
        return '';
    }

    $(document).on('change', '#was-news-photos', function () {
        var $err  = $('#was-news-error').hide();
        var $prev = $('#was-news-preview').empty();
        var fail  = wasNewsCheckFiles(this, 0);
        if (fail) { $err.text(fail).show(); $(this).val(''); return; }
        for (var i = 0; i < this.files.length; i++) {
            $('<img>', { src: URL.createObjectURL(this.files[i]), 'class': 'was-news-preview-thumb', alt: '' }).appendTo($prev);
        }
    });

    $(document).on('submit', '#was-news-form', function (e) {
        e.preventDefault();
        var $btn = $('#was-news-submit-btn');
        var $err = $('#was-news-error').hide();
        var $ok  = $('#was-news-success').hide();

        var title   = $.trim($('#was-news-title').val());
        var content = $.trim($('#was-news-content').val());
        var input   = $('#was-news-photos')[0];

        if (!title)   { $err.text('Please add a title.').show(); return; }
        if (!content) { $err.text('Please add a short description.').show(); return; }
        var fail = input ? wasNewsCheckFiles(input, 0) : '';
        if (fail) { $err.text(fail).show(); return; }

        var fd = new FormData();
        fd.append('action',    'was_news_submit');
        fd.append('nonce',     wasAjax.nonce);
        fd.append('team_id',   $('#was-news-team').val());
        fd.append('news_type', $('#was-news-type').val() || 'general_news');
        fd.append('title',     title);
        fd.append('content',   content);
        if ($('#was-news-social').prop('checked')) fd.append('social_queue', '1');
        if (input) {
            for (var i = 0; i < input.files.length; i++) fd.append('photos[]', input.files[i]);
        }

        $btn.prop('disabled', true);
        $btn.find('.was-btn-label').hide();
        $btn.find('.was-btn-spinner').show();

        function restore() {
            $btn.prop('disabled', false);
            $btn.find('.was-btn-label').show();
            $btn.find('.was-btn-spinner').hide();
        }

        $.ajax({
            url: wasAjax.ajaxurl, type: 'POST', data: fd,
            processData: false, contentType: false,
            success: function (res) {
                restore();
                if (res.success) {
                    $ok.text(res.data.message).show();
                    $('#was-news-title').val('');
                    $('#was-news-content').val('');
                    $('#was-news-photos').val('');
                    $('#was-news-preview').empty();
                } else {
                    $err.text((res.data && res.data.message) || 'Something went wrong — please try again.').show();
                }
            },
            error: function () {
                restore();
                $err.text('A network error occurred — please try again.').show();
            }
        });
    });

    // ── Team News: review section (social media / treasurer / admin) ─────────

    // Toggle a photo for removal (applied on Save / Approve)
    $(document).on('click', '.was-news-photo-remove', function () {
        $(this).closest('.was-news-photo').toggleClass('is-removed');
    });

    $(document).on('change', '.was-news-add-photos', function () {
        var $card    = $(this).closest('.was-news-review-card');
        var $err     = $card.find('.was-news-review-error').hide();
        var existing = $card.find('.was-news-photo:not(.is-removed)').length;
        var fail     = wasNewsCheckFiles(this, existing);
        if (fail) { $err.text(fail).show(); $(this).val(''); }
    });

    function wasNewsReviewFd($card, action) {
        var fd = new FormData();
        fd.append('action',  action);
        fd.append('nonce',   wasAjax.nonce);
        fd.append('post_id', $card.data('post-id'));
        fd.append('title',   $card.find('.was-news-edit-title').val());
        fd.append('content', $card.find('.was-news-edit-content').val());
        $card.find('.was-news-photo.is-removed').each(function () {
            fd.append('remove_ids[]', $(this).data('attachment-id'));
        });
        var input = $card.find('.was-news-add-photos')[0];
        if (input && input.files) {
            for (var i = 0; i < input.files.length; i++) fd.append('photos[]', input.files[i]);
        }
        return fd;
    }

    function wasNewsReviewAction($btn, action) {
        var $card = $btn.closest('.was-news-review-card');
        var $err  = $card.find('.was-news-review-error').hide();
        $card.find('.was-btn').prop('disabled', true);
        $.ajax({
            url: wasAjax.ajaxurl, type: 'POST', data: wasNewsReviewFd($card, action),
            processData: false, contentType: false,
            success: function (res) {
                if (res.success) {
                    location.reload();
                } else {
                    $card.find('.was-btn').prop('disabled', false);
                    $err.text((res.data && res.data.message) || 'Something went wrong.').show();
                }
            },
            error: function () {
                $card.find('.was-btn').prop('disabled', false);
                $err.text('A network error occurred — please try again.').show();
            }
        });
    }

    $(document).on('click', '.was-news-save-btn',    function () { wasNewsReviewAction($(this), 'was_news_update'); });
    $(document).on('click', '.was-news-approve-btn', function () { wasNewsReviewAction($(this), 'was_news_approve'); });

    $(document).on('click', '.was-news-reject-btn', function () {
        $(this).closest('.was-news-review-card').find('.was-news-reject-panel').slideToggle(150);
    });

    $(document).on('click', '.was-news-reject-confirm', function () {
        var $card = $(this).closest('.was-news-review-card');
        var $err  = $card.find('.was-news-review-error').hide();
        $card.find('.was-btn').prop('disabled', true);
        $.post(wasAjax.ajaxurl, {
            action:  'was_news_reject',
            nonce:   wasAjax.nonce,
            post_id: $card.data('post-id'),
            reason:  $card.find('.was-news-reject-reason').val()
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                $card.find('.was-btn').prop('disabled', false);
                $err.text((res.data && res.data.message) || 'Something went wrong.').show();
            }
        }).fail(function () {
            $card.find('.was-btn').prop('disabled', false);
            $err.text('A network error occurred — please try again.').show();
        });
    });

    // ── Team News: Facebook tools ────────────────────────────────────────────

    $(document).on('click', '.was-news-copy-btn', function () {
        var $btn = $(this);
        var text = $btn.closest('.was-news-fb-tools').find('.was-news-fb-text').val();
        function done() {
            var label = $btn.text();
            $btn.text('Copied ✓');
            setTimeout(function () { $btn.text(label); }, 2000);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var $ta = $btn.closest('.was-news-fb-tools').find('.was-news-fb-text');
            $ta[0].removeAttribute('readonly');
            $ta[0].select();
            document.execCommand('copy');
            $ta[0].setAttribute('readonly', 'readonly');
            done();
        }
    });

    function wasNewsFbAction($btn, action, workingLabel) {
        var $card = $btn.closest('.was-news-fb-card');
        var $err  = $card.find('.was-news-fb-error').hide();
        var label = $btn.text();
        $btn.prop('disabled', true).text(workingLabel);
        $.post(wasAjax.ajaxurl, {
            action:  action,
            nonce:   wasAjax.nonce,
            post_id: $card.data('post-id')
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                $btn.prop('disabled', false).text(label);
                $err.text((res.data && res.data.message) || 'Something went wrong.').show();
            }
        }).fail(function () {
            $btn.prop('disabled', false).text(label);
            $err.text('A network error occurred — please try again.').show();
        });
    }

    $(document).on('click', '.was-news-fb-mark-btn', function () { wasNewsFbAction($(this), 'was_news_fb_mark', 'Saving…'); });
    $(document).on('click', '.was-news-fb-push-btn', function () { wasNewsFbAction($(this), 'was_news_fb_push', 'Posting…'); });

})(jQuery);
