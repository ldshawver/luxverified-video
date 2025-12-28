<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Install {

	public static function activate(): void {

		self::create_members_table();
		self::create_audit_table();

		// Default: auto-approve OFF
		if ( get_option( 'luxvv_auto_approve_after_w9', null ) === null ) {
			add_option( 'luxvv_auto_approve_after_w9', 0 );
		}
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
}
