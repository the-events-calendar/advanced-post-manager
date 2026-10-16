<?php

use Codeception\TestCase\WPTestCase;

class Tribe_FiltersTest extends WPTestCase {
	public function test_can_be_instantiated(): void {
		$filters = new Tribe_Filters( 'post', [] );
		$this->assertInstanceOf( Tribe_Filters::class, $filters );
	}

	public function bad_last_user_filters_data(): array {
		return [
			'null'           => [ null ],
			'empty string'   => [ '' ],
			'bad json'       => [ 'bad json' ],
			'zero'           => [ 0 ],
			'zero string'    => [ 0 ],
			'integer'        => [ 23 ],
			'integer string' => [ '89' ],
			'float'          => [ 3.14 ],
			'float string'   => [ '3.14' ],
			'false'          => [ false ],
			'true'           => [ true ],
		];
	}

	/**
	 * @dataProvider bad_last_user_filters_data
	 */
	public function test_init_active_with_bad_last_user_filters_data( $last_used_filters ): void {
		unset( $_GET['saved_filter'] );
		$_POST = [];
		$user  = set_current_user( static::factory()->user->create( [ 'role' => 'administrator' ] ) );
		update_user_meta( $user->ID, 'last_used_filters_post', $last_used_filters );

		// PHPUnit will be implicitly strict about errors and warnings.
		$filters = new Tribe_Filters( 'post', [] );
		$filters->init_active();

		$this->assertEquals( [], $filters->get_active() );
	}

	/**
	 * Verifies a saved_filter post of any type other than the filter-set post
	 * type is ignored before deserialization is attempted.
	 */
	public function test_saved_filter_ignores_a_post_of_the_wrong_post_type(): void {
		Tribe_Filters_Unserialize_Probe::$triggered = false;

		$contributor = static::factory()->user->create( [ 'role' => 'contributor' ] );
		set_current_user( $contributor );

		$post_id = static::factory()->post->create(
			[
				'post_author'  => $contributor,
				'post_type'    => 'post',
				'post_content' => serialize( new Tribe_Filters_Unserialize_Probe() ),
			]
		);

		$_GET['saved_filter'] = (string) $post_id;
		$_POST                = [];

		$filters = new Tribe_Filters( 'post', [] );
		$filters->init_active();

		unset( $_GET['saved_filter'] );

		$this->assertFalse( Tribe_Filters_Unserialize_Probe::$triggered, 'unserialize() must never run on a non-filter-set post.' );
		$this->assertEquals( [], $filters->get_active() );
	}

	/**
	 * Verifies a filter-set post's content never instantiates an object
	 * during unserialize(), even when the post type is correct.
	 */
	public function test_saved_filter_never_instantiates_objects_from_filter_set_content(): void {
		Tribe_Filters_Unserialize_Probe::$triggered = false;

		$owner = static::factory()->user->create( [ 'role' => 'administrator' ] );
		set_current_user( $owner );

		$post_id = static::factory()->post->create(
			[
				'post_author'  => $owner,
				'post_type'    => Tribe_Filters::FILTER_POST_TYPE,
				'post_content' => serialize( new Tribe_Filters_Unserialize_Probe() ),
			]
		);
		update_post_meta( $post_id, Tribe_Filters::FILTER_META, 'post' );

		$_GET['saved_filter'] = (string) $post_id;
		$_POST                = [];

		$filters = new Tribe_Filters( 'post', [] );
		// Migrating the filter set runs a WP_Query, and parse_query assumes an admin list-table screen.
		remove_action( 'pre_get_posts', [ $filters, 'parse_query' ] );
		$filters->init_active();

		unset( $_GET['saved_filter'] );

		$this->assertFalse( Tribe_Filters_Unserialize_Probe::$triggered, 'unserialize() must never instantiate objects, even from a real filter set post.' );
	}

	/**
	 * Verifies a user without read capability on a filter-set post cannot
	 * load it via saved_filter.
	 */
	public function test_saved_filter_is_capability_checked(): void {
		$owner = static::factory()->user->create( [ 'role' => 'administrator' ] );

		$post_id = static::factory()->post->create(
			[
				'post_author'  => $owner,
				'post_type'    => Tribe_Filters::FILTER_POST_TYPE,
				'post_status'  => 'private',
				'post_content' => wp_json_encode( [ 'some_key' => [ 'value' => 'secret' ] ] ),
			]
		);
		update_post_meta( $post_id, Tribe_Filters::FILTER_META, 'post' );

		$other_user = static::factory()->user->create( [ 'role' => 'contributor' ] );
		set_current_user( $other_user );

		$_GET['saved_filter'] = (string) $post_id;
		$_POST                = [];

		$filters = new Tribe_Filters( 'post', [] );
		$filters->init_active();

		unset( $_GET['saved_filter'] );

		$this->assertEquals( [], $filters->get_active(), 'A user without read capability on the filter set post must not have it loaded.' );
	}

