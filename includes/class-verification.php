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
		add_shortcode( 'luxvv_w9_form', [ __CLASS__, 'shortcode_w9_form' ] );

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
			self::store_w9_submission( $user_id, is_array( $data ) ? $data : [] );
		}

		self::sync( $user_id );
	}

	/* =========================
	 * STATUS SYNC (FIXED)
	 * ========================= */
	private static function sync( int $user_id ): void {

		global $wpdb;
		$table = $wpdb->prefix . 'lux_verified_members';

		$status = self::derive_status_from_meta( $user_id );

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

		self::approve( $user_id );

		wp_safe_redirect( admin_url( 'admin.php?page=luxvv-requests&approved=1' ) );
		exit;
	}

	public static function handle_reject(): void {

		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( self::NONCE_ACTION, '_wpnonce' );

		$user_id = (int) ( $_GET['user_id'] ?? 0 );
		if ( ! $user_id ) wp_die( 'Invalid user' );

		self::reject( $user_id, sanitize_textarea_field( $_GET['notes'] ?? '' ) );

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
	public static function approve( int $user_id ): void {
		update_user_meta( $user_id, self::VERIFIED_META, 1 );

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'lux_verified_members',
			[
				'verification_status' => 'approved',
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'user_id' => $user_id ]
		);

		$email = self::get_approval_email( $user_id );
		if ( $email ) {
			wp_mail( $email['to'], $email['subject'], $email['body'] );
		}

		self::audit( get_current_user_id(), 'approve', [ 'user_id' => $user_id ] );
	}

	public static function reject( int $user_id, string $notes = '' ): void {
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

		$email = self::get_rejection_email( $user_id, $notes );
		if ( $email ) {
			wp_mail( $email['to'], $email['subject'], $email['body'] );
		}

		self::audit( get_current_user_id(), 'reject', [
			'user_id' => $user_id,
			'notes'   => $notes,
		] );
	}

	public static function get_approval_email( int $user_id ): array {

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [];
		}

		$subject = 'You are officially verified 🎉';
		$message = "Hi {$user->display_name},\n\n".
		           "Your creator account is now verified.\n\n".
		           "Upload here:\nhttps://lucifercruz.com/submit-your-video/\n\n".
		           "— Lucifer Cruz Studios";

		return [
			'to' => $user->user_email,
			'subject' => $subject,
			'body' => $message,
		];
	}

	public static function get_rejection_email( int $user_id, string $notes = '' ): array {

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [];
		}

		$subject = 'Verification update';
		$message = "Hi {$user->display_name},\n\n".
			"Your creator verification request was not approved at this time.\n\n".
			( $notes ? "Notes from the reviewer:\n{$notes}\n\n" : '' ).
			"Please update your submission and try again.\n\n".
			"— Lucifer Cruz Studios";

		return [
			'to' => $user->user_email,
			'subject' => $subject,
			'body' => $message,
		];
	}

	public static function audit( int $admin_user_id, string $action, array $meta = [] ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'luxvv_audit',
			[
				'created_at' => current_time( 'mysql' ),
				'admin_user_id' => $admin_user_id,
				'target_user_id' => (int) ( $meta['user_id'] ?? 0 ),
				'action' => sanitize_key( $action ),
				'meta' => wp_json_encode( $meta ),
				'ip' => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
			],
			[ '%s', '%d', '%d', '%s', '%s', '%s' ]
		);
	}

	public static function derive_status_from_meta( int $user_id ): string {
		$is_verified = (int) get_user_meta( $user_id, self::VERIFIED_META, true ) === 1;
		if ( $is_verified ) {
			return 'approved';
		}

		$s1 = (int) get_user_meta( $user_id, self::STEP1, true );
		$s2 = (int) get_user_meta( $user_id, self::STEP2, true );
		$s3 = (int) get_user_meta( $user_id, self::STEP3, true );

		if ( $s1 && $s2 && $s3 ) {
			return 'ready_for_review';
		}
		if ( $s1 && $s2 ) {
			return 'step2_completed';
		}
		if ( $s1 ) {
			return 'step1_completed';
		}
		return 'started';
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

	public static function shortcode_w9_form(): string {
		if ( ! is_user_logged_in() ) {
			return '<div class="luxvv-blocked">Please log in to submit your W-9.</div>';
		}

		$form_id = (int) Settings::get( 'w9_form_id' );
		if ( ! $form_id ) {
			return '<div class="luxvv-blocked">W-9 form is not configured.</div>';
		}

		$status = get_user_meta( get_current_user_id(), 'luxvv_w9_status', true );
		$submitted_at = get_user_meta( get_current_user_id(), 'luxvv_w9_submitted_at', true );

		$summary = $status
			? '<div class="luxvv-note">W-9 Status: ' . esc_html( ucfirst( $status ) ) . ( $submitted_at ? ' (Submitted ' . esc_html( $submitted_at ) . ')' : '' ) . '</div>'
			: '';

		return $summary . do_shortcode( '[forminator_form id="' . $form_id . '"]' );
	}

	private static function store_w9_submission( int $user_id, array $data ): void {
		$fields = self::extract_w9_fields( $data );

		$tax_id = '';
		$tax_type = '';
		if ( ! empty( $fields['ein'] ) ) {
			$tax_id = $fields['ein'];
			$tax_type = 'ein';
		} elseif ( ! empty( $fields['ssn'] ) ) {
			$tax_id = $fields['ssn'];
			$tax_type = 'ssn';
		}

		$tax_id = Helpers::normalize_tax_id( $tax_id );
		$tax_last4 = $tax_id ? substr( $tax_id, -4 ) : '';
		$tax_encrypted = $tax_id ? Helpers::encrypt_sensitive( $tax_id ) : '';

		update_user_meta( $user_id, 'luxvv_w9_legal_name', $fields['legal_name'] );
		update_user_meta( $user_id, 'luxvv_w9_business_name', $fields['business_name'] );
		update_user_meta( $user_id, 'luxvv_w9_entity_type', $fields['entity_type'] );
		update_user_meta( $user_id, 'luxvv_w9_address', $fields['address'] );
		update_user_meta( $user_id, 'luxvv_w9_city', $fields['city'] );
		update_user_meta( $user_id, 'luxvv_w9_state', $fields['state'] );
		update_user_meta( $user_id, 'luxvv_w9_zip', $fields['zip'] );
		update_user_meta( $user_id, 'luxvv_w9_signature', $fields['signature'] );
		update_user_meta( $user_id, 'luxvv_w9_tax_type', $tax_type );
		update_user_meta( $user_id, 'luxvv_w9_tax_id_last4', $tax_last4 );

		if ( $tax_encrypted ) {
			update_user_meta( $user_id, 'luxvv_w9_tax_id_encrypted', $tax_encrypted );
		}

		if ( $tax_type === 'ein' ) {
			update_user_meta( $user_id, 'luxvv_ein_masked', '***-**-' . $tax_last4 );
			delete_user_meta( $user_id, 'luxvv_ssn_last4' );
		} elseif ( $tax_type === 'ssn' ) {
			update_user_meta( $user_id, 'luxvv_ssn_last4', $tax_last4 );
			delete_user_meta( $user_id, 'luxvv_ein_masked' );
		}

		$field_keys = array_map( 'strval', array_keys( $data ) );
		update_user_meta( $user_id, 'luxvv_w9_field_keys', wp_json_encode( $field_keys ) );

		update_user_meta( $user_id, 'luxvv_w9_status', 'submitted' );
		update_user_meta( $user_id, 'luxvv_w9_submitted_at', current_time( 'mysql' ) );

		self::audit( get_current_user_id(), 'w9_submit', [
			'user_id' => $user_id,
			'tax_type' => $tax_type,
			'last4' => $tax_last4,
		] );
	}

	private static function extract_w9_fields( array $data ): array {
		$lookup = static function ( array $keys ) use ( $data ): string {
			foreach ( $data as $key => $value ) {
				$key = strtolower( (string) $key );
				foreach ( $keys as $match ) {
					if ( strpos( $key, $match ) !== false ) {
						return is_array( $value ) ? (string) reset( $value ) : (string) $value;
					}
				}
			}
			return '';
		};

		return [
			'legal_name' => sanitize_text_field( $lookup( [ 'legal', 'full_name', 'name' ] ) ),
			'business_name' => sanitize_text_field( $lookup( [ 'business', 'company' ] ) ),
			'entity_type' => sanitize_text_field( $lookup( [ 'entity', 'tax_classification' ] ) ),
			'address' => sanitize_text_field( $lookup( [ 'address' ] ) ),
			'city' => sanitize_text_field( $lookup( [ 'city' ] ) ),
			'state' => sanitize_text_field( $lookup( [ 'state' ] ) ),
			'zip' => sanitize_text_field( $lookup( [ 'zip', 'postal' ] ) ),
			'ssn' => sanitize_text_field( $lookup( [ 'ssn', 'social' ] ) ),
			'ein' => sanitize_text_field( $lookup( [ 'ein', 'tax_id', 'tin' ] ) ),
			'signature' => sanitize_text_field( $lookup( [ 'signature', 'acknowledg' ] ) ),
		];
	}
}
