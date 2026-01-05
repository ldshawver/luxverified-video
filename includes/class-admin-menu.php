<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Admin_Menu {

	const SLUG = 'luxvv';

	public static function register(): void {

		add_menu_page(
			'LUX Verified',
			'LUX Verified',
			'manage_options',
			self::SLUG,
			[ __CLASS__, 'render_dashboard' ],
			'dashicons-shield-alt',
			58
		);

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
			'luxvv-videos',
			[ __CLASS__, 'render_videos' ]
		);

		add_submenu_page(
			self::SLUG,
			'Events',
			'Events',
			'manage_options',
			'luxvv-events',
			[ __CLASS__, 'render_events' ]
		);

		add_submenu_page(
			self::SLUG,
			'Verification Requests',
			'Requests',
			'manage_options',
			'luxvv-requests',
			[ __CLASS__, 'render_requests' ]
		);

		add_submenu_page(
			self::SLUG,
			'Payouts',
			'Payouts',
			'manage_options',
			'luxvv-payouts',
			[ __CLASS__, 'render_payouts' ]
		);

		add_submenu_page(
			self::SLUG,
			'Settings',
			'Settings',
			'manage_options',
			'luxvv-settings',
			[ __CLASS__, 'render_settings' ]
		);

		add_submenu_page(
			self::SLUG,
			'AI Control',
			'AI Control',
			'manage_options',
			'luxvv-ai',
			[ __CLASS__, 'render_ai' ]
		);
	}

	public static function render_dashboard(): void {
		$summary = class_exists( '\\LuxVerified\\Analytics' )
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
								<strong><?php echo esc_html( $row['title'] ?? $row['bunny_video_guid'] ); ?></strong><br>
								<small><?php echo esc_html( $row['bunny_video_guid'] ?? '' ); ?></small>
							</td>
							<td><?php echo (int) ( $row['owner_user_id'] ?? 0 ); ?></td>
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

	public static function render_events(): void {
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
							<td><?php echo esc_html( $row['bunny_video_guid'] ?? '' ); ?></td>
							<td><?php echo (int) ( $row['owner_user_id'] ?? 0 ); ?></td>
							<td><?php echo (int) ( $row['viewer_user_id'] ?? 0 ); ?></td>
							<td><?php echo (int) ( $row['current_time'] ?? 0 ); ?></td>
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
		global $wpdb;

		$table = $wpdb->prefix . 'lux_payouts';
		if ( ! self::table_exists( $table ) ) {
			echo '<div class="wrap"><h1>Payouts</h1><p>Payouts table not found.</p></div>';
			return;
		}

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY period_start DESC LIMIT 50",
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
							<th>Views ≥ 20s</th>
							<th>CPM</th>
							<th>Payout</th>
							<th>Status</th>
							<th>Paid At</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$payout_id = (int) ( $row['id'] ?? 0 );
						$is_paid = ( $row['status'] ?? '' ) === 'paid';
						$receipt_url = $payout_id
							? wp_nonce_url(
								admin_url( 'admin-post.php?action=luxvv_download_receipt&payout_id=' . $payout_id ),
								'luxvv_download_receipt_' . $payout_id
							)
							: '';
						?>
						<tr>
							<td><?php echo (int) ( $row['user_id'] ?? 0 ); ?></td>
							<td><?php echo esc_html( $row['period_start'] ?? '' ); ?> → <?php echo esc_html( $row['period_end'] ?? '' ); ?></td>
							<td><?php echo (int) ( $row['views_20s'] ?? 0 ); ?></td>
							<td><?php echo (int) ( $row['cpm_cents'] ?? 0 ); ?>¢</td>
							<td><?php echo (int) ( $row['payout_cents'] ?? 0 ); ?>¢</td>
							<td><?php echo esc_html( $row['status'] ?? 'pending' ); ?></td>
							<td><?php echo esc_html( $row['paid_at'] ?? '' ); ?></td>
							<td>
								<?php if ( ! $is_paid ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="luxvv-inline-form">
										<input type="hidden" name="action" value="luxvv_mark_payout_paid">
										<input type="hidden" name="payout_id" value="<?php echo esc_attr( $payout_id ); ?>">
										<?php wp_nonce_field( 'luxvv_mark_payout_paid' ); ?>
										<input type="text" name="reference" placeholder="Reference">
										<input type="text" name="notes" placeholder="Notes">
										<button type="submit">Mark Paid</button>
									</form>
								<?php else : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="luxvv-inline-form">
										<input type="hidden" name="action" value="luxvv_reset_payout">
										<input type="hidden" name="payout_id" value="<?php echo esc_attr( $payout_id ); ?>">
										<?php wp_nonce_field( 'luxvv_reset_payout' ); ?>
										<input type="text" name="reason" placeholder="Reset reason">
										<button type="submit">Reset</button>
									</form>
									<?php if ( $receipt_url ) : ?>
										<a href="<?php echo esc_url( $receipt_url ); ?>">Receipt</a>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		require LUXVV_DIR . 'includes/views/settings.php';
	}

	public static function render_ai(): void {
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
