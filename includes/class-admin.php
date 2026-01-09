<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public static function init(): void {

		// Enqueue admin assets only on our pages
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		// Temporary admin notice (can be removed after verification)
		add_action( 'admin_notices', [ __CLASS__, 'render_admin_notice' ] );
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
	 * ADMIN NOTICES
	 * ============================================================ */
	public static function render_admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( 'luxvv_fpdi_missing' ) ) {
			?>
			<div class="notice notice-error">
				<p><?php echo esc_html__( 'LUX Verified Video: FPDI/TCPDF library missing. Run Composer install to enable W-9 PDF generation.', 'lux-verified-video' ); ?></p>
			</div>
			<?php
		}
		?>
		<div class="notice notice-info">
			<p><?php echo esc_html__( 'LUX Verified Video — Deploy Confirmed', 'lux-verified-video' ); ?></p>
		</div>
		<?php
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

}
