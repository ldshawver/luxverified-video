<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Repair {

	public static function init(): void {
		add_action( 'admin_post_luxvv_rebuild_tables', [ __CLASS__, 'rebuild_tables' ] );
	}

	public static function rebuild_tables(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		check_admin_referer( 'luxvv_rebuild_tables' );

		Install::activate();

		wp_safe_redirect( admin_url( 'admin.php?page=lux-verified-settings&tables=rebuilt' ) );
		exit;
	}

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
				'luxvv_w9_pdf'
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
			self::repair_w9_meta( $user_id );

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

	private static function repair_w9_meta( int $user_id ): void {
		$has_tax_id = (bool) get_user_meta( $user_id, Verification::TAX_ID_ENCRYPTED_META, true );
		$has_status = (bool) get_user_meta( $user_id, Verification::W9_STATUS_META, true );

		if ( $has_tax_id && ! $has_status ) {
			update_user_meta( $user_id, Verification::W9_STATUS_META, 'complete' );
		}

		if ( $has_tax_id && ! get_user_meta( $user_id, Verification::W9_SUBMITTED_META, true ) ) {
			update_user_meta( $user_id, Verification::W9_SUBMITTED_META, current_time( 'timestamp' ) );
		}
	}
}
