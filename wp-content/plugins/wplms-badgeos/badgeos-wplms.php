<?php
/**
 * Plugin Name: WPLMS BadgeOS Add-On
 * Plugin URI: http://www.vibethemes.com/
 * Description: Integrates BadgeOS with WPLMS
 * Author: VibeThemes
 * Version: 1.3.1
 * Author URI: https://vibethemes.com/
 * Text Domain: badgeos-wplms
 * License: GNU AGPLv3
 * License URI: http://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Our main plugin instantiation class
 *
 * This contains important things that our relevant to
 * our add-on running correctly. Things like registering
 * custom post types, taxonomies, posts-to-posts
 * relationships, and the like.
 *
 * @since 1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WPLMS_BadgeOS_Addon {

	/**
	 * Plugin Basename
	 *
	 * @var string
	 */
	public $basename = '';

	/**
	 * Plugin Directory Path
	 *
	 * @var string
	 */
	public $directory_path = '';

	/**
	 * Plugin Directory URL
	 *
	 * @var string
	 */
	public $directory_url = '';

	/**
	 * BadgeOS WPLMS Triggers
	 *
	 * @var array
	 */
	public $triggers;
	public $evaluate_triggers = array('wplms_evaluate_course','wplms_evaluate_quiz','wplms_evaluate_assignment');
	/**
	 * Actions to forward for splitting an action up
	 *
	 * @var array
	 */
	public $actions = array();
	/**
	 * Get everything running.
	 *
	 * @since 1.0.0
	 */
	function __construct() {

		// Define plugin constants
		$this->basename       = plugin_basename( __FILE__ );
		$this->directory_path = plugin_dir_path( __FILE__ );
		$this->directory_url  = plugins_url( dirname( $this->basename ) );
		$this->triggers = array(
			'course' => array(
				'wplms_start_course' => __('Start Course','badgeos-wplms'),
				'wplms_submit_course' => __('Finish Course','badgeos-wplms'),
				'wplms_evaluate_course' => __('Marks in Course greater than (+30) Or Less than (-30) OR Range (30-70)','badgeos-wplms'),
				),
			'quiz' => array(
				'wplms_start_quiz' => __('Start Quiz','badgeos-wplms'),
				'wplms_submit_quiz' => __('Finish Quiz','badgeos-wplms'),
				'wplms_evaluate_quiz' => __('Marks in Quiz greater than (+30) Or Less than (-30) OR Range (30-70)','badgeos-wplms'),
				),
			'assignment' => array(
				'wplms_start_assignment' => __('Start Assignment','badgeos-wplms'),
				'wplms_submit_assignment' => __('Finish Assignment','badgeos-wplms'),
				'wplms_evaluate_assignment' => __('Marks in Assignment greater than (+30) Or Less than (-30) OR Range (30-70)','badgeos-wplms'),
				),
			'unit' => array(
				'wplms_unit_complete' => __('Complete a Unit','badgeos-wplms'),
				),
			);
		// Load translations
		load_plugin_textdomain( 'badgeos-addon', false, dirname( $this->basename ) . '/languages' );

		// Run our activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// If BadgeOS is unavailable, deactivate our plugin
		add_action( 'admin_notices', array( $this, 'maybe_disable_plugin' ) );

		// Include our other plugin files
		add_action( 'init', array( $this, 'includes' ) );
		add_action( 'init', array($this,'badgeos_wplms_load_triggers' ));
		add_action('admin_enqueue_scripts',array($this,'badgeos_wplms_register_script'));
		add_filter('badgeos_activity_triggers',array($this,'badgeos_wplms_activity_triggers'));
		add_action( 'badgeos_steps_ui_html_after_trigger_type',array($this, 'badgeos_bp_step_course_trigger_select'), 20, 2 );
		add_action( 'badgeos_steps_ui_html_after_trigger_type',array($this, 'badgeos_bp_step_course_trigger_input'), 30, 2 );
		add_filter( 'badgeos_save_step', array($this,'badgeos_wplms_save_step'), 10, 3 );
		//add_filter('badgeos_user_deserves_trigger',array($this,'badgeos_wplms_user_deserves_trigger'),20,3);
		add_filter( 'badgeos_get_step_requirements', array($this,'badgeos_wplms_step_requirements'), 10, 2 );
		
		/*== RULE ENGINE Custom Achievement Earning Rules ====*/
		add_filter( 'user_deserves_achievement', array($this,'badgeos_wplms_user_deserves_wplms_step'), 15, 6 );
	} 


	/**
	 * Include our plugin dependencies
	 *
	 * @since 1.0.0
	 */
	public function includes() {
		// If BadgeOS is available...
		if ( $this->meets_requirements() ) {

			// Include some files

		}

	} /* includes() */

	/**
	 * Activation hook for the plugin.
	 *
	 * @since 1.0.0
	 */
	public function activate() {

		// If BadgeOS is available, run our activation functions
		if ( $this->meets_requirements() ) {

			// Do some activation things

		}

	} /* activate() */

	/**
	 * Deactivation hook for the plugin.
	 *
	 * Note: this plugin may auto-deactivate due
	 * to $this->maybe_disable_plugin()
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {

		// Do some deactivation things.

	} /* deactivate() */

	/**
	 * Check if BadgeOS is available
	 *
	 * @since  1.0.0
	 * @return bool True if BadgeOS is available, false otherwise
	 */
	public static function meets_requirements() {

		if ( class_exists('BadgeOS') )
			return true;
		else
			return false;

	} /* meets_requirements() */

	/**
	 * Potentially output a custom error message and deactivate
	 * this plugin, if we don't meet requriements.
	 *
	 * This fires on admin_notices.
	 *
	 * @since 1.0.0
	 */
	public function maybe_disable_plugin() {

		if ( ! $this->meets_requirements() ) {
			// Display our error
			echo '<div id="message" class="error">';
			echo '<p>' . sprintf( __( 'BadgeOS Add-On requires BadgeOS and has been <a href="%s">deactivated</a>. Please install and activate BadgeOS and then reactivate this plugin.', 'badgeos-wplms' ), admin_url( 'plugins.php' ) ) . '</p>';
			echo '</div>';

			// Deactivate our plugin
			deactivate_plugins( $this->basename );
		}

	} /* maybe_disable_plugin() */


	function badgeos_wplms_activity_triggers($triggers){
		$triggers['course_activity'] = __( 'Course Activity', 'badgeos-wplms' );
		
		return $triggers;
	}
	/**
	 * Add a Community Triggers selector to the Steps UI
	 *
	 * @since 1.0.0
	 * @param integer $step_id The given step's post ID
	 * @param integer $post_id The given parent post's post ID
	 */
	function badgeos_bp_step_course_trigger_select( $step_id, $post_id ) {

		// Setup our select input
		echo '<select name="course_activity" class="select-course-activity">';
		echo '<option value="">' . __( 'Select a Course Activity', 'badgeos-wplms' ) . '</option>';

		// Loop through all of our community trigger groups
		$current_selection = get_post_meta( $step_id, '_badgeos_course_activity', true );
		
		if ( !empty( $this->triggers ) ) {
			foreach ( $this->triggers as $optgroup_name => $triggers ) {
				echo '<optgroup label="' . strtoupper($optgroup_name). '">';
				// Loop through each trigger in the group
				foreach ( $triggers as $trigger_hook => $trigger_name )
					echo '<option' . selected( $current_selection, $trigger_hook, false ) . ' value="' . $trigger_hook . '">' . $trigger_name . '</option>';
				echo '</optgroup>';
			}
		}

		echo '</select>';

	}

	function badgeos_wplms_step_requirements( $requirements, $step_id ) {

	// Add our new requirements to the list
	$requirements[ 'course_activity' ] = get_post_meta( $step_id, '_badgeos_course_activity', true );
	$requirements[ 'activity_id' ] = (int) get_post_meta( $step_id, '_badgeos_course_activity_id', true );
	$requirements[ 'activity_info' ] = get_post_meta( $step_id, '_badgeos_course_activity_info', true );

	// Return the requirements array
	return $requirements;

	}


	function badgeos_bp_step_course_trigger_input( $step_id, $post_id ) {
		// Loop through all of our community trigger groups
		$current_box1 = get_post_meta( $step_id, '_badgeos_course_activity_id', true );
		$current_box2 = get_post_meta( $step_id, '_badgeos_course_activity_info', true );
		echo '<input type="text" name="activity_id" class="input-activity-id" placeholder="'.__('Enter ID (Blank for any)','badgeos-wplms').'"  value="'.$current_box1.'">';
		echo '<input type="text" name="activity_info" class="input-activity-info" placeholder="'.__('Enter Marks','badgeos-wplms').'" value="'.$current_box2.'">';
	}
	function badgeos_wplms_save_step($title, $step_id, $step_data){
		// If we're working on a WPLMS trigger
		if ( 'course_activity' == $step_data[ 'trigger_type' ] ) {

			update_post_meta( $step_id, '_badgeos_course_activity', $step_data[ 'course_activity' ] );

			// Rewrite the step title
			$title = $step_data[ 'course_activity_label' ];
			$activity_id = $step_data[ 'activity_id' ];
			$activity_info = $step_data[ 'activity_info' ];
			switch($step_data[ 'course_activity' ]){
				case 'start_course':
					if ( empty( $activity_id ) ) {
						$title = __( 'Start any Course', 'badgeos-wplms' );
					}else {
						$title = sprintf( __( 'Started Course "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ) );
					}
				break;
				case 'submit_course':
					if ( empty( $activity_id ) ) {
						$title = __( 'Complete any Course', 'badgeos-wplms' );
					}else {
						$title = sprintf( __( 'Completed Course "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ) );
					}
				break;
				case 'evaluate_course':
					if ( empty( $activity_id ) ) {
						$title = __( 'Marks in any Course greater than', 'badgeos-wplms' );
					}else {
						if(is_numeric($activity_info)){
							if($activity_info >= 0){
								$title = sprintf( __( 'Marks in Course "%s" greater than "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ), $activity_info );
							}else{
								$activity_info = -1*$activity_info;
								$title = sprintf( __( 'Marks in Course "%s" less than "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ), $activity_info );
							}
						}else{
							if(strpos($activity_info,'-') !== false){
								$range = explode('-',$activity_info);
								if(isset($args[1]) && $args[1] < $range[1] && $args[1] >= $range[0] ){
									$title = sprintf( __( 'Marks in Course "%s" in range  "%s" and "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ), $range[0],$range[1] );
								}
							}
						}
						
					}
				break;
				case 'start_quiz':
					if ( empty( $activity_id ) ) {
						$title = __( 'Start any Quiz', 'badgeos-wplms' );
					}else {
						$title = sprintf( __( 'Started Quiz "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ) );
					}
				break;
				case 'submit_quiz':
					if ( empty( $activity_id ) ) {
						$title = __( 'Complete any Quiz', 'badgeos-wplms' );
					}else {
						$title = sprintf( __( 'Completed Quiz "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ) );
					}
				break;
				case 'evaluate_quiz':
					if ( empty( $activity_id ) ) {
						$title = __( 'Marks in any Quiz greater than', 'badgeos-wplms' );
					}else {
						$title = sprintf( __( 'Marks in Quiz "%s" greater than "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ), $activity_info );
					}
				break;
				case 'start_assignment':
					if ( empty( $activity_id ) ) {
						$title = __( 'Start any Assignment', 'badgeos-wplms' );
					}else {
						$title = sprintf( __( 'Started Assignment "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ) );
					}
				break;
				case 'submit_assignment':
					if ( empty( $activity_id ) ) {
						$title = __( 'Complete any Assignment', 'badgeos-wplms' );
					}else {
						$title = sprintf( __( 'Completed Assignment "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ) );
					}
				break;
				case 'evaluate_assignment':
					if ( empty( $activity_id ) ) {
						$title = __( 'Marks in any Assignment greater than', 'badgeos-wplms' );
					}else {
						$title = sprintf( __( 'Marks in Assignment "%s" greater than "%s"', 'badgeos-wplms' ), get_the_title( $activity_id ), $activity_info );
					}
				break;
			} 
			update_post_meta( $step_id, '_badgeos_course_activity_id', $activity_id );
			update_post_meta( $step_id, '_badgeos_course_activity_info', $activity_info );
		}
		return $title;
	}

	/* ===== RULE  ENGINE : Custom Functions to Check User Achievements ==== */
	function badgeos_wplms_load_triggers() {
		// Grab our WPLMS triggers
		$triggers = $this->triggers;

		if ( !empty( $triggers ) ) {
			foreach ( $triggers as $trigger => $trigger_label ) {
				if ( is_array( $trigger_label ) ) {
					$triggers = $trigger_label;
					foreach ( $triggers as $trigger_hook => $trigger_name ) {
						add_action( $trigger_hook, array($this,'badgeos_wplms_trigger_event'), 10, 20 );
					}
				}else {
					add_action( $trigger, array($this,'badgeos_wplms_trigger_event'), 10, 20 );
				}
			}
		}

	}


	/**
	 * Handle each of our LearnDash triggers
	 *
	 * @since 1.0.0
	 */
	function badgeos_wplms_trigger_event() {
		
		// Setup all our important variables
		global $blog_id, $wpdb;
		
		$args = func_get_args();

		$userID = get_current_user_id();

		if ( is_array( $args ) && isset( $args[ 'user' ] ) ) {
			if ( is_object( $args[ 'user' ] ) ) {
				$userID = (int) $args[ 'user' ]->ID;
			}
			else {
				$userID = (int) $args[ 'user' ];
			}
		}

		if ( empty( $userID ) ) {
			return;
		}

		// Grab the current trigger 
		$this_trigger = current_filter();

		if(in_array($this_trigger,array('wplms_evaluate_course','wplms_evaluate_quiz','wplms_evaluate_assignment'))){
			if(isset($args[2]) && is_numeric($args[2]))
			$userID = $args[2];
		}


		$user_data = get_user_by( 'id', $userID );

		if ( empty( $user_data ) ) {
			return;
		}

		

		// Update hook count for this user
		$new_count = badgeos_update_user_trigger_count( $userID, $this_trigger, $blog_id );

		// Mark the count in the log entry
		badgeos_post_log_entry( null, $userID, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', 'badgeos-wplms' ), $user_data->user_login, $this_trigger, $new_count ) );

		// Now determine if any badges are earned based on this trigger event
		$triggered_achievements = $wpdb->get_results( $wpdb->prepare( "
			SELECT post_id
			FROM   $wpdb->postmeta
			WHERE  meta_key = '_badgeos_course_activity'
					AND meta_value = %s
			", $this_trigger ) );

		foreach ( $triggered_achievements as $achievement ) {
			badgeos_maybe_award_achievement_to_user( $achievement->post_id, $userID, $this_trigger, $blog_id, $args );
		}
	}
	function badgeos_wplms_user_deserves_wplms_step( $return, $user_id, $achievement_id, $this_trigger = '', $site_id = 1, $args = array() ) {

		
		// If we're not dealing with a step, bail here
		if ( 'step' != get_post_type( $achievement_id ) ) {
			return $return;
		}

		$requirements = badgeos_get_step_requirements( $achievement_id );


		if ( 'course_activity' == $requirements[ 'trigger_type' ] ) {
			// Do not pass go until we say you can
			$return = false;

			// Set Default Trigger as False
			$course_activity_trigger = false;

			// Set our main vars
			$course_activity = $requirements[ 'course_activity' ];
			$activity_id = $requirements[ 'activity_id' ];
			$activity_info = $requirements[ 'activity_info' ];
			// Extra arg handling for further expansion

			switch($this_trigger){
				case 'wplms_start_course':
				case 'wplms_submit_course':
				case 'wplms_start_quiz':
				case 'wplms_submit_quiz':
				case 'wplms_start_assignment':
				case 'wplms_submit_assignment':
				case 'wplms_unit_complete':

					if(isset($args[0])){
						if(isset($activity_id) && is_numeric($activity_id) && $activity_id == $args[0]){
							$course_activity_trigger = true;
						}else{
							if(!is_numeric($activity_id) || empty($activity_id)){ 
								$course_activity_trigger = true;
							}
						}
					}
					
				break;
				case 'wplms_evaluate_course':
				case 'wplms_evaluate_quiz':
				case 'wplms_evaluate_assignment':
				        
					if(isset($activity_id) && is_numeric($activity_id) && $activity_id == $args[0]){
						if(is_numeric($activity_info)){
							if($activity_info >= 0 ){
								if(isset($args[1]) && $args[1] >= $activity_info){
									$course_activity_trigger = true;
								}
							}else{
								$activity_info = -1*$activity_info;
								if(isset($args[1]) && $args[1] < $activity_info){
									$course_activity_trigger = true;
								}
							}
						}else{
							if(strpos($activity_info,'-') !== false){
								$range = explode('-',$activity_info);
								if(isset($args[1]) && $args[1] < $range[1] && $args[1] >= $range[0] ){
									$course_activity_trigger = true;
								}
							}
						}
					}
				 
				break;
				default: 
					$course_activity_trigger = false;
				break;
			}

			if ( $course_activity_trigger ) {
				// Grab the trigger count
				$trigger_count = badgeos_get_user_trigger_count( $user_id, $this_trigger, $site_id );
				// Exceed the required number of checkins, they deserve the step
				if ( 1 == $requirements[ 'count' ] || $requirements[ 'count' ] <= $trigger_count ) {
					$return = true;
					if(function_exists('badgeos_post_log_entry'))
					badgeos_post_log_entry( null, $user_id, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', 'badgeos' ), bp_core_get_username($user_id), $this_trigger, $trigger_count ) );
				}
			}
		} 
		return $return;
	}

	/* ===== End Custom Achievement Functions ==== */
	function badgeos_wplms_register_script(){
		wp_enqueue_script('wplms_badgeos-js',$this->directory_url.'/js/wplms_badgeos.js',array('badgeos-admin-js'));
	}
} /* BadgeOS_Addon */

// Instantiate our class to a global variable that we can access elsewhere
$GLOBALS['badgeos_addon'] = new WPLMS_BadgeOS_Addon();
