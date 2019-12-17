<?php

/**
 * Register [badgeos_achievements_list] shortcode.
 *
 * @since 1.4.0
 */
function badgeos_register_achievements_list_shortcode() {

	// Setup a custom array of achievement types
    $achievement_types = get_posts( array(
        'post_type'      =>	'achievement-type',
        'posts_per_page' =>	-1,
    ) );

    $types = array( 'all' => __( 'All', 'badgeos' ) );
    foreach( $achievement_types as $type ) {
        $types[ $type->post_name ] = $type->post_title;
    }

    $users = get_users();
    $user_list = array();
    foreach( $users as $user ) {
        $user_list[ $user->ID ] = $user->user_login;
    }

    $posts = get_posts();
    $post_list = array();
    foreach( $posts as $post ) {
        $post_list[ $post->ID ] = $post->post_title;
    }

    badgeos_register_shortcode( array(
		'name'            => __( 'Achievement List', 'badgeos' ),
		'description'     => __( 'Output a list of achievements.', 'badgeos' ),
		'slug'            => 'badgeos_achievements_list',
		'output_callback' => 'badgeos_achievements_list_shortcode',
		'attributes'      => array(
			'type' => array(
				'name'        => __( 'Achievement Type(s)', 'badgeos' ),
				'description' => __( 'Single, or comma-separated list of, achievement type(s) to display.', 'badgeos' ),
                'type'        => 'select',
                'values'      => $types,
                'default'     => '',
            ),
			'limit' => array(
				'name'        => __( 'Limit', 'badgeos' ),
				'description' => __( 'Number of achievements to display.', 'badgeos' ),
				'type'        => 'text',
				'default'     => 10,
				),
			'show_filter' => array(
				'name'        => __( 'Show Filter', 'badgeos' ),
				'description' => __( 'Display filter controls.', 'badgeos' ),
				'type'        => 'select',
				'values'      => array(
					'true'  => __( 'True', 'badgeos' ),
					'false' => __( 'False', 'badgeos' )
					),
				'default'     => 'true',
				),
			'show_search' => array(
				'name'        => __( 'Show Search', 'badgeos' ),
				'description' => __( 'Display a search input.', 'badgeos' ),
				'type'        => 'select',
				'values'      => array(
					'true'  => __( 'True', 'badgeos' ),
					'false' => __( 'False', 'badgeos' )
					),
				'default'     => 'true',
				),
			'orderby' => array(
				'name'        => __( 'Order By', 'badgeos' ),
				'description' => __( 'Parameter to use for sorting.', 'badgeos' ),
				'type'        => 'select',
				'values'      => array(
					'menu_order' => __( 'Menu Order', 'badgeos' ),
					'ID'         => __( 'Achievement ID', 'badgeos' ),
					'title'      => __( 'Achievement Title', 'badgeos' ),
					'date'       => __( 'Published Date', 'badgeos' ),
					'modified'   => __( 'Last Modified Date', 'badgeos' ),
					'author'     => __( 'Achievement Author', 'badgeos' ),
					'rand'       => __( 'Random', 'badgeos' ),
					),
				'default'     => 'menu_order',
				),
			'order' => array(
				'name'        => __( 'Order', 'badgeos' ),
				'description' => __( 'Sort order.', 'badgeos' ),
				'type'        => 'select',
				'values'      => array( 'ASC' => __( 'Ascending', 'badgeos' ), 'DESC' => __( 'Descending', 'badgeos' ) ),
				'default'     => 'ASC',
				),
			'user_id' => array(
				'name'          => __( 'User ID', 'badgeos' ),
				'description'   => __( 'Show only achievements earned by a specific user.', 'badgeos' ),
                'type'          => 'select',
                'values'        => $user_list,
            ),
			'include' => array(
				'name'          => __( 'Include', 'badgeos' ),
				'description'   => __( 'Comma-separated list of specific achievement IDs to include.', 'badgeos' ),
                'type'          => 'select',
                'values'        => $post_list
            ),
			'exclude' => array(
				'name'          => __( 'Exclude', 'badgeos' ),
				'description'   => __( 'Comma-separated list of specific achievement IDs to exclude.', 'badgeos' ),
                'type'          => 'select',
                'values'        => $post_list
            ),
			'wpms' => array(
				'name'        => __( 'Include Multisite Achievements', 'badgeos' ),
				'description' => __( 'Show achievements from all network sites.', 'badgeos' ),
				'type'        => 'select',
				'values'      => array(
					'true'  => __( 'True', 'badgeos' ),
					'false' => __( 'False', 'badgeos' )
					),
				'default'     => 'false',
				),
		),
	) );
}
add_action( 'init', 'badgeos_register_achievements_list_shortcode' );

/**
 * Achievement List Shortcode.
 *
 * @since  1.0.0
 *
 * @param  array $atts Shortcode attributes.
 * @return string 	   HTML markup.
 */
