<?php
/*
 Plugin Name: Advanced Post Manager
 Description: Dialing custom post types to 11 with advanced filtering controls.
 Version: 4.5
 Author: Modern Tribe, Inc.
 Author URI: http://m.tri.be/4n
 Text Domain: advanced-post-manager
 */

define( 'TRIBE_APM_PATH', plugin_dir_path( __FILE__ ) );
define( 'TRIBE_APM_FILE', __FILE__ );
define( 'TRIBE_APM_LIB_PATH', TRIBE_APM_PATH . 'lib/' );

// Load the required php min version functions
require_once TRIBE_APM_LIB_PATH . 'php-min-version.php';

/**
 * Verifies if we need to warn the user about min PHP version and bail to avoid fatals
 */
if ( tribe_is_not_min_php_version() ) {
	tribe_not_php_version_textdomain( 'advanced-post-manager', TRIBE_APM_FILE );
	/**
	 * Include the plugin name into the correct place
	 *
	 * @since  TBD
	 *
	 * @param  array $names current list of names
	 *
	 * @return array
	 */
	function tribe_apm_not_php_version_plugin_name( $names ) {
		$names['tribe-apm'] = esc_html__( 'Advanced Post Manager', 'advanced-post-manager' );
		return $names;
	}
	add_filter( 'tribe_not_php_version_names', 'tribe_apm_not_php_version_plugin_name' );
	if ( ! has_filter( 'admin_notices', 'tribe_not_php_version_notice' ) ) {
		add_action( 'admin_notices', 'tribe_not_php_version_notice' );
	}
	return false;
}


class Tribe_APM {
	/**
	 * The current version of iCal Importer
	 */
	const VERSION = '4.5';

	protected $textdomain = 'advanced-post-manager';
	protected $args;
	protected $metaboxes;
	protected $url;

	public $columns; // holds a Tribe_Columns object
	public $filters; // holds a Tribe_Filters object

	public $post_type;
	public $add_taxonomies = true; // Automatically add filters/cols for registered taxonomies?
	public $do_metaboxes = true;
	public $export = false; // Show export button? (Currently does nothing)

	// CONSTRUCTOR

	/**
	 * Kicks things off
	 * @param $post_type What post_type to enable filters for
	 * @param $args array multidimensional array of filter/column arrays. See documentation
	 */
	public function __construct( $post_type, $args, $metaboxes = array() ) {
		$this->post_type = $post_type;
		$this->args = $args;
		$this->metaboxes = $metaboxes;

		$this->textdomain = apply_filters( 'tribe_apm_textdomain', $this->textdomain );
		$this->url = apply_filters( 'tribe_apm_url', plugins_url( '', __FILE__ ), __FILE__ );

		$this->register_active_plugin();

		add_action( 'admin_init', array( $this, 'init' ), 0 );
		add_action( 'admin_init', array( $this, 'init_meta_box' ) );
		add_action( 'tribe_cpt_filters_init', array( $this, 'maybe_add_taxonomies' ), 10, 1 );
		add_filter( 'tribe_apm_resources_url', array( $this, 'resources_url' ) );
	}

	// PUBLIC METHODS

	/**
	 * Registers this plugin as being active for other tribe plugins and extensions
	 *
	 * @return bool Indicates if Tribe Common wants the plugin to run
	 */
	public function register_active_plugin() {
		if ( ! function_exists( 'tribe_register_plugin' ) ) {
			return true;
		}

		return tribe_register_plugin( TRIBE_APM_FILE, __CLASS__, self::VERSION );
	}

	/**
	 * Add some additional filters/columns
	 *
	 * @param $filters multidimensional array of filter/column arrays
	 */
	public function add_filters( $filters = array() ) {
		if ( is_array( $filters ) && ! empty( $filters ) ) {
			$this->args = array_merge( $this->args, $filters );
		}
	}

	// CALLBACKS

