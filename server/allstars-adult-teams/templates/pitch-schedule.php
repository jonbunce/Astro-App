<?php if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * [pitch-schedule] — home games on club pitches: a tab per weekend, then day → ground → pitch.
 * $vm comes from Allstars_Pitches::view_model(). assets/pitch-schedule.js runs the tabs and the
 * "Hide 3G pitches" tickbox; without JavaScript both weekends simply show one after the other.
 */
$updated = $vm['updated'] ? Allstars_Adult_Teams::fmt_date( $vm['updated'], 'D j M, H:i' ) : '';
$uid     = 'aat-ps-' . wp_unique_id();
?>
<div class="aat aat-pitches" id="<?php echo esc_attr( $uid ); ?>">
    <p class="aat-pitches__intro">
        Home games on club pitches, from FA Full-Time.
        <?php if ( $updated ) : ?>Updated <strong><?php echo esc_html( $updated ); ?></strong>.<?php endif; ?>
    </p>

    <?php if ( ! $vm['has_data'] ) : ?>
        <p class="aat-empty">No fixtures have been fetched yet. They arrive with the next Full-Time refresh.</p>
    <?php else : ?>

    <?php if ( $vm['stale'] ) : ?>
        <div class="aat-pitches__warn">These fixtures haven't been refreshed for <?php echo esc_html( human_time_diff( $vm['updated'] ) ); ?>, so late changes may be missing. Check Full-Time before marking out.</div>
    <?php elseif ( $vm['behind'] ) : ?>
        <div class="aat-pitches__warn">Not refreshed in the last 3 days: <?php echo esc_html( implode( ', ', $vm['behind'] ) ); ?>.</div>
    <?php endif; ?>

    <div class="aat-toolbar">
        <div class="aat-tabs" role="tablist" aria-label="Weekend">
            <?php $first = true; foreach ( $vm['tabs'] as $key => $tab ) : ?>
            <button type="button" class="aat-tab" role="tab" id="<?php echo esc_attr( $uid . '-tab-' . $key ); ?>" aria-controls="<?php echo esc_attr( $uid . '-panel-' . $key ); ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>"<?php echo $first ? '' : ' tabindex="-1"'; ?>>
                <span class="aat-tab__label"><?php echo esc_html( $tab['label'] ); ?></span>
                <span class="aat-tab__dates"><?php echo esc_html( $tab['dates'] ); ?></span>
                <span class="aat-tab__count" aria-label="home games"><?php echo (int) $tab['count']; ?></span>
            </button>
            <?php $first = false; endforeach; ?>
        </div>
        <label class="aat-filter-3g"><input type="checkbox"> Hide 3G pitches</label>
    </div>

    <?php foreach ( $vm['tabs'] as $key => $tab ) : ?>
    <div class="aat-panel" role="tabpanel" id="<?php echo esc_attr( $uid . '-panel-' . $key ); ?>" aria-labelledby="<?php echo esc_attr( $uid . '-tab-' . $key ); ?>">
        <h2 class="aat-panel__title"><?php echo esc_html( $tab['label'] . ' · ' . $tab['dates'] ); ?></h2>

        <?php if ( ! $tab['days'] ) : ?>
            <p class="aat-none">Nothing on club pitches on Full-Time.</p>
        <?php endif; ?>

        <?php foreach ( $tab['days'] as $day ) : ?>
        <section class="aat-day">
            <header class="aat-day__head">
                <?php if ( $day['kicker'] === 'Midweek' ) : ?><span class="aat-day__kicker">Midweek</span><?php endif; ?>
                <h3 class="aat-day__title"><?php echo esc_html( $day['label'] ); ?></h3>
                <span class="aat-day__count"><?php echo $day['count'] ? esc_html( $day['count'] . ' home game' . ( $day['count'] === 1 ? '' : 's' ) ) : 'No home games'; ?></span>
            </header>

            <?php if ( ! $day['count'] ) : ?>
                <p class="aat-none">Nothing on club pitches on Full-Time.</p>
            <?php else : ?>
                <p class="aat-none aat-day__filtered" hidden>Every home game today is on 3G.</p>
            <?php endif; ?>

            <?php foreach ( $day['grounds'] as $gr ) : ?>
            <div class="aat-ground">
                <h4 class="aat-ground__name"><?php echo esc_html( $gr['name'] ); ?> <span><?php echo (int) $gr['count']; ?></span></h4>
                <?php foreach ( $gr['pitches'] as $p ) : ?>
                <div class="aat-pitch"<?php echo $p['g3'] ? ' data-3g' : ''; ?>>
                    <div class="aat-pitch__label" title="<?php echo esc_attr( $p['raw'] ); ?>"><?php echo esc_html( $p['label'] !== '' ? $p['label'] : 'Pitch not given' ); ?></div>
                    <ul class="aat-pitch__games">
                        <?php foreach ( $p['games'] as $g ) : ?>
                        <li class="aat-game<?php echo $g['off'] ? ' is-off' : ''; ?><?php echo $g['clash'] ? ' is-clash' : ''; ?>"<?php echo $g['g3'] ? ' data-3g' : ''; ?>>
                            <span class="aat-game__time"><?php echo esc_html( $g['time'] ?: 'TBC' ); ?></span>
                            <span class="aat-game__match">
                                <?php if ( $g['url'] ) : ?><a href="<?php echo esc_url( $g['url'] ); ?>" target="_blank" rel="noopener"><?php endif; ?>
                                <strong><?php echo esc_html( $g['ours'] ); ?></strong> v <?php echo esc_html( $g['opp'] ); ?>
                                <?php if ( $g['url'] ) : ?></a><?php endif; ?>
                                <?php if ( $g['comp'] ) : ?><span class="aat-game__comp"><?php echo esc_html( $g['comp'] ); ?></span><?php endif; ?>
                            </span>
                            <?php if ( $g['status'] ) : ?><span class="aat-chip aat-chip--off"><?php echo esc_html( $g['status'] ); ?></span><?php endif; ?>
                            <?php if ( $g['clash'] ) : ?><span class="aat-chip aat-chip--clash">Same pitch &amp; time</span><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <?php if ( $day['check'] ) : ?>
            <div class="aat-ground aat-ground--check">
                <h4 class="aat-ground__name">Check venue <span><?php echo count( $day['check'] ); ?></span></h4>
                <p class="aat-ground__note">Listed as home games, but the venue on Full-Time isn't one of our grounds.</p>
                <ul class="aat-pitch__games">
                    <?php foreach ( $day['check'] as $g ) : ?>
                    <li class="aat-game<?php echo $g['off'] ? ' is-off' : ''; ?>"<?php echo $g['g3'] ? ' data-3g' : ''; ?>>
                        <span class="aat-game__time"><?php echo esc_html( $g['time'] ?: 'TBC' ); ?></span>
                        <span class="aat-game__match">
                            <?php if ( $g['url'] ) : ?><a href="<?php echo esc_url( $g['url'] ); ?>" target="_blank" rel="noopener"><?php endif; ?>
                            <strong><?php echo esc_html( $g['ours'] ); ?></strong> v <?php echo esc_html( $g['opp'] ); ?>
                            <?php if ( $g['url'] ) : ?></a><?php endif; ?>
                            <span class="aat-game__comp">Venue: <?php echo esc_html( $g['venue'] !== '' ? $g['venue'] : 'not given' ); ?></span>
                        </span>
                        <?php if ( $g['status'] ) : ?><span class="aat-chip aat-chip--off"><?php echo esc_html( $g['status'] ); ?></span><?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <p class="aat-foot">
        Friendlies, training and anything not yet on Full-Time won't appear here.
        <?php if ( $vm['away'] ) : ?><?php echo (int) $vm['away']; ?> away game<?php echo $vm['away'] === 1 ? ' isn\'t' : 's aren\'t'; ?> shown.<?php endif; ?>
        Tap a game to open it on <a href="https://fulltime.thefa.com" target="_blank" rel="noopener">FA Full-Time</a>.
    </p>
    <?php endif; ?>
</div>
