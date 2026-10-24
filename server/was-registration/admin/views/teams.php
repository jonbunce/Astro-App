<?php defined( 'ABSPATH' ) || exit;
global $wpdb;
$notice = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_teams_nonce'] ) ) {
    check_admin_referer( 'was_save_teams', 'was_teams_nonce' );
    $_POST = wp_unslash( $_POST ); // strip WordPress' added slashes before sanitising
    $action = sanitize_key( $_POST['action_type'] ?? '' );

    if ( $action === 'add_team' ) {
        $name = sanitize_text_field( $_POST['team_name'] ?? '' );
        if ( $name ) {
            $age_band = ( isset( $_POST['age_band'] ) && $_POST['age_band'] !== '' ) ? absint( $_POST['age_band'] ) : 0;
            $insert_data = array(
                'name'           => $name,
                'age_band'       => $age_band ?: null,
                'age_group'      => $age_band ? 'U' . $age_band : '',
                'league'         => sanitize_text_field( $_POST['league'] ?? '' ),
                'description'    => sanitize_textarea_field( $_POST['description'] ?? '' ),
                'season'         => sanitize_text_field( $_POST['season']         ?? was_season() ),
                'subs_amount'    => (float) ( $_POST['subs_amount']    ?? 0 ),
                'sibling_amount' => (float) ( $_POST['sibling_amount'] ?? 0 ),
                'second_team_amount' => (float) ( $_POST['second_team_amount'] ?? 0 ),
                'active'         => 1,
                'logo_path'      => '',
            );
            // Handle logo upload
            if ( ! empty( $_FILES['team_logo_new']['tmp_name'] ) ) {
                $tmp  = $_FILES['team_logo_new']['tmp_name'];
                $name_file = sanitize_file_name( $_FILES['team_logo_new']['name'] );
                $ext  = strtolower( pathinfo( $name_file, PATHINFO_EXTENSION ) );
                if ( in_array( $ext, array('jpg','jpeg','png','gif','webp','svg'), true ) ) {
                    $filename = 'team-new-' . time() . '.' . $ext;
                    if ( move_uploaded_file( $tmp, was_logo_dir() . $filename ) ) {
                        $insert_data['logo_path'] = $filename;
                    }
                }
            }
            $wpdb->insert( "{$wpdb->prefix}was_teams", $insert_data );
            $notice = 'Team added.';
        }
    } elseif ( $action === 'edit_team' ) {
        $id = absint( $_POST['team_id'] ?? 0 );
        $age_band = ( isset( $_POST['age_band'] ) && $_POST['age_band'] !== '' ) ? absint( $_POST['age_band'] ) : 0;
        $update = array(
            'name'           => sanitize_text_field( $_POST['team_name']      ?? '' ),
            'age_band'       => $age_band ?: null,
            'age_group'      => $age_band ? 'U' . $age_band : '',
            'league'         => sanitize_text_field( $_POST['league'] ?? '' ),
            'description'    => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'season'         => sanitize_text_field( $_POST['season']         ?? was_season() ),
            'subs_amount'    => (float) ( $_POST['subs_amount']    ?? 0 ),
            'sibling_amount' => (float) ( $_POST['sibling_amount'] ?? 0 ),
            'second_team_amount' => (float) ( $_POST['second_team_amount'] ?? 0 ),
            'active'         => isset( $_POST['active'] ) ? 1 : 0,
        );
        // Handle logo upload
        if ( ! empty( $_FILES['team_logo']['tmp_name'] ) ) {
            $tmp  = $_FILES['team_logo']['tmp_name'];
            $name_file = sanitize_file_name( $_FILES['team_logo']['name'] );
            $ext  = strtolower( pathinfo( $name_file, PATHINFO_EXTENSION ) );
            if ( in_array( $ext, array('jpg','jpeg','png','gif','webp','svg'), true ) ) {
                $filename = 'team-' . $id . '-' . time() . '.' . $ext;
                if ( move_uploaded_file( $tmp, was_logo_dir() . $filename ) ) {
                    $update['logo_path'] = $filename;
                }
            }
        }
        $wpdb->update( "{$wpdb->prefix}was_teams", $update, array( 'id' => $id ) );
        $notice = 'Team updated.';
    }
}

