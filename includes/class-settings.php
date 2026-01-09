<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Settings {

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
    }

    public static function opt_key(): string {
        return 'luxvv_settings';
    }

    public static function register(): void {
        if ( false === get_option( self::opt_key(), false ) ) {
            add_option( self::opt_key(), self::defaults(), '', false );
        }

        register_setting(
            'luxvv_settings_group',
            self::opt_key(),
            [
                'type' => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize' ],
                'default' => self::defaults(),
            ]
        );
    }

    public static function defaults(): array {
        return [
            'agreement_form_id' => '',
            'w9_form_id' => '',
            'bunny_library_id' => '',
            'bunny_api_key' => '',
            'bunny_cdn_host' => '',
            'bunny_webhook_url' => '',
            'analytics_min_view_seconds' => 20,
            'analytics_retention_days' => 30,
            'payout_tiers_json' => json_encode( [
                [ 'min_views' => 0, 'cpm_cents' => 350 ],
                [ 'min_views' => 10000, 'cpm_cents' => 450 ],
                [ 'min_views' => 50000, 'cpm_cents' => 600 ],
            ], JSON_PRETTY_PRINT ),
            'payout_ctr_bonus_threshold' => 0.05,
            'payout_retention_bonus_threshold' => 0.75,
            'payout_1099_threshold' => 600,
        ];
    }

    public static function sanitize( $input ): array {
        $input = is_array( $input ) ? $input : [];
        $defaults = self::defaults();
        $sanitized = $defaults;

        foreach ( $defaults as $key => $default ) {
            if ( ! array_key_exists( $key, $input ) ) {
                continue;
            }

            $value = $input[ $key ];

            if ( is_numeric( $default ) ) {
                $sanitized[ $key ] = is_float( $default ) ? (float) $value : (int) $value;
                continue;
            }

            if ( 'payout_tiers_json' === $key ) {
                $sanitized[ $key ] = wp_kses_post( $value );
                continue;
            }

            $sanitized[ $key ] = sanitize_text_field( $value );
        }

        return $sanitized;
    }

    public static function get( string $key, $default = null ) {
        $settings = get_option( self::opt_key(), self::defaults() );
        return $settings[ $key ] ?? $default;
    }
}
