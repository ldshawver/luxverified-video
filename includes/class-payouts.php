<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Payouts {
    public static function init(): void {
        add_action( 'luxvv_weekly_payouts', [ __CLASS__, 'run_weekly_payouts' ] );
        add_action( 'luxvv_yearly_1099', [ __CLASS__, 'send_yearly_1099s' ] );
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedules' ] );
        add_action( 'admin_post_luxvv_mark_payout_paid', [ __CLASS__, 'handle_mark_paid' ] );
        add_action( 'admin_post_luxvv_reset_payout', [ __CLASS__, 'handle_reset_payout' ] );
        add_action( 'admin_post_luxvv_download_receipt', [ __CLASS__, 'handle_download_receipt' ] );
        add_action( 'admin_post_luxvv_export_1099', [ __CLASS__, 'handle_export_1099' ] );
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( 'luxvv_weekly_payouts' ) ) {
            // Next Monday 00:15 site time
            $ts = strtotime( 'next monday 00:15', current_time( 'timestamp' ) );
            if ( ! $ts ) { $ts = time() + DAY_IN_SECONDS; }
            wp_schedule_event( $ts, 'weekly', 'luxvv_weekly_payouts' );
        }

        if ( ! wp_next_scheduled( 'luxvv_yearly_1099' ) ) {
            $now = current_time( 'timestamp' );
            $year = (int) gmdate( 'Y', $now + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
            $ts = strtotime( "{$year}-01-01 00:10:00" );
            if ( $ts <= $now ) {
                $ts = strtotime( ( $year + 1 ) . "-01-01 00:10:00" );
            }
            wp_schedule_event( $ts, 'yearly', 'luxvv_yearly_1099' );
        }
    }

    public static function unschedule(): void {
        $ts = wp_next_scheduled( 'luxvv_weekly_payouts' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'luxvv_weekly_payouts' );
        }

        $ts = wp_next_scheduled( 'luxvv_yearly_1099' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'luxvv_yearly_1099' );
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
        $t_rollups = $wpdb->prefix . 'lux_video_rollups';
        $t_payouts = $wpdb->prefix . 'lux_payouts';

        $table_exists = $wpdb->get_var(
            $wpdb->prepare( "SHOW TABLES LIKE %s", $t_rollups )
        ) === $t_rollups;

        if ( $table_exists ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT owner_user_id AS user_id,
                            SUM(impressions) AS impressions,
                            SUM(plays) AS plays,
                            SUM(views_20s) AS views_20s,
                            SUM(p75) AS p75
                     FROM {$t_rollups}
                     WHERE day BETWEEN %s AND %s
                     GROUP BY owner_user_id",
                    $period_start,
                    $period_end
                ),
                ARRAY_A
            );
        } else {
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
        }

        foreach ( $rows as $r ) {
            $user_id  = (int) $r['user_id'];
            $views_20 = (int) ( $r['views_20s'] ?? 0 );
            if ( $user_id <= 0 ) { continue; }

            $impressions = (int) ( $r['impressions'] ?? 0 );
            $plays = (int) ( $r['plays'] ?? 0 );
            $p75 = (int) ( $r['p75'] ?? 0 );

            $calc = self::calculate_payout_breakdown( $views_20, $impressions, $plays, $p75 );
            $tier = $calc['cpm_cents'];
            $payout_cents = $calc['payout_cents'];

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$t_payouts}
                        (period_start, period_end, user_id, impressions, plays, views_20s, cpm_cents, payout_cents, ctr, retention_rate, bonus_pct, status, created_at, updated_at)
                     VALUES (%s,%s,%d,%d,%d,%d,%d,%d,%f,%f,%f,'pending',%s,%s)
                     ON DUPLICATE KEY UPDATE
                        impressions=VALUES(impressions),
                        plays=VALUES(plays),
                        views_20s=VALUES(views_20s),
                        cpm_cents=VALUES(cpm_cents),
                        payout_cents=VALUES(payout_cents),
                        ctr=VALUES(ctr),
                        retention_rate=VALUES(retention_rate),
                        bonus_pct=VALUES(bonus_pct),
                        status=IF(status='paid','paid','pending'),
                        updated_at=VALUES(updated_at)",
                    $period_start,
                    $period_end,
                    $user_id,
                    $impressions,
                    $plays,
                    $views_20,
                    $tier,
                    $payout_cents,
                    $calc['ctr'],
                    $calc['retention_rate'],
                    $calc['bonus_pct'],
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

    public static function calculate_payout_breakdown( int $views_20s, int $impressions, int $plays, int $p75 ): array {
        $cpm = self::resolve_tier_cpm_cents( $views_20s );
        $tier_name = self::resolve_tier_name( $views_20s );

        $ctr = $impressions > 0 ? ( $views_20s / $impressions ) : 0;
        $retention_rate = $views_20s > 0 ? ( $p75 / $views_20s ) : 0;

        $ctr_threshold = (float) Settings::get( 'payout_ctr_bonus_threshold', 0.05 );
        $retention_threshold = (float) Settings::get( 'payout_retention_bonus_threshold', 0.75 );

        $bonus_pct = 0.0;
        if ( $views_20s >= 250000 ) {
            if ( $retention_rate >= $retention_threshold ) {
                $bonus_pct = 0.25;
            }
        } elseif ( $views_20s >= 50000 ) {
            if ( $ctr >= $ctr_threshold ) {
                $bonus_pct = 0.10;
            }
        } elseif ( $views_20s >= 10000 ) {
            if ( $ctr >= $ctr_threshold ) {
                $bonus_pct = 0.05;
            }
        }

        $base_payout = ( $views_20s / 1000 ) * $cpm;
        $bonus = $base_payout * $bonus_pct;
        $payout_cents = (int) floor( $base_payout + $bonus );

        return [
            'cpm_cents' => $cpm,
            'tier_name' => $tier_name,
            'ctr' => $ctr,
            'retention_rate' => $retention_rate,
            'bonus_pct' => $bonus_pct,
            'base_payout_cents' => (int) floor( $base_payout ),
            'bonus_cents' => (int) floor( $bonus ),
            'payout_cents' => $payout_cents,
        ];
    }

    public static function calculate_creator_breakdown_for_period( int $user_id, string $start, string $end ): array {
        global $wpdb;
        $t_rollups = $wpdb->prefix . 'lux_video_rollups';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COALESCE(SUM(impressions),0) AS impressions,
                    COALESCE(SUM(plays),0) AS plays,
                    COALESCE(SUM(views_20s),0) AS views_20s,
                    COALESCE(SUM(p75),0) AS p75
                 FROM {$t_rollups}
                 WHERE day BETWEEN %s AND %s AND owner_user_id = %d",
                $start,
                $end,
                $user_id
            ),
            ARRAY_A
        );

        $views_20s = (int) ( $row['views_20s'] ?? 0 );
        return array_merge(
            [
                'impressions' => (int) ( $row['impressions'] ?? 0 ),
                'plays' => (int) ( $row['plays'] ?? 0 ),
                'views_20s' => $views_20s,
                'p75' => (int) ( $row['p75'] ?? 0 ),
            ],
            self::calculate_payout_breakdown(
                $views_20s,
                (int) ( $row['impressions'] ?? 0 ),
                (int) ( $row['plays'] ?? 0 ),
                (int) ( $row['p75'] ?? 0 )
            )
        );
    }

    public static function get_creator_earnings_summary( int $user_id ): array {
        global $wpdb;
        $t_payouts = $wpdb->prefix . 'lux_payouts';

        $totals = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COALESCE(SUM(payout_cents),0) AS total_cents,
                    COALESCE(SUM(CASE WHEN status='paid' THEN payout_cents ELSE 0 END),0) AS paid_cents,
                    COALESCE(SUM(CASE WHEN status!='paid' THEN payout_cents ELSE 0 END),0) AS pending_cents
                 FROM {$t_payouts}
                 WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );

        $weeks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT period_start, period_end, payout_cents, status
                 FROM {$t_payouts}
                 WHERE user_id = %d
                 ORDER BY period_start DESC
                 LIMIT 12",
                $user_id
            ),
            ARRAY_A
        );

        return [
            'total_cents' => (int) ( $totals['total_cents'] ?? 0 ),
            'paid_cents' => (int) ( $totals['paid_cents'] ?? 0 ),
            'pending_cents' => (int) ( $totals['pending_cents'] ?? 0 ),
            'weekly' => $weeks,
        ];
    }

    public static function resolve_tier_name( int $views ): string {
        if ( $views >= 50000 ) {
            return 'Gold';
        }
        if ( $views >= 10000 ) {
            return 'Silver';
        }
        return 'Bronze';
    }

    public static function handle_mark_paid(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }

        check_admin_referer( 'luxvv_mark_payout_paid' );

        $payout_id = absint( $_POST['payout_id'] ?? 0 );
        $reference = sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) );
        $notes = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

        if ( ! $payout_id ) {
            wp_die( 'Invalid payout.' );
        }

        self::mark_payout_paid( $payout_id, $reference, $notes );

        wp_safe_redirect( admin_url( 'admin.php?page=luxvv-payouts&paid=1' ) );
        exit;
    }

    public static function handle_reset_payout(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }

        check_admin_referer( 'luxvv_reset_payout' );

        $payout_id = absint( $_POST['payout_id'] ?? 0 );
        $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

        if ( ! $payout_id ) {
            wp_die( 'Invalid payout.' );
        }

        self::reset_payout( $payout_id, $reason );

        wp_safe_redirect( admin_url( 'admin.php?page=luxvv-payouts&reset=1' ) );
        exit;
    }

    public static function handle_download_receipt(): void {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        $payout_id = absint( $_GET['payout_id'] ?? 0 );
        $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, 'luxvv_download_receipt_' . $payout_id ) ) {
            wp_die( 'Invalid receipt request.' );
        }

        $row = self::get_payout( $payout_id );
        if ( ! $row ) {
            wp_die( 'Receipt not found.' );
        }

        if ( ( $row['status'] ?? '' ) !== 'paid' ) {
            wp_die( 'Receipt unavailable.' );
        }

        $user_id = (int) $row['user_id'];
        if ( ! current_user_can( 'manage_options' ) && get_current_user_id() !== $user_id ) {
            wp_die( 'Unauthorized' );
        }

        $path = $row['receipt_path'] ?? '';
        if ( ! $path || ! file_exists( $path ) ) {
            $path = self::generate_receipt( $row );
        }

        if ( ! $path || ! file_exists( $path ) ) {
            wp_die( 'Receipt missing.' );
        }

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="luxvv-receipt-' . $payout_id . '.pdf"' );
        readfile( $path );
        exit;
    }

    public static function mark_payout_paid( int $payout_id, string $reference = '', string $notes = '' ): void {
        global $wpdb;
        $t_payouts = $wpdb->prefix . 'lux_payouts';

        $row = self::get_payout( $payout_id );
        if ( ! $row ) {
            return;
        }

        $paid_at = current_time( 'mysql' );
        $paid_by = get_current_user_id();
        $path = self::generate_receipt( $row, $paid_at, $paid_by, $reference, $notes );

        $wpdb->update(
            $t_payouts,
            [
                'status' => 'paid',
                'paid_at' => $paid_at,
                'paid_by' => $paid_by,
                'paid_reference' => $reference,
                'paid_notes' => $notes,
                'receipt_path' => $path,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $payout_id ],
            [ '%s', '%s', '%d', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    public static function reset_payout( int $payout_id, string $reason = '' ): void {
        global $wpdb;
        $t_payouts = $wpdb->prefix . 'lux_payouts';
        $t_resets = $wpdb->prefix . 'lux_payout_resets';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$t_payouts}
                 SET status='pending',
                     paid_at=NULL,
                     paid_by=NULL,
                     paid_reference=NULL,
                     paid_notes=NULL,
                     receipt_path=NULL,
                     updated_at=%s
                 WHERE id=%d",
                current_time( 'mysql' ),
                $payout_id
            )
        );

        $wpdb->insert(
            $t_resets,
            [
                'payout_id' => $payout_id,
                'admin_user_id' => get_current_user_id(),
                'reason' => $reason,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s' ]
        );
    }

    private static function get_payout( int $payout_id ): ?array {
        global $wpdb;
        $t_payouts = $wpdb->prefix . 'lux_payouts';
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$t_payouts} WHERE id = %d", $payout_id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    private static function generate_receipt(
        array $row,
        ?string $paid_at = null,
        ?int $paid_by = null,
        string $reference = '',
        string $notes = ''
    ): string {
        $user = get_userdata( (int) $row['user_id'] );
        $paid_at = $paid_at ?: (string) ( $row['paid_at'] ?? current_time( 'mysql' ) );
        $paid_by = $paid_by ?: (int) ( $row['paid_by'] ?? 0 );
        $reference = $reference ?: (string) ( $row['paid_reference'] ?? '' );
        $notes = $notes ?: (string) ( $row['paid_notes'] ?? '' );

        $p75 = 0;
        if ( isset( $row['retention_rate'] ) ) {
            $p75 = (int) floor( (float) $row['retention_rate'] * (int) $row['views_20s'] );
        }

        $calc = self::calculate_payout_breakdown(
            (int) $row['views_20s'],
            (int) $row['impressions'],
            (int) $row['plays'],
            $p75
        );

        $data = [
            'payout_id' => (int) $row['id'],
            'creator_name' => $user ? $user->display_name : 'Unknown',
            'creator_email' => $user ? $user->user_email : '',
            'period_start' => $row['period_start'] ?? '',
            'period_end' => $row['period_end'] ?? '',
            'views_20s' => (int) $row['views_20s'],
            'cpm_cents' => (int) $row['cpm_cents'],
            'base_payout_cents' => (int) $calc['base_payout_cents'],
            'bonus_pct' => (float) ( $row['bonus_pct'] ?? 0 ),
            'payout_cents' => (int) $row['payout_cents'],
            'paid_at' => $paid_at,
            'paid_by' => $paid_by,
            'paid_reference' => $reference,
            'paid_notes' => $notes,
        ];

        $path = PDF::generate_payout_receipt( $data );

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'lux_payouts',
            [
                'receipt_path' => $path,
            ],
            [ 'id' => (int) $row['id'] ],
            [ '%s' ],
            [ '%d' ]
        );

        return $path;
    }

    public static function add_cron_schedules( array $schedules ): array {
        if ( ! isset( $schedules['yearly'] ) ) {
            $schedules['yearly'] = [
                'interval' => 365 * DAY_IN_SECONDS,
                'display' => 'Once Yearly',
            ];
        }
        return $schedules;
    }

    public static function send_yearly_1099s(): void {
        global $wpdb;

        $year = (int) gmdate( 'Y', current_time( 'timestamp' ) ) - 1;
        $start = "{$year}-01-01";
        $end = "{$year}-12-31";
        $threshold_cents = (int) Settings::get( 'tax_1099_threshold_cents', 60000 );

        $t_payouts = $wpdb->prefix . 'lux_payouts';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, SUM(payout_cents) AS total_cents
                 FROM {$t_payouts}
                 WHERE period_end BETWEEN %s AND %s
                 GROUP BY user_id",
                $start,
                $end
            ),
            ARRAY_A
        );

        if ( ! $rows ) {
            return;
        }

        foreach ( $rows as $row ) {
            $user_id = (int) $row['user_id'];
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                continue;
            }

            $total = (int) $row['total_cents'];
            if ( $total < $threshold_cents ) {
                continue;
            }
            $amount = number_format( $total / 100, 2 );

            $subject = "1099-NEC Summary for {$year}";
            $body = "Hi {$user->display_name},\n\n".
                "Your total creator earnings for {$year} were \${$amount}.\n\n".
                "This is a summary to support tax filing. Please keep this for your records.\n\n".
                "— Lucifer Cruz Studios";

            wp_mail( $user->user_email, $subject, $body );

            $admin_email = get_option( 'admin_email' );
            if ( $admin_email ) {
                wp_mail( $admin_email, "Creator 1099-NEC Summary {$year}", "{$user->display_name} ({$user->user_email}): \${$amount}" );
            }
        }
    }

    public static function get_annual_earnings_for_export( int $year, int $threshold_cents ): array {
        global $wpdb;

        $start = "{$year}-01-01";
        $end = "{$year}-12-31";
        $t_payouts = $wpdb->prefix . 'lux_payouts';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, SUM(payout_cents) AS total_cents
                 FROM {$t_payouts}
                 WHERE period_end BETWEEN %s AND %s
                 GROUP BY user_id
                 HAVING total_cents >= %d
                 ORDER BY total_cents DESC",
                $start,
                $end,
                $threshold_cents
            ),
            ARRAY_A
        );

        if ( ! $rows ) {
            return [];
        }

        $output = [];
        foreach ( $rows as $row ) {
            $user_id = (int) $row['user_id'];
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                continue;
            }

            $last4 = get_user_meta( $user_id, 'luxvv_w9_tax_id_last4', true );
            $tax_masked = $last4 ? '***-**-' . $last4 : '—';
            $gross = (int) $row['total_cents'];
            $fees = 0;
            $net = $gross - $fees;

            $output[] = [
                'user_id' => $user_id,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'tax_id_masked' => $tax_masked,
                'gross_earnings' => number_format( $gross / 100, 2 ),
                'platform_fees' => number_format( $fees / 100, 2 ),
                'net_payout' => number_format( $net / 100, 2 ),
            ];
        }

        return $output;
    }

    public static function handle_export_1099(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }

        check_admin_referer( 'luxvv_export_1099' );

        $year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) gmdate( 'Y', current_time( 'timestamp' ) );
        $threshold_cents = (int) Settings::get( 'tax_1099_threshold_cents', 60000 );

        $rows = self::get_annual_earnings_for_export( $year, $threshold_cents );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename=luxvv-1099-' . $year . '.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Creator', 'Email', 'Tax ID (Masked)', 'Gross Earnings', 'Platform Fees', 'Net Payout' ] );
        foreach ( $rows as $row ) {
            fputcsv( $out, [
                $row['name'],
                $row['email'],
                $row['tax_id_masked'],
                $row['gross_earnings'],
                $row['platform_fees'],
                $row['net_payout'],
            ] );
        }
        fclose( $out );
        exit;
    }
}
