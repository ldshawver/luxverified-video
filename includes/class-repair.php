<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Review {

	public static function init(): void {
		add_action( 'admin_post_luxvv_repair', [ __CLASS__, 'handle_repair' ] );
	}

	public static function handle_repair(): void {

		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( Verification::NONCE_ACTION, '_wpnonce' );

		global $wpdb;

		$members = $wpdb->prefix . 'lux_verified_members';
		$meta    = $wpdb->usermeta;

		// ONLY users who have ANY step meta
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$meta}
				 WHERE meta_key IN (%s, %s, %s)",
				Verification::STEP1,
				Verification::STEP2,
				Verification::STEP3
			)
		);

		$updated = 0;

		foreach ( $user_ids as $user_id ) {

			$user_id = (int) $user_id;
			if ( ! $user_id ) continue;

			$status = Verification::derive_status_from_meta( $user_id );
			if ( $status === 'started' ) continue; // 🚫 never insert started

			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$members} WHERE user_id=%d",
					$user_id
				)
			);

			$data = [
				'user_id'             => $user_id,
				'verification_status' => $status,
				'updated_at'          => current_time( 'mysql' ),
			];

			if ( $exists ) {
				$wpdb->update( $members, $data, [ 'user_id' => $user_id ] );
			} else {
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $members, $data );
			}

			$updated++;
		}

		Verification::audit( 0, 'repair', [
			'users_repaired' => $updated,
		] );

		wp_safe_redirect(
			admin_url( 'admin.php?page=luxvv-requests&repaired=1&count=' . $updated )
		);
		exit;
	}
}
