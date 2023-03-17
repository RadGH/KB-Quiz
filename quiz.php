<?php

if ( function_exists( 'opcache_invalidate') ) {
opcache_invalidate( __FILE__ );
}

class KB_Quiz {
	
	// Quiz form ID
	// @see https://karenbenoy.com/wp-admin/admin.php?page=gf_edit_forms&id=4
	public $form_id = 4;
	
	public $field_ids = array(
		25,   // sustainable transformation   Which statement best describes your clients:
		5,    // confidence                   Which of the following best describes your use of the silent pause in sessions
		7,    // risk taking                  If you had to pick, which of the following best describes how comfortable you
		11,   // proficiency with tools       Which of the following describes your confidence level when using various tools
		13,   // efficiency / agility         Which statement best describes your time management in client sessions:
		15,   // connecting to values         Which of the following best describes how you relate a client’s challenges
		17,   // connection the dots          Which of the following is most true for you
		19,   // what is not being said       Which statement best matches how you feel about getting to underlying issues?
		21,   // staying present              Which statement best describes your ability to stay in the now during sessions?
		23,   // highest potentiality         Pick the statement that best describes your coaching style:
	);
	
	public $key_id = 31; // secret key field ID
	
	public $first_name_id = 27;
	public $last_name_id = 28;
	public $email_id = 29;
	
	public $confirmation = null;
	public $temp_pdf_path = '';
	
	public function __construct() {
		
		require_once( __DIR__ . '/pdf/quiz-mpdf.php' ); // Adds the PDF functionality to the quiz
		require_once( __DIR__ . '/pdf/pdf-preview.php' ); // Adds an optional preview mode by adding ?previewpdf to a pdf url
		
		// Enqueue quiz css
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// Displays the radar chart SVG for an entry, using the shortcode [kb_spiderweb_chart entry_id="123"]
		add_shortcode( 'kb_spiderweb_chart', array( $this, 'shortcode_kb_spiderweb_chart' ) );
		
		// Displays quiz result content from Theme Settings > Coaching Style Quiz Settings
		add_shortcode( 'kb_quiz_confirmation', array( $this, 'shortcode_kb_quiz_confirmation' ) );
		
		// Register the Coaching Style Quiz Settings page
		add_action( 'admin_menu', array( $this, 'acf_register_options_page' ) );
		
		// Allows shortcodes in gravity form confirmations
		add_filter( 'gform_confirmation', array( $this, 'gf_allow_shortcodes_in_confirmation' ), 80, 4 );
		
		// Allow shortcodes in gravity form notifications
		add_filter( 'gform_pre_send_email', array( $this, 'gf_allow_shortcodes_in_notification' ), 20, 4 );
		
		// Attach PDF to email
		add_filter( 'gform_pre_send_email', array( $this, 'gf_attach_pdf_to_email' ), 20, 4 );
		
		// Clean up PDF after email is sent
		add_action( '', array( $this, 'gf_clean_up_pdf' ), 20, 2 );
		
		// When an entry is created, save a unique identifier to its metadata
		add_action( 'gform_entry_created', array( $this, 'gf_generate_entry_unique_key' ), 5, 2 );
		
		// Used in the PDF, but also can be used for testing:
		// Spiderweb chart with specific values
		// https://karenbenoy.com/?kb_radar&scores=0.5,0.25,0.75,1.0,0.25,0.5,0.75,0.5,1.0,0.75
		if ( isset($_GET['kb_radar']) ) {
			add_action( 'init', array( $this, 'kb_radar' ) );
		}
		
		// View past results by secret url
		// @see https://karenbenoy.com/quiz/?kb_entry=6410d5ee5a372
		if ( isset($_GET['kb_entry']) ) {
			add_action( 'gform_pre_render', array( $this, 'display_previous_entry' ), 50, 1 );
		}
		
		// For testing, allow viewing the results of a previous entry.
		// @see https://karenbenoy.com/quiz/?test_previous_entry_confirmation=135
		if ( isset($_GET['test_previous_entry_confirmation']) ) {
			add_action( 'gform_pre_render', array( $this, 'test_previous_entry_confirmation' ), 50, 1 );
		}
		
		// For testing the SVG
		// https://karenbenoy.com/?test_radar_svg
		if ( isset($_GET['test_radar_svg']) ) {
			add_action( 'init', array( $this, 'test_radar_svg' ) );
		}
		
	}
	
