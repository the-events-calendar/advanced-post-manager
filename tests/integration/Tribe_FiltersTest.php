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
		$filters->init_active();

		unset( $_GET['saved_filter'] );

		$this->assertEquals( $legacy_active, $filters->get_active() );
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
