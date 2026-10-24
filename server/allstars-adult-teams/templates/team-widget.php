<?php if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * [adult-team-widget-N] — compact card: up next, latest result, league position.
 * Variables: $cards, $now (see Allstars_Adult_Teams::render_widget).
 */
?>
<div class="aat aat-widgets">
<?php foreach ( $cards as $c ) :
    $s    = $c['slot'];
    $d    = $c['data'];
    $name = $d ? $d['team_name'] : $s['label'];
    $next = $c['next'];
    $last = $c['last'];
    $row  = ( $c['our_row'] && $c['our_row']['p'] > 0 ) ? $c['our_row'] : null;   // no standing until a game has been played
?>
<div class="aat-widget" id="adult-team-widget-<?php echo (int) $s['slot']; ?>">
    <div class="aat-widget__head">
        <img class="aat-widget__badge" src="<?php echo esc_url( Allstars_Adult_Teams::our_badge_url() ); ?>" alt="">
        <div class="aat-widget__head-text">
            <div class="aat-widget__title"><?php echo esc_html( $s['label'] ); ?></div>
            <?php if ( $d ) : ?>
            <div class="aat-widget__sub"><?php echo esc_html( $d['division_name'] ?: $d['league_name'] ); ?><?php if ( $row ) : ?> &middot; <strong><?php echo esc_html( Allstars_Adult_Teams::ordinal( $row['pos'] ) ); ?></strong><?php endif; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! $d ) : ?>
        <p class="aat-widget__none">Fixtures are being fetched from FA Full-Time &mdash; check back shortly.</p>
    <?php else : ?>

    <div class="aat-widget__section">
        <div class="aat-widget__label">Up next</div>
        <?php if ( $next ) :
            list( $side ) = Allstars_Adult_Teams::perspective( $next, $name );
            $opp      = $side === 'home' ? $next['away'] : $next['home'];
            $opp_logo = $side === 'home' ? $next['away_logo'] : $next['home_logo'];
            $is_today = Allstars_Adult_Teams::fmt_date( $next['ts'], 'Y-m-d' ) === Allstars_Adult_Teams::fmt_date( $now, 'Y-m-d' );
        ?>
        <div class="aat-widget__fixture">
            <img class="aat-crest" src="<?php echo esc_url( Allstars_Adult_Teams::crest_for( $opp, $d, $opp_logo ) ); ?>" alt="" loading="lazy">
            <div class="aat-widget__fixture-text">
                <div class="aat-widget__opp"><?php echo esc_html( $opp ); ?> <span class="aat-chip aat-chip--<?php echo $side; ?>"><?php echo $side === 'home' ? 'H' : 'A'; ?></span></div>
                <div class="aat-widget__when">
                    <?php echo $is_today ? 'Today' : esc_html( Allstars_Adult_Teams::fmt_date( $next['ts'], 'D j M' ) ); ?><?php if ( $next['time'] ) : ?>, <?php echo esc_html( $next['time'] ); ?><?php endif; ?>
                    <?php if ( $next['venue'] ) : ?><span class="aat-widget__venue"> &middot; <?php echo esc_html( ucwords( strtolower( $next['venue'] ) ) ); ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php else : ?>
        <p class="aat-widget__none">No fixture published yet.</p>
        <?php endif; ?>
    </div>

    <div class="aat-widget__section">
        <div class="aat-widget__label">Latest result</div>
        <?php if ( $last ) :
            list( $side, $res ) = Allstars_Adult_Teams::perspective( $last, $name );
            $opp      = $side === 'home' ? $last['away'] : $last['home'];
            $opp_logo = $side === 'home' ? $last['away_logo'] : $last['home_logo'];
            $us       = $side === 'home' ? $last['hs'] : $last['as'];
            $them     = $side === 'home' ? $last['as'] : $last['hs'];
            $pens     = Allstars_Adult_Teams::pens( $last, $side );
        ?>
        <div class="aat-widget__fixture">
            <img class="aat-crest" src="<?php echo esc_url( Allstars_Adult_Teams::crest_for( $opp, $d, $opp_logo ) ); ?>" alt="" loading="lazy">
            <div class="aat-widget__fixture-text">
                <div class="aat-widget__opp"><?php echo esc_html( $opp ); ?> <span class="aat-chip aat-chip--<?php echo $side; ?>"><?php echo $side === 'home' ? 'H' : 'A'; ?></span></div>
                <div class="aat-widget__when"><?php echo esc_html( Allstars_Adult_Teams::fmt_date( $last['ts'], 'D j M' ) ); ?><?php if ( $last['competition'] ) : ?> &middot; <?php echo esc_html( $last['competition'] ); ?><?php endif; ?><?php if ( $pens ) : ?> &middot; <?php echo $res === 'W' ? 'won' : 'lost'; ?> <?php echo (int) $pens[0]; ?>-<?php echo (int) $pens[1]; ?> on pens<?php endif; ?></div>
            </div>
            <span class="aat-score aat-score--<?php echo esc_attr( $res ?: 'none' ); ?>"><?php if ( $last['played'] ) : echo (int) $us; ?>&ndash;<?php echo (int) $them; else : echo $res ? 'W/O' : esc_html( $last['status'] ); endif; ?></span>
        </div>
        <?php else : ?>
        <p class="aat-widget__none">Season not started yet.</p>
        <?php endif; ?>
    </div>

    <?php if ( $row ) : ?>
    <div class="aat-widget__standing">
        <span><?php echo esc_html( Allstars_Adult_Teams::ordinal( $row['pos'] ) ); ?></span> in <?php echo esc_html( $d['division_name'] ?: 'the league' ); ?>
        &middot; P<?php echo (int) $row['p']; ?> W<?php echo (int) $row['w']; ?> D<?php echo (int) $row['d']; ?> L<?php echo (int) $row['l']; ?> &middot; <?php echo (int) $row['pts']; ?> pts
        <?php if ( $c['form'] ) : ?>
        <span class="aat-form"><?php foreach ( $c['form'] as $f ) : ?><i class="aat-form__dot aat-form__dot--<?php echo esc_attr( $f ); ?>"><?php echo esc_html( $f ); ?></i><?php endforeach; ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <a class="aat-widget__link" href="<?php echo esc_url( $c['link'] ); ?>"<?php echo empty( $s['page_url'] ) ? ' target="_blank" rel="noopener"' : ''; ?>>Fixtures, results &amp; table &rarr;</a>

    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
