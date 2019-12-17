<?php
/**
 * Custom Achievement Rules
 *
 * @package BadgeOS Community
 * @subpackage Achievements
 * @author LearningTimes, LLC 
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Load up our community triggers so we can add actions to them
 *
 * @since 1.0.0
 */
function badgeos_bp_load_community_triggers() {

	/**
	 * Grab our community triggers
	 */
	$community_triggers = $GLOBALS['badgeos_community']->community_triggers;
	if ( !empty( $community_triggers ) ) {
		foreach ( $community_triggers as $optgroup_name => $triggers ) {
			foreach ( $triggers as $trigger_hook => $trigger_name ) {
				add_action( $trigger_hook, 'badgeos_bp_trigger_event', 10, 10 );
				add_action( $trigger_hook, 'badgeos_bp_trigger_award_points_event', 10, 10 );
				add_action( $trigger_hook, 'badgeos_bp_trigger_deduct_points_event', 10, 10 );
				add_action( $trigger_hook, 'badgeos_bp_trigger_ranks_event', 10, 10 );
			}
		}
	}

}
add_action( 'init', 'badgeos_bp_load_community_triggers' );

/**
 * Count a user's relevant actions for a given step
 *
 * @param  integer $activities count that specifies count if applied on previous filters
 * @param  integer $user_id The given user's ID
 * @param  integer $step_id The Current Step ID
 *
 * @return integer $activities The activity count of applied trigger
 */
function badgeos_bp_step_activity( $return, $user_id, $step_id, $this_trigger, $site_id, $arg ) {
	
	if ( 'step' != get_post_type( $step_id ) )
		return $return;

	// Grab the requirements for this step
	/**
	 * Grab our community triggers
	 */
	$step_requirements = badgeos_get_step_requirements( $step_id );
	
	
	/**
	 * If the step is triggered by joining a specific group
	 */
	if ( 'get_accepted_on_specific_private_group' ==  $step_requirements['community_trigger'] ) {

		/**
		 * And our user is a part of that group, return true
		 */
		if ( ! groups_is_user_member( $user_id, $step_requirements['private_group_id'] ) ) {
			return 0;
		}
	}

	if ( ! empty( $step_requirements["trigger_type"] ) && trim( $step_requirements["trigger_type"] )=='community_trigger' ) {

		$parent_achievement = badgeos_get_parent_of_achievement( $step_id );
		$parent_id = $parent_achievement->ID;
		
		$user_crossed_max_allowed_earnings = badgeos_achievement_user_exceeded_max_earnings( $user_id, $parent_id );
		if ( ! $user_crossed_max_allowed_earnings ) {
			$minimum_activity_count = absint( get_post_meta( $step_id, '_badgeos_count', true ) );
			$count_step_trigger = $step_requirements["community_trigger"];
			$activities = badgeos_get_user_trigger_count( $user_id, $count_step_trigger );
			$relevant_count = absint( $activities );

			$achievements = badgeos_get_user_achievements(
				array(
					'user_id' => absint( $user_id ),
					'achievement_id' => $step_id
				)
			);

			$total_achievments = count( $achievements );
			$used_points = intval( $minimum_activity_count ) * intval( $total_achievments );
			$remainder = intval( $relevant_count ) - $used_points;

			$return  = 0;
			if ( absint( $remainder ) >= $minimum_activity_count )
				$return  = $remainder;
			
			return $return;
		} else {
			return 0;
		}
	}

	return $return;
}
add_filter( 'user_deserves_achievement', 'badgeos_bp_step_activity', 15, 6 );

function groups_promote_member_callback($group_id, $user_id, $status) {
	//echo $group_id.', '.$user_id.', '.$status;
	//exit;
}
add_action( 'groups_promote_member', 'groups_promote_member_callback',10,3 );

/**
 * Handle community triggers for award points
 */
