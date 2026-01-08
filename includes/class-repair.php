<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Repair {

	public static function run_repair(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'lux_verified_members';

		$user_ids = $wpdb->get_col(
			"
			SELECT DISTINCT user_id
			FROM {$wpdb->usermeta}
			WHERE meta_key IN (
				'luxvv_step1',
				'luxvv_step2',
				'luxvv_step3',
				'luxvv_verified',
				'luxvv_w9_pdf',
				'luxvv_w9_status',
				'luxvv_w9_submitted_at'
			)
			"
		);

		$updated = 0;

		foreach ( $user_ids as $user_id ) {
			$user_id = (int) $user_id;
			if ( ! $user_id ) {
				continue;
			}

			$status = Verification::derive_status_from_meta( $user_id );

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

		Verification::audit( 0, 'repair', [
			'users_processed' => $updated,
			'source' => 'rest',
		] );

		return [
			'success' => true,
			'users_processed' => $updated,
		];
	}
}
