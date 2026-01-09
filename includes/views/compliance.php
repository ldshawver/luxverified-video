<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1>LUX Verified – Tax &amp; Compliance</h1>

	<h2>1099-NEC Export</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'luxvv_export_1099' ); ?>
		<input type="hidden" name="action" value="luxvv_export_1099">
		<label>
			Year
			<input type="number" name="tax_year" value="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" min="2000" max="2100">
		</label>
		<p class="description">Threshold: <?php echo esc_html( number_format_i18n( (float) $threshold, 2 ) ); ?></p>
		<?php submit_button( 'Export 1099-NEC CSV', 'secondary', 'submit', false ); ?>
	</form>

	<h2>W-9 Status</h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th>User</th>
				<th>Email</th>
				<th>Status</th>
				<th>Submitted</th>
				<th>Field Keys</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $users ) ) : ?>
			<tr><td colspan="6">No creators found.</td></tr>
		<?php else : ?>
			<?php foreach ( $users as $user ) : ?>
				<?php
				$user_id = (int) $user->ID;
				$status = get_user_meta( $user_id, 'luxvv_w9_status', true ) ?: 'missing';
				$submitted_at = get_user_meta( $user_id, 'luxvv_w9_submitted_at', true );
				$submitted_at = $submitted_at ? date_i18n( 'Y-m-d H:i', (int) $submitted_at ) : '—';
				$field_keys = get_user_meta( $user_id, 'luxvv_w9_field_keys', true );
				$field_keys = is_array( $field_keys ) ? implode( ', ', $field_keys ) : (string) $field_keys;
				$w9_file = get_user_meta( $user_id, 'luxvv_w9_pdf', true );
				$generate_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=luxvv_generate_w9&user_id=' . $user_id ),
					'luxvv_generate_w9'
				);
				$download_url = $w9_file
					? wp_nonce_url(
						admin_url( 'admin-post.php?action=luxvv_download_w9&user_id=' . $user_id ),
						'luxvv_download_w9'
					)
					: '';
				?>
				<tr>
					<td><?php echo esc_html( $user->user_login ); ?></td>
					<td><?php echo esc_html( $user->user_email ); ?></td>
					<td><?php echo esc_html( $status ); ?></td>
					<td><?php echo esc_html( $submitted_at ); ?></td>
					<td><?php echo esc_html( $field_keys ); ?></td>
					<td>
						<a class="button" href="<?php echo esc_url( $generate_url ); ?>">Generate W-9</a>
						<?php if ( $download_url ) : ?>
							<a class="button" href="<?php echo esc_url( $download_url ); ?>">Download</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
