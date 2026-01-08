<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use LuxVerified\Verification;

$export_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=luxvv_export_1099&year=' . (int) $year ),
	'luxvv_export_1099'
);
?>

<div class="wrap luxvv-wrap">
	<h1>LUX Verified – Tax & Compliance</h1>

	<h2>W-9 Status</h2>
	<?php if ( empty( $w9_rows ) ) : ?>
		<p>No W-9 submissions found.</p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>User</th>
					<th>Email</th>
					<th>Status</th>
					<th>Submitted At</th>
					<th>Tax ID (Masked)</th>
					<th>Form Fields</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $w9_rows as $row ) : ?>
					<?php
					$user_id = (int) $row['user_id'];
					$tax_last4 = $row['tax_last4'] ? '***-**-' . $row['tax_last4'] : '—';
					$w9_status = $row['w9_status'] ? ucfirst( (string) $row['w9_status'] ) : 'Missing';
					$field_keys = get_user_meta( $user_id, 'luxvv_w9_field_keys', true );
					$field_keys = $field_keys ? json_decode( (string) $field_keys, true ) : [];
					$field_keys = is_array( $field_keys ) ? array_map( 'strval', $field_keys ) : [];
					$field_keys_display = $field_keys ? implode( ', ', $field_keys ) : '—';

					$w9_generate_url = wp_nonce_url(
						admin_url( 'admin-post.php?action=luxvv_generate_w9&user_id=' . $user_id ),
						'luxvv_generate_w9'
					);

					$w9_url = $row['w9_pdf']
						? wp_nonce_url(
							admin_url( 'admin-post.php?action=luxvv_download_w9&user_id=' . $user_id ),
							Verification::NONCE_ACTION,
							'_wpnonce'
						)
						: '';
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $row['user_login'] ); ?></strong><br>
							<small>ID: <?php echo esc_html( $user_id ); ?></small>
						</td>
						<td><?php echo esc_html( $row['user_email'] ); ?></td>
						<td><?php echo esc_html( $w9_status ); ?></td>
						<td><?php echo esc_html( $row['w9_submitted_at'] ?: '—' ); ?></td>
						<td><?php echo esc_html( $tax_last4 ); ?></td>
						<td><?php echo esc_html( $field_keys_display ); ?></td>
						<td>
							<a class="button" href="<?php echo esc_url( $w9_generate_url ); ?>">Generate W-9</a>
							<?php if ( $w9_url ) : ?>
								<a class="button" href="<?php echo esc_url( $w9_url ); ?>">Download</a>
							<?php else : ?>
								<span class="luxvv-muted">No PDF</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2>1099 Annual Export</h2>
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="luxvv-compliance">
		<label for="luxvv-year">Year</label>
		<input type="number" id="luxvv-year" name="year" value="<?php echo esc_attr( $year ); ?>">
		<button class="button">Filter</button>
	</form>
	<p>Threshold: <?php echo esc_html( number_format_i18n( $threshold_cents / 100, 2 ) ); ?> USD</p>
	<p><a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>">Export <?php echo esc_html( $year ); ?> CSV</a></p>

	<?php if ( empty( $annual_rows ) ) : ?>
		<p>No qualifying creators found for <?php echo esc_html( $year ); ?>.</p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>Creator</th>
					<th>Email</th>
					<th>Tax ID (Masked)</th>
					<th>Gross Earnings</th>
					<th>Platform Fees</th>
					<th>Net Payout</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $annual_rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row['name'] ); ?></td>
					<td><?php echo esc_html( $row['email'] ); ?></td>
					<td><?php echo esc_html( $row['tax_id_masked'] ); ?></td>
					<td><?php echo esc_html( $row['gross_earnings'] ); ?></td>
					<td><?php echo esc_html( $row['platform_fees'] ); ?></td>
					<td><?php echo esc_html( $row['net_payout'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
