<?php defined( 'ABSPATH' ) || exit;
global $wpdb;
$notice = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_schools_nonce'] ) ) {
    check_admin_referer( 'was_save_schools', 'was_schools_nonce' );

    // Delete is triggered by its own submit button (name="delete_school"), so it can
    // live inside the same form as the bulk editor without nesting <form> tags.
    // Check it FIRST — a delete click also carries the editor's hidden action_type.
    if ( isset( $_POST['delete_school'] ) ) {
        $id     = absint( $_POST['delete_school'] );
        $in_use = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}was_players WHERE school_id = %d", $id ) );
        if ( $in_use > 0 ) {
            $notice = 'Cannot delete — this school is assigned to ' . $in_use . ' player(s). Deactivate it instead.';
        } else {
            $wpdb->delete( "{$wpdb->prefix}was_schools", array( 'id' => $id ) );
            $notice = 'School deleted.';
        }
    } else {
        $action = sanitize_key( $_POST['action_type'] ?? '' );

        if ( $action === 'add' ) {
            $name = sanitize_text_field( wp_unslash( $_POST['school_name'] ?? '' ) );
            if ( $name ) {
                $max_order = $wpdb->get_var( "SELECT MAX(sort_order) FROM {$wpdb->prefix}was_schools" ) ?: 0;
                $wpdb->insert( "{$wpdb->prefix}was_schools", array( 'name' => $name, 'sort_order' => $max_order + 10, 'active' => 1 ) );
                $notice = 'School added.';
            }
        } elseif ( $action === 'update' ) {
            $updates = $_POST['schools'] ?? array();
            foreach ( $updates as $id => $data ) {
                $wpdb->update(
                    "{$wpdb->prefix}was_schools",
                    array(
                        'name'       => sanitize_text_field( wp_unslash( $data['name'] ?? '' ) ),
                        'sort_order' => absint( $data['sort_order'] ?? 0 ),
                        'active'     => isset( $data['active'] ) ? 1 : 0,
                    ),
                    array( 'id' => absint( $id ) )
                );
            }
            $notice = 'Schools updated.';
        }
    }
}

$schools = WAS_Database::get_schools( false ); // all including inactive
?>
<div class="wrap was-admin-wrap">
    <h1>Schools</h1>
    <p class="description">Manage the list of schools shown in the player registration form. Deactivate a school to hide it from the dropdown without deleting historical data. Schools with active player registrations cannot be deleted.</p>

    <?php if ( $notice ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
    <?php endif; ?>

    <h2>Add School</h2>
    <form method="post" style="display:flex; gap:8px; align-items:flex-end; margin-bottom:2rem;">
        <?php wp_nonce_field( 'was_save_schools', 'was_schools_nonce' ); ?>
        <input type="hidden" name="action_type" value="add">
        <div>
            <label for="school_name" class="screen-reader-text">School name</label>
            <input type="text" id="school_name" name="school_name" class="regular-text" placeholder="e.g. Himley Primary School" required>
        </div>
        <?php submit_button( 'Add School', 'primary', 'submit', false ); ?>
    </form>

    <h2>All Schools</h2>
    <form method="post">
        <?php wp_nonce_field( 'was_save_schools', 'was_schools_nonce' ); ?>
        <input type="hidden" name="action_type" value="update">

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>School Name</th>
                    <th style="width:80px;">Sort Order</th>
                    <th style="width:80px;">Active</th>
                    <th style="width:80px;">Players</th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $schools as $s ) :
                $player_count = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}was_players WHERE school_id = %d",
                    $s->id
                ) );
            ?>
            <tr>
                <td>
                    <input type="text" name="schools[<?php echo $s->id; ?>][name]" value="<?php echo esc_attr($s->name); ?>" class="regular-text">
                </td>
                <td>
                    <input type="number" name="schools[<?php echo $s->id; ?>][sort_order]" value="<?php echo esc_attr($s->sort_order); ?>" class="small-text">
                </td>
                <td>
                    <input type="checkbox" name="schools[<?php echo $s->id; ?>][active]" value="1" <?php checked( $s->active, 1 ); ?>>
                </td>
                <td><?php echo (int) $player_count; ?></td>
                <td>
                    <?php if ( ! $player_count ) : ?>
                    <button type="submit" name="delete_school" value="<?php echo (int) $s->id; ?>" class="button button-small"
                            onclick="return confirm('Delete <?php echo esc_js($s->name); ?>? This cannot be undone.')">Delete</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button( 'Save Changes' ); ?>
    </form>
</div>
