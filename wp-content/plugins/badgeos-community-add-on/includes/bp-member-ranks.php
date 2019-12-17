<?php
/**
 * BuddyPress Membership ranks Functions
 *
 * @package BadgeOS Community
 * @subpackage Members
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Creates a BuddyPress member page for BadgeOS
 *
 * @since 1.0.0
 */
function badgeos_bp_member_ranks() {
	add_action( 'bp_template_content', 'badgeos_bp_member_ranks_content' );
	bp_core_load_template( apply_filters( 'badgeos_bp_member_ranks', 'members/single/plugins' ) );
}

/**
 * Displays a members achievements
 *
 * @since 1.0.0
 */
function badgeos_bp_member_ranks_content() {

    $user_id = bp_displayed_user_id();
    if( intval( $user_id ) == 0 ) {
        $user_id = get_current_user_id();
    }

    $rank_types = badgeos_get_rank_types_slugs_detailed();
	$can_manage = current_user_can( badgeos_get_manager_capability() );

	if ( is_array( $rank_types ) && !empty( $rank_types ) ) {
		$rank_default = false;
		foreach ( $rank_types as $rank_key => $rank_type ) {
			if( ! $rank_default ) {
				$rank_default = $rank_key;
			}
			$name = $rank_type['plural_name'];
			$slug = str_replace( ' ', '-', strtolower( $rank_key ) );
			if ( $slug && strpos( $_SERVER['REQUEST_URI'], $slug ) ) {
				$type = $rank_key;
			}
		}
		if ( empty( $type ) )
			$type = $rank_default;
	}
	ob_start();
	?>
	<hr />
	<table class="form-table badgeos-rank-table badgeos-rank-revoke-table">
		<thead>
			<tr>
				<th><?php _e( 'Image', 'badgeos' ); ?></th>
				<th><?php _e( 'Name', 'badgeos' ); ?></th>
				<th><?php _e( 'Rank Type', 'badgeos' ); ?></th>
				<th><?php _e( 'Last Awarded', 'badgeos' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
				$user_ranks = badgeos_get_user_ranks( array(
					'user_id' => absint( $user_id ),
					'rank_type' => $type,
				) );

				if( $user_ranks ) {
					foreach( $user_ranks as $rank ) {
						?>
						<tr class="<?php echo $rank->rank_type; ?> ">
							<?php
								$ranks_image = badgeos_get_rank_image( $rank->rank_id );
							?>
							<td><?php echo $ranks_image; ?></td>
							<td><?php echo $rank->rank_title; ?></td>
							<td><?php echo $rank->rank_type; ?></td>
							<?php
							$last_awarded = get_user_meta( $user_id, '_badgeos_'.$rank->rank_type.'_rank', true );
							?>
							<td class="last-awarded">
								<?php
								if( !empty( $last_awarded ) && $last_awarded == $rank->id ) {
									?><i class="fa fa-check" aria-hidden="true"></i><?php
								}
								?>
							</td>
						</tr>
						<?php
					}
				} else {
					$colpan = ( $can_manage ) ? 5 : 4;
					?>
					<tr class="no-awarded-rank">
						<td colspan="<?php echo $colpan; ?>">
						<span class="description">
							<?php _e( 'No Awarded Ranks', 'badgeos' ); ?>
						</span>
						</td>
					</tr>
					<?php
				}
			?>
		</tbody>
		<tfoot>
		<tr>
			<th><?php _e( 'Image', 'badgeos' ); ?></th>
			<th><?php _e( 'Name', 'badgeos' ); ?></th>
			<th><?php _e( 'Rank Type', 'badgeos' ); ?></th>
			<th><?php _e( 'Last Awarded', 'badgeos' ); ?></th>
			
		</tr>
		</tfoot>
	</table>

	<?php
	
	$output = ob_get_clean();
	
	echo apply_filters( 'bp_badgeos_community_ranks', $output );
}

/**
 * Loads BadgeOS_Community_Members Class from bp_init
 *
 * @since 1.0.0
 */
function badgeos_community_ranks_loader() {
	$bp = buddypress();
	$hasbp = function_exists( 'buddypress' ) && buddypress() && ! buddypress()->maintenance_mode && bp_is_active( 'xprofile' );
	if ( !$hasbp )
		return;

	$GLOBALS['badgeos_community_member_ranks'] = new BadgeOS_Community_Member_Ranks();

}
add_action( 'bp_init', 'badgeos_community_ranks_loader', 1 );

/**
 * handel progress map for ranks
 */
function badgeos_bp_ranks_progress_map() {
	add_action( 'bp_template_content', 'badgeos_bp_ranks_progress_map_content' );
	bp_core_load_template( apply_filters( 'badgeos_bp_member_ranks', 'members/single/plugins' ) );
}

function badgeos_bp_ranks_progress_map_content(){
	echo do_shortcode('[badgeos_ranks_interactive_progress_map limit="10" status="all"]');
}

/**
 * Build BP_Component extension object
 *
 * @since 1.0.0
 */
class BadgeOS_Community_Member_Ranks extends BP_Component {

	function __construct() {
		parent::start(
			'badgeos',
			__( 'BadgeOS', 'badgeos-community' ),
			BP_PLUGIN_DIR
		);

	}

	// Globals
	public function setup_globals( $args = '' ) {
		parent::setup_globals( array(
				'has_directory' => true,
				'root_slug'     => 'ranks',
				'slug'          => 'ranks',
			) );
	}

	// BuddyPress actions
	public function setup_actions() {
		parent::setup_actions();
	}

	// Member Profile Menu
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) {

		if ( ! is_user_logged_in() && ! bp_displayed_user_id() )
			return;

		$parent_url = trailingslashit( bp_displayed_user_domain() . $this->slug );

		// Loop existing achievement types to build array of array( 'slug' => 'ID' )
        // @TODO: update global $badgeos->achievement_types to include the post_id of each slug
        $settings = ( $exists = get_option( 'badgeos_settings' ) ) ? $exists : array();
		$args=array(
			'post_type'      => trim( $settings['ranks_main_post_type'] ),
			'post_status'    => 'publish',
			'posts_per_page' => -1
		);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) : $query->the_post();
			$arr_achievement_types[$query->post->post_name] = $query->post->ID;
			endwhile;
        }
        
		$rank_types = badgeos_get_rank_types_slugs_detailed();
		if ( !empty( $rank_types ) ) {
            // Loop achievement types current user has earned
                
			foreach ( $rank_types as $rank_key=>$rank_type ) {
                
				$name = $rank_type['plural_name'];
				$slug = str_replace( ' ', '-', strtolower( $rank_key ) );
				// Get post_id of earned achievement type slug
				
				$post_id = isset( $arr_achievement_types[$rank_key] ) ? $arr_achievement_types[$rank_key] : 0;
				
				if ( $post_id ) {

					//check if this achievement type can be shown on the member profile page
					// Only run once to set main nav and defautl sub nav
					if ( empty( $main ) ) {
						// Add to the main navigation
						$main_nav = array(
							'name'                => __( 'Ranks', 'badgeos-community' ),
							'slug'                => $this->slug,
							'position'            => 100,
							'screen_function'     => 'badgeos_bp_member_ranks',
							'default_subnav_slug' => $slug
						);
						$main = true;
					}

					$sub_nav[] = array(
						'name'            => $name,
						'slug'            => $slug,
						'parent_url'      => $parent_url,
						'parent_slug'     => $this->slug,
						'screen_function' => 'badgeos_bp_member_ranks',
						'position'        => 10,
					);

				}
			}
		}
		else {
			// Add to the main navigation
			$main_nav = array(
				'name'                => __( 'Ranks', 'badgeos-community' ),
				'slug'                => $this->slug,
				'position'            => 100,
				'screen_function'     => 'badgeos_bp_member_ranks',
				'default_subnav_slug' => 'ranks'
			);

			$sub_nav[] = array(
				'name'            => __( 'Ranks', 'badgeos-community' ),
				'slug'            => 'ranks',
				'parent_url'      => $parent_url,
				'parent_slug'     => $this->slug,
				'screen_function' => 'badgeos_bp_member_ranks',
				'position'        => 10,
			);
		}
		
		$settings = ( $exists = get_option( 'badgeos_settings' ) ) ? $exists : array();
		if( isset( $settings['badgeos_bp_inp_tab'] ) && !empty( $settings['badgeos_bp_inp_tab'] ) ) {
			$badgeos_bp_inp_tab = trim( $settings['badgeos_bp_inp_tab'] );
			if( $badgeos_bp_inp_tab=='yes' && class_exists('BadgeOS_Interactive_Progress_Map') ) {
				$sub_nav[] = array(
					'name'            => __( 'Progress Map', 'badgeos-community' ),
					'slug'            => 'progress-map',
					'parent_url'      => $parent_url,
					'parent_slug'     => $this->slug,
					'screen_function' => 'badgeos_bp_ranks_progress_map',
					'position'        => 10,
				);
			}
		}
		parent::setup_nav( $main_nav, $sub_nav );
	}

}

?>