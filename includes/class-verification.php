<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verification {

    public const NONCE_ACTION = 'luxvv_verify_action';
    public const STEP1 = 'luxvv_step1';
    public const STEP2 = 'luxvv_step2';
    public const STEP3 = 'luxvv_step3';
    public const VERIFIED_META = 'luxvv_verified';
    public const W9_STATUS_META = 'luxvv_w9_status';
    public const W9_SUBMITTED_META = 'luxvv_w9_submitted_at';
    public const W9_FIELD_KEYS_META = 'luxvv_w9_field_keys';
    public const TAX_ID_ENCRYPTED_META = 'luxvv_tax_id_encrypted';
    public const TAX_ID_TYPE_META = 'luxvv_tax_id_type';
    public const TAX_ID_MASKED_META = 'luxvv_tax_id_masked';
    public const BUSINESS_NAME_META = 'luxvv_business_name';

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

        // Shortcode for creator verification steps UI
        add_shortcode( 'luxvv_verification', [ $this, 'render_verification_steps' ] );
        add_shortcode( 'luxvv_w9_form', [ $this, 'render_w9_form' ] );

        // Admin AJAX for approve/reject
        add_action( 'wp_ajax_lux_verified_update_status', [ $this, 'ajax_update_status' ] );

        // Step 1 — Registration Magic hook
        add_action( 'rm_user_registered', [ $this, 'mark_step1_completed' ], 10, 1 );

        // Step 2 REST endpoint (Forminator)
        add_action( 'rest_api_init', [ $this, 'register_forminator_endpoint' ] );

        // Step 3 REST endpoint (Adobe Sign)
        add_action( 'rest_api_init', [ $this, 'register_adobe_endpoint' ] );

        add_action( 'forminator_custom_form_after_save_entry', [ $this, 'handle_w9_forminator_submission' ], 10, 4 );
    }

    /* ---------------------------------------------------------
     * 🔵 UTILITIES
     * ---------------------------------------------------------*/

    public static function user_is_verified( int $user_id ) : bool {
        return (int) get_user_meta( $user_id, self::VERIFIED_META, true ) === 1;
    }

    private function maybe_auto_verify( int $user_id ) {

        $s1 = get_user_meta( $user_id, self::STEP1, true );
        $s2 = get_user_meta( $user_id, self::STEP2, true );
        $s3 = get_user_meta( $user_id, self::STEP3, true );

        if ( ! $s1 || ! $s2 || ! $s3 ) {
            return;
        }

        self::upsert_status( $user_id, 'ready_for_review' );
    }

    public static function derive_status_from_meta( int $user_id ): string {
        if ( self::user_is_verified( $user_id ) ) {
            return 'approved';
        }

        $step1 = get_user_meta( $user_id, self::STEP1, true );
        $step2 = get_user_meta( $user_id, self::STEP2, true );
        $step3 = get_user_meta( $user_id, self::STEP3, true );

        $step1_done = ! empty( $step1 );
        $step2_done = ! empty( $step2 );
        $step3_done = ! empty( $step3 );

        if ( $step1_done && $step2_done && $step3_done ) {
            return 'ready_for_review';
        }

        if ( $step1_done && $step2_done ) {
            return 'step2_completed';
        }

        if ( $step1_done ) {
            return 'step1_completed';
        }

        return 'started';
    }

    public static function approve( int $user_id ): void {
        update_user_meta( $user_id, self::VERIFIED_META, 1 );
        update_user_meta( $user_id, 'luxvv_verified_at', time() );
        self::upsert_status( $user_id, 'approved' );
    }

    public static function reject( int $user_id, string $notes = '' ): void {
        delete_user_meta( $user_id, self::VERIFIED_META );
        self::upsert_status( $user_id, 'rejected', $notes );
    }

    private static function upsert_status( int $user_id, string $status, string $notes = '' ): void {
        global $wpdb;
        $tbl = $wpdb->prefix . 'lux_verified_members';

        $exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $tbl WHERE user_id = %d",
                $user_id
            )
        );

        $data = [
            'user_id' => $user_id,
            'verification_status' => $status,
            'updated_at' => current_time( 'mysql' ),
        ];

        if ( $notes ) {
            $data['notes'] = $notes;
        }

        if ( $exists ) {
            $wpdb->update( $tbl, $data, [ 'user_id' => $user_id ] );
            return;
        }

        $data['created_at'] = current_time( 'mysql' );
        $wpdb->insert( $tbl, $data );
    }

    /* ---------------------------------------------------------
     * 🔵 STEP 1 — Registration Magic
     * ---------------------------------------------------------*/

    public function mark_step1_completed( $user_id ) {
        update_user_meta( $user_id, self::STEP1, 1 );
        $this->maybe_auto_verify( (int) $user_id );
    }

    /* ---------------------------------------------------------
     * 🔵 STEP 2 — Forminator REST Webhook
     * ---------------------------------------------------------*/

    public function register_forminator_endpoint() {
        register_rest_route(
            'luxvv/v1',
            '/verification/forminator',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_forminator_callback' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function handle_forminator_callback( $request ) {

        $user_id = absint( $request['user_id'] ?? 0 );

        if ( ! $user_id ) {
            return new \WP_REST_Response(
                [ 'error' => 'Missing user_id' ],
                400
            );
        }

        update_user_meta( $user_id, self::STEP2, 1 );
        $this->maybe_auto_verify( $user_id );

        return new \WP_REST_Response(
            [ 'success' => true ],
            200
        );
    }

    /* ---------------------------------------------------------
     * 🔵 STEP 3 — Adobe Sign REST Webhook
     * ---------------------------------------------------------*/

    public function register_adobe_endpoint() {
        register_rest_route(
            'luxvv/v1',
            '/verification/adobe',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_adobe_callback' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function handle_adobe_callback( $request ) {

        $user_id = absint( $request['user_id'] ?? 0 );

        if ( ! $user_id ) {
            return new \WP_REST_Response(
                [ 'error' => 'Missing user_id' ],
                400
            );
        }

        update_user_meta( $user_id, self::STEP3, 1 );
        $this->maybe_auto_verify( $user_id );

        return new \WP_REST_Response(
            [ 'success' => true ],
            200
        );
    }

    /* ---------------------------------------------------------
     * 🔵 ADMIN AJAX — Approve / Reject
     * ---------------------------------------------------------*/

    public function ajax_update_status() {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        check_ajax_referer( 'luxvv_admin', 'nonce' );

        $user_id = absint( $_POST['user_id'] ?? 0 );
        $status  = sanitize_text_field( $_POST['status'] ?? '' );

        if ( ! $user_id || ! in_array( $status, [ 'approved', 'rejected' ], true ) ) {
            wp_send_json_error( 'Invalid request', 400 );
        }

        if ( 'approved' === $status ) {
            self::approve( $user_id );
        } else {
            self::reject( $user_id );
        }

        wp_send_json_success();
    }

    /* ---------------------------------------------------------
     * 🔵 SHORTCODE UI (Basic Placeholder)
     * ---------------------------------------------------------*/

    public function render_verification_steps() {
        return '<div class="luxvv-verification">Verification in progress…</div>';
    }

    public function render_w9_form(): string {
        if ( ! is_user_logged_in() ) {
            return '<p>You must be logged in to submit a W-9.</p>';
        }

        $form_id = (int) Settings::get( 'w9_form_id' );
        if ( ! $form_id ) {
            return '<p>W-9 form is not configured.</p>';
        }

        return do_shortcode( '[forminator_form id="' . $form_id . '"]' );
    }

    public function handle_w9_forminator_submission( $entry_id, $form_id, $form_data, $form_fields = [] ): void {
        $configured_id = (int) Settings::get( 'w9_form_id' );
        if ( ! $configured_id || (int) $form_id !== $configured_id ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id && is_array( $form_data ) && isset( $form_data['user_id'] ) ) {
            $user_id = absint( $form_data['user_id'] );
        }

        if ( ! $user_id ) {
            return;
        }

        $values = $this->extract_forminator_values( $form_data );
        $field_keys = array_keys( $values );
        $field_keys = array_map( 'sanitize_key', $field_keys );

        $tax_id_value = $this->extract_value_by_key_match(
            $values,
            [ 'ssn', 'ein', 'tax_id', 'taxid', 'tin' ]
        );
        $business_name = $this->extract_value_by_key_match(
            $values,
            [ 'business', 'company' ]
        );

        $tax_id = Helpers::normalize_tax_id( (string) $tax_id_value );
        $tax_type = $this->derive_tax_id_type( $values, $tax_id );
        $tax_id_encrypted = Helpers::encrypt_tax_id( $tax_id );
        $tax_id_masked = $tax_id ? Helpers::mask_tax_id( $tax_id, $tax_type ) : '';

        if ( $tax_id_encrypted ) {
            update_user_meta( $user_id, self::TAX_ID_ENCRYPTED_META, $tax_id_encrypted );
            update_user_meta( $user_id, self::TAX_ID_TYPE_META, $tax_type );
            update_user_meta( $user_id, self::TAX_ID_MASKED_META, $tax_id_masked );
        }

        if ( $tax_id && 'ssn' === $tax_type ) {
            update_user_meta( $user_id, 'luxvv_ssn_last4', substr( $tax_id, -4 ) );
        }

        if ( $tax_id && 'ein' === $tax_type ) {
            update_user_meta( $user_id, 'luxvv_ein_masked', $tax_id_masked );
        }

        if ( $business_name ) {
            update_user_meta( $user_id, self::BUSINESS_NAME_META, sanitize_text_field( $business_name ) );
        }

        update_user_meta( $user_id, self::W9_STATUS_META, 'complete' );
        update_user_meta( $user_id, self::W9_SUBMITTED_META, current_time( 'timestamp' ) );
        update_user_meta( $user_id, self::W9_FIELD_KEYS_META, $field_keys );
        update_user_meta( $user_id, self::STEP3, 1 );

        $this->maybe_auto_verify( $user_id );
    }

    private function extract_forminator_values( $form_data ): array {
        $values = [];
        if ( ! is_array( $form_data ) ) {
            return $values;
        }

        foreach ( $form_data as $key => $value ) {
            if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
                $values[ $key ] = $value['value'];
                continue;
            }

            $values[ $key ] = $value;
        }

        return $values;
    }

    private function extract_value_by_key_match( array $values, array $matchers ): string {
        foreach ( $values as $key => $value ) {
            $key = strtolower( (string) $key );
            foreach ( $matchers as $matcher ) {
                if ( false !== strpos( $key, $matcher ) ) {
                    return is_scalar( $value ) ? (string) $value : '';
                }
            }
        }

        return '';
    }

    private function derive_tax_id_type( array $values, string $tax_id ): string {
        foreach ( array_keys( $values ) as $key ) {
            $key = strtolower( (string) $key );
            if ( str_contains( $key, 'ein' ) ) {
                return 'ein';
            }
            if ( str_contains( $key, 'ssn' ) ) {
                return 'ssn';
            }
        }

        return strlen( $tax_id ) === 9 ? 'tin' : 'tin';
    }
}
