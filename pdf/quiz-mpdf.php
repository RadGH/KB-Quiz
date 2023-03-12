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
		
		$path = __DIR__;
		
		$pdf = new \Mpdf\Mpdf(array(
			'fontDir' => array_merge($fontDirs, array($path)),
			'fontdata' => $fontData +
				array(
					'lato' => array(
						'R' => 'fonts/lato-regular.ttf',
						'I' => 'fonts/lato-italic.ttf',
						'B' => 'fonts/lato-bold.ttf',
						'BI' => 'fonts/lato-bold-italic.ttf',
					),
					'juana' => array(
						'R' => 'fonts/juana-regular.ttf',
						'i' => 'fonts/juana-italic.ttf',
					),
					'silversouthscript' => array(
						'R' => 'fonts/silver-south-script.ttf',
					),
				),
		));
		
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
		
		// Clear output buffer
		// IDK why but we need this or else the pdf gives an error
		ob_end_clean();
		
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
		
		// Font test page
		$this->add_test_fonts_page( $pdf );
		
		$pdf->Output();
		
		exit;
	}
	
	public function add_test_fonts_page( $pdf ) {
		ob_start();
		?>
		<html>
		<head>
			<style>
				.lato {
					font-family: "lato";
				}
				.juana {
					font-family: "Juana";
				}
				.silversouthscript {
					font-family: "Silversouthscript";
				}
			</style>
		</head>
		<body>
		<h1>Using custom font in the document</h1>
		<p>version 1.0</p>
		
		<p>Example - Default text</p>
		<p style="font-family: Lato">Example - Lato</p>
		<p style="font-family: Lato, sans-serif">Example - Lato, sans-serif</p>
		<p style="font-family: 'Lato'">Example - 'Lato'</p>
		<p class="lato">Example - class="lato"</p>
		
		<p style='font-family: "Lato"'>Example - "Lato" (Double quotes do not work for some reason)</p>
		
		<hr>
		
		<p style="font-family: Lato;">Lato Regular</p>
		<p style="font-family: Lato;"><strong>Lato Bold</strong></p>
		<p style="font-family: Lato;"><em>Lato Italic</em></p>
		<p style="font-family: Lato;"><strong><em>Lato Bold Italic</em></strong></p>
		
		<hr>
		
		<p style="font-family: juana;">Juana Regular</p>
		<p style="font-family: juana;"><em>Juana Italic</em></p>
		<p style="font-family: silversouthscript;">Silversouthscript</p>
		
		</body>
		</html>
		<?php
		$html = ob_get_clean();
		
		$pdf->WriteHtml($html);
	}
	
}

global $KB_Quiz_PDF;
$KB_Quiz_PDF = new KB_Quiz_PDF();