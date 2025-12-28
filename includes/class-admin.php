<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public static function init(): void {

		// Enqueue admin assets only on our pages
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		// Temporary admin notice
		add_action( 'admin_notices', [ __CLASS__, 'render_admin_notice' ] );

		// Render main admin pages
		add_action( 'admin_menu', [ __CLASS__, 'register_pages' ] );
	}

	/* ============================================================
	 * ADMIN MENU PAGES
	 * ============================================================ */
	public static function register_pages(): void {

		// MAIN MENU (parent)
		add_menu_page(
			'LUX Verified',
			'LUX Verified',
			'manage_options',
			'lux-verified-video',
			[ __CLASS__, 'render_dashboard' ],
			'dashicons-shield-alt',
			56
		);

		// VERIFICATION REQUESTS
		add_submenu_page(
			'lux-verified-video',
			'Verification Requests',
			'Requests',
			'manage_options',
			'luxvv-verification',
			[ __CLASS__, 'render_verification_requests' ]
		);
	}

	/* ============================================================
	 * ADMIN ASSETS
	 * ============================================================ */
	public static function enqueue_assets( string $hook ): void {

		if ( strpos( $hook, 'lux' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'luxvv-admin',
			LUXVV_URL . 'assets/admin.css',
			[],
			LUXVV_VERSION
		);
	}

	/* ============================================================
	 * ADMIN NOTICES
	 * ============================================================ */
	public static function render_admin_notice(): void {
		?>
		<div class="notice notice-info">
			<p><?php echo esc_html__( 'Codex PR Test', 'lux-verified-video' ); ?></p>
		</div>
		<?php
	}
		/* ============================================================
	 * VERIFICATION FUNNEL STATS
	 * ============================================================ */
public static function funnel_stats(): array {
    global $wpdb;

    return $wpdb->get_results(
        "
        SELECT verification_status, COUNT(*) as total
        FROM {$wpdb->prefix}lux_verified_members
        GROUP BY verification_status
        ",
        ARRAY_A
    );
}

	/* ============================================================
	 * DASHBOARD PAGE
	 * ============================================================ */
	public static function render_dashboard(): void {
		?>
		<div class="wrap">
			<h1>LUX Verified</h1>

			<p><strong>Status:</strong> Plugin is active.</p>

			<ul>
				<li>✔ Step 1: Registration tracking</li>
				<li>✔ Step 2: Agreement submission</li>
				<li>✔ Step 3: W-9 collection</li>
			</ul>

			<p>
				Use <strong>Requests</strong> to approve or reject creators.
			</p>
		</div>
		<?php
	}

	/* ============================================================
	 * VERIFICATION REQUESTS PAGE
	 * ============================================================ */
	public static function render_verification_requests(): void {

		global $wpdb;
		$table = $wpdb->prefix . 'lux_verified_members';

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY updated_at DESC",
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1>Verification Requests</h1>

			<?php if ( empty( $rows ) ) : ?>
				<p>No verification requests yet.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>User</th>
							<th>Status</th>
							<th>Steps</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) : 
						$user = get_user_by( 'id', $row['user_id'] );
						if ( ! $user ) continue;

						$approve_url = wp_nonce_url(
							admin_url( 'admin.php?page=luxvv-verification&luxvv_action=approve&user_id=' . $user->ID ),
							Verification::NONCE_ACTION
						);

						$reject_url = wp_nonce_url(
							admin_url( 'admin.php?page=luxvv-verification&luxvv_action=reject&user_id=' . $user->ID ),
							Verification::NONCE_ACTION
						);
					?>
						<tr>
							<td>
								<strong><?php echo esc_html( $user->display_name ); ?></strong><br>
								<small><?php echo esc_html( $user->user_email ); ?></small>
							</td>
							<td><?php echo esc_html( $row['verification_status'] ); ?></td>
							<td>
								S1: <?php echo (int) $row['step1']; ?> |
								S2: <?php echo (int) $row['step2']; ?> |
								S3: <?php echo (int) $row['step3']; ?>
							</td>
							<td>
								<a class="button button-primary" href="<?php echo esc_url( $approve_url ); ?>">Approve</a>
								<a class="button" href="<?php echo esc_url( $reject_url ); ?>">Reject</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
