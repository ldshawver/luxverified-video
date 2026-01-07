<?php
/**
 * Plugin Name: LUX Verified Video
 * Description: Verified creator uploads to Bunny Stream with analytics, payouts, and BuddyPress/PMPro integration.
 * Version: 3.6.1
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
define( 'LUXVV_URL', plugin_dir_url( __FILE__ ) );

require_once LUXVV_PATH . 'includes/class-plugin.php';

function luxvv_run_plugin() {
    return \LuxVerified\Plugin::instance();
}
add_action( 'plugins_loaded', 'luxvv_run_plugin' );
