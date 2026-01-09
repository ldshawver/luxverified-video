<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class PDF_Controller {

	public static function init(): void {
		add_action(
			'admin_post_luxvv_generate_w9',
			[ __CLASS__, 'generate' ]
		);
		add_action(
			'admin_post_luxvv_download_w9',
			[ __CLASS__, 'download' ]
		);
		add_action(
			'admin_post_luxvv_w9_self_test',
			[ __CLASS__, 'run_self_test' ]
		);
	}

	public static function generate(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		error_log( wp_json_encode( [
			'component' => 'w9_pdf',
			'step' => 'start',
			'status' => 'ok',
			'user_id' => $user_id,
		] ) );

		if ( ! class_exists( '\\setasign\\Fpdi\\Tcpdf\\Fpdi' ) ) {
			update_option( 'luxvv_fpdi_missing', current_time( 'timestamp' ), false );
			error_log( wp_json_encode( [
				'component' => 'w9_pdf',
				'step' => 'fpdi_check',
				'status' => 'missing',
				'user_id' => $user_id,
			] ) );
			wp_die( 'W-9 PDF library missing. Please run Composer install for FPDI/TCPDF.' );
		}

		check_admin_referer( 'luxvv_generate_w9' );

		if ( $user_id <= 0 ) {
			wp_die( 'Invalid user' );
		}

		error_log( wp_json_encode( [
			'component' => 'w9_pdf',
			'step' => 'fpdi_check',
			'status' => 'ok',
			'user_id' => $user_id,
		] ) );

		$data = [
			'name' => trim(
				get_user_meta( $user_id, 'first_name', true ) . ' ' .
				get_user_meta( $user_id, 'last_name', true )
			),
			'business' => get_user_meta( $user_id, Verification::BUSINESS_NAME_META, true ),
			'address'  => get_user_meta( $user_id, 'billing_address_1', true ),
			'city'     => get_user_meta( $user_id, 'billing_city', true ),
			'state'    => get_user_meta( $user_id, 'billing_state', true ),
			'zip'      => get_user_meta( $user_id, 'billing_postcode', true ),
		];

		$encrypted_tax_id = (string) get_user_meta( $user_id, Verification::TAX_ID_ENCRYPTED_META, true );
		$tax_type = (string) get_user_meta( $user_id, Verification::TAX_ID_TYPE_META, true );
		$tax_id = Helpers::decrypt_tax_id( $encrypted_tax_id );

		if ( $tax_id ) {
			if ( 'ein' === $tax_type ) {
				$data['ein'] = Helpers::format_tax_id( $tax_id, 'ein' );
			} else {
				$data['ssn'] = Helpers::format_tax_id( $tax_id, 'ssn' );
			}
		}

		$template = LUXVV_DIR . 'assets/w9-template.pdf';

		if ( ! file_exists( $template ) ) {
			wp_die( 'W-9 template missing. Please upload assets/w9-template.pdf.' );
		}

		$city_state_zip = trim( implode( ' ', array_filter( [
			$data['city'],
			$data['state'],
			$data['zip'],
		] ) ) );

		$data['city_state_zip'] = $city_state_zip;
		$file = PDF::generate_w9_pdf( $data, $template );
		if ( ! $file ) {
			error_log( wp_json_encode( [
				'component' => 'w9_pdf',
				'step' => 'generate',
				'status' => 'failed',
				'user_id' => $user_id,
			] ) );
			wp_die( 'Unable to generate W-9 PDF.' );
		}
		error_log( wp_json_encode( [
			'component' => 'w9_pdf',
			'step' => 'generate',
			'status' => 'ok',
			'user_id' => $user_id,
		] ) );
		update_user_meta( $user_id, 'luxvv_w9_pdf', $file );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="W9-' . $user_id . '.pdf"' );
		readfile( $file );
		exit;
	}

	public static function download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		check_admin_referer( 'luxvv_download_w9' );

		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		if ( $user_id <= 0 ) {
			wp_die( 'Invalid user' );
		}

		$file = (string) get_user_meta( $user_id, 'luxvv_w9_pdf', true );
		if ( ! $file || ! file_exists( $file ) ) {
			wp_die( 'W-9 file not found.' );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="W9-' . $user_id . '.pdf"' );
		readfile( $file );
		exit;
	}

	public static function run_self_test(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		check_admin_referer( 'luxvv_w9_self_test' );

		$results = PDF::self_test();
		set_transient( 'luxvv_w9_self_test', $results, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=lux-verified-compliance&w9_test=1' ) );
		exit;
	}
}
