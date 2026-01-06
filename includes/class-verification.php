<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verification {

    private static $instance = null;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {

        // Shortcode for creator verification steps UI
        add_shortcode( 'luxvv_verification', [ $this, 'render_verification_steps' ] );

        // Admin AJAX for approve/reject
        add_action( 'wp_ajax_lux_verified_update_status', [ $this, 'ajax_update_status' ] );

        // Step 1 — Registration Magic hook
        add_action( 'rm_user_registered', [ $this, 'mark_step1_completed' ], 10, 1 );

        // Step 2 REST endpoint (Forminator)
        add_action( 'rest_api_init', [ $this, 'register_forminator_endpoint' ] );

        // Step 3 REST endpoint (Adobe Sign)
        add_action( 'rest_api_init', [ $this, 'register_adobe_endpoint' ] );
    }

    /* ---------------------------------------------------------
     * 🔵 UTILITIES
     * ---------------------------------------------------------*/

    public static function user_is_verified( int $user_id ) : bool {
        global $wpdb;
        $tbl = $wpdb->prefix . 'lux_verified_members';
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT admin_status FROM $tbl WHERE user_id = %d", $user_id )
        );
        return ( $row && $row->admin_status === 'approved' );
    }

    private function maybe_auto_verify( int $user_id ) {
        $s1 = get_user_meta( $user_id, 'luxvv_step1', true );
        $s2 = get_user_meta( $user_id, 'luxvv_step2', true );
        $s3 = get_user_meta( $user_id, 'luxvv_step3', true );

        if ( $s1 === 'completed' && $s2 === 'completed' && $s3 === 'completed' ) {

            global $wpdb;
            $tbl = $wpdb->prefix . 'lux_verified_members';

            // Insert/update record
            $exists = $wpdb->get_var(
                $wpdb->prepare( "SELECT id FROM $tbl WHERE user_id = %d", $user_id )
            );

            if ( $exists ) {
                $wpdb->update(
                    $tbl,
                    [ 'admin_status' => 'approved' ],
                    [ 'user_id' => $user_id ]
                );
            } else {
                $wpdb->insert(
                    $tbl,
                    [
                        'user_id'      => $user_id,
                        'admin_status' => 'approved'
                    ]
                );
            }

            update_user_meta( $user_id, 'luxvv_verified_at', time() );
        }
    }

    /* ---------------------------------------------------------
     * 🔵 STEP 1 — Registration Magic profile created
     * ---------------------------------------------------------*/
    public function mark_step1_completed( $user_id ) {
        update_user_meta( $user_id, 'luxvv_step1', 'completed' );
        $this->maybe_auto_verify( $user_id );
    }

    /* ---------------------------------------------------------
     * 🔵 STEP 2 — Forminator Webhook (REST Endpoint)
     * ---------------------------------------------------------*/
    public function register_forminator_endpoint() {
        register_rest_route( 'luxvv/v1', '/verification/forminator', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_forminator_callback' ],
            'permission_callback' => '__return_true'
        ] );
    }

    public function handle_forminator_callback( $request ) {
        $user_id = absint( $request['user_id'] );

        if ( ! $user_id ) {_
