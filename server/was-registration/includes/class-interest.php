<?php
/**
 * WAS_Interest — "Register your interest" waiting list.
 *
 * Public form ([was_interest_form]) for families who want to play but can't
 * yet be placed (teams full). Captures the player's details, works out their
 * FA age group from DOB, and alerts the club by email. Entries are managed
 * on ASTRO → Interest List in wp-admin, and on the portal's Interest List tab
 * for committee members, with CSV export for the pitch-demand case.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class WAS_Interest {

	public static function init() {
		add_shortcode( 'was_interest_form', array( __CLASS__, 'render_form' ) );
		add_action( 'wp_ajax_was_interest_submit',        array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_was_interest_submit', array( __CLASS__, 'ajax_submit' ) );

		// 'init' (not 'admin_init') so the export works from the portal as well as
		// wp-admin — it fires on both, so the wp-admin link keeps working unchanged.
		add_action( 'init', array( __CLASS__, 'maybe_export_csv' ) );

		// Portal (front-end) management of the waiting list.
		add_action( 'wp_ajax_was_interest_save',   array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_was_interest_delete', array( __CLASS__, 'ajax_delete' ) );
	}

	/**
	 * Who may see and manage the waiting list.
	 *
	 * The list holds children's names, dates of birth, home postcodes, contact
	 * details and disability information, so it stays with the full-access club
	 * roles: WP admins and the 'committee' role. To widen it, add roles here —
	 * this is the single gate used by the portal tab, the AJAX handlers and the
	 * CSV export. (was_has_role() already returns true for manage_options.)
	 */
	public static function can_manage() {
		return is_user_logged_in() && was_has_role( 'committee' );
	}

	/** Workflow statuses, in pipeline order. */
	public static function statuses() {
		return array(
			'new'       => 'New',
			'contacted' => 'Contacted',
			'offered'   => 'Offered place',
			'placed'    => 'Placed in a team',
			'archived'  => 'Archived',
		);
	}

	/** Nonced CSV URL. Defaults to the current page so it works on the portal too. */
	public static function export_url( $base = '' ) {
		$base = $base ?: remove_query_arg( array( 'was_interest_csv', '_wpnonce' ) );
		return wp_nonce_url( add_query_arg( 'was_interest_csv', 1, $base ), 'was_interest_csv' );
	}

	/**
	 * FA age group for the season: age at 23:59 on 31 August of the season's
	 * start year, plus one. Born 1 Sep 2018 – 31 Aug 2019 → U8 in 2026-27.
	 *
	 * @return array|null {code, label, band} — band is 'youth' | 'pre' | 'adult';
	 *                    null when the date is unusable.
	 */
	public static function age_group( $dob, $season = '' ) {
		$season = $season ?: was_season();
		$year   = (int) substr( $season, 0, 4 );
		$d      = DateTime::createFromFormat( 'Y-m-d', $dob );
		if ( ! $d || $d->format( 'Y-m-d' ) !== $dob ) return null;
		$cutoff = new DateTime( $year . '-08-31 23:59:59' );
		if ( $d > $cutoff || (int) $d->format( 'Y' ) < 1920 ) return null;

		$u = $d->diff( $cutoff )->y + 1;
		if ( $u >= 19 ) {
			return array( 'code' => 'Adult', 'label' => 'Adult football', 'band' => 'adult' );
		}
		if ( $u < 7 ) {
			return array( 'code' => 'U' . $u, 'label' => 'U' . $u . ' — below league age (perfect for our youngest offerings)', 'band' => 'pre' );
		}
		return array( 'code' => 'U' . $u, 'label' => 'U' . $u, 'band' => 'youth' );
	}

	public static function render_form() {
		ob_start();
		include WAS_PATH . 'templates/interest-form.php';
		return ob_get_clean();
	}

	public static function ajax_submit() {
		global $wpdb;

		if ( ! check_ajax_referer( 'was_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Your session expired (this can happen if the page sat open a while). Please refresh the page and try again — nothing has been lost.' ) );
		}

		// Honeypot — bots fill every field.
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'message' => 'Thank you!' ) );
		}

		// Gentle rate limit per IP.
		$ip_key = 'was_int_ip_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
		if ( get_transient( $ip_key ) ) {
			wp_send_json_error( array( 'message' => 'You\'ve just sent us a registration of interest — please wait a minute before sending another.' ) );
		}

		$parent_name  = sanitize_text_field( wp_unslash( $_POST['parent_name'] ?? '' ) );
		$relationship = sanitize_text_field( wp_unslash( $_POST['relationship'] ?? '' ) );
		$email        = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone        = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$player_first = sanitize_text_field( wp_unslash( $_POST['player_first'] ?? '' ) );
		$player_last  = sanitize_text_field( wp_unslash( $_POST['player_last'] ?? '' ) );
		$dob          = sanitize_text_field( wp_unslash( $_POST['dob'] ?? '' ) );
		$postcode     = strtoupper( sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) ) );
		$play_up      = ! empty( $_POST['play_up'] ) ? 1 : 0;
		$experience   = sanitize_textarea_field( wp_unslash( $_POST['experience'] ?? '' ) );
		$disabilities = sanitize_textarea_field( wp_unslash( $_POST['disabilities'] ?? '' ) );
		$consent      = ! empty( $_POST['consent'] );

		if ( ! $parent_name || ! $player_first || ! $player_last ) {
			wp_send_json_error( array( 'message' => 'Please fill in your name and the player\'s full name.' ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address so we can get back to you.' ) );
		}
		if ( ! $phone ) {
			wp_send_json_error( array( 'message' => 'Please enter a contact telephone number.' ) );
		}
		if ( ! $postcode ) {
			wp_send_json_error( array( 'message' => 'Please enter your postcode — it helps us make the case for more pitches locally.' ) );
		}
		if ( ! $consent ) {
			wp_send_json_error( array( 'message' => 'Please tick the consent box so we\'re allowed to keep your details on the waiting list.' ) );
		}

		$group = self::age_group( $dob );
		if ( ! $group ) {
			wp_send_json_error( array( 'message' => 'Please enter the player\'s date of birth.' ) );
		}

		$season = was_season();
		$ok = $wpdb->insert( "{$wpdb->prefix}was_interest", array(
			'created_at'   => current_time( 'mysql' ),
			'season'       => $season,
			'parent_name'  => $parent_name,
			'relationship' => $relationship,
			'email'        => $email,
			'phone'        => $phone,
			'player_first' => $player_first,
			'player_last'  => $player_last,
			'dob'          => $dob,
			'postcode'     => $postcode,
			'age_group'    => $group['code'],
			'play_up'      => $play_up,
			'experience'   => $experience,
			'disabilities' => $disabilities,
			'status'       => 'new',
		) );

		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => 'Sorry — something went wrong saving your details. Please try again, or contact the club directly.' ) );
		}

		set_transient( $ip_key, 1, MINUTE_IN_SECONDS );

		// Alert the club.
		$to    = get_option( 'was_admin_email' ) ?: get_option( 'admin_email' );
		$name  = trim( $player_first . ' ' . $player_last );
		$lines = array(
			'New registration of interest — ' . $name . ' (' . $group['code'] . ( $play_up ? ', could play a year up' : '' ) . ')',
			'',
			'Player:        ' . $name,
			'Date of birth: ' . $dob,
			'Age group ' . $season . ': ' . $group['code'],
			'Play a year up: ' . ( $play_up ? 'YES — parent says the player could potentially play up an age group' : 'no' ),
			'',
			'Contact:       ' . $parent_name . ( $relationship ? ' (' . $relationship . ')' : '' ),
			'Email:         ' . $email,
			'Phone:         ' . $phone,
			'Postcode:      ' . $postcode,
			'',
			'Previous experience: ' . ( $experience ?: '(none given)' ),
			'',
			'Disabilities / additional needs: ' . ( $disabilities ?: '(none given)' ),
			'',
			'Manage the waiting list: ' . admin_url( 'admin.php?page=was-interest' ),
		);
		WAS_Notifications::send_club_message(
			$to,
			'New interest: ' . $name . ' (' . $group['code'] . ')',
			implode( "\n", $lines ),
			$email
		);

		wp_send_json_success( array(
			'message'   => 'Thank you — ' . esc_html( $player_first ) . ' is on our list! We\'ll be in touch the moment a suitable place opens up.',
			'age_label' => $group['label'],
		) );
	}

	// ── Portal management (committee) ──────────────────────────────────────────

	/** Save one entry's status + notes from the portal's Interest List tab. */
	public static function ajax_save() {
		global $wpdb;
		check_ajax_referer( 'was_nonce', 'nonce' );
		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$id = absint( $_POST['entry_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Entry not found.' ) );
		}

		$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'new' ) );
		if ( ! array_key_exists( $status, self::statuses() ) ) $status = 'new';

		$ok = $wpdb->update(
			"{$wpdb->prefix}was_interest",
			array(
				'status'      => $status,
				'admin_notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			),
			array( 'id' => $id )
		);

		if ( false === $ok ) {
			wp_send_json_error( array( 'message' => 'Could not save that entry — please try again.' ) );
		}

		wp_send_json_success( array( 'message' => 'Saved.', 'status' => $status ) );
	}

	/** Delete one entry (also the route for a GDPR erasure request). */
	public static function ajax_delete() {
		global $wpdb;
		check_ajax_referer( 'was_nonce', 'nonce' );
		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$id = absint( $_POST['entry_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Entry not found.' ) );
		}

		$wpdb->delete( "{$wpdb->prefix}was_interest", array( 'id' => $id ) );
		wp_send_json_success( array( 'message' => 'Entry deleted.' ) );
	}

	/** CSV export of the interest list (wp-admin and the portal's Interest List tab). */
	public static function maybe_export_csv() {
		if ( empty( $_GET['was_interest_csv'] ) ) return;
		if ( ! self::can_manage() ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'was_interest_csv' ) ) return;

		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}was_interest ORDER BY created_at DESC", ARRAY_A );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=allstars-interest-list-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Date', 'Season', 'Player', 'DOB', 'Age group', 'Play up', 'Contact', 'Relationship', 'Email', 'Phone', 'Postcode', 'Experience', 'Disabilities / needs', 'Status' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array(
				$r['created_at'], $r['season'],
				trim( $r['player_first'] . ' ' . $r['player_last'] ),
				$r['dob'], $r['age_group'], $r['play_up'] ? 'yes' : 'no',
				$r['parent_name'], $r['relationship'], $r['email'], $r['phone'], $r['postcode'],
				$r['experience'], $r['disabilities'], $r['status'],
			) );
		}
		fclose( $out );
		exit;
	}
}
