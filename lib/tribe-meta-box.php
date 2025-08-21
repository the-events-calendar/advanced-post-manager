<?php
/**
 * Create meta box for editing pages in WordPress
 *
 * Compatible with custom post types since WordPress 3.0
 * Support input types: text, textarea, checkbox, checkbox list, radio box, select, wysiwyg, file, image, date, time, color
 */

if ( class_exists( 'Tribe_Meta_Box' ) ) {
	return;
}

/**
 * Meta Box class
 */
class Tribe_Meta_Box {

	/**
	 * The meta box.
	 *
	 * @var array
	 *
	 * @since 1.7.3
	 * @deprecated TBD Use $this->meta_box instead.
	 */
	protected $_meta_box; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The meta box.
	 *
	 * @var array
	 *
	 * @since TBD
	 */
	protected $meta_box;

	/**
	 * The fields.
	 *
	 * @var array
	 *
	 * @since 1.7.3
	 * @deprecated TBD Use $this->fields instead.
	 */
	protected $_fields; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The fields.
	 *
	 * @var array
	 *
	 * @since TBD
	 */
	protected $fields;

	/**
	 * Create meta box based on given data.
	 *
	 * @param array $meta_box The meta box.
	 */
	public function __construct( $meta_box ) {
		// Run script only in admin area.
		if ( ! is_admin() ) {
			return;
		}

		// Assign meta box values to local variables and add it's missed values.
		$this->meta_box = $meta_box;
		// Cast pages to array.
		$this->meta_box['pages'] = (array) $meta_box['pages'];
		$this->fields            = $this->meta_box['fields'];
		$this->add_missed_values();
		$this->register_scripts_and_styles();

		add_action( 'add_meta_boxes', [ $this, 'add' ] ); // Add meta box, using 'add_meta_boxes' for WP 3.0+.
		add_action( 'save_post', [ $this, 'save' ] ); // Save meta box's data.

		// Check for some special fields and add needed actions for them.
		$this->check_field_upload();
		$this->check_field_color();
		$this->check_field_date();
		$this->check_field_time();

		// Load common js, css files.
		// Must enqueue for all pages as we need js for the media upload, too.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'js_css' ] );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @return void
	 */
	public function register_scripts_and_styles() {
		// Change '\' to '/' in case using Windows.
		$content_dir = str_replace( '\\', '/', WP_CONTENT_DIR );
		$script_dir  = str_replace( '\\', '/', __DIR__ );

		// Get URL of the directory of current file, this works in both theme or plugin.
		$base_url      = trailingslashit( str_replace( $content_dir, WP_CONTENT_URL, $script_dir ) );
		$resources_url = apply_filters( 'tribe_apm_resources_url', $base_url . 'resources' );
		$resources_url = trailingslashit( $resources_url );

		wp_register_style(
			'tribe-meta-box',
			$resources_url . 'meta-box.css',
			[],
			Tribe_APM::VERSION,
			'all'
		);
		wp_register_script(
			'tribe-meta-box',
			$resources_url . 'meta-box.js',
			[ 'jquery' ],
			Tribe_APM::VERSION,
			true
		);

		wp_register_style(
			'tribe-jquery-ui-css',
			'https://ajax.googleapis.com/ajax/libs/jqueryui/' . self::get_jqueryui_ver() . '/themes/base/jquery-ui.css',
			[],
			Tribe_APM::VERSION
		);
		wp_register_script(
			'tribe-jquery-ui',
			'https://ajax.googleapis.com/ajax/libs/jqueryui/' . self::get_jqueryui_ver() . '/jquery-ui.min.js',
			[ 'jquery' ],
			Tribe_APM::VERSION,
			true
		);
		wp_register_script(
			'tribe-timepicker',
			'https://github.com/trentrichardson/jQuery-Timepicker-Addon/raw/master/jquery-ui-timepicker-addon.js',
			[ 'tribe-jquery-ui' ],
			Tribe_APM::VERSION,
			true
		);
	}

	/**
	 * Load common js, css files for the script.
	 *
	 * @return void
	 */
	public static function js_css() {
		wp_enqueue_script( 'tribe-meta-box' );
		wp_enqueue_style( 'tribe-meta-box' );
	}

	/* BEGIN UPLOAD */

	/**
	 * Check field upload and add needed actions.
	 *
	 * @return void
	 */
	public function check_field_upload() {
		if ( ! $this->has_field( 'image' ) && ! $this->has_field( 'file' ) ) {
			return;
		}

		add_action( 'post_edit_form_tag', [ $this, 'add_enctype' ] ); // Add data encoding type for file uploading.

		// Make upload feature works even when custom post type doesn't support 'editor'.
		wp_enqueue_script( 'media-upload' );
		add_thickbox();
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		add_action( 'media_upload_gallery', [ $this, 'insert_images' ] ); // Process adding multiple images to image meta field.
		add_action( 'media_upload_library', [ $this, 'insert_images' ] );
		add_action( 'media_upload_image', [ $this, 'insert_images' ] );

		add_action( 'wp_ajax_tribe_delete_file', [ $this, 'delete_file' ] ); // Ajax delete files.
		add_action( 'wp_ajax_tribe_reorder_images', [ $this, 'reorder_images' ] ); // Ajax reorder images.
	}

	/**
	 * Add data encoding type for file uploading.
	 *
	 * @return void
	 */
	public function add_enctype() {
		echo ' enctype="multipart/form-data"';
	}

	/**
	 * Process adding images to image meta field, modify from 'Faster image insert' plugin.
	 *
	 * @return void
	 */
	public function insert_images() {
		if ( ! isset( $_POST['tribe-insert'] ) || empty( $_POST['attachments'] ) ) {
			return;
		}

		check_admin_referer( 'media-form' );

		$nonce   = wp_create_nonce( 'tribe_ajax_delete' );
		$post_id = intval( $_POST['post_id'] ?? '' );
		$id      = intval( $_POST['field_id'] ?? '' );

		// Modify the insertion string.
		$html = '';
		foreach ( $_POST['attachments'] as $attachment_id => $attachment ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- all sanitized before use below.
			$attachment = stripslashes_deep( $attachment );
			if ( empty( $attachment['selected'] ) || empty( $attachment['url'] ) ) {
				continue;
			}

			$delete_title = __( 'Delete this image', 'advanced-post-manager' );
			$delete_link  = __( 'Delete', 'advanced-post-manager' );

			$li =
			'<li id="item_' . esc_attr( $attachment_id ) . '">
				<img src="' . esc_url( $attachment['url'] ) . '" />
				<a title="' . esc_attr( $delete_title ) . '" class="tribe-delete-file" href="#" rel="' . esc_attr( $nonce ) . '|' . esc_attr( $post_id ) . '|' . esc_attr( $id ) . '|' . esc_attr( $attachment_id ) . '">' . esc_html( $delete_link ) . '</a>
				<input type="hidden" name="' . esc_attr( $id ) . '[]" value="' . esc_attr( $attachment_id ) . '" />
			</li>';

			$html .= $li;
		}

		media_send_to_editor( $html );
	}

	/**
	 * Delete all attachments when delete post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function delete_attachments( $post_id ) {
		$attachments = get_posts(
			[
				'numberposts' => -1,
				'post_type'   => 'attachment',
				'post_parent' => $post_id,
			]
		);
		if ( ! empty( $attachments ) ) {
			foreach ( $attachments as $att ) {
				wp_delete_attachment( $att->ID );
			}
		}
	}

	/**
	 * Ajax callback for deleting files. Modified from a function used by "Verve Meta Boxes" plugin (http://goo.gl/LzYSq).
	 *
	 * @return void
	 */
	public function delete_file() {
		if ( ! isset( $_POST['data'] ) ) {
			die();
		}

		list( $nonce, $post_id, $key, $attach_id ) = explode( '|', $_POST['data'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- all sanitized before use below.

		if ( ! wp_verify_nonce( $nonce, 'tribe_ajax_delete' ) ) {
			die( '1' );
		}

		$post_id   = intval( $post_id );
		$key       = intval( $key );
		$attach_id = intval( $attach_id );

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			die( '1' );
		}

		$cap = get_post_type_object( $post->post_type )->cap->edit_post;

		// Check if the user can edit the post by ID.
		if ( ! current_user_can( $cap, $post->ID ) ) {
			die( '1' );
		}

		delete_post_meta( $post_id, $key, $attach_id );

		die( '0' );
	}

	/**
	 * Ajax callback for reordering images.
	 *
	 * @return void
	 */
	public function reorder_images() {
		if ( ! isset( $_POST['data'] ) ) {
			die();
		}

		list( $item_list, $post_id, $key, $nonce ) = explode( '|', $_POST['data'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- all sanitized befor euse below.

		if ( ! wp_verify_nonce( $nonce, 'tribe_ajax_reorder' ) ) {
			die( '1' );
		}

		$post_id = intval( $post_id );
		$key     = intval( $key );

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			die( '1' );
		}

		$cap = get_post_type_object( $post->post_type )->cap->edit_posts;

		// Check if the user can edit the post by ID.
		if ( ! current_user_can( $cap ) ) {
			die( '1' );
		}

		parse_str( $item_list, $items );
		$items = $items['item'];
		$order = 1;

		foreach ( $items as $item ) {
			wp_update_post(
				[
					'ID'          => $item,
					'post_parent' => $post_id,
					'menu_order'  => $order,
				]
			);

			++$order;
		}

		die( '0' );
	}

	/** END UPLOAD */

	/** BEGIN OTHER FIELDS */

	/**
	 * Check field color.
	 *
	 * @return void
	 */
	public function check_field_color() {
		if ( $this->has_field( 'color' ) && self::is_edit_page() ) {
			wp_enqueue_style( 'farbtastic' ); // Enqueue built-in script and style for color picker.
			wp_enqueue_script( 'farbtastic' );
		}
	}

	/**
	 * Check field date.
	 *
	 * @return void
	 */
	public function check_field_date() {
		if ( $this->has_field( 'date' ) && self::is_edit_page() ) {
			wp_enqueue_style( 'tribe-jquery-ui-css' );
			wp_enqueue_script( 'tribe-jquery-ui' );
		}
	}

	/**
	 * Check field time.
	 *
	 * @return void
	 */
	public function check_field_time() {
		if ( $this->has_field( 'time' ) && self::is_edit_page() ) {
			// Add style and script, use proper jQuery UI version.
			wp_enqueue_style( 'tribe-jquery-ui-css' );
			wp_enqueue_script( 'tribe-jquery-ui' );
			wp_enqueue_script( 'tribe-timepicker' );
		}
	}

	/* END OTHER FIELDS */

	/* BEGIN META BOX PAGE */

	/**
	 * Add meta box for multiple post types.
	 *
	 * @return void
	 */
	public function add() {
		foreach ( (array) $this->meta_box['pages'] as $page ) {
			add_meta_box( $this->meta_box['id'], $this->meta_box['title'], [ $this, 'show' ], $page, $this->meta_box['context'], $this->meta_box['priority'] );
		}
	}

	/**
	 * Callback function to show fields in meta box.
	 *
	 * @return void
	 */
	public function show() {
		global $post;

		wp_nonce_field( basename( __FILE__ ), 'tribe_meta_box_nonce' );
		echo '<table class="form-table tribe-meta">';

		foreach ( $this->fields as $field ) {
			$meta = $this->retrieve_meta_for_field( $field, $post );
			echo '<tr>';
			// Call separated methods for displaying each type of field.
			call_user_func( [ $this, 'show_field_' . $field['type'] ], $field, $meta );
			echo '</tr>';
		}
		echo '</table>';
	}

	/**
	 * Retrieve meta for field.
	 *
	 * @param array   $field The field.
	 * @param WP_Post $post  The post.
	 *
	 * @return mixed
	 */
	public function retrieve_meta_for_field( $field, $post ) {
		$meta = get_post_meta( $post->ID, $field['meta'], ! $field['multiple'] );
		$meta = ! empty( $meta ) ? $meta : $field['std'];
		$meta = ( is_array( $meta ) ) ? self::array_map_deep( 'esc_attr', $meta ) : esc_attr( $meta );
		return $meta;
	}

	/**
	 * Array map deep.
	 *
	 * @param callable $callback The callback.
	 * @param array    $data     The data.
	 *
	 * @return array
	 */
	public function array_map_deep( $callback, $data ) {
		$results = [];
		$args    = [];
		if ( func_num_args() > 2 ) {
			$args = (array) array_shift( array_slice( func_get_args(), 2 ) );
		}

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				array_unshift( $args, $value );
				array_unshift( $args, $callback );
				$results[ $key ] = call_user_func_array( [ 'self', 'array_map_deep' ], $args );
			} else {
				array_unshift( $args, $value );
				$results[ $key ] = call_user_func_array( $callback, $args );
			}
		}

		return $results;
	}

	/** END META BOX PAGE */

	/** BEGIN META BOX FIELDS */

	/**
	 * Show field begin.
	 *
	 * @param array $field The field.
	 *
	 * @return void
	 */
	public function show_field_begin( $field ) {
		if ( isset( $field['span'] ) && 'full' === $field['span'] ) {
			echo '<td colspan="2" class="full-span ' . esc_attr( $field['type'] ) . '"><label for="' . esc_attr( $field['meta'] ) . '">' . esc_html( $field['name'] ) . '</label>';
		} else {
			echo '<th scope="row" class="label-row"><label for="' . esc_attr( $field['meta'] ) . '">' . esc_html( $field['name'] ) . '</label></th><td class="' . esc_attr( $field['type'] ) . '">';
		}
	}

	/**
	 * Show field end.
	 *
	 * @param array $field The field.
	 *
	 * @return void
	 */
	public function show_field_end( $field ) {
		if ( isset( $field['desc'] ) ) {
			echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
		}
		echo '</td>';
	}

	/**
	 * Show field text.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_text( $field, $meta ) {
		$this->show_field_begin( $field );
		echo '<input type="text" class="tribe-text" name="' . esc_attr( $field['meta'] ) . '" id="' . esc_attr( $field['meta'] ) . '" value="' . esc_attr( $meta ) . '" size="30" />';
		$this->show_field_end( $field );
	}

	/**
	 * Show field textarea.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_textarea( $field, $meta ) {
		$this->show_field_begin( $field );
		echo '<textarea class="tribe-textarea large-text" name="' . esc_attr( $field['meta'] ) . '" id="' . esc_attr( $field['meta'] ) . '" cols="60" rows="10">' . esc_textarea( $meta ) . '</textarea>';
		$this->show_field_end( $field );
	}

	/**
	 * Show field select.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_select( $field, $meta ) {
		if ( ! is_array( $meta ) ) {
			$meta = (array) $meta;
		}

		$this->show_field_begin( $field );

		// Define the variables first to keep the HTML clean.
		$meta_key       = $field['meta'];
		$is_multiple    = $field['multiple'] ? 'multiple="multiple"' : '';
		$name_attribute = $meta_key . ( $field['multiple'] ? '[]' : '' );

		$html = '<select class="tribe-select" name="' . esc_attr( $name_attribute ) . '" id="' . esc_attr( $meta_key ) . '" ' . esc_attr( $is_multiple ) . '>';

		echo wp_kses_post( $html );

		foreach ( $field['options'] as $key => $value ) {
			$is_selected  = selected( in_array( $key, $meta ), true, false );
			$option_value = $key;
			$option_text  = $value;

			$option_html = '<option value="' . esc_attr( $option_value ) . '"' . esc_attr( $is_selected ) . '>' . esc_html( $option_text ) . '</option>';

			echo wp_kses_post( $option_html );
		}

		echo '</select>';

		$this->show_field_end( $field );
	}

	/**
	 * Show field radio.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_radio( $field, $meta ) {
		$this->show_field_begin( $field );
		foreach ( $field['options'] as $key => $value ) {
			echo '<input type="radio" class="tribe-radio" name="' . esc_attr( $field['meta'] ) . '" value="' . esc_attr( $key ) . '"' . checked( $meta, $key, false ) . ' /> ' . esc_html( $value ) . ' ';
		}
		$this->show_field_end( $field );
	}

	/**
	 * Show field checkbox.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_checkbox( $field, $meta ) {
		$this->show_field_begin( $field );

		echo '<label><input type="checkbox" class="tribe-checkbox" name="' . esc_attr( $field['meta'] ) . '" id="' . esc_attr( $field['meta'] ) . '"' . checked( ! empty( $meta ), true, false ) . ' /> ' . esc_html( $field['desc'] ) . '</label></td>';
	}

	/**
	 * Show field wysiwyg.
	 *
	 * @param array $field The field.
	 *
	 * @return void
	 */
	public function show_field_wysiwyg( $field ) {
		$this->show_field_begin( $field );

		$content  = get_post_meta( get_the_ID(), $field['meta'], true );
		$content  = empty( $content ) || ! is_string( $content ) ? '' : $content;
		$settings = [
			'media_buttons' => isset( $field['media_buttons'] ) ? (bool) $field['meta_buttons'] : false,
		];
		wp_editor( $content, $field['meta'], $settings );

		$this->show_field_end( $field );
	}

	/**
	 * Show field file.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_file( $field, $meta ) {
		global $post;

		if ( ! is_array( $meta ) ) {
			$meta = (array) $meta;
		}

		$this->show_field_begin( $field );
		if ( isset( $field['desc'] ) ) {
			echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
		}

		if ( ! empty( $meta ) ) {
			$nonce = wp_create_nonce( 'tribe_ajax_delete' );
			echo '<div style="margin-bottom: 10px"><strong>' . esc_html__( 'Uploaded files', 'advanced-post-manager' ) . '</strong></div>';
			echo '<ol class="tribe-upload">';
			foreach ( $meta as $att ) {
				echo '<li>' . wp_get_attachment_link( $att, '', false, false, ' ' ) . ' (<a class="tribe-delete-file" href="#" rel="' . esc_attr( $nonce ) . '|' . esc_attr( $post->ID ) . '|' . esc_attr( $field['meta'] ) . '|' . esc_attr( $att ) . '">' . esc_html__( 'Delete', 'advanced-post-manager' ) . '</a>)</li>';
			}
			echo '</ol>';
		}

		// Show form upload.
		$upload_text = esc_html__( 'Upload new files', 'advanced-post-manager' );
		$add_text    = esc_html__( 'Add another file', 'advanced-post-manager' );
		$field_name  = esc_attr( $field['meta'] );

		$output =
		'<div style="clear: both"><strong>' . esc_html( $upload_text ) . '</strong></div>
		<div class="new-files">
			<div class="file-input"><input type="file" name="' . esc_attr( $field_name ) . '[]" /></div>
			<a class="tribe-add-file" href="#">' . esc_html( $add_text ) . '</a>
		</div>
		</td>';

		echo wp_kses_post( $output );
	}

	/**
	 * Show field image.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_image( $field, $meta ) {
		global $wpdb, $post;

		if ( ! is_array( $meta ) ) {
			$meta = (array) $meta;
		}

		$this->show_field_begin( $field );
		if ( isset( $field['desc'] ) ) {
			echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
		}

		$nonce_delete = wp_create_nonce( 'tribe_ajax_delete' );
		$nonce_sort   = wp_create_nonce( 'tribe_ajax_reorder' );

		echo '<input type="hidden" class="tribe-images-data" value="' . esc_attr( $post->ID ) . '|' . esc_attr( $field['meta'] ) . '|' . esc_attr( $nonce_sort ) . '" />
				<ul class="tribe-images tribe-upload" id="tribe-images-' . esc_attr( $field['meta'] ) . '">';

		// Re-arrange images with 'menu_order', thanks Onur!
		if ( ! empty( $meta ) ) {
			$meta = implode( ',', $meta );

			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$images = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts
					WHERE post_type = 'attachment'
					AND ID in (%s)
					ORDER BY menu_order ASC",
					$meta
				)
			);

			foreach ( $images as $image ) {
				$src          = wp_get_attachment_image_src( $image );
				$src          = $src[0] ?? '';
				$delete_title = esc_attr__( 'Delete this image', 'advanced-post-manager' );
				$delete_link  = esc_html__( 'Delete', 'advanced-post-manager' );
				$post_id      = $post->ID ?? '';
				$meta_key     = $field['meta'] ?? '';

				$li_html =
				'<li id="item_' . esc_attr( $image ) . '">
					<img src="' . esc_url( $src ) . '" />
					<a title="' . esc_attr( $delete_title ) . '" class="tribe-delete-file" href="#" rel="' . esc_attr( $nonce_delete ) . '|' . esc_attr( $post_id ) . '|' . esc_attr( $meta_key ) . '|' . esc_attr( $image ) . '">' . esc_html( $delete_link ) . '</a>
					<input type="hidden" name="' . esc_attr( $meta_key ) . '[]" value="' . esc_attr( $image ) . '" />
				</li>';

				echo wp_kses_post( $li_html );
			}
		}
		echo '</ul>';

		echo '<a href="#" class="tribe-upload-button button" rel="' . esc_attr( $post->ID ) . '|' . esc_attr( $field['meta'] ) . '">' . esc_html__( 'Add more images', 'advanced-post-manager' ) . '</a>';
		echo '</td>';
	}

	/**
	 * Show field color.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_color( $field, $meta ) {
		if ( empty( $meta ) ) {
			$meta = '#';
		}

		$this->show_field_begin( $field );

		$meta_name    = esc_attr( $field['meta'] );
		$meta_value   = esc_attr( $meta );
		$button_label = esc_html__( 'Select a color', 'advanced-post-manager' );

		$html =
		'<input class="tribe-color" type="text" name="' . esc_attr( $meta_name ) . '" id="' . esc_attr( $meta_name ) . '" value="' . esc_attr( $meta_value ) . '" size="8" />
		<a href="#" class="tribe-color-select" rel="' . esc_attr( $meta_name ) . '">' . esc_html( $button_label ) . '</a>
		<div style="display:none" class="tribe-color-picker" rel="' . esc_attr( $meta_name ) . '"></div>';

		echo wp_kses_post( $html );

		$this->show_field_end( $field );
	}

	/**
	 * Show field checkbox list.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_checkbox_list( $field, $meta ) {
		if ( ! is_array( $meta ) ) {
			$meta = (array) $meta;
		}

		$this->show_field_begin( $field );

		$html = [];
		foreach ( $field['options'] as $key => $value ) {
			$html[] = '<input type="checkbox" class="tribe-checkbox_list" name="' . esc_attr( $field['meta'] ) . '[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $meta ), true, false ) . ' /> ' . esc_html( $value );
		}

		echo wp_kses_post( implode( '<br />', $html ) );

		$this->show_field_end( $field );
	}

	/**
	 * Show field date.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_date( $field, $meta ) {
		$this->show_field_begin( $field );
		echo '<input type="text" class="tribe-date" name="' . esc_attr( $field['meta'] ) . '" id="' . esc_attr( $field['meta'] ) . '" rel="' . esc_attr( $field['format'] ) . '" value="' . esc_attr( $meta ) . '" size="30" />';
		$this->show_field_end( $field );
	}

	/**
	 * Show field time.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_time( $field, $meta ) {
		$this->show_field_begin( $field );
		echo '<input type="text" class="tribe-time" name="' . esc_attr( $field['meta'] ) . '" id="' . esc_attr( $field['meta'] ) . '" rel="' . esc_attr( $field['format'] ) . '" value="' . esc_attr( $meta ) . '" size="30" />';
		$this->show_field_end( $field );
	}

	/**
	 * Show field text multi.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_text_multi( $field, $meta ) {
		$this->show_field_begin( $field );

		$meta        = (array) $meta;
		$hide_remove = ( count( $meta ) < 2 ) ? ' hide-remove' : '';
		$size        = floor( 36 / count( $field['ids'] ) );

		echo '<div class="tribe-multi-text-wrap' . esc_attr( $hide_remove ) . '">';
		foreach ( $meta as $v ) {
			echo '<div class="tribe-multi-text">';
			foreach ( $field['ids'] as $key => $id ) {
				$val  = ( isset( $v[ $id ] ) ) ? $v[ $id ] : '';
				$name = "{$field['meta']}[{$id}][]";
				$ph   = $field['placeholders'][ $key ];
				echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" size="' . esc_attr( $size ) . '" placeholder="' . esc_attr( $ph ) . '" /> ';
			}
			echo '<a class="tribe-add">+</a><a class="tribe-remove">-</a></div>';
		}
		echo '</div>';

		$this->show_field_end( $field );
	}

	/**
	 * Show field html.
	 *
	 * @param array $field The field.
	 *
	 * @return void
	 */
	public function show_field_html( $field ) {
		$this->show_field_begin( $field );

		echo wp_kses_post( $field['html'] );

		$this->show_field_end( $field );
	}

	/**
	 * Show field post2post.
	 *
	 * @param array $field The field.
	 * @param mixed $meta The meta.
	 *
	 * @return void
	 */
	public function show_field_post2post( $field, $meta ) {
		$this->show_field_begin( $field );

		if ( ! isset( $field['dropdown_title'] ) ) {
			$post_type_object        = get_post_type_object( $field['post_type'] );
			$field['dropdown_title'] = sprintf(
				/* translators: %s is the post type singular name. */
				__( 'Select %s', 'advanced-post-manager' ),
				$post_type_object->labels->singular_name
			);
		}

		$this->dropdown_posts(
			[
				'post_type'        => $field['post_type'],
				'show_option_none' => $field['dropdown_title'],
				'name'             => $field['meta'],
				'class'            => 'p2p-drop',
			]
		);

		$list_items         = '';
		$list_item_template = '<li><label><input type="checkbox" name="' . esc_attr( $field['meta'] ) . '[]" value="%s" checked="checked" /> %s</label></li>';
		if ( ! empty( $meta ) ) {
			foreach ( (array) $meta as $post_id ) {
				$p           = get_post( $post_id );
				$list_items .= sprintf( $list_item_template, $p->ID, $p->post_title );
			}
		}

		echo '<ul class="p2p-connected">' . wp_kses_post( $list_items ) . '</ul>';
		$this->show_field_end( $field );
	}

	/** END META BOX FIELDS */

	/** BEGIN META BOX SAVE */

	/**
	 * Save data from meta box.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return int The post ID.
	 */
	public function save( $post_id ) {
		global $post_type;
		$post_type_object = get_post_type_object( $post_type );

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) // Check autosave.
		|| ( ! isset( $_POST['post_ID'] ) || $post_id != $_POST['post_ID'] ) // Check revision.
		|| ( ! in_array( $post_type, $this->meta_box['pages'] ) ) // Check if current post type is supported.
		|| ( ! check_admin_referer( basename( __FILE__ ), 'tribe_meta_box_nonce' ) ) // Verify nonce.
		|| ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) ) { // Check permission.
			return $post_id;
		}

		foreach ( $this->fields as $field ) {
			$name = $field['meta'];
			$type = $field['type'];
			$old  = get_post_meta( $post_id, $name, ! $field['multiple'] );
			$new  = $_POST[ $name ] ?? ( $field['multiple'] ? [] : '' ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// Validate meta value.
			if ( class_exists( 'Tribe_Meta_Box_Validate' ) && method_exists( 'Tribe_Meta_Box_Validate', $field['validate_func'] ) ) {
				$new = call_user_func( [ 'Tribe_Meta_Box_Validate', $field['validate_func'] ], $new );
			}

			// Call defined method to save meta value, if there's no methods, call common one.
			$save_func = 'save_field_' . $type;
			if ( method_exists( $this, $save_func ) ) {
				call_user_func( [ $this, 'save_field_' . $type ], $post_id, $field, $old, $new );
			} else {
				$this->save_field( $post_id, $field, $old, $new );
			}
		}
	}

	/**
	 * Common functions for saving field.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $field The field.
	 * @param mixed $old The old value.
	 * @param mixed $new_val The new value.
	 *
	 * @return void
	 */
	public function save_field( $post_id, $field, $old, $new_val ) {
		$name = $field['meta'];

		delete_post_meta( $post_id, $name );
		if ( $new_val === '' || $new_val === [] ) {
			return;
		}

		if ( $field['multiple'] ) {
			foreach ( $new_val as $add_new ) {
				add_post_meta( $post_id, $name, $add_new, false );
			}
		} else {
			update_post_meta( $post_id, $name, $new_val );
		}
	}

	/**
	 * Save field wysiwyg.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $field The field.
	 * @param mixed $old The old value.
	 * @param mixed $new_val The new value.
	 *
	 * @return void
	 */
	public function save_field_wysiwyg( $post_id, $field, $old, $new_val ) {
		$new_val = wpautop( $new_val );
		$this->save_field( $post_id, $field, $old, $new_val );
	}

	/**
	 * Save field file.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $field The field.
	 *
	 * @return void
	 */
	public function save_field_file( $post_id, $field ) {
		$name  = $field['meta'];
		$files = $_FILES[ $name ] ?? ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

		if ( empty( $files ) ) {
			return;
		}

		self::fix_file_array( $files );

		foreach ( $files as $position => $fileitem ) {
			$file = wp_handle_upload( $fileitem, [ 'test_form' => false ] );

			if ( empty( $file['file'] ) ) {
				continue;
			}

			$filename   = $file['file'];
			$attachment = [
				'post_mime_type' => $file['type'],
				'guid'           => $file['url'],
				'post_parent'    => $post_id,
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content'   => '',
			];

			$id = wp_insert_attachment( $attachment, $filename, $post_id );

			if ( ! is_wp_error( $id ) ) {
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $filename ) );
				add_post_meta( $post_id, $name, $id, false ); // Save file's url in meta fields.
			}
		}
	}

	/**
	 * Save field text multi.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $field The field.
	 * @param mixed $old The old value.
	 * @param mixed $new_val The new value.
	 *
	 * @return void
	 */
	public function save_field_text_multi( $post_id, $field, $old, $new_val ) {
		$data    = [];
		$new_val = (array) $new_val;

		foreach ( $field['ids'] as $id ) {
			foreach ( $new_val[ $id ] as $key => $value ) {
				$data[ $key ][ $id ] = $value;
			}
		}

		if ( ! empty( $data ) ) {
			update_post_meta( $post_id, $field['meta'], $data );
		}
	}

	/**
	 * Save field html.
	 *
	 * @return void
	 */
	public function save_field_html() {
		// Do nothing.
	}

	/**
	 * Save field post2post.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $field The field.
	 * @param mixed $old The old value.
	 * @param mixed $new_val The new value.
	 *
	 * @return void
	 */
	public function save_field_post2post( $post_id, $field, $old, $new_val ) {
		delete_post_meta( $post_id, $field['meta'] );
		$new_val = (array) $new_val;
		$new_val = array_unique( $new_val );

		foreach ( $new_val as $id ) {
			add_post_meta( $post_id, $field['meta'], $id );
		}
	}

	/** END META BOX SAVE */

	/** BEGIN HELPER FUNCTIONS */

	/**
	 * Dropdown posts.
	 *
	 * @param array $args The arguments.
	 * @return string
	 */
	public function dropdown_posts( $args = '' ) {
		$defaults = [
			'numberposts'           => -1,
			'post_type'             => 'post',
			'depth'                 => 0,
			'selected'              => 0,
			'echo'                  => 1,
			'name'                  => 'page_id',
			'id'                    => '',
			'class'                 => '',
			'show_option_none'      => '',
			'show_option_no_change' => '',
			'option_none_value'     => '',
		];

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$get_posts_args = compact( 'post_type', 'numberposts' );
		$pages          = get_posts( $get_posts_args );
		$output         = '';
		$name           = esc_attr( $name );

		// Back-compat with old system where both id and name were based on $name argument.
		if ( empty( $id ) ) {
			$id = $name;
		}

		if ( ! empty( $pages ) ) {
			$output = "<select name=\"$name\" id=\"$id\" class=\"$class\">\n";

			if ( $show_option_no_change ) {
				$output .= "\t<option value=\"-1\">$show_option_no_change</option>";
			}

			if ( $show_option_none ) {
				$output .= "\t<option value=\"" . esc_attr( $option_none_value ) . "\">$show_option_none</option>\n";
			}

			$output .= walk_page_dropdown_tree( $pages, $depth, $r );
			$output .= "</select>\n";
		}

		$output = apply_filters( 'dropdown_posts-' . $post_type, $output ); //phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		if ( $echo ) {
			echo wp_kses_post( $output );
		}

		return $output;
	}

	/**
	 * Add missed values for meta box.
	 *
	 * @return void
	 */
	public function add_missed_values() {
		// Default values for meta box.
		$this->meta_box = array_merge(
			[
				'context'  => 'normal',
				'priority' => 'high',
				'pages'    => [ 'post' ],
			],
			$this->meta_box
		);

		// Default values for fields.
		foreach ( $this->fields as &$field ) {
			$multiple = in_array( $field['type'], [ 'checkbox_list', 'file', 'image' ] );
			$std      = $multiple ? [] : '';
			$format = 'date' == $field['type'] ? 'yy-mm-dd' : ( 'time' == $field['type'] ? 'hh:mm' : '' );

			$field = array_merge(
				[
					'multiple'      => $multiple,
					'std'           => $std,
					'desc'          => '',
					'format'        => $format,
					'validate_func' => '',
				],
				$field
			);
		}
	}

	/**
	 * Check if field with $type exists
	 *
	 * @since 1.7.3
	 *
	 * @param string $type The field type.
	 * @return bool
	 */
	public function has_field( $type ) {
		foreach ( $this->fields as $field ) {
			if ( $type == $field['type'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if current page is edit page
	 *
	 * @since 1.7.3
	 *
	 * @return bool
	 */
	public static function is_edit_page() {
		global $pagenow;
		return in_array( $pagenow, [ 'post.php', 'post-new.php' ] );
	}

	/**
	 * Fixes the odd indexing of multiple file uploads from the format:
	 *    $_FILES['field']['key']['index']
	 * To the more standard and appropriate:
	 *    $_FILES['field']['index']['key']
	 *
	 * @since 1.7.3
	 *
	 * @param array &$files The files array.
	 */
	public static function fix_file_array( &$files ) {
		$output = [];
		foreach ( $files as $key => $list ) {
			foreach ( $list as $index => $value ) {
				$output[ $index ][ $key ] = $value;
			}
		}
		$files = $output;
	}

	/**
	 * Get proper jQuery UI version to not conflict with WP admin scripts
	 *
	 * @return string
	 */
	public static function get_jqueryui_ver() {
		global $wp_version;
		if ( version_compare( $wp_version, '3.5', '>=' ) ) {
			return '1.9.2';
		}

		if ( version_compare( $wp_version, '3.1', '>=' ) ) {
			return '1.8.10';
		}

		return '1.7.3';
	}
	/** END HELPER FUNCTIONS */
}
