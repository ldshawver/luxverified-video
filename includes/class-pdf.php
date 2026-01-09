<?php
namespace LuxVerified;

use setasign\Fpdi\Tcpdf\Fpdi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PDF {

	/**
	 * Generate IRS W-9 PDF
	 */
	public static function generate_w9_pdf( array $data, string $template ): string {

		if ( ! file_exists( $template ) ) {
			wp_die(
				'W-9 template file is missing. Please contact the site administrator.',
				'W-9 Generation Error',
				[ 'response' => 500 ]
			);
		}

		$dir = self::get_upload_dir();
		$file = sprintf(
			'w9-%s-%s.pdf',
			time(),
			wp_generate_password( 8, false, false )
		);

		$path = $dir . $file;

		$pdf = new Fpdi();
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );

		$page_count = $pdf->setSourceFile( $template );

		for ( $i = 1; $i <= $page_count; $i++ ) {
			$tpl  = $pdf->importPage( $i );
			$size = $pdf->getTemplateSize( $tpl );

			$pdf->AddPage(
				$size['orientation'],
				[ $size['width'], $size['height'] ]
			);

			$pdf->useTemplate( $tpl );

			if ( $i === 1 ) {
				self::overlay_w9_fields( $pdf, $data );
			}
		}

		$pdf->Output( $path, 'F' );

		return $path;
	}

	/**
	 * Generate payout receipt PDF
	 */
	public static function generate_payout_receipt( array $data ): string {

		$upload = wp_upload_dir();
		$dir = trailingslashit( $upload['basedir'] ) . 'luxvv/receipts/';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$file = sprintf(
			'payout-%d-%d.pdf',
			(int) ( $data['payout_id'] ?? 0 ),
			time()
		);

		$path = $dir . $file;

		$pdf = new Fpdi();
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );
		$pdf->AddPage();
		$pdf->SetFont( 'helvetica', '', 12 );

		$pdf->Write( 0, 'LUX Verified Video – Payout Receipt' );
		$pdf->Ln( 8 );

		$lines = [
			'Receipt ID: ' . (int) ( $data['payout_id'] ?? 0 ),
			'Creator: ' . (string) ( $data['creator_name'] ?? '' ),
			'Email: ' . (string) ( $data['creator_email'] ?? '' ),
			'Period: ' . (string) ( $data['period_start'] ?? '' ) . ' → ' . (string) ( $data['period_end'] ?? '' ),
			'Views ≥ 20s: ' . (int) ( $data['views_20s'] ?? 0 ),
			'CPM (cents): ' . (int) ( $data['cpm_cents'] ?? 0 ),
			'Base payout (cents): ' . (int) ( $data['base_payout_cents'] ?? 0 ),
			'Bonus %: ' . (float) ( $data['bonus_pct'] ?? 0 ),
			'Total payout (cents): ' . (int) ( $data['payout_cents'] ?? 0 ),
			'Paid at: ' . (string) ( $data['paid_at'] ?? '' ),
			'Paid by (admin ID): ' . (int) ( $data['paid_by'] ?? 0 ),
			'Reference: ' . (string) ( $data['paid_reference'] ?? '' ),
			'Notes: ' . (string) ( $data['paid_notes'] ?? '' ),
		];

		foreach ( $lines as $line ) {
			$pdf->Write( 0, $line );
			$pdf->Ln( 6 );
		}

		$pdf->Output( $path, 'F' );

		return $path;
	}

	/**
	 * Overlay W-9 fields on page 1
	 */
	private static function overlay_w9_fields( Fpdi $pdf, array $data ): void {

		$pdf->SetFont( 'helvetica', '', 10 );
		$pdf->SetTextColor( 0, 0, 0 );

		$get = static fn( string $key ): string =>
			isset( $data[ $key ] ) ? wp_strip_all_tags( (string) $data[ $key ] ) : '';

		$pdf->SetXY( 24, 36 );  $pdf->Write( 0, $get( 'name' ) );
		$pdf->SetXY( 24, 45 );  $pdf->Write( 0, $get( 'business' ) );
		$pdf->SetXY( 24, 63 );  $pdf->Write( 0, $get( 'address' ) );
		$pdf->SetXY( 24, 71 );  $pdf->Write( 0, $get( 'city_state_zip' ) );
		$pdf->SetXY( 140, 98 ); $pdf->Write( 0, $get( 'ein' ) );

		if ( ! empty( $data['ssn'] ) ) {
			$pdf->SetXY( 140, 106 );
			$pdf->Write( 0, $get( 'ssn' ) );
		} elseif ( ! empty( $data['ssn_last4'] ) ) {
			$pdf->SetXY( 140, 106 );
			$pdf->Write( 0, '***-**-' . $get( 'ssn_last4' ) );
		}

		$pdf->SetXY( 160, 246 );
		$pdf->Write( 0, date( 'm/d/Y' ) );
	}

	/**
	 * Base upload directory helper
	 */
	private static function get_upload_dir(): string {

		$upload = wp_upload_dir();
		$dir = trailingslashit( $upload['basedir'] ) . 'luxvv/';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$htaccess = $dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" );
		}

		$index = $dir . 'index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' );
		}

		return $dir;
	}
}