	public function enqueue_scripts() {
		$v = filemtime(get_stylesheet_directory() . '/_includes/functions/quiz/assets/quiz.css');
		wp_enqueue_style( 'coaching-style-quiz', get_stylesheet_directory_uri() . '/_includes/functions/quiz/assets/quiz.css', array(), $v );
	}
	
	/**
	 * Displays the radar chart SVG for an entry
	 * Uses the shortcode [kb_spiderweb_chart entry_id="123"]
	 *
	 * @param array $atts
	 * @param string $content
	 * @param string $shortcode_name
	 *
	 * @return string
	 */
	public function shortcode_kb_spiderweb_chart( $atts, string $content = '', $shortcode_name = 'kb_spiderweb_chart' ) {
		$atts = shortcode_atts(array(
			'entry_id' => null,
		), $atts, $shortcode_name);
		
		$entry_id = $atts['entry_id'];
		if ( !$entry_id ) return '(Spiderweb chart error: No entry_id specified in shortcode)';
		
		$scores = $this->calculate_scores( $entry_id );
		
		$svg = $this->generate_svg( $scores );
		
		// center the svg
		$svg = '<p class="kb-spiderweb-chart" style="text-align: center;">'. $svg .'</p>';
		
		return $svg;
	}
	
	/**
	 * Get the settings field prefix to use based on the quiz grade value
	 *
	 * @param array $entry
	 *
	 * @return false|string
	 */
	public function get_settings_prefix( $entry ) {
		$quiz_grade = rgar( $entry, 'gquiz_grade' );
		
		switch( $quiz_grade ) {
			case 'Consistent Coach':
				return 'consistent_coach';
			case 'Intuitive Coach':
				return 'intuitive_coach';
			case 'Sage Coach':
				return 'sage_coach';
			case 'Confident Coach':
				return 'confident_coach';
			default:
				return false;
		}
	}
	
	/**
	 * Displays quiz result content from Theme Settings > Coaching Style Quiz Settings
	 * Uses the shortcode [kb_quiz_confirmation entry_id="123" quiz_grade="Intuitive Coach"]
	 *
	 * @param array $atts
	 * @param string $content
	 * @param string $shortcode_name
	 *
	 * @return string
	 */
	public function shortcode_kb_quiz_confirmation( $atts, string $content = '', $shortcode_name = 'kb_quiz_confirmation' ) {
		$atts = shortcode_atts(array(
			'entry_id' => null,
			'quiz_grade' => null,
			'format' => null,
		), $atts, $shortcode_name);
		
		$entry_id = $atts['entry_id'];
		if ( !$entry_id ) return '(Coaching style quiz error: No entry_id specified in '. $shortcode_name .')';
		
		$entry = GFAPI::get_entry( $entry_id );
		$form = GFAPI::get_form( $entry['form_id'] );
		if ( $form['id'] != $this->form_id ) return '(Coaching style quiz error: Mismatched form id in '. $shortcode_name .')';
		
		$format = $atts['format'];
		
		require_once( __DIR__ . '/templates/results.php' );
		
		$html = kb_generate_quiz_results_html( $entry, $form, $format );
		
		return $html;
	}
	
	/**
	 * Get the weighted value for a quiz field, instead of the randomized key that is stored in the entry.
	 *
	 * @param array $entry
	 * @param int $field_id
	 *
	 * @return int
	 */
	public function get_quiz_value( $entry, $field_id ) {
		$form_id = $entry['form_id'];
		
		// Get the field object to look up the quiz weighted values
		$field = RGFormsModel::get_field( $form_id, $field_id );
		if ( $field['type'] != 'quiz' ) return 0;
		
		// Get the "value" which is the quiz answer key. It is a random string.
		$selected_key = rgar( $entry, $field_id ); // "gquiz12345678"
		
		// Loop through choices looking for the selected answer. Return the quiz weight value.
		foreach( $field['choices'] as $c ) {
			$choice_key = $c['value']; // "gquiz12345678"
			$weighted_value = (int) $c['gquizWeight']; // 1, 3, 5, 7
			if ( $choice_key == $selected_key ) return $weighted_value;
		}
		
		return 0;
	}
	
