<?php

/**
 * @group taxonomy
 *
 * @covers ::get_term
 */
class Tests_Term_GetTerm extends WP_UnitTestCase {
	/**
	 * Test taxonomy term object.
	 *
	 * @var WP_Term
	 */
	public static $term;

	/**
	 * Set up shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		register_taxonomy( 'wptests_tax', 'post' );

		self::$term = $factory->term->create_and_get( array( 'taxonomy' => 'wptests_tax' ) );
	}

	/**
	 * Set up the test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		// Required as taxonomies are reset between tests.
		register_taxonomy( 'wptests_tax', 'post' );
		register_taxonomy( 'wptests_tax_2', 'post' );
	}

	public function test_should_return_error_for_empty_term() {
		$found = get_term( '', 'wptests_tax' );
		$this->assertWPError( $found );
		$this->assertSame( 'invalid_term', $found->get_error_code() );
	}

	public function test_should_return_error_for_invalid_taxonomy() {
		$found = get_term( 'foo', 'bar' );
		$this->assertWPError( $found );
		$this->assertSame( 'invalid_taxonomy', $found->get_error_code() );
	}

	public function test_passing_term_object_should_skip_database_query_when_filter_property_is_empty() {
		$term = self::$term;
		clean_term_cache( $term->term_id, 'wptests_tax' );

		$num_queries = get_num_queries();

		$term_a = get_term( $term, 'wptests_tax' );

		$this->assertSame( $num_queries, get_num_queries() );
	}

	public function test_passing_term_string_that_casts_to_int_0_should_return_null() {
		$this->assertNull( get_term( 'abc', 'wptests_tax' ) );
	}

	public function test_should_return_null_for_invalid_term_id() {
		$this->assertNull( get_term( 99999999, 'wptests_tax' ) );
	}

	public function test_cache_should_be_populated_by_successful_fetch() {
		$term_id = self::$term->term_id;
		clean_term_cache( $term_id, 'wptests_tax' );

		// Prime cache.
		$term_a      = get_term( $term_id, 'wptests_tax' );
		$num_queries = get_num_queries();

		// Second call shouldn't require a database query.
		$term_b = get_term( $term_id, 'wptests_tax' );
		$this->assertSame( $num_queries, get_num_queries() );
		$this->assertEquals( $term_a, $term_b );
	}

	public function test_output_object() {
		$term_id = self::$term->term_id;
		$this->assertIsObject( get_term( $term_id, 'wptests_tax', OBJECT ) );
	}

	public function test_output_array_a() {
		$term_id = self::$term->term_id;
		$term    = get_term( $term_id, 'wptests_tax', ARRAY_A );
		$this->assertIsArray( $term );
		$this->assertArrayHasKey( 'term_id', $term );
	}

	public function test_output_array_n() {
		$term_id = self::$term->term_id;
		$term    = get_term( $term_id, 'wptests_tax', ARRAY_N );
		$this->assertIsArray( $term );
		$this->assertArrayNotHasKey( 'term_id', $term );
		foreach ( $term as $k => $v ) {
			$this->assertIsInt( $k );
		}
	}

	public function test_output_should_fall_back_to_object_for_invalid_input() {
		$term_id = self::$term->term_id;
		$this->assertIsObject( get_term( $term_id, 'wptests_tax', 'foo' ) );
	}

	/**
	 * @ticket 14162
	 * @ticket 53235
	 */
	public function test_numeric_properties_should_be_cast_to_ints() {
		global $wpdb;

		$term_id = self::$term->term_id;

		// Get raw data from the database.
		$term_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->terms t JOIN $wpdb->term_taxonomy tt ON ( t.term_id = tt.term_id ) WHERE t.term_id = %d", $term_id ) );

		$contexts = array( 'raw', 'edit', 'db', 'display', 'rss', 'attribute', 'js' );

		foreach ( $contexts as $context ) {
			$found = get_term( $term_data, '', OBJECT, $context );

			$this->assertInstanceOf( 'WP_Term', $found );
			$this->assertIsInt( $found->term_id );
			$this->assertIsInt( $found->term_taxonomy_id );
			$this->assertIsInt( $found->parent );
			$this->assertIsInt( $found->count );
			$this->assertIsInt( $found->term_group );
		}
	}

	/**
	 * @ticket 34332
	 */
	public function test_should_return_null_when_provided_taxonomy_does_not_match_actual_term_taxonomy() {
		$term_id = self::$term->term_id;
		$this->assertNull( get_term( $term_id, 'category' ) );
	}

	/**
	 * @ticket 34533
	 */
	public function test_should_return_error_when_only_matching_term_is_in_an_invalid_taxonomy() {
		$term_id = self::$term->term_id;

		_unregister_taxonomy( 'wptests_tax' );

		$found = get_term( $term_id );
		$this->assertWPError( $found );
		$this->assertSame( 'invalid_taxonomy', $found->get_error_code() );
	}
}
