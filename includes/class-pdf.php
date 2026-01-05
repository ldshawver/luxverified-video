<?php
namespace LuxVerified;

use setasign\Fpdi\Tcpdf\Fpdi;

if ( ! defined( 'ABSPATH' ) ) exit;

final class PDF {

	public static function generate_w9_pdf( array $data, string $template ): string {

		if ( ! file_exists( $template ) ) {
			return '';
		}

		$upload = wp_upload_dir();
		$dir = trailingslashit( $upload['basedir'] ) . 'luxvv/';
		if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );

		$file = 'w9-' . time() . '-' . wp_generate_password(6,false,false) . '.pdf';
		$path = $dir . $file;

		$pdf = new Fpdi();
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);

		$pageCount = $pdf->setSourceFile( $template );

		for ( $i = 1; $i <= $pageCount; $i++ ) {
			$tpl = $pdf->importPage($i);
			$size = $pdf->getTemplateSize($tpl);

			$pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
			$pdf->useTemplate($tpl);

			if ( $i === 1 ) {
				self::overlay( $pdf, $data );
			}
		}

		$pdf->Output( $path, 'F' );
		return $path;
	}

	public static function generate_payout_receipt( array $data ): string {
		$upload = wp_upload_dir();
		$dir = trailingslashit( $upload['basedir'] ) . 'luxvv/receipts/';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$file = 'payout-' . (int) $data['payout_id'] . '-' . time() . '.pdf';
		$path = $dir . $file;

		$pdf = new Fpdi();
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );
		$pdf->AddPage();
		$pdf->SetFont( 'helvetica', '', 12 );

		$pdf->Write( 0, 'LUX Verified Video - Payout Receipt' );
		$pdf->Ln( 8 );

		$lines = [
			'Receipt ID: ' . (int) $data['payout_id'],
			'Creator: ' . (string) $data['creator_name'],
			'Email: ' . (string) $data['creator_email'],
			'Period: ' . (string) $data['period_start'] . ' → ' . (string) $data['period_end'],
			'Views ≥ 20s: ' . (int) $data['views_20s'],
			'CPM (cents): ' . (int) $data['cpm_cents'],
			'Base payout (cents): ' . (int) $data['base_payout_cents'],
			'Bonus %: ' . (float) $data['bonus_pct'],
			'Total payout (cents): ' . (int) $data['payout_cents'],
			'Paid at: ' . (string) $data['paid_at'],
			'Paid by (admin ID): ' . (int) $data['paid_by'],
			'Reference: ' . (string) $data['paid_reference'],
			'Notes: ' . (string) $data['paid_notes'],
		];

		foreach ( $lines as $line ) {
			$pdf->Write( 0, $line );
			$pdf->Ln( 6 );
		}

		$pdf->Output( $path, 'F' );

		return $path;
	}

	private static function overlay( Fpdi $pdf, array $d ): void {

		$pdf->SetFont('helvetica','',10);
		$pdf->SetTextColor(0,0,0);

		$f = fn($k) => isset($d[$k]) ? wp_strip_all_tags($d[$k]) : '';

		$pdf->SetXY(24, 36);  $pdf->Write(0, $f('name'));
		$pdf->SetXY(24, 45);  $pdf->Write(0, $f('business'));
		$pdf->SetXY(24, 63);  $pdf->Write(0, $f('address'));
		$pdf->SetXY(24, 71);  $pdf->Write(0, $f('city_state_zip'));
		$pdf->SetXY(140, 98); $pdf->Write(0, $f('ein'));

		if ( ! empty($d['ssn_last4']) ) {
			$pdf->SetXY(140, 106);
			$pdf->Write(0, '***-**-' . $f('ssn_last4'));
		}

		$pdf->SetXY(160, 246);
		$pdf->Write(0, date('m/d/Y'));
	}
}