	/**
	 * Calculates scores for the 11 questions
	 * mapped values:
	 *  a = 1 = 0.25
	 *  b = 3 = 0.50
	 *  c = 5 = 0.75
	 *  d = 7 = 1.00
	 *
	 * explained:
	 *  a-d = answer selected
	 *  1-7 = weighted value
	 *  0.25-1.00 = percentage to use on radar chart
	 *
	 * @param int $entry_id
	 *
	 * @return array
	 */
	public function calculate_scores( $entry_id ) {
		$entry = GFAPI::get_entry( $entry_id );
		
		$scores = array();
		
		foreach( $this->field_ids as $field_id ) {
			$value = $this->get_quiz_value( $entry, $field_id );
			
			if ( $value <= 1 )      $percentage = 0.25;
			else if ( $value <= 3 ) $percentage = 0.50;
			else if ( $value <= 5 ) $percentage = 0.75;
			else                    $percentage = 1.00;
			
			$scores[] = $percentage;
		}
		
		return $scores;
	}
	
	/**
	 * Allows shortcodes in gravity form confirmations
	 *
	 * @param string|array $confirmation
	 * @param array $form
	 * @param array $entry
	 * @param bool $ajax
	 *
	 * @return string|array
	 */
	public function gf_allow_shortcodes_in_confirmation( $confirmation, $form, $entry, $ajax ) {
		if ( $form['id'] != $this->form_id ) return $confirmation;
		
		return $this->expand_shortcode_and_merge_tags( $confirmation, $form, $entry );
	}
	
	/**
	 * Allow shortcodes in gravity form notifications
	 *
	 * @param array $email
	 * @param string $message_format
	 * @param array $notification
	 * @param array $entry
	 *
	 * @return array
	 */
	public function gf_allow_shortcodes_in_notification( $email, $message_format, $notification, $entry ) {
		if ( $entry['form_id'] != $this->form_id ) return $email;
		
		$email['subject'] = do_shortcode( $email['subject'] );
		
		return $email;
	}
	
	/**
	 * Downloads a file to the uploads folder /quizzes/ directory with a given filename
	 *
	 * @param $url
	 * @param $filename
	 *
	 * @return string
	 */
	public function download_file_as_path( $url, $filename, $form_id ) {
		$contents = file_get_contents( $url );
		
		$uploads = wp_upload_dir();
		$upload_path = untrailingslashit( $uploads['basedir'] ) . '/quiz-tmp/';
		if ( !file_exists( $upload_path ) ) mkdir( $upload_path );
		
		$path = $upload_path . $filename;
		
		file_put_contents( $path, $contents );
		
		return $path;
	}
	
	/**
	 * Attach PDF to email
	 *
	 * @param array $email
	 * @param string $message_format
	 * @param array $notification
	 * @param array $entry
	 *
	 * @return array
	 */
	public function gf_attach_pdf_to_email( $email, $message_format, $notification, $entry ) {
		if ( $entry['form_id'] != $this->form_id ) return $notification;
		
		if ( ! is_array($email['attachments']) ) $email['attachments'] = array();
		
		global $KB_Quiz_PDF;
		
		// Get the filename
		$filename = $KB_Quiz_PDF->get_pdf_filename( $entry );
		
		// Get the PDF by URL
		$pdf_url = $this->get_pdf_url( $entry );
		
		// Download the PDF to a file in uploads/quiz-tmp/
		$path = $this->download_file_as_path( $pdf_url, $filename, $entry['form_id'] );
		
		// Remember the filename to clean up afterwards
		$this->temp_pdf_path = $path;
		
		// Attaches the temporary file by full path
		$email['attachments'][] = $path;
		
		GFCommon::log_debug( __METHOD__ . '(): tmp file of quiz pdf added to attachments list: ' . $path);
		
		return $email;
	}
	
