<?php
/**
 * A class for providing WordPress filters in a manage "posts" view.
 */

if ( class_exists( 'Tribe_Filters' ) ) {
	return;
}

/**
 * Class Tribe_Filters
 */
class Tribe_Filters {

	/**
	 * The post type for storing filtersets.
	 *
	 * @var string
	 */
	const FILTER_POST_TYPE = 'tribe_filters';

	/**
	 * The meta key for storing the post type.
	 *
	 * @var string
	 */
	const FILTER_META = '_post_type';

	/**
	 * The post type that is being filtered.
	 *
	 * @var string
	 */
	private $filtered_post_type;

	/**
	 * The filters that are available.
	 *
	 * @var array
	 */
	private $filters = [];

	/**
	 * The active filters.
	 *
	 * @var array
	 */
	private $active = [];

	/**
	 * The inactive filters.
	 *
	 * @var array
	 */
	private $inactive = [];

	/**
	 * The order by cast.
	 *
	 * @var string
	 */
	private $orderby_cast;

	/**
	 * The URL for the filters.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * The nonce for the filters.
	 *
	 * @var string
	 */
	private $nonce = '_tribe_filters';

	/**
	 * The prefix for the filters.
	 *
	 * @var string
	 */
	private $prefix = 'tribe_filters_';

	/**
	 * The is prefix.
	 *
	 * @var string
	 */
	private $is_pre;

	/**
	 * The value prefix.
	 *
	 * @var string
	 */
	private $val_pre;

	/**
	 * The query options.
	 *
	 * @var array
	 */
	private $query_options = [];

	/**
	 * The query options map.
	 * Turned into SQL comparison operators
	 *
	 * @var array
	 */
	private $query_options_map = [
		'is'   => '=',
		'not'  => '!=',
		'gt'   => '>',
		'lt'   => '<',
		'gte'  => '>=',
		'lte'  => '<=',
		'like' => 'LIKE',
	];

	/**
	 * The query search options.
	 *
	 * @var array
	 */
	private $query_search_options = [];

	/**
	 * The sortby.
	 *
	 * @var array
	 */
	private $sortby = [];

	/**
	 * The filters example.
	 *
	 * @var array
	 */
	private $filters_example = [];

	/**
	 * The active example.
	 *
	 * @var array
	 */
	private $active_example = [];

	/**
	 * Saved active filters.
	 *
	 * @var object
	 */
	private $saved_active;

	/**
	 * Constructor function is critical.
	 *
	 * @param string $post_type The post type to be filtered.
	 * @param array  $filters   A multidimensional array of available filters with named keys and options for how to query them.
	 */
	public function __construct( $post_type, $filters = [] ) {

		$this->query_options = [
			'is'  => __( 'Is', 'advanced-post-manager' ),
			'not' => __( 'Is Not', 'advanced-post-manager' ),
			'gt'  => '>',
			'lt'  => '<',
			'gte' => '>=',
			'lte' => '<=',
		];

		$this->query_search_options = array_merge(
			$this->query_options_map,
			[
				'like' => __( 'Search', 'advanced-post-manager' ),
				'is'   => __( 'Is', 'advanced-post-manager' ),
				'not'  => __( 'Is Not', 'advanced-post-manager' ),
			]
		);

		$this->filters_example = [
			'filter_key' => [
				'name'     => __( 'Member Type', 'advanced-post-manager' ), // Text label.
				'meta'     => '_type', // The meta key to query.
				'taxonomy' => 'some_taxonomy', // The taxonomy to query. Would never be set alongside meta above.
				'options'  => [ // Options for a meta query. Restricts them.
					'cafe'   => __( 'Cafe', 'advanced-post-manager' ),
					'desk'   => __( 'Private Desk', 'advanced-post-manager' ),
					'office' => __( 'Office', 'advanced-post-manager' ),
				],
			],
		];

		$this->active_example = [
			'filter_key' => [ // Array key corresponds to key in $filters.
				'value'        => __( 'what i’m querying. probably a key in the options array in $filters', 'advanced-post-manager' ),
				'query_option' => 'is/is not,etc.',
			],
		];

		$this->filtered_post_type = $post_type;
		$this->set_filters( $filters );

		$this->url = trailingslashit( plugins_url( '', __FILE__ ) );
		$this->add_actions_and_filters();

		$this->is_pre  = $this->prefix . 'is_';
		$this->val_pre = $this->prefix . 'val_';
	}

