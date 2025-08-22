<?php
/**
 * Plugin Name: Advanced Post Manager
 * Description: Dialing custom post types to 11 with advanced filtering controls.
 * Version: 4.5.5
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: The Events Calendar
 * Author URI: https://evnt.is/4n
 * Text Domain: advanced-post-manager
 * License: GPLv2 or later
 * Elementor tested up to: 3.23.1
 * Elementor Pro tested up to: 3.23.0
 */

define( 'TRIBE_APM_PATH', plugin_dir_path( __FILE__ ) );
define( 'TRIBE_APM_FILE', __FILE__ );
define( 'TRIBE_APM_LIB_PATH', TRIBE_APM_PATH . 'lib/' );

// Load the required php min version functions.
require_once TRIBE_APM_LIB_PATH . 'php-min-version.php';

/**
 * Verifies if we need to warn the user about min PHP version and bail to avoid fatals.
 */
if ( tribe_is_not_min_php_version() ) {
	tribe_not_php_version_textdomain( 'advanced-post-manager', TRIBE_APM_FILE );
	/**
	 * Include the plugin name into the correct place.
	 *
	 * @since 4.5.5
	 *
	 * @param array $names current list of names.
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

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
/**
 * A class to manage the plugin.
 */
class Tribe_APM {
	/**
	 * The current version of APM.
	 */
	const VERSION = '4.5.5';

	/**
	 * The textdomain for the plugin.
	 *
	 * @var string
	 */
	protected $textdomain = 'advanced-post-manager';

	/**
	 * The arguments for the plugin.
	 *
	 * @var array
	 */
	protected $args;

	/**
	 * The metaboxes for the plugin.
	 *
	 * @var array
	 */
	protected $metaboxes;

	/**
	 * The URL for the plugin.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * The columns for the plugin.
	 * Holds a Tribe_Columns object.
	 *
	 * @var Tribe_Columns
	 */
	public $columns;

	/**
	 * The filters for the plugin.
	 * Holds a Tribe_Filters object.
	 *
	 * @var Tribe_Filters
	 */
	public $filters;

	/**
	 * The post type for the plugin.
	 *
	 * @var string
	 */
	public $post_type;

	/**
	 * Automatically add filters/cols for registered taxonomies?
	 *
	 * @var bool
	 */
	public $add_taxonomies = true;

	/**
	 * Show export button? (Currently does nothing)
	 *
	 * @var bool
	 */
	public $export = false;

	/**
	 * Show metaboxes?
	 *
	 * @var bool
	 */
	public $do_metaboxes = true;

	/**
	 * Whether we're in a delayed initialization.
	 *
	 * @var bool
	 */
	public static $delayed_init = false;

	// CONSTRUCTOR.

	/**
	 * Kicks things off
	 *
	 * @param string $post_type What post_type to enable filters for.
	 * @param array  $args      Multidimensional array of filter/column arrays. See documentation.
	 * @param array  $metaboxes The metaboxes to use.
	 */
	public function __construct( $post_type, $args, $metaboxes = [] ) {
		$this->post_type = $post_type;
		$this->args      = $args;
		$this->metaboxes = $metaboxes;

		$this->textdomain = apply_filters( 'tribe_apm_textdomain', $this->textdomain );
		$this->url        = apply_filters( 'tribe_apm_url', plugins_url( '', __FILE__ ), __FILE__ );

		$this->register_active_plugin();
		$this->register_hooks();

		// Check if we need to delay initialization for screen availability.
		if ( ! is_admin() ) {
			// Not admin, bail.
			return;
		} elseif ( ! wp_doing_ajax() && ! get_current_screen() ) {
			// Screen not available yet, delay until current_screen.
			self::$delayed_init = true;
			add_action( 'current_screen', [ $this, 'delayed_init' ] );
		} else {
			// Screen available or not needed, initialize normally.
			self::$delayed_init = false;
			add_action( 'admin_init', [ $this, 'init' ], 0 );
		}
	}

	// PUBLIC METHODS.

	/**
	 * Register hooks that don't depend on initialization state.
	 *
	 * @since 4.5.5
	 */
	private function register_hooks() {
		// Always-available filter for resource URLs.
		add_filter( 'tribe_apm_resources_url', [ $this, 'resources_url' ] );
	}

	/**
	 * Registers this plugin as being active for other tribe plugins and extensions.
	 *
	 * @return bool Indicates if Tribe Common wants the plugin to run.
	 */
	public function register_active_plugin() {
		if ( ! function_exists( 'tribe_register_plugin' ) ) {
			return true;
		}

		return tribe_register_plugin( TRIBE_APM_FILE, __CLASS__, self::VERSION );
	}

	/**
	 * Add some additional filters/columns.
	 *
	 * @param array $filters Multidimensional array of filter/column arrays.
	 */
	public function add_filters( $filters = [] ) {
		if ( empty( $filters ) ) {
			return;
		}

		if ( ! is_array( $filters ) ) {
			return;
		}

		$this->args = array_merge( $this->args, $filters );
	}

	// CALLBACKS.

	/**
	 * Initialize the filters and columns.
	 */
	public function init() {
		if ( ! $this->is_active() ) {
			return;
		}

		$hook = self::$delayed_init ? 'current_screen' : 'admin_init';

		$this->load_text_domain();

		// Register hooks that depend on successful initialization.
		add_action( $hook, [ $this, 'init_meta_box' ] );
		add_action( 'tribe_cpt_filters_init', [ $this, 'maybe_add_taxonomies' ], 10, 1 );

		do_action( 'tribe_cpt_filters_init', $this );

		require_once TRIBE_APM_LIB_PATH . 'tribe-filters.class.php';
		require_once TRIBE_APM_LIB_PATH . 'tribe-columns.class.php';
		$this->filters = new Tribe_Filters( $this->post_type, $this->get_filter_args() );
		$this->columns = new Tribe_Columns( $this->post_type, $this->get_column_args() );

		do_action( 'tribe_cpt_filters_after_init', $this );

		add_action( 'admin_notices', [ $this, 'maybe_show_filters' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue' ] );
	}

	/**
	 * Delayed initialization when screen is available.
	 *
	 * This method is called when get_current_screen() is not available during admin_init,
	 * which can happen in newer versions of The Events Calendar (6.12.0+).
	 *
	 * @since 4.5.4
	 */
	public function delayed_init() {
		// Remove the hook immediately to prevent multiple calls.
		remove_action( 'current_screen', [ $this, 'delayed_init' ] );

		// Only initialize if we haven't already and we're on the right screen.
		if ( ! $this->is_active() ) {
			return;
		}

		// Check if already initialized (filters/columns objects exist).
		if ( isset( $this->filters ) && isset( $this->columns ) ) {
			return;
		}

		$this->init( true );
	}

	/**
	 * Load the text domain.
	 *
	 * @return void
	 */
	private function load_text_domain() {
		load_plugin_textdomain( 'advanced-post-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
	}

	/**
	 * Get the resources URL.
	 *
	 * @since 4.5.5 Remove unused parameter.
	 *
	 * @return string The resources URL.
	 */
	public function resources_url() {
		return trailingslashit( $this->url ) . 'resources/';
	}

	/**
	 * Initialize the meta box.
	 *
	 * @return void
	 */
	public function init_meta_box() {
		if ( ! $this->do_metaboxes ) {
			return;
		}

		require_once TRIBE_APM_LIB_PATH . 'tribe-meta-box-helper.php';
		$for_meta_box = $this->only_meta_filters( $this->args, 'metabox' );
		new Tribe_Meta_Box_Helper( $this->post_type, $for_meta_box, $this->metaboxes );
	}

	/**
	 * Maybe add taxonomies.
	 *
	 * Dogfooding a bit! We're hooked into the tribe_cpt_filters_init action hook.
	 *
	 * @param Tribe_APM $tribe_apm The Tribe_APM object.
	 *
	 * @return void
	 */
	public function maybe_add_taxonomies( $tribe_apm ) {
		if ( ! $tribe_apm->add_taxonomies ) {
			return;
		}

		$args       = [];
		$taxonomies = apply_filters( 'tribe_apm_taxonomies', get_taxonomies( [], 'objects' ), $this->post_type );
		foreach ( $taxonomies as $tax ) {
			if ( $tax->show_ui && in_array( $tribe_apm->post_type, (array) $tax->object_type, true ) ) {
				$args[ 'taxonomy-' . $tax->name ] = [
					'name'       => $tax->labels->name,
					'taxonomy'   => $tax->name,
					'query_type' => 'taxonomy',
				];
			}
		}

		$tribe_apm->add_filters( $args );
	}

	/**
	 * Maybe enqueue the scripts and styles.
	 *
	 * @since 4.5.5 Remove unused parameter.
	 *
	 * @return void
	 */
	public function maybe_enqueue() {
		if ( ! $this->is_active() ) {
			return;
		}

		wp_enqueue_script(
			'tribe-fac',
			$this->url . '/resources/tribe-apm.js',
			[ 'jquery' ],
			self::VERSION,
			true
		);

		wp_enqueue_style(
			'tribe-fac',
			$this->url . '/resources/tribe-apm.css',
			[],
			self::VERSION,
			'all'
		);
	}

	/**
	 * Maybe show the filters.
	 *
	 * @return void
	 */
	public function maybe_show_filters() {
		if ( ! $this->is_active() ) {
			return;
		}

		require_once 'views/edit-filters.php';
	}

	// UTILITIES AND INTERNAL METHODS.

	/**
	 * Get the filter arguments.
	 *
	 * @return array The filter arguments.
	 */
	protected function get_filter_args() {
		return $this->filter_disabled( $this->args, 'filters' );
	}

	/**
	 * Get the column arguments.
	 *
	 * @return array The column arguments.
	 */
	protected function get_column_args() {
		return $this->filter_disabled( $this->args, 'columns' );
	}

	/**
	 * Filter out an array of args where children arrays have a disable key set to $type.
	 *
	 * @param array        $args Multidimensional array of arrays.
	 * @param string|array $type Value(s) of filter key to remove.
	 *
	 * @return array Filtered array.
	 */
	protected function filter_disabled( $args, $type ) {
		return $this->filter_on_key_value( $args, $type, 'disable' );
	}

	/**
	 * Filter on key value.
	 *
	 * @param array  $args      Multidimensional array of arrays.
	 * @param string $type      Value(s) of filter key to remove.
	 * @param string $filterkey The key to filter on.
	 *
	 * @return array Filtered array.
	 */
	protected function filter_on_key_value( $args, $type, $filterkey ) {
		foreach ( $args as $key => $value ) {
			if ( isset( $value[ $filterkey ] ) && in_array( $type, (array) $value[ $filterkey ] ) ) {
				unset( $args[ $key ] );
			}
		}

		return $args;
	}

	/**
	 * Only meta filters.
	 *
	 * @param array $args The arguments.
	 *
	 * @return array The filtered arguments.
	 */
	protected function only_meta_filters( $args ) {
		foreach ( $args as $k => $v ) {
			if ( ! isset( $v['meta'] ) ) {
				unset( $args[ $k ] );
			}
		}
		return $this->filter_disabled( $args, 'metabox' );
	}

	/**
	 * Check if the plugin is active.
	 *
	 * @return bool Whether the plugin is active.
	 */
	protected function is_active() {
		$desired_screen = 'edit-' . $this->post_type;

		// Exit early on autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		// Inline save?
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['screen'] ) && $desired_screen === $_POST['screen'] ) {
			return true;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			global $pagenow;
			if ( 'edit.php' === $pagenow ) {
				if ( isset( $_GET['post_type'] ) && $this->post_type === $_GET['post_type'] ) {
					return true;
				}

				if ( 'post' === $this->post_type ) {
					return true;
				}

				return false;
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

		if ( is_object( $screen ) && isset( $screen->id ) ) {
			return $desired_screen === $screen->id;
		}

		return false;
	}

	/**
	 * Log data.
	 *
	 * @param array $data The data to log.
	 *
	 * @return void
	 */
	public function log( $data ) {
		error_log( print_r( $data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}
}

require_once 'lib/template-tags.php';
