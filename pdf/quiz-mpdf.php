<?php

if ( function_exists( 'opcache_invalidate') ) {
opcache_invalidate( __FILE__ );
}


class KB_Quiz_PDF {
	
	public function __construct() {
		
		// PDF generation is done using mpdf
		// @see https://mpdf.github.io/installation-setup/installation-v7-x.html
		
		// Generate a test pdf
		// https://karenbenoy.com/?kb_pdf=640b80a562034
		if ( isset($_GET['kb_pdf']) ) {
			add_action( 'init', array( $this, 'display_pdf_to_visitor' ) );
		}
		
	}
	
	public function create_pdf() {
		require_once( __DIR__ . '/vendor/autoload.php' );
		
		$defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
		$fontDirs = $defaultConfig['fontDir'];
		
		$defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
		$fontData = $defaultFontConfig['fontdata'];
		
		$font_path = get_stylesheet_directory() . '/_includes/functions/quiz/assets/';
		
		$pdf = new \Mpdf\Mpdf([
			'fontDir' => array_merge($fontDirs, [ $font_path ]),
			'fontdata' => $fontData +
				[
					'angerthas' => [
						'R' => 'angerthas.ttf',
					],
					'inkfree' => [
						'R' => 'Inkfree.ttf',
					],
				],
		]);
		
		/*
		$defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
		$defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
		
		$fontDirs = (array) $defaultConfig['fontDir'];
		$fontData = (array) $defaultFontConfig['fontdata'];
		
		$font_path = get_stylesheet_directory() . '/_includes/functions/quiz/pdf/fonts/';
		
		$fonts = array(
			'lato' => array(
				'R' => 'lato-regular.ttf',
				'I' => 'lato-italic.ttf',
				'B' => 'lato-bold.ttf',
				'BI' => 'lato-bold-italic.ttf',
			),
			'juana' => array(
				'R' => 'juana-regular.ttf',
				'i' => 'juana-italic.ttf',
			),
			'silversouthscript' => array(
				'R' => 'silver-south-script.ttf',
			),
		);
		
		$args = array(
			'tempDir' => __DIR__ . '/tmp/',
			'fontDir' => array_merge( $fontDirs, array( $font_path ) ),
			'fontdata' => array_merge( $fontData, $fonts ),
			// 'default_font' => 'lato',
		);
		
		// $args dump: https://radleysustaire.com/s3/81aa73/
		
		$pdf = new Mpdf\Mpdf( $args );
		*/
		
		return $pdf;
	}
	
	// https://karenbenoy.com/?kb_pdf=640b80a562034
	public function display_pdf_to_visitor() {
		global $KB_Quiz;
		
		$key = $_GET['kb_pdf'] ?? false;
		
		if ( ! $key ) {
			echo 'Coaching Style Quiz PDF Error: Invalid secret key specified.';
			exit;
		}
		
		$entry = $KB_Quiz->get_entry_from_key( $key );
		$form = GFAPI::get_entry( $entry['form_id'] );
		
		if ( !$entry || !$form ) {
			echo 'Coaching Style Quiz PDF Error: Invalid entry or form provided.';
			exit;
		}
		
		// Do not put PDF on Google
		header( "X-Robots-Tag: noindex, nofollow" );
		
		// Do not cache PDF
		header( "Pragma: no-cache" );
		header( "Expires: 0" );
		header( "Cache-Control: no-store, no-cache, must-revalidate" );
		header( "Cache-Control: post-check=0, pre-check=0", false );
		
		require_once( dirname(__DIR__) . '/templates/pdf.php' );
		
		// Create PDF
		$pdf = $this->create_pdf();
		
		/*
		// works!
		$pdf->WriteHTML( '<span style="font-family: dejavuserif;">Your coaching competence shows in how SERIF</span>' );
		$pdf->WriteHTML( '<span style="font-family: dejavusans;">Your coaching competence shows in how SANS</span>' );
		// $pdf->WriteHTML( '<span style="font-family: silversouthscript;">Your coaching competence shows in how SANS</span>' );
		
		$pdf->SetFont( 'dejavuserif', 'R' );
		$pdf->WriteHTML( 'Your coaching competence shows in how SERIF 2' );
		
		$pdf->SetFont( 'dejavusans', 'R' );
		$pdf->WriteHTML( 'Your coaching competence shows in how SANS 2' );
		/**/
		
		
		$pdf->WriteHtml('<html>
    <head>
		<style>
		.inkfree {
			font-family: "Ink Free";
		}
		</style>
    </head>
    <body>
<h1>Using custom font in the document</h1>

<p style=\'font-family: angerthas\'>This example shows how to keep default font families while adding a custom font directory and definitions.</p>

<p style="font-family: \'Ink Free\'">Inkfree line of text</p>

<p style="font-family: "Ink Free";">Inkfree line of text that is not working</p>

<p style=\'font-family: "Ink Free"\'>Inkfree line of text that is not working</p>

<p class="inkfree">Inkfree line of text</p>

</body>
</html>');
		/**/
		
		$pdf->Output();
		
		exit;
	}
	
}

global $KB_Quiz_PDF;
$KB_Quiz_PDF = new KB_Quiz_PDF();