function badgeos_bp_trigger_award_points_event() {
	
	/**
     * Setup all our globals
     */
	global $user_ID, $blog_id, $wpdb;

	$site_id = $blog_id;

	$args = func_get_args();
	
	/**
     * Grab our current trigger
     */
	$this_trigger = current_filter();
	
	/**
     * Grab the user ID
     */
	$user_id = badgeos_trigger_get_user_id( $this_trigger, $args );
	$user_data = get_user_by( 'id', $user_id );

	/**
     * Sanity check, if we don't have a user object, bail here
     */
	if ( ! is_object( $user_data ) )
		return $args[ 0 ];
	
	/**
     * If the user doesn't satisfy the trigger requirements, bail here\
     */
	if ( ! apply_filters( 'user_deserves_point_award_trigger', true, $user_id, $this_trigger, $site_id, $args ) ) {
        return $args[ 0 ];
    }
    
	/**
     * Now determine if any badges are earned based on this trigger event
     */
	$triggered_points = $wpdb->get_results( $wpdb->prepare("
			SELECT p.ID as post_id FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON 
			( p.ID = pm.post_id AND pm.meta_key = '_point_trigger_type' )INNER JOIN $wpdb->postmeta AS pmtrg 
			ON ( p.ID = pmtrg.post_id AND pmtrg.meta_key = '_badgeos_community_trigger' ) 
			where p.post_status = 'publish' AND pmtrg.meta_value =  %s 
			",
			$this_trigger
		) );
	
	if( !empty( $triggered_points ) ) {
		foreach ( $triggered_points as $point ) { 

			$parent_point_id = get_parent_id( $point->post_id );

			/**
			 * Update hook count for this user
			 */
			$new_count = badgeos_points_update_user_trigger_count( $point->post_id, $parent_point_id, $user_id, $this_trigger, $site_id, 'Award', $args );
			
			badgeos_maybe_award_points_to_user( $point->post_id, $parent_point_id , $user_id, $this_trigger, $site_id, $args );
		}
	}
}

/**
 * Handle community triggers for deduct points
 */
function badgeos_bp_trigger_deduct_points_event( $args='' ) {
	
	/**
     * Setup all our globals
     */
	global $user_ID, $blog_id, $wpdb;

	$site_id = $blog_id;

	$args = func_get_args();

	/**
     * Grab our current trigger
     */
	$this_trigger = current_filter();

	/**
     * Grab the user ID
     */
	$user_id = badgeos_trigger_get_user_id( $this_trigger, $args );
	$user_data = get_user_by( 'id', $user_id );

	/**
     * Sanity check, if we don't have a user object, bail here
     */
	if ( ! is_object( $user_data ) ) {
        return $args[ 0 ];
    }

	/**
     * If the user doesn't satisfy the trigger requirements, bail here
     */
	if ( ! apply_filters( 'user_deserves_point_deduct_trigger', true, $user_id, $this_trigger, $site_id, $args ) ) {
        return $args[ 0 ];
    }

	/**
     * Now determine if any Achievements are earned based on this trigger event
     */
	$triggered_deducts = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.ID as post_id FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON 
		( p.ID = pm.post_id AND pm.meta_key = '_deduct_trigger_type' )INNER JOIN $wpdb->postmeta AS pmtrg 
		ON ( p.ID = pmtrg.post_id AND pmtrg.meta_key = '_badgeos_community_trigger' ) 
		where p.post_status = 'publish' AND pmtrg.meta_value =  %s",
        $this_trigger
    ) );

	if( !empty( $triggered_deducts ) ) {
		foreach ( $triggered_deducts as $point ) { 
			
			$parent_point_id = get_parent_id( $point->post_id );

			/**
             * Update hook count for this user
             */
			$new_count = badgeos_points_update_user_trigger_count( $point->post_id, $parent_point_id, $user_id, $this_trigger, $site_id, 'Deduct', $args );
			
			badgeos_maybe_deduct_points_to_user( $point->post_id, $parent_point_id , $user_id, $this_trigger, $site_id, $args );

		}
	}	
}

/**
 * Handle community triggers for ranks
 */
