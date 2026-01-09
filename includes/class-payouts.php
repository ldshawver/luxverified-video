<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Payouts {

    private static $instance = null;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_post_luxvv_export_1099', [ $this, 'export_1099' ] );
    }

    public function calculate_user_period( int $user_id, string $period ) : array {
        return [
            'user_id' => $user_id,
            'period'  => $period,
            'views'   => 0,
            'revenue' => 0.00,
        ];
    }

    public function export_1099(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }

        check_admin_referer( 'luxvv_export_1099' );

        $year = isset( $_POST['tax_year'] ) ? (int) $_POST['tax_year'] : (int) gmdate( 'Y' );
        $threshold = (float) Settings::get( 'payout_1099_threshold', 600 );

        global $wpdb;
        $table = $wpdb->prefix . 'lux_payouts';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT user_id, SUM(revenue) AS total_revenue, SUM(views) AS total_views
                FROM {$table}
                WHERE YEAR(created_at) = %d
                GROUP BY user_id
                HAVING SUM(revenue) >= %f
                ORDER BY total_revenue DESC
                ",
                $year,
                $threshold
            ),
            ARRAY_A
        );

        nocache_headers();
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="luxvv-1099-' . $year . '.csv"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [ 'user_id', 'name', 'email', 'total_revenue', 'tax_id_masked' ] );

        foreach ( $rows as $row ) {
            $user_id = (int) $row['user_id'];
            $user = get_user_by( 'id', $user_id );
            if ( ! $user ) {
                continue;
            }

            $name = trim( $user->first_name . ' ' . $user->last_name );
            $tax_masked = (string) get_user_meta( $user_id, Verification::TAX_ID_MASKED_META, true );

            fputcsv( $output, [
                $user_id,
                $name,
                $user->user_email,
                number_format( (float) $row['total_revenue'], 2, '.', '' ),
                $tax_masked,
            ] );
        }

        fclose( $output );
        exit;
    }
}
