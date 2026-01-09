<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Install {

    private const REQUIRED_TABLES = [
        'lux_videos',
        'lux_video_events',
        'lux_video_rollups',
        'lux_verified_members',
        'lux_payouts',
        'lux_payout_resets',
    ];

    public static function init(): void {
        register_activation_hook( LUXVV_PATH . 'lux-verified-video.php', [ __CLASS__, 'activate' ] );
        add_action( 'admin_notices', [ __CLASS__, 'render_missing_tables_notice' ] );

        if ( get_option( 'luxvv_version' ) !== LUXVV_VERSION ) {
            update_option( 'luxvv_version', LUXVV_VERSION, false );
        }
    }

    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tbl_members   = $wpdb->prefix . 'lux_verified_members';
        $tbl_videos    = $wpdb->prefix . 'lux_videos';
        $tbl_events    = $wpdb->prefix . 'lux_video_events';
        $tbl_actors    = $wpdb->prefix . 'lux_video_actors';
        $tbl_rollups   = $wpdb->prefix . 'lux_video_rollups';
        $tbl_payouts   = $wpdb->prefix . 'lux_payouts';
        $tbl_resets    = $wpdb->prefix . 'lux_payout_resets';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql1 = "CREATE TABLE $tbl_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            verification_status VARCHAR(30) DEFAULT 'started',
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        $sql2 = "CREATE TABLE $tbl_videos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            post_id BIGINT UNSIGNED DEFAULT 0,
            bunny_guid VARCHAR(255) NOT NULL,
            title VARCHAR(255) NOT NULL,
            thumb_url TEXT NULL,
            duration INT DEFAULT 0,
            status VARCHAR(30) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY bunny_guid (bunny_guid),
            KEY user_id (user_id)
        ) $charset_collate;";

        $sql3 = "CREATE TABLE $tbl_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            video_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT 0,
            event_type VARCHAR(50) NOT NULL,
            watch_seconds INT DEFAULT 0,
            ip_hash VARCHAR(64) NULL,
            ua_hash VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY video_id (video_id),
            KEY event_type (event_type)
        ) $charset_collate;";

        $sql4 = "CREATE TABLE $tbl_actors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            video_id BIGINT UNSIGNED NOT NULL,
            actor_user_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY video_id (video_id),
            KEY actor_user_id (actor_user_id)
        ) $charset_collate;";

        $sql5 = "CREATE TABLE $tbl_rollups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            video_id BIGINT UNSIGNED NOT NULL,
            impressions BIGINT UNSIGNED DEFAULT 0,
            plays BIGINT UNSIGNED DEFAULT 0,
            views_20s BIGINT UNSIGNED DEFAULT 0,
            completes BIGINT UNSIGNED DEFAULT 0,
            watch_seconds BIGINT UNSIGNED DEFAULT 0,
            last_synced DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY video_id (video_id)
        ) $charset_collate;";

        $sql6 = "CREATE TABLE $tbl_payouts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            period VARCHAR(20) NOT NULL,
            views INT DEFAULT 0,
            revenue DECIMAL(10,2) DEFAULT 0.00,
            is_paid TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY period (period)
        ) $charset_collate;";

        $sql7 = "CREATE TABLE $tbl_resets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT 0,
            period VARCHAR(20) DEFAULT '',
            note TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
        dbDelta( $sql4 );
        dbDelta( $sql5 );
        dbDelta( $sql6 );
        dbDelta( $sql7 );

        add_option( 'luxvv_bunny_library_id', '494980' );
        add_option( 'luxvv_bunny_hostname', 'vz-d469c60f-3ca.b-cdn.net' );
        add_option( 'luxvv_bunny_api_key', '1d5c0661-57c5-4a27-bcc5f8977272-4924-4883' );
        add_option( 'luxvv_bunny_webhook_url', 'https://lucifercruz.com/wp-json/luxvv/v1/videos/webhook' );
        add_option( 'luxvv_forminator_id', '11099' );
        add_option( 'luxvv_regmagic_shortcode', "[RM_Forms id='1']" );
        add_option( 'luxvv_w9_iframe', 'https://adiken.na4.documents.adobe.com/public/esignWidget?wid=CBFCIBAA3AAABLblqZhBsm1v6MiHht-eSYJwiIo6J5loh-RkG0jeTMWVDXfO84eaez9VzmK96JEZRPV-Tp2E*' );
        add_option( 'luxvv_badge_location', 'both' );

        $missing = self::missing_tables();
        if ( ! empty( $missing ) ) {
            if ( ! function_exists( 'deactivate_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            deactivate_plugins( plugin_basename( LUXVV_PATH . 'lux-verified-video.php' ) );
            wp_die( 'LUX Verified Video activation failed. Missing tables: ' . esc_html( implode( ', ', $missing ) ) );
        }
    }

    public static function render_missing_tables_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $missing = self::missing_tables();
        if ( empty( $missing ) ) {
            return;
        }

        $table_list = implode( ', ', array_map( 'esc_html', $missing ) );
        $repair_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=luxvv_rebuild_tables' ),
            'luxvv_rebuild_tables'
        );
        ?>
        <div class="notice notice-error">
            <p><?php echo esc_html__( 'LUX Verified Video: missing required tables.', 'lux-verified-video' ); ?></p>
            <p><?php echo esc_html( $table_list ); ?></p>
            <p><a href="<?php echo esc_url( $repair_url ); ?>" class="button button-primary">Run Repair → Rebuild Tables</a></p>
        </div>
        <?php
    }

    public static function missing_tables(): array {
        global $wpdb;

        $missing = [];
        foreach ( self::REQUIRED_TABLES as $table ) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var(
                $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
            );
            if ( $exists !== $table_name ) {
                $missing[] = $table_name;
            }
        }

        return $missing;
    }
}
