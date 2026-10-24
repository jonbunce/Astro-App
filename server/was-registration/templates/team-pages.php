<?php defined( 'ABSPATH' ) || exit; ?>
<div class="was-team-pages">

<?php if ( $view === 'team' && $team ) : ?>

    <a class="was-news-back" href="<?php echo esc_url( remove_query_arg( array( 'was_team', 'news_page' ) ) ); ?>">&larr; All teams</a>

    <div class="was-news-team-header">
        <?php if ( $team->logo_path ) : ?>
            <img src="<?php echo esc_url( was_logo_url( $team->logo_path ) ); ?>" alt="" class="was-news-team-logo">
        <?php endif; ?>
        <div>
            <h2 class="was-news-team-title"><?php echo esc_html( $team->name ); ?></h2>
            <?php if ( $team->age_group ) : ?><span class="was-news-team-age"><?php echo esc_html( $team->age_group ); ?></span><?php endif; ?>
            <?php if ( ! empty( $team->league ) ) : ?>
            <div class="was-profile-league">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                <?php echo esc_html( $team->league ); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! empty( $team->description ) ) : ?>
    <div class="was-profile-desc"><?php echo nl2br( esc_html( $team->description ) ); ?></div>
    <?php endif; ?>

    <?php
    // Optional profile facts — each renders only when the coach has filled it in.
    $facts = array();
    if ( ! empty( $team->training_info ) ) {
        $facts[] = array( 'Training', $team->training_info,
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' );
    }
    if ( ! empty( $team->coach_names ) ) {
        $contact = ! empty( $team->coach_contact ) ? ' &middot; ' . esc_html( $team->coach_contact ) : '';
        $facts[] = array( 'Coaches', esc_html( $team->coach_names ) . $contact,
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>', true );
    }
    if ( ! empty( $team->honours ) ) {
        $facts[] = array( 'Honours', $team->honours,
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>' );
    }
    ?>
    <?php if ( $facts ) : ?>
    <div class="was-profile-facts">
        <?php foreach ( $facts as $f ) : ?>
        <div class="was-profile-fact">
            <span class="was-profile-fact__icon"><?php echo $f[2]; ?></span>
            <div><span class="was-profile-fact__label"><?php echo esc_html( $f[0] ); ?></span>
            <span class="was-profile-fact__value"><?php echo ! empty( $f[3] ) ? $f[1] : nl2br( esc_html( $f[1] ) ); ?></span></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ( ! empty( $team->sponsor_line ) ) : ?>
    <p class="was-profile-sponsor">&#9733; <?php echo esc_html( $team->sponsor_line ); ?> &mdash; <a href="<?php echo esc_url( home_url( '/our-sponsors/' ) ); ?>">see all our sponsors</a></p>
    <?php endif; ?>

    <h3 class="was-profile-news-heading">Latest news</h3>

    <?php if ( $news && $news->have_posts() ) : ?>
    <div class="was-news-list">
        <?php while ( $news->have_posts() ) : $news->the_post(); ?>
        <article class="was-news-card">
            <?php if ( has_post_thumbnail() ) : ?>
            <a href="<?php the_permalink(); ?>" class="was-news-card__thumb"><?php the_post_thumbnail( 'medium_large' ); ?></a>
            <?php endif; ?>
            <div class="was-news-card__body">
                <h3 class="was-news-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <time class="was-news-card__date"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
                <p class="was-news-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_content(), 30 ) ); ?></p>
                <a class="was-link" href="<?php the_permalink(); ?>">Read more &rarr;</a>
            </div>
        </article>
        <?php endwhile; ?>
    </div>

    <?php if ( $news->max_num_pages > 1 ) : ?>
    <div class="was-news-pagination">
        <?php if ( $page > 1 ) : ?>
        <a class="was-btn was-btn--ghost was-btn--sm" href="<?php echo esc_url( add_query_arg( array( 'was_team' => $slug, 'news_page' => $page - 1 ) ) ); ?>">&larr; Newer</a>
        <?php endif; ?>
        <span class="was-news-pagination__info">Page <?php echo (int) $page; ?> of <?php echo (int) $news->max_num_pages; ?></span>
        <?php if ( $page < $news->max_num_pages ) : ?>
        <a class="was-btn was-btn--ghost was-btn--sm" href="<?php echo esc_url( add_query_arg( array( 'was_team' => $slug, 'news_page' => $page + 1 ) ) ); ?>">Older &rarr;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <p class="was-news-empty">No news posted for <?php echo esc_html( $team->name ); ?> yet &mdash; check back soon!</p>
    <?php endif; ?>

<?php else : ?>

    <?php if ( empty( $teams ) ) : ?>
    <p class="was-news-empty">No teams found for the current season.</p>
    <?php else : ?>
    <div class="was-teams-grid">
        <?php foreach ( $teams as $t ) :
            // One combined label; skip the age suffix when it's already in the name
            $label = $t->name;
            if ( $t->age_group && stripos( $t->name, $t->age_group ) === false ) {
                $label .= ' (' . $t->age_group . ')';
            }
        ?>
        <a class="was-team-tile" href="<?php echo esc_url( add_query_arg( 'was_team', WAS_Team_News::team_slug( $t ) ) ); ?>">
            <?php if ( $t->logo_path ) : ?>
                <img src="<?php echo esc_url( was_logo_url( $t->logo_path ) ); ?>" alt="" class="was-team-tile__logo">
            <?php else : ?>
                <div class="was-team-tile__logo was-team-tile__logo--placeholder">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/></svg>
                </div>
            <?php endif; ?>
            <span class="was-team-tile__name"><?php echo esc_html( $label ); ?></span>
            <span class="was-team-tile__pill">Info / News</span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>

</div>
