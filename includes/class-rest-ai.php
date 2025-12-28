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
        $token = $request->get_header('X-Lux-Token');
        $secret = get_option('lux_ai_token', '');

        if ( empty($secret) ) {
            return true;
        }

        return $token && hash_equals($secret, $token);
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
}