	/**
	 * Deletes temporary pdf after entry notifications are sent
	 *
	 * @param $entry
	 * @param $form
	 *
	 * @return void
	 */
	public function gf_clean_up_pdf( $entry, $form ) {
		if ( $this->temp_pdf_path ) {
			unlink( $this->temp_pdf_path);
		}
	}
	
	/**
	 * Expands GF merge tags like {entry_id} and then processes shortcodes for the string
	 *
	 * @param $html
	 * @param $form
	 * @param $entry
	 *
	 * @return string
	 */
	public function expand_shortcode_and_merge_tags( $html, $form, $entry ) {
		
		$html = GFCommon::replace_variables( $html, $form, $entry, false, false, false, 'text' );
		
		$html = do_shortcode( $html );
		
		return $html;
	}
	
	/**
	 * @param $entry
	 * @param $form
	 *
	 * @return void
	 */
	public function gf_generate_entry_unique_key( $entry, $form ) {
		if ( $form['id'] != $this->form_id ) return;
		
		// Just touch the key to generate it for the first time.
		$this->get_unique_key( $entry );
	}
	
	/**
	 * Register settings page where you can customize the pdf and results screen content
	 *
	 * @return void
	 */
	public function acf_register_options_page() {
		// Theme Settings -> Coaching Style Quiz
		acf_add_options_sub_page(array(
			'page_title' 	=> 'Coaching Style Quiz Settings (kb_quiz)',
			'menu_title' 	=> 'Coaching Style Quiz',
			'parent_slug' 	=> 'theme-general-settings', // 'admin.php?page=theme-general-settings',
			'post_id'       => 'kb_quiz',
			'slug'          => 'acf-kb-quiz-settings',
			'autoload'      => false,
			'capability'    => 'manage_options',
		));
	}
	
	/**
	 * Get the unique key for an entry. Generates it if needed.
	 *
	 * @param $entry
	 *
	 * @return false|string
	 */
	public function get_unique_key( $entry ) {
		if ( $entry['form_id'] != $this->form_id ) return false;
		
		$key = gform_get_meta( $entry['id'], $this->key_id );
		
		if ( empty( $key ) ) {
			$key = uniqid();
			gform_update_meta( $entry['id'], $this->key_id, $key );
		}
		
		return $key;
	}
	
	/**
	 * Gets an entry by its unique key which is generated when entry is created
	 *
	 * @param $key
	 *
	 * @return array|false
	 */
	public function get_entry_from_key( $key ) {
		$search = array();
		
		$search['field_filters'] = array(
			array(
				'key' => $this->key_id,
				'value' => $key
			)
		);
		
		$entries = GFAPI::get_entries( $this->form_id, $search );
		
		return $entries ? $entries[0] : false;
	}
	
	/**
	 * Get URL to the PDF
	 * https://karenbenoy.com/quiz/?kb_pdf=6410d8c42cab1
	 *
	 * @param $entry
	 *
	 * @return string
	 */
	public function get_pdf_url( $entry ) {
		$code = $this->get_unique_key( $entry );
		
		$url = site_url( '/quiz/' );
		
		$url = add_query_arg(array( 'kb_pdf' => $code ), $url);
		
		return $url;
	}
	
	/**
	 * Get a value from a field in an entry by custom named key
	 *
	 * @param $key
	 *
	 * @return mixed
	 */
	public function get_entry_value( $entry, $key ) {
		$field_id = null;
		
		switch( $key ) {
			case 'first_name': $field_id = $this->first_name_id; break;
			case 'last_name': $field_id = $this->last_name_id; break;
			case 'email': $field_id = $this->email_id; break;
		}
		
		return rgar( $entry, $field_id );
	}
	
