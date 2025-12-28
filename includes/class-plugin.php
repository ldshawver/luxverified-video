<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LUXVV_Plugin {
    private static $instance = null;

    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
    }

    public function enqueue_frontend_assets(): void {
        if ( ! is_singular() ) { return; }

        $post_id = get_queried_object_id();
        if ( ! $post_id ) { return; }

        $video_guid = (string) get_post_meta( $post_id, '_luxvv_video_guid', true );
        if ( ! $video_guid ) { return; }

        wp_enqueue_script(
            'luxvv-player-tracking',
            LUXVV_URL . 'assets/player-tracking.js',
            [],
            LUXVV_VERSION,
            true
        );

        wp_localize_script(
            'luxvv-player-tracking',
            'luxvvPlayer',
            [
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'luxvv_track_event' ),
                'postId'    => (int) $post_id,
                'videoGuid' => $video_guid,
                'timeUpdateInterval' => 15,
                'debug'     => false,
            ]
        );
    }
}
