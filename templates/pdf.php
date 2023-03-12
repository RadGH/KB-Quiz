<?php

/**
 * Generates quiz results PDF using the mPDF 7 library
 *
 * @param $pdf
 * @param $entry
 * @param $form
 *
 * @return Mpdf\Mpdf
 */
function kb_generate_quiz_pdf( $pdf, $entry, $form ) {
	global $KB_Quiz, $KB_Quiz_PDF;
	
	$prefix = $KB_Quiz->get_settings_prefix( $entry );
	$chart_src = $KB_Quiz->get_chart_image_src( $entry['id'] );
	
	// Get content
	$title = get_field( "{$prefix}_form_confirmation_title", 'kb_quiz' );
	$intro = get_field( "{$prefix}_form_confirmation_intro", 'kb_quiz', false );
	$next_steps = get_field( "{$prefix}_form_confirmation_next_steps", 'kb_quiz', false );
	$detailed_results = get_field( "{$prefix}_form_confirmation_detailed_results", 'kb_quiz', false );
	
	// All content and the template below support gravity form merge tags.
	// Refer to form #4 for merge tags and field IDs
	// @see https://karenbenoy.com/wp-admin/admin.php?page=gf_edit_forms&id=4
	
	
	// Letter size (mm): 612.00, 792.00
	ob_start();
	?>
	<div class="shortcode_kb_quiz_confirmation" style="font-family: lato, sans-serif;">
		<div class="quiz-results-section quiz-results-intro">
			<div class="results-title"><h2><?php echo $title; ?></h2></div>
			<div class="results-content"><?php echo $intro; ?></div>
		</div>
		<div class="quiz-results-section quiz-results-chart">
			<div class="results-content">
				<div class="chart-shortcode"><img src="<?php echo $chart_src; ?>"></div>
			</div>
		</div>
		<div class="next-steps-container">
			<div class="quiz-results-section quiz-results-intro">
				<div class="results-title"><h2>Next Steps</h2></div>
				<div class="results-content"><?php echo $next_steps; ?></div>
			</div>
		</div>
		<div class="quiz-results-section quiz-results-intro">
			<div class="results-title">
				<h2>Detailed Results</h2>
				<h3><?php echo $title; ?></h3>
			</div>
			<div class="results-content"><?php echo $detailed_results; ?></div>
		</div>
	</div>
	<?php
	$html = trim( ob_get_clean() );
	
	$html = $KB_Quiz->expand_shortcode_and_merge_tags( $html, $form, $entry );
	
	$pdf->WriteHTML( $html );
	
	return $pdf;
}