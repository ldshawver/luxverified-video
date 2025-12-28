<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public static function init(): void {

		// Enqueue admin assets only on our pages
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ __CLASS__, 'show_codex_notice' ] );
	}

	/* ============================================================
	 * ADMIN ASSETS
	 * ============================================================ */
	public static function enqueue_assets( string $hook ): void {

		if ( strpos( $hook, 'lux' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'luxvv-admin',
			LUXVV_URL . 'assets/admin.css',
			[],
			LUXVV_VERSION
		);
	}
		/* ============================================================
	 * VERIFICATION FUNNEL STATS
	 * ============================================================ */
public static function funnel_stats(): array {
    global $wpdb;

    return $wpdb->get_results(
        "
        SELECT verification_status, COUNT(*) as total
        FROM {$wpdb->prefix}lux_verified_members
        GROUP BY verification_status
        ",
        ARRAY_A
    );
}

	public static function show_codex_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p>Codex PR Test</p></div>';
	}

}
