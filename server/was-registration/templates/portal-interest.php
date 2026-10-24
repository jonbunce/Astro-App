<?php defined( 'ABSPATH' ) || exit; ?>
<div id="was-section-interest" class="was-portal-section">

    <div class="was-portal-section__header">
        <h3 class="was-portal-section__title">Interest List</h3>
        <a href="<?php echo esc_url( $int_csv_url ); ?>" class="was-btn was-btn--secondary was-btn--sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
        </a>
    </div>

    <div class="was-smo-banner was-smo-banner--gdpr">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <p>
            <strong>Data protection (GDPR)</strong>
            These are children&rsquo;s details and family contact information given to us in confidence. Use them only to offer a place &mdash; do not share or distribute the list outside the committee.
        </p>
    </div>

    <p class="was-int-intro">
        Families waiting for a place. The demand figures below exclude anyone already placed or archived &mdash; that&rsquo;s the evidence for the council and landowners. Export the CSV for the full pack.
    </p>

    <div class="was-int-demand">
        <?php if ( $int_demand ) : foreach ( $int_demand as $d ) : ?>
        <span class="was-int-demand__pill">
            <?php echo esc_html( $d->age_group ); ?> &times; <?php echo (int) $d->c; ?>
            <?php if ( $d->up ) : ?><i><?php echo (int) $d->up; ?> could play up</i><?php endif; ?>
        </span>
        <?php endforeach; else : ?>
        <span class="was-int-demand__empty">No families are currently waiting.</span>
        <?php endif; ?>
    </div>

    <div class="was-reg-controls">
        <input type="search" id="was-int-search" class="was-input" placeholder="Search name, parent, postcode&hellip;">
        <select id="was-int-status-filter" class="was-select">
            <option value="waiting">Still waiting (new, contacted, offered)</option>
            <option value="">All statuses</option>
            <?php foreach ( $int_statuses as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?><?php echo isset( $int_counts[ $val ] ) ? ' (' . (int) $int_counts[ $val ] . ')' : ''; ?></option>
            <?php endforeach; ?>
        </select>
        <span class="was-reg-count"><span id="was-int-shown"><?php echo count( $int_rows ); ?></span> of <?php echo count( $int_rows ); ?> entries</span>
    </div>

    <div id="was-int-msg" class="was-alert" style="display:none; margin-bottom:1rem;"></div>

    <table class="was-treas-table" id="was-int-table">
        <thead>
            <tr>
                <th>Received</th>
                <th>Player</th>
                <th>Contact</th>
                <th>Postcode</th>
                <th>Experience</th>
                <th>Needs</th>
                <th class="was-int-manage-col">Status &amp; notes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $int_rows as $r ) : ?>
        <tr data-entry-id="<?php echo (int) $r->id; ?>" data-status="<?php echo esc_attr( $r->status ); ?>">
            <td data-label="Received">
                <?php echo esc_html( mysql2date( 'j M Y', $r->created_at ) ); ?>
                <br><span class="was-reg-muted"><?php echo esc_html( $r->season ); ?></span>
            </td>
            <td data-label="Player">
                <strong><?php echo esc_html( trim( $r->player_first . ' ' . $r->player_last ) ); ?></strong><br>
                <span class="was-badge was-badge--blue"><?php echo esc_html( $r->age_group ); ?></span>
                <?php if ( $r->play_up ) : ?><span class="was-badge was-badge--amber">could play up</span><?php endif; ?>
                <br><span class="was-reg-muted">DOB <?php echo esc_html( $r->dob ? mysql2date( 'j M Y', $r->dob ) : '—' ); ?></span>
            </td>
            <td data-label="Contact">
                <?php echo esc_html( $r->parent_name ); ?><?php if ( $r->relationship ) : ?> <span class="was-reg-muted">(<?php echo esc_html( $r->relationship ); ?>)</span><?php endif; ?><br>
                <a href="mailto:<?php echo esc_attr( $r->email ); ?>" class="was-link"><?php echo esc_html( $r->email ); ?></a><br>
                <a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $r->phone ) ); ?>" class="was-link"><?php echo esc_html( $r->phone ); ?></a>
            </td>
            <td data-label="Postcode"><?php echo esc_html( $r->postcode ); ?></td>
            <td data-label="Experience" class="was-int-freetext"><?php echo esc_html( $r->experience ?: '—' ); ?></td>
            <td data-label="Needs" class="was-int-freetext"><?php echo esc_html( $r->disabilities ?: '—' ); ?></td>
            <td data-label="Status &amp; notes" class="was-int-manage">
                <select class="was-select was-int-status">
                    <?php foreach ( $int_statuses as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $r->status, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea class="was-textarea was-int-notes" rows="2" placeholder="Notes&hellip;"><?php echo esc_textarea( $r->admin_notes ); ?></textarea>
                <div class="was-int-actions">
                    <button type="button" class="was-btn was-btn--primary was-btn--sm was-int-save">Save</button>
                    <button type="button" class="was-btn was-btn--sm was-btn--outline was-int-delete" title="Permanently delete this entry">Delete</button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p class="was-empty-note" id="was-int-empty" <?php echo $int_rows ? 'style="display:none;"' : ''; ?>>
        <?php echo $int_rows ? 'No entries match that filter.' : 'Nothing here yet — entries from the Register Your Interest form appear here automatically.'; ?>
    </p>

</div>
