<?php
/**
 * Plugin Name: LUX Verified Video
 * Description: Verified creator video platform with gated uploads, W-9 compliance, analytics, and AI control.
 * Version: 3.5.4
 * Author: Lucifer Cruz Studios
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 * CONSTANTS
 * ============================================================ */
define( 'LUXVV_VERSION', '3.5.4' );
define( 'LUXVV_FILE', __FILE__ );
define( 'LUXVV_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXVV_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXVV_VERIFIED_META', 'luxvv_verified' );

/* ============================================================
 * SAFE REQUIRE
 * ============================================================ */
if ( ! function_exists( 'luxvv_require' ) ) {
	function luxvv_require( string $file ): void {
		$path = LUXVV_DIR . ltrim( $file, '/\\' );
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}

/* ============================================================
 * AUTOLOAD (FPDI / TCPDF)
 * ============================================================ */
if ( file_exists( LUXVV_DIR . 'vendor/autoload.php' ) ) {
	require_once LUXVV_DIR . 'vendor/autoload.php';
}

/* ============================================================
 * LOAD CORE CLASSES
 * ============================================================ */
luxvv_require( 'includes/class-install.php' );
luxvv_require( 'includes/class-settings.php' );
luxvv_require( 'includes/helpers.php' );
luxvv_require( 'includes/class-plugin.php' );
luxvv_require( 'includes/class-analytics.php' );
luxvv_require( 'includes/class-ajax.php' );
luxvv_require( 'includes/class-payouts.php' );
luxvv_require( 'includes/class-ai.php' );
luxvv_require( 'includes/class-rest-ai.php' );
luxvv_require( 'includes/class-verification.php' );
luxvv_require( 'includes/class-admin-menu.php' );
luxvv_require( 'includes/class-admin.php' );
luxvv_require( 'includes/class-pdf.php' );
luxvv_require( 'includes/class-pdf-controller.php' );
luxvv_require( 'includes/class-review.php' );
luxvv_require( 'includes/class-admin-actions.php' );
luxvv_require( 'includes/class-repair.php' );

/* ============================================================
 * ADMIN CSS (Requests page only)
 * ============================================================ */
add_action( 'admin_enqueue_scripts', function( $hook ) {

	if ( $hook !== 'luxvv_page_luxvv-requests' ) {
		return;
	}

	wp_enqueue_script(
		'luxvv-admin-dashboard',
		LUXVV_URL . 'assets/js/admin-dashboard.js',
		[],
		LUXVV_VERSION,
		true
	);
});

/* ============================================================
 * ACTIVATION
 * ============================================================ */
register_activation_hook( __FILE__, function () {
	if ( class_exists( 'LuxVerified\\Install' ) ) {
		\LuxVerified\Install::activate();
	}
});

register_deactivation_hook( __FILE__, function () {
	if ( class_exists( 'LuxVerified\\Analytics' ) ) {
		\LuxVerified\Analytics::unschedule();
	}
	if ( class_exists( 'LuxVerified\\Payouts' ) ) {
		\LuxVerified\Payouts::unschedule();
	}
});

/* ============================================================
 * INIT
 * ============================================================ */
add_action( 'init', function () {

	if ( class_exists( 'LuxVerified\\Settings' ) ) {
		\LuxVerified\Settings::init();
	}

	if ( class_exists( 'LuxVerified\\Verification' ) ) {
		\LuxVerified\Verification::init();
	}

	if ( class_exists( 'LuxVerified\\Review' ) ) {
		\LuxVerified\Review::init();
	}

	if ( class_exists( 'LuxVerified\\Ajax' ) ) {
		\LuxVerified\Ajax::init();
	}

	if ( class_exists( 'LuxVerified\\Analytics' ) ) {
		\LuxVerified\Analytics::init();
	}

	if ( class_exists( 'LuxVerified\\Payouts' ) ) {
		\LuxVerified\Payouts::init();
	}

	if ( class_exists( 'LuxVerified\\AI' ) ) {
		\LuxVerified\AI::init();
	}

	if ( class_exists( 'LuxVerified\\Rest_AI' ) ) {
		add_action( 'rest_api_init', [ '\LuxVerified\Rest_AI', 'init' ] );
	}

	if ( class_exists( 'LUXVV_Plugin' ) ) {
		LUXVV_Plugin::instance();
	}

}, 1 );

/* ============================================================
 * ADMIN MENU (ONLY ONCE)
 * ============================================================ */
add_action( 'admin_menu', function () {
	if ( class_exists( 'LuxVerified\\Admin_Menu' ) ) {
		\LuxVerified\Admin_Menu::register();
	}
}, 9 );

/* ============================================================
 * ADMIN POST HANDLERS
 * ============================================================ */
add_action( 'admin_init', function () {

	if ( class_exists( 'LuxVerified\\Admin' ) ) {
		\LuxVerified\Admin::init();
	}

	if ( class_exists( 'LuxVerified\\PDF_Controller' ) ) {
		\LuxVerified\PDF_Controller::init();
	}

});
