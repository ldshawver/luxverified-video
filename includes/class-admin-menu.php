<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Admin_Menu {

	const SLUG = 'luxvv';

	public static function register(): void {

		add_menu_page(
			'LUX Verified',
			'LUX Verified',
			'manage_options',
			self::SLUG,
			[ __CLASS__, 'render_dashboard' ],
			'dashicons-shield-alt',
			58
		);

		add_submenu_page(
			self::SLUG,
			'Verification Requests',
			'Requests',
			'manage_options',
			'luxvv-requests',
			[ __CLASS__, 'render_requests' ]
		);
	}

	public static function render_dashboard(): void {
		echo '<div class="wrap"><h1>LUX Verified</h1></div>';
	}

	public static function render_requests(): void {

		global $wpdb;

		$table = $wpdb->prefix . 'lux_verified_members';

		$requests = $wpdb->get_results(
			"
			SELECT 
				u.ID as user_id,
				u.user_login,
				u.user_email,
				m.verification_status,
				m.created_at,
				m.updated_at
			FROM {$wpdb->users} u
			LEFT JOIN {$table} m ON m.user_id = u.ID
			WHERE u.ID IN (
				SELECT DISTINCT user_id
				FROM {$wpdb->usermeta}
				WHERE meta_key LIKE 'luxvv_%'
			)
			ORDER BY m.updated_at DESC
			",
			ARRAY_A
		);

		require LUXVV_DIR . 'includes/views/requests.php';
	}
}
