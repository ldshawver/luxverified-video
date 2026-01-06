<?php
namespace LuxVerified\Rest;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Main_REST {

    private static $instance = null;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'luxvv/v1', '/videos/webhook', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'handle_bunny_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle_bunny_webhook( WP_REST_Request $request ) : WP_REST_Response {
        $data = $request->get_json_params();
        $guid = $data['videoGuid'] ?? '';

        if ( ! $guid ) {
            return new WP_REST_Response( [ 'message' => 'Missing GUID' ], 400 );
        }

        global $wpdb;
        $tbl = $wpdb->prefix . 'lux_videos';
        $video = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tbl WHERE bunny_guid = %s", $guid ) );

        if ( ! $video ) {
            return new WP_REST_Response( [ 'message' => 'Video not found' ], 404 );
        }

        $thumb = $data['thumbnailUrl'] ?? '';
        $duration = isset( $data['length'] ) ? absint( $data['length'] ) : 0;

        $wpdb->update( $tbl, [
            'thumb_url' => $thumb,
            'duration'  => $duration,
            'status'    => 'ready',
        ], [ 'id' => $video->id ] );

        if ( $video->post_id ) {
            update_post_meta( $video->post_id, '_luxvv_thumb', $thumb );
            update_post_meta( $video->post_id, '_luxvv_duration', $duration );
        }

        return new WP_REST_Response( [ 'message' => 'ok' ], 200 );
    }
}
