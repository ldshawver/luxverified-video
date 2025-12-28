<?php
namespace LuxVerified;

use setasign\Fpdi\Tcpdf\Fpdi;

if ( ! defined( 'ABSPATH' ) ) exit;

final class PDF {

	public static function generate_w9_pdf( array $data, string $template ): string {

		if ( ! file_exists( $template ) ) {
			throw new \RuntimeException( 'W-9 template missing.' );
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
