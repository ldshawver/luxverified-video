<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Analytics {

    private static $instance = null;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public static function get_video_metrics( int $video_id ) : array {
        global $wpdb;
        $tbl = $wpdb->prefix . 'lux_video_events';

        if ( ! $video_id ) {
            return [
                'views'     => 0,
                'ctr'       => 0,
                'play_rate' => 0,
                'avg_watch' => 0,
                'retention' => [
                    '25%'  => 0,
                    '50%'  => 0,
                    '75%'  => 0,
                    '100%' => 0,
                ],
            ];
        }

        $impressions = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE video_id = %d AND event_type = %s", $video_id, 'impression' ) );
        $plays       = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE video_id = %d AND event_type = %s", $video_id, 'play' ) );
        $views20     = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE video_id = %d AND event_type = %s", $video_id, 'view_20s+' ) );
        $total_watch = (int) $wpdb->get_var( $wpdb->prepare("SELECT SUM(watch_seconds) FROM $tbl WHERE video_id = %d", $video_id ) );

        $avg_watch   = $plays > 0 ? round( $total_watch / $plays, 1 ) : 0;
        $ctr         = $impressions > 0 ? round( ( $plays / $impressions ) * 100, 2 ) : 0;
        $play_rate   = $plays > 0 ? round( ( $views20 / $plays ) * 100, 2 ) : 0;

        return [
            'views'     => $views20,
            'ctr'       => $ctr,
            'play_rate' => $play_rate,
            'avg_watch' => $avg_watch,
            'retention' => [
                '25%'  => 0,
                '50%'  => 0,
                '75%'  => 0,
                '100%' => 0,
            ],
        ];
    }
}
