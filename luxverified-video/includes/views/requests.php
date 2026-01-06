<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use LuxVerified\Verification;

if ( empty( $requests ) ) {
	echo '<div class="wrap luxvv-wrap"><p>No verification requests found.</p></div>';
	return;
}
?>

<div class="wrap luxvv-wrap">

	<h1 class="luxvv-h1">LUX Verified – Requests</h1>

	<div class="luxvv-table-wrap">
		<table class="widefat luxvv-table">
			<thead>
				<tr>
					<th>User</th>
					<th>Email</th>
					<th>Step 1</th>
					<th>Step 2</th>
					<th>Step 3</th>
					<th>Status</th>
					<th class="luxvv-actions-col">Actions</th>
				</tr>
			</thead>

			<tbody>
			<?php foreach ( $requests as $row ) :

				$user_id = (int) $row['user_id'];

				// SINGLE SOURCE OF TRUTH
				$status = sanitize_key( $row['verification_status'] ?: 'started' );

				// STEP CHECKS (DERIVED FROM STATUS)
				$step1 = in_array( $status, [ 'step1_completed','step2_completed','ready_for_review','approved' ], true );
				$step2 = in_array( $status, [ 'step2_completed','ready_for_review','approved' ], true );
				$step3 = in_array( $status, [ 'ready_for_review','approved' ], true );

				$can_approve = ( $status === 'ready_for_review' );

				$row_class = 'luxvv-row luxvv-status-' . esc_attr( $status );

				$approve_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=luxvv_approve_user&user_id=' . $user_id ),
					Verification::NONCE_ACTION,
					'_wpnonce'
				);

				$reject_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=luxvv_reject_user&user_id=' . $user_id ),
					Verification::NONCE_ACTION,
					'_wpnonce'
				);

				$w9_generate_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=luxvv_generate_w9&user_id=' . $user_id ),
					'luxvv_generate_w9'
				);

				$w9_file = get_user_meta( $user_id, 'luxvv_w9_pdf', true );
				$w9_url  = $w9_file
					? wp_nonce_url(
						admin_url( 'admin-post.php?action=luxvv_download_w9&user_id=' . $user_id ),
						Verification::NONCE_ACTION,
						'_wpnonce'
					)
					: '';
			?>
				<tr class="<?php echo esc_attr( $row_class ); ?>">

					<td>
						<strong><?php echo esc_html( $row['user_login'] ); ?></strong><br>
						<small>ID: <?php echo esc_html( $user_id ); ?></small>
					</td>

					<td><?php echo esc_html( $row['user_email'] ); ?></td>

					<td><?php echo $step1 ? '✅' : '❌'; ?></td>
					<td><?php echo $step2 ? '✅' : '❌'; ?></td>
					<td><?php echo $step3 ? '✅' : '❌'; ?></td>

					<td>
						<span class="luxvv-badge luxvv-badge-<?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?>
						</span>
					</td>

					<td class="luxvv-actions-col">

						<?php if ( $can_approve ) : ?>
							<a class="button button-primary luxvv-approve-btn"
                                href="<?php echo esc_url( $approve_url ); ?>">
                            	Approve
                            </a>

						<?php else : ?>
							<span class="button disabled">Approve</span>
						<?php endif; ?>

						<a href="#"
						   class="button luxvv-reject-btn"
						   data-user="<?php echo esc_attr( $row['user_login'] ); ?>"
						   data-reject-url="<?php echo esc_attr( $reject_url ); ?>">
							Reject
						</a>

						<?php if ( $can_approve ) : ?>
							<a class="button"
							   href="<?php echo esc_url( $w9_generate_url ); ?>">
								Generate W-9
							</a>
						<?php endif; ?>

						<?php if ( $w9_url ) : ?>
							<a class="button luxvv-icon-btn"
							   href="<?php echo esc_url( $w9_url ); ?>"
							   title="Download W-9">📄</a>
						<?php else : ?>
							<small class="luxvv-muted">No W-9</small>
						<?php endif; ?>

					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>

		</table>
	</div>
</div>

<!-- REJECT MODAL -->
<div id="luxvv-reject-modal" aria-hidden="true">
	<div class="luxvv-modal-box">
		<h3 id="luxvv-reject-user"></h3>

		<textarea id="luxvv-reject-notes"
		          rows="5"
		          placeholder="Enter rejection notes (emailed to user)"></textarea>

		<div class="luxvv-modal-actions">
			<button class="button" id="luxvv-reject-cancel">Cancel</button>
			<button class="button button-primary" id="luxvv-reject-confirm">
				Reject & Send Email
			</button>
		</div>
	</div>
</div>
