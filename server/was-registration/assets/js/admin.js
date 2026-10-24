/* global wasAdmin, jQuery */
(function ($) {
    'use strict';

    // ── Role edit modal ────────────────────────────────────────────────────────

    var $modal    = $('#was-roles-modal');
    var $form     = $('#was-roles-form');
    var $teamsFld = $('#was-teams-fieldset');

    if ($modal.length) {

        // Show coach teams fieldset when coach is ticked
        $(document).on('change', '#was-role-coach', function () {
            $teamsFld.toggle($(this).prop('checked'));
        });

        // Open modal and populate
        $(document).on('click', '.was-edit-roles-btn', function () {
            var userId    = $(this).data('user-id');
            var name      = $(this).data('name');
            // data() may return array if JSON-like — normalise to string first
            var rolesRaw  = $(this).attr('data-roles') || '';
            var teamsRaw  = $(this).attr('data-teams') || '';
            var roles     = rolesRaw.split(',').map($.trim).filter(Boolean);
            var teams     = teamsRaw.split(',').map($.trim).filter(Boolean).map(Number);

            $('#was-modal-title').text('Edit roles — ' + name);
            $('#was-modal-user-id').val(userId);

            // Reset all checkboxes
            $form.find('input[type=checkbox]').prop('checked', false);

            // Tick current roles
            $.each(roles, function (i, role) {
                $('#was-role-' + role).prop('checked', true);
            });

            // Tick current teams
            $.each(teams, function (i, teamId) {
                $form.find('input.was-team-check[value="' + teamId + '"]').prop('checked', true);
            });

            $teamsFld.toggle(roles.indexOf('coach') !== -1);
            $modal.css('display', 'flex');
        });

        // Close modal
        $('#was-modal-cancel').on('click', function () { $modal.hide(); });
        $modal.on('click', function (e) { if ($(e.target).is($modal)) $modal.hide(); });
        $(document).on('keydown', function (e) { if (e.key === 'Escape') $modal.hide(); });
    }

    // ── Player list: select all / bulk action ──────────────────────────────────

    function updateCount() {
        var n = $('.was-player-cb:checked').length;
        $('#was-selected-count').text(n > 0 ? n + ' selected' : '');
    }

    $(document).on('change', '#was-check-all, #was-select-all', function () {
        $('.was-player-cb').prop('checked', $(this).prop('checked'));
        updateCount();
    });

    $(document).on('change', '.was-player-cb', updateCount);

    $(document).on('change', '#was-bulk-action', function () {
        $('#was-move-team').toggle($(this).val() === 'move_team');
    });

    $(document).on('click', '#was-bulk-apply', function (e) {
        var action = $('#was-bulk-action').val();
        var count  = $('.was-player-cb:checked').length;
        if (!action) { alert('Please select a bulk action.'); e.preventDefault(); return; }
        if (!count)  { alert('Please select at least one player.'); e.preventDefault(); return; }
        if (action === 'delete') {
            if (!confirm('Permanently delete ' + count + ' player record(s)? This cannot be undone and should only be used for GDPR data removal requests.')) {
                e.preventDefault();
            }
        } else if (action === 'move_team' && !$('#was-move-team').val()) {
            alert('Please select a team to move players to.');
            e.preventDefault();
        }
    });

    // ── Player edit: hard delete ───────────────────────────────────────────────

    $(document).on('click', '.was-hard-delete', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        if (confirm('PERMANENTLY delete all data for this player including payments, medical records, photo and consents? This cannot be undone.')) {
            $.post(wasAdmin.ajaxurl, {
                action:    'was_hard_delete_player',
                nonce:     wasAdmin.nonce,
                player_id: id
            }, function (res) {
                if (res.success) {
                    window.location.href = res.data.redirect;
                } else {
                    alert(res.data.message || 'Delete failed.');
                }
            });
        }
    });

    // ── Player edit: apply monthly supplement ─────────────────────────────────

    $(document).on('click', '#was-apply-supplement', function () {
        var $btn = $(this);
        var id   = $btn.data('id');
        var amt  = $.trim($('#was-supplement-amount').val()) || '0';
        var lbl  = $.trim($('#was-supplement-label').val());
        var $res = $('#was-supplement-result');

        var msg = parseFloat(amt) > 0
            ? 'Add a £' + parseFloat(amt).toFixed(2) + '/month supplement' + (lbl ? ' ("' + lbl + '")' : '') + ' and update the Direct Debit to the new total?'
            : 'Remove the supplement and update the Direct Debit back to the standard rate?';
        if (!confirm(msg)) return;

        $btn.prop('disabled', true).text('Applying...');
        $res.hide();
        $.post(wasAdmin.ajaxurl, {
            action:    'was_set_supplement',
            notify:    wasNotifyParent(),
            nonce:     wasAdmin.nonce,
            player_id: id,
            amount:    amt,
            label:     lbl
        }, function (res) {
            var m = (res.data && res.data.message) ? res.data.message : (res.success ? 'Done.' : 'Failed.');
            $res.removeClass('notice-success notice-error')
                .addClass(res.success ? 'notice-success' : 'notice-error')
                .text(m).show();
            $btn.prop('disabled', false).text('Apply');
        }).fail(function () {
            $res.removeClass('notice-success').addClass('notice-error').text('Network error.').show();
            $btn.prop('disabled', false).text('Apply');
        });
    });

    // ── Player edit: apply custom subscription amount ─────────────────────────

    $(document).on('click', '#was-apply-subs', function () {
        var $btn = $(this);
        var id   = $btn.data('id');
        var amt  = $.trim($('#was-custom-subs').val());
        var $res = $('#was-subs-result');

        var msg;
        if (amt === '') {
            msg = 'Remove the custom amount for this player? (Any existing Direct Debit is left unchanged.)';
        } else if (parseFloat(amt) === 0) {
            msg = 'Set this child to EXEMPT? This cancels their Direct Debit (no monthly charge).';
        } else {
            msg = 'Apply £' + amt + '/month? If they have an active Direct Debit it will be cancelled and recreated at this amount.';
        }
        if (!confirm(msg)) return;

        $btn.prop('disabled', true).text('Applying...');
        $res.hide();
        $.post(wasAdmin.ajaxurl, {
            action:    'was_set_custom_subs',
            notify:    wasNotifyParent(),
            nonce:     wasAdmin.nonce,
            player_id: id,
            amount:    amt
        }, function (res) {
            var m = (res.data && res.data.message) ? res.data.message : (res.success ? 'Done.' : 'Failed.');
            $res.removeClass('notice-success notice-error')
                .addClass(res.success ? 'notice-success' : 'notice-error')
                .empty().append($('<p>').css('margin', 0).text(m)).show();
            $btn.prop('disabled', false).text('Apply');
        }).fail(function () {
            $res.removeClass('notice-success').addClass('notice-error')
                .empty().append($('<p>').css('margin', 0).text('Network error — please try again.')).show();
            $btn.prop('disabled', false).text('Apply');
        });
    });

    // ── Player edit: "Email the parent" tick-box + pause payment emails ──────

    // 1 unless the admin un-ticked "Email the parent" on the player page (default yes).
    function wasNotifyParent() {
        var $cb = $('#was-notify-parent');
        return ($cb.length && !$cb.is(':checked')) ? 0 : 1;
    }

    $(document).on('change', '#was-pause-emails', function () {
        var $cb = $(this), paused = $cb.is(':checked');
        var $res = $('#was-pause-result').css('color', '#2271b1').text('Saving...');
        $cb.prop('disabled', true);
        $.post(wasAdmin.ajaxurl, {
            action: 'was_set_payment_emails_paused', nonce: wasAdmin.nonce,
            player_id: $cb.data('id'), paused: paused ? 1 : 0
        }, function (res) {
            $cb.prop('disabled', false);
            if (!res.success) { $cb.prop('checked', !paused); $res.css('color', '#b32d2e'); }
            $res.text((res.data && res.data.message) || (res.success ? 'Saved.' : 'Could not save.'));
        }).fail(function () {
            $cb.prop('disabled', false).prop('checked', !paused);
            $res.css('color', '#b32d2e').text('Network error, not saved. Please try again.');
        });
    });

    // ── Player edit: change payment date (preview, then confirm) ──────────────

    function paydayResult(ok, msg, lines) {
        var $res = $('#was-payday-result');
        $res.removeClass('notice-success notice-error')
            .addClass(ok ? 'notice-success' : 'notice-error')
            .empty().append($('<p>').css('margin', 0).text(msg));
        if (lines && lines.length) {
            var $ul = $('<ul>').css({ margin: '6px 0 0 18px', listStyle: 'disc' });
            $.each(lines, function (i, l) { $ul.append($('<li>').text(l)); });
            $res.append($ul);
        }
        $res.show();
    }

    $(document).on('click', '#was-preview-payday', function () {
        var $btn = $(this);
        $('#was-payday-preview, #was-payday-result').hide();
        $btn.prop('disabled', true).text('Checking GoCardless...');
        $.post(wasAdmin.ajaxurl, {
            action:    'was_preview_payment_day',
            nonce:     wasAdmin.nonce,
            player_id: $btn.data('id'),
            day:       $('#was-payment-day').val()
        }, function (res) {
            $btn.prop('disabled', false).text('Preview');
            if (!res.success) {
                paydayResult(false, (res.data && res.data.message) || 'Could not preview the change.');
                return;
            }
            $('#was-payday-summary').text(res.data.message);
            var $ul = $('#was-payday-lines').empty();
            $.each(res.data.lines || [], function (i, l) { $ul.append($('<li>').text(l)); });
            $('#was-confirm-payday').text(res.data.store_only ? 'Save' : 'Confirm change');
            $('#was-payday-preview').show();
        }).fail(function () {
            $btn.prop('disabled', false).text('Preview');
            paydayResult(false, 'Network error — please try again.');
        });
    });

    $(document).on('click', '#was-cancel-payday', function () {
        $('#was-payday-preview').hide();
    });

    $(document).on('change', '#was-payment-day', function () {
        $('#was-payday-preview').hide();
    });

    $(document).on('click', '#was-confirm-payday', function () {
        var $btn  = $(this);
        var label = $btn.text();
        $btn.prop('disabled', true).text('Updating GoCardless...');
        $.post(wasAdmin.ajaxurl, {
            action:    'was_set_payment_day',
            notify:    wasNotifyParent(),
            nonce:     wasAdmin.nonce,
            player_id: $btn.data('id'),
            day:       $('#was-payment-day').val()
        }, function (res) {
            $btn.prop('disabled', false).text(label);
            $('#was-payday-preview').hide();
            paydayResult(res.success, (res.data && res.data.message) || (res.success ? 'Done.' : 'Failed.'), res.data && res.data.lines);
        }).fail(function () {
            $btn.prop('disabled', false).text(label);
            paydayResult(false, 'Network error — check the player in GoCardless before trying again.');
        });
    });

    // ── Player edit: add / remove additional team ────────────────────────────

    $(document).on('click', '.was-add-team', function () {
        var $btn  = $(this);
        var pid   = $btn.data('player');
        var tid   = $('#was-add-team-select').val();
        var ptype = $('#was-add-team-type').val();
        var $res  = $('#was-team-result');
        if (!tid) { alert('Pick a team to add.'); return; }
        $btn.prop('disabled', true).text('Adding...');
        $.post(wasAdmin.ajaxurl, {
            action: 'was_add_player_team', nonce: wasAdmin.nonce,
            notify: wasNotifyParent(),
            player_id: pid, team_id: tid, participation_type: ptype
        }, function (res) {
            var m = (res.data && res.data.message) ? res.data.message : (res.success ? 'Done.' : 'Failed.');
            $res.removeClass('notice-success notice-error').addClass(res.success ? 'notice-success' : 'notice-error')
                .empty().append($('<p>').css('margin', 0).text(m)).show();
            if (res.success) { setTimeout(function () { location.reload(); }, 1600); }
            else { $btn.prop('disabled', false).text('Add team'); }
        }).fail(function () {
            $res.removeClass('notice-success').addClass('notice-error')
                .empty().append($('<p>').css('margin', 0).text('Network error — please try again.')).show();
            $btn.prop('disabled', false).text('Add team');
        });
    });

    $(document).on('click', '.was-remove-team', function () {
        var $btn = $(this);
        if (!confirm('Remove this child from the extra team and recompute their Direct Debit?')) return;
        var pid  = $btn.data('player');
        var mid  = $btn.data('membership');
        var $res = $('#was-team-result');
        $btn.prop('disabled', true).text('Removing...');
        $.post(wasAdmin.ajaxurl, {
            action: 'was_remove_player_team', nonce: wasAdmin.nonce,
            notify: wasNotifyParent(),
            player_id: pid, membership_id: mid
        }, function (res) {
            var m = (res.data && res.data.message) ? res.data.message : (res.success ? 'Done.' : 'Failed.');
            $res.removeClass('notice-success notice-error').addClass(res.success ? 'notice-success' : 'notice-error')
                .empty().append($('<p>').css('margin', 0).text(m)).show();
            if (res.success) { setTimeout(function () { location.reload(); }, 1600); }
            else { $btn.prop('disabled', false).text('Remove'); }
        }).fail(function () {
            $res.removeClass('notice-success').addClass('notice-error')
                .empty().append($('<p>').css('margin', 0).text('Network error — please try again.')).show();
            $btn.prop('disabled', false).text('Remove');
        });
    });

    // ── Teams: withdraw a team mid-season ─────────────────────────────────────

    $(document).on('click', '.was-withdraw-team', function () {
        var $btn = $(this);
        var id   = $btn.data('team');
        var name = $btn.data('name');
        if (!confirm('Withdraw "' + name + '" for the rest of the season?\n\nThis deactivates the team, cancels the Direct Debit for players whose only team this is, reduces it for players who also play elsewhere, and moves a remaining team up to primary where possible. Continue?')) return;
        $btn.prop('disabled', true).text('Withdrawing...');
        var $res = $('#was-withdraw-result');
        $.post(wasAdmin.ajaxurl, { action: 'was_withdraw_team', nonce: wasAdmin.nonce, team_id: id }, function (res) {
            var m = (res.data && res.data.message) ? res.data.message : (res.success ? 'Done.' : 'Failed.');
            $res.removeClass('notice-success notice-error').addClass(res.success ? 'notice-success' : 'notice-error')
                .empty().append($('<p>').css('margin', 0).text(m)).show();
            if (res.success) { setTimeout(function () { location.reload(); }, 2200); }
            else { $btn.prop('disabled', false).text('Withdraw'); }
        }).fail(function () {
            $res.removeClass('notice-success').addClass('notice-error')
                .empty().append($('<p>').css('margin', 0).text('Network error — please try again.')).show();
            $btn.prop('disabled', false).text('Withdraw');
        });
    });

    // ── Dashboard: manual GoCardless sync ─────────────────────────────────────

    $(document).on('click', '#was-manual-sync-btn', function () {
        var $btn = $(this);
        var $msg = $('#was-sync-msg');
        $btn.prop('disabled', true).text('Syncing...');
        $msg.text('');
        $.post(wasAdmin.ajaxurl, {
            action: 'was_run_sync',
            nonce:  wasAdmin.nonce,
        }, function (res) {
            if (res.success) {
                $msg.text(res.data.message);
            } else {
                $msg.css('color', '#c62828').text('Sync failed.');
            }
            $btn.prop('disabled', false).text('Run sync now');
        });
    });

})(jQuery);

// ── Logo media picker on settings page ───────────────────────────────────────
$(document).on('click', '#was-logo-picker', function (e) {
    e.preventDefault();
    var frame = wp.media({
        title:    'Select ASTRO Portal Logo',
        button:   { text: 'Use this image' },
        multiple: false,
        library:  { type: ['image'] },
    });
    frame.on('select', function () {
        var att = frame.state().get('selection').first().toJSON();
        $('#portal_logo_url').val(att.url);
        // Preview
        var $preview = $('#was-logo-preview');
        if ($preview.length) {
            $preview.attr('src', att.url).show();
        }
    });
    frame.open();
});
