<?php defined( 'ABSPATH' ) || exit; ?>
<div class="was-card was-news-submit-card" id="was-coach-news">

    <h4 class="was-card-title">Team news</h4>
    <p class="was-empty-note">Share match reports and team updates &mdash; a title, a short write-up and up to 5 photos. Your post is reviewed by the club before it appears on the website.</p>

    <div class="was-smo-banner was-smo-banner--gdpr was-news-policy-note">
        <p><strong>Before you post:</strong> no match scores (unless a competitive league U12+ or cup match) and no children&rsquo;s names.</p>
    </div>

    <form id="was-news-form">
        <?php if ( count( $team_data ) > 1 ) : ?>
        <div class="was-field">
            <label class="was-label" for="was-news-team">Team</label>
            <select id="was-news-team" class="was-select">
                <?php foreach ( $team_data as $td ) : ?>
                <option value="<?php echo esc_attr( $td['team']->id ); ?>"><?php echo esc_html( $td['team']->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else : ?>
        <input type="hidden" id="was-news-team" value="<?php echo esc_attr( $team_data[0]['team']->id ); ?>">
        <?php endif; ?>

        <?php foreach ( $team_data as $td ) :
            $names = $news_no_photo[ $td['team']->id ] ?? array();
            if ( ! $names ) continue; ?>
        <div class="was-smo-banner was-smo-banner--gdpr was-news-consent-banner" data-team-id="<?php echo esc_attr( $td['team']->id ); ?>" style="display:none;">
            <strong>Photo consent warning:</strong> the following players must NOT appear in photos (no photography consent):
            <?php echo esc_html( implode( ', ', $names ) ); ?>
        </div>
        <?php endforeach; ?>

        <div class="was-field">
            <label class="was-label" for="was-news-type">Subject</label>
            <select id="was-news-type" class="was-select">
                <option value="match_report" selected>Match Report</option>
                <option value="general_news">General News</option>
                <option value="shoutout">Shoutout</option>
            </select>
        </div>

        <div class="was-field">
            <label class="was-label" for="was-news-title" id="was-news-title-label">Match headline</label>
            <input type="text" id="was-news-title" class="was-input" maxlength="120" placeholder="e.g. Hard-fought win in Sunday's cup tie">
        </div>

        <div class="was-field">
            <label class="was-label" for="was-news-content" id="was-news-content-label">Match report</label>
            <textarea id="was-news-content" class="was-input" rows="5" placeholder="How did the game go? Highlights, effort, sportsmanship &mdash; only include the score if it was a competitive league (U12+) or cup match."></textarea>
        </div>

        <div class="was-field">
            <label class="was-label" for="was-news-photos">Photos <span class="was-hint">(optional &mdash; up to 5, max 8MB each)</span></label>
            <input type="file" id="was-news-photos" accept="image/*" multiple>
            <div id="was-news-preview" class="was-news-photo-strip"></div>
        </div>

        <div class="was-field">
            <label class="was-checkbox-label">
                <input type="checkbox" id="was-news-social" value="1">
                <span>&#128227; <strong>Suggest for the club&rsquo;s social media</strong> &mdash; the social team will review it before anything is posted</span>
            </label>
        </div>

        <div class="was-alert was-alert--error"   id="was-news-error"   style="display:none;"></div>
        <div class="was-alert was-alert--success" id="was-news-success" style="display:none;"></div>

        <button type="submit" class="was-btn was-btn--primary" id="was-news-submit-btn">
            <span class="was-btn-label">Post update</span>
            <span class="was-btn-spinner" style="display:none;">Sending&hellip;</span>
        </button>
    </form>

    <?php if ( ! empty( $my_news ) ) : ?>
    <div class="was-news-mine">
        <h4 class="was-card-title was-mt-2">My submissions</h4>
        <ul class="was-news-mine-list">
            <?php foreach ( $my_news as $np ) :
                $status = $np->post_status;
                $reason = get_post_meta( $np->ID, WAS_Team_News::META_REJECT, true );
                $tid    = (int) get_post_meta( $np->ID, WAS_Team_News::META_TEAM_ID, true );
                $tname  = '';
                foreach ( $team_data as $td ) {
                    if ( (int) $td['team']->id === $tid ) { $tname = $td['team']->name; break; }
                }
                if ( ! $tname && $tid ) {
                    $t = WAS_Database::get_team( $tid );
                    $tname = $t ? $t->name : '';
                }
            ?>
            <li class="was-news-mine-item">
                <span class="was-news-mine-title"><?php echo esc_html( get_the_title( $np ) ); ?></span>
                <span class="was-news-mine-meta"><?php echo esc_html( $tname ); ?> &middot; <?php echo esc_html( get_the_date( 'j M Y', $np ) ); ?></span>
                <?php if ( $status === 'publish' ) : ?>
                    <a class="was-badge was-badge--green" href="<?php echo esc_url( get_permalink( $np ) ); ?>" target="_blank" rel="noopener">Published</a>
                <?php elseif ( $status === 'pending' ) : ?>
                    <span class="was-badge was-badge--amber">Awaiting review</span>
                <?php else : ?>
                    <span class="was-badge was-badge--red">Not approved</span>
                    <?php if ( $reason ) : ?><span class="was-news-mine-reason">Reviewer note: <?php echo esc_html( $reason ); ?></span><?php endif; ?>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

</div>
