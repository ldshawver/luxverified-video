<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) exit;

final class PDF_Controller {

	public static function init(): void {
		add_action(
			'admin_post_luxvv_generate_w9',
			[ __CLASS__, 'generate' ]
		);
	}

	public static function generate(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		check_admin_referer( 'luxvv_generate_w9' );

		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		if ( $user_id <= 0 ) {
			wp_die( 'Invalid user' );
		}

		$data = [
			'name' => trim(
				get_user_meta( $user_id, 'first_name', true ) . ' ' .
				get_user_meta( $user_id, 'last_name', true )
			),
			'business' => get_user_meta( $user_id, 'luxvv_business_name', true ),
			'ein'      => get_user_meta( $user_id, 'luxvv_ein_masked', true ),
			'ssn'      => get_user_meta( $user_id, 'luxvv_ssn_last4', true ),
			'address'  => get_user_meta( $user_id, 'billing_address_1', true ),
			'city'     => get_user_meta( $user_id, 'billing_city', true ),
			'state'    => get_user_meta( $user_id, 'billing_state', true ),
			'zip'      => get_user_meta( $user_id, 'billing_postcode', true ),
		];

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
		$data['ssn_last4'] = $data['ssn'] ?? '';

		$file = PDF::generate_w9_pdf( $data, $template );
		if ( ! $file ) {
			wp_die( 'Unable to generate W-9 PDF.' );
		}
		update_user_meta( $user_id, 'luxvv_w9_pdf', $file );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="W9-' . $user_id . '.pdf"' );
		readfile( $file );
		exit;
	}
}
