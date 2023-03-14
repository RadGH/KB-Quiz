<?php

/**
 * Generates content used on the quiz results page (gravity forms confirmation screen)
 *
 * @param $entry
 * @param $form
 * @param string|null $format
 *
 * @return mixed
 */
function kb_generate_quiz_results_html( $entry, $form, $format ) {
	global $KB_Quiz, $KB_Quiz_PDF;
	
	$prefix = $KB_Quiz->get_settings_prefix( $entry );
	$chart_src = $KB_Quiz->get_chart_image_src( $entry['id'] );
	
	// Get content
	$title = get_field( "{$prefix}_form_confirmation_title", 'kb_quiz' );
	$intro = get_field( "{$prefix}_form_confirmation_intro", 'kb_quiz', false );
	$next_steps = get_field( "{$prefix}_form_confirmation_next_steps", 'kb_quiz', false );
	$detailed_results = get_field( "{$prefix}_form_confirmation_detailed_results", 'kb_quiz', false );
	
	if ( $format == 'title' ) {
		return $title;
	}
	
	// All content and the template below support gravity form merge tags.
	// Refer to form #4 for merge tags and field IDs
	// @see https://karenbenoy.com/wp-admin/admin.php?page=gf_edit_forms&id=4
	ob_start();
	?>
	
	<div class="shortcode_kb_quiz_confirmation">
		
		<div class="quiz-results-section quiz-results-intro">
			<div class="results-title"><h2><?php echo $title; ?></h2></div>
			<div class="results-content"><?php echo wpautop($intro); ?></div>
		</div>
		
		<?php if ( $format !== 'email' ) { ?>
		<div class="quiz-results-section quiz-results-chart">
			<div class="results-content">
				<div class="chart-shortcode"><img src="<?php echo $chart_src; ?>"></div>
				<p style="text-align: center;"><a class="button button-primary" href="{embed_url}?kb_pdf={[Hidden] Secret Key:31}" target="_blank"><strong>Save as PDF</strong></a></p>
			</div>
		</div>
		<?php } ?>
		
		<div class="next-steps-container">
			<div class="quiz-results-section quiz-results-next-steps">
				<div class="results-title"><h2>Next Steps</h2></div>
				<div class="results-content"><?php echo wpautop($next_steps); ?></div>
			</div>
		</div>
		
		<?php if ( $format !== 'email' ) { ?>
		<div class="quiz-results-section quiz-results-detailed">
			<div class="results-title">
				<h2>Detailed Results</h2>
				<h3><?php echo $title; ?></h3>
			</div>
			<div class="results-content"><?php echo wpautop($detailed_results); ?></div>
		</div>
		<?php } ?>
		
	</div>
	<?php
	$html = trim( ob_get_clean());
	
	$html = $KB_Quiz->expand_shortcode_and_merge_tags( $html, $form, $entry );
	
	return $html;
}