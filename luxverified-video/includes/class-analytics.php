<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Analytics {
    public static function init(): void {
        add_action( 'luxvv_rollup_daily', [ __CLASS__, 'run_daily_rollup' ] );
        add_action( 'luxvv_cleanup_events', [ __CLASS__, 'cleanup' ] );
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( 'luxvv_rollup_daily' ) ) {
            wp_schedule_event( time() + 600, 'daily', 'luxvv_rollup_daily' );
        }
        if ( ! wp_next_scheduled( 'luxvv_cleanup_events' ) ) {
            wp_schedule_event( time() + 1200, 'daily', 'luxvv_cleanup_events' );
        }
    }

    public static function unschedule(): void {
        foreach ( [ 'luxvv_rollup_daily', 'luxvv_cleanup_events' ] as $hook ) {
            $ts = wp_next_scheduled( $hook );
            if ( $ts ) {
                wp_unschedule_event( $ts, $hook );
            }
        }
    }

    /**
     * Roll up yesterday by default.
     */
    public static function run_daily_rollup( ?string $day = null ): void {
        global $wpdb;

        $min_view = (int) Settings::get( 'analytics_min_view_seconds', 20 );
        $interval = 15; // time_update interval used by player-tracking.js

        $day = $day ?: gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
        $start = $day . ' 00:00:00';
        $end   = $day . ' 23:59:59';

        $t_events  = $wpdb->prefix . 'lux_video_events';
        $t_rollups = $wpdb->prefix . 'lux_video_rollups';

        // Get all videos seen that day
        $videos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT bunny_video_guid, MAX(owner_user_id) AS owner_user_id, MAX(post_id) AS post_id
                 FROM {$t_events}
                 WHERE created_at BETWEEN %s AND %s AND bunny_video_guid <> ''
                 GROUP BY bunny_video_guid",
                $start,
                $end
            ),
            ARRAY_A
        );

        if ( ! $videos ) {
            return;
        }

        foreach ( $videos as $v ) {
            $guid  = (string) $v['bunny_video_guid'];
            $owner = (int) $v['owner_user_id'];
            $post  = (int) $v['post_id'];

            $impressions = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t_events} WHERE created_at BETWEEN %s AND %s AND bunny_video_guid=%s AND event_type IN ('page_load','impression')",
                $start, $end, $guid
            ) );

            $plays = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t_events} WHERE created_at BETWEEN %s AND %s AND bunny_video_guid=%s AND event_type='play'",
                $start, $end, $guid
            ) );

            $completes = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t_events} WHERE created_at BETWEEN %s AND %s AND bunny_video_guid=%s AND event_type IN ('ended','complete')",
                $start, $end, $guid
            ) );

            // Approx watch seconds: count of time_update events * interval (best-effort)
            $time_updates = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t_events} WHERE created_at BETWEEN %s AND %s AND bunny_video_guid=%s AND event_type IN ('time_update','progress')",
                $start, $end, $guid
            ) );
            $watch_seconds = $time_updates * $interval;

            // Distinct sessions that reached >= min_view seconds at least once that day
            $views_20s = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM (
                    SELECT session_id
                    FROM {$t_events}
                    WHERE created_at BETWEEN %s AND %s
                      AND bunny_video_guid=%s
                      AND event_type IN ('time_update','progress')
                    GROUP BY session_id
                    HAVING MAX(current_time) >= %d
                ) x",
                $start, $end, $guid, $min_view
            ) );

            $p25 = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM (
                    SELECT session_id
                    FROM {$t_events}
                    WHERE created_at BETWEEN %s AND %s AND bunny_video_guid=%s
                      AND event_type IN ('time_update','progress')
                    GROUP BY session_id
                    HAVING MAX(current_time) >= 25
                ) x",
                $start, $end, $guid
            ) );
            $p50 = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM (
                    SELECT session_id
                    FROM {$t_events}
                    WHERE created_at BETWEEN %s AND %s AND bunny_video_guid=%s
                      AND event_type IN ('time_update','progress')
                    GROUP BY session_id
                    HAVING MAX(current_time) >= 50
                ) x",
                $start, $end, $guid
            ) );
            $p75 = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM (
                    SELECT session_id
                    FROM {$t_events}
                    WHERE created_at BETWEEN %s AND %s AND bunny_video_guid=%s
                      AND event_type IN ('time_update','progress')
                    GROUP BY session_id
                    HAVING MAX(current_time) >= 75
                ) x",
                $start, $end, $guid
            ) );
            $p100 = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM (
                    SELECT session_id
                    FROM {$t_events}
                    WHERE created_at BETWEEN %s AND %s AND bunny_video_guid=%s
                      AND event_type IN ('time_update','progress')
                    GROUP BY session_id
                    HAVING MAX(current_time) >= 100
                ) x",
                $start, $end, $guid
            ) );

            $wpdb->replace(
                $t_rollups,
                [
                    'day'            => $day,
                    'owner_user_id'  => $owner,
                    'post_id'        => $post,
                    'bunny_video_guid'=> $guid,
                    'impressions'    => $impressions,
                    'plays'          => $plays,
                    'views_20s'      => $views_20s,
                    'completes'      => $completes,
                    'watch_seconds'  => $watch_seconds,
                    'p25' => $p25,
                    'p50' => $p50,
                    'p75' => $p75,
                    'p100'=> $p100,
                ],
                [ '%s','%d','%d','%s','%d','%d','%d','%d','%d','%d','%d','%d','%d' ]
            );
        }
    }

    public static function cleanup(): void {
        global $wpdb;
        $days = (int) Settings::get( 'analytics_retention_days', 90 );
        if ( $days <= 0 ) { return; }
        $t_events = $wpdb->prefix . 'lux_video_events';
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$t_events} WHERE created_at < (NOW() - INTERVAL %d DAY)", $days ) );
    }

    public static function rollup_range( int $days ): void {
        $days = max( 1, $days );
        for ( $i = $days; $i >= 1; $i-- ) {
            $day = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            self::run_daily_rollup( $day );
        }
    }

    public static function get_dashboard_summary( int $days = 30 ): array {
        global $wpdb;

        $days = max( 1, $days );
        $since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        $t_rollups = $wpdb->prefix . 'lux_video_rollups';

        $table_exists = $wpdb->get_var(
            $wpdb->prepare( "SHOW TABLES LIKE %s", $t_rollups )
        ) === $t_rollups;

        if ( ! $table_exists ) {
            return [
                'days' => $days,
                'impressions' => 0,
                'plays' => 0,
                'views_20s' => 0,
                'completes' => 0,
                'watch_seconds' => 0,
            ];
        }

        $totals = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COALESCE(SUM(impressions),0) AS impressions,
                    COALESCE(SUM(plays),0) AS plays,
                    COALESCE(SUM(views_20s),0) AS views_20s,
                    COALESCE(SUM(completes),0) AS completes,
                    COALESCE(SUM(watch_seconds),0) AS watch_seconds
                 FROM {$t_rollups}
                 WHERE day >= %s",
                $since
            ),
            ARRAY_A
        );

        return [
            'days' => $days,
            'impressions' => (int) ( $totals['impressions'] ?? 0 ),
            'plays' => (int) ( $totals['plays'] ?? 0 ),
            'views_20s' => (int) ( $totals['views_20s'] ?? 0 ),
            'completes' => (int) ( $totals['completes'] ?? 0 ),
            'watch_seconds' => (int) ( $totals['watch_seconds'] ?? 0 ),
        ];
    }

    public static function get_creator_summary( int $user_id, int $days = 30 ): array {
        global $wpdb;

        $days = max( 1, $days );
        $since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        $t_rollups = $wpdb->prefix . 'lux_video_rollups';

        $totals = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COALESCE(SUM(impressions),0) AS impressions,
                    COALESCE(SUM(plays),0) AS plays,
                    COALESCE(SUM(views_20s),0) AS views_20s,
                    COALESCE(SUM(completes),0) AS completes,
                    COALESCE(SUM(watch_seconds),0) AS watch_seconds,
                    COALESCE(SUM(p75),0) AS p75
                 FROM {$t_rollups}
                 WHERE day >= %s AND owner_user_id = %d",
                $since,
                $user_id
            ),
            ARRAY_A
        );

        $impressions = (int) ( $totals['impressions'] ?? 0 );
        $plays = (int) ( $totals['plays'] ?? 0 );
        $views_20s = (int) ( $totals['views_20s'] ?? 0 );
        $completes = (int) ( $totals['completes'] ?? 0 );
        $watch_seconds = (int) ( $totals['watch_seconds'] ?? 0 );
        $p75 = (int) ( $totals['p75'] ?? 0 );

        $ctr = $impressions > 0 ? ( $views_20s / $impressions ) : 0;
        $completion_rate = $plays > 0 ? ( $completes / $plays ) : 0;
        $retention_rate = $views_20s > 0 ? ( $p75 / $views_20s ) : 0;

        return [
            'days' => $days,
            'impressions' => $impressions,
            'plays' => $plays,
            'views_20s' => $views_20s,
            'completes' => $completes,
            'watch_seconds' => $watch_seconds,
            'ctr' => $ctr,
            'completion_rate' => $completion_rate,
            'retention_rate' => $retention_rate,
        ];
    }

    public static function get_creator_rollups( int $user_id, int $days = 30 ): array {
        global $wpdb;

        $days = max( 1, $days );
        $since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        $t_rollups = $wpdb->prefix . 'lux_video_rollups';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT day, impressions, plays, views_20s, completes, watch_seconds, p75
                 FROM {$t_rollups}
                 WHERE day >= %s AND owner_user_id = %d
                 ORDER BY day ASC",
                $since,
                $user_id
            ),
            ARRAY_A
        );
    }
}
