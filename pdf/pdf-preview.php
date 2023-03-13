<?php

class KB_PDF_Preview {
	
	public $content = '';
	public $extra_css = '';
	
	public function construct() {
	}
	
	public function SetTitle( $title ) {
	}
	
	public function WriteHTML( $string, $ignore_dom = 0 ) {
		if ( $ignore_dom === 0 ) {
			$this->content .= "\n\n" . $string;
		}else{
			$this->extra_css .= "\n" . $string;
		}
	}
	
	public function Output() {
		$stylesheet = file_get_contents(__DIR__ . '/../assets/previewpdf.css');
		$this->extra_css .= $stylesheet;
		
		echo '<div class="previewpdf">';
		echo $this->content;
		echo '</div>';
		
		echo "\n\n\n";
		echo '<style type="text/css">';
		echo $this->extra_css;
		echo '</style>';
		exit;
	}
	
}