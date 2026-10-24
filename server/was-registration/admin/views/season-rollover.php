<?php defined( 'ABSPATH' ) || exit;
global $wpdb;

$current = was_season();
$notice  = '';
$error   = '';
$summary = null;

// Suggested next-season label (e.g. 2024-25 -> 2025-26)
$suggest = '';
if ( preg_match( '/^(\d{4})-(\d{2})$/', $current, $m ) ) {
    $suggest = sprintf( '%d-%02d', (int) $m[1] + 1, ( (int) $m[2] + 1 ) % 100 );
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_rollover_nonce'] ) ) {
    check_admin_referer( 'was_rollover', 'was_rollover_nonce' );
    $to_season = sanitize_text_field( $_POST['to_season'] ?? '' );
    $roll_ids  = array_map( 'absint', (array) ( $_POST['roll_team'] ?? array() ) );
    $confirm   = strtoupper( trim( sanitize_text_field( $_POST['confirm_text'] ?? '' ) ) );

    if ( empty( $to_season ) ) {
        $error = 'Please enter the new season label.';
    } elseif ( $to_season === $current ) {
        $error = 'The new season must be different from the current one.';
    } elseif ( $confirm !== 'ROLLOVER' ) {
        $error = 'Type ROLLOVER in the confirmation box to proceed.';
    } else {
        $summary = WAS_Database::rollover_season( $current, $to_season, $roll_ids );
        $current = was_season(); // now the new season
        $notice  = 'Season rolled over to ' . $current . '.';
    }
}

// Teams to display: current-season teams (before), or the new season's teams (after)
$teams = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}was_teams WHERE season = %s AND active = 1 ORDER BY age_band ASC, name ASC",
    $current
) );
?>
<div class="wrap was-admin-wrap">
    <h1>Season Rollover</h1>

    <?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
    <?php if ( $error )  : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

    <?php if ( $summary ) : ?>
        <div class="postbox"><div class="inside">
            <h2>Rollover complete</h2>
            <ul style="list-style:disc;margin-left:20px;">
                <li><strong><?php echo (int) $summary['teams_cloned']; ?></strong> teams cloned into <strong><?php echo esc_html( $current ); ?></strong> (ages advanced).</li>
                <li><strong><?php echo (int) $summary['subs_cancelled']; ?></strong> Direct Debit subscriptions cancelled<?php echo $summary['subs_failed'] ? ' (' . (int) $summary['subs_failed'] . ' failed — check GoCardless)' : ''; ?>.</li>
                <li><strong><?php echo (int) $summary['players_archived']; ?></strong> players archived (data retained).</li>
            </ul>
            <p>The current season is now <strong><?php echo esc_html( $current ); ?></strong>. Parents re-register for the new season; their mandate is retained.</p>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=was-teams' ) ); ?>" class="button button-primary">Review the new season's teams &rarr;</a></p>
        </div></div>
    <?php else : ?>

        <div class="notice notice-warning"><p>
            <strong>This ends the current season (<?php echo esc_html( $current ); ?>).</strong>
            Ticked teams are cloned into the new season with their age group advanced (e.g. U7 &rarr; U8).
            All of <?php echo esc_html( $current ); ?>'s Direct Debit subscriptions are cancelled and its players are archived.
            This cannot be undone &mdash; take a backup first.
        </p></div>

        <form method="post">
            <?php wp_nonce_field( 'was_rollover', 'was_rollover_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th><label for="to_season">New season label</label></th>
                    <td><input type="text" id="to_season" name="to_season" class="small-text" value="<?php echo esc_attr( $suggest ); ?>" placeholder="2026-27" required>
                        <p class="description">Current season: <strong><?php echo esc_html( $current ); ?></strong></p></td>
                </tr>
            </table>

            <h2>Teams to roll over</h2>
            <?php if ( empty( $teams ) ) : ?>
                <p><em>No active teams in the current season.</em></p>
            <?php else : ?>
            <table class="widefat striped" style="max-width:640px;">
                <thead><tr>
                    <th style="width:40px;"><input type="checkbox" id="was-roll-all" checked></th>
                    <th>Team</th><th>This season</th><th>Next season</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $teams as $t ) :
                    $next_label = ! empty( $t->age_band ) ? 'U' . ( (int) $t->age_band + 1 ) : ( $t->age_group ?: '—' );
                ?>
                    <tr>
                        <td><input type="checkbox" class="was-roll-cb" name="roll_team[]" value="<?php echo (int) $t->id; ?>" checked></td>
                        <td><strong><?php echo esc_html( $t->name ); ?></strong></td>
                        <td><?php echo esc_html( $t->age_group ?: '—' ); ?></td>
                        <td><strong><?php echo esc_html( $next_label ); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">Untick a team to <strong>fold</strong> it (e.g. the oldest age group leaving) &mdash; it will not be carried into the new season.</p>
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><label for="confirm_text">Confirm</label></th>
                    <td><input type="text" id="confirm_text" name="confirm_text" class="regular-text" placeholder="Type ROLLOVER" autocomplete="off">
                        <p class="description">Type <code>ROLLOVER</code> to confirm. This cancels subscriptions and archives players for <?php echo esc_html( $current ); ?>.</p></td>
                </tr>
            </table>

            <?php submit_button( 'Run season rollover', 'primary', 'submit', true, array( 'onclick' => "return confirm('Roll over the season now? This cancels current subscriptions and archives players.');" ) ); ?>
        </form>

        <script>
        document.getElementById('was-roll-all') && document.getElementById('was-roll-all').addEventListener('change', function(){
            document.querySelectorAll('.was-roll-cb').forEach(function(cb){ cb.checked = this.checked; }, this);
        });
        </script>

    <?php endif; ?>
</div>
