<?php
/**
 * Register add-on settings.
 *
 * @since 1.0.0
 */
function badgeos_community_settings( $settings = array() ) {
    
    if( class_exists('BadgeOS_Interactive_Progress_Map') ) {
        ?>
            <tr>
                <td colspan="2">
                    <hr/>
                    <h2><?php _e( 'Interactive Map Settings', 'badgeos-interactive-progress-map' ); ?></h2>
                    <p class="description"><?php _e( 'Select which interactive map tab should show on buddypress profile.', 'badgeos-interactive-progress-map' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Interactive Map Tab:', 'badgeos-interactive-progress-map' ); ?></th>
                <td>
                    <p>
                        <label>
                            <input type="checkbox" name="badgeos_settings[badgeos_bp_inp_tab]" value="yes" <?php isset( $settings['badgeos_bp_inp_tab'] ) ? checked( $settings['badgeos_bp_inp_tab'], 'yes' ) : ''; ?> />
                            <?php _e( 'Yes', 'badgeos-interactive-progress-map' ); ?>
                        </label>
                    </p>
                </td>
            </tr>
            
        <?php
    }
} /* settings() */
add_action( 'badgeos_settings', 'badgeos_community_settings' );