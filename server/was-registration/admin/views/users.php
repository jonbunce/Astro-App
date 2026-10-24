<?php defined( 'ABSPATH' ) || exit;

// Handle form submission
$notice = '';
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_users_nonce'] ) ) {
    check_admin_referer( 'was_save_user_roles', 'was_users_nonce' );

    $target_id   = absint( $_POST['target_user_id'] ?? 0 );
    $new_roles   = array_map( 'sanitize_key', (array) ( $_POST['was_roles'] ?? array() ) );
    $valid_roles = array( 'parent', 'coach', 'treasurer', 'social_media', 'committee', 'registration_officer' );
    $new_roles   = array_values( array_intersect( $new_roles, $valid_roles ) );
    $coach_teams = array_map( 'absint', (array) ( $_POST['coach_teams'] ?? array() ) );

    if ( $target_id ) {
        // Capture BEFORE saving — reading after made "newly added coach" never true.
        $prev_roles = was_get_roles( $target_id );
        update_user_meta( $target_id, 'was_roles', $new_roles );

        // Sync coach team assignments
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}was_coach_teams", array( 'user_id' => $target_id ), array( '%d' ) );
        foreach ( $coach_teams as $tid ) {
            $wpdb->insert(
                "{$wpdb->prefix}was_coach_teams",
                array( 'user_id' => $target_id, 'team_id' => $tid ),
                array( '%d', '%d' )
            );
        }

        // Notify user if they've just been made a coach
        if ( in_array( 'coach', $new_roles, true ) && ! in_array( 'coach', $prev_roles, true ) ) {
            $u = get_userdata( $target_id );
            if ( $u ) {
                wp_mail(
                    $u->user_email,
                    get_bloginfo('name') . ' — You\'ve been added as a coach',
                    "Hi {$u->first_name},\n\nYou have been added as a coach on the " . get_bloginfo('name') . " club system.\n\nYou can now log in at " . home_url('/account-login/') . " to view your team's player information.\n\nThanks,\n" . get_bloginfo('name')
                );
            }
        }

        $notice = 'Roles updated.';
    }
}

// Fetch ALL WP users — no cap. (A 'number' => 200 limit here silently hid
// everyone past 200 alphabetically once registrations took off.)
$all_users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC', 'number' => -1 ) );
$all_teams = WAS_Database::get_teams();
?>
<div class="wrap was-admin-wrap">
    <h1>Club Members &amp; Roles</h1>
    <p class="description">Manage which members have coach or treasurer access. Every registered parent account appears below. Use the role checkboxes to promote them and, for coaches, select which teams they manage.</p>

    <?php if ( $notice ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
    <?php endif; ?>

    <p style="display:flex;align-items:center;gap:12px;">
        <input type="search" id="was-users-filter" class="regular-text" placeholder="Filter by name or email&hellip;" style="max-width:320px;">
        <span class="description"><span id="was-users-count"><?php echo count( $all_users ); ?></span> of <?php echo count( $all_users ); ?> members shown</span>
    </p>

    <table class="widefat striped" id="was-users-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Current roles</th>
                <th>Teams (if coach)</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $all_users as $u ) :
            $u_roles      = was_get_roles( $u->ID );
            $coach_team_ids = array();
            if ( in_array( 'coach', $u_roles, true ) ) {
                global $wpdb;
                $coach_team_ids = $wpdb->get_col( $wpdb->prepare(
                    "SELECT team_id FROM {$wpdb->prefix}was_coach_teams WHERE user_id = %d",
                    $u->ID
                ) );
            }
            $is_wp_admin = user_can( $u->ID, 'manage_options' );
        ?>
        <tr>
            <td>
                <strong><?php echo esc_html( $u->display_name ); ?></strong>
                <?php if ( $is_wp_admin ) : ?><br><small style="color:#999">WP Admin</small><?php endif; ?>
            </td>
            <td><?php echo esc_html( $u->user_email ); ?></td>
            <td>
                <?php if ( $is_wp_admin ) : ?>
                    <span class="was-badge was-badge--blue">Admin</span>
                <?php endif; ?>
                <?php if ( $is_wp_admin && empty( $u_roles ) ) : ?>
                <?php elseif ( empty( $u_roles ) ) : ?>
                    <span class="was-badge was-badge--grey">None yet</span>
                <?php else : ?>
                    <?php foreach ( $u_roles as $r ) : ?>
                    <span class="was-badge was-badge--<?php echo $r === 'treasurer' ? 'amber' : ( $r === 'coach' ? 'green' : ( $r === 'committee' ? 'red' : 'blue' ) ); ?>"><?php echo $r === 'social_media' ? 'Social Media' : ( $r === 'registration_officer' ? 'Registration' : ucfirst($r) ); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if ( ! empty( $coach_team_ids ) ) :
                    foreach ( $all_teams as $t ) :
                        if ( in_array( $t->id, $coach_team_ids ) ) : ?>
                        <span class="was-badge was-badge--grey"><?php echo esc_html( $t->name ); ?></span>
                    <?php endif;
                    endforeach;
                else : ?>
                    <span style="color:#aaa">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ( ! $is_wp_admin ) : ?>
                <button type="button"
                        class="button button-small was-edit-roles-btn"
                        data-user-id="<?php echo esc_attr( $u->ID ); ?>"
                        data-name="<?php echo esc_attr( $u->display_name ); ?>"
                        data-roles="<?php echo esc_attr( implode( ',', $u_roles ) ); ?>"
                        data-teams="<?php echo esc_attr( implode( ',', $coach_team_ids ) ); ?>">
                    Edit roles
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
(function () {
    var input = document.getElementById('was-users-filter');
    var count = document.getElementById('was-users-count');
    if (!input) return;
    input.addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        var rows = document.querySelectorAll('#was-users-table tbody tr');
        var shown = 0;
        rows.forEach(function (row) {
            var hit = !q || row.cells[0].textContent.toLowerCase().indexOf(q) !== -1
                        || row.cells[1].textContent.toLowerCase().indexOf(q) !== -1;
            row.style.display = hit ? '' : 'none';
            if (hit) shown++;
        });
        count.textContent = shown;
    });
})();
</script>

