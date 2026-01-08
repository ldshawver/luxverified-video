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
			'name' => get_user_meta( $user_id, 'luxvv_w9_legal_name', true ),
			'business' => get_user_meta( $user_id, 'luxvv_w9_business_name', true ),
			'address'  => get_user_meta( $user_id, 'luxvv_w9_address', true ),
			'city'     => get_user_meta( $user_id, 'luxvv_w9_city', true ),
			'state'    => get_user_meta( $user_id, 'luxvv_w9_state', true ),
			'zip'      => get_user_meta( $user_id, 'luxvv_w9_zip', true ),
		];

		if ( ! $data['name'] ) {
			$data['name'] = trim(
				get_user_meta( $user_id, 'first_name', true ) . ' ' .
				get_user_meta( $user_id, 'last_name', true )
			);
		}

		if ( ! $data['address'] ) {
			$data['address'] = get_user_meta( $user_id, 'billing_address_1', true );
			$data['city']    = get_user_meta( $user_id, 'billing_city', true );
			$data['state']   = get_user_meta( $user_id, 'billing_state', true );
			$data['zip']     = get_user_meta( $user_id, 'billing_postcode', true );
		}

		$tax_type = get_user_meta( $user_id, 'luxvv_w9_tax_type', true );
		$encrypted = get_user_meta( $user_id, 'luxvv_w9_tax_id_encrypted', true );
		$tax_id = $encrypted ? Helpers::decrypt_sensitive( $encrypted ) : '';
		$tax_id = Helpers::format_tax_id( $tax_id, $tax_type );

		$data['ein'] = $tax_type === 'ein' ? $tax_id : '';
		if ( $tax_type === 'ein' && ! $data['ein'] ) {
			$data['ein'] = get_user_meta( $user_id, 'luxvv_ein_masked', true );
		}

		$data['ssn'] = $tax_type === 'ssn' ? $tax_id : '';
		$data['ssn_last4'] = get_user_meta( $user_id, 'luxvv_w9_tax_id_last4', true );

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
