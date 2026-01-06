<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bunny {

    private static $instance = null;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function create_video_slot( string $title = '' ) {
        $library_id = get_option( 'luxvv_bunny_library_id' );
        $api_key    = get_option( 'luxvv_bunny_api_key' );

        if ( ! $library_id || ! $api_key ) {
            return new \WP_Error( 'bunny-missing', 'Bunny credentials missing.' );
        }

        $url  = "https://video.bunnycdn.com/library/{$library_id}/videos";
        $body = [
            'title' => $title ?: 'Untitled',
        ];

        $response = wp_remote_post( $url, [
            'headers' => [
                'AccessKey'   => $api_key,
                'Content-Type'=> 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code && 201 !== $code ) {
            return new \WP_Error( 'bunny-error', 'Error from Bunny: ' . print_r( $data, true ) );
        }

        return $data;
    }
}
