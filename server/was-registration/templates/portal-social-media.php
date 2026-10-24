<?php defined( 'ABSPATH' ) || exit; ?>

<div id="was-section-social-media" class="was-portal-section">
    <div class="was-portal-section__header">
        <h3 class="was-portal-section__title">Photo &amp; Media</h3>
    </div>

    <?php /* GDPR notice */ ?>
    <div class="was-smo-banner was-smo-banner--gdpr">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div>
            <strong>Data protection notice</strong>
            <p>This section contains personal data about club members. Access is restricted to authorised personnel only. Do not share, download, or distribute any information viewed here.</p>
        </div>
    </div>

    <?php /* Identification notice */ ?>
    <div class="was-smo-banner was-smo-banner--info">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <div>
            <p>Player photos shown here are provided for <strong>identification purposes only</strong> and must not be shared outside of this system.</p>
        </div>
    </div>

    <?php /* Team filter */ ?>
    <div class="was-smo-controls">
        <label for="was-smo-team-filter" class="was-label">Filter by team</label>
        <select id="was-smo-team-filter" class="was-select was-select--inline">
            <option value="">All teams</option>
            <?php foreach ( $teams as $t ) : ?>
            <option value="<?php echo esc_attr( $t->id ); ?>">
                <?php echo esc_html( $t->name ); ?><?php if ( $t->age_group ) : ?> (<?php echo esc_html( $t->age_group ); ?>)<?php endif; ?>
            </option>
            <?php endforeach; ?>
        </select>
        <span class="was-smo-count" id="was-smo-count">
            <?php echo count( $players ); ?> player<?php echo count($players) !== 1 ? 's' : ''; ?> with consent restrictions
        </span>
    </div>

    <?php if ( empty( $players ) ) : ?>
    <div class="was-empty-state">
        <p>No players with consent restrictions found for the <?php echo esc_html( $season ); ?> season.</p>
    </div>

    <?php else : ?>
    <div class="was-card was-smo-card">
        <table class="was-smo-table" id="was-smo-table">
            <thead>
                <tr>
                    <th class="was-smo-col-name">Name</th>
                    <th class="was-smo-col-photo">Photo</th>
                    <th class="was-smo-col-team">Team</th>
                    <th class="was-smo-col-consent">Photo Rights</th>
                    <th class="was-smo-col-consent">Legal Rights</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $players as $p ) :
                $photo_nonce = wp_create_nonce( 'was_photo_nonce' );
                $photo_src   = $p->photo_path
                    ? admin_url( 'admin-ajax.php' ) . '?action=was_serve_photo&player_id=' . $p->id . '&nonce=' . $photo_nonce
                    : '';
                $team_label  = esc_html( $p->team_name . ( $p->age_group ? ' (' . $p->age_group . ')' : '' ) );
            ?>
            <tr data-team-id="<?php echo esc_attr( $p->team_id ); ?>">
                <td class="was-smo-col-name">
                    <strong><?php echo esc_html( trim( $p->first_name . ' ' . $p->last_name ) ); ?></strong>
                </td>
                <td class="was-smo-col-photo">
                    <?php if ( $photo_src ) : ?>
                    <a href="<?php echo esc_url( $photo_src ); ?>" target="_blank" class="was-smo-thumb-link" title="View full size">
                        <img src="<?php echo esc_url( $photo_src ); ?>"
                             class="was-smo-thumb"
                             alt="<?php echo esc_attr( $p->first_name ); ?>"
                             loading="lazy">
                    </a>
                    <?php else : ?>
                    <span class="was-hint">No photo</span>
                    <?php endif; ?>
                </td>
                <td class="was-smo-col-team"><?php echo $team_label; ?></td>
                <td class="was-smo-col-consent">
                    <?php if ( $p->consent_photography === 'agree' ) : ?>
                    <span class="was-consent-tick" title="Agreed">&#10003;</span>
                    <?php else : ?>
                    <span class="was-consent-cross" title="Declined">&#10007;</span>
                    <?php endif; ?>
                </td>
                <td class="was-smo-col-consent">
                    <?php if ( $p->consent_legal === 'agree' ) : ?>
                    <span class="was-consent-tick" title="No proceedings declared">&#10003;</span>
                    <?php else : ?>
                    <span class="was-consent-cross" title="Family, care or legal proceedings declared: do not photograph or share">&#10007;</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<?php /* Lightbox overlay for full-size photo */ ?>
<div id="was-photo-lightbox" class="was-lightbox" style="display:none;" role="dialog" aria-label="Player photo">
    <div class="was-lightbox-backdrop"></div>
    <div class="was-lightbox-content">
        <button class="was-lightbox-close" id="was-lightbox-close" aria-label="Close">&times;</button>
        <img id="was-lightbox-img" src="" alt="Player photo">
    </div>
</div>