	// PUBLIC API METHODS.

	/**
	 * Set Filters with an array of filter arrays.
	 *
	 * See documentation for the paramaters of a filter array.
	 *
	 * @param array $filters A multidimensional array of available filters with named keys and options for how to query them.
	 */
	public function set_filters( $filters = [] ) {
		if ( ! empty( $filters ) ) {
			$this->filters = $filters;
		}

		$this->alphabetize_filters();
	}

	/**
	 * Get array of currently set filters.
	 *
	 * @return array filters
	 */
	public function get_filters() {
		return $this->filters;
	}

	/**
	 * Set active filter state.
	 *
	 * Only use this to specifically set a particular set of filters that shouldn't be changed, as this will override filters set by the UI
	 *
	 * @param array $active A multidimensional array.
	 *
	 * @see $active_example
	 */
	public function set_active( $active = null ) {
		if ( empty( $active ) ) {
			$this->log( 'set_active: empty active' );
			return;
		}

		$this->active = (array) $active;
		$this->cache_last_query( $active );
	}

	/**
	 * Merges a new active array into current active array.
	 *
	 * @param array $new_active A multidimensional array.
	 *
	 * @see $active_example
	 */
	public function add_active( $new_active = [] ) {
		$new_active   = (array) $new_active;
		$this->active = array_merge( $this->active, $new_active );
	}

	/**
	 * Get currently active filters
	 *
	 * @return array current active filters array.
	 */
	public function get_active() {
		return $this->active;
	}

	/**
	 * Outputs the drag & drop columns view.
	 * Expects to be inside a form
	 */
	public function output_form() {
		wp_nonce_field( $this->nonce, $this->nonce, false );

		$this->saved_filters_dropdown();

		$this->inactive = array_diff_key( $this->filters, $this->active );

		echo '<div class="apm-inactive-filters">';

		$this->active_filters_table();

		$this->inactive_dropdown();

		echo '</div>';

		$this->form_js();
	}

