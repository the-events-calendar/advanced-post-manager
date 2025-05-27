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
}
