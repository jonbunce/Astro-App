<?php defined( 'ABSPATH' ) || exit; ?>
<div id="was-section-team-news" class="was-portal-section">

    <div class="was-portal-section__header">
        <h3 class="was-portal-section__title">Team news</h3>
    </div>

    <?php // ── Coach: submit a news item ─────────────────────────────────────
    if ( ! empty( $can_submit ) && ! empty( $team_data ) ) {
        include WAS_PATH . 'templates/portal-coach-news.php';
    }
    ?>

    <?php if ( ! empty( $can_review ) ) : ?>

    <div class="was-portal-section__header was-mt-2">
        <h3 class="was-portal-section__title">Review &amp; publish</h3>
    </div>

    <h4 class="was-detail-heading">Awaiting review (<?php echo count( $pending ); ?>)</h4>

    <?php if ( empty( $pending ) ) : ?>
        <p class="was-empty-note">No submissions waiting for review.</p>
    <?php else : ?>
        <?php foreach ( $pending as $row ) : $p = $row['post']; ?>
        <div class="was-card was-news-review-card" data-post-id="<?php echo esc_attr( $p->ID ); ?>">
            <div class="was-news-review-meta">
                <strong><?php echo esc_html( $row['team_name'] ); ?></strong>
                <?php if ( $row['type_label'] ) : ?>&middot; <span class="was-badge was-badge--grey"><?php echo esc_html( $row['type_label'] ); ?></span><?php endif; ?>
                <?php if ( ! empty( $row['social'] ) ) : ?>&middot; <span class="was-badge was-badge--red" title="The coach asked for this to go on the club's social media">&#128227; Social requested</span><?php endif; ?>
                &middot; submitted by <?php echo esc_html( $row['coach'] ); ?>
                &middot; <?php echo esc_html( get_the_date( 'j M Y', $p ) ); ?>
            </div>

            <?php if ( ! empty( $row['no_photo'] ) ) : ?>
            <div class="was-smo-banner was-smo-banner--gdpr">
                <strong>Photo consent warning:</strong> these players must NOT appear in photos:
                <?php echo esc_html( implode( ', ', $row['no_photo'] ) ); ?>
            </div>
            <?php endif; ?>

            <div class="was-field">
                <label class="was-label">Title</label>
                <input type="text" class="was-input was-news-edit-title" maxlength="120" value="<?php echo esc_attr( $p->post_title ); ?>">
            </div>
            <div class="was-field">
                <label class="was-label">Text</label>
                <textarea class="was-input was-news-edit-content" rows="5"><?php echo esc_textarea( $p->post_content ); ?></textarea>
            </div>

            <?php if ( $row['photos'] ) : ?>
            <div class="was-news-photo-strip">
                <?php foreach ( $row['photos'] as $aid ) : ?>
                <div class="was-news-photo" data-attachment-id="<?php echo esc_attr( $aid ); ?>">
                    <?php echo wp_get_attachment_image( $aid, 'thumbnail' ); ?>
                    <button type="button" class="was-news-photo-remove" title="Remove this photo">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="was-field">
                <label class="was-label">Add photos <span class="was-hint">(5 max in total)</span></label>
                <input type="file" class="was-news-add-photos" accept="image/*" multiple>
            </div>

            <div class="was-alert was-alert--error was-news-review-error" style="display:none;"></div>

            <div class="was-news-review-actions">
                <button type="button" class="was-btn was-btn--ghost was-btn--sm was-news-save-btn">Save changes</button>
                <button type="button" class="was-btn was-btn--primary was-btn--sm was-news-approve-btn">Approve &amp; publish</button>
                <button type="button" class="was-btn was-btn--ghost was-btn--sm was-news-reject-btn">Reject</button>
            </div>
            <div class="was-news-reject-panel" style="display:none;">
                <textarea class="was-input was-news-reject-reason" rows="2" placeholder="Optional note to the coach &mdash; why is this not being published?"></textarea>
                <button type="button" class="was-btn was-btn--primary was-btn--sm was-news-reject-confirm">Confirm reject</button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h4 class="was-card-title was-mt-2">Recently published &mdash; Facebook</h4>

    <?php if ( empty( $published ) ) : ?>
        <p class="was-empty-note">Nothing published yet.</p>
    <?php else : ?>
        <?php
        // Coach-requested social posts that aren't on Facebook yet float to the top.
        usort( $published, function ( $a, $b ) {
            $aq = ( ! empty( $a['social'] ) && $a['fb_status'] === 'none' ) ? 0 : 1;
            $bq = ( ! empty( $b['social'] ) && $b['fb_status'] === 'none' ) ? 0 : 1;
            return $aq <=> $bq;
        } );
        ?>
        <?php foreach ( $published as $row ) : $p = $row['post'];
            $compose = $p->post_title . "\n\n" . $p->post_content . "\n\n" . get_permalink( $p );
        ?>
        <div class="was-card was-news-fb-card" data-post-id="<?php echo esc_attr( $p->ID ); ?>">
            <div class="was-news-review-meta">
                <strong><?php echo esc_html( get_the_title( $p ) ); ?></strong>
                &middot; <?php echo esc_html( $row['team_name'] ); ?>
                &middot; <?php echo esc_html( get_the_date( 'j M Y', $p ) ); ?>
                &middot; <a href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener">View on site</a>
                <?php if ( $row['fb_status'] === 'api' ) : ?>
                    <span class="was-badge was-badge--green">Posted via API</span>
                <?php elseif ( $row['fb_status'] === 'manual' ) : ?>
                    <span class="was-badge was-badge--green">Posted (manual)</span>
                <?php else : ?>
                    <span class="was-badge was-badge--grey">Not on Facebook</span>
                <?php endif; ?>
                <?php if ( ! empty( $row['social'] ) && $row['fb_status'] === 'none' ) : ?>
                    <span class="was-badge was-badge--red" title="The coach asked for this to go on the club's social media">&#128227; Social requested</span>
                <?php endif; ?>
            </div>

            <details class="was-news-fb-tools">
                <summary>Facebook tools</summary>
                <div class="was-field was-mt-1">
                    <label class="was-label">Post text</label>
                    <textarea class="was-input was-news-fb-text" rows="4" readonly><?php echo esc_textarea( $compose ); ?></textarea>
                </div>
                <div class="was-news-fb-actions">
                    <button type="button" class="was-btn was-btn--ghost was-btn--sm was-news-copy-btn">Copy text</button>
                    <?php foreach ( $row['photos'] as $i => $aid ) : $u = wp_get_attachment_url( $aid ); if ( ! $u ) continue; ?>
                    <a class="was-btn was-btn--ghost was-btn--sm" href="<?php echo esc_url( $u ); ?>" download>Photo <?php echo (int) $i + 1; ?></a>
                    <?php endforeach; ?>
                    <?php if ( $fb_page_url ) : ?>
                    <a class="was-btn was-btn--ghost was-btn--sm" href="<?php echo esc_url( $fb_page_url ); ?>" target="_blank" rel="noopener">Open Facebook page</a>
                    <?php endif; ?>
                    <?php if ( $row['fb_status'] === 'none' ) : ?>
                    <button type="button" class="was-btn was-btn--ghost was-btn--sm was-news-fb-mark-btn">Mark as posted</button>
                    <?php if ( $fb_api ) : ?>
                    <button type="button" class="was-btn was-btn--primary was-btn--sm was-news-fb-push-btn">Push to Facebook</button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="was-alert was-alert--error was-news-fb-error" style="display:none;"></div>
            </details>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; // $can_review ?>

</div>
