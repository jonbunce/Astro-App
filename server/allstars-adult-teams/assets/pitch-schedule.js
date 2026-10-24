/* Allstars Adult Teams — [pitch-schedule]: weekend tabs and the "Hide 3G pitches" tickbox.
 * Without this script both weekends show one after the other and nothing is filtered. */
(function () {
    var STORE = 'aatPitchesHide3G';   // remembered per device, so the groundsman sets it once

    function plural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }
    function each(list, fn) { Array.prototype.forEach.call(list, fn); }

    function setup(root) {
        var tabs   = root.querySelectorAll('.aat-tab');
        var panels = root.querySelectorAll('.aat-panel');
        var box    = root.querySelector('.aat-filter-3g input');
        if (!tabs.length || tabs.length !== panels.length) return;

        function select(i) {
            each(tabs, function (t, j) {
                var on = i === j;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                panels[j].hidden = !on;
            });
        }
        each(tabs, function (t, i) {
            t.addEventListener('click', function () { select(i); });
            t.addEventListener('keydown', function (e) {
                var step = e.key === 'ArrowRight' ? 1 : (e.key === 'ArrowLeft' ? -1 : 0);
                if (!step) return;
                var n = (i + step + tabs.length) % tabs.length;
                select(n);
                tabs[n].focus();
                e.preventDefault();
            });
        });

        // Hide 3G games, then recount every badge so the numbers always match what's on screen.
        function apply() {
            var hide = !!(box && box.checked);
            each(root.querySelectorAll('.aat-pitch[data-3g], .aat-game[data-3g]'), function (el) { el.hidden = hide; });
            each(root.querySelectorAll('.aat-ground'), function (g) {
                var n = g.querySelectorAll('.aat-game:not([hidden])').length;
                var badge = g.querySelector('.aat-ground__name span');
                if (badge) badge.textContent = n;
                g.hidden = n === 0;
                // The divider sits above every pitch but the first one still showing.
                var shown = g.querySelectorAll('.aat-pitch:not([hidden])');
                each(g.querySelectorAll('.aat-pitch'), function (p) { p.classList.toggle('is-first', p === shown[0]); });
            });
            each(root.querySelectorAll('.aat-day'), function (d) {
                var all = d.querySelectorAll('.aat-game').length;
                var n   = d.querySelectorAll('.aat-game:not([hidden])').length;
                var count = d.querySelector('.aat-day__count');
                if (count) count.textContent = (n ? plural(n, 'home game') : 'No home games') + (all > n ? ' · ' + (all - n) + ' on 3G hidden' : '');
                var empty = d.querySelector('.aat-day__filtered');
                if (empty) empty.hidden = !(all && !n);
            });
            each(panels, function (p, j) {
                var badge = tabs[j].querySelector('.aat-tab__count');
                if (badge) badge.textContent = p.querySelectorAll('.aat-game:not([hidden])').length;
            });
            try { localStorage.setItem(STORE, hide ? '1' : '0'); } catch (e) {}
        }
        if (box) {
            try { box.checked = localStorage.getItem(STORE) === '1'; } catch (e) {}
            box.addEventListener('change', apply);
            apply();
        }

        root.classList.add('is-js');
        select(0);
    }

    function init() { each(document.querySelectorAll('.aat-pitches'), setup); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
