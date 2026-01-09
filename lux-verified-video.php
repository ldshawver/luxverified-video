<?php
/**
 * Plugin Name: LUX Verified Video
 * Description: Verified creator uploads to Bunny Stream with analytics, payouts, and BuddyPress/PMPro integration.
 * Version: 1.0.0
 * Author: Luke Shawver
 * Text Domain: lux-verified-video
 * Requires at least: 6.0
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LUXVV_VERSION', '1.0.0' );
define( 'LUXVV_PATH', plugin_dir_path( __FILE__ ) );
define( 'LUXVV_DIR', LUXVV_PATH );
define( 'LUXVV_URL', plugin_dir_url( __FILE__ ) );

require_once LUXVV_PATH . 'includes/class-install.php';
require_once LUXVV_PATH . 'includes/class-admin-menu.php';
require_once LUXVV_PATH . 'includes/class-admin-actions.php';
require_once LUXVV_PATH . 'includes/class-admin.php';
require_once LUXVV_PATH . 'includes/class-helpers.php';
require_once LUXVV_PATH . 'includes/class-settings.php';
require_once LUXVV_PATH . 'includes/class-verification.php';
require_once LUXVV_PATH . 'includes/class-plugin.php';
require_once LUXVV_PATH . 'includes/class-ai.php';
require_once LUXVV_PATH . 'includes/class-repair.php';
require_once LUXVV_PATH . 'includes/class-review.php';
require_once LUXVV_PATH . 'includes/class-pdf.php';
require_once LUXVV_PATH . 'includes/class-pdf-controller.php';

\LuxVerified\Install::init();
\LuxVerified\Admin_Menu::init();
\LuxVerified\Admin_Actions::init();
\LuxVerified\Admin::init();
\LuxVerified\Settings::init();
\LuxVerified\Verification::init();
\LuxVerified\Plugin::init();
\LuxVerified\AI::init();
\LuxVerified\Repair::init();
\LuxVerified\Review::init();
\LuxVerified\PDF_Controller::init();
