<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Payouts {
    public static function init(): void {
        add_action( 'luxvv_weekly_payouts', [ __CLASS__, 'run_weekly_payouts' ] );
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( 'luxvv_weekly_payouts' ) ) {
            // Next Monday 00:15 site time
            $ts = strtotime( 'next monday 00:15', current_time( 'timestamp' ) );
            if ( ! $ts ) { $ts = time() + DAY_IN_SECONDS; }
            wp_schedule_event( $ts, 'weekly', 'luxvv_weekly_payouts' );
        }
    }

    public static function unschedule(): void {
        $ts = wp_next_scheduled( 'luxvv_weekly_payouts' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'luxvv_weekly_payouts' );
        }
    }

    /**
     * Calculates payouts for the previous full week (Mon..Sun).
     * Metric: views where viewer reached >= min seconds (default 20).
     */
    public static function run_weekly_payouts(): void {
        global $wpdb;

        $min = (int) Settings::get( 'analytics_min_view_seconds', 20 );

        // Previous week range (Mon..Sun)
        $now = current_time( 'timestamp' );
        $monday_this_week = strtotime( 'monday this week', $now );
        $start_ts = strtotime( '-7 days', $monday_this_week );
        $end_ts   = strtotime( '-1 day 23:59:59', $monday_this_week );

        $period_start = gmdate( 'Y-m-d', $start_ts + ( get_option('gmt_offset') * HOUR_IN_SECONDS ) );
        $period_end   = gmdate( 'Y-m-d', $end_ts + ( get_option('gmt_offset') * HOUR_IN_SECONDS ) );

        $t_events  = $wpdb->prefix . 'lux_video_events';
        $t_payouts = $wpdb->prefix . 'lux_payouts';

        // Aggregate per owner: count distinct (session_id, bunny_video_guid) with max(current_time) >= min
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT owner_user_id AS user_id,
                        COUNT(*) AS views_20s
                 FROM (
                    SELECT owner_user_id, bunny_video_guid, session_id, MAX(current_time) AS max_t
                    FROM {$t_events}
                    WHERE created_at BETWEEN %s AND %s
                      AND event_type IN ('time_update','progress','ended')
                      AND owner_user_id > 0
                      AND session_id IS NOT NULL AND session_id <> ''
                    GROUP BY owner_user_id, bunny_video_guid, session_id
                 ) x
                 WHERE x.max_t >= %d
                 GROUP BY owner_user_id",
                gmdate( 'Y-m-d H:i:s', $start_ts ),
                gmdate( 'Y-m-d H:i:s', $end_ts ),
                $min
            ),
            ARRAY_A
        );

        foreach ( $rows as $r ) {
            $user_id  = (int) $r['user_id'];
            $views_20 = (int) $r['views_20s'];
            if ( $user_id <= 0 ) { continue; }

            $tier = self::resolve_tier_cpm_cents( $views_20 );
            $payout_cents = (int) floor( ( $views_20 / 1000 ) * $tier );

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$t_payouts}
                        (period_start, period_end, user_id, views_20s, cpm_cents, payout_cents, status, created_at, updated_at)
                     VALUES (%s,%s,%d,%d,%d,%d,'pending',%s,%s)
                     ON DUPLICATE KEY UPDATE
                        views_20s=VALUES(views_20s),
                        cpm_cents=VALUES(cpm_cents),
                        payout_cents=VALUES(payout_cents),
                        updated_at=VALUES(updated_at)",
                    $period_start,
                    $period_end,
                    $user_id,
                    $views_20,
                    $tier,
                    $payout_cents,
                    current_time( 'mysql' ),
                    current_time( 'mysql' )
                )
            );
        }
    }

    public static function resolve_tier_cpm_cents( int $views ): int {
        $json = (string) Settings::get( 'payout_tiers_json', '' );
        $tiers = json_decode( $json, true );
        if ( ! is_array( $tiers ) ) {
            return 350;
        }
        // Sort by min_views ascending
        usort( $tiers, function( $a, $b ) {
            return (int) ($a['min_views'] ?? 0) <=> (int) ($b['min_views'] ?? 0);
        });

        $cpm = 350;
        foreach ( $tiers as $t ) {
            $min_views = (int) ( $t['min_views'] ?? 0 );
            $tier_cpm  = (int) ( $t['cpm_cents'] ?? 0 );
            if ( $views >= $min_views && $tier_cpm > 0 ) {
                $cpm = $tier_cpm;
            }
        }
        return $cpm;
    }
}