	/**
	 * Active filters table.
	 *
	 * @since TBD
	 */
	public function active_filters_table(): void {
		echo '<h4>' . esc_html__( 'Active Filters', 'advanced-post-manager' ) . '</h4>';
		echo '<table id="tribe-filters-active" class="table-form">';
		foreach ( $this->active as $k => $v ) {
			echo $this->table_row( $k, $v ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,StellarWP.XSS.EscapeOutput.OutputNotEscaped
		}
		echo '</table>';
	}
	/** CALLBACKS. */

	/**
	 * Add actions and filters.
	 */
	protected function add_actions_and_filters() {
		// We need to add actions and filters on the current screen hook if we're in a delayed initialization.
		$hook = Tribe_APM::$delayed_init ? 'current_screen' : 'admin_init';

		add_action( $hook, [ $this, 'init_active' ], 10 );
		add_action( $hook, [ $this, 'save_active' ], 20 );
		add_action( $hook, [ $this, 'update_or_delete_saved_filters' ], 21 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'load-edit.php', [ $this, 'add_query_filters' ], 30 );
		add_action( $hook, [ $this, 'register_post_type' ] );
		add_filter( 'admin_body_class', [ $this, 'add_body_class' ] );
		add_action( 'tribe_after_parse_query', [ $this, 'maybe_cast_for_ordering' ], 10, 2 );
		add_action( 'tribe_after_parse_query', [ $this, 'add_cast_helpers' ] );
		add_filter( 'tribe_filter_input_class', [ $this, 'input_date_class' ], 10, 2 );
		add_filter( 'tribe_query_options', [ $this, 'input_date_options' ], 10, 3 );
	}

	/**
	 * Input date options.
	 *
	 * @param array  $options The options.
	 * @param string $key     The key.
	 * @param array  $filter  The filter.
	 *
	 * @return array The options.
	 */
	public function input_date_options( $options, $key, $filter ) {
		if ( self::is_date( $filter ) && isset( $options['like'] ) ) {
			unset( $options['like'] );
		}
		return $options;
	}

	/**
	 * Input date class.
	 *
	 * @param string $current_class  The class.
	 * @param array  $filter The filter.
	 *
	 * @return string The class.
	 */
	public function input_date_class( $current_class, $filter ) {
		if ( self::is_date( $filter ) ) {
			return 'date tribe-datepicker';
		}

		return $current_class;
	}

	/**
	 * Add cast helpers.
	 */
	public function add_cast_helpers() {
		add_filter( 'posts_request', [ $this, 'help_decimal_cast' ], 10, 1 );
	}

	/**
	 * Maybe cast for ordering.
	 *
	 * @param WP_Query $wp_query The WP_Query object.
	 *
	 * @since TBD Removed unused $active parameter.
	 */
	public function maybe_cast_for_ordering( $wp_query ) {
		// Only if it's sorting on meta.
		if ( 'meta_value' !== $wp_query->get( 'orderby' ) ) {
			return;
		}
		$meta_key = $wp_query->get( 'meta_key' );
		$filter   = $this->get_filter_by_field( 'meta', $meta_key );
		// Only if it's one of our filters.
		if ( ! $filter ) {
			return;
		}

		$this->orderby_cast = $this->map_meta_cast( $filter );
		add_filter( 'posts_orderby', [ $this, 'cast_orderby' ], 10, 2 );
	}

	/**
	 * Help decimal cast.
	 *
	 * @param string $query The query.
	 *
	 * @return string The query.
	 */
	public function help_decimal_cast( $query ) {
		// Run once.
		remove_filter( 'posts_request', [ $this, 'help_decimal_cast' ], 10, 1 );
		return preg_replace( '/AS DECIMAL\)/', 'AS DECIMAL(6,2))', $query );
	}

	/**
	 * Cast orderby.
	 *
	 * @param string $orderby The orderby.
	 *
	 * @since TBD Removed unused $wp_query parameter.
	 *
	 * @return string The orderby.
	 */
	public function cast_orderby( $orderby ) {
		// Run once.
		remove_filter( 'posts_orderby', [ $this, 'cast_orderby' ], 10, 2 );
		list( $by, $dir ) = explode( ' ', trim( $orderby ) );
		if ( ! empty( $this->orderby_cast ) && 'CAST' !== $this->orderby_cast ) {
			$by = sprintf( 'CAST(%s AS %s)', $by, $this->orderby_cast );
			return $by . ' ' . $dir;
		}
		return $orderby;
	}

	/**
	 * Add query filters.
	 */
	public function add_query_filters() {
		$screen = get_current_screen();

		// Only filter our post type.
		if ( $screen->post_type !== $this->filtered_post_type ) {
			return;
		}

		add_action( 'parse_query', [ $this, 'parse_query' ] );
	}

	/**
	 * Parse query.
	 *
	 * @param WP_Query $wp_query The WP_Query object.
	 */
	public function parse_query( $wp_query ) {
		/*
		 * Run once.
		 * However, if we just remove it without leaving something in its place
		 * the next action that's supposed to run on parse query might be skipped.
		 */
		add_action( 'parse_query', '__return_true' );
		remove_action( 'parse_query', [ $this, 'parse_query' ] );

		do_action_ref_array( 'tribe_before_parse_query', [ $wp_query, $this->active ] );

		$tax_query  = [];
		$meta_query = [];

		foreach ( $this->active as $k => $v ) {
			if ( ! isset( $this->filters[ $k ] ) ) {
				continue;
			}

			$filter = $this->filters[ $k ];
			if ( isset( $filter['taxonomy'] ) ) {
				$tax_query[] = $this->tax_query( $k, $v );
			} elseif ( isset( $filter['meta'] ) ) {
				$meta_query[] = $this->meta_query( $k, $v );
			}
		}
		$old_tax_query = $wp_query->get( 'tax_query' );
		$old_tax_query = ( empty( $old_tax_query ) ) ? [] : $old_tax_query;
		$tax_query     = array_merge( $old_tax_query, $tax_query );

		$wp_query->set( 'tax_query', $tax_query );

		$old_meta_query = $wp_query->get( 'meta_query' );
		$old_meta_query = ( empty( $old_meta_query ) ) ? [] : $old_meta_query;
		$meta_query     = array_merge( $old_meta_query, $meta_query );

		$wp_query->set( 'meta_query', $meta_query );

		$this->maybe_set_ordering( $wp_query );

		do_action_ref_array( 'tribe_after_parse_query', [ $wp_query, $this->active ] );
	}

	/**
	 * Debug.
	 */
	public function debug() {
		$this->log( $GLOBALS['wp_query'] );
	}

	/**
	 * Add body class.
	 *
	 * @param string $classes The classes.
	 *
	 * @return string The classes.
	 */
	public function add_body_class( $classes ) {
		global $wp_query;
		// Takes a string.
		$ours = 'tribe-filters-active';

		// No results.
		if ( 0 == $wp_query->found_posts ) {
			$ours .= ' empty-result-set';
		}

		$classes = $ours . ' ' . trim( $classes );
		return trim( $classes ) . ' ';
	}

	/**
	 * Inits a saved filter set if one submitted.
	 *
	 * @return void
	 */
	public function init_active() {
		// Saved filter active?
		if ( isset( $_GET['saved_filter'] ) && absint( $_GET['saved_filter'] ) > 0 ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filterset = get_post( absint( $_GET['saved_filter'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( substr( $filterset->post_content, 0, 2 ) === 'a:' ) {
				// If post_content is serialized, grab it and update it to json_encoded.
				$active = unserialize( $filterset->post_content ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

				if ( $active ) {
					wp_update_post(
						[
							'ID'           => $filterset->ID,
							'post_content' => wp_json_encode( $active ),
						]
					);
				}
			} else {
				$active = json_decode( $filterset->post_content, true );
			}

			if ( $active ) {
				$this->set_active( $active );
				$this->saved_active = $filterset;
			}
		} elseif ( ! $_POST ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$last_query = $this->last_query();
			if ( $last_query ) {
				$this->set_active( $last_query );
			}
		}
	}

	/**
	 * Active Items set via a POST form.
	 *
	 * Or, active items can be saved for later retrieval and application.
	 */
	public function save_active() {
		if ( ! ( isset( $_POST[ $this->nonce ] ) && wp_verify_nonce( sanitize_key( $_POST[ $this->nonce ] ), $this->nonce ) ) ) {
			return;
		}

		// Clear button on frontend.
		if ( isset( $_POST['tribe-clear'] ) ) {
			$this->reset_active();
			return;
		}
		$active = [];

		foreach ( $this->filters as $key => $filter ) {
			$maybe_active = false;


			if ( isset( $filter['meta'] ) ) {
				// Meta fields.
				$maybe_active = $this->maybe_active_meta( $key, $filter );
			} elseif ( isset( $filter['taxonomy'] ) ) {
				// Taxonomies.
				$maybe_active = $this->maybe_active_taxonomy( $key, $filter );
			} elseif ( isset( $filter['custom_type'] ) ) {
				// Custom types.
				$maybe_active = apply_filters( 'tribe_maybe_active' . $filter['custom_type'], false, $key, $filter );
			}

			// Add em if ya got em.
			if ( $maybe_active ) {
				$active[ $key ] = $maybe_active;
			}
		}

		if ( ! empty( $active ) ) {
			$this->set_active( $active );
		} else {
			$this->reset_active();
			return;
		}

		if ( isset( $_POST['tribe-save'] ) ) {
			$this->save_filter();
		}
	}

	/**
	 * Update or delete saved filters.
	 *
	 * @return void
	 */
	public function update_or_delete_saved_filters() {
		if ( ! ( isset( $_POST[ $this->nonce ] ) && wp_verify_nonce( sanitize_key( $_POST[ $this->nonce ] ), $this->nonce ) ) ) {
			return;
		}

		// If there wasn't a saved filter ID, no point.
		if ( ! isset( $_POST['tribe-saved-filter-active'] ) || empty( $_POST['tribe-saved-filter-active'] ) ) {
			return;
		}

		// Update the filter with currently active stuff.
		if ( isset( $_POST['tribe-update-saved-filter'] ) ) {
			$filter               = get_post( absint( $_POST['tribe-saved-filter-active'] ) );
			$filter->post_content = wp_json_encode( $this->active );
			wp_update_post( $filter );
		}

		// Delete the saved filter.
		if ( isset( $_POST['tribe-delete-saved-filter'] ) && absint( $_POST['tribe-saved-filter-active'] ) > 0 ) {
			wp_delete_post( absint( $_POST['tribe-saved-filter-active'] ), true );
			// Clear all filters while we're at it.
			$this->reset_active();
		}
	}

	/**
	 * Register post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::FILTER_POST_TYPE,
			[
				'show_ui'           => false,
				'rewrite'           => false,
				'show_in_nav_menus' => false,
			]
		);
	}

	/**
	 * Enqueue.
	 *
	 * @return void
	 */
	public function enqueue() {
		global $current_screen;
		$resources_url = apply_filters( 'tribe_apm_resources_url', $this->url . 'resources' );
		$resources_url = trailingslashit( $resources_url );
		if ( $current_screen->id == 'edit-' . $this->filtered_post_type ) {
			wp_enqueue_style( 'tribe-jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.10/themes/base/jquery-ui.css', [], '1.8.10' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script(
				'tribe-filters',
				$resources_url .
				'tribe-filters.js',
				[
					'jquery-ui-sortable',
					'jquery-ui-datepicker',
				],
				Tribe_APM::VERSION,
				true
			);
		}
	}

	/**
	 * Last query.
	 *
	 * @return array The last query.
	 */
	protected function last_query() {
		$meta = get_user_meta( get_current_user_id(), 'last_used_filters_' . $this->filtered_post_type, true );

		if ( ! is_string( $meta ) ) {
			return [];
		}

		$decoded = json_decode( $meta, true );

		if ( ! is_array( $decoded ) ) {
			return [];
		}

		return $decoded;
	}

	/**
	 * Cache last query.
	 *
	 * @param array $query The query.
	 *
	 * @return bool The result.
	 */
	protected function cache_last_query( $query ) {
		return update_user_meta( get_current_user_id(), 'last_used_filters_' . $this->filtered_post_type, wp_json_encode( $query ) );
	}

	/**
	 * Clear last query.
	 *
	 * @return bool The result.
	 */
	protected function clear_last_query() {
		return delete_user_meta( get_current_user_id(), 'last_used_filters_' . $this->filtered_post_type );
	}

	/**
	 * Reset active.
	 *
	 * @return void
	 */
	protected function reset_active() {
		$this->active = [];
		$this->clear_last_query();
	}

	/**
	 * Alphabetize filters.
	 *
	 * @return void
	 */
	protected function alphabetize_filters() {
		$filters       = (array) $this->filters;
		$temp          = [];
		$alpha_filters = [];

		if ( empty( $filters ) ) {
			return;
		}

		foreach ( $filters as $k => $v ) {
			if ( ! empty( $v['name'] ) ) {
				$temp[ $k ] = $v['name'];
			}
		}
		asort( $temp );

		foreach ( $temp as $k => $v ) {
			$alpha_filters[ $k ] = $filters[ $k ];
		}

		$this->filters = $alpha_filters;
		unset( $alpha_filters, $temp );
	}

	/**
	 * Maybe active taxonomy.
	 *
	 * @param string $key    The key.
	 *
	 * @return array The result.
	 */
	protected function maybe_active_taxonomy( $key ) {
		$val = $this->prefix . $key;
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $_POST[ $val ] ) ) {
			return [ 'value' => $_POST[ $val ] ];
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return false;
	}

	/**
	 * Maybe active meta.
	 *
	 * @param string $key The key.
	 *
	 * @return array The result.
	 */
	protected function maybe_active_meta( $key ) {
		$val = $this->val_pre . $key;
		$is  = $this->is_pre . $key;

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $_POST[ $val ] ) && isset( $_POST[ $is ] ) ) {
			return [
				'value'        => $_POST[ $val ],
				'query_option' => $_POST[ $is ],
			];
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return false;
	}

	/**
	 * Save filter.
	 *
	 * @return void
	 */
	protected function save_filter() {
		if ( ! isset( $_POST['filter_name'] ) || empty( $this->active ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$filter = [
			'post_content' => wp_json_encode( $this->active ),
			'post_title'   => sanitize_text_field( $_POST['filter_name'] ), //phpcs:ignore WordPress.Security.NonceVerification.Missing
			'post_type'    => self::FILTER_POST_TYPE,
			'post_status'  => 'publish',
		];

		$post_id = wp_insert_post( $filter );
		update_post_meta( $post_id, self::FILTER_META, $this->filtered_post_type );
	}

	/**
	 * Log.
	 *
	 * @param array $data The data.
	 *
	 * @return void
	 */
	public function log( $data = [] ) {
		error_log( print_r( $data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}

	/**
	 * Saved filters dropdown.
	 *
	 * @return void
	 */
	protected function saved_filters_dropdown() {
		$filters = get_posts(
			[
				'numberposts' => -1,
				'post_type'   => self::FILTER_POST_TYPE,
				'meta_key'    => self::FILTER_META,
				'meta_value'  => $this->filtered_post_type, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		if ( empty( $filters ) ) {
			return;
		}

		$url = add_query_arg(
			[
				'post_type'    => $this->filtered_post_type,
				'saved_filter' => 'balderdash',
			],
			admin_url( 'edit.php' )
		);
		$url = str_replace( 'balderdash', '', $url );

		// @TODO: this is an inappropriate way to do pluralization.
		?>
		<div class="apm-saved-filters">
			<h2 class="select-saved-filter"><?php echo esc_html__( 'Saved Filter Set', 'advanced-post-manager' ); ?><span>s</span></h2>
			<span class="apm-select-wrap"><select id="tribe-saved-filters" name="tribe-saved-filters" data:submit_url="<?php echo esc_url( $url ); ?>">
				<option value="0"><?php echo esc_html__( 'Choose a Saved Filter', 'advanced-post-manager' ); ?></option>
				<?php
				foreach ( $filters as $filter ) {
					?>
					<option value="<?php echo esc_attr( $filter->ID ); ?>" <?php selected( $this->saved_active && $this->saved_active->ID == $filter->ID, true ); ?>><?php echo esc_html( $filter->post_title ); ?></option>
					<?php
				}
				?>

			</select></span>
		</div>

		<?php
		// Delete/Update Saved Query if one is active.
		if ( ! empty( $this->saved_active ) && isset( $this->saved_active ) ) {
			?>
			<span class="hide-if-no-js saved-filter-actions">
				<input type="hidden" name="tribe-saved-filter-active" value="<?php echo esc_attr( $this->saved_active->ID ); ?>" />
				<input type="submit" name="tribe-update-saved-filter" value="Update Filter" class="button-secondary" />
				<input type="submit" name="tribe-delete-saved-filter" value="Delete Filter" class="button-secondary" />
			</span>
			<?php
		}
	}

	/**
	 * Get filter by field.
	 *
	 * @param string $field The field.
	 * @param string $value The value.
	 *
	 * @return array The result.
	 */
	protected function get_filter_by_field( $field, $value ) {
		foreach ( $this->filters as $k => $v ) {
			if ( isset( $v[ $field ] ) && $value === $v[ $field ] ) {
				return $this->filters[ $k ];
			}
		}
		return false;
	}

	/**
	 * Table row.
	 *
	 * Accepts a $this->active $key => value pair.
	 *
	 * @param string $key The key.
	 * @param string $value The value.
	 *
	 * @return void
	 */
	protected function table_row( $key, $value ) {
		if ( ! isset( $this->filters[ $key ] ) ) {
			return;
		}

		$filter = $this->filters[ $key ];
		$before = '<tr><th scope="row">' . esc_html( $filter['name'] ) . '</th><td>';
		$after  = '<b class="close">×</b></td></tr>';
		if ( isset( $filter['taxonomy'] ) ) {
			$ret = $this->taxonomy_row( $key, $value, $filter );
		} elseif ( isset( $filter['meta'] ) ) {
			$ret = $this->meta_row( $key, $value, $filter );
		} elseif ( isset( $filter['custom_type'] ) ) {
			$ret = apply_filters( 'tribe_custom_row' . $filter['custom_type'], '', $key, $value, $filter );
		}

		return $before . $ret . $after;
	}

	/**
	 * Taxonomy row.
	 *
	 * @param string $key    The key.
	 * @param string $value  The value.
	 * @param array  $filter The filter.
	 *
	 * @return string The result.
	 */
	protected function taxonomy_row( $key, $value, $filter ) {
		$terms = get_terms( $filter['taxonomy'] );
		$value = array_merge( [ 'value' => 0 ], (array) $value );
		$opts  = [];

		foreach ( $terms as $term ) {
			$opts[ $term->term_id ] = $term->name;
		}
		return self::select_field( $this->prefix . $key, $opts, $value['value'], true );
	}

	/**
	 * Meta row.
	 *
	 * @param string $key    The key.
	 * @param string $value  The value.
	 * @param array  $filter The filter.
	 *
	 * @return string The result.
	 */
	protected function meta_row( $key, $value, $filter ) {
		$ret     = '';
		$is_key  = $this->is_pre . $key;
		$val_key = $this->val_pre . $key;
		$value   = array_merge(
			[
				'value'        => 0,
				'query_option' => 0,
			],
			(array) $value
		);

		// We have explicit dropdown options.
		if ( isset( $filter['options'] ) && ! empty( $filter['options'] ) ) {
			$query_options = apply_filters( 'tribe_query_options', $this->query_options, $key, $filter );
			$ret          .= self::select_field( $is_key, $query_options, $value['query_option'] );
			$ret          .= self::select_field( $val_key, $filter['options'], $value['value'], true );
		} else {
			// No explicit options. We're showing a search field.
			$query_options = apply_filters( 'tribe_query_options', $this->query_search_options, $key, $filter );
			$input_class   = apply_filters( 'tribe_filter_input_class', 'text', $filter, $key, $value );
			$ret          .= self::select_field( $is_key, $query_options, $value['query_option'] );
			$ret          .= "<input type='text' name='{$val_key}' value='{$value['value']}' class='{$input_class}' >";
		}

		return $ret;
	}

	/**
	 * Select field.
	 *
	 * @param string $name The name.
	 * @param array  $options The options.
	 * @param string $active The active.
	 * @param bool   $allow_multi The allow multi.
	 *
	 * @return string The result.
	 */
	public static function select_field( $name, $options = null, $active = '', $allow_multi = false ) {

		$is_multi = ( is_array( $active ) ) ? true : false;
		if ( ! $allow_multi ) {
			$class = 'no-multi';
			$multi = '';
		} else {
			$class = 'multi-active';
			$multi = ' multiple="multiple"';
			$name  = $name . '[]';
		}

		// In case we only had a single value passed, we'll typecast to array to keep it DRY.
		$active = (array) $active;
		$sel    = '';

		if ( is_array( $options ) ) {
			$sel .= '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" class="' . esc_attr( $class ) . '"' . $multi . '>';

			foreach ( $options as $k => $v ) {
				$selected = selected( in_array( $k, $active ), true, false );
				$sel     .= '<option value="' . esc_attr( $k ) . '"' . $selected . '>' . $v . '</option>';
			}
			$sel .= '</select>';
		}
		return $sel;
	}

	/**
	 * Inactive dropdown.
	 *
	 * @return void
	 */
	protected function inactive_dropdown() {
		$inactive = $this->inactive;
		echo '<span class="apm-select-wrap"><select name="tribe-filters-inactive" id="tribe-filters-inactive">';
		echo '<option value="0">' . esc_html__( 'Add a Filter', 'advanced-post-manager' ) . '</option>';

		foreach ( $inactive as $k => $v ) {
			$row = $this->dropdown_row( $k, $v );
			echo $row; // phpcs:ignore StellarWP.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</select></span>';
	}

	/**
	 * Dropdown row.
	 *
	 * @param string $k The key.
	 * @param array  $v The value.
	 *
	 * @return string The result.
	 */
	protected function dropdown_row( $k, $v ) {
		return '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['name'] ) . '</option>';
	}

	/**
	 * Form JS.
	 *
	 * @return void
	 */
	protected function form_js() {
		global $wp_query;

		$templates   = [];
		$option_rows = [];

		foreach ( $this->filters as $k => $v ) {
			$templates[ $k ]   = $this->table_row( $k, '' );
			$option_rows[ $k ] = $this->dropdown_row( $k, $v );
		}

		$js = [
			'filters'    => $this->filters,
			'template'   => $templates,
			'option'     => $option_rows,
			'valPrefix'  => $this->val_pre,
			'prefix'     => $this->prefix,
			'displaying' => $wp_query->found_posts . ' found',
		];

		echo "\n<script>";
		echo "\n\tvar Tribe_Filters = " . wp_json_encode( $js );
		echo "\n</script>";
	}

	/**
	 * Maybe set ordering.
	 *
	 * @param WP_Query $wp_query The WP_Query object.
	 *
	 * @return void
	 */
	protected function maybe_set_ordering( $wp_query ) {
		$sort_prefix = apply_filters( 'tribe_sort_prefix', 'tribe_sort_' );
		$orderby     = $wp_query->get( 'orderby' );
		if ( empty( $orderby ) && isset( $_POST['orderby'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$orderby = sanitize_text_field( $_POST['orderby'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( ! empty( $orderby ) && 0 === strpos( $orderby, $sort_prefix ) ) {
			$orderby = preg_replace( '/^' . $sort_prefix . '/', '', $orderby );
			// If it's a meta field, easy enough.
			$meta_field = $this->get_filter_by_field( 'meta', $orderby );
			if ( $meta_field ) {
				$wp_query->set( 'orderby', 'meta_value' );
				$wp_query->set( 'meta_key', $orderby );
				return;
			}
			// Custom Field?
			$custom_field = $this->get_filter_by_field( 'custom_type', $orderby );
			if ( $custom_field ) {
				do_action_ref_array( 'tribe_orderby_custom' . $orderby, [ $wp_query, $custom_field ] );
			}
		}
	}

	/**
	 * Tax query.
	 *
	 * @param string $key The key.
	 * @param array  $val The value.
	 *
	 * @return array The result.
	 */
	protected function tax_query( $key, $val ) {
		$filter    = $this->filters[ $key ];
		$tax_query = [
			'taxonomy' => $filter['taxonomy'],
			'field'    => 'id',
			'terms'    => $val['value'],
			'operator' => 'IN',
		];
		return apply_filters( 'tribe_filters_tax_query', $tax_query, $key, $val, $filter );
	}

	/**
	 * Meta query.
	 *
	 * @param string $key The key.
	 * @param array  $val The value.
	 *
	 * @return array The result.
	 */
	protected function meta_query( $key, $val ) {
		$filter     = $this->filters[ $key ];
		$meta_query = [
			'key'     => $filter['meta'],
			'value'   => $val['value'],
			'compare' => $this->map_meta_compare( $val ),
			'type'    => $this->map_meta_cast( $filter ),
		];
		return apply_filters( 'tribe_filters_meta_query', $meta_query, $key, $val, $filter );
	}

	/**
	 * Map meta cast.
	 *
	 * @param array $filter The filter.
	 *
	 * @return string The result.
	 */
	protected function map_meta_cast( $filter ) {
		$cast    = ( isset( $filter['cast'] ) ) ? strtoupper( $filter['cast'] ) : 'CHAR';
		$cast    = ( 'NUMERIC' === $cast ) ? 'SIGNED' : $cast;
		$allowed = [ 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED' ];

		return ( in_array( $cast, $allowed ) ) ? $cast : 'CHAR';
	}

	/**
	 * Map meta compare.
	 *
	 * @param array $val The value.
	 *
	 * @return string The result.
	 */
	protected function map_meta_compare( $val ) {
		$compare = ( isset( $val['query_option'] ) ) ? $val['query_option'] : 'is';
		if ( is_array( $val['value'] ) ) {
			return ( 'not' === $compare ) ? 'NOT IN' : 'IN';
		}
		return $this->query_options_map[ $compare ];
	}

	/**
	 * Map query option.
	 *
	 * @param string $option The option.
	 *
	 * @return string The result.
	 */
	public function map_query_option( $option ) {
		return $this->query_options_map[ $option ];
	}

	/**
	 * Is date.
	 *
	 * @param array $filter The filter.
	 *
	 * @return bool The result.
	 */
	protected function is_date( $filter ) {
		if ( isset( $filter['cast'] ) ) {
			$cast = ucwords( $filter['cast'] );
			if ( in_array( $cast, [ 'DATE', 'DATETIME' ] ) ) {
				return true;
			}
		} elseif ( isset( $filter['type'] ) && 'DATE' === ucwords( $filter['type'] ) ) {
			return true;
		}

		return false;
	}
}
