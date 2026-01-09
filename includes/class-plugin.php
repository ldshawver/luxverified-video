<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    private static $instance = null;

    public static function init() : void {
        self::instance();
    }

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once LUXVV_PATH . 'includes/class-install.php';
        require_once LUXVV_PATH . 'includes/class-settings.php';
        require_once LUXVV_PATH . 'includes/class-verification.php';
        require_once LUXVV_PATH . 'includes/class-bunny.php';
        require_once LUXVV_PATH . 'includes/class-videos.php';
        require_once LUXVV_PATH . 'includes/class-analytics.php';
        require_once LUXVV_PATH . 'includes/class-payouts.php';
        require_once LUXVV_PATH . 'includes/Rest/class-rest.php';
        require_once LUXVV_PATH . 'includes/class-rm-slicewp-sync.php';
    }

    private function init_hooks() {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_assets' ] );

        Bunny::instance();
        Videos::instance();
        Analytics::instance();
        Payouts::instance();
        Rest\Main_REST::instance();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'lux-verified-video', false, dirname( plugin_basename( LUXVV_PATH . 'lux-verified-video.php' ) ) . '/languages' );
    }

    public function admin_assets() {
        wp_enqueue_style( 'luxvv-admin', LUXVV_URL . 'assets/css/lux-verified.css', [], LUXVV_VERSION );
        wp_enqueue_style( 'luxvv-admin-core', LUXVV_URL . 'assets/admin.css', [], LUXVV_VERSION );
        wp_enqueue_script( 'luxvv-admin', LUXVV_URL . 'assets/js/lux-verified.js', [ 'jquery' ], LUXVV_VERSION, true );
        wp_localize_script( 'luxvv-admin', 'luxVerified', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'luxvv_admin' ),
        ] );
    }

    public function frontend_assets() {
        wp_enqueue_style( 'luxvv-frontend', LUXVV_URL . 'assets/frontend.css', [], LUXVV_VERSION );
        wp_enqueue_style( 'luxvv-frontend-shared', LUXVV_URL . 'assets/css/lux-verified.css', [], LUXVV_VERSION );
    }
}
