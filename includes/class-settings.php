<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Settings {

    /** @return string */
    public static function opt_key(): string {
        return 'luxvv_settings';
    }

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
    }

    public static function register(): void {

        if ( false === get_option( self::opt_key(), false ) ) {
            add_option( self::opt_key(), self::defaults(), '', false );
        }

        register_setting(
            'luxvv_settings_group',
            self::opt_key(),
            [
                'type'              => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize' ],
                'default'           => self::defaults(),
            ]
        );
    }

    /* ======================================================
     * DEFAULTS
     * ====================================================== */

    public static function defaults(): array {

        return [
            // Verification forms
            'agreement_form_id' => 0,
            'w9_form_id'        => 0,

            // 🔐 Verification behavior
            'auto_approve_after_w9' => 0,

            // Bunny
            'bunny_library_id'  => '',
            'bunny_api_key'     => '',
            'bunny_cdn_host'    => '',
            'bunny_webhook_url' => '',

            // Analytics
            'analytics_min_view_seconds' => 20,
            'analytics_retention_days'   => 90,
            'analytics_debug'            => 0,

            // Payouts
            'payout_minimum_cents' => 2500,
            'payout_tiers_json'    => json_encode(
                [
                    [ 'min_views' => 0, 'cpm_cents' => 350 ],
                    [ 'min_views' => 10000, 'cpm_cents' => 450 ],
                    [ 'min_views' => 50000, 'cpm_cents' => 600 ],
                ],
                JSON_PRETTY_PRINT
            ),
            'payout_ctr_bonus_threshold' => 0.05,
            'payout_retention_bonus_threshold' => 0.75,

            'debug' => 0,
        ];
    }

    /* ======================================================
     * SANITIZATION
     * ====================================================== */

    public static function sanitize( $in ): array {

        $in  = is_array( $in ) ? $in : [];
        $out = self::defaults();

        foreach ( $out as $key => $default ) {

            if ( ! array_key_exists( $key, $in ) ) {
                continue;
            }

            if ( is_int( $default ) ) {
                $out[ $key ] = absint( $in[ $key ] );
            }
            elseif ( in_array( $key, [ 'payout_ctr_bonus_threshold', 'payout_retention_bonus_threshold' ], true ) ) {
                $out[ $key ] = (float) $in[ $key ];
            }
            elseif ( strpos( $key, '_url' ) !== false ) {
                $out[ $key ] = esc_url_raw( $in[ $key ] );
            }
            elseif ( $key === 'payout_tiers_json' ) {
                $out[ $key ] = wp_kses_post( $in[ $key ] );
            }
            else {
                $out[ $key ] = sanitize_text_field( $in[ $key ] );
            }
        }

        return $out;
    }

    /* ======================================================
     * ACCESSORS
     * ====================================================== */

    public static function get( string $key, $default = null ) {
        $opts = get_option( self::opt_key(), self::defaults() );
        return $opts[ $key ] ?? $default;
    }

    public static function all(): array {
        return get_option( self::opt_key(), self::defaults() );
    }

    public static function maybe_seed_defaults(): void {
        if ( false === get_option( self::opt_key(), false ) ) {
            add_option( self::opt_key(), self::defaults(), '', false );
        }
    }
}
