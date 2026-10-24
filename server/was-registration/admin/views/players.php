<?php defined( 'ABSPATH' ) || exit;

$status_labels = array(
    'active'    => '<span class="was-badge was-badge--green">Active</span>',
    'pending'   => '<span class="was-badge was-badge--yellow">Pending</span>',
    'inactive'  => '<span class="was-badge was-badge--red">Inactive</span>',
    'withdrawn' => '<span class="was-badge was-badge--grey">Withdrawn</span>',
);

// ── Bulk action handling ──────────────────────────────────────────────────────
$notice = '';
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_bulk_nonce'] ) ) {
    check_admin_referer( 'was_bulk_action', 'was_bulk_nonce' );

    $selected   = array_map( 'absint', (array) ( $_POST['player_ids'] ?? array() ) );
    $bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );

    if ( ! empty( $selected ) && $bulk_action ) {
        global $wpdb;
        switch ( $bulk_action ) {
            case 'withdraw':
                foreach ( $selected as $pid ) {
                    $wpdb->update( "{$wpdb->prefix}was_players", array( 'registration_status' => 'withdrawn' ), array( 'id' => $pid ) );
                }
                $notice = count($selected) . ' player(s) marked as withdrawn.';
                break;

            case 'activate':
                foreach ( $selected as $pid ) {
                    $wpdb->update( "{$wpdb->prefix}was_players", array( 'registration_status' => 'active' ), array( 'id' => $pid ) );
                }
                $notice = count($selected) . ' player(s) activated.';
                break;

            case 'delete':
                foreach ( $selected as $pid ) {
                    // Delete photo file if exists
                    $p = WAS_Database::get_player( $pid );
                    if ( $p && $p->photo_path ) {
                        $photo = was_photo_dir() . basename( $p->photo_path );
                        if ( file_exists( $photo ) ) @unlink( $photo );
                    }
                    $wpdb->delete( "{$wpdb->prefix}was_players", array( 'id' => $pid ) );
                    $wpdb->delete( "{$wpdb->prefix}was_medical", array( 'player_id' => $pid ) );
                    $wpdb->delete( "{$wpdb->prefix}was_consents", array( 'player_id' => $pid ) );
                    $wpdb->delete( "{$wpdb->prefix}was_emergency_contacts", array( 'player_id' => $pid ) );
                    $wpdb->delete( "{$wpdb->prefix}was_payments", array( 'player_id' => $pid ) );
                }
                $notice = count($selected) . ' player record(s) permanently deleted.';
                break;

            case 'move_team':
                $new_team = absint( $_POST['move_to_team'] ?? 0 );
                if ( $new_team ) {
                    foreach ( $selected as $pid ) {
                        $wpdb->update( "{$wpdb->prefix}was_players", array( 'team_id' => $new_team ), array( 'id' => $pid ) );
                    }
                    $notice = count($selected) . ' player(s) moved to new team.';
                }
                break;
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$season    = was_season();
$team_id   = absint( $_GET['team']   ?? 0 );
$status_f  = sanitize_key( $_GET['status'] ?? '' );
$search    = sanitize_text_field( $_GET['s'] ?? '' );

$where  = array( 'p.season = %s' );
$params = array( $season );

if ( $team_id )  { $where[] = 'p.team_id = %d';             $params[] = $team_id; }
if ( $status_f ) { $where[] = 'p.registration_status = %s'; $params[] = $status_f; }
if ( $search ) {
    $like     = '%' . $wpdb->esc_like( $search ) . '%';
    $where[]  = '(p.first_name LIKE %s OR p.last_name LIKE %s OR g.email LIKE %s OR g.first_name LIKE %s OR g.last_name LIKE %s)';
    $params   = array_merge( $params, array( $like, $like, $like, $like, $like ) );
}

$where_sql = 'WHERE ' . implode( ' AND ', $where );

global $wpdb;
$players = $wpdb->get_results( $wpdb->prepare(
    "SELECT p.*,
            t.name AS team_name,
            g.first_name AS g_first, g.last_name AS g_last,
            g.email AS g_email, g.telephone AS g_tel,
            ( SELECT consent_value FROM {$wpdb->prefix}was_consents WHERE player_id=p.id AND consent_type='photography' LIMIT 1 ) AS photo_consent,
            ( SELECT consent_given FROM {$wpdb->prefix}was_medical WHERE player_id=p.id LIMIT 1 ) AS med_consent,
            ( SELECT status FROM {$wpdb->prefix}was_payments WHERE player_id=p.id ORDER BY charge_date DESC LIMIT 1 ) AS last_pay_status
     FROM {$wpdb->prefix}was_players p
     LEFT JOIN {$wpdb->prefix}was_teams t    ON p.team_id = t.id
     LEFT JOIN {$wpdb->prefix}was_guardians g ON p.primary_guardian_id = g.id
     {$where_sql}
     ORDER BY p.last_name ASC, p.first_name ASC",
    ...$params
) );

$teams = WAS_Database::get_teams( $season );
?>
<div class="wrap was-admin-wrap">
    <h1>Players &mdash; <?php echo esc_html( $season ); ?>
        <a href="<?php echo admin_url('admin.php?page=was-player-edit&player_id=new'); ?>" class="page-title-action">Add player</a>
    </h1>

    <?php if ( $notice ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
    <?php endif; ?>

    <form method="get" class="was-filter-bar">
        <input type="hidden" name="page" value="was-players">
        <select name="team">
            <option value="">All teams</option>
            <?php foreach ( $teams as $t ) : ?>
            <option value="<?php echo $t->id; ?>" <?php selected( $team_id, $t->id ); ?>><?php echo esc_html($t->name); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <option value="active"    <?php selected($status_f,'active'); ?>>Active</option>
            <option value="pending"   <?php selected($status_f,'pending'); ?>>Pending</option>
            <option value="inactive"  <?php selected($status_f,'inactive'); ?>>Inactive</option>
            <option value="withdrawn" <?php selected($status_f,'withdrawn'); ?>>Withdrawn</option>
        </select>
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name / email">
        <button type="submit" class="button">Filter</button>
    </form>

    <form method="post" id="was-players-form">
        <?php wp_nonce_field( 'was_bulk_action', 'was_bulk_nonce' ); ?>

        <div class="was-bulk-bar">
            <label><input type="checkbox" id="was-select-all"> Select all</label>
            <select name="bulk_action" id="was-bulk-action">
                <option value="">Bulk action&hellip;</option>
                <option value="activate">Activate</option>
                <option value="withdraw">Mark as withdrawn</option>
                <option value="move_team">Move to team</option>
                <option value="delete">Delete permanently (GDPR)</option>
            </select>
            <select name="move_to_team" id="was-move-team" style="display:none;">
                <option value="">Select team&hellip;</option>
                <?php foreach ( $teams as $t ) : ?>
                <option value="<?php echo $t->id; ?>"><?php echo esc_html($t->name); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button" id="was-bulk-apply">Apply</button>
            <span class="was-bulk-count" id="was-selected-count"></span>
        </div>

        <p><?php echo count($players); ?> player(s) found.</p>

        <table class="widefat striped was-players-table">
            <thead>
                <tr>
                    <th style="width:30px;"><input type="checkbox" id="was-check-all"></th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Team</th>
                    <th>Type</th>
                    <th>Payment</th>
                    <th>Monthly</th>
                    <th>Parent</th>
                    <th>Email / Tel</th>
                    <th>Photo</th>
                    <th>Med.</th>
                    <th>Status</th>
                    <th>Last pay</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $players ) ) : ?>
                <tr><td colspan="14">No players found.</td></tr>
            <?php else : ?>
                <?php foreach ( $players as $p ) :
                    $full_name = trim( $p->first_name . ' ' . $p->last_name ) ?: '(incomplete)';
                ?>
                <tr>
                    <td><input type="checkbox" name="player_ids[]" value="<?php echo $p->id; ?>" class="was-player-cb"></td>
                    <td><strong><?php echo esc_html($full_name); ?></strong></td>
                    <td><?php $ag = was_age( $p->dob ?? '' ); echo $ag !== '' ? esc_html($ag) : '<span style="color:#999;">&mdash;</span>'; ?></td>
                    <td>
                        <?php echo esc_html($p->team_name); ?>
                        <?php foreach ( WAS_Database::get_player_extra_teams( $p->id ) as $pe ) :
                            $ex_ov = ( $pe->amount_override !== null && $pe->amount_override !== '' );
                            $tag   = ( $pe->participation_type !== 'signed' ) ? ' (train)'
                                   : ( ( $ex_ov && (float) $pe->amount_override == 0.0 ) ? ' (exempt)' : '' );
                        ?>
                        <br><span style="color:#666;font-size:11px;">+ <?php echo esc_html( $pe->team_name . $tag ); ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><?php echo $p->participation_type === 'signed' ? 'Signed' : 'Train only'; ?></td>
                    <td><?php echo esc_html( ucwords( str_replace('_',' ',$p->payment_type) ) ); ?></td>
                    <td><?php $m = was_player_monthly_amount( $p ); echo $m > 0 ? '&pound;' . number_format( $m, 2 ) : '<span style="color:#999;">&mdash;</span>'; ?></td>
                    <td><?php echo esc_html( trim($p->g_first . ' ' . $p->g_last) ); ?></td>
                    <td>
                        <?php if($p->g_email): ?><a href="mailto:<?php echo esc_attr($p->g_email); ?>"><?php echo esc_html($p->g_email); ?></a><br><?php endif; ?>
                        <small><?php echo esc_html($p->g_tel); ?></small>
                    </td>
                    <td class="was-tc">
                        <?php if ( $p->photo_path ) : ?>
                            <span class="was-consent-tick">&#10003;</span>
                        <?php else : ?>
                            <span class="was-consent-cross">&#10007;</span>
                        <?php endif; ?>
                    </td>
                    <td class="was-tc">
                        <?php if ( $p->med_consent ) : ?>
                            <span class="was-consent-tick">&#10003;</span>
                        <?php elseif ( $p->med_consent === '0' ) : ?>
                            <span class="was-consent-cross">&#10007;</span>
                        <?php else : ?>
                            <span class="was-consent-grey">&mdash;</span>
                        <?php endif; ?>
                        <?php if ( $p->photo_consent === 'agree' ) : ?>
                            <span class="was-consent-tick" title="Photo consent">&#9783;</span>
                        <?php elseif ( $p->photo_consent === 'disagree' ) : ?>
                            <span class="was-consent-cross" title="No photo consent">&#9783;</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $status_labels[$p->registration_status] ?? esc_html($p->registration_status); ?></td>
                    <td>
                        <?php
                        $pay_cls = array('confirmed'=>'green','paid_out'=>'green','failed'=>'red','submitted'=>'blue','pending_submission'=>'yellow','cancelled'=>'grey');
                        if ( $p->last_pay_status ) {
                            $cls = $pay_cls[$p->last_pay_status] ?? 'grey';
                            echo '<span class="was-badge was-badge--' . $cls . '">' . esc_html(ucwords(str_replace('_',' ',$p->last_pay_status))) . '</span>';
                        } else {
                            echo '<span class="was-badge was-badge--grey">None</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=was-player-edit&player_id=' . $p->id) ); ?>" class="button button-small">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>
