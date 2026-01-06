<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Ajax {
    public static function init(): void {
        add_action( 'wp_ajax_luxvv_track_event', [ __CLASS__, 'track_event' ] );
        add_action( 'wp_ajax_nopriv_luxvv_track_event', [ __CLASS__, 'track_event' ] );

        add_action( 'wp_ajax_luxvv_admin_summary', [ __CLASS__, 'admin_summary' ] );
        add_action( 'wp_ajax_luxvv_rollup_now', [ __CLASS__, 'rollup_now' ] );
    }

    public static function track_event(): void {
        header( 'Content-Type: application/json; charset=utf-8' );

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'luxvv_track_event' ) ) {
            wp_send_json_error( [ 'reason' => 'invalid_nonce' ], 403 );
        }

        $post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $guid      = isset( $_POST['video_guid'] ) ? sanitize_text_field( wp_unslash( $_POST['video_guid'] ) ) : '';
        $event     = isset( $_POST['event_type'] ) ? sanitize_key( wp_unslash( $_POST['event_type'] ) ) : '';
        $ct        = isset( $_POST['current_time'] ) ? absint( $_POST['current_time'] ) : 0;
        $payload   = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';

        if ( ! $post_id || ! $event ) {
            wp_send_json_error( [ 'reason' => 'missing_fields' ], 400 );
        }

        global $wpdb;
        $t = $wpdb->prefix . 'lux_video_events';

        $viewer_user_id = get_current_user_id();
        $owner_user_id  = 0;
        if ( $post_id ) {
            $owner_user_id = (int) get_post_field( 'post_author', $post_id );
        }

        $data = [
            'created_at'      => current_time( 'mysql' ),
            'owner_user_id'   => $owner_user_id,
            'viewer_user_id'  => $viewer_user_id,
            'session_id'      => Helpers::session_id(),
            'post_id'         => $post_id,
            'bunny_video_guid'=> substr( (string) $guid, 0, 191 ),
            'event_type'      => substr( (string) $event, 0, 32 ),
            'current_time'    => $ct,
            'payload'         => is_string( $payload ) ? $payload : '',
            'ip_hash'         => Helpers::ip_hash(),
            'user_agent'      => Helpers::user_agent(),
            'referrer'        => Helpers::referrer(),
        ];

        $ok = $wpdb->insert( $t, $data, [ '%s','%d','%d','%s','%d','%s','%s','%d','%s','%s','%s','%s' ] );
        if ( ! $ok ) {
            wp_send_json_error( [ 'reason' => 'db_insert_failed', 'db_error' => $wpdb->last_error ], 500 );
        }

        wp_send_json_success( [ 'insert_id' => (int) $wpdb->insert_id ] );
    }

    public static function admin_summary(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'reason' => 'forbidden' ], 403 );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'luxvv_admin' ) ) {
            wp_send_json_error( [ 'reason' => 'invalid_nonce' ], 403 );
        }

        $days = isset( $_POST['days'] ) ? max( 7, min( 60, absint( $_POST['days'] ) ) ) : 30;

        $summary = Analytics::get_dashboard_summary( $days );
        wp_send_json_success( $summary );
    }

    public static function rollup_now(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'reason' => 'forbidden' ], 403 );
        }
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'luxvv_admin' ) ) {
            wp_send_json_error( [ 'reason' => 'invalid_nonce' ], 403 );
        }

        Analytics::rollup_range( 7 );
        wp_send_json_success( [ 'rolled_up' => true ] );
    }
}
