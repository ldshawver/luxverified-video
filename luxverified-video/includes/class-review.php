<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Review {

	public static function init(): void {
		add_action( 'admin_post_luxvv_repair', [ __CLASS__, 'handle_repair' ] );
	}

	/**
	 * SAFE + AUTHORITATIVE REPAIR
	 * Source of truth = user_meta
	 */
	public static function handle_repair(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		check_admin_referer( Verification::NONCE_ACTION, '_wpnonce' );

		global $wpdb;

		$table = $wpdb->prefix . 'lux_verified_members';

		// 🔑 All users that have ANY verification-related meta
		$user_ids = $wpdb->get_col(
			"
			SELECT DISTINCT user_id
			FROM {$wpdb->usermeta}
			WHERE meta_key IN (
				'luxvv_step1',
				'luxvv_step2',
				'luxvv_step3',
				'luxvv_verified',
				'luxvv_w9_pdf'
			)
			"
		);

		$updated = 0;

		foreach ( $user_ids as $user_id ) {

			$user_id = (int) $user_id;
			if ( ! $user_id ) continue;

			// ✅ Derive status from meta (REAL truth)
			$status = Verification::derive_status_from_meta( $user_id );
// Backfill step meta from status
if ( $status === 'approved' || $status === 'ready_for_review' ) {
	update_user_meta( $user_id, Verification::STEP1, 1 );
	update_user_meta( $user_id, Verification::STEP2, 1 );
	update_user_meta( $user_id, Verification::STEP3, 1 );
}
elseif ( $status === 'step2_completed' ) {
	update_user_meta( $user_id, Verification::STEP1, 1 );
	update_user_meta( $user_id, Verification::STEP2, 1 );
}
elseif ( $status === 'step1_completed' ) {
	update_user_meta( $user_id, Verification::STEP1, 1 );
}

			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
					$user_id
				)
			);

			$data = [
				'user_id'             => $user_id,
				'verification_status' => $status,
				'updated_at'          => current_time( 'mysql' ),
			];

			if ( $exists ) {
				$wpdb->update( $table, $data, [ 'user_id' => $user_id ] );
			} else {
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $table, $data );
			}

			$updated++;
		}

		// Audit
		Verification::audit( 0, 'repair', [
			'users_processed' => $updated,
			'source' => 'usermeta',
		] );

		wp_safe_redirect(
			admin_url( 'admin.php?page=luxvv-requests&repair=1&count=' . $updated )
		);
		exit;
	}
}
