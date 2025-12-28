<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Install {

	public static function activate(): void {

		self::create_videos_table();
		self::create_events_table();
		self::create_rollups_table();
		self::create_members_table();
		self::create_payouts_table();
		self::create_payout_resets_table();
		self::create_audit_table();

		if ( class_exists( '\\LuxVerified\\Settings' ) ) {
			Settings::maybe_seed_defaults();
		}

		if ( class_exists( '\\LuxVerified\\Analytics' ) ) {
			Analytics::schedule();
		}

		if ( class_exists( '\\LuxVerified\\Payouts' ) ) {
			Payouts::schedule();
		}

		// Default: auto-approve OFF
		if ( get_option( 'luxvv_auto_approve_after_w9', null ) === null ) {
			add_option( 'luxvv_auto_approve_after_w9', 0 );
		}
	}

	private static function create_videos_table(): void {

		global $wpdb;
		$table = $wpdb->prefix . 'lux_videos';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NULL,
			owner_user_id BIGINT(20) UNSIGNED NOT NULL,
			title VARCHAR(255) NULL,
			bunny_video_guid VARCHAR(191) NOT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'uploading',
			cdn_url VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY bunny_video_guid (bunny_video_guid),
			KEY owner_user_id (owner_user_id),
			KEY post_id (post_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private static function create_events_table(): void {

		global $wpdb;
		$table = $wpdb->prefix . 'lux_video_events';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			owner_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			viewer_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			session_id VARCHAR(64) NULL,
			post_id BIGINT(20) UNSIGNED NULL,
			bunny_video_guid VARCHAR(191) NOT NULL,
			event_type VARCHAR(32) NOT NULL,
			current_time INT NOT NULL DEFAULT 0,
			payload LONGTEXT NULL,
			ip_hash CHAR(64) NULL,
			user_agent VARCHAR(500) NULL,
			referrer VARCHAR(500) NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY owner_user_id (owner_user_id),
			KEY bunny_video_guid (bunny_video_guid),
			KEY session_id (session_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private static function create_rollups_table(): void {

		global $wpdb;
		$table = $wpdb->prefix . 'lux_video_rollups';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			day DATE NOT NULL,
			owner_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			post_id BIGINT(20) UNSIGNED NULL,
			bunny_video_guid VARCHAR(191) NOT NULL,
			impressions INT NOT NULL DEFAULT 0,
			plays INT NOT NULL DEFAULT 0,
			views_20s INT NOT NULL DEFAULT 0,
			completes INT NOT NULL DEFAULT 0,
			watch_seconds INT NOT NULL DEFAULT 0,
			p25 INT NOT NULL DEFAULT 0,
			p50 INT NOT NULL DEFAULT 0,
			p75 INT NOT NULL DEFAULT 0,
			p100 INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY day_guid (day, bunny_video_guid),
			KEY owner_user_id (owner_user_id),
			KEY post_id (post_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private static function create_members_table(): void {

		global $wpdb;
		$table = $wpdb->prefix . 'lux_verified_members';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			user_id BIGINT(20) UNSIGNED NOT NULL,
			verification_status VARCHAR(50) NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (user_id),
			KEY verification_status (verification_status),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private static function create_audit_table(): void {

		global $wpdb;
		$table = $wpdb->prefix . 'luxvv_audit';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			admin_user_id BIGINT(20) UNSIGNED NULL,
			target_user_id BIGINT(20) UNSIGNED NOT NULL,
			action VARCHAR(50) NOT NULL,
			meta LONGTEXT NULL,
			ip VARCHAR(45) NULL,
			PRIMARY KEY  (id),
			KEY target_user_id (target_user_id),
			KEY action (action),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private static function create_payouts_table(): void {

		global $wpdb;
		$table = $wpdb->prefix . 'lux_payouts';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			period_start DATE NOT NULL,
			period_end DATE NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			impressions INT NOT NULL DEFAULT 0,
			plays INT NOT NULL DEFAULT 0,
			views_20s INT NOT NULL DEFAULT 0,
			cpm_cents INT NOT NULL DEFAULT 0,
			payout_cents INT NOT NULL DEFAULT 0,
			ctr FLOAT NOT NULL DEFAULT 0,
			retention_rate FLOAT NOT NULL DEFAULT 0,
			bonus_pct FLOAT NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY period_user (period_start, period_end, user_id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private static function create_payout_resets_table(): void {

		global $wpdb;
		$table = $wpdb->prefix . 'lux_payout_resets';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			payout_id BIGINT(20) UNSIGNED NOT NULL,
			admin_user_id BIGINT(20) UNSIGNED NULL,
			reason TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY payout_id (payout_id),
			KEY admin_user_id (admin_user_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
