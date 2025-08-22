<?php
/**
 * Accepts a standard set of APM args and automagically creates meta boxes.
 */

if ( class_exists( 'Tribe_Meta_Box_Helper' ) ) {
	return;
}

/**
 * A class to help create meta boxes.
 */
class Tribe_Meta_Box_Helper {

	/**
	 * The prefix for the meta boxes.
	 *
	 * @var string
	 */
	const PREFIX = 'tribe_';

	/**
	 * The fields.
	 *
	 * @var array
	 */
	protected $fields = [];

	/**
	 * The post type.
	 *
	 * @var string
	 */
	protected $post_type = '';

	/**
	 * The meta boxes.
	 *
	 * @var array
	 */
	protected $metaboxes = [];

	/**
	 * The type map.
	 *
	 * @var array
	 */
	protected $type_map = [
		'DATE' => 'date',
		'TIME' => 'time',
	];

	/**
	 * Constructor.
	 *
	 * @param string $post_type The post type.
	 * @param array  $fields    The fields.
	 * @param array  $metaboxes The meta boxes.
	 */
	public function __construct( $post_type, $fields, $metaboxes = [] ) {
		$this->post_type = $post_type;
		$this->fields    = $this->fill_filter_vars( $fields );
		$this->metaboxes = $metaboxes;

		$this->create_meta_boxes();
	}

	// HELPERS AND UTILITIES.

	/**
	 * Create the meta boxes.
	 *
	 * @return void
	 */
	protected function create_meta_boxes() {
		require_once 'tribe-meta-box.php';
		$boxes = $this->map_meta_boxes();

		foreach ( $boxes as $box ) {
			new Tribe_Meta_Box( $box );
		}
	}

	/**
	 * Map the meta boxes.
	 *
	 * @return array The meta boxes.
	 */
	protected function map_meta_boxes() {
		$return_boxes = [];
		$default_id   = self::PREFIX . $this->post_type . '_metabox';
		$default_box  = [ $default_id => __( 'Extended Information', 'advanced-post-manager' ) ];
		$metaboxes    = $this->metaboxes;

		if ( is_string( $metaboxes ) ) {
			$default_box[ $default_id ] = $metaboxes;
			$metaboxes                  = [];
		}

		$boxes      = array_merge( $metaboxes, $default_box );
		$box_fields = [];

		foreach ( $boxes as $key => $value ) {
			$box_fields[ $key ] = [];
		}

		foreach ( $this->fields as $field ) {
			if ( isset( $field['metabox'] ) && isset( $box_fields[ $field['metabox'] ] ) ) {
				$box_fields[ $field['metabox'] ][] = $field;
			} else {
				$box_fields[ $default_id ][] = $field;
			}
		}
		foreach ( $boxes as $key => $value ) {
			if ( empty( $box_fields[ $key ] ) ) {
				continue;
			}
			$return_boxes[] = [
				'id'     => $key,
				'title'  => $value,
				'pages'  => $this->post_type,
				'fields' => $this->order_meta_fields( $box_fields[ $key ] ),
			];
		}
		return $return_boxes;
	}

	/**
	 * Order the meta fields.
	 *
	 * @param array $fields The fields.
	 *
	 * @return array The ordered fields.
	 */
	protected function order_meta_fields( $fields ) {
		$ordered = [];

		foreach ( $fields as $key => $field ) {
			if ( isset( $field['metabox_order'] ) ) {
				$order             = (int) $field['metabox_order'];
				$ordered[ $order ] = $field;

				unset( $fields[ $key ] );
			}
		}
		ksort( $ordered );

		return array_merge( $ordered, $fields );
	}

	/**
	 * Fill the filter vars.
	 *
	 * @param array $fields The fields.
	 *
	 * @return array The fields.
	 */
	protected function fill_filter_vars( $fields ) {
		foreach ( $fields as $key => $field ) {
			if ( ! isset( $field['type'] ) ) {
				$fields[ $key ]['type'] = $this->predictive_type( $field );
			}
		}
		return $fields;
	}

	/**
	 * Predict the type of field based on the field's attributes.
	 *
	 * Only gets called when no explicit type was set.
	 *
	 * @param array $field The field attributes.
	 *
	 * @return string The predicted type.
	 */
	protected function predictive_type( $field ) {
		$type = 'text';
		// Options? Select or radio.
		if ( isset( $field['options'] ) && ! empty( $field['options'] ) ) {
			$type = ( count( $field['options'] ) < 3 ) ? 'radio' : 'select';
		} elseif ( isset( $field['cast'] ) ) {
			$cast = ucwords( $field['cast'] );
			$type = $this->type_map[ $cast ] ?? $type;
		}
		return $type;
	}

	/**
	 * Log data to the error log.
	 *
	 * @return void
	 */
	public function log() {
		foreach ( func_get_args() as $data ) {
			error_log( print_r( $data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}
}