	public function init() {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->load_text_domain();

		do_action( 'tribe_cpt_filters_init', $this );

		require_once TRIBE_APM_LIB_PATH . 'tribe-filters.class.php';
		require_once TRIBE_APM_LIB_PATH . 'tribe-columns.class.php';
		$this->filters = new Tribe_Filters( $this->post_type, $this->get_filter_args() );
		$this->columns = new Tribe_Columns( $this->post_type, $this->get_column_args() );

		do_action( 'tribe_cpt_filters_after_init', $this );

		add_action( 'admin_notices', array( $this, 'maybe_show_filters' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	private function load_text_domain() {
		load_plugin_textdomain( 'advanced-post-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
	}//end load_text_domain

	public function resources_url( $unused_resource_url ) {
		return trailingslashit( $this->url ) . 'resources/';
	}

	public function init_meta_box() {
		if ( ! $this->do_metaboxes )
			return;
		require_once TRIBE_APM_LIB_PATH . 'tribe-meta-box-helper.php';
		$for_meta_box = $this->only_meta_filters( $this->args, 'metabox' );
		new Tribe_Meta_Box_Helper( $this->post_type, $for_meta_box, $this->metaboxes );
	}

	// Dogfooding a bit! We're hooked into the tribe_cpt_filters_init action hook
	public function maybe_add_taxonomies( $tribe_cpt_filters ) {
		if ( ! $tribe_cpt_filters->add_taxonomies ) return;
		$args = array();
		$taxonomies = apply_filters( 'tribe_apm_taxonomies', get_taxonomies( array(), 'objects' ), $this->post_type );
		foreach ( $taxonomies as $tax ) {
			if ( $tax->show_ui && in_array( $tribe_cpt_filters->post_type, (array) $tax->object_type, true ) ) {
				$args[ 'taxonomy-'.$tax->name ] = array(
					'name'       => $tax->labels->name,
					'taxonomy'   => $tax->name,
					'query_type' => 'taxonomy',
				);
			}
		}

		$tribe_cpt_filters->add_filters( $args );
	}

	public function maybe_enqueue( $unused_blah ) {
		if ( $this->is_active() ) {
			wp_enqueue_script( 'tribe-fac', $this->url . '/resources/tribe-apm.js', array( 'jquery' ) );
			wp_enqueue_style( 'tribe-fac', $this->url . '/resources/tribe-apm.css', array() );
		}
	}

	public function maybe_show_filters() {
		if ( $this->is_active() ) {
			include 'views/edit-filters.php';
		}
	}

	// UTLITIES AND INTERNAL METHODS

	protected function get_filter_args() {
		return $this->filter_disabled( $this->args, 'filters' );
	}

	protected function get_column_args() {
		return $this->filter_disabled( $this->args, 'columns' );
	}

	/**
	 * Filter out an array of args where children arrays have a disable key set to $type
	 *
	 * @param $args array Multidimensional array of arrays
	 * @param $type string|array Value(s) of filter key to remove
	 * @return array Filtered array
	 */
	protected function filter_disabled( $args, $type ) {
		return $this->filter_on_key_value( $args, $type, 'disable' );
	}

	protected function filter_on_key_value( $args, $type, $filterkey ) {
		foreach ( $args as $key => $value ) {
			if ( isset( $value[ $filterkey ] ) && in_array( $type, (array) $value[ $filterkey ] ) ) {
				unset( $args[ $key ] );
			}
		}
		return $args;
	}

	protected function only_meta_filters( $args ) {
		foreach ( $args as $k => $v ) {
			if ( ! isset( $v['meta'] ) ) {
				unset( $args[ $k ] );
			}
		}
		return $this->filter_disabled( $args, 'metabox' );
	}

	protected function is_active() {
		$desired_screen = 'edit-' . $this->post_type;

		// Exit early on autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Inline save?
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['screen'] ) && $desired_screen === $_POST['screen'] ) {
			return true;
		}

		if ( ! $screen = get_current_screen() ) {
			global $pagenow;
			if ( 'edit.php' === $pagenow ) {
				if ( isset( $_GET['post_type'] ) && $this->post_type === $_GET['post_type'] ) {
					return true;
				}
				elseif ( 'post' === $this->post_type ) {
					return true;
				}
				return false;
			}
		}
		if ( is_object( $screen ) && isset( $screen->id ) ) {
			return $desired_screen === $screen->id;
		} else {
			return false;
		}
	}

	public function log( $data ) {
		error_log( print_r( $data, true ) );
	}
}

include 'lib/template-tags.php';
