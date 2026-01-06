<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Install {

    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tbl_members   = $wpdb->prefix . 'lux_verified_members';
        $tbl_videos    = $wpdb->prefix . 'lux_videos';
        $tbl_events    = $wpdb->prefix . 'lux_video_events';
        $tbl_actors    = $wpdb->prefix . 'lux_video_actors';
        $tbl_payouts   = $wpdb->prefix . 'lux_payouts';
        $tbl_resets    = $wpdb->prefix . 'lux_payout_resets';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql1 = "CREATE TABLE $tbl_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            selfie_id BIGINT UNSIGNED DEFAULT 0,
            contract_status VARCHAR(30) DEFAULT 'pending',
            profile_complete TINYINT(1) DEFAULT 0,
            admin_status VARCHAR(30) DEFAULT 'pending',
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

        $sql5 = "CREATE TABLE $tbl_payouts (
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

        $sql6 = "CREATE TABLE $tbl_resets (
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

        add_option( 'luxvv_bunny_library_id', '494980' );
        add_option( 'luxvv_bunny_hostname', 'vz-d469c60f-3ca.b-cdn.net' );
        add_option( 'luxvv_bunny_api_key', '1d5c0661-57c5-4a27-bcc5f8977272-4924-4883' );
        add_option( 'luxvv_bunny_webhook_url', 'https://lucifercruz.com/wp-json/luxvv/v1/videos/webhook' );
        add_option( 'luxvv_forminator_id', '11099' );
        add_option( 'luxvv_regmagic_shortcode', "[RM_Forms id='1']" );
        add_option( 'luxvv_w9_iframe', 'https://adiken.na4.documents.adobe.com/public/esignWidget?wid=CBFCIBAA3AAABLblqZhBsm1v6MiHht-eSYJwiIo6J5loh-RkG0jeTMWVDXfO84eaez9VzmK96JEZRPV-Tp2E*' );
        add_option( 'luxvv_badge_location', 'both' );
    }
}
