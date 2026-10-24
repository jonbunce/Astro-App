<?php if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * [adult-team-info-N] — fixtures / results / league table, rendered from the cached
 * Full-Time data. Variables: $blocks, $show, $limit, $now (see Allstars_Adult_Teams::render).
 */
$crest = function( $name, $d, $auto = '' ) {
    return '<img class="aat-crest" src="' . esc_url( Allstars_Adult_Teams::crest_for( $name, $d, $auto ) ) . '" alt="" loading="lazy">';
};
?>
<div class="aat">

<?php if ( empty( $blocks ) ) : ?>
    <p class="aat-empty">No adult teams have been linked to FA Full-Time yet.</p>
<?php endif; ?>

<?php foreach ( $blocks as $b ) :
    $s    = $b['slot'];
    $d    = $b['data'];
    $name = $d ? $d['team_name'] : $s['label'];
?>
<section class="aat-team" id="adult-team-<?php echo (int) $s['slot']; ?>">

    <header class="aat-head">
        <img class="aat-head__logo" src="<?php echo esc_url( Allstars_Adult_Teams::our_badge_url() ); ?>" alt="">
        <div class="aat-head__text">
            <h2 class="aat-head__title"><?php echo esc_html( $s['label'] ); ?></h2>
            <?php if ( $d ) : ?>
            <div class="aat-head__sub">
                <?php echo esc_html( $d['league_name'] ); ?><?php if ( $d['division_name'] ) : ?> &middot; <?php echo esc_html( $d['division_name'] ); ?><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ( $b['our_row'] && $b['our_row']['p'] > 0 ) : $r = $b['our_row']; // position is meaningless until a game has been played ?>
        <div class="aat-standing">
            <span class="aat-standing__pos"><?php echo esc_html( Allstars_Adult_Teams::ordinal( $r['pos'] ) ); ?></span>
            <span class="aat-standing__meta">
                P<?php echo (int) $r['p']; ?> &nbsp;W<?php echo (int) $r['w']; ?> &nbsp;D<?php echo (int) $r['d']; ?> &nbsp;L<?php echo (int) $r['l']; ?>
                <strong><?php echo (int) $r['pts']; ?> pts</strong>
            </span>
            <?php if ( $b['form'] ) : ?>
            <span class="aat-form" title="Last <?php echo count( $b['form'] ); ?> results">
                <?php foreach ( $b['form'] as $f ) : ?><i class="aat-form__dot aat-form__dot--<?php echo esc_attr( $f ); ?>"><?php echo esc_html( $f ); ?></i><?php endforeach; ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>

    <?php if ( ! $d ) : ?>
        <p class="aat-empty">Fixtures for <?php echo esc_html( $s['label'] ); ?> are being fetched from FA Full-Time &mdash; check back in a few minutes.</p>
    <?php else : ?>

    <?php if ( in_array( 'next', $show, true ) && $b['next'] ) :
        $f = $b['next'];
        list( $side ) = Allstars_Adult_Teams::perspective( $f, $name );
        $is_today = Allstars_Adult_Teams::fmt_date( $f['ts'], 'Y-m-d' ) === Allstars_Adult_Teams::fmt_date( $now, 'Y-m-d' );
    ?>
    <div class="aat-next">
        <div class="aat-next__label">Up next<?php echo $is_today ? ' &middot; Today' : ''; ?></div>
        <div class="aat-next__when">
            <?php echo esc_html( Allstars_Adult_Teams::fmt_date( $f['ts'], 'l j F' ) ); ?>
            <?php if ( $f['time'] ) : ?><span class="aat-next__ko"><?php echo esc_html( $f['time'] ); ?> KO</span><?php endif; ?>
        </div>
        <div class="aat-next__teams">
            <div class="aat-next__team">
                <?php echo $crest( $f['home'], $d, $f['home_logo'] ); ?>
                <span class="aat-next__name"><?php echo esc_html( $f['home'] ); ?></span>
            </div>
            <div class="aat-next__v">v</div>
            <div class="aat-next__team">
                <?php echo $crest( $f['away'], $d, $f['away_logo'] ); ?>
                <span class="aat-next__name"><?php echo esc_html( $f['away'] ); ?></span>
            </div>
        </div>
        <div class="aat-next__meta">
            <span class="aat-chip aat-chip--<?php echo $side; ?>"><?php echo $side === 'home' ? 'Home' : 'Away'; ?></span>
            <?php if ( $f['competition'] ) : ?><span class="aat-next__comp"><?php echo esc_html( $f['competition'] ); ?></span><?php endif; ?>
            <?php if ( $f['venue'] ) : ?>
            <span class="aat-next__venue">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?php echo esc_html( ucwords( strtolower( $f['venue'] ) ) ); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="aat-cols">

    <?php if ( in_array( 'fixtures', $show, true ) ) : ?>
    <div class="aat-block">
        <h3 class="aat-block__title">Up later</h3>
        <?php if ( $b['upcoming'] ) : ?>
        <ul class="aat-list">
            <?php foreach ( $b['upcoming'] as $f ) :
                list( $side ) = Allstars_Adult_Teams::perspective( $f, $name );
                $opp      = $side === 'home' ? $f['away'] : $f['home'];
                $opp_logo = $side === 'home' ? $f['away_logo'] : $f['home_logo'];
            ?>
            <li class="aat-row<?php echo $f['status'] ? ' is-off' : ''; ?>">
                <div class="aat-row__date">
                    <span class="aat-row__day"><?php echo esc_html( Allstars_Adult_Teams::fmt_date( $f['ts'], 'D j M' ) ); ?></span>
                    <?php if ( $f['time'] ) : ?><span class="aat-row__time"><?php echo esc_html( $f['time'] ); ?></span><?php endif; ?>
                </div>
                <div class="aat-row__main">
                    <div class="aat-row__opp"><?php echo $crest( $opp, $d, $opp_logo ); ?> <span><?php echo esc_html( $opp ); ?></span></div>
                    <div class="aat-row__sub">
                        <?php if ( $f['competition'] ) : ?><span><?php echo esc_html( $f['competition'] ); ?></span><?php endif; ?>
                        <?php if ( $f['venue'] ) : ?><span class="aat-row__venue"><?php echo esc_html( ucwords( strtolower( $f['venue'] ) ) ); ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="aat-row__end">
                    <?php if ( $f['status'] ) : ?>
                        <span class="aat-chip aat-chip--off"><?php echo esc_html( Allstars_Adult_Teams::walkover_side( $f ) ? 'Walkover' : $f['status'] ); ?></span>
                    <?php else : ?>
                        <span class="aat-chip aat-chip--<?php echo $side; ?>"><?php echo $side === 'home' ? 'H' : 'A'; ?></span>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else : ?>
        <p class="aat-none">No further fixtures published yet.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ( in_array( 'results', $show, true ) ) : ?>
    <div class="aat-block">
        <h3 class="aat-block__title">Recent results</h3>
        <?php if ( $b['results'] ) : ?>
        <ul class="aat-list">
            <?php foreach ( $b['results'] as $f ) :
                list( $side, $res ) = Allstars_Adult_Teams::perspective( $f, $name );
                $opp      = $side === 'home' ? $f['away'] : $f['home'];
                $opp_logo = $side === 'home' ? $f['away_logo'] : $f['home_logo'];
                $us       = $side === 'home' ? $f['hs'] : $f['as'];
                $them     = $side === 'home' ? $f['as'] : $f['hs'];
                $pens     = Allstars_Adult_Teams::pens( $f, $side );
            ?>
            <li class="aat-row">
                <div class="aat-row__date">
                    <span class="aat-row__day"><?php echo esc_html( Allstars_Adult_Teams::fmt_date( $f['ts'], 'D j M' ) ); ?></span>
                    <span class="aat-row__time"><?php echo $side === 'home' ? 'Home' : 'Away'; ?></span>
                </div>
                <div class="aat-row__main">
                    <div class="aat-row__opp"><?php echo $crest( $opp, $d, $opp_logo ); ?> <span><?php echo esc_html( $opp ); ?></span></div>
                    <div class="aat-row__sub">
                        <?php if ( $f['competition'] ) : ?><span><?php echo esc_html( $f['competition'] ); ?></span><?php endif; ?>
                        <?php if ( $f['ht'] ) : ?><span>HT <?php echo esc_html( $f['ht'] ); ?></span><?php endif; ?>
                        <?php if ( $pens ) : ?><span><?php echo $res === 'W' ? 'Won' : 'Lost'; ?> <?php echo (int) $pens[0]; ?>-<?php echo (int) $pens[1]; ?> on pens</span><?php endif; ?>
                        <?php if ( ! $f['played'] && Allstars_Adult_Teams::walkover_side( $f ) ) : ?><span>Walkover</span><?php endif; ?>
                    </div>
                </div>
                <div class="aat-row__end">
                    <span class="aat-score aat-score--<?php echo esc_attr( $res ?: 'none' ); ?>"><?php if ( $f['played'] ) : echo (int) $us; ?>&ndash;<?php echo (int) $them; else : echo $res ? 'W/O' : esc_html( $f['status'] ); endif; ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else : ?>
        <p class="aat-none">No results yet this season.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    </div><!-- /.aat-cols -->

    <?php if ( in_array( 'table', $show, true ) && $b['table'] ) :
        $has_fa = false;
        foreach ( $b['table'] as $r ) { if ( $r['f'] !== null ) { $has_fa = true; break; } }
    ?>
    <div class="aat-block aat-block--table">
        <h3 class="aat-block__title"><?php echo esc_html( $d['division_name'] ?: 'League table' ); ?></h3>
        <div class="aat-table-wrap">
            <table class="aat-table">
                <thead>
                    <tr><th class="c">Pos</th><th>Team</th><th class="c">P</th><th class="c">W</th><th class="c">D</th><th class="c">L</th><?php if ( $has_fa ) : ?><th class="c aat-hide-sm">F</th><th class="c aat-hide-sm">A</th><?php endif; ?><th class="c">GD</th><th class="c">Pts</th></tr>
                </thead>
                <tbody>
                <?php foreach ( $b['table'] as $r ) : $us = Allstars_Adult_Teams::norm( $r['team'] ) === Allstars_Adult_Teams::norm( $name ); ?>
                    <tr<?php echo $us ? ' class="is-us"' : ''; ?>>
                        <td class="c"><?php echo (int) $r['pos']; ?></td>
                        <td class="aat-table__team"><img class="aat-table__crest" src="<?php echo esc_url( Allstars_Adult_Teams::crest_for( $r['team'], $d ) ); ?>" alt="" loading="lazy"><?php echo esc_html( $r['team'] ); ?></td>
                        <td class="c"><?php echo (int) $r['p']; ?></td>
                        <td class="c"><?php echo (int) $r['w']; ?></td>
                        <td class="c"><?php echo (int) $r['d']; ?></td>
                        <td class="c"><?php echo (int) $r['l']; ?></td>
                        <?php if ( $has_fa ) : ?>
                        <td class="c aat-hide-sm"><?php echo (int) $r['f']; ?></td>
                        <td class="c aat-hide-sm"><?php echo (int) $r['a']; ?></td>
                        <?php endif; ?>
                        <td class="c"><?php echo $r['gd'] > 0 ? '+' : ''; echo (int) $r['gd']; ?></td>
                        <td class="c aat-table__pts"><?php echo (int) $r['pts']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <footer class="aat-foot">
        <?php
        $age   = $now - (int) $d['fetched_at'];
        $stale = $age > 3 * DAY_IN_SECONDS;
        ?>
        Data from <a href="<?php echo esc_url( $d['links']['team'] ); ?>" target="_blank" rel="noopener">FA Full-Time</a>
        &middot; updated <?php echo esc_html( human_time_diff( (int) $d['fetched_at'], $now ) ); ?> ago<?php if ( $stale ) : ?> <span class="aat-foot__stale">(may be out of date)</span><?php endif; ?>
        <?php if ( ! empty( $d['links']['fixtures'] ) ) : ?>
        &middot; <a href="<?php echo esc_url( $d['links']['fixtures'] ); ?>" target="_blank" rel="noopener">All league fixtures</a>
        <?php endif; ?>
    </footer>

    <?php endif; ?>
</section>
<?php endforeach; ?>

</div>
