<?php

/**
 * Tests for how WP_Query handles query vars given values of an unexpected type.
 *
 * @group query
 *
 * @covers WP_Query::parse_query
 * @covers WP_Query::fill_query_vars
 */
class Tests_Query_QueryVarTypes extends WP_UnitTestCase {

	/**
	 * Query vars which take a list of values.
	 *
	 * @var string[]
	 */
	const LIST_QUERY_VARS = array(
		'author__in',
		'author__not_in',
		'category__and',
		'category__in',
		'category__not_in',
		'post__in',
		'post__not_in',
		'post_name__in',
		'post_parent__in',
		'post_parent__not_in',
		'search_columns',
		'tag__and',
		'tag__in',
		'tag__not_in',
		'tag_slug__and',
		'tag_slug__in',
	);

	/**
	 * Tests that query vars which take a single value do not cause an error when given an array.
	 *
	 * @ticket 60745
	 *
	 * @dataProvider data_single_value_query_vars
	 *
	 * @param string $query_var Query var name.
	 */
	public function test_single_value_query_var_given_array_does_not_cause_error( string $query_var ): void {
		self::factory()->post->create();

		$query = new WP_Query( array( $query_var => array( 'admin' ) ) );

		$this->assertIsArray( $query->posts );
	}

	/**
	 * Tests that a query var which takes a single value does not cause an error when set to an array in a 'pre_get_posts' callback.
	 *
	 * @ticket 60745
	 *
	 * @dataProvider data_single_value_query_vars
	 *
	 * @param string $query_var Query var name.
	 */
	public function test_single_value_query_var_set_to_array_in_pre_get_posts_does_not_cause_error( string $query_var ): void {
		self::factory()->post->create();

		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ) use ( $query_var ): void {
				$query->set( $query_var, array( 'admin' ) );
			}
		);

		$query = new WP_Query( array( 'post_type' => 'post' ) );

		$this->assertIsArray( $query->posts );
	}

	/**
	 * Data provider for {@see self::test_single_value_query_var_given_array_does_not_cause_error()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string }>
	 */
	public static function data_single_value_query_vars(): array {
		$query_vars = array(
			'attachment',
			'attachment_id',
			'author',
			'author_name',
			'cat',
			'category_name',
			'day',
			'embed',
			'error',
			'favicon',
			'feed',
			'fields',
			'hour',
			'm',
			'menu_order',
			'minute',
			'monthnum',
			'name',
			'p',
			'page',
			'page_id',
			'paged',
			'pagename',
			'post_parent',
			'post_status',
			'post_type',
			'preview',
			'robots',
			's',
			'second',
			'sentence',
			'subpost',
			'subpost_id',
			'tag',
			'tag_id',
			'tb',
			'title',
			'w',
			'withcomments',
			'withoutcomments',
			'year',
		);

		$data = array();
		foreach ( $query_vars as $query_var ) {
			$data[ $query_var ] = array( $query_var );
		}
		return $data;
	}

	/**
	 * Tests that query vars which take a list of values are normalized to arrays.
	 *
	 * This checks {@see WP_Query::fill_query_vars()} directly, since {@see WP_Query::parse_tax_query()}
	 * further transforms some of these query vars.
	 *
	 * @ticket 60745
	 *
	 * @dataProvider data_list_query_var_values
	 *
	 * @param mixed   $value    Value given for each list query var.
	 * @param mixed[] $expected Expected normalized value.
	 */
	public function test_list_query_vars_are_normalized_to_arrays( $value, array $expected ): void {
		$query      = new WP_Query();
		$query_vars = $query->fill_query_vars( array_fill_keys( self::LIST_QUERY_VARS, $value ) );

		foreach ( self::LIST_QUERY_VARS as $query_var ) {
			$this->assertSame( $expected, $query_vars[ $query_var ], "Unexpected value for the '$query_var' query var." );
		}
	}

	/**
	 * Data provider for {@see self::test_list_query_vars_are_normalized_to_arrays()}.
	 *
	 * @return array<non-falsy-string, array{ 0: mixed, 1: mixed[] }>
	 */
	public static function data_list_query_var_values(): array {
		return array(
			'array'               => array( array( 1, 2 ), array( 1, 2 ) ),
			'nested array'        => array(
				array( 1, array( 2 ), 'a', null, new stdClass() ),
				array(
					0 => 1,
					2 => 'a',
				),
			),
			'empty array'         => array( array(), array() ),
			'empty string'        => array( '', array() ),
			'zero'                => array( 0, array() ),
			'zero string'         => array( '0', array() ),
			'false'               => array( false, array() ),
			'null'                => array( null, array() ),
			'integer'             => array( 5, array( 5 ) ),
			'numeric string'      => array( '5', array( '5' ) ),
			'non-numeric string'  => array( 'string', array( 'string' ) ),
			'comma-separated IDs' => array( '1,2', array( '1,2' ) ),
			'true'                => array( true, array( true ) ),
			'object'              => array( new stdClass(), array() ),
		);
	}

	/**
	 * Tests that a list query var given a non-empty value of the wrong type does not cause an error.
	 *
	 * @ticket 60745
	 *
	 * @dataProvider data_list_query_vars
	 *
	 * @param string $query_var Query var name.
	 */
	public function test_list_query_var_given_string_does_not_cause_error( string $query_var ): void {
		self::factory()->post->create();

		$query = new WP_Query( array( $query_var => 'string' ) );

		$this->assertIsArray( $query->posts );
	}

	/**
	 * Tests that a list query var given a multidimensional array does not cause an error.
	 *
	 * @ticket 60745
	 *
	 * @dataProvider data_list_query_vars
	 *
	 * @param string $query_var Query var name.
	 */
	public function test_list_query_var_given_multidimensional_array_does_not_cause_error( string $query_var ): void {
		self::factory()->post->create();

		$query = new WP_Query( array( $query_var => array( 1, array( 'admin' ) ) ) );

		$this->assertIsArray( $query->posts );
	}

	/**
	 * Data provider for {@see self::test_list_query_var_given_string_does_not_cause_error()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string }>
	 */
	public static function data_list_query_vars(): array {
		$data = array();
		foreach ( self::LIST_QUERY_VARS as $query_var ) {
			$data[ $query_var ] = array( $query_var );
		}
		return $data;
	}

	/**
	 * Tests that the 'cat' query var given an array ignores values which are not scalar.
	 *
	 * @ticket 60745
	 */
	public function test_cat_given_array_with_non_scalar_value_does_not_cause_error(): void {
		$category_id = self::factory()->category->create();
		$post_id     = self::factory()->post->create( array( 'post_category' => array( $category_id ) ) );
		self::factory()->post->create();

		$query = new WP_Query(
			array(
				'cat'    => array( $category_id, array( 'admin' ) ),
				'fields' => 'ids',
			)
		);

		$this->assertSame( array( $post_id ), $query->posts );
	}

	/**
	 * Tests that a scalar post ID queries the same as an array containing that ID.
	 *
	 * @ticket 60745
	 */
	public function test_post__in_given_scalar_matches_array(): void {
		$post_ids = self::factory()->post->create_many( 3 );

		$scalar_query = new WP_Query(
			array(
				'post__in' => $post_ids[1],
				'fields'   => 'ids',
			)
		);
		$array_query  = new WP_Query(
			array(
				'post__in' => array( $post_ids[1] ),
				'fields'   => 'ids',
			)
		);

		$this->assertSame( array( $post_ids[1] ), $scalar_query->posts );
		$this->assertSame( $array_query->posts, $scalar_query->posts );
	}

	/**
	 * Tests that a non-numeric post__in value matches no posts instead of being ignored.
	 *
	 * @ticket 60745
	 */
	public function test_post__in_given_non_numeric_string_matches_no_posts(): void {
		self::factory()->post->create_many( 2 );

		$query = new WP_Query(
			array(
				'post__in' => 'string',
				'fields'   => 'ids',
			)
		);

		$this->assertSame( array(), $query->posts );
	}

	/**
	 * Tests that a list query var set to a scalar in a 'pre_get_posts' callback is normalized.
	 *
	 * @ticket 60745
	 */
	public function test_list_query_var_set_in_pre_get_posts_is_normalized(): void {
		$post_ids = self::factory()->post->create_many( 3 );

		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ) use ( $post_ids ): void {
				$query->set( 'post__in', $post_ids[2] );
			}
		);

		$query = new WP_Query( array( 'fields' => 'ids' ) );

		$this->assertSame( array( $post_ids[2] ), $query->posts );
		$this->assertSame( array( $post_ids[2] ), $query->query_vars['post__in'] );
	}
}
