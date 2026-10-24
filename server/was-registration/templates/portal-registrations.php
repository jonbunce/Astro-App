<?php defined( 'ABSPATH' ) || exit; ?>
<div id="was-section-registrations" class="was-portal-section">

    <div class="was-portal-section__header">
        <h3 class="was-portal-section__title">Players &mdash; <?php echo esc_html( was_season() ); ?></h3>
        <span class="was-readonly-note">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Registration officer &mdash; read only
        </span>
    </div>

    <div class="was-smo-banner">
        <strong>Data protection (GDPR)</strong> This register contains children&rsquo;s photos and family addresses. Access is restricted to authorised personnel only &mdash; do not share, download or distribute.
    </div>

    <div class="was-reg-controls">
        <input type="search" id="was-reg-search" class="was-input" placeholder="Search name, parent, address or postcode&hellip;">
        <?php
        // Default to the first team alphabetically so the initial view isn't the whole club.
        $reg_alpha = $reg_teams;
        usort( $reg_alpha, function ( $a, $b ) { return strcasecmp( $a->name, $b->name ); } );
        $reg_default_team = $reg_alpha ? (int) $reg_alpha[0]->id : 0;
        ?>
        <select id="was-reg-team-filter" class="was-select">
            <option value="">All teams</option>
            <?php foreach ( $reg_alpha as $t ) : ?>
            <option value="<?php echo esc_attr( $t->id ); ?>" <?php selected( (int) $t->id, $reg_default_team ); ?>><?php echo esc_html( $t->name . ( $t->age_group ? ' (' . $t->age_group . ')' : '' ) ); ?></option>
            <?php endforeach; ?>
        </select>
        <span class="was-reg-count"><span id="was-reg-shown"><?php echo count( $reg_players ); ?></span> of <?php echo count( $reg_players ); ?> players</span>
    </div>

    <table class="was-treas-table" id="was-reg-table">
        <thead>
            <tr>
                <th></th>
                <th>Player</th>
                <th>Teams</th>
                <th>Parent / Guardian</th>
                <th>Address</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $reg_players as $rp ) :
            $team_ids = array( (int) $rp->team_id );
            foreach ( $rp->extra_teams as $xt ) $team_ids[] = (int) $xt->team_id;
            $age = was_age( $rp->dob ?? '' );
            $photo_src = $rp->photo_path
                ? admin_url( 'admin-ajax.php' ) . '?action=was_serve_photo&player_id=' . $rp->id . '&nonce=' . $photo_nonce
                : '';
        ?>
        <tr data-team-ids=",<?php echo esc_attr( implode( ',', $team_ids ) ); ?>,">
            <td class="was-reg-photo-cell">
                <?php if ( $photo_src ) : ?>
                <a href="<?php echo esc_url( $photo_src ); ?>" target="_blank" class="was-smo-thumb-link" title="View full size">
                    <img src="<?php echo esc_url( $photo_src ); ?>" class="was-reg-photo" alt="" loading="lazy">
                </a>
                <?php else : ?>
                <span class="was-player-avatar was-player-avatar--sm"><?php echo esc_html( strtoupper( substr( $rp->first_name, 0, 1 ) ) ); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <strong><?php echo esc_html( trim( $rp->first_name . ' ' . $rp->last_name ) ); ?></strong>
                <?php if ( $age !== '' ) : ?><span class="was-reg-age"><?php echo esc_html( $age ); ?> yrs</span><?php endif; ?>
                <br>
                <?php if ( $rp->participation_type === 'signed' ) : ?>
                <span class="was-chip was-chip--signed" title="Signed player &mdash; FA registered">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                    <i>Signed</i>
                </span>
                <?php else : ?>
                <span class="was-chip was-chip--train" title="Train only &mdash; not FA registered">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 3 4 21h16L12 3z"/><line x1="8.7" y1="14" x2="15.3" y2="14"/></svg>
                    <i>Train only</i>
                </span>
                <?php endif; ?>
                <?php if ( $rp->registration_status !== 'active' ) : ?><span class="was-badge was-badge--amber"><?php echo esc_html( ucfirst( $rp->registration_status ) ); ?></span><?php endif; ?>
            </td>
            <td>
                <span class="was-badge was-badge--blue"><?php echo esc_html( $rp->team_name ); ?></span>
                <?php foreach ( $rp->extra_teams as $xt ) : ?>
                <span class="was-badge was-badge--grey"><?php echo esc_html( $xt->team_name ); ?><?php echo $xt->participation_type === 'train_only' ? ' (train)' : ''; ?></span>
                <?php endforeach; ?>
            </td>
            <td>
                <?php echo esc_html( trim( $rp->g_first . ' ' . $rp->g_last ) ); ?><br>
                <span class="was-reg-muted"><?php echo esc_html( $rp->g_tel ); ?> &middot; <a href="mailto:<?php echo esc_attr( $rp->g_email ); ?>" class="was-link"><?php echo esc_html( $rp->g_email ); ?></a></span>
            </td>
            <td>
                <?php echo esc_html( $rp->g_address ); ?><br>
                <span class="was-reg-muted"><?php echo esc_html( $rp->g_postcode ); ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="was-empty-note" id="was-reg-empty" style="display:none;">No players match that filter.</p>

</div>
