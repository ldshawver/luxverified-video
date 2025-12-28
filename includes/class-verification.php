<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Verification {

	/* =========================
	 * CONSTANTS / META
	 * ========================= */
	const NONCE_ACTION = 'luxvv_review_actions';

	const STEP1 = 'luxvv_step1';
	const STEP2 = 'luxvv_step2';
	const STEP3 = 'luxvv_step3';

	const VERIFIED_META = 'luxvv_verified';

	/* =========================
	 * INIT
	 * ========================= */
	public static function init(): void {

		add_action( 'rm_user_registered', [ __CLASS__, 'step1' ], 10, 1 );

		add_action(
			'forminator_form_after_handle_submit',
			[ __CLASS__, 'handle_forminator_submit' ],
			10,
			3
		);

		add_action( 'admin_post_luxvv_approve_user', [ __CLASS__, 'handle_approve' ] );
		add_action( 'admin_post_luxvv_reject_user',  [ __CLASS__, 'handle_reject' ] );
		add_action( 'admin_post_luxvv_download_w9',  [ __CLASS__, 'handle_w9_download' ] );

		add_filter( 'the_content', [ __CLASS__, 'inject_creator_badge' ] );
		add_shortcode( 'luxvv_verified_badge', [ __CLASS__, 'shortcode_badge' ] );
		add_shortcode( 'luxvv_can_upload', [ __CLASS__, 'shortcode_can_upload' ] );

		add_filter( 'wpst_allow_video_submission', function () {
			return is_user_logged_in() && self::can_upload( get_current_user_id() );
		});
	}

	/* =========================
	 * STEP HANDLERS
	 * ========================= */

	public static function step1( int $user_id ): void {
		update_user_meta( $user_id, self::STEP1, 1 );
		self::sync( $user_id );
	}

	public static function handle_forminator_submit( $form_id, $data, $settings ): void {

		$user_id = get_current_user_id();
		if ( ! $user_id ) return;

		if ( (int) $form_id === (int) Settings::get( 'agreement_form_id' ) ) {
			update_user_meta( $user_id, self::STEP2, 1 );
		}

		if ( (int) $form_id === (int) Settings::get( 'w9_form_id' ) ) {
			update_user_meta( $user_id, self::STEP3, 1 );
		}

		self::sync( $user_id );
	}

	/* =========================
	 * STATUS SYNC (FIXED)
	 * ========================= */
	private static function sync( int $user_id ): void {

		global $wpdb;
		$table = $wpdb->prefix . 'lux_verified_members';

		$s1 = (int) get_user_meta( $user_id, self::STEP1, true );
		$s2 = (int) get_user_meta( $user_id, self::STEP2, true );
		$s3 = (int) get_user_meta( $user_id, self::STEP3, true );

		if ( $s1 && $s2 && $s3 ) {
			$status = 'ready_for_review';
		} elseif ( $s1 && $s2 ) {
			$status = 'step2_completed';
		} elseif ( $s1 ) {
			$status = 'step1_completed';
		} else {
			$status = 'started';
		}

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);

		$data = [
			'verification_status' => $status,
			'updated_at'          => current_time( 'mysql' ),
		];

		if ( $exists ) {
			$wpdb->update( $table, $data, [ 'user_id' => $user_id ] );
		} else {
			$data['user_id']    = $user_id;
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
		}
	}

	/* =========================
	 * ADMIN ACTIONS
	 * ========================= */

	public static function handle_approve(): void {

		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( self::NONCE_ACTION, '_wpnonce' );

		$user_id = (int) ( $_GET['user_id'] ?? 0 );
		if ( ! $user_id ) wp_die( 'Invalid user' );

		update_user_meta( $user_id, self::VERIFIED_META, 1 );

		self::send_approval_email( $user_id );

		wp_safe_redirect( admin_url( 'admin.php?page=luxvv-requests&approved=1' ) );
		exit;
	}

	public static function handle_reject(): void {

		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( self::NONCE_ACTION, '_wpnonce' );

		$user_id = (int) ( $_GET['user_id'] ?? 0 );
		if ( ! $user_id ) wp_die( 'Invalid user' );

		delete_user_meta( $user_id, self::VERIFIED_META );

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'lux_verified_members',
			[
				'verification_status' => 'rejected',
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'user_id' => $user_id ]
		);

		wp_safe_redirect( admin_url( 'admin.php?page=luxvv-requests&rejected=1' ) );
		exit;
	}

	public static function handle_w9_download(): void {

		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( self::NONCE_ACTION, '_wpnonce' );

		$user_id = (int) ( $_GET['user_id'] ?? 0 );
		$file = get_user_meta( $user_id, 'luxvv_w9_pdf', true );

		if ( ! $file || ! file_exists( $file ) ) wp_die( 'W-9 not found' );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename=W9-' . $user_id . '.pdf' );
		readfile( $file );
		exit;
	}

	/* =========================
	 * EMAIL
	 * ========================= */
	private static function send_approval_email( int $user_id ): void {

		$user = get_userdata( $user_id );
		if ( ! $user ) return;

		$subject = 'You are officially verified 🎉';
		$message = "Hi {$user->display_name},\n\n".
		           "Your creator account is now verified.\n\n".
		           "Upload here:\nhttps://lucifercruz.com/submit-your-video/\n\n".
		           "— Lucifer Cruz Studios";

		wp_mail( $user->user_email, $subject, $message );
	}

	/* =========================
	 * HELPERS
	 * ========================= */

	public static function is_verified( int $user_id ): bool {
		return (int) get_user_meta( $user_id, self::VERIFIED_META, true ) === 1;
	}

	public static function can_upload( int $user_id ): bool {
		return self::is_verified( $user_id );
	}

	public static function shortcode_badge(): string {
		if ( ! is_user_logged_in() ) return '';
		return self::is_verified( get_current_user_id() )
			? '<span class="luxvv-badge luxvv-badge--ok">Verified</span>'
			: '<span class="luxvv-badge luxvv-badge--pending">Pending</span>';
	}

	public static function shortcode_can_upload(): string {
		if ( ! is_user_logged_in() ) return '';
		return self::can_upload( get_current_user_id() )
			? '<div class="luxvv-allowed">Uploads enabled</div>'
			: '<div class="luxvv-blocked">Verification required</div>';
	}

	public static function inject_creator_badge( string $content ): string {

		if ( ! is_singular( 'post' ) ) return $content;

		global $post;
		return self::is_verified( (int) $post->post_author )
			? '<div class="luxvv-creator-badge"><span class="luxvv-badge luxvv-badge--ok">Verified Creator</span></div>' . $content
			: $content;
	}
}
