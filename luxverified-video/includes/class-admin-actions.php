<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Admin_Actions {

	public static function init(): void {

		// Approve / Reject
		add_action( 'admin_post_luxvv_approve_user', [ __CLASS__, 'approve' ] );
		add_action( 'admin_post_luxvv_reject_user',  [ __CLASS__, 'reject' ] );
        add_action( 'admin_post_luxvv_preview_email', [ __CLASS__, 'preview_email' ] );

		// Repair & Resync (non-destructive)
		add_action( 'admin_post_luxvv_repair_resync', [ __CLASS__, 'repair_resync' ] );
	}

	/* =========================
	 * APPROVE USER
	 * ========================= */
	public static function approve(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		check_admin_referer( Verification::NONCE_ACTION, '_wpnonce' );

		$user_id = (int) ( $_GET['user_id'] ?? 0 );
		if ( ! $user_id ) {
			wp_die( 'Invalid user' );
		}

		Verification::approve( $user_id );

		wp_safe_redirect( admin_url( 'admin.php?page=luxvv-requests&approved=1' ) );
		exit;
	}

	/* =========================
	 * REJECT USER (WITH NOTES)
	 * ========================= */
	public static function reject(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		check_admin_referer( Verification::NONCE_ACTION, '_wpnonce' );

		$user_id = (int) ( $_GET['user_id'] ?? 0 );
		$notes   = sanitize_textarea_field( $_GET['notes'] ?? '' );

		if ( ! $user_id ) {
			wp_die( 'Invalid user' );
		}

		Verification::reject( $user_id, $notes );

		wp_safe_redirect( admin_url( 'admin.php?page=luxvv-requests&rejected=1' ) );
		exit;
	}
	/* =========================
	 * REJECT EMAIL
	 * ========================= */
public static function preview_email(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden' );
	}

	check_admin_referer( Verification::NONCE_ACTION, '_wpnonce' );

	$user_id = (int) $_GET['user_id'];
	$type    = sanitize_key( $_GET['type'] ?? 'approval' );
	$notes   = sanitize_textarea_field( $_GET['notes'] ?? '' );

	if ( $type === 'rejection' ) {
		$email = Verification::get_rejection_email( $user_id, $notes );
	} else {
		$email = Verification::get_approval_email( $user_id );
	}

	echo '<div class="wrap">';
	echo '<h1>Email Preview</h1>';
	echo '<h3>' . esc_html( $email['subject'] ) . '</h3>';
	echo '<hr>';
	echo wp_kses_post( $email['body'] );
	echo '</div>';

	exit;
}

	/* =========================
	 * REPAIR & RESYNC (SAFE)
	 * ========================= */
	public static function repair_resync(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		check_admin_referer( Verification::NONCE_ACTION, '_wpnonce' );

		global $wpdb;
		$table = $wpdb->prefix . 'lux_verified_members';

		$user_ids = get_users([
			'fields' => 'ID',
			'number' => -1,
		]);

		foreach ( $user_ids as $user_id ) {

			$s1 = (int) get_user_meta( $user_id, Verification::STEP1, true );
			$s2 = (int) get_user_meta( $user_id, Verification::STEP2, true );
			$s3 = (int) get_user_meta( $user_id, Verification::STEP3, true );

			if ( $s1 && $s2 && $s3 ) {
				$status = 'ready_for_review';
			} elseif ( $s1 && $s2 ) {
				$status = 'step2_completed';
			} elseif ( $s1 ) {
				$status = 'step1_completed';
			} else {
				continue; // do not insert users with no steps
			}

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT user_id FROM {$table} WHERE user_id = %d",
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
		}

		wp_safe_redirect(
			admin_url( 'admin.php?page=luxvv-requests&repaired=1' )
		);
		exit;
	}
}
