<?php if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Adult Teams admin screen: one numbered slot per team (label + Full-Time link + page on site),
 * sync status, manual refresh, badge & crest settings, shortcode help.
 */
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permission denied.' );

$notice = '';
$error  = '';

if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['aat_nonce'] ) ) {
    check_admin_referer( 'aat_save', 'aat_nonce' );

    // Each action has its own submit button (no nested forms).
    if ( isset( $_POST['remove_slot'] ) ) {
        $n = (int) $_POST['remove_slot'];
        Allstars_Adult_Teams::remove_slot( $n );
        $notice = sprintf( 'Slot %d removed.', $n );

    } elseif ( isset( $_POST['save_slots'] ) ) {
        $rows = isset( $_POST['slots'] ) && is_array( $_POST['slots'] ) ? wp_unslash( $_POST['slots'] ) : array();
        foreach ( $rows as $n => $r ) {
            Allstars_Adult_Teams::update_slot( (int) $n, $r['label'] ?? '', $r['page_url'] ?? '' );
        }
        $notice = 'Team details saved.';

    } elseif ( isset( $_POST['refresh_now'] ) ) {
        $ok      = Allstars_Adult_Teams::sync_all();
        $all     = count( Allstars_Adult_Teams::get_slots() );
        $srv     = get_option( Allstars_Adult_Teams::OPT_SERVER, array() );
        $blocked = ! empty( $srv['blocked_at'] ) && time() - (int) $srv['blocked_at'] < MINUTE_IN_SECONDS;
        if ( $blocked && $ok < $all ) {
            $error = 'Full-Time\'s bot protection (Cloudflare) blocked the server, so nothing more was refreshed. The daily browser refresh keeps the data up to date — see the note above the table.';
        } elseif ( $ok === $all ) {
            $notice = sprintf( 'Refreshed %d team%s from Full-Time.', $ok, $ok === 1 ? '' : 's' );
        } else {
            $error = sprintf( 'Refreshed %d of %d team(s) — see the status column below.', $ok, $all );
        }

    } elseif ( isset( $_POST['add_slot'] ) ) {
        $slot = Allstars_Adult_Teams::slot_from_url(
            (int) ( $_POST['slot_no'] ?? 0 ),
            wp_unslash( $_POST['ft_url'] ?? '' ),
            wp_unslash( $_POST['ft_label'] ?? '' ),
            wp_unslash( $_POST['ft_page'] ?? '' )
        );
        if ( is_wp_error( $slot ) ) {
            $error = $slot->get_error_message();
        } else {
            $existing = Allstars_Adult_Teams::get_slots();
            if ( isset( $existing[ $slot['slot'] ] ) ) {
                $error = sprintf( 'Slot %d is already in use — remove it first or pick another number.', $slot['slot'] );
            } else {
                Allstars_Adult_Teams::save_slot( $slot );
                $data = Allstars_Adult_Teams::get_data();
                $okay = Allstars_Adult_Teams::sync_slot( $slot, $data );
                update_option( Allstars_Adult_Teams::OPT_DATA, $data, false );
                if ( $okay ) {
                    $notice = sprintf( 'Slot %d linked to %s on Full-Time and its fixtures fetched. Use [adult-team-info-%d] on a page.', $slot['slot'], $data[ $slot['slot'] ]['team_name'] ?? 'the team', $slot['slot'] );
                } else {
                    $error = 'Team saved, but the first fetch failed: ' . ( $data[ $slot['slot'] ]['error'] ?? 'unknown error' );
                }
            }
        }

    } elseif ( isset( $_POST['save_badges'] ) ) {
        Allstars_Adult_Teams::save_settings( array(
            'our_badge'      => wp_unslash( $_POST['our_badge'] ?? '' ),
            'fallback_crest' => wp_unslash( $_POST['fallback_crest'] ?? '' ),
        ) );
        $notice = 'Badge settings saved.';

    } elseif ( isset( $_POST['save_crests'] ) ) {
        $map = isset( $_POST['crests'] ) && is_array( $_POST['crests'] ) ? wp_unslash( $_POST['crests'] ) : array();
        Allstars_Adult_Teams::save_crest_overrides( $map );
        $notice = 'Opponent crests saved.';

    } elseif ( isset( $_POST['save_pitch_settings'] ) ) {
        Allstars_Pitches::save_settings( wp_unslash( $_POST['pitch_grounds'] ?? '' ), wp_unslash( $_POST['pitch_club_pages'] ?? '' ) );
        $notice = 'Pitch schedule settings saved.';

    } elseif ( isset( $_POST['pitch_rediscover'] ) ) {
        Allstars_Pitches::reset_discovery();
        $notice = 'The next browser refresh will re-read the club pages and every league\'s club filter.';
    }
}