	/**
	 * Verifies a filter set stored as a serialized array of scalars still
	 * migrates to the active filters array.
	 */
	public function test_saved_filter_migrates_legacy_serialized_array_content(): void {
		$owner = static::factory()->user->create( [ 'role' => 'administrator' ] );
		set_current_user( $owner );

		$legacy_active = [ 'some_key' => [ 'value' => 'legacy-value' ] ];

		$post_id = static::factory()->post->create(
			[
				'post_author'  => $owner,
				'post_type'    => Tribe_Filters::FILTER_POST_TYPE,
				'post_content' => serialize( $legacy_active ), //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			]
		);
		update_post_meta( $post_id, Tribe_Filters::FILTER_META, 'post' );

		$_GET['saved_filter'] = (string) $post_id;
		$_POST                = [];

		$filters = new Tribe_Filters( 'post', [] );
		// Migrating the filter set runs a WP_Query, and parse_query assumes an admin list-table screen.
		remove_action( 'pre_get_posts', [ $filters, 'parse_query' ] );
		$filters->init_active();

		unset( $_GET['saved_filter'] );

		$this->assertEquals( $legacy_active, $filters->get_active() );
	}

	/**
	 * Verifies a contributor holding a valid nonce cannot force-delete a post
	 * that is not a saved filter.
	 *
	 * @dataProvider non_filter_post_types
	 */
	public function test_delete_saved_filter_ignores_posts_that_are_not_filter_sets( string $post_type ): void {
		$victim = static::factory()->post->create(
			[
				'post_author' => static::factory()->user->create( [ 'role' => 'administrator' ] ),
				'post_type'   => $post_type,
			]
		);

		$filters = $this->submit_saved_filter_action( 'tribe-delete-saved-filter', $victim, 'contributor' );
		$filters->update_or_delete_saved_filters();

		$this->assertNotNull( get_post( $victim ), 'A non-filter post must survive the saved filter delete handler.' );
	}

	/**
	 * Verifies a contributor holding a valid nonce cannot overwrite the content
	 * of a post that is not a saved filter.
	 *
	 * @dataProvider non_filter_post_types
	 */
	public function test_update_saved_filter_ignores_posts_that_are_not_filter_sets( string $post_type ): void {
		$content = 'original-' . $post_type;
		$victim  = static::factory()->post->create(
			[
				'post_author'  => static::factory()->user->create( [ 'role' => 'administrator' ] ),
				'post_type'    => $post_type,
				'post_content' => $content,
			]
		);

		$filters = $this->submit_saved_filter_action( 'tribe-update-saved-filter', $victim, 'contributor' );
		$filters->update_or_delete_saved_filters();

		$this->assertSame( $content, get_post( $victim )->post_content );
	}

	/**
	 * Verifies a user who cannot delete another user's filter set is refused.
	 */
	public function test_delete_saved_filter_requires_delete_capability(): void {
		$filter_id = $this->create_filter_set( static::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$filters = $this->submit_saved_filter_action( 'tribe-delete-saved-filter', $filter_id, 'contributor' );
		$filters->update_or_delete_saved_filters();

		$this->assertNotNull( get_post( $filter_id ) );
	}

	/**
	 * Verifies a user who cannot edit another user's filter set is refused.
	 */
	public function test_update_saved_filter_requires_edit_capability(): void {
		$filter_id = $this->create_filter_set( static::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$original  = get_post( $filter_id )->post_content;

		$filters = $this->submit_saved_filter_action( 'tribe-update-saved-filter', $filter_id, 'contributor', [ 'a' => [ 'value' => 'b' ] ] );
		$filters->update_or_delete_saved_filters();

		$this->assertSame( $original, get_post( $filter_id )->post_content );
	}

	/**
	 * Verifies the legitimate owner can still delete and update a filter set.
	 */
	public function test_owner_can_still_update_and_delete_their_filter_set(): void {
		$admin     = static::factory()->user->create( [ 'role' => 'administrator' ] );
		$filter_id = $this->create_filter_set( $admin );
		$active    = [ 'some_key' => [ 'value' => 'new' ] ];

		$filters = $this->submit_saved_filter_action( 'tribe-update-saved-filter', $filter_id, $admin, $active );
		$filters->update_or_delete_saved_filters();

		$this->assertSame( wp_json_encode( $active ), get_post( $filter_id )->post_content );

		$filters = $this->submit_saved_filter_action( 'tribe-delete-saved-filter', $filter_id, $admin );
		$filters->update_or_delete_saved_filters();

		$this->assertNull( get_post( $filter_id ) );
	}

	/**
	 * Verifies a user who cannot edit the filtered post type cannot create a filter set.
	 */
	public function test_save_filter_requires_edit_posts_capability(): void {
		$filters = $this->submit_saved_filter_action( 'tribe-save', 0, 'subscriber', [ 'some_key' => [ 'value' => 'x' ] ] );
		$_POST['filter_name'] = 'Injected';

		$method = new ReflectionMethod( Tribe_Filters::class, 'save_filter' );
		$method->setAccessible( true );
		$method->invoke( $filters );

		$this->assertSame( [], get_posts( [ 'post_type' => Tribe_Filters::FILTER_POST_TYPE, 'title' => 'Injected', 'post_status' => 'any' ] ) );
	}

	/**
	 * Verifies a filter set that belongs to another post type cannot be updated or deleted
	 * through this post type's screen, even by a user with full capabilities.
	 */
	public function test_saved_filter_of_another_post_type_is_untouchable(): void {
		$admin     = static::factory()->user->create( [ 'role' => 'administrator' ] );
		$filter_id = $this->create_filter_set( $admin );
		update_post_meta( $filter_id, Tribe_Filters::FILTER_META, 'page' );
		$original = get_post( $filter_id )->post_content;

		$filters = $this->submit_saved_filter_action( 'tribe-update-saved-filter', $filter_id, $admin, [ 'a' => [ 'value' => 'b' ] ] );
		$filters->update_or_delete_saved_filters();

		$this->assertSame( $original, get_post( $filter_id )->post_content );

		$filters = $this->submit_saved_filter_action( 'tribe-delete-saved-filter', $filter_id, $admin );
		$filters->update_or_delete_saved_filters();

		$this->assertNotNull( get_post( $filter_id ) );
	}

	/**
	 * Verifies a nonce issued for another post type's filter screen is rejected.
	 */
	public function test_filter_nonce_is_scoped_to_the_filtered_post_type(): void {
		$admin     = static::factory()->user->create( [ 'role' => 'administrator' ] );
		$filter_id = $this->create_filter_set( $admin );

		$filters = $this->submit_saved_filter_action( 'tribe-delete-saved-filter', $filter_id, $admin );

		$_POST['_tribe_filters'] = wp_create_nonce( '_tribe_filters_page' );
		$filters->update_or_delete_saved_filters();

		$this->assertNotNull( get_post( $filter_id ) );
	}

	/**
	 * Verifies submitted filter values and query options are sanitized before they become active.
	 */
	public function test_submitted_filter_values_are_sanitized(): void {
		$admin = static::factory()->user->create( [ 'role' => 'administrator' ] );
		set_current_user( $admin );

		$_GET  = [];
		$_POST = [
			'_tribe_filters'           => wp_create_nonce( '_tribe_filters_post' ),
			'tribe_filters_val_color'  => [ '<script>alert(1)</script>red', [ '<b>nested</b>' ] ],
			'tribe_filters_is_color'   => '<i>is</i>',
		];

		$filters = new Tribe_Filters( 'post', [ 'color' => [ 'name' => 'Color', 'meta' => '_color' ] ] );
		remove_action( 'pre_get_posts', [ $filters, 'parse_query' ] );
		$filters->save_active();

		$this->assertSame(
			[ 'color' => [ 'value' => [ 'red', [ 'nested' ] ], 'query_option' => 'is' ] ],
			$filters->get_active()
		);
	}

	/**
	 * Verifies a contributor cannot re-parent or re-order posts they cannot edit.
	 */
	public function test_reorder_images_ignores_items_the_user_cannot_edit(): void {
		$author  = static::factory()->user->create( [ 'role' => 'author' ] );
		$admin   = static::factory()->user->create( [ 'role' => 'administrator' ] );
		$own     = static::factory()->post->create( [ 'post_author' => $author ] );
		$victim  = static::factory()->post->create( [ 'post_author' => $admin, 'post_parent' => 0, 'menu_order' => 0 ] );
		$mine    = static::factory()->attachment->create( [ 'post_author' => $author, 'post_parent' => 0 ] );
		$theirs  = static::factory()->attachment->create( [ 'post_author' => $admin, 'post_parent' => 0 ] );
		$regular = static::factory()->post->create( [ 'post_author' => $author, 'post_parent' => 0 ] );

		$this->run_reorder( $author, $own, [ $victim, $theirs, $regular, $mine ] );

		$this->assertSame( 0, get_post( $victim )->post_parent, 'Another user\'s post must not be re-parented.' );
		$this->assertSame( 0, get_post( $theirs )->post_parent, 'Another user\'s attachment must not be re-parented.' );
		$this->assertSame( 0, get_post( $regular )->post_parent, 'Only attachments may be re-parented.' );
		$this->assertSame( $own, get_post( $mine )->post_parent, 'Legitimate attachments are still re-ordered.' );
		$this->assertSame( 4, get_post( $mine )->menu_order );
	}

	/**
	 * Verifies a user who cannot edit the parent post is refused outright.
	 */
	public function test_reorder_images_requires_edit_capability_on_the_parent_post(): void {
		$author = static::factory()->user->create( [ 'role' => 'author' ] );
		$admin  = static::factory()->user->create( [ 'role' => 'administrator' ] );
		$parent = static::factory()->post->create( [ 'post_author' => $admin ] );
		$mine   = static::factory()->attachment->create( [ 'post_author' => $author, 'post_parent' => 0 ] );

		$this->run_reorder( $author, $parent, [ $mine ] );

		$this->assertSame( 0, get_post( $mine )->post_parent );
	}

	/**
	 * Runs the reorder AJAX callback as $user_id, swallowing wp_die().
	 *
	 * @param int[] $items Attachment IDs, in the submitted order.
	 */
	private function run_reorder( int $user_id, int $parent_id, array $items ): void {
		set_current_user( $user_id );

		$_POST = [
			'data' => implode(
				'|',
				[
					http_build_query( [ 'item' => $items ] ),
					$parent_id,
					1,
					wp_create_nonce( 'tribe_ajax_reorder' ),
				]
			),
		];

		$box = new Tribe_Meta_Box(
			[
				'id'     => 'qa',
				'title'  => 'QA',
				'pages'  => [ 'post' ],
				'fields' => [ [ 'name' => 'Img', 'id' => 'img', 'type' => 'image' ] ],
			]
		);

		$die = static function () {
			throw new RuntimeException( 'wp_die' );
		};
		add_filter( 'wp_die_ajax_handler', static fn() => $die );
		add_filter( 'wp_doing_ajax', '__return_true' );

		try {
			$box->reorder_images();
		} catch ( RuntimeException $e ) {
			// Expected: every exit path ends in wp_die().
		}
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function non_filter_post_types(): array {
		return [
			'post'       => [ 'post' ],
			'page'       => [ 'page' ],
			'attachment' => [ 'attachment' ],
		];
	}

	private function create_filter_set( int $author ): int {
		$filter_id = static::factory()->post->create(
			[
				'post_author'  => $author,
				'post_type'    => Tribe_Filters::FILTER_POST_TYPE,
				'post_content' => wp_json_encode( [ 'some_key' => [ 'value' => 'orig' ] ] ),
			]
		);
		update_post_meta( $filter_id, Tribe_Filters::FILTER_META, 'post' );

		return $filter_id;
	}

	/**
	 * Logs in as $user (role name or user ID) and prepares a POST carrying a valid nonce.
	 *
	 * @param string|int $user   A role to create a user for, or an existing user ID.
	 * @param array      $active Active filters the handler will write on update.
	 */
	private function submit_saved_filter_action( string $button, int $target_id, $user, array $active = [] ): Tribe_Filters {
		$user_id = is_int( $user ) ? $user : static::factory()->user->create( [ 'role' => $user ] );
		set_current_user( $user_id );

		$_GET  = [];
		$_POST = [
			'_tribe_filters'            => wp_create_nonce( '_tribe_filters_post' ),
			'tribe-saved-filter-active' => (string) $target_id,
			$button                     => '1',
		];

		$filters = new Tribe_Filters( 'post', [] );
		// The constructor hooks parse_query, which assumes an admin list-table query.
		remove_action( 'pre_get_posts', [ $filters, 'parse_query' ] );
		$filters->register_post_type();
		$filters->set_active( $active );

		return $filters;
	}
}

/**
 * Sets $triggered to true when __wakeup() runs during unserialize().
 */
class Tribe_Filters_Unserialize_Probe {
	public static $triggered = false;

	public function __wakeup() {
		self::$triggered = true;
	}
}
