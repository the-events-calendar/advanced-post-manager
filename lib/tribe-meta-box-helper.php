<?php

/**
 * Accepts a standard set of APM args and automagically creates meta boxes
 *
 */

if ( class_exists( 'Tribe_Meta_Box_Helper' ) ) {
	return;
}

class Tribe_Meta_Box_Helper {

	const PREFIX = 'tribe_';

	protected $fields = [];
	protected $post_type = '';
	protected $metaboxes = [];

	protected $type_map = [
		'DATE' => 'date',
		'TIME' => 'time',
	];

	public function __construct( $post_type, $fields, $metaboxes = [] ) {
		$this->post_type = $post_type;
		$this->fields = $this->fill_filter_vars( $fields );
		$this->metaboxes = $metaboxes;
		$this->create_meta_boxes();
	}

	// HELPERS AND UTILITIES

	protected function create_meta_boxes() {
		require_once 'tribe-meta-box.php';
		$boxes = $this->map_meta_boxes();
		foreach ( $boxes as $box ) {
			new Tribe_Meta_Box( $box );
		}
	}

	protected function map_meta_boxes() {
		$return_boxes = [];
		$default_id = self::PREFIX . $this->post_type . '_metabox';
		$default_box = [ $default_id => __( 'Extended Information', 'advanced-post-manager' ) ];
		$metaboxes = $this->metaboxes;
		if ( is_string( $metaboxes ) ) {
			$default_box[ $default_id ] = $metaboxes;
			$metaboxes = [];
		}

		$boxes = array_merge( $metaboxes, $default_box );
		$box_fields = [];
		foreach ( $boxes as $key => $value ) {
			$box_fields[ $key ] = [];
		}

		foreach ( $this->fields as $field ) {
			if ( isset( $field['metabox'] ) && array_key_exists( $field['metabox'], $box_fields ) ) {
				$box_fields[ $field['metabox'] ][] = $field;
			}
			else {
				$box_fields[ $default_id ][] = $field;
			}
		}
		foreach ( $boxes as $key => $value ) {
			if ( empty( $box_fields[ $key ] ) ) {
				continue;
			}
			$return_boxes[] = [
				'id' => $key,
				'title' => $value,
				'pages' => $this->post_type,
				'fields' => $this->order_meta_fields( $box_fields[ $key ] ),
			];
		}
		return $return_boxes;
	}

	protected function order_meta_fields( $fields ) {
		$ordered = [];
		foreach ( $fields as $key => $field ) {
			if ( isset( $field['metabox_order'] ) ) {
				$order = (int) $field['metabox_order'];
				$ordered[ $order ] = $field;
				unset( $fields[ $key ] );
			}
		}
		ksort( $ordered );
		return array_merge( $ordered, $fields );
	}

	protected function fill_filter_vars( $fields ) {
		foreach ( $fields as $key => $field ) {
			if ( ! isset( $field['type'] ) ) {
				$fields[ $key ]['type'] = $this->predictive_type( $field );
			}
		}
		return $fields;
	}

	// Only gets called when no explicit type was set, remember
	protected function predictive_type( $field ) {
		$type = 'text';
		// Options? Select or radio
		if ( isset( $field['options'] ) && ! empty( $field['options'] ) ) {
			$type = ( count( $field['options'] ) < 3 ) ? 'radio' : 'select';
		}
		elseif ( isset( $field['cast'] ) ) {
			$cast = ucwords( $field['cast'] );
			$type = isset( $this->type_map[ $cast ] ) ? $this->type_map[ $cast ] : $type;
		}
		return $type;
	}

	public function log() {
		foreach ( func_get_args() as $data ) {
			error_log( print_r( $data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}
}