	/**
	 * Generates the radar chart from a template file. A polygon is moved around based on the given scores.
	 *
	 * @param array $scores
	 *
	 * @return string
	 */
	public function generate_svg( $scores ) {
		// /northstar-child/_static/images/radar-chart-clean.svg
		$theme_path = get_stylesheet_directory();
		// $svg_content = file_get_contents( $theme_path . '/_static/images/radar-simple.svg' ); // pdf safe version
		$svg_content = file_get_contents( $theme_path . '/_static/images/radar-simple-text.svg' ); // pdf safe version
		
		// $size = array( 792, 612 );
		
		// x1, y1
		$center = array( 393, 299 );
		
		// we'll edit this polygon within id="selection"
		// the coordinates on the left must match exactly
		$coords = array(
			//     x2   y2
			array( 394, 37  ),  // sustainable transformation
			array( 549, 87  ),  // confidence
			array( 643, 218 ),  // risk taking
			array( 643, 381 ),  // proficiency with tools
			array( 549, 512 ),  // efficiency / agility
			array( 394, 562 ),  // connecting to values
			array( 238, 512 ),  // connection the dots
			array( 144, 381 ),  // what is not being said
			array( 144, 218 ),  // staying present
			array( 238, 87  )   // highest potentiality
		);
		
		// here are important coordinates:
		/*
area:
w 792
h 612

center:
x 393
y 299

points starting from top going clockwise:
    x, y
1.  394, 37		sustainable
2.  549, 87		confidence
3.  643, 218	risk
4.  643, 381	proficiency
5.  549, 512	efficiency
6.	394, 562	connecting values
7.  238, 512	connection dots
8.  144, 381	what
9.  144, 218	staying
10. 238, 87     highest
		 */
		
		// here is the math explained by ai
		/*
		To find a point that is a certain percentage along the line between
		two points A and B, you can use the following formula:
		
		New point = A + percentage * (B - A)
		
		where A and B are the coordinates of the two points, and percentage
		is the percentage of the distance between them where you want to find the new point.
		 */
		
		// here is our formula
		// x3 = x1 + ( 0.25 * ( x2 - x1 ) )
		// y3 = y1 + ( 0.25 * ( y2 - y1 ) )
		
		// center of radar
		$x1 = $center[0];
		$y1 = $center[1];
		
		$replacements = array();
		
		// loop through each coordinate to get the search/replace values
		foreach( $coords as $i => &$c ) {
			$percentage = $scores[ $i ];
			
			// x1/y1 = Center of the circle
			// x2/y2 = Position at 100%
			// x3/y3 = Target coordinates based on the percentage
			
			// point at 100% score
			$x2 = $c[0];
			$y2 = $c[1];
			
			// point at variable % score between 0% and 100%
			$x3 = $x1 + ( $percentage * ( $x2 - $x1 ) );
			$y3 = $y1 + ( $percentage * ( $y2 - $y1 ) );
			
			$replacements[ "$x2, $y2" ] = "$x3, $y3";
			$replacements[ "cx=\"$x2\" cy=\"$y2\"" ] = "cx=\"$x3\" cy=\"$y3\"";
		}
		
		// replace old coordinates with newly calculated ones
		$svg_content = str_replace( array_keys( $replacements ), array_values( $replacements ), $svg_content );
		
		// remove empty space and linebreaks to prevent wpautop issues
		$svg_content = trim(preg_replace('/\s+/', ' ', $svg_content));
		
		// Replace scores
		$score_numbers = array(
			'[score_transformation]' => $this->get_percent( $scores[0] ),
			'[score_confidence]'     => $this->get_percent( $scores[1] ),
			'[score_risk]'           => $this->get_percent( $scores[2] ),
			'[score_tools]'          => $this->get_percent( $scores[3] ),
			'[score_efficiency]'     => $this->get_percent( $scores[4] ),
			'[score_values]'         => $this->get_percent( $scores[5] ),
			'[score_dots]'           => $this->get_percent( $scores[6] ),
			'[score_not_said]'       => $this->get_percent( $scores[7] ),
			'[score_present]'        => $this->get_percent( $scores[8] ),
			'[score_potential]'      => $this->get_percent( $scores[9] ),
		);

		$svg_content = str_replace( array_keys($score_numbers), array_values($score_numbers), $svg_content );
		
		// Customize colors by URL
		// <polygon
		//    points="394, 37  549, 87  643, 218  643, 381  549, 512  394, 562  238, 512  144, 381  144, 218  238, 87"
		//    style="opacity: 1;fill: #35a3f1;fill-opacity: .5;stroke: #0661a0;stroke-width: 5;stroke-linejoin: round;" />
		
		// returns a string <svg>...</svg>
		return $svg_content;
	}
	
