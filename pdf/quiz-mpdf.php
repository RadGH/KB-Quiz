<?php

if ( function_exists( 'opcache_invalidate') ) {
opcache_invalidate( __FILE__ );
}


class KB_Quiz_PDF {
	
	/** @var Mpdf\Mpdf $pdf */
	public $pdf = null;
	
	/** @var array $entry */
	public $entry = null;
	
	/** @var array $form */
	public $form = null;
	
	public function __construct() {
		
		// PDF generation is done using mpdf
		// @see https://mpdf.github.io/installation-setup/installation-v7-x.html
		
		// Display a PDF to a visitor by specifying ?kb_pdf in the URL with the value of a secret key
		// https://karenbenoy.com/?kb_pdf=640b80a562034
		if ( isset($_GET['kb_pdf']) ) {
			add_action( 'init', array( $this, 'display_pdf_to_visitor' ) );
		}
		
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
		
		// Store variables in this object
		$this->entry = $entry;
		$this->form = $form;
		$this->pdf = $this->create_pdf();
		
		// Add CSS from pdf.css
		$this->add_stylesheet();
		
		// Page: Intro
		$this->add_intro_page();
		
		// Page: Font test page
		$this->add_test_fonts_page();
		
		// Finish
		$this->send_pdf();
		
		exit;
	}
	
	/**
	 * Get a value from the entry such as: first_name last_name email
	 *
	 * @param $key
	 *
	 * @return mixed
	 */
	public function get_entry_value( $key ) {
		global $KB_Quiz;
		
		return $KB_Quiz->get_entry_value( $this->entry, $key );
	}
	
	/**
	 * Creates the PDF object with custom settings applied
	 */
	public function create_pdf() {
		require_once( __DIR__ . '/vendor/autoload.php' );
		
		// Use default configs
		/*
		$defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
		$fontDirs = $defaultConfig['fontDir'];
		
		$defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
		$fontData = $defaultFontConfig['fontdata'];
		*/
		$fontDirs = array();
		$fontData = array();
		
		$path = __DIR__;
		
		if ( isset($_GET['previewpdf']) ) {
			return new KB_PDF_Preview();
		}
		
		// Create PDF object
		return new \Mpdf\Mpdf(array(
			
			// Fonts
			'default_font_size' => 14,
			'default_font' => 'lato',
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
			
			// Page settings
			'format' => 'LETTER',
			'orientation' => 'L',
			
			// Margins
			'margin_left'   => 0, // 15,
			'margin_right'  => 0, // 15,
			'margin_top'    => 0, // 16,
			'margin_bottom' => 0, // 16,
			'margin_header' => 0, // 9,
			'margin_footer' => 0, // 9,
			
		));
	}
	
	/*
	 * Sends headers and then streams PDF to the browser
	 */
	public function send_pdf() {
		
		// Clear output buffer - Without this the PDF fails to load.
		ob_end_clean();
		
		// Send headers informing browser that this is a PDF
		// 1. Do not put PDF on Google
		header( "X-Robots-Tag: noindex, nofollow" );
		
		// 2. Do not cache PDF
		header( "Pragma: no-cache" );
		header( "Expires: 0" );
		header( "Cache-Control: no-store, no-cache, must-revalidate" );
		header( "Cache-Control: post-check=0, pre-check=0", false );
		
		// Send PDF to browser
		$this->pdf->Output();
		exit;
		
	}
	
	/*
	 * Add stylesheet to the PDF (embedded directly, not a link)
	 */
	public function add_stylesheet() {
		$stylesheet = file_get_contents(__DIR__ . '/../assets/pdf.css');
		$this->pdf->WriteHTML($stylesheet, 1); // The parameter 1 tells that this is css/style only and no body/html/text
	}
	
	/*
	 * First page of the PDF
	 *
	 * @return void
	 */
	public function add_intro_page() {
		$full_name = trim( $this->get_entry_value( 'first_name' ) . ' ' . $this->get_entry_value( 'last_name' ) );
		
		ob_start();
		?>
<pagebreak page-selector="intro">
	<div class="page page-intro">
		<div class="page-inner">
			<div class="intro-title">
				<h1>What is Your Coaching Style? <em>Quiz</em></h1>
				<h2>Here's How to Be a Transformational Leadership Coach</h2>
			</div>
			
			<div class="report-for">
				<p>Report for: <?php echo $full_name; ?></p>
				<p>Date: <?php echo current_time('m/d/Y'); ?></p>
			</div>
		</div>
	</div>
	
	<div class="copyright left">&copy; 2023 Karen Benoy Coaching. All Rights Reserved.</div>
	
	<div class="logo right">
		<img src="https://karenbenoy.com/wp-content/uploads/2022/05/karen-benoy-logo-full-color-rgb.svg" alt="Karen Brody Logo">
	</div>
</pagebreak>
		<?php
		$html = ob_get_clean();
		
		$this->pdf->WriteHTML($html);
	}
	
	/*
	 * Test page with all our custom fonts
	 */
	public function add_test_fonts_page() {
		ob_start();
		?>
	.lato {
		font-family: "lato";
	}
	.juana {
		font-family: "Juana";
	}
	.silversouthscript {
		font-family: "Silversouthscript";
	}
		<?php
		$html = ob_get_clean();
		
		$this->pdf->WriteHtml($html, 1);
		?>
<pagebreak page-selector="test-fonts">
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
</pagebreak>
		<?php
		$html = ob_get_clean();
		
		$this->pdf->WriteHtml($html);
	}
	
}

global $KB_Quiz_PDF;
$KB_Quiz_PDF = new KB_Quiz_PDF();