function badgeos_bp_trigger_ranks_event( $args='' ) {
	
	/**
     * Setup all our globals
     */
	global $user_ID, $blog_id, $wpdb;

	$site_id = $blog_id;

	$args = func_get_args();

	/**
     * Grab our current trigger
     */
	$this_trigger = current_filter();

	
	/**
     * Grab the user ID
     */
	$user_id = badgeos_trigger_get_user_id( $this_trigger, $args );
	$user_data = get_user_by( 'id', $user_id );

	/**
     * Sanity check, if we don't have a user object, bail here
     */
	if ( ! is_object( $user_data ) )
		return $args[ 0 ];

	/**
     * If the user doesn't satisfy the trigger requirements, bail here
     */
	if ( ! apply_filters( 'badgeos_user_rank_deserves_trigger', true, $user_id, $this_trigger, $site_id, $args ) )
		return $args[ 0 ];

	/**
     * Now determine if any Achievements are earned based on this trigger event
     */
	$triggered_ranks = $wpdb->get_results( $wpdb->prepare(
							"SELECT p.ID as post_id FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON 
							( p.ID = pm.post_id AND pm.meta_key = '_rank_trigger_type' )INNER JOIN $wpdb->postmeta AS pmtrg 
							ON ( p.ID = pmtrg.post_id AND pmtrg.meta_key = '_badgeos_community_trigger' ) 
							where p.post_status = 'publish' AND pmtrg.meta_value =  %s",
							$this_trigger
						) );
	
	if( !empty( $triggered_ranks ) ) {
		foreach ( $triggered_ranks as $rank ) { 
			$parent_id = get_parent_id( $rank->post_id );
			if( absint($parent_id) > 0) { 
				$new_count = badgeos_ranks_update_user_trigger_count( $rank->post_id, $parent_id,$user_id, $this_trigger, $site_id, $args );
				badgeos_maybe_award_rank( $rank->post_id,$parent_id,$user_id, $this_trigger, $site_id, $args );
			} 
		}
	}
}

/**
 * Handle each of our community triggers
 *
 * @since 1.0.0
 */
