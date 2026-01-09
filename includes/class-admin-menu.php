<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Admin_Menu {

	const SLUG = 'lux-verified';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register' ] );
	}

	public static function register(): void {

		$hook = add_menu_page(
			'LUX Verified',
			'LUX Verified',
			'manage_options',
			self::SLUG,
			[ __CLASS__, 'render_dashboard' ],
			'dashicons-shield-alt',
			58
		);
		if ( ! $hook ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			update_option( 'luxvv_menu_attached', 0, false );
			deactivate_plugins( plugin_basename( LUXVV_PATH . 'lux-verified-video.php' ) );
			wp_die( 'LUX Verified menu failed to attach. Plugin deactivated.' );
		}

		update_option( 'luxvv_menu_attached', 1, false );

		add_submenu_page(
			self::SLUG,
			'Dashboard',
			'Dashboard',
			'manage_options',
			self::SLUG,
			[ __CLASS__, 'render_dashboard' ]
		);

		add_submenu_page(
			self::SLUG,
			'Videos',
			'Videos',
			'manage_options',
			'lux-verified-videos',
			[ __CLASS__, 'render_videos' ]
		);

		add_submenu_page(
			self::SLUG,
			'Events',
			'Events',
			'manage_options',
			'lux-verified-events',
			[ __CLASS__, 'render_events' ]
		);

		add_submenu_page(
			self::SLUG,
			'Verification',
			'Verification',
			'manage_options',
			'lux-verified-verification',
			[ __CLASS__, 'render_verification' ]
		);

		add_submenu_page(
			self::SLUG,
			'Tax & Compliance',
			'Tax & Compliance',
			'manage_options',
			'lux-verified-compliance',
			[ __CLASS__, 'render_compliance' ]
		);

		add_submenu_page(
			self::SLUG,
			'Payouts',
			'Payouts',
			'manage_options',
			'lux-verified-payouts',
			[ __CLASS__, 'render_payouts' ]
		);

		add_submenu_page(
			self::SLUG,
			'Settings',
			'Settings',
			'manage_options',
			'lux-verified-settings',
			[ __CLASS__, 'render_settings' ]
		);

		add_submenu_page(
			self::SLUG,
			'AI Control',
			'AI Control',
			'manage_options',
			'lux-verified-ai',
			[ __CLASS__, 'render_ai' ]
		);
	}

	public static function render_dashboard(): void {
		echo '<h1>LUX Verified – Dashboard Loaded</h1>';

		$summary = class_exists( '\\LuxVerified\\Analytics' ) && method_exists( '\\LuxVerified\\Analytics', 'get_dashboard_summary' )
			? Analytics::get_dashboard_summary( 30 )
			: [
				'days' => 30,
				'impressions' => 0,
				'plays' => 0,
				'views_20s' => 0,
				'completes' => 0,
				'watch_seconds' => 0,
			];

		$views = max( 1, (int) $summary['views_20s'] );
		$plays = max( 1, (int) $summary['plays'] );
		$impressions = max( 1, (int) $summary['impressions'] );
		$completion_rate = $plays > 0 ? ( (int) $summary['completes'] / $plays ) : 0;
		$ctr = $impressions > 0 ? ( (int) $summary['views_20s'] / $impressions ) : 0;
		$watch_minutes = (int) floor( (int) $summary['watch_seconds'] / 60 );

		?>
		<div class="wrap luxvv-dashboard">
			<h1>LUX Verified</h1>
			<p><strong>Last <?php echo (int) $summary['days']; ?> days</strong></p>

			<div class="luxvv-cards">
				<div class="luxvv-card">
					<div class="luxvv-card-label">Impressions</div>
					<div class="luxvv-card-value"><?php echo number_format_i18n( (int) $summary['impressions'] ); ?></div>
				</div>
				<div class="luxvv-card">
					<div class="luxvv-card-label">Plays</div>
					<div class="luxvv-card-value"><?php echo number_format_i18n( (int) $summary['plays'] ); ?></div>
				</div>
				<div class="luxvv-card">
					<div class="luxvv-card-label">Views ≥ 20s</div>
					<div class="luxvv-card-value"><?php echo number_format_i18n( (int) $summary['views_20s'] ); ?></div>
				</div>
				<div class="luxvv-card">
					<div class="luxvv-card-label">Completion Rate</div>
					<div class="luxvv-card-value"><?php echo esc_html( number_format_i18n( $completion_rate * 100, 1 ) ); ?>%</div>
				</div>
				<div class="luxvv-card">
					<div class="luxvv-card-label">CTR (20s Views)</div>
					<div class="luxvv-card-value"><?php echo esc_html( number_format_i18n( $ctr * 100, 1 ) ); ?>%</div>
				</div>
				<div class="luxvv-card">
					<div class="luxvv-card-label">Watch Minutes</div>
					<div class="luxvv-card-value"><?php echo number_format_i18n( $watch_minutes ); ?></div>
				</div>
			</div>

			<div class="luxvv-bars">
				<div class="luxvv-bar">
					<span>Views vs Plays</span>
					<div class="luxvv-bar-track">
						<div class="luxvv-bar-fill" style="width: <?php echo esc_attr( min( 100, ( $summary['views_20s'] / $plays ) * 100 ) ); ?>%"></div>
					</div>
				</div>
				<div class="luxvv-bar">
					<span>CTR (20s Views / Impressions)</span>
					<div class="luxvv-bar-track">
						<div class="luxvv-bar-fill" style="width: <?php echo esc_attr( min( 100, $ctr * 100 ) ); ?>%"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_verification(): void {
		echo '<h1>LUX Verified – Dashboard Loaded</h1>';
		self::render_requests();
	}

	public static function render_compliance(): void {
		echo '<h1>LUX Verified – Dashboard Loaded</h1>';
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$users = get_users( [
			'fields' => [ 'ID', 'user_login', 'user_email' ],
		] );
		$threshold = Settings::get( 'payout_1099_threshold', 600 );

		require LUXVV_DIR . 'includes/views/compliance.php';
	}

	public static function render_requests(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'lux_verified_members';

		$requests = $wpdb->get_results(
			"
			SELECT 
				u.ID as user_id,
				u.user_login,
				u.user_email,
				m.verification_status,
				m.created_at,
				m.updated_at
			FROM {$wpdb->users} u
			LEFT JOIN {$table} m ON m.user_id = u.ID
			WHERE u.ID IN (
				SELECT DISTINCT user_id
				FROM {$wpdb->usermeta}
				WHERE meta_key LIKE 'luxvv_%'
			)
			ORDER BY m.updated_at DESC
			",
			ARRAY_A
		);

		require LUXVV_DIR . 'includes/views/requests.php';
	}

	public static function render_videos(): void {
		echo '<h1>LUX Verified – Dashboard Loaded</h1>';
		global $wpdb;

		$table = $wpdb->prefix . 'lux_videos';
		if ( ! self::table_exists( $table ) ) {
			echo '<div class="wrap"><h1>Videos</h1><p>Video table not found.</p></div>';
			return;
		}

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 50",
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1>Videos</h1>
			<?php if ( empty( $rows ) ) : ?>
				<p>No videos found.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Video</th>
							<th>Owner</th>
							<th>Status</th>
							<th>Created</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $row['title'] ?? $row['bunny_guid'] ); ?></strong><br>
								<small><?php echo esc_html( $row['bunny_guid'] ?? '' ); ?></small>
							</td>
							<td><?php echo (int) ( $row['user_id'] ?? 0 ); ?></td>
							<td><?php echo esc_html( $row['status'] ?? 'unknown' ); ?></td>
							<td><?php echo esc_html( $row['created_at'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_marketing(): void {
		echo '<h1>LUX Verified – Dashboard Loaded</h1>';
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$settings = class_exists( '\\LuxVerified\\Marketing' )
			? Marketing::all()
			: [];
		$tools = class_exists( '\\LuxVerified\\Marketing' )
			? Marketing::tool_catalog()
			: [];
		$workflow = class_exists( '\\LuxVerified\\Marketing' )
			? Marketing::weekly_workflow()
			: [];
		$prompts = class_exists( '\\LuxVerified\\Marketing' )
			? Marketing::workflow_templates()
			: [];

		$grouped_tools = [];
		foreach ( $tools as $tool_key => $tool ) {
			$category = $tool['category'] ?? 'Other';
			if ( ! isset( $grouped_tools[ $category ] ) ) {
				$grouped_tools[ $category ] = [];
			}
			$grouped_tools[ $category ][ $tool_key ] = $tool;
		}

		require LUXVV_DIR . 'includes/views/marketing.php';
	}

	public static function render_events(): void {
		echo '<h1>LUX Verified – Dashboard Loaded</h1>';
		global $wpdb;

		$table = $wpdb->prefix . 'lux_video_events';
		if ( ! self::table_exists( $table ) ) {
			echo '<div class="wrap"><h1>Events</h1><p>Events table not found.</p></div>';
			return;
		}

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100",
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1>Events</h1>
			<?php if ( empty( $rows ) ) : ?>
				<p>No events found.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Event</th>
							<th>Video</th>
							<th>Owner</th>
							<th>Viewer</th>
							<th>Time</th>
							<th>Created</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['event_type'] ?? '' ); ?></td>
							<td><?php echo (int) ( $row['video_id'] ?? 0 ); ?></td>
							<td><?php echo (int) ( $row['user_id'] ?? 0 ); ?></td>
							<td><?php echo (int) ( $row['watch_seconds'] ?? 0 ); ?></td>
							<td><?php echo esc_html( $row['created_at'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_payouts(): void {
		echo '<h1>LUX Verified – Dashboard Loaded</h1>';
		global $wpdb;

		$table = $wpdb->prefix . 'lux_payouts';
		if ( ! self::table_exists( $table ) ) {
			echo '<div class="wrap"><h1>Payouts</h1><p>Payouts table not found.</p></div>';
			return;
		}

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 50",
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1>Payouts</h1>
			<?php if ( empty( $rows ) ) : ?>
				<p>No payouts found.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>User</th>
							<th>Period</th>
							<th>Views</th>
							<th>Revenue</th>
							<th>Paid</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo (int) ( $row['user_id'] ?? 0 ); ?></td>
							<td><?php echo esc_html( $row['period'] ?? '' ); ?></td>
							<td><?php echo (int) ( $row['views'] ?? 0 ); ?></td>
							<td><?php echo esc_html( $row['revenue'] ?? '' ); ?></td>
							<td><?php echo ! empty( $row['is_paid'] ) ? 'Yes' : 'No'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_settings(): void {
		echo '<h1>LUX Verified – Dashboard Loaded</h1>';
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$health = [
			'missing_tables' => class_exists( '\\LuxVerified\\Install' ) ? Install::missing_tables() : [],
			'menu_attached' => (int) get_option( 'luxvv_menu_attached', 0 ) === 1,
			'bunny_library_id' => Settings::get( 'bunny_library_id' ),
			'bunny_api_key' => Settings::get( 'bunny_api_key' ),
			'bunny_cdn_host' => Settings::get( 'bunny_cdn_host' ),
			'rest_url' => function_exists( 'rest_url' ) ? rest_url( 'luxvv/v1' ) : '',
		];

		require LUXVV_DIR . 'includes/views/settings.php';
	}

	public static function render_ai(): void {
		echo '<h1>LUX Verified – Dashboard Loaded</h1>';
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		if ( class_exists( '\\LuxVerified\\AI' ) ) {
			AI::render_page();
			return;
		}

		echo '<div class="wrap"><h1>AI Control</h1><p>AI module not available.</p></div>';
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		) === $table;
	}
}