<!-- Role edit modal -->
<div id="was-roles-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:99999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:8px; padding:24px; width:480px; max-width:90vw;">
        <h2 id="was-modal-title" style="margin:0 0 16px;">Edit roles</h2>

        <form method="post" id="was-roles-form">
            <?php wp_nonce_field( 'was_save_user_roles', 'was_users_nonce' ); ?>
            <input type="hidden" name="target_user_id" id="was-modal-user-id">

            <fieldset style="border:1px solid #dde1e7; border-radius:6px; padding:12px 16px; margin-bottom:16px;">
                <legend style="font-weight:600; padding:0 6px;">Roles</legend>
                <label style="display:block; margin-bottom:8px;">
                    <input type="checkbox" name="was_roles[]" value="parent" id="was-role-parent"> Parent
                </label>
                <label style="display:block; margin-bottom:8px;">
                    <input type="checkbox" name="was_roles[]" value="coach" id="was-role-coach"> Coach
                </label>
                <label style="display:block; margin-bottom:8px;">
                    <input type="checkbox" name="was_roles[]" value="treasurer" id="was-role-treasurer"> Treasurer
                </label>
                <label style="display:block; margin-bottom:8px;">
                    <input type="checkbox" name="was_roles[]" value="social_media" id="was-role-social_media"> Social Media Officer
                </label>
                <label style="display:block; margin-bottom:8px;">
                    <input type="checkbox" name="was_roles[]" value="committee" id="was-role-committee"> Committee <small style="color:#888;">(full access: all teams' player data, payments, consents, news review, club messages)</small>
                </label>
                <label style="display:block;">
                    <input type="checkbox" name="was_roles[]" value="registration_officer" id="was-role-registration_officer"> Registration Officer <small style="color:#888;">(Players panel: all players, photos, addresses, teams, payment status)</small>
                </label>
            </fieldset>

            <div id="was-teams-fieldset" style="display:none;">
                <fieldset style="border:1px solid #dde1e7; border-radius:6px; padding:12px 16px; margin-bottom:16px;">
                    <legend style="font-weight:600; padding:0 6px;">Coach teams</legend>
                    <?php foreach ( $all_teams as $t ) : ?>
                    <label style="display:block; margin-bottom:6px;">
                        <input type="checkbox" name="coach_teams[]" value="<?php echo esc_attr( $t->id ); ?>" class="was-team-check">
                        <?php echo esc_html( $t->name ); ?>
                        <?php if ( $t->age_group ) : ?><small style="color:#999">(<?php echo esc_html($t->age_group); ?>)</small><?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </fieldset>
            </div>

            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" id="was-modal-cancel" class="button">Cancel</button>
                <input type="submit" class="button button-primary" value="Save roles">
            </div>
        </form>
    </div>
</div>
