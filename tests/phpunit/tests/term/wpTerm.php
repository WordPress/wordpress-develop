<?php

/**
 * @group taxonomy
 */
class Tests_Term_WpTerm extends WP_UnitTestCase {
	protected static int $term_id;

	public function set_up() {
		parent::set_up();
		register_taxonomy( 'wptests_tax', 'post' );
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		register_taxonomy( 'wptests_tax', 'post' );

		// Ensure that there is a term with ID 1.
		if ( ! get_term( 1 ) ) {
			$wpdb->insert(
				$wpdb->terms,
				array(
					'term_id' => 1,
				)
			);

			$wpdb->insert(
				$wpdb->term_taxonomy,
				array(
					'term_id'  => 1,
					'taxonomy' => 'wptests_tax',
				)
			);

			clean_term_cache( 1, 'wptests_tax' );
		}

		self::$term_id = $factory->term->create( array( 'taxonomy' => 'wptests_tax' ) );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_work_for_numeric_string() {
		$found = WP_Term::get_instance( (string) self::$term_id );

		$this->assertSame( self::$term_id, $found->term_id );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_negative_number() {
		$found = WP_Term::get_instance( -self::$term_id );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_non_numeric_string() {
		$found = WP_Term::get_instance( 'abc' );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_succeed_for_float_that_is_equal_to_post_id() {
		$found = WP_Term::get_instance( 1.0 );

		$this->assertSame( 1, $found->term_id );
	}

	/**
	 * @ticket 40671
	 */
	public function test_get_instance_should_respect_taxonomy_when_term_id_is_found_in_cache() {
		global $wpdb;

		register_taxonomy( 'wptests_tax2', 'post' );

		// Ensure that cache is primed.
		WP_Term::get_instance( self::$term_id, 'wptests_tax' );

		$found = WP_Term::get_instance( self::$term_id, 'wptests_tax2' );
		$this->assertFalse( $found );
	}

	/**
	 * Tests that a cached value which cannot be used as a term is treated as a cache miss.
	 *
	 * @ticket 65962
	 *
	 * @dataProvider data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss
	 *
	 * @param mixed $cache_value Value to poison the object cache with.
	 */
	public function test_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss( $cache_value ): void {
		wp_cache_set( self::$term_id, $cache_value, 'terms' );

		$num_queries = get_num_queries();

		$term = WP_Term::get_instance( self::$term_id );

		$this->assertInstanceOf( WP_Term::class, $term, 'A term object was not returned.' );
		$this->assertSame( self::$term_id, $term->term_id, 'The wrong term was returned.' );
		$this->assertSame( 'wptests_tax', $term->taxonomy, 'The term was returned without its taxonomy.' );
		$this->assertSame( $num_queries + 1, get_num_queries(), 'The term was not fetched from the database.' );
	}

	/**
	 * Tests that a poisoned cache value is treated as a miss when a taxonomy is given.
	 *
	 * The taxonomy comparison reads a property off whatever is cached, so the guard has to
	 * reject an unusable value before that point.
	 *
	 * @ticket 65962
	 *
	 * @dataProvider data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss
	 *
	 * @param mixed $cache_value Value to poison the object cache with.
	 */
	public function test_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss_with_a_taxonomy( $cache_value ): void {
		wp_cache_set( self::$term_id, $cache_value, 'terms' );

		$term = WP_Term::get_instance( self::$term_id, 'wptests_tax' );

		$this->assertInstanceOf( WP_Term::class, $term, 'A term object was not returned.' );
		$this->assertSame( self::$term_id, $term->term_id, 'The wrong term was returned.' );
	}

	/**
	 * Tests that the refetched term replaces the poisoned cache value.
	 *
	 * Otherwise the poisoned value survives and every subsequent lookup queries the database again.
	 *
	 * @ticket 65962
	 *
	 * @dataProvider data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss
	 *
	 * @param mixed $cache_value Value to poison the object cache with.
	 */
	public function test_get_instance_replaces_a_poisoned_cache_value( $cache_value ): void {
		wp_cache_set( self::$term_id, $cache_value, 'terms' );

		// Prime the object cache, replacing the poisoned value.
		WP_Term::get_instance( self::$term_id );

		$num_queries = get_num_queries();

		$term = WP_Term::get_instance( self::$term_id );

		$this->assertInstanceOf( WP_Term::class, $term, 'A term object was not returned.' );
		$this->assertSame( self::$term_id, $term->term_id, 'The wrong term was returned.' );
		$this->assertSame( $num_queries, get_num_queries(), 'The database was queried again.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ mixed }>
	 */
	public static function data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss(): array {
		return array(
			'true'                       => array( true ),
			'a non-numeric string'       => array( 'not-a-term' ),
			'an array of term data'      => array(
				array(
					'term_id'  => 1,
					'taxonomy' => 'wptests_tax',
				),
			),
			'an object without term_id'  => array(
				(object) array(
					'taxonomy' => 'wptests_tax',
				),
			),
			'an object without taxonomy' => array(
				(object) array(
					'term_id' => 1,
				),
			),
			'a WP_Term without term_id'  => array( new WP_Term( new stdClass() ) ),
		);
	}

	/**
	 * Tests that `term_group` defaults to the integer 0 when the term data does not include it.
	 *
	 * @ticket 66739
	 *
	 * @covers WP_Term::__construct
	 *
	 * @dataProvider data_term_group
	 *
	 * @param object $data     Term data passed to the constructor.
	 * @param int    $expected Expected term group.
	 */
	public function test_term_group( object $data, int $expected ): void {
		$term = new WP_Term( $data );

		$this->assertSame( $expected, $term->term_group );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: object, 1: int }>
	 */
	public static function data_term_group(): array {
		return array(
			'term data without term_group' => array( new stdClass(), 0 ),
			'term data with a term_group'  => array( (object) array( 'term_group' => 2 ), 2 ),
		);
	}
}
