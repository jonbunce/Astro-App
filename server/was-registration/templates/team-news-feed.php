<?php defined( 'ABSPATH' ) || exit; ?>
<div class="was-team-news-feed">

<?php if ( $feed->have_posts() ) : ?>

    <div class="was-feed">
        <?php while ( $feed->have_posts() ) : $feed->the_post();
            $post_id = get_the_ID();
            $team    = WAS_Team_News::post_team( $post_id );
            $t_slug  = get_post_meta( $post_id, WAS_Team_News::META_TEAM_SLUG, true );
            $t_url   = WAS_Team_News::profile_url( $t_slug );
        ?>
        <article class="was-feed-card">
            <div class="was-feed-card__head">
                <?php if ( $team && $team->logo_path ) : ?>
                    <img src="<?php echo esc_url( was_logo_url( $team->logo_path ) ); ?>" alt="" class="was-feed-card__badge">
                <?php endif; ?>
                <div class="was-feed-card__meta">
                    <?php if ( $team ) : ?>
                        <?php if ( $t_url ) : ?>
                        <a class="was-feed-card__team" href="<?php echo esc_url( $t_url ); ?>"><?php echo esc_html( $team->name ); ?></a>
                        <?php else : ?>
                        <span class="was-feed-card__team"><?php echo esc_html( $team->name ); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <time class="was-feed-card__date"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
                </div>
            </div>

            <?php if ( has_post_thumbnail() ) : ?>
            <a href="<?php the_permalink(); ?>" class="was-feed-card__thumb"><?php the_post_thumbnail( 'medium_large' ); ?></a>
            <?php endif; ?>

            <div class="was-feed-card__body">
                <h3 class="was-feed-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <p class="was-feed-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_content(), 25 ) ); ?></p>
                <a class="was-link" href="<?php the_permalink(); ?>">Read more &rarr;</a>
            </div>
        </article>
        <?php endwhile; ?>
    </div>

    <?php if ( $feed->max_num_pages > 1 ) : ?>
    <div class="was-news-pagination">
        <?php if ( $page > 1 ) : ?>
        <a class="was-btn was-btn--ghost was-btn--sm" href="<?php echo esc_url( add_query_arg( 'news_page', $page - 1 ) ); ?>">&larr; Newer</a>
        <?php endif; ?>
        <span class="was-news-pagination__info">Page <?php echo (int) $page; ?> of <?php echo (int) $feed->max_num_pages; ?></span>
        <?php if ( $page < $feed->max_num_pages ) : ?>
        <a class="was-btn was-btn--ghost was-btn--sm" href="<?php echo esc_url( add_query_arg( 'news_page', $page + 1 ) ); ?>">Older &rarr;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php else : ?>
    <p class="was-news-empty">No team news yet &mdash; check back soon!</p>
<?php endif; ?>

</div>
