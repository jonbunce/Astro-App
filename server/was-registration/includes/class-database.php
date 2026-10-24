<?php
defined( 'ABSPATH' ) || exit;

class WAS_Database {

    /**
     * Create all plugin tables on activation.
     * Uses dbDelta so it's also safe to call on plugin update.
     */
    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Note: TEXT columns are declared without a DEFAULT. MySQL refuses one, and dbDelta
        // would otherwise try (and fail) to "fix" the missing default on every update.

        // ── Teams ──────────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_teams (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            age_group varchar(20) NOT NULL DEFAULT '',
            age_band tinyint unsigned DEFAULT NULL,
            league varchar(150) NOT NULL DEFAULT '',
            description text DEFAULT NULL,
            season varchar(20) NOT NULL DEFAULT '2024-25',
            subs_amount decimal(8,2) NOT NULL DEFAULT 0.00,
            sibling_amount decimal(8,2) NOT NULL DEFAULT 0.00,
            second_team_amount decimal(8,2) NOT NULL DEFAULT 0.00,
            active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) $charset;" );

        // ── Guardians (parents / carers) ───────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_guardians (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) DEFAULT NULL,
            first_name varchar(100) NOT NULL DEFAULT '',
            last_name varchar(100) NOT NULL DEFAULT '',
            email varchar(200) NOT NULL DEFAULT '',
            telephone varchar(30) NOT NULL DEFAULT '',
            address_line1 varchar(200) NOT NULL DEFAULT '',
            postcode varchar(10) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY wp_user_id (wp_user_id)
        ) $charset;" );

        // ── Players ────────────────────────────────────────────────────────────
        // payment_type: gocardless | team_direct | sibling_rate | exempt
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_players (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL DEFAULT '',
            last_name varchar(100) NOT NULL DEFAULT '',
            dob date DEFAULT NULL,
            team_id mediumint(9) NOT NULL DEFAULT 0,
            primary_guardian_id mediumint(9) NOT NULL DEFAULT 0,
            participation_type varchar(20) NOT NULL DEFAULT 'signed',
            payment_type varchar(20) NOT NULL DEFAULT 'gocardless',
            subs_override decimal(8,2) DEFAULT NULL,
            season varchar(20) NOT NULL DEFAULT '2024-25',
            registration_status varchar(20) NOT NULL DEFAULT 'pending',
            gc_mandate_id varchar(100) NOT NULL DEFAULT '',
            gc_subscription_id varchar(100) NOT NULL DEFAULT '',
            gc_customer_id varchar(100) NOT NULL DEFAULT '',
            registration_token varchar(64) NOT NULL DEFAULT '',
            created_by_user bigint(20) unsigned NOT NULL DEFAULT 0,
            notes text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY team_id (team_id),
            KEY primary_guardian_id (primary_guardian_id),
            KEY registration_token (registration_token),
            KEY season (season)
        ) $charset;" );

        // ── Emergency contacts ─────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_emergency_contacts (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            player_id mediumint(9) NOT NULL,
            contact_name varchar(200) NOT NULL DEFAULT '',
            contact_telephone varchar(30) NOT NULL DEFAULT '',
            contact_occupation varchar(150) NOT NULL DEFAULT '',
            contact_employer varchar(200) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY player_id (player_id)
        ) $charset;" );

        // ── Medical data ───────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_medical (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            player_id mediumint(9) NOT NULL,
            consent_given tinyint(1) NOT NULL DEFAULT 0,
            consent_date datetime DEFAULT NULL,
            health_needs text NOT NULL,
            current_medication text NOT NULL,
            medical_training_needs text NOT NULL,
            access_needs text NOT NULL,
            communication_needs text NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY player_id (player_id)
        ) $charset;" );

        // ── Consents ───────────────────────────────────────────────────────────
        // consent_type: photography | legal_proceedings | medical_data | privacy_policy
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_consents (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            player_id mediumint(9) NOT NULL,
            consent_type varchar(50) NOT NULL,
            consent_value varchar(10) NOT NULL DEFAULT 'disagree',
            consent_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY player_id (player_id)
        ) $charset;" );

        // ── Payments ───────────────────────────────────────────────────────────
        // status mirrors GC payment lifecycle
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_payments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            player_id mediumint(9) NOT NULL,
            guardian_id mediumint(9) NOT NULL,
            gc_payment_id varchar(100) NOT NULL DEFAULT '',
            gc_mandate_id varchar(100) NOT NULL DEFAULT '',
            amount decimal(8,2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'GBP',
            charge_date date DEFAULT NULL,
            payment_date datetime DEFAULT NULL,
            status varchar(30) NOT NULL DEFAULT 'pending_submission',
            season varchar(20) NOT NULL DEFAULT '2024-25',
            description varchar(200) NOT NULL DEFAULT '',
            kind varchar(20) NOT NULL DEFAULT 'subscription',
            retry_count tinyint unsigned NOT NULL DEFAULT 0,
            retried_at datetime DEFAULT NULL,
            recovered_by varchar(100) NOT NULL DEFAULT '',
            failure_reason varchar(60) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY player_id (player_id),
            KEY gc_payment_id (gc_payment_id),
            KEY gc_mandate_id (gc_mandate_id),
            KEY status (status)
        ) $charset;" );

        // ── GoCardless webhook event log ───────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_gc_events (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            gc_event_id varchar(100) NOT NULL,
            event_action varchar(50) NOT NULL DEFAULT '',
            resource_type varchar(50) NOT NULL DEFAULT '',
            resource_id varchar(100) NOT NULL DEFAULT '',
            payload longtext NOT NULL,
            processed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY gc_event_id (gc_event_id)
        ) $charset;" );

        // v2.1: split first_name/last_name, add photo_path
        // Use SHOW COLUMNS to check existence before ALTER — works on all MySQL versions
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_players" );
        if ( ! in_array( 'last_name', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN last_name varchar(100) NOT NULL DEFAULT '' AFTER first_name" );
        }
        if ( ! in_array( 'photo_path', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN photo_path varchar(255) NOT NULL DEFAULT '' AFTER registration_token" );
        }

        // Coach team assignments
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_coach_teams (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            team_id mediumint(9) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_team (user_id, team_id),
            KEY user_id (user_id),
            KEY team_id (team_id)
        ) $charset;" );

        // v2.1.13: Schools table
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_schools (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY active (active)
        ) $charset;" );

        // v2.5.0: additional team memberships — a child's extra teams beyond their primary (was_players.team_id)
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_player_teams (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            player_id mediumint(9) NOT NULL,
            team_id mediumint(9) NOT NULL,
            participation_type varchar(20) NOT NULL DEFAULT 'signed',
            amount_override decimal(8,2) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY player_team (player_id, team_id),
            KEY player_id (player_id),
            KEY team_id (team_id)
        ) $charset;" );

        // v2.6.0: per-additional-team amount override (NULL = use team second-team rate; 0 = exempt/no charge)
        $ptcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_player_teams" );
        if ( ! in_array( 'amount_override', $ptcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_player_teams ADD COLUMN amount_override decimal(8,2) DEFAULT NULL AFTER participation_type" );
        }

        // v2.6.2: child date of birth
        $pdcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_players" );
        if ( ! in_array( 'dob', $pdcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN dob date DEFAULT NULL AFTER last_name" );
        }

        // v2.6.1: occupation + employer for the alternative (2nd) emergency contact
        $eccols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_emergency_contacts" );
        if ( ! in_array( 'contact_occupation', $eccols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_emergency_contacts ADD COLUMN contact_occupation varchar(150) NOT NULL DEFAULT '' AFTER contact_telephone" );
        }
        if ( ! in_array( 'contact_employer', $eccols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_emergency_contacts ADD COLUMN contact_employer varchar(200) NOT NULL DEFAULT '' AFTER contact_occupation" );
        }

        // v2.1.13: Add occupation + employer to guardians
        $gcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_guardians" );
        if ( ! in_array( 'occupation_category', $gcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_guardians ADD COLUMN occupation_category varchar(100) NOT NULL DEFAULT '' AFTER postcode" );
        }
        if ( ! in_array( 'employer_name', $gcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_guardians ADD COLUMN employer_name varchar(200) NOT NULL DEFAULT '' AFTER occupation_category" );
        }

        // v2.1.13: Add school_id + school_other to players
        $pcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_players" );
        if ( ! in_array( 'school_id', $pcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN school_id mediumint(9) NOT NULL DEFAULT 0 AFTER last_name" );
        }
        if ( ! in_array( 'school_other', $pcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN school_other varchar(200) NOT NULL DEFAULT '' AFTER school_id" );
        }

        // v2.2: Add logo_path to teams
        $tcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_teams" );
        if ( ! in_array( 'logo_path', $tcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_teams ADD COLUMN logo_path varchar(255) NOT NULL DEFAULT '' AFTER age_group" );
        }

        // v2.4.0: per-player custom monthly subscription override (NULL = use team/sibling rate)
        $scols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_players" );
        if ( ! in_array( 'subs_override', $scols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN subs_override decimal(8,2) DEFAULT NULL AFTER payment_type" );
        }

        // v2.4.2: per-team second-team supplement (added to a child's sub for each extra team)
        $t2cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_teams" );
        if ( ! in_array( 'second_team_amount', $t2cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_teams ADD COLUMN second_team_amount decimal(8,2) NOT NULL DEFAULT 0.00 AFTER sibling_amount" );
        }

        // v2.4.3: structured age band (number) so age groups auto-advance on rollover.
        // dbDelta may already have added the column from the CREATE TABLE above, so the
        // backfill must NOT be gated on column-absence — it fills any team still missing one.
        $abcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_teams" );
        if ( ! in_array( 'age_band', $abcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_teams ADD COLUMN age_band tinyint unsigned DEFAULT NULL AFTER age_group" );
        }
        // Backfill age_band from the age_group label (e.g. "U7" -> 7) for any team without one
        $age_rows = $wpdb->get_results( "SELECT id, age_group FROM {$wpdb->prefix}was_teams WHERE age_band IS NULL AND age_group <> ''" );
        foreach ( $age_rows as $ar ) {
            if ( preg_match( '/(\d+)/', (string) $ar->age_group, $m ) ) {
                $wpdb->update( "{$wpdb->prefix}was_teams", array( 'age_band' => (int) $m[1] ), array( 'id' => $ar->id ) );
            }
        }

        // v2.7.0: team profile fields (league + description) for the public Team Profiles pages
        $tpcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_teams" );
        if ( ! in_array( 'league', $tpcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_teams ADD COLUMN league varchar(150) NOT NULL DEFAULT '' AFTER age_band" );
        }
        if ( ! in_array( 'description', $tpcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_teams ADD COLUMN description text DEFAULT NULL AFTER league" );
        }

        // v2.8.0: extra optional team-profile fields (render only when filled)
        $tp2 = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_teams" );
        foreach ( array(
            'training_info' => "varchar(255) NOT NULL DEFAULT ''",
            'coach_names'   => "varchar(200) NOT NULL DEFAULT ''",
            'coach_contact' => "varchar(200) NOT NULL DEFAULT ''",
            'honours'       => 'text DEFAULT NULL',
            'sponsor_line'  => "varchar(255) NOT NULL DEFAULT ''",
        ) as $col => $ddl ) {
            if ( ! in_array( $col, $tp2, true ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_teams ADD COLUMN $col $ddl" );
            }
        }

        // v2.8.0: club messages log (Messages portal tab)
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sender_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            audiences varchar(100) NOT NULL DEFAULT '',
            team_id mediumint(9) DEFAULT NULL,
            subject varchar(200) NOT NULL DEFAULT '',
            body text,
            recipients int unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'sent',
            from_mode varchar(10) NOT NULL DEFAULT 'me',
            reply_to varchar(190) NOT NULL DEFAULT '',
            send_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            sent_count int unsigned NOT NULL DEFAULT 0,
            recipient_list longtext,
            run_key varchar(40) NOT NULL DEFAULT '',
            lock_until int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset;" );

        // v2.8.1: per-player monthly supplement (e.g. JPL league supplement)
        $spcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_players" );
        if ( ! in_array( 'supplement_amount', $spcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN supplement_amount decimal(8,2) NOT NULL DEFAULT 0.00" );
        }
        if ( ! in_array( 'supplement_label', $spcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN supplement_label varchar(100) NOT NULL DEFAULT ''" );
        }

        // v2.9.0: register-your-interest waiting list
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_interest (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            season varchar(20) NOT NULL DEFAULT '',
            parent_name varchar(120) NOT NULL DEFAULT '',
            relationship varchar(40) NOT NULL DEFAULT '',
            email varchar(120) NOT NULL DEFAULT '',
            phone varchar(40) NOT NULL DEFAULT '',
            player_first varchar(60) NOT NULL DEFAULT '',
            player_last varchar(60) NOT NULL DEFAULT '',
            dob date DEFAULT NULL,
            postcode varchar(12) NOT NULL DEFAULT '',
            age_group varchar(12) NOT NULL DEFAULT '',
            play_up tinyint(1) NOT NULL DEFAULT 0,
            experience text,
            disabilities text,
            status varchar(20) NOT NULL DEFAULT 'new',
            admin_notes text,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY age_group (age_group)
        ) $charset;" );

        // v2.10.0: arrears handling — one automatic retry per failed collection,
        // catch-up (one-off) payments that cover failed rows, and payment holds.
        $arcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_payments" );
        foreach ( array(
            'kind'           => "varchar(20) NOT NULL DEFAULT 'subscription'",   // subscription | catchup
            'retry_count'    => 'tinyint unsigned NOT NULL DEFAULT 0',
            'retried_at'     => 'datetime DEFAULT NULL',
            'recovered_by'   => "varchar(100) NOT NULL DEFAULT ''",              // gc id of the catch-up covering this failed row, or 'manual'
            'failure_reason' => "varchar(60) NOT NULL DEFAULT ''",               // GoCardless cause, e.g. insufficient_funds
        ) as $col => $ddl ) {
            if ( ! in_array( $col, $arcols, true ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_payments ADD COLUMN $col $ddl" );
            }
        }
        $phcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_players" );
        if ( ! in_array( 'payment_hold_since', $phcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN payment_hold_since datetime DEFAULT NULL" );
        }
        if ( ! in_array( 'payment_hold_reason', $phcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN payment_hold_reason varchar(60) NOT NULL DEFAULT ''" );
        }

        // v2.11.0: manual payment-day override (day of month 1-28; NULL = the 1st)
        if ( ! in_array( 'payment_day', $phcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN payment_day tinyint unsigned DEFAULT NULL" );
        }

        // v2.12.0: pause automatic payment emails to a family (hardship / manual arrangements)
        if ( ! in_array( 'payment_emails_paused', $phcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN payment_emails_paused tinyint(1) NOT NULL DEFAULT 0" );
        }

        // v2.13.0: each payment failure is handled once (webhook vs nightly sync race)
        $fhcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_payments" );
        if ( ! in_array( 'failure_handled_at', $fhcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_payments ADD COLUMN failure_handled_at datetime DEFAULT NULL" );
            // Failures already on file have been dealt with (automatically or manually).
            $wpdb->query( "UPDATE {$wpdb->prefix}was_payments SET failure_handled_at = NOW() WHERE status = 'failed'" );
        }

        // v2.14.0: scheduled club messages + send-as-club. Rows logged before this were sent immediately.
        $wpdb->query( "UPDATE {$wpdb->prefix}was_messages SET sent_at = created_at WHERE sent_at IS NULL AND status = 'sent'" );

        // v2.15.0: the account that started a pending registration — only it may complete it
        $cbcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}was_players" );
        if ( ! in_array( 'created_by_user', $cbcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}was_players ADD COLUMN created_by_user bigint(20) unsigned NOT NULL DEFAULT 0 AFTER registration_token" );
        }

        // v2.16.0: the app inbox — one row per member per club message / team news item (WAS_Inbox)
        dbDelta( "CREATE TABLE {$wpdb->prefix}was_inbox (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            type varchar(30) NOT NULL DEFAULT '',
            title varchar(255) NOT NULL DEFAULT '',
            body text,
            data longtext,
            ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            read_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_created (user_id,created_at)
        ) $charset;" );

        update_option( 'was_db_version', WAS_DB_VERSION );
        // Clear any cached version mismatch
        delete_option( 'was_db_needs_upgrade' );
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Teams
    // ───────────────────────────────────────────────────────────────────────────

    public static function get_teams( $season = null ) {
        global $wpdb;
        $season = $season ?: was_season();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_teams
             WHERE season = %s AND active = 1
             ORDER BY name ASC",
            $season
        ) );
    }

    public static function get_team( $team_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_teams WHERE id = %d",
            $team_id
        ) );
    }

    /**
     * Season rollover. Clones the chosen teams into the new season with the age band
     * advanced (U7 -> U8), cancels the closing season's GoCardless subscriptions,
     * archives that season's players (data retained, hidden from portals), and sets
     * the current season to the new one. Players RE-REGISTER for the new season.
     * Returns counts. Mandates are left intact for possible reuse on re-registration.
     */
    public static function rollover_season( $from_season, $to_season, $roll_team_ids ) {
        global $wpdb;
        @set_time_limit( 0 );
        $out = array( 'teams_cloned' => 0, 'subs_cancelled' => 0, 'subs_failed' => 0, 'players_archived' => 0 );

        // 1. Clone selected teams into the new season, advancing the age band.
        foreach ( array_map( 'absint', (array) $roll_team_ids ) as $tid ) {
            $t = self::get_team( $tid );
            if ( ! $t || $t->season !== $from_season ) continue;
            $new_band  = ! empty( $t->age_band ) ? ( (int) $t->age_band + 1 ) : null;
            $new_label = $new_band ? 'U' . $new_band : $t->age_group;
            $wpdb->insert( "{$wpdb->prefix}was_teams", array(
                'name'               => $t->name,
                'age_group'          => $new_label,
                'age_band'           => $new_band,
                'league'             => $t->league ?? '',
                'description'        => $t->description ?? null,
                'season'             => $to_season,
                'subs_amount'        => $t->subs_amount,
                'sibling_amount'     => $t->sibling_amount,
                'second_team_amount' => $t->second_team_amount,
                'logo_path'          => $t->logo_path,
                'active'             => 1,
            ) );
            $out['teams_cloned']++;
        }

        // 2. Cancel the closing season's active GoCardless subscriptions (mandate kept).
        $subbed = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, gc_subscription_id FROM {$wpdb->prefix}was_players
             WHERE season = %s AND gc_subscription_id <> ''",
            $from_season
        ) );
        foreach ( $subbed as $p ) {
            if ( WAS_GoCardless::cancel_subscription( $p->gc_subscription_id ) ) {
                $out['subs_cancelled']++;
            } else {
                $out['subs_failed']++;
            }
        }

        // 3. Archive the closing season's players (retain data; hide from active views).
        $out['players_archived'] = (int) $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}was_players SET registration_status = 'archived'
             WHERE season = %s AND registration_status NOT IN ( 'withdrawn', 'archived' )",
            $from_season
        ) );

        // 4. Advance the current season.
        update_option( 'was_current_season', $to_season );

        return $out;
    }

    /**
     * Recompute a player's combined monthly amount and apply it to their Direct Debit.
     * Returns true on success or no-op (override set / not billable), false on GC error.
     */
    public static function apply_player_subscription( $player_id, $notify = true ) {
        global $wpdb;
        $player = self::get_player( $player_id );
        if ( ! $player ) return true;
        // A manual custom override means the admin controls the amount — leave the DD alone.
        if ( $player->subs_override !== null && $player->subs_override !== '' ) return true;
        $billable = ( $player->participation_type === 'signed' && $player->registration_status === 'active' && ! empty( $player->gc_mandate_id ) );
        if ( ! $billable ) return true;
        $res = WAS_GoCardless::set_player_subscription( $player, (int) round( was_player_monthly_amount( $player ) * 100 ), $notify );
        if ( empty( $res['ok'] ) ) return false;
        $wpdb->update( "{$wpdb->prefix}was_players", array( 'gc_subscription_id' => $res['sub_id'] ), array( 'id' => $player_id ) );
        return true;
    }

    /**
     * Withdraw a team mid-season: deactivate it and handle its players.
     *  - Players who have it as an ADDITIONAL team: drop that membership, recompute DD (lower).
     *  - Players whose PRIMARY team it is: promote a remaining additional team to primary if they
     *    have one (recompute), else cancel their DD and set them inactive.
     */
    public static function withdraw_team( $team_id ) {
        global $wpdb;
        @set_time_limit( 0 );
        $out  = array( 'ok' => true, 'extras_removed' => 0, 'promoted' => 0, 'deactivated' => 0, 'sub_errors' => 0 );
        $team = self::get_team( $team_id );
        if ( ! $team ) { $out['ok'] = false; return $out; }

        // 1. Additional-membership players: remove the membership, recompute their DD downward.
        $extras = $wpdb->get_results( $wpdb->prepare(
            "SELECT id AS membership_id, player_id FROM {$wpdb->prefix}was_player_teams WHERE team_id = %d",
            $team_id
        ) );
        foreach ( $extras as $row ) {
            $wpdb->delete( "{$wpdb->prefix}was_player_teams", array( 'id' => $row->membership_id ), array( '%d' ) );
            if ( ! self::apply_player_subscription( $row->player_id ) ) $out['sub_errors']++;
            $out['extras_removed']++;
        }

        // 2. Primary-team players.
        $primary = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_players
             WHERE team_id = %d AND registration_status NOT IN ( 'withdrawn', 'archived' )",
            $team_id
        ) );
        foreach ( $primary as $p ) {
            $promote = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}was_player_teams WHERE player_id = %d ORDER BY id ASC LIMIT 1",
                $p->id
            ) );
            if ( $promote ) {
                // Move a remaining team up to primary so the child keeps playing.
                $wpdb->update( "{$wpdb->prefix}was_players",
                    array( 'team_id' => $promote->team_id, 'participation_type' => $promote->participation_type ),
                    array( 'id' => $p->id ) );
                $wpdb->delete( "{$wpdb->prefix}was_player_teams", array( 'id' => $promote->id ), array( '%d' ) );
                if ( ! self::apply_player_subscription( $p->id ) ) $out['sub_errors']++;
                $out['promoted']++;
            } else {
                // No other team — cancel the Direct Debit and deactivate the player.
                $res = WAS_GoCardless::set_player_subscription( $p, 0 );
                if ( empty( $res['ok'] ) ) $out['sub_errors']++;
                $wpdb->update( "{$wpdb->prefix}was_players",
                    array( 'registration_status' => 'inactive', 'gc_subscription_id' => '' ),
                    array( 'id' => $p->id ) );
                $out['deactivated']++;
            }
        }

        // 3. Deactivate the team.
        $wpdb->update( "{$wpdb->prefix}was_teams", array( 'active' => 0 ), array( 'id' => $team_id ) );

        return $out;
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Guardians
    // ───────────────────────────────────────────────────────────────────────────

    public static function get_guardian_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_guardians WHERE email = %s",
            sanitize_email( $email )
        ) );
    }

    public static function get_guardian( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_guardians WHERE id = %d",
            $id
        ) );
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Players
    // ───────────────────────────────────────────────────────────────────────────

    public static function get_player( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_players WHERE id = %d",
            $id
        ) );
    }

    public static function get_player_by_token( $token ) {
        global $wpdb;
        if ( empty( $token ) ) {
            return null;
        }
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_players WHERE registration_token = %s",
            sanitize_text_field( $token )
        ) );
    }

    public static function get_player_by_mandate( $mandate_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_players WHERE gc_mandate_id = %s",
            $mandate_id
        ) );
    }

    public static function get_players_by_team( $team_id, $season = null ) {
        global $wpdb;
        $season = $season ?: was_season();
        // Includes children whose PRIMARY team is this one, plus children who have
        // this team as an ADDITIONAL membership (was_player_teams) — so a coach of
        // an extra team also sees the child.
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT p.*,
                    g.first_name AS g_first, g.last_name AS g_last,
                    g.email AS g_email, g.telephone AS g_tel
             FROM {$wpdb->prefix}was_players p
             LEFT JOIN {$wpdb->prefix}was_guardians g ON p.primary_guardian_id = g.id
             LEFT JOIN {$wpdb->prefix}was_player_teams pt ON pt.player_id = p.id AND pt.team_id = %d
             WHERE p.season = %s
               AND ( p.team_id = %d OR pt.id IS NOT NULL )
               AND p.registration_status NOT IN ( 'pending', 'withdrawn', 'archived' )
             ORDER BY p.last_name ASC, p.first_name ASC",
            $team_id, $season, $team_id
        ) );
    }

    /**
     * A child's ADDITIONAL team memberships (beyond their primary team), with team info.
     */
    public static function get_player_extra_teams( $player_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT pt.*, t.name AS team_name, t.age_group, t.subs_amount, t.second_team_amount
             FROM {$wpdb->prefix}was_player_teams pt
             LEFT JOIN {$wpdb->prefix}was_teams t ON pt.team_id = t.id
             WHERE pt.player_id = %d
             ORDER BY t.name ASC",
            $player_id
        ) );
    }

    /**
     * All active players under the same guardian (used to detect siblings).
     *
     * By default every registration this season except pending and withdrawn ones (the
     * parent portal's list). Pass $statuses (e.g. array( 'pending', 'active' )) to get
     * only registrations with those statuses instead.
     */
    public static function get_players_by_guardian( $guardian_id, $season = null, $statuses = null ) {
        global $wpdb;
        $season = $season ?: was_season();
        if ( is_array( $statuses ) ) {
            $statuses = array_values( array_filter( array_map( 'sanitize_key', $statuses ) ) );
            if ( ! $statuses ) return array();
            $in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT p.*, t.name AS team_name, t.logo_path AS team_logo
                 FROM {$wpdb->prefix}was_players p
                 LEFT JOIN {$wpdb->prefix}was_teams t ON p.team_id = t.id
                 WHERE p.primary_guardian_id = %d
                   AND p.season = %s
                   AND p.registration_status IN ( $in )
                 ORDER BY p.id ASC",
                array_merge( array( (int) $guardian_id, $season ), $statuses )
            ) );
        }
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, t.name AS team_name, t.logo_path AS team_logo
             FROM {$wpdb->prefix}was_players p
             LEFT JOIN {$wpdb->prefix}was_teams t ON p.team_id = t.id
             WHERE p.primary_guardian_id = %d
               AND p.season = %s
               AND p.registration_status NOT IN ( 'withdrawn', 'pending' )",
            $guardian_id, $season
        ) );
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Payments
    // ───────────────────────────────────────────────────────────────────────────

    public static function get_player_payments( $player_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_payments
             WHERE player_id = %d
             ORDER BY charge_date DESC, created_at DESC",
            $player_id
        ) );
    }

    public static function upsert_payment( $gc_payment_id, $player_id, $guardian_id, $mandate_id, $amount, $charge_date, $status, $season, $description = '' ) {
        global $wpdb;

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}was_payments WHERE gc_payment_id = %s",
            $gc_payment_id
        ) );

        $payment_date = ( $status === 'confirmed' || $status === 'paid_out' ) ? current_time( 'mysql' ) : null;

        if ( $existing ) {
            $wpdb->update(
                "{$wpdb->prefix}was_payments",
                array(
                    'status'       => $status,
                    'payment_date' => $payment_date,
                ),
                array( 'gc_payment_id' => $gc_payment_id ),
                array( '%s', '%s' ),
                array( '%s' )
            );
        } else {
            $wpdb->insert(
                "{$wpdb->prefix}was_payments",
                array(
                    'player_id'    => $player_id,
                    'guardian_id'  => $guardian_id,
                    'gc_payment_id'=> $gc_payment_id,
                    'gc_mandate_id'=> $mandate_id,
                    'amount'       => $amount,
                    'currency'     => 'GBP',
                    'charge_date'  => $charge_date,
                    'payment_date' => $payment_date,
                    'status'       => $status,
                    'season'       => $season,
                    'description'  => $description,
                ),
                array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
            );
        }
    }

    public static function update_payment_status( $gc_payment_id, $status ) {
        global $wpdb;
        $payment_date = in_array( $status, array( 'confirmed', 'paid_out' ), true )
            ? current_time( 'mysql' )
            : null;

        $wpdb->update(
            "{$wpdb->prefix}was_payments",
            array( 'status' => $status, 'payment_date' => $payment_date ),
            array( 'gc_payment_id' => $gc_payment_id ),
            array( '%s', '%s' ),
            array( '%s' )
        );
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Medical / Consents
    // ───────────────────────────────────────────────────────────────────────────

    public static function get_medical( $player_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_medical WHERE player_id = %d",
            $player_id
        ) );
    }

    public static function get_consents( $player_id ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT consent_type, consent_value FROM {$wpdb->prefix}was_consents WHERE player_id = %d",
            $player_id
        ) );
        $out = array();
        foreach ( $rows as $r ) {
            $out[ $r->consent_type ] = $r->consent_value;
        }
        return $out;
    }
    // ── Schools ────────────────────────────────────────────────────────────────

    public static function get_schools( $active_only = true ) {
        global $wpdb;
        $where = $active_only ? 'WHERE active = 1' : '';
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}was_schools {$where} ORDER BY sort_order ASC, name ASC"
        );
    }

    public static function get_school( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}was_schools WHERE id = %d",
            $id
        ) );
    }

    public static function seed_schools() {
        global $wpdb;
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}was_schools" );
        if ( $count > 0 ) return; // Already seeded

        // Close-Wombourne schools pinned to the top (sort 1-6), everything else
        // alphabetical (10+), "Home educated" pinned last (900). The registration form's
        // "My child's school isn't listed" option is rendered after this list, so it
        // always sits at the very bottom of the dropdown.
        $schools = array(
            // Pinned — closest Wombourne schools
            array( 'Blakeley Heath Primary School', 1 ),
            array( 'Westfield Primary School', 2 ),
            array( 'St Benedict Biscop CofE Primary School', 3 ),
            array( "St Bernadette's Catholic Primary School", 4 ),
            array( "St John's CofE Primary School", 5 ),
            array( 'Wombourne High School', 6 ),
            // The rest, alphabetical
            array( 'Alder Coppice Primary School', 10 ),
            array( "All Saints' CofE Primary School", 20 ),
            array( 'Bhylls Acre Primary School', 30 ),
            array( 'Blanford Mere Primary School', 40 ),
            array( 'Castlecroft Primary School', 50 ),
            array( 'Cherry Trees School', 60 ),
            array( 'Church of the Ascension CofE Primary School', 70 ),
            array( 'Crestwood High School', 80 ),
            array( 'Dawley Brook Primary School', 90 ),
            array( 'Highfields School', 100 ),
            array( 'Kingswinford Academy', 110 ),
            array( "Kingswinford and Wall Heath Children's Centre", 120 ),
            array( 'Kinver High School', 130 ),
            array( 'Maidensbridge Primary School', 140 ),
            array( 'Penn Fields School', 150 ),
            array( 'Penn Hall School', 160 ),
            array( 'Springdale Primary School', 170 ),
            array( 'St Bartholomews Church of England Primary School', 180 ),
            array( "St Mary's CofE Primary School", 190 ),
            array( "St Michael's Catholic Primary Academy and Nursery", 200 ),
            array( 'St Regis Church of England Academy', 210 ),
            array( 'Straits Primary School', 220 ),
            array( 'Summerhill School', 230 ),
            array( "The Crescent Children's Centre", 240 ),
            array( "The Star Children's Centre", 250 ),
            array( 'The Wordsley School', 260 ),
            array( 'Warstones Primary School', 270 ),
            array( 'Home educated', 900 ),
        );

        foreach ( $schools as $s ) {
            $wpdb->insert(
                "{$wpdb->prefix}was_schools",
                array( 'name' => $s[0], 'sort_order' => $s[1], 'active' => 1 ),
                array( '%s', '%d', '%d' )
            );
        }
    }
}