function badgeos_achievements_list_shortcode( $atts = array () ){

    $key = 'badgeos_achievements_list';
    if( is_array( $atts ) && count( $atts ) > 0 ) {
        foreach( $atts as $index => $value ) {
            $key .= "_".strval( $value ) ;
        }
    }

    /**
     * check if shortcode has already been run
     */
	if ( isset( $GLOBALS[$key] ) ) {
        return '';
    }

	global $user_ID;
	extract( shortcode_atts( array(
		'type'        => 'all',
		'limit'       => '10',
		'show_filter' => true,
		'show_search' => true,
        'show_child' => true,
        'show_parent' => true,
        'group_id'    => '0',
		'user_id'     => '0',
		'wpms'        => false,
		'orderby'     => 'menu_order',
		'order'       => 'ASC',
		'include'     => array(),
		'exclude'     => array(),
		'meta_key'    => '',
		'meta_value'  => ''
	), $atts, 'badgeos_achievements_list' ) );

	wp_enqueue_style( 'badgeos-front' );
	wp_enqueue_script( 'badgeos-achievements' );

	$data = array(
		'ajax_url'    => esc_url( admin_url( 'admin-ajax.php', 'relative' ) ),
		'type'        => $type,
		'limit'       => $limit,
		'show_filter' => $show_filter,
		'show_search' => $show_search,
        'show_child'  => $show_child,
        'show_parent' => $show_parent,
        'group_id'    => $group_id,
		'user_id'     => $user_id,
		'wpms'        => $wpms,
		'orderby'     => $orderby,
		'order'       => $order,
		'include'     => $include,
		'exclude'     => $exclude,
		'meta_key'    => $meta_key,
		'meta_value'  => $meta_value
	);
//	wp_localize_script( 'badgeos-achievements', 'badgeos', $data );

	// If we're dealing with multiple achievement types
	if ( 'all' == $type ) {
		$post_type_plural = __( 'achievements', 'badgeos' );
	} else {
		$types = explode( ',', $type );
        $badge_post_type = get_post_type_object( $types[0] );
        $type_name = '';
        if( $badge_post_type ) {
            if( isset( $badge_post_type->labels ) ) {
                if( isset( $badge_post_type->labels->name ) ) {
                    $type_name = $badge_post_type->labels->name;
                }
            }
        }
        $post_type_plural = ( 1 == count( $types ) && !empty( $types[0] ) ) ? $type_name : __( 'achievements', 'badgeos' );
    }

	$badges = '';

	$badges .= '<div id="badgeos-achievements-filters-wrap">';
		// Filter
		if ( $show_filter == 'false' ) {

			$filter_value = 'all';
			if( $user_id ) {
				$filter_value = 'completed';
				$badges .= '<input type="hidden" name="user_id" id="user_id" value="'.$user_id.'">';
			}
			$badges .= '<input type="hidden" name="achievements_list_filter" class="achievements_list_filter" id="achievements_list_filter" value="'.$filter_value.'">';

		} else {

			$badges .= '<div id="badgeos-achievements-filter">';

				$badges .= __( 'Filter:', 'badgeos' ) . '<select name="achievements_list_filter" class="achievements_list_filter" id="achievements_list_filter">';

					$badges .= '<option value="all">' . sprintf( __( 'All %s', 'badgeos' ), $post_type_plural );

					// If logged in
					if ( $user_ID > 0 ) {
						$badges .= '<option value="completed">' . sprintf( __( 'Completed %s', 'badgeos' ), $post_type_plural );
						$badges .= '<option value="not-completed">' . sprintf( __( 'Not Completed %s', 'badgeos' ), $post_type_plural );
					}
					// TODO: if show_points is true "Badges by Points"
					// TODO: if dev adds a custom taxonomy to this post type then load all of the terms to filter by

				$badges .= '</select>';

			$badges .= '</div>';

		}

		// Search
		if ( $show_search != 'false' ) {

			$search = isset( $_POST['achievements_list_search'] ) ? $_POST['achievements_list_search'] : '';
			$badges .= '<div id="badgeos-achievements-search">';
            $badges .= '<form id="achievements_list_search_go_form" class="achievements_list_search_go_form" action="'. get_permalink( get_the_ID() ) .'" method="post">';
            $badges .= sprintf( __( 'Search: %s', 'badgeos' ), '<input type="text" id="achievements_list_search" name="achievements_list_search" class="achievements_list_search" value="'. $search .'">' );
            $badges .= '<input type="submit" id="achievements_list_search_go" name="achievements_list_search_go" class="achievements_list_search_go" value="' . esc_attr__( 'Go', 'badgeos' ) . '">';
            $badges .= '</form>';
			$badges .= '</div>';

		}

	$badges .= '</div><!-- #badgeos-achievements-filters-wrap -->';

	// Content Container
	$badges .= '<div id="badgeos-achievements-container"></div>';

	// Hidden fields and Load More button
	$badges .= '<input type="hidden" id="badgeos_achievements_offset" value="0">';
	$badges .= '<input type="hidden" id="badgeos_achievements_count" value="0">';
	$badges .= '<input type="button" class="achievements_list_load_more" value="' . esc_attr__( 'Load More', 'badgeos' ) . '" style="display:none;">';
	$badges .= '<div class="badgeos-spinner"></div>';

    if( is_array( $include ) ){
        $include = implode(',', $include);
    }
    if( is_array( $exclude ) ){
        $exclude = implode(',', $exclude);
    }

    $maindiv = '<div class="badgeos_achievement_main_container" data-url="'.esc_url( admin_url( 'admin-ajax.php', 'relative' ) ).'" data-type="'.$type.'" data-limit="'.$limit.'" data-show_child="'.$show_child.'" data-show_parent="'.$show_parent.'" data-show_filter="'.$show_filter.'" data-show_search="'.$show_search.'" data-group_id="'.$group_id.'" data-user_id="'.$user_id.'" data-wpms="'.$wpms.'" data-orderby="'.$orderby.'" data-order="'.$order.'" data-include="'.$include.'" data-exclude="'.$exclude.'" data-meta_key="'.$meta_key.'" data-meta_value="'.$meta_value.'">';
    $maindiv .= $badges;
    $maindiv .= '</div>';


    // Reset Post Data
	wp_reset_postdata();

	// Save a global to prohibit multiple shortcodes
	$GLOBALS[$key] = true;
	return $maindiv;
}