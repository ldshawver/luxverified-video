<?php
namespace LuxVerified;

if ( ! defined('ABSPATH') ) exit;

class Rest_AI {

    public static function init() {
        self::register_routes();
    }

    public static function register_routes() {
        register_rest_route('luxvv/v1', '/health', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'health_check'],
            'permission_callback' => [__CLASS__, 'basic_auth_only'],
        ]);

        register_rest_route('luxvv/v1', '/repair', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'handle_repair'],
            'permission_callback' => [__CLASS__, 'basic_auth_only'],
        ]);

        register_rest_route('luxvv/v1', '/ai/diagnostics', [
            'methods'  => ['GET', 'POST'],
            'callback' => [__CLASS__, 'diagnostics'],
            'permission_callback' => [__CLASS__, 'token_auth'],
        ]);

        register_rest_route('luxvv/v1', '/analytics/summary', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'analytics_summary'],
            'permission_callback' => [__CLASS__, 'basic_auth_only'],
        ]);

        register_rest_route('luxvv/v1', '/payouts', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'payouts_list'],
            'permission_callback' => [__CLASS__, 'basic_auth_only'],
        ]);

        register_rest_route('luxvv/v1', '/payouts/(?P<id>\d+)/mark-paid', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'payouts_mark_paid'],
            'permission_callback' => [__CLASS__, 'basic_auth_only'],
        ]);

        register_rest_route('luxvv/v1', '/payouts/(?P<id>\d+)/reset', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'payouts_reset'],
            'permission_callback' => [__CLASS__, 'basic_auth_only'],
        ]);

        register_rest_route('luxvv/v1', '/payouts/(?P<id>\d+)/receipt', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'payouts_receipt'],
            'permission_callback' => [__CLASS__, 'basic_auth_only'],
        ]);

        register_rest_route('luxvv/v1', '/marketing/export', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'marketing_export'],
            'permission_callback' => [__CLASS__, 'token_auth'],
        ]);
    }

    public static function basic_auth_only() {
        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $pass = $_SERVER['PHP_AUTH_PW'] ?? null;

        if ( ! $user || ! $pass ) {
            return new \WP_Error(
                'luxvv_no_auth',
                'Authorization required',
                [ 'status' => 401 ]
            );
        }

        $wp_user = wp_authenticate( $user, $pass );

        if ( is_wp_error( $wp_user ) ) {
            return new \WP_Error(
                'luxvv_invalid_auth',
                'Invalid credentials',
                [ 'status' => 403 ]
            );
        }

        if ( ! user_can( $wp_user, 'manage_options' ) ) {
            return new \WP_Error(
                'luxvv_forbidden',
                'Insufficient permissions',
                [ 'status' => 403 ]
            );
        }

        wp_set_current_user( $wp_user->ID );
        return true;
    }

    public static function token_auth( $request ) {
        $token = $request->get_header( 'X-Lux-Token' );
        $keys = get_option( AI::OPTION_KEYS, [] );

        if ( empty( $keys['enabled'] ) || empty( $keys['token'] ) ) {
            return true;
        }

        return $token && hash_equals( (string) $keys['token'], (string) $token );
    }

    public static function health_check() {
        global $wpdb;

        $tables = [
            'lux_videos',
            'lux_video_events',
            'lux_video_rollups',
            'lux_verified_members',
            'lux_payouts',
            'lux_payout_resets',
        ];

        $missing = [];
        foreach ( $tables as $table ) {
            $full = $wpdb->prefix . $table;
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$full'" ) !== $full ) {
                $missing[] = $table;
            }
        }

        return [
            'ok'        => empty( $missing ),
            'missing'   => $missing,
            'version'   => defined('LUXVV_VERSION') ? LUXVV_VERSION : '1.0.0',
            'wp'        => get_bloginfo('version'),
            'php'       => phpversion(),
            'time'      => time(),
        ];
    }

    public static function handle_repair() {
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        if ( class_exists( '\\LuxVerified\\Repair' ) ) {
            return Repair::run_repair();
        }

        return [
            'success' => false,
            'error' => 'Repair class not found',
        ];
    }

    public static function diagnostics( $request ) {
        return [
            'ok' => true,
            'plugin' => 'lux-verified-video',
            'version' => defined('LUXVV_VERSION') ? LUXVV_VERSION : '1.0.0',
            'time' => time(),
        ];
    }

    public static function analytics_summary( $request ) {
        $days = max( 1, min( 60, (int) $request->get_param( 'days' ) ) );
        if ( class_exists( '\\LuxVerified\\Analytics' ) ) {
            return \LuxVerified\Analytics::get_dashboard_summary( $days );
        }
        return [ 'error' => 'analytics_unavailable' ];
    }

    public static function marketing_export( $request ) {
        if ( ! class_exists( '\\LuxVerified\\Marketing' ) ) {
            return [
                'error' => 'marketing_unavailable',
            ];
        }

        return Marketing::export_config();
    }

    public static function payouts_list( $request ) {
        global $wpdb;

        $limit = max( 1, min( 200, (int) $request->get_param( 'limit' ) ) );
        $status = sanitize_key( (string) $request->get_param( 'status' ) );
        $t_payouts = $wpdb->prefix . 'lux_payouts';

        if ( $status ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$t_payouts} WHERE status = %s ORDER BY period_start DESC LIMIT %d",
                    $status,
                    $limit
                ),
                ARRAY_A
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$t_payouts} ORDER BY period_start DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    public static function payouts_mark_paid( $request ) {
        $payout_id = (int) $request->get_param( 'id' );
        $reference = sanitize_text_field( (string) $request->get_param( 'reference' ) );
        $notes = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );

        if ( ! class_exists( '\\LuxVerified\\Payouts' ) ) {
            return [ 'error' => 'payouts_unavailable' ];
        }

        \LuxVerified\Payouts::mark_payout_paid( $payout_id, $reference, $notes );
        return [ 'success' => true, 'payout_id' => $payout_id ];
    }

    public static function payouts_reset( $request ) {
        $payout_id = (int) $request->get_param( 'id' );
        $reason = sanitize_textarea_field( (string) $request->get_param( 'reason' ) );

        if ( ! class_exists( '\\LuxVerified\\Payouts' ) ) {
            return [ 'error' => 'payouts_unavailable' ];
        }

        \LuxVerified\Payouts::reset_payout( $payout_id, $reason );
        return [ 'success' => true, 'payout_id' => $payout_id ];
    }

    public static function payouts_receipt( $request ) {
        global $wpdb;
        $payout_id = (int) $request->get_param( 'id' );
        $t_payouts = $wpdb->prefix . 'lux_payouts';
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, receipt_path, status FROM {$t_payouts} WHERE id = %d", $payout_id ),
            ARRAY_A
        );

        if ( ! $row || ( $row['status'] ?? '' ) !== 'paid' ) {
            return [ 'error' => 'receipt_unavailable' ];
        }

        return [
            'payout_id' => $payout_id,
            'receipt_path' => $row['receipt_path'] ?? '',
        ];
    }
}