$teams = WAS_Database::get_teams( false );
$edit_id = absint( $_GET['edit'] ?? 0 );
$edit_team = $edit_id ? WAS_Database::get_team( $edit_id ) : null;
?>
<div class="wrap was-admin-wrap">
    <h1>Teams</h1>

    <?php if ( $notice ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
    <?php endif; ?>

    <?php if ( $edit_team ) : ?>
    <h2>Edit Team: <?php echo esc_html( $edit_team->name ); ?></h2>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'was_save_teams', 'was_teams_nonce' ); ?>
        <input type="hidden" name="action_type" value="edit_team">
        <input type="hidden" name="team_id" value="<?php echo $edit_team->id; ?>">
        <table class="form-table">
            <tr>
                <th><label>Team Name *</label></th>
                <td><input type="text" name="team_name" class="regular-text" required value="<?php echo esc_attr($edit_team->name); ?>"></td>
            </tr>
            <tr>
                <th><label>Age group</label></th>
                <td>
                    U&nbsp;<input type="number" name="age_band" class="small-text" min="4" max="21" step="1" value="<?php echo esc_attr( $edit_team->age_band ?? '' ); ?>" style="width:70px;">
                    <p class="description">Just the number (e.g. 7 for U7). Advances automatically at season rollover. Leave blank for non-age teams.</p>
                </td>
            </tr>
            <tr>
                <th><label>League</label></th>
                <td>
                    <input type="text" name="league" class="regular-text" value="<?php echo esc_attr( $edit_team->league ?? '' ); ?>" placeholder="e.g. Bilston &amp; District Youth League">
                    <p class="description">Shown on the team's public profile page. Leave blank to hide.</p>
                </td>
            </tr>
            <tr>
                <th><label>Team description</label></th>
                <td>
                    <textarea name="description" class="large-text" rows="4" placeholder="A short introduction to the team for the public Team Profiles page&hellip;"><?php echo esc_textarea( $edit_team->description ?? '' ); ?></textarea>
                    <p class="description">Shown on the team's public profile page above their news feed. Coaches can also edit this from their portal.</p>
                </td>
            </tr>
            <tr>
                <th><label>Season</label></th>
                <td><input type="text" name="season" class="small-text" required value="<?php echo esc_attr($edit_team->season); ?>"></td>
            </tr>
            <tr>
                <th><label>Monthly subs (£)</label></th>
                <td><input type="number" name="subs_amount" class="small-text" value="<?php echo esc_attr($edit_team->subs_amount); ?>" step="0.01" min="0"></td>
            </tr>
            <tr>
                <th><label>Sibling rate (£/mo)</label></th>
                <td><input type="number" name="sibling_amount" class="small-text" value="<?php echo esc_attr($edit_team->sibling_amount); ?>" step="0.01" min="0"></td>
            </tr>
            <tr>
                <th><label>Second-team rate (£/mo)</label></th>
                <td>
                    <input type="number" name="second_team_amount" class="small-text" value="<?php echo esc_attr($edit_team->second_team_amount ?? 0); ?>" step="0.01" min="0">
                    <p class="description">Added to a child's subscription when this team is an <em>additional</em> team they play for.</p>
                </td>
            </tr>
            <tr>
                <th><label>Team Logo</label></th>
                <td>
                    <?php if ( $edit_team->logo_path ) : ?>
                    <img src="<?php echo esc_url( was_logo_url( $edit_team->logo_path ) ); ?>" style="width:60px;height:60px;object-fit:contain;border-radius:6px;display:block;margin-bottom:8px;border:1px solid #ddd;padding:4px;">
                    <small>Upload a new image to replace the current logo.</small><br>
                    <?php endif; ?>
                    <input type="file" name="team_logo" accept="image/*">
                    <p class="description">PNG or SVG recommended. Square format works best.</p>
                </td>
            </tr>
            <tr>
                <th><label>Active</label></th>
                <td><input type="checkbox" name="active" value="1" <?php checked($edit_team->active, 1); ?>> Tick to keep team active</td>
            </tr>
        </table>
        <?php submit_button( 'Save Changes' ); ?>
        <a href="<?php echo esc_url( admin_url('admin.php?page=was-teams') ); ?>" class="button" style="margin-left:8px;">Cancel</a>
    </form>
    <hr>
    <?php else : ?>
    <h2>Add New Team</h2>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'was_save_teams', 'was_teams_nonce' ); ?>
        <input type="hidden" name="action_type" value="add_team">
        <table class="form-table">
            <tr>
                <th><label for="team_name">Team Name *</label></th>
                <td><input type="text" id="team_name" name="team_name" class="regular-text" required placeholder="e.g. Wombourne Allstars Falcons"></td>
            </tr>
            <tr>
                <th><label for="age_band">Age group</label></th>
                <td>
                    U&nbsp;<input type="number" id="age_band" name="age_band" class="small-text" min="4" max="21" step="1" placeholder="7" style="width:70px;">
                    <p class="description">Just the number (e.g. <strong>7</strong> for U7). This advances automatically each season at rollover. Leave blank for non-age teams.</p>
                </td>
            </tr>
            <tr>
                <th><label for="league">League</label></th>
                <td>
                    <input type="text" id="league" name="league" class="regular-text" placeholder="e.g. Bilston &amp; District Youth League">
                    <p class="description">Shown on the team's public profile page. Leave blank to hide.</p>
                </td>
            </tr>
            <tr>
                <th><label for="description">Team description</label></th>
                <td>
                    <textarea id="description" name="description" class="large-text" rows="4" placeholder="A short introduction to the team for the public Team Profiles page&hellip;"></textarea>
                    <p class="description">Shown on the team's public profile page above their news feed. Coaches can also edit this from their portal.</p>
                </td>
            </tr>
            <tr>
                <th><label for="season">Season *</label></th>
                <td><input type="text" id="season" name="season" class="small-text" value="<?php echo esc_attr( was_season() ); ?>" required></td>
            </tr>
            <tr>
                <th><label for="subs_amount">Monthly subs (£)</label></th>
                <td><input type="number" id="subs_amount" name="subs_amount" class="small-text" value="0.00" step="0.01" min="0"></td>
            </tr>
            <tr>
                <th><label for="sibling_amount">Sibling rate (£/mo)</label></th>
                <td>
                    <input type="number" id="sibling_amount" name="sibling_amount" class="small-text" value="0.00" step="0.01" min="0">
                    <p class="description">Discounted rate applied automatically when a second or further child from the same family registers as a signed player.</p>
                </td>
            </tr>
            <tr>
                <th><label for="second_team_amount">Second-team rate (£/mo)</label></th>
                <td>
                    <input type="number" id="second_team_amount" name="second_team_amount" class="small-text" value="17.00" step="0.01" min="0">
                    <p class="description">Added to a child's subscription when this team is an <em>additional</em> team they play for (e.g. £17).</p>
                </td>
            </tr>
            <tr>
                <th><label for="team_logo_new">Team Logo</label></th>
                <td>
                    <input type="file" id="team_logo_new" name="team_logo_new" accept="image/*">
                    <p class="description">PNG or SVG recommended. You can also upload via the Teams page later.</p>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Add Team' ); ?>
    </form>
    <hr>
    <?php endif; ?>

    <h2>All Teams</h2>
    <div id="was-withdraw-result" class="notice" style="display:none; margin:8px 0; padding:8px 12px;"></div>
    <table class="widefat striped">
        <thead>
            <tr>
                <th style="width:50px;">Logo</th>
                <th>Name</th>
                <th>Age Group</th>
                <th>Season</th>
                <th>Subs</th>
                <th>Sibling Rate</th>
                <th>2nd Team</th>
                <th>Players</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $teams ) ) : ?>
            <tr><td colspan="10">No teams yet. Add one above.</td></tr>
        <?php else : ?>
            <?php foreach ( $teams as $t ) : ?>
            <tr>
                <td>
                    <?php if ( $t->logo_path ) : ?>
                    <img src="<?php echo esc_url( was_logo_url( $t->logo_path ) ); ?>" style="width:36px;height:36px;object-fit:contain;border-radius:4px;">
                    <?php else : ?>
                    <span style="color:#bbb;font-size:1.2rem;">&#128100;</span>
                    <?php endif; ?>
                </td>
                <td><strong><?php echo esc_html( $t->name ); ?></strong></td>
                <td><?php echo esc_html( $t->age_group ); ?></td>
                <td><?php echo esc_html( $t->season ); ?></td>
                <td><?php echo $t->subs_amount > 0 ? '£' . number_format( $t->subs_amount, 2 ) . '/mo' : '—'; ?></td>
                <td><?php echo $t->sibling_amount > 0 ? '£' . number_format( $t->sibling_amount, 2 ) . '/mo' : '—'; ?></td>
                <td><?php echo ( ( $t->second_team_amount ?? 0 ) > 0 ) ? '£' . number_format( $t->second_team_amount, 2 ) . '/mo' : '—'; ?></td>
                <td><?php echo (int) ( $t->player_count ?? 0 ); ?></td>
                <td><?php echo $t->active ? '<span class="was-badge was-badge--green">Active</span>' : '<span class="was-badge was-badge--grey">Inactive</span>'; ?></td>
                <td>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=was-teams&edit=' . $t->id) ); ?>" class="button button-small">Edit</a>
                    <?php if ( $t->active ) : ?>
                    <button type="button" class="button button-small was-withdraw-team" data-team="<?php echo (int) $t->id; ?>" data-name="<?php echo esc_attr( $t->name ); ?>" style="color:#c62828;">Withdraw</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