function badgeos_bp_trigger_event( $args='' ) {
	/**
	 * Setup all our important variables
	 */
	global $user_ID, $blog_id, $wpdb;
	
	
	
	if ( 'bp_core_activated_user' == current_filter() ) {
		$user_ID = absint( $args );
	}

	if ( 'groups_promoted_member' == current_filter() ) {
		$user_ID = get_current_user_id();
	}

	if ( 'groups_promote_member' == current_filter() ) {
		$args = func_get_args();
		$user_ID = $args[1];
	}
	
	if ( 'get_a_favorite_on_activity_stream' == current_filter() ||  'groups_join_specific_group' == current_filter() || 'get_accepted_on_private_group' == current_filter() || 'get_accepted_on_specific_private_group' == current_filter() ) {
		$user_ID = absint( $args[1] );
	}

	$user_data = get_user_by( 'id', $user_ID );

	/**
	 * Sanity check, if we don't have a user object, bail here
	 */
	if ( ! is_object( $user_data ) ) {
		return $args[0];
	}

	/**
	 * Grab the current trigger
	 */
	$this_trigger = current_filter();

	/**
	 * Now determine if any badges are earned based on this trigger event
	 */
	$triggered_achievements = $wpdb->get_results( 
		$wpdb->prepare( 
			"SELECT pm.post_id FROM $wpdb->postmeta as pm inner 
			join $wpdb->posts as p on( pm.post_id = p.ID ) WHERE p.post_status = 'publish' and 
			pm.meta_key = '_badgeos_community_trigger' AND pm.meta_value = %s", $this_trigger) 
		);
	
	if( count( $triggered_achievements ) > 0 ) {
		$is_not_logged = true;
		foreach ( $triggered_achievements as $achievement ) {
		
			$parents = badgeos_get_achievements( array( 'parent_of' => $achievement->post_id ) );
			if( count( $parents ) > 0 ) {
				
				/**
				 * Since we are triggering multiple times based on group joining, we need to check if we're on the groups_join_specific_group filter.
				 */
				if ( 'bp_user_completed_profile' == current_filter() ) {
					$main_achievement = $parents[0];
					if( $main_achievement ) {

						$achievements = badgeos_get_user_achievements( array( 'user_id' => absint( $user_ID ), 'achievement_id' => absint( $main_achievement->ID ) ) );
						if( count( $achievements ) == 0 ) {

							if( $is_not_logged == true ) {
								
								/**
								 * Update hook count for this user
								 */
								$new_count = badgeos_update_user_trigger_count( $user_ID, $this_trigger, $blog_id ); 
		
								/**
								 * Mark the count in the log entry
								 */
								badgeos_post_log_entry( null, $user_ID, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', 'badgeos-community' ), $user_data->user_login, $this_trigger, $new_count ) );
								$is_not_logged = false;
							}

							badgeos_maybe_award_achievement_to_user( $achievement->post_id, $user_ID, $this_trigger, $blog_id, $args );
						}
					}
				} else if ( 'groups_join_specific_group' == current_filter() ) {
					
					/**
					 * We only want to trigger this when we're checking for the appropriate triggered group ID.
					 */
					$group_id = get_post_meta( $achievement->post_id, '_badgeos_group_id', true );
					
					if ( $group_id == $args[0] ) {
						
						if( $is_not_logged == true ) {
							
							/**
							 * Update hook count for this user
							 */
							$new_count = badgeos_update_user_trigger_count( $user_ID, $this_trigger, $blog_id ); 
	
							/**
							 * Mark the count in the log entry
							 */
							badgeos_post_log_entry( null, $user_ID, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', 'badgeos-community' ), $user_data->user_login, $this_trigger, $new_count ) );
							$is_not_logged = false;
						}

						badgeos_maybe_award_achievement_to_user( $achievement->post_id, $user_ID, $this_trigger, $blog_id, $args );
					}
				} else if ( 'get_accepted_on_specific_private_group' == current_filter() ) {
					
					/**
					 * We only want to trigger this when we're checking for the appropriate triggered group ID.
					 */
					$group_id = get_post_meta( $achievement->post_id, '_badgeos_private_group_id', true );
										
					if ( $group_id == $args[0] ) {
						
						if( $is_not_logged == true ) {
							
							/**
							 * Update hook count for this user
							 */
							$new_count = badgeos_update_user_trigger_count( $user_ID, $this_trigger, $blog_id ); 

							/**
							 * Mark the count in the log entry
							 */
							badgeos_post_log_entry( null, $user_ID, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', 'badgeos-community' ), $user_data->user_login, $this_trigger, $new_count ) );
							$is_not_logged = false;
						}

						badgeos_maybe_award_achievement_to_user( $achievement->post_id, $user_ID, $this_trigger, $blog_id, $args );
					}
				}
				
				else {
					if( $is_not_logged == true ) {
						
						/**
						 * Update hook count for this user
						 */
						$new_count = badgeos_update_user_trigger_count( $user_ID, $this_trigger, $blog_id ); 

						/**
						 * Mark the count in the log entry
						 */
						badgeos_post_log_entry( null, $user_ID, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', 'badgeos-community' ), $user_data->user_login, $this_trigger, $new_count ) );
						
						$is_not_logged = false;
					}
					
					badgeos_maybe_award_achievement_to_user( $achievement->post_id, $user_ID, $this_trigger, $blog_id, $args );
				}
			}
		}
	}
}

if ( ! function_exists('write_log')) {
	function write_log ( $log )  {
	   if ( is_array( $log ) || is_object( $log ) ) {
		  error_log( print_r( $log, true ) );
	   } else {
		  error_log( $log );
	   }
	}
 }

/**
 * Fires our group_join_specific_group action for joining public groups.
 *
 * @since 1.2.1
 *
 * @param int $group_id ID of the public group being joined.
 * @param int $user_id ID of the user joining the group.
 */
function badgeos_bp_do_specific_group( $group_id = 0, $user_id = 0 ) {
	
	if ( groups_is_user_member( $user_id, $group_id ) ) {
		do_action( 'groups_join_specific_group', array( $group_id, $user_id ) );
	}
}
add_action( 'groups_join_group', 'badgeos_bp_do_specific_group', 15, 2 );

/**
 * Fires our group_join_specific_group action for joining Membership request or Hidden groups.
 *
 * @since 1.2.2
 *
 * @param int       $user_id  ID of the user joining the group.
 * @param int       $group_id ID of the group being joined.
 * @param bool|true $accepted Whether or not the membership was accepted. Default true.
 */
function badgeos_bp_do_specific_group_requested_invited( $user_id = 0, $group_id = 0, $accepted = true ) {
	if ( groups_is_user_member( $user_id, $group_id ) ) {
		do_action( 'groups_join_specific_group', array( $group_id, $user_id ) );
	}
}
add_action( 'groups_membership_accepted', 'badgeos_bp_do_specific_group_requested_invited', 15, 3 );
add_action( 'groups_accept_invite', 'badgeos_bp_do_specific_group_requested_invited', 15, 3 );



/**
 * Fires when user make an item favorite.
 *
 * @since 1.2.6
 *
 * @param int       $user_id  ID of the user joining the group.
 * @param int       $group_id ID of the group being joined.
 * @param bool|true $accepted Whether or not the membership was accepted. Default true.
 */
function get_accepted_on_private_group_callback( $user_id = 0, $group_id = 0, $accepted = true ) {
	$group = groups_get_group( $group_id );
	if( $group ) {
		if( trim( $group->status ) == 'private' ) {
			do_action( 'get_accepted_on_private_group', array( $group_id, $user_id  ) );

			do_action( 'get_accepted_on_specific_private_group', array( $group_id, $user_id  ) );
		}
	}
}
add_action( 'groups_membership_accepted', 'get_accepted_on_private_group_callback', 15, 3 );

/**
 * Fires when user make an item favorite.
 *
 * @since 1.2.6
 *
 * @param int       $user_id  ID of the user joining the group.
 * @param int       $group_id ID of the group being joined.
 * @param bool|true $accepted Whether or not the membership was accepted. Default true.
 */
function get_a_favorite_on_activity_stream_item( $activity_id = 0, $user_id = 0 ) {

	$favorites = bp_get_user_meta( $user_id, 'bp_favorite_activities', true );

	$activites = bp_has_activities( array(
		'show_hidden'      => true,
		'include'          => $activity_id,
	) );
	if( $activites ) {
		bp_the_activity();
		$new_user_id = bp_get_activity_user_id();
		if( intval( $new_user_id ) > 0 ) {
			do_action( 'get_a_favorite_on_activity_stream', array( $activity_id, $new_user_id ) );
		}
	}
}
add_action( 'bp_activity_add_user_favorite', 'get_a_favorite_on_activity_stream_item', 15, 3 );

/**
 * Fires when user make an item favorite.
 *
 * @since 1.2.6
 *
 * @param int       $user_id  ID of the user joining the group.
 * @param int       $group_id ID of the group being joined.
 * @param bool|true $accepted Whether or not the membership was accepted. Default true.
 */
function bp_user_completed_profile_callback() {
	
	$user_id = get_current_user_id();

	if ( ! function_exists( 'bp_get_profile_field_data' ) ) { 
		require_once '/bp-xprofile/bp-xprofile-template.php'; 
	}
	
	$name = bp_get_profile_field_data('field=Name&user_id='.$user_id);
	if( ! empty( $name ) ) {
		if( bp_get_user_has_avatar()){
			if( bp_attachments_get_user_has_cover_image()){
				do_action( 'bp_user_completed_profile', array(  ) );
			}
		}
	}
}

add_action( 'xprofile_profile_field_data_updated', 'bp_user_completed_profile_callback' );//$field_id,  $value 
add_action( 'xprofile_avatar_uploaded', 'bp_user_completed_profile_callback' );//$int_avatar_data_item_id,  $avatar_data_type,  $avatar_data 
add_action( 'xprofile_cover_image_uploaded', 'bp_user_completed_profile_callback' );//$int_bp_params_item_id 

/**
 * Decrease the number of times trigger when a community badge has been revoked..
 *
 * @since 1.2.6
 *
 * @param $user_id
 * @param $step_id
 * @param $trigger
 * @param $del_ach_id
 * @param $site_id
 */
function bos_bp_decrement_user_trigger_count_callback( $user_id, $step_id, $trigger, $del_ach_id, $site_id ){
	if( $trigger == 'community_trigger' ) {
		$times 				= absint( get_post_meta( $step_id, '_badgeos_count', true ) );
		$community_trigger 	= get_post_meta( $step_id, '_badgeos_community_trigger', true );
		
		$trigger_count = absint( badgeos_get_user_trigger_count( $user_id, $community_trigger, $site_id, [] ) );
		$trigger_count -= $times;
		
		if( $trigger_count < 0 )
	        $trigger_count = 0;

		$user_triggers = badgeos_get_user_triggers( $user_id, false );
		$user_triggers[$site_id][$community_trigger] = $trigger_count;
		update_user_meta( $user_id, '_badgeos_triggered_triggers', $user_triggers );
	}
}

add_action( 'badgeos_decrement_user_trigger_count', 'bos_bp_decrement_user_trigger_count_callback', 10, 5 );