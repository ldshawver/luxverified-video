<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LUXVV_Plugin {
    private static $instance = null;

    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_post_luxvv_upload_video', [ $this, 'handle_upload' ] );
        add_action( 'admin_post_luxvv_toggle_favorite', [ $this, 'handle_toggle_favorite' ] );
        add_action( 'admin_post_luxvv_set_featured', [ $this, 'handle_set_featured' ] );
        add_action( 'admin_post_luxvv_export_payouts', [ $this, 'handle_export_payouts' ] );
        add_shortcode( 'luxvv_upload', [ $this, 'render_upload' ] );
        add_shortcode( 'luxvv_upload_form', [ $this, 'render_upload_form' ] );
        add_shortcode( 'luxvv_creator_videos', [ $this, 'render_creator_videos' ] );
        add_shortcode( 'luxvv_creator_statistics', [ $this, 'render_creator_statistics' ] );
        add_shortcode( 'luxvv_creator_payouts', [ $this, 'render_creator_payouts' ] );
        add_shortcode( 'luxvv_creator_dashboard', [ $this, 'render_creator_dashboard' ] );
    }

    public function enqueue_frontend_assets(): void {
        if ( ! is_singular() ) { return; }

        $post_id = get_queried_object_id();
        if ( ! $post_id ) { return; }

        $video_guid = (string) get_post_meta( $post_id, '_luxvv_video_guid', true );
        if ( ! $video_guid ) { return; }

        wp_enqueue_script(
            'luxvv-player-tracking',
            LUXVV_URL . 'assets/player-tracking.js',
            [],
            LUXVV_VERSION,
            true
        );

        wp_localize_script(
            'luxvv-player-tracking',
            'luxvvPlayer',
            [
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'luxvv_track_event' ),
                'postId'    => (int) $post_id,
                'videoGuid' => $video_guid,
                'timeUpdateInterval' => 15,
                'debug'     => false,
            ]
        );
    }

    public function render_upload(): string {
        $this->enqueue_frontend_style();
        if ( ! is_user_logged_in() ) {
            return '<div class="luxvv-blocked">Please log in to upload.</div>';
        }

        if ( ! class_exists( '\\LuxVerified\\Verification' ) || ! \LuxVerified\Verification::can_upload( get_current_user_id() ) ) {
            $apply_url = 'https://lucifercruz.com/about-lucifer-cruz-studios/content-creater-agreement/';
            return '<div class="luxvv-blocked">Become a verified LUX creator to upload content and start making money. <a href="' . esc_url( $apply_url ) . '">Apply now</a>.</div>';
        }

        return '<div class="luxvv-allowed">Uploads are enabled for your account.</div>' . $this->render_upload_form();
    }

    public function render_upload_form(): string {
        $this->enqueue_frontend_style();
        if ( ! is_user_logged_in() ) {
            return '<div class="luxvv-blocked">Please log in to upload.</div>';
        }

        if ( ! class_exists( '\\LuxVerified\\Verification' ) || ! \LuxVerified\Verification::can_upload( get_current_user_id() ) ) {
            $apply_url = 'https://lucifercruz.com/about-lucifer-cruz-studios/content-creater-agreement/';
            return '<div class="luxvv-blocked">Become a verified LUX creator to upload content and start making money. <a href="' . esc_url( $apply_url ) . '">Apply now</a>.</div>';
        }

        $nonce = wp_create_nonce( 'luxvv_upload_video' );

        ob_start();
        ?>
        <form class="luxvv-upload-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="luxvv_upload_video">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">

            <p>
                <label>Title</label><br>
                <input type="text" name="luxvv_title" required>
            </p>
            <p>
                <label>Description</label><br>
                <textarea name="luxvv_description" rows="4"></textarea>
            </p>
            <p>
                <label>Categories (IDs, comma-separated)</label><br>
                <input type="text" name="luxvv_categories" placeholder="12, 18">
            </p>
            <p>
                <label>Actors / Tags (comma-separated)</label><br>
                <input type="text" name="luxvv_actors" placeholder="Actor Name, Co-Star">
            </p>
            <p>
                <label>Upload Video</label><br>
                <input type="file" name="luxvv_video_file" accept="video/*" required>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="luxvv_featured" value="1">
                    Set as featured video
                </label>
            </p>
            <p>
                <button type="submit">Upload Video</button>
            </p>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    public function render_creator_videos(): string {
        $this->enqueue_frontend_style();
        if ( ! is_user_logged_in() ) {
            return '<div class="luxvv-blocked">Please log in to view your videos.</div>';
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'lux_videos';
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
        ) === $table;
        if ( ! $table_exists ) {
            return '<div class="luxvv-blocked">Video table not found.</div>';
        }

        $status = isset( $_GET['luxvv_status'] ) ? sanitize_key( wp_unslash( $_GET['luxvv_status'] ) ) : '';
        $search = isset( $_GET['luxvv_search'] ) ? sanitize_text_field( wp_unslash( $_GET['luxvv_search'] ) ) : '';
        $favorites_only = isset( $_GET['luxvv_favorites'] ) ? (bool) absint( $_GET['luxvv_favorites'] ) : false;

        $favorites = (array) get_user_meta( $user_id, 'luxvv_favorite_videos', true );
        $featured = (int) get_user_meta( $user_id, 'luxvv_featured_video', true );

        $where = 'WHERE owner_user_id = %d';
        $params = [ $user_id ];

        if ( $status ) {
            $where .= ' AND status = %s';
            $params[] = $status;
        }

        if ( $search ) {
            $where .= ' AND (title LIKE %s OR bunny_video_guid LIKE %s)';
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY created_at DESC",
                $params
            ),
            ARRAY_A
        );

        if ( $favorites_only ) {
            $rows = array_values( array_filter( $rows, function ( $row ) use ( $favorites ) {
                return in_array( (int) $row['id'], $favorites, true );
            } ) );
        }

        ob_start();
        ?>
        <div class="luxvv-creator-videos">
            <form method="get" class="luxvv-video-filters">
                <input type="text" name="luxvv_search" placeholder="Search videos" value="<?php echo esc_attr( $search ); ?>">
                <select name="luxvv_status">
                    <option value="">All Statuses</option>
                    <option value="uploading" <?php selected( $status, 'uploading' ); ?>>Uploading</option>
                    <option value="processing" <?php selected( $status, 'processing' ); ?>>Processing</option>
                    <option value="ready" <?php selected( $status, 'ready' ); ?>>Ready</option>
                </select>
                <label>
                    <input type="checkbox" name="luxvv_favorites" value="1" <?php checked( $favorites_only ); ?>>
                    Favorites only
                </label>
                <button type="submit">Filter</button>
            </form>

            <?php if ( empty( $rows ) ) : ?>
                <p>No videos found.</p>
            <?php else : ?>
                <ul class="luxvv-video-list">
                    <?php foreach ( $rows as $row ) : ?>
                        <?php
                        $video_id = (int) $row['id'];
                        $is_favorite = in_array( $video_id, $favorites, true );
                        $is_featured = ( $featured === $video_id );
                        ?>
                        <li class="luxvv-video-card">
                            <strong><?php echo esc_html( $row['title'] ?: $row['bunny_video_guid'] ); ?></strong>
                            <div class="luxvv-video-meta">
                                <span>Status: <?php echo esc_html( $row['status'] ); ?></span>
                                <?php if ( $is_featured ) : ?><span class="luxvv-chip">Featured</span><?php endif; ?>
                                <?php if ( $is_favorite ) : ?><span class="luxvv-chip">Favorite</span><?php endif; ?>
                            </div>
                            <div class="luxvv-video-actions">
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <input type="hidden" name="action" value="luxvv_toggle_favorite">
                                    <input type="hidden" name="video_id" value="<?php echo esc_attr( $video_id ); ?>">
                                    <?php wp_nonce_field( 'luxvv_toggle_favorite' ); ?>
                                    <button type="submit"><?php echo $is_favorite ? 'Unfavorite' : 'Favorite'; ?></button>
                                </form>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <input type="hidden" name="action" value="luxvv_set_featured">
                                    <input type="hidden" name="video_id" value="<?php echo esc_attr( $video_id ); ?>">
                                    <?php wp_nonce_field( 'luxvv_set_featured' ); ?>
                                    <button type="submit">Set Featured</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function render_creator_statistics(): string {
        $this->enqueue_frontend_style();
        if ( ! is_user_logged_in() ) {
            return '<div class="luxvv-blocked">Please log in to view statistics.</div>';
        }

        $user_id = get_current_user_id();
        $summary = class_exists( '\\LuxVerified\\Analytics' )
            ? \LuxVerified\Analytics::get_creator_summary( $user_id, 30 )
            : [];

        $rollups = class_exists( '\\LuxVerified\\Analytics' )
            ? \LuxVerified\Analytics::get_creator_rollups( $user_id, 30 )
            : [];

        $max_views = 1;
        foreach ( $rollups as $row ) {
            $max_views = max( $max_views, (int) $row['views_20s'] );
        }

        ob_start();
        ?>
        <div class="luxvv-creator-stats">
            <h3>Creator Statistics (Last <?php echo (int) ( $summary['days'] ?? 30 ); ?> days)</h3>
            <div class="luxvv-cards">
                <div class="luxvv-card">
                    <div class="luxvv-card-label">Impressions</div>
                    <div class="luxvv-card-value"><?php echo number_format_i18n( (int) ( $summary['impressions'] ?? 0 ) ); ?></div>
                </div>
                <div class="luxvv-card">
                    <div class="luxvv-card-label">Plays</div>
                    <div class="luxvv-card-value"><?php echo number_format_i18n( (int) ( $summary['plays'] ?? 0 ) ); ?></div>
                </div>
                <div class="luxvv-card">
                    <div class="luxvv-card-label">Views ≥ 20s</div>
                    <div class="luxvv-card-value"><?php echo number_format_i18n( (int) ( $summary['views_20s'] ?? 0 ) ); ?></div>
                </div>
                <div class="luxvv-card">
                    <div class="luxvv-card-label">CTR</div>
                    <div class="luxvv-card-value"><?php echo esc_html( number_format_i18n( ( $summary['ctr'] ?? 0 ) * 100, 1 ) ); ?>%</div>
                </div>
                <div class="luxvv-card">
                    <div class="luxvv-card-label">Retention (75%)</div>
                    <div class="luxvv-card-value"><?php echo esc_html( number_format_i18n( ( $summary['retention_rate'] ?? 0 ) * 100, 1 ) ); ?>%</div>
                </div>
                <div class="luxvv-card">
                    <div class="luxvv-card-label">Watch Minutes</div>
                    <div class="luxvv-card-value"><?php echo number_format_i18n( (int) floor( (int) ( $summary['watch_seconds'] ?? 0 ) / 60 ) ); ?></div>
                </div>
            </div>

            <div class="luxvv-chart">
                <?php if ( empty( $rollups ) ) : ?>
                    <p>No rollup data yet.</p>
                <?php else : ?>
                    <?php foreach ( $rollups as $row ) : ?>
                        <?php
                        $width = $max_views > 0 ? ( (int) $row['views_20s'] / $max_views ) * 100 : 0;
                        ?>
                        <div class="luxvv-chart-row">
                            <span><?php echo esc_html( $row['day'] ); ?></span>
                            <div class="luxvv-chart-bar">
                                <div class="luxvv-chart-fill" style="width: <?php echo esc_attr( $width ); ?>%"></div>
                            </div>
                            <strong><?php echo number_format_i18n( (int) $row['views_20s'] ); ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function render_creator_payouts(): string {
        $this->enqueue_frontend_style();
        if ( ! is_user_logged_in() ) {
            return '<div class="luxvv-blocked">Please log in to view payouts.</div>';
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'lux_payouts';
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
        ) === $table;
        if ( ! $table_exists ) {
            return '<div class="luxvv-blocked">Payout table not found.</div>';
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY period_start DESC LIMIT 24",
                $user_id
            ),
            ARRAY_A
        );

        $breakdown = class_exists( '\\LuxVerified\\Payouts' )
            ? \LuxVerified\Payouts::calculate_creator_breakdown_for_period(
                $user_id,
                gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
                gmdate( 'Y-m-d', strtotime( 'yesterday' ) )
            )
            : [];

        $ctr_threshold = (float) \LuxVerified\Settings::get( 'payout_ctr_bonus_threshold', 0.05 );
        $retention_threshold = (float) \LuxVerified\Settings::get( 'payout_retention_bonus_threshold', 0.75 );

        $export_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=luxvv_export_payouts' ),
            'luxvv_export_payouts'
        );

        ob_start();
        ?>
        <div class="luxvv-creator-payouts">
            <h3>Weekly Payout Preview</h3>
            <?php if ( $breakdown ) : ?>
                <ul>
                    <li>Impressions: <?php echo number_format_i18n( (int) $breakdown['impressions'] ); ?></li>
                    <li>Views ≥ 20s: <?php echo number_format_i18n( (int) $breakdown['views_20s'] ); ?></li>
                    <li>CTR: <?php echo esc_html( number_format_i18n( $breakdown['ctr'] * 100, 2 ) ); ?>% (Bonus threshold: <?php echo esc_html( number_format_i18n( $ctr_threshold * 100, 1 ) ); ?>%)</li>
                    <li>Retention (75%): <?php echo esc_html( number_format_i18n( $breakdown['retention_rate'] * 100, 2 ) ); ?>% (Bonus threshold: <?php echo esc_html( number_format_i18n( $retention_threshold * 100, 1 ) ); ?>%)</li>
                    <li>Tier: <?php echo esc_html( $breakdown['tier_name'] ?? 'Bronze' ); ?></li>
                    <li>Base CPM: <?php echo number_format_i18n( (int) $breakdown['cpm_cents'] ); ?>¢</li>
                    <li>Base Payout: <?php echo number_format_i18n( (int) $breakdown['base_payout_cents'] ); ?>¢</li>
                    <li>Bonus: <?php echo esc_html( number_format_i18n( $breakdown['bonus_pct'] * 100, 1 ) ); ?>% (<?php echo number_format_i18n( (int) $breakdown['bonus_cents'] ); ?>¢)</li>
                    <li><strong>Estimated Payout: <?php echo number_format_i18n( (int) $breakdown['payout_cents'] ); ?>¢</strong></li>
                </ul>
            <?php endif; ?>

            <h3>Payout History</h3>
            <p><a href="<?php echo esc_url( $export_url ); ?>">Export CSV</a></p>
            <?php if ( empty( $rows ) ) : ?>
                <p>No payouts yet.</p>
            <?php else : ?>
                <table>
                    <thead>
                    <tr>
                        <th>Period</th>
                        <th>Views ≥ 20s</th>
                        <th>CPM</th>
                        <th>Bonus</th>
                        <th>Payout</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row['period_start'] ); ?> → <?php echo esc_html( $row['period_end'] ); ?></td>
                            <td><?php echo number_format_i18n( (int) $row['views_20s'] ); ?></td>
                            <td><?php echo number_format_i18n( (int) $row['cpm_cents'] ); ?>¢</td>
                            <td><?php echo esc_html( number_format_i18n( (float) $row['bonus_pct'] * 100, 1 ) ); ?>%</td>
                            <td><?php echo number_format_i18n( (int) $row['payout_cents'] ); ?>¢</td>
                            <td><?php echo esc_html( $row['status'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function render_creator_dashboard(): string {
        $this->enqueue_frontend_style();
        if ( ! is_user_logged_in() ) {
            return '<div class="luxvv-blocked">Please log in to view your dashboard.</div>';
        }

        $user_id = get_current_user_id();
        $status = class_exists( '\\LuxVerified\\Verification' )
            ? \LuxVerified\Verification::derive_status_from_meta( $user_id )
            : 'unknown';

        return '<div class="luxvv-creator-dashboard"><strong>Status:</strong> ' . esc_html( $status ) . '</div>';
    }

    private function enqueue_frontend_style(): void {
        wp_enqueue_style(
            'luxvv-frontend',
            LUXVV_URL . 'assets/frontend.css',
            [],
            LUXVV_VERSION
        );
    }

    public function handle_upload(): void {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'luxvv_upload_video' ) ) {
            wp_die( 'Invalid nonce.' );
        }

        $user_id = get_current_user_id();
        if ( ! class_exists( '\\LuxVerified\\Verification' ) || ! \LuxVerified\Verification::can_upload( $user_id ) ) {
            wp_die( 'Verification required.' );
        }

        $title = sanitize_text_field( wp_unslash( $_POST['luxvv_title'] ?? '' ) );
        $description = wp_kses_post( wp_unslash( $_POST['luxvv_description'] ?? '' ) );
        $categories = sanitize_text_field( wp_unslash( $_POST['luxvv_categories'] ?? '' ) );
        $actors = sanitize_text_field( wp_unslash( $_POST['luxvv_actors'] ?? '' ) );
        $featured = ! empty( $_POST['luxvv_featured'] );

        if ( empty( $_FILES['luxvv_video_file']['name'] ) ) {
            wp_die( 'No file uploaded.' );
        }

        $library_id = \LuxVerified\Settings::get( 'bunny_library_id' );
        $api_key = \LuxVerified\Settings::get( 'bunny_api_key' );
        $cdn_host = \LuxVerified\Settings::get( 'bunny_cdn_host' );

        if ( ! $library_id || ! $api_key ) {
            wp_die( 'Bunny Stream settings are missing.' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload = wp_handle_upload( $_FILES['luxvv_video_file'], [ 'test_form' => false ] );
        if ( ! empty( $upload['error'] ) ) {
            wp_die( esc_html( $upload['error'] ) );
        }

        $create_response = wp_remote_post(
            "https://video.bunnycdn.com/library/{$library_id}/videos",
            [
                'headers' => [
                    'AccessKey' => $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode( [ 'title' => $title ?: 'Creator Upload' ] ),
                'timeout' => 60,
            ]
        );

        if ( is_wp_error( $create_response ) ) {
            wp_die( esc_html( $create_response->get_error_message() ) );
        }

        $create_body = json_decode( wp_remote_retrieve_body( $create_response ), true );
        $guid = $create_body['guid'] ?? '';
        if ( ! $guid ) {
            wp_die( 'Unable to create Bunny video.' );
        }

        $file_contents = file_get_contents( $upload['file'] );
        $upload_response = wp_remote_request(
            "https://video.bunnycdn.com/library/{$library_id}/videos/{$guid}",
            [
                'method' => 'PUT',
                'headers' => [
                    'AccessKey' => $api_key,
                ],
                'body' => $file_contents,
                'timeout' => 300,
            ]
        );

        if ( is_wp_error( $upload_response ) ) {
            wp_die( esc_html( $upload_response->get_error_message() ) );
        }
        $status_code = (int) wp_remote_retrieve_response_code( $upload_response );
        if ( $status_code < 200 || $status_code >= 300 ) {
            wp_die( 'Upload failed with Bunny Stream.' );
        }

        if ( ! empty( $upload['file'] ) && file_exists( $upload['file'] ) ) {
            unlink( $upload['file'] );
        }

        $cdn_url = $cdn_host ? "https://{$cdn_host}/{$guid}/play_720p.mp4" : '';
        $embed_url = "https://iframe.mediadelivery.net/embed/{$library_id}/{$guid}";

        $post_id = wp_insert_post(
            [
                'post_title' => $title ?: 'Creator Video',
                'post_content' => '<iframe src="' . esc_url( $embed_url ) . '" allowfullscreen></iframe>',
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_author' => $user_id,
            ]
        );

        if ( ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, '_luxvv_video_guid', $guid );
            update_post_meta( $post_id, '_luxvv_video_cdn_url', $cdn_url );

            if ( $categories ) {
                $cat_ids = array_filter( array_map( 'absint', explode( ',', $categories ) ) );
                if ( $cat_ids ) {
                    wp_set_post_categories( $post_id, $cat_ids, false );
                }
            }

            if ( $actors ) {
                $actors_list = array_filter( array_map( 'trim', explode( ',', $actors ) ) );
                if ( taxonomy_exists( 'actors' ) ) {
                    wp_set_object_terms( $post_id, $actors_list, 'actors', false );
                } else {
                    wp_set_post_tags( $post_id, $actors_list, false );
                }
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'lux_videos';
        $wpdb->insert(
            $table,
            [
                'post_id' => is_wp_error( $post_id ) ? 0 : (int) $post_id,
                'owner_user_id' => $user_id,
                'title' => $title,
                'bunny_video_guid' => $guid,
                'status' => 'processing',
                'cdn_url' => $cdn_url,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        $video_id = (int) $wpdb->insert_id;
        if ( $featured && $video_id ) {
            update_user_meta( $user_id, 'luxvv_featured_video', $video_id );
        }

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
        exit;
    }

    public function handle_toggle_favorite(): void {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        check_admin_referer( 'luxvv_toggle_favorite' );

        $video_id = absint( $_POST['video_id'] ?? 0 );
        if ( ! $video_id ) {
            wp_die( 'Invalid video.' );
        }

        $user_id = get_current_user_id();
        $favorites = (array) get_user_meta( $user_id, 'luxvv_favorite_videos', true );

        if ( in_array( $video_id, $favorites, true ) ) {
            $favorites = array_values( array_diff( $favorites, [ $video_id ] ) );
        } else {
            $favorites[] = $video_id;
        }

        update_user_meta( $user_id, 'luxvv_favorite_videos', $favorites );

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
        exit;
    }

    public function handle_set_featured(): void {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        check_admin_referer( 'luxvv_set_featured' );

        $video_id = absint( $_POST['video_id'] ?? 0 );
        if ( ! $video_id ) {
            wp_die( 'Invalid video.' );
        }

        update_user_meta( get_current_user_id(), 'luxvv_featured_video', $video_id );

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
        exit;
    }

    public function handle_export_payouts(): void {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        check_admin_referer( 'luxvv_export_payouts' );

        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'lux_payouts';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT period_start, period_end, views_20s, cpm_cents, bonus_pct, payout_cents, status
                 FROM {$table}
                 WHERE user_id = %d
                 ORDER BY period_start DESC",
                $user_id
            ),
            ARRAY_A
        );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename=luxvv-payouts.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Period Start', 'Period End', 'Views >= 20s', 'CPM (cents)', 'Bonus %', 'Payout (cents)', 'Status' ] );
        foreach ( $rows as $row ) {
            fputcsv( $out, [
                $row['period_start'],
                $row['period_end'],
                $row['views_20s'],
                $row['cpm_cents'],
                $row['bonus_pct'],
                $row['payout_cents'],
                $row['status'],
            ] );
        }
        fclose( $out );
        exit;
    }
}
