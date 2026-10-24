<?php defined( 'ABSPATH' ) || exit;
global $wpdb;
$table  = "{$wpdb->prefix}was_interest";
$notice = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_interest_nonce'] ) ) {
	check_admin_referer( 'was_save_interest', 'was_interest_nonce' );

	if ( isset( $_POST['delete_entry'] ) ) {
		$wpdb->delete( $table, array( 'id' => absint( $_POST['delete_entry'] ) ) );
		$notice = 'Entry deleted.';
	} elseif ( isset( $_POST['save_entry'] ) ) {
		$id     = absint( $_POST['save_entry'] );
		$status = sanitize_key( $_POST['entry_status'][ $id ] ?? 'new' );
		if ( ! array_key_exists( $status, WAS_Interest::statuses() ) ) $status = 'new';
		$wpdb->update( $table, array(
			'status'      => $status,
			'admin_notes' => sanitize_textarea_field( wp_unslash( $_POST['entry_notes'][ $id ] ?? '' ) ),
		), array( 'id' => $id ) );
		$notice = 'Entry updated.';
	}
}

$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY FIELD(status,'new','contacted','offered','placed','archived'), created_at DESC" );

$demand = $wpdb->get_results( "SELECT age_group, COUNT(*) c, SUM(play_up) up FROM {$table} WHERE status NOT IN ('placed','archived') GROUP BY age_group ORDER BY LENGTH(age_group), age_group" );
$counts = array( 'new' => 0, 'contacted' => 0, 'offered' => 0, 'placed' => 0, 'archived' => 0 );
foreach ( $rows as $r ) { if ( isset( $counts[ $r->status ] ) ) $counts[ $r->status ]++; }

$csv_url  = WAS_Interest::export_url( admin_url( 'admin.php?page=was-interest' ) );
$statuses = WAS_Interest::statuses();
?>
<div class="wrap">
	<h1>Interest List <a href="<?php echo esc_url( $csv_url ); ?>" class="page-title-action">Export CSV</a></h1>
	<p>Families waiting to play. Live demand below (excludes placed/archived) is your evidence for the council &amp; landowners — export the CSV for the full pack.</p>

	<?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>

	<h2 style="margin-bottom:6px;">Current demand by age group</h2>
	<p style="margin-top:0;">
		<?php if ( $demand ) : foreach ( $demand as $d ) : ?>
			<span style="display:inline-block;background:#14161a;color:#fff;border-radius:999px;padding:5px 14px;margin:0 6px 6px 0;font-weight:600;">
				<?php echo esc_html( $d->age_group ); ?> &times; <?php echo (int) $d->c; ?><?php if ( $d->up ) : ?> <span style="color:#E39A00;">(<?php echo (int) $d->up; ?> could play up)</span><?php endif; ?>
			</span>
		<?php endforeach; else : ?>
			<em>No waiting entries yet.</em>
		<?php endif; ?>
	</p>
	<p style="color:#666;">
		<?php echo (int) $counts['new']; ?> new &middot; <?php echo (int) $counts['contacted']; ?> contacted &middot; <?php echo (int) $counts['offered']; ?> offered &middot; <?php echo (int) $counts['placed']; ?> placed &middot; <?php echo (int) $counts['archived']; ?> archived
	</p>

	<form method="post">
		<?php wp_nonce_field( 'was_save_interest', 'was_interest_nonce' ); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>Received</th>
					<th>Player</th>
					<th>Contact</th>
					<th>Postcode</th>
					<th>Experience</th>
					<th>Disabilities / needs</th>
					<th style="min-width:200px;">Status &amp; notes</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="8"><em>Nothing here yet — entries from the Register Your Interest form will appear automatically.</em></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) : ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'j M Y', $r->created_at ) ); ?><br><small><?php echo esc_html( $r->season ); ?></small></td>
					<td>
						<strong><?php echo esc_html( trim( $r->player_first . ' ' . $r->player_last ) ); ?></strong><br>
						<span style="display:inline-block;background:#e8f0fe;color:#1a49a5;border-radius:999px;padding:1px 10px;font-weight:600;"><?php echo esc_html( $r->age_group ); ?></span>
						<?php if ( $r->play_up ) : ?><span style="display:inline-block;background:#fdf0d5;color:#8a5a00;border-radius:999px;padding:1px 10px;font-weight:600;">could play up</span><?php endif; ?>
						<br><small>DOB <?php echo esc_html( $r->dob ); ?></small>
					</td>
					<td>
						<?php echo esc_html( $r->parent_name ); ?><?php if ( $r->relationship ) : ?> <small>(<?php echo esc_html( $r->relationship ); ?>)</small><?php endif; ?><br>
						<a href="mailto:<?php echo esc_attr( $r->email ); ?>"><?php echo esc_html( $r->email ); ?></a><br>
						<?php echo esc_html( $r->phone ); ?>
					</td>
					<td><?php echo esc_html( $r->postcode ); ?></td>
					<td style="max-width:200px;"><?php echo esc_html( $r->experience ?: '—' ); ?></td>
					<td style="max-width:200px;"><?php echo esc_html( $r->disabilities ?: '—' ); ?></td>
					<td>
						<select name="entry_status[<?php echo (int) $r->id; ?>]">
							<?php foreach ( $statuses as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $r->status, $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<textarea name="entry_notes[<?php echo (int) $r->id; ?>]" rows="2" style="width:100%;margin-top:4px;" placeholder="Notes&hellip;"><?php echo esc_textarea( $r->admin_notes ); ?></textarea>
					</td>
					<td style="white-space:nowrap;">
						<button type="submit" name="save_entry" value="<?php echo (int) $r->id; ?>" class="button button-primary">Save</button>
						<button type="submit" name="delete_entry" value="<?php echo (int) $r->id; ?>" class="button" onclick="return confirm('Permanently delete this entry? This can\'t be undone.');">Delete</button>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</form>
</div>
