jQuery(document).ready(function($){


$("#_badgeos_earned_by").change( function() {
	// Define our potentially unnecessary inputs
	var badgeos_course = $("#wplms_course").parent().parent();
	var badgeos_quiz = $("#wplms_quiz").parent().parent();
	var badgeos_unit = $("#wplms_unit").parent().parent();
	var badgeos_assignment = $("#wplms_assignment").parent().parent();
	// // Hide our potentially unnecessary inputs
	badgeos_course.hide();
	badgeos_quiz.hide();
	badgeos_unit.hide();
	badgeos_assignment.hide();
	// Determine which inputs we should show
	if ( "course" == $(this).val() )
		badgeos_course.show();
	else if( "quiz" == $(this).val() )
		badgeos_quiz.show();
	else if( "unit" == $(this).val() )
		badgeos_unit.show();
	else if( "assignment" == $(this).val() )
		badgeos_assignment.show();

}).change();

$('.select-course-activity').hide();
$('.input-activity-id').hide();
$('.input-activity-info').hide();


$( '.select-trigger-type' ).on( 'change', function(event) {

	var trigger_type = $(this);

	// Show our group selector if we're awarding based on a specific group
	if ( 'course_activity' == trigger_type.val() ) {
		trigger_type.siblings('.select-course-activity').show().change();
		trigger_type.siblings('.input-activity-id').show();
	} else {
		trigger_type.siblings('.select-course-activity').hide().change();
		trigger_type.siblings('.input-activity-id').hide();
	}

});
// Listen for our change to our trigger type selector
$( '.select-course-activity' ).on( 'change', function() {

	var trigger_type = $(this);

	// Show our group selector if we're awarding based on a specific group
	if ( 'wplms_evaluate_course' == trigger_type.val() || 'wplms_evaluate_quiz' == trigger_type.val() || 'wplms_evaluate_assignment' == trigger_type.val() ) {
		trigger_type.siblings('.input-activity-info').show();
	} else {
		trigger_type.siblings('.input-activity-info').hide();
	}

});
// Trigger a change so we properly show/hide our community menues
$('.select-trigger-type').change();

$(document).on( 'update_step_data', function( event, step_details, step ) {
	step_details.course_activity = $('.select-course-activity', step).val();
	step_details.course_activity_label = $( '.select-course-activity option', step ).filter( ':selected' ).text();
	step_details.activity_id = $('.input-activity-id', step).val();
	step_details.activity_info = $('.input-activity-info', step).val();
});
});