	public function get_percent( $float ) {
		return round($float * 100) . '%';
	}
	
	// View past results by secret url
	// @see https://karenbenoy.com/quiz/?kb_entry=6410d5ee5a372
	public function display_previous_entry( $form ) {
		if ( $form['id'] != $this->form_id ) return $form;
		
		$key = stripslashes($_GET['kb_entry']);
		if ( empty($key) ) return $form;
		
		$entry = $this->get_entry_from_key( $key );
		if ( ! $entry ) return $form;
		
		return $this->maybe_replace_form_with_confirmation( $form, $entry );
	}
	
	// For testing, allow viewing the results of a previous entry.
	// @see https://karenbenoy.com/quiz/?test_previous_entry_confirmation=135
	public function test_previous_entry_confirmation( $form ) {
		if ( $form['id'] != $this->form_id ) return $form;
		
		$entry_id = $_GET['test_previous_entry_confirmation'] ?? false;
		$entry = $entry_id ? GFAPI::get_entry($entry_id) : false;
		if ( ! $entry ) return $form;
		
		// admin only
		if ( ! current_user_can( 'administrator' ) ) {
			wp_die( __FUNCTION__ . ' is only available to admins.' );
		}
		
		return $this->maybe_replace_form_with_confirmation( $form, $entry );
	}
	
	// Replaces the form with confirmation using a hack that replaces the "form cannot be loaded" error message
	public function maybe_replace_form_with_confirmation( $form, $entry ) {
		// @see handle_submission()
		$confirmation = GFFormDisplay::handle_confirmation( $form, $entry );
		
		if ( $confirmation ) {
			// Hack to replace the form with a confirmation.
			// Save the confirmation temporarily, and return false, disabling the form.
			// We capture the error handler to display a confirmation instead.
			$this->confirmation = $confirmation;
			add_filter( 'gform_form_not_found_message', array($this, '_replace_with_confirmation'), 5 );
			return false;
		}
		
		return $form;
	}
	
	/**
	 * Replaces the "form not found" error message with our confirmation.
	 * This is a hack because gravity forms doesn't have an easy way to do this, or at least I could not find one.
	 *
	 * @param $form
	 *
	 * @return mixed|null
	 */
	public function _replace_with_confirmation( $form ) {
		remove_filter( 'gform_form_not_found_message', array($this, '_replace_with_confirmation'), 5 );
		return $this->confirmation;
	}
	
	// For testing the SVG
	// https://karenbenoy.com/?test_radar_svg
	public function test_radar_svg() {
		// fixed scores
		$scores = array( 0.5, 1.0, 0.75, 0.33, 0.5, 0.66, 0.8, 0.75, 1.0, 0.33 );
		
		// better yet, lets randomize it
		foreach( $scores as $i => $v ) {
			$scores[$i] = rand(0, 100) / 100; // 0.00 -> 1.00
		}
		
		$this->stream_svg( $scores );
		exit;
	}
	
	// Used in the PDF, but also can be used for testing
	// https://karenbenoy.com/?kb_radar&scores=0.5,0.25,0.75,1.0,0.25,0.5,0.75,0.5,1.0,0.75
	public function kb_radar() {
		$raw_scores = $_GET['scores'] ?? '';
		$scores = explode( ',', stripslashes($raw_scores) );
		
		$this->stream_svg( $scores );
		exit;
	}
	
	/**
	 * Gets an image url to use as the src of an <img> tag, using scores from the given entry
	 *
	 * @param $entry_id
	 *
	 * @return string
	 */
	public function get_chart_image_src( $entry_id ) {
		$scores = $this->calculate_scores( $entry_id );
		
		$path = '/?kb_radar&scores=' . implode(',', $scores);
		
		return site_url( $path );
	}
	
	/**
	 * Generate the SVG and send it directly to the browser. This replaces the entire page.
	 *
	 * @param $scores
	 *
	 * @return void
	 */
	public function stream_svg( $scores ) {
		header('Content-type: image/svg+xml');
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');
		
		echo $this->generate_svg( $scores );
		exit;
	}
	
}

global $KB_Quiz;
$KB_Quiz = new KB_Quiz();