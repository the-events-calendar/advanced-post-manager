<?php
/**
 * Template tags for Advanced Post Manager.
 *
 * @package Advanced_Post_Manager
 * @subpackage Template_Tags
 * @since 1.0.0
 */

/**
 * Handy function for creating a dropdown field for filters.
 *
 * @param string       $name The HTML name for the <select> field.
 * @param array        $options An array of $key=>$value pairs, producing <option value="$key">$value</option> in the dropdown.
 * @param string|array $active The active state of the field. Values correspond to the $key's in $options.
 * @param boolean      $allow_multi Whether or not this field should be expandable to a multi-select field.
 * @return string HTML <select> element.
 */
function tribe_select_field( $name, $options = [], $active = '', $allow_multi = false ) {
	if ( ! class_exists( 'Tribe_Filters' ) ) {
		include_once TRIBE_APM_LIB_PATH . 'tribe-filters.class.php';
	}
	return Tribe_Filters::select_field( $name, $options, $active, $allow_multi );
}

/**
 * Registers APM
 *
 * @param string       $post_type The post_type Advanced Post Manager will be attached to.
 * @param array        $args A multidimensional array of filter/column arrays. See documentation for more.
 * @param string|array $metaboxes An array of metabox => Meta Box Title pairs or a single Meta Box Title string.
 * @return object Tribe_APM object
 */
function tribe_setup_apm( $post_type, $args, $metaboxes = [] ) {
	return new Tribe_APM( $post_type, $args, $metaboxes );
}
