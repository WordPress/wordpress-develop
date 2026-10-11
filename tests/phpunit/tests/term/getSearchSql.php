<?php

/**
 * Tests for WP_Term_Query search SQL generation.
 *
 * @group taxonomy
 * @covers WP_Term_Query::get_search_sql
 */
class Tests_Term_GetSearchSql extends WP_UnitTestCase {

	/**
	 * Category ID for terms containing an ampersand.
	 *
	 * @var int
	 */
	private static $tour_travel_id;

	/**
	 * Tag ID for terms without special characters.
	 *
	 * @var int
	 */
	private static $burrito_id;

	/**
	 * Tag ID for terms with Wilbur.
	 *
	 * @var int
	 */
	private static $wilbur_id;

	/**
	 * Set up test fixtures before running tests.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$tour_travel_id = $factory->category->create(
			array(
				'name' => 'Tour & Travel',
				'slug' => 'tour-travel',
			)
		);

		self::$burrito_id = $factory->tag->create(
			array(
				'name' => 'Burrito',
				'slug' => 'burrito',
			)
		);

		self::$wilbur_id = $factory->tag->create(
			array(
				'name' => 'Wilbur',
				'slug' => 'wilbur',
			)
		);
	}

	/**
	 * Tests that category names containing ampersands are found when searching
	 * with raw characters, spaces, and HTML entities.
	 *
	 * @ticket 66279
	 *
	 * @dataProvider data_term_search_with_ampersand_queries
	 *
	 * @param string $search_term Search string to query.
	 */
	public function test_get_terms_search_with_ampersand_queries( $search_term ) {
		$found = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'search'     => $search_term,
				'fields'     => 'ids',
			)
		);

		$this->assertSameSets(
			array( self::$tour_travel_id ),
			$found,
			sprintf( 'Failed finding category for search query "%s".', $search_term )
		);
	}

	/**
	 * Data provider for ampersand search queries.
	 *
	 * @return array[] Array of search queries.
	 */
	public static function data_term_search_with_ampersand_queries() {
		return array(
			'ampersand followed by space'      => array( 'Tour & ' ),
			'ampersand followed by first word' => array( 'Tour & T' ),
			'full name with raw ampersand'     => array( 'Tour & Travel' ),
			'suffix with raw ampersand'        => array( '& Travel' ),
			'raw ampersand only'               => array( '&' ),
			'encoded entity followed by space' => array( 'Tour &amp; ' ),
			'full name with encoded entity'    => array( 'Tour &amp; Travel' ),
			'suffix with encoded entity'       => array( '&amp; Travel' ),
		);
	}

	/**
	 * Tests that standard term search without special characters continues to work as expected.
	 *
	 * @ticket 66279
	 */
	public function test_get_terms_search_without_special_characters() {
		$found = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
				'search'     => 'bur',
				'fields'     => 'ids',
			)
		);

		$this->assertSameSets(
			array( self::$burrito_id, self::$wilbur_id ),
			$found,
			'Search without entities should return matching terms.'
		);
	}

	/**
	 * Tests that the generated SQL contains a single clause when no entity conversion occurs,
	 * but expands to an OR clause when entities differ from the raw search term.
	 *
	 * @ticket 66279
	 */
	public function test_get_search_sql_structure() {
		$reflection_method = new ReflectionMethod( 'WP_Term_Query', 'get_search_sql' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection_method->setAccessible( true );
		}

		$query = new WP_Term_Query();

		// Case 1: Search term without special characters returns a single clause.
		$standard_sql = $reflection_method->invoke( $query, 'burrito' );
		$this->assertStringNotContainsString( ') OR ((', $standard_sql, 'Standard search should not create multiple OR groups.' );

		// Case 2: Search term with ampersand returns expanded OR clauses covering raw and encoded forms.
		$ampersand_sql = $reflection_method->invoke( $query, 'Tour & ' );
		$this->assertStringContainsString( ') OR ((', $ampersand_sql, 'Expanded search must join groups with OR.' );
		$this->assertStringContainsString( 'Tour & ', $ampersand_sql, 'SQL must match raw search term.' );
		$this->assertStringContainsString( 'Tour &amp; ', $ampersand_sql, 'SQL must match entity-encoded search term.' );
	}
}