$slots     = Allstars_Adult_Teams::get_slots();
$data      = Allstars_Adult_Teams::get_data();
$last_run  = (int) get_option( Allstars_Adult_Teams::OPT_LASTRUN, 0 );
$server    = get_option( Allstars_Adult_Teams::OPT_SERVER, array() );
$next_run  = wp_next_scheduled( Allstars_Adult_Teams::CRON_HOOK );
$free      = Allstars_Adult_Teams::next_free_slot();
$settings  = Allstars_Adult_Teams::settings();
$overrides = Allstars_Adult_Teams::crest_overrides();
$opponents = Allstars_Adult_Teams::all_opponents();

global $wpdb;
$pages = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type IN ('page','post') AND post_status = 'publish' AND (post_content LIKE '%[adult-team-info%' OR post_content LIKE '%[adult-team-widget%') ORDER BY ID ASC" );
?>
<style>
.aat-admin { max-width: 1150px; }
.aat-badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:.78rem; font-weight:600; }
.aat-badge--green { background:#e8f5e9; color:#2e7d32; }
.aat-badge--red   { background:#ffebee; color:#c62828; }
.aat-badge--yellow{ background:#fffde7; color:#f57f17; }
.aat-badge--grey  { background:#f5f5f5; color:#616161; }
.aat-admin code { font-size:.85em; }
.aat-admin .aat-prev { width:40px; height:40px; object-fit:contain; vertical-align:middle; background:#fafafa; border:1px solid #e0e0e0; border-radius:6px; margin-right:6px; }
.aat-admin .aat-prev--lg { width:72px; height:72px; }
.aat-admin .aat-pickrow { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.aat-admin .aat-pickrow input[type=url] { flex:1 1 320px; }
.aat-admin .aat-crest-table td { vertical-align:middle; }
.aat-admin h2.title { margin-top:32px; }
</style>
<div class="wrap aat-admin">
    <h1>Adult Teams <span style="font-size:.6em;color:#5a6473;font-weight:400">fixtures &amp; results via FA Full-Time</span></h1>

    <?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
    <?php if ( $error ) :  ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

    <p style="max-width:780px">
        Link each adult team to its page on <a href="https://fulltime.thefa.com" target="_blank" rel="noopener">FA Full-Time</a>.
        Fixtures, results and the league table are fetched <strong>once a day</strong> (06:00 UK time) and shown wherever the team's shortcode is placed.
        To find a team's page: open the league on Full-Time, click the team name in the table or a fixture, then copy the address bar
        (it contains <code>displayTeam.html?…&amp;teamID=…</code>).
    </p>

    <?php if ( ! empty( $server['blocked_at'] ) ) : ?>
    <div class="notice notice-warning inline" style="max-width:780px"><p>
        <strong>Server check blocked <?php echo esc_html( human_time_diff( (int) $server['blocked_at'] ) ); ?> ago.</strong>
        <?php echo esc_html( $server['message'] ?? '' ); ?>
        Fixtures, results and tables are refreshed instead by the daily <em>browser refresh</em> (a Claude scheduled task on a committee PC
        that opens Full-Time in a normal browser and posts the pages to this site) — rows updated that way say "via browser" below.
    </p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'aat_save', 'aat_nonce' ); ?>

        <h2 class="title">Linked teams</h2>
        <?php if ( empty( $slots ) ) : ?>
            <p><em>No teams linked yet.</em></p>
        <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:50px">Slot</th>
                    <th>Shortcodes</th>
                    <th>Name on site</th>
                    <th>Page on this site</th>
                    <th>On Full-Time</th>
                    <th>Cached</th>
                    <th>Updated</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $slots as $n => $s ) :
                $d      = $data[ $n ] ?? array();
                $has    = ! empty( $d['fetched_at'] );
                $err    = $d['error'] ?? '';
                $failed = ! $has || ( ! empty( $d['last_attempt'] ) && $d['last_attempt'] > $d['fetched_at'] );
            ?>
                <tr>
                    <td><strong><?php echo (int) $n; ?></strong></td>
                    <td style="white-space:nowrap"><code>[adult-team-info-<?php echo (int) $n; ?>]</code><br><code>[adult-team-widget-<?php echo (int) $n; ?>]</code></td>
                    <td><input type="text" name="slots[<?php echo (int) $n; ?>][label]" value="<?php echo esc_attr( $s['label'] ); ?>" style="width:160px"></td>
                    <td>
                        <input type="url" name="slots[<?php echo (int) $n; ?>][page_url]" value="<?php echo esc_attr( $s['page_url'] ?? '' ); ?>" placeholder="<?php echo esc_attr( home_url( '/first-team/' ) ); ?>" style="width:220px">
                        <p class="description" style="margin:2px 0 0">Where the widget's link goes.</p>
                    </td>
                    <td>
                        <?php if ( $has ) : ?>
                            <a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $d['team_name'] ); ?></a>
                            <div style="font-size:.82em;color:#5a6473"><?php echo esc_html( $d['league_name'] . ( $d['division_name'] ? ' — ' . $d['division_name'] : '' ) ); ?></div>
                        <?php else : ?>
                            <a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener">teamID <?php echo esc_html( $s['team_id'] ); ?></a>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;font-size:.85em">
                        <?php if ( $has ) : ?>
                            <?php echo count( $d['fixtures'] ); ?> fixtures<br><?php echo count( $d['results'] ); ?> results<br><?php echo count( $d['table'] ); ?>-team table
                        <?php else : ?>—<?php endif; ?>
                    </td>
                    <td style="font-size:.85em"><?php echo $has ? esc_html( human_time_diff( $d['fetched_at'] ) . ' ago' . ( ( $d['source'] ?? '' ) === 'browser' ? ' via browser' : '' ) ) : 'never'; ?></td>
                    <td style="max-width:220px">
                        <?php if ( $err ) : ?>
                            <span class="aat-badge aat-badge--<?php echo $failed ? 'red' : 'yellow'; ?>"><?php echo $failed ? 'Failed' : 'Partial'; ?></span>
                            <div style="font-size:.8em;color:#5a6473;margin-top:4px"><?php echo esc_html( $err ); ?></div>
                        <?php elseif ( $has ) : ?>
                            <span class="aat-badge aat-badge--green">OK</span>
                        <?php else : ?>
                            <span class="aat-badge aat-badge--grey">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="submit" class="button-link-delete" name="remove_slot" value="<?php echo (int) $n; ?>"
                                onclick="return confirm('Remove slot <?php echo (int) $n; ?> (<?php echo esc_js( $s['label'] ); ?>)? Any [adult-team-info-<?php echo (int) $n; ?>] shortcode on the site will stop showing this team.');">Remove</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:10px">
            <button type="submit" class="button button-primary" name="save_slots" value="1">Save team details</button>
            <button type="submit" class="button" name="refresh_now" value="1">Refresh from Full-Time now</button>
            <span style="color:#5a6473;margin-left:8px">
                <?php if ( $last_run ) : ?>Last automatic check: <?php echo esc_html( human_time_diff( $last_run ) ); ?> ago.<?php endif; ?>
                <?php if ( $next_run ) : ?>Next: <?php echo esc_html( wp_date( 'D j M, H:i', $next_run, new DateTimeZone( Allstars_Adult_Teams::TZ ) ) ); ?> (UK time).<?php endif; ?>
            </span>
        </p>
        <?php endif; ?>

        <h2 class="title">Link a team</h2>
        <?php if ( ! $free ) : ?>
            <p><em>All <?php echo (int) Allstars_Adult_Teams::MAX_SLOTS; ?> slots are in use.</em></p>
        <?php else : ?>
        <table class="form-table" style="max-width:780px">
            <tr>
                <th scope="row"><label for="slot_no">Slot number</label></th>
                <td>
                    <select id="slot_no" name="slot_no">
                        <?php for ( $i = 1; $i <= Allstars_Adult_Teams::MAX_SLOTS; $i++ ) : if ( isset( $slots[ $i ] ) ) continue; ?>
                        <option value="<?php echo $i; ?>"<?php selected( $i, $free ); ?>><?php echo $i; ?> &mdash; [adult-team-info-<?php echo $i; ?>]</option>
                        <?php endfor; ?>
                    </select>
                    <p class="description">e.g. 1 for the men's first team, 2 for the women's team.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ft_label">Name on the site</label></th>
                <td><input type="text" id="ft_label" name="ft_label" class="regular-text" placeholder="e.g. Men's First Team"></td>
            </tr>
            <tr>
                <th scope="row"><label for="ft_url">Full-Time team page link</label></th>
                <td>
                    <input type="url" id="ft_url" name="ft_url" class="large-text" placeholder="https://fulltime.thefa.com/displayTeam.html?divisionseason=…&teamID=…">
                    <p class="description">League, season and division are worked out automatically from the team page.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ft_page">Team page on this site (optional)</label></th>
                <td><input type="url" id="ft_page" name="ft_page" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/first-team/' ) ); ?>"></td>
            </tr>
        </table>
        <p><button type="submit" class="button button-primary" name="add_slot" value="1">Link team &amp; fetch fixtures</button></p>
        <?php endif; ?>

        <h2 class="title">Badges</h2>
        <p style="max-width:780px">Our own teams always show the <strong>club badge</strong> rather than whatever Full-Time holds. Opponents use the crest Full-Time publishes; if there isn't one, the <strong>fallback crest</strong> is shown (or set an override per club below). Leave a field blank to use the built-in image.</p>
        <table class="form-table" style="max-width:780px">
            <tr>
                <th scope="row">Our badge</th>
                <td>
                    <div class="aat-pickrow">
                        <img class="aat-prev aat-prev--lg" src="<?php echo esc_url( Allstars_Adult_Teams::our_badge_url() ); ?>" alt="" data-preview="our_badge">
                        <input type="url" id="our_badge" name="our_badge" value="<?php echo esc_attr( $settings['our_badge'] ); ?>" placeholder="built-in: square-cropped club badge">
                        <button type="button" class="button aat-pick" data-target="our_badge">Choose image</button>
                    </div>
                    <p class="description">Use a square image — it's shown at up to 64px.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Fallback crest</th>
                <td>
                    <div class="aat-pickrow">
                        <img class="aat-prev aat-prev--lg" src="<?php echo esc_url( Allstars_Adult_Teams::fallback_crest_url() ); ?>" alt="" data-preview="fallback_crest">
                        <input type="url" id="fallback_crest" name="fallback_crest" value="<?php echo esc_attr( $settings['fallback_crest'] ); ?>" placeholder="built-in: neutral shield">
                        <button type="button" class="button aat-pick" data-target="fallback_crest">Choose image</button>
                    </div>
                </td>
            </tr>
        </table>
        <p><button type="submit" class="button button-primary" name="save_badges" value="1">Save badge settings</button></p>

        <h2 class="title">Opponent crests</h2>
        <?php if ( empty( $opponents ) ) : ?>
            <p><em>No opponents cached yet — they appear here after the first sync.</em></p>
        <?php else : ?>
        <p style="max-width:780px">Every club seen in your teams' leagues. "From Full-Time" is what the league has uploaded; add an override to replace it or to fill a gap (upload the image to the Media Library, then choose it). Blank = no override.</p>
        <table class="widefat striped aat-crest-table">
            <thead><tr><th>Club</th><th style="width:140px">From Full-Time</th><th>Override</th></tr></thead>
            <tbody>
            <?php foreach ( $opponents as $norm => $display ) :
                $auto = Allstars_Adult_Teams::auto_crest( $norm );
                $ov   = $overrides[ $norm ] ?? '';
                $fid  = 'crest_' . md5( $norm );
            ?>
                <tr>
                    <td><?php echo esc_html( $display ); ?></td>
                    <td>
                        <?php if ( $auto ) : ?><img class="aat-prev" src="<?php echo esc_url( $auto ); ?>" alt=""> <span style="color:#2e7d32;font-size:.85em">yes</span>
                        <?php else : ?><img class="aat-prev" src="<?php echo esc_url( Allstars_Adult_Teams::fallback_crest_url() ); ?>" alt="" style="opacity:.6"> <span style="color:#5a6473;font-size:.85em">none — fallback</span><?php endif; ?>
                    </td>
                    <td>
                        <div class="aat-pickrow">
                            <img class="aat-prev" src="<?php echo esc_url( $ov ?: Allstars_Adult_Teams::fallback_crest_url() ); ?>" alt="" data-preview="<?php echo esc_attr( $fid ); ?>"<?php echo $ov ? '' : ' style="opacity:.35"'; ?>>
                            <input type="url" id="<?php echo esc_attr( $fid ); ?>" name="crests[<?php echo esc_attr( $norm ); ?>]" value="<?php echo esc_attr( $ov ); ?>" placeholder="https://…/crest.png">
                            <button type="button" class="button aat-pick" data-target="<?php echo esc_attr( $fid ); ?>">Choose</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p><button type="submit" class="button button-primary" name="save_crests" value="1">Save opponent crests</button></p>
        <?php endif; ?>

        <?php
        $p_cfg   = Allstars_Pitches::cfg();
        $p_data  = Allstars_Pitches::data();
        $p_set   = Allstars_Pitches::settings();
        $p_pages = Allstars_Pitches::pages();
        $p_run   = $p_data['last_run'];
        ?>
        <h2 class="title">Pitch schedule (groundsman)</h2>
        <p style="max-width:780px">
            <code>[pitch-schedule]</code> lists every home game on club pitches this weekend and next, by ground and pitch, for <strong>all</strong> club sides.
            The browser refresh reads the club's pages on Full-Time to find every league we play in, then each league's fixtures for our club.
            <?php if ( $p_pages ) : ?>
                Shown on:
                <?php $i = 0; foreach ( $p_pages as $pid => $path ) : ?><?php echo $i++ ? ', ' : ''; ?><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $pid ) ); ?></a><?php echo get_post_field( 'post_password', $pid ) !== '' ? ' (password protected)' : ''; ?><?php endforeach; ?>.
            <?php else : ?>
                No page uses the shortcode yet.
            <?php endif; ?>
        </p>
        <p style="color:#5a6473">
            <?php if ( ! empty( $p_run['at'] ) ) : ?>
                Last browser refresh: <?php echo esc_html( human_time_diff( (int) $p_run['at'] ) ); ?> ago — <?php echo (int) $p_run['fetched']; ?> page(s) fetched<?php echo ! empty( $p_run['failed'] ) ? ', ' . (int) $p_run['failed'] . ' failed' : ''; ?><?php echo ! empty( $p_run['blocked'] ) ? ', <strong>stopped by Cloudflare</strong>' : ''; ?>.
            <?php else : ?>
                Not refreshed yet.
            <?php endif; ?>
            <?php if ( ! empty( $p_cfg['club_pages_at'] ) ) : ?>Club pages last read <?php echo esc_html( human_time_diff( (int) $p_cfg['club_pages_at'] ) ); ?> ago.<?php endif; ?>
        </p>
        <?php if ( $p_cfg['leagues'] ) : ?>
        <table class="widefat striped" style="max-width:1000px">
            <thead><tr><th>League</th><th>Our club ID</th><th>Fixtures (next 30 days)</th><th>Updated</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ( $p_cfg['leagues'] as $lid => $l ) : ?>
                <?php if ( empty( $l['clubs'] ) ) : ?>
                <tr>
                    <td><?php echo esc_html( $l['name'] ?: 'League ' . $lid ); ?></td>
                    <td>—</td><td>—</td>
                    <td style="font-size:.85em"><?php echo ! empty( $l['discovered_at'] ) ? 'checked ' . esc_html( human_time_diff( (int) $l['discovered_at'] ) ) . ' ago' : 'not checked yet'; ?></td>
                    <td><span class="aat-badge aat-badge--grey"><?php echo ! empty( $l['discovered_at'] ) ? 'No side this season' : 'Pending'; ?></span></td>
                </tr>
                <?php else : foreach ( $l['clubs'] as $cid ) : $li = $p_data['lists'][ $lid . ':' . $cid ] ?? array(); ?>
                <tr>
                    <td><?php echo esc_html( $l['name'] ?: 'League ' . $lid ); ?></td>
                    <td><code><?php echo esc_html( $cid ); ?></code></td>
                    <td><?php echo isset( $li['rows'] ) ? count( $li['rows'] ) : '—'; ?></td>
                    <td style="font-size:.85em"><?php echo ! empty( $li['fetched_at'] ) ? esc_html( human_time_diff( (int) $li['fetched_at'] ) . ' ago' ) : 'never'; ?></td>
                    <td style="max-width:260px">
                        <?php if ( ! empty( $li['error'] ) ) : ?><span class="aat-badge aat-badge--red">Failed</span><div style="font-size:.8em;color:#5a6473;margin-top:4px"><?php echo esc_html( $li['error'] ); ?></div>
                        <?php elseif ( ! empty( $li['fetched_at'] ) ) : ?><span class="aat-badge aat-badge--green">OK</span>
                        <?php else : ?><span class="aat-badge aat-badge--grey">Pending</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <table class="form-table" style="max-width:900px">
            <tr>
                <th scope="row"><label for="pitch_grounds">Our grounds</label></th>
                <td>
                    <textarea id="pitch_grounds" name="pitch_grounds" rows="6" class="large-text code"><?php echo esc_textarea( $p_set['grounds'] ); ?></textarea>
                    <p class="description">One ground per line: the name to show, then any other spellings Full-Time uses, separated by <code>|</code>. Home games whose venue matches none of these appear under "Check venue".</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="pitch_club_pages">Club pages on Full-Time</label></th>
                <td>
                    <textarea id="pitch_club_pages" name="pitch_club_pages" rows="3" class="large-text code"><?php echo esc_textarea( $p_set['club_pages'] ); ?></textarea>
                    <p class="description">Full-Time's list of every team under each club name (search Full-Time for "Wombourne", then open the club). One link per line.</p>
                </td>
            </tr>
        </table>
        <p>
            <button type="submit" class="button button-primary" name="save_pitch_settings" value="1">Save pitch schedule settings</button>
            <button type="submit" class="button" name="pitch_rediscover" value="1">Re-check leagues on the next refresh</button>
        </p>
    </form>

    <h2 class="title">Showing a team on the site</h2>
    <p>
        <?php if ( $pages ) : ?>
            Shortcodes are currently on:
            <?php foreach ( $pages as $i => $p ) : ?><?php echo $i ? ', ' : ''; ?><a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" target="_blank"><?php echo esc_html( $p->post_title ); ?></a><?php endforeach; ?>.
        <?php else : ?>
            No page uses a shortcode yet.
        <?php endif; ?>
    </p>
    <table class="widefat" style="max-width:780px">
        <tbody>
            <tr><td><code>[adult-team-info-1]</code></td><td>Full section for slot 1 — up next, up later, recent results and league table.</td></tr>
            <tr><td><code>[adult-team-info-1 show="next,results"]</code></td><td>Pick sections: <code>next</code>, <code>fixtures</code>, <code>results</code>, <code>table</code>. <code>limit="10"</code> sets how many rows.</td></tr>
            <tr><td><code>[adult-team-widget-1]</code></td><td><strong>Front-page card</strong> for slot 1: up next, latest result, league position, link to the team page. Drop one per team into a front-page text/HTML element or column; they sit side by side in a row.</td></tr>
            <tr><td><code>[adult-team-widget-1 title="Men's 1st XI"]</code></td><td>Override the card's heading (<code>link="…"</code> overrides where it points).</td></tr>
            <tr><td>Appearance &rarr; Widgets</td><td>The same card is available as the <em>"Adult Team — Up next &amp; latest"</em> widget for sidebars and footers.</td></tr>
            <tr><td><code>[adult-team-info]</code> / <code>[adult-team-widget]</code></td><td>Every linked team, one after another.</td></tr>
        </tbody>
    </table>
</div>
