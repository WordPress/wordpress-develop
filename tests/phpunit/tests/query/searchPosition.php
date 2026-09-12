<?php
/**
 * Test cases for the search position feature.
 *
 * @group query
 * @group search
 *
 * @covers WP_Query::parse_search
 *
 * @since 7.2.0
 */
class Tests_Query_SearchPosition extends WP_UnitTestCase {
	/**
	 * The post ID of the test post.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Create posts fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory The factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'one two three',
				'post_content' => 'four five six',
			)
		);
	}

	/**
	 * Tests that search_position parameter works with different values.
	 *
	 * @ticket 64250
	 *
	 * @dataProvider data_search_position_with_different_values
	 *
	 * @param string $search_term     The search term to use.
	 * @param string $search_position The search position value.
	 * @param bool   $should_find     Whether the post should be found.
	 */
	public function test_search_position_with_different_values( $search_term, $search_position, $should_find ) {
		$q = new WP_Query(
			array(
				's'               => $search_term,
				'search_position' => $search_position,
				'fields'          => 'ids',
			)
		);

		if ( $should_find ) {
			$this->assertContains( self::$post_id, $q->posts, "Post should be found when searching for '{$search_term}' with search_position '{$search_position}'." );
		} else {
			$this->assertNotContains( self::$post_id, $q->posts, "Post should not be found when searching for '{$search_term}' with search_position '{$search_position}'." );
		}
	}

	/**
	 * Data provider for search position tests.
	 *
	 * @return array
	 */
	public function data_search_position_with_different_values() {
		return array(
			// Test 'start' position.
			'start - match at start'            => array(
				'search_term'     => 'one',
				'search_position' => 'start',
				'should_find'     => true,
			),
			'start - match at middle'           => array(
				'search_term'     => 'two',
				'search_position' => 'start',
				'should_find'     => false,
			),
			'start - match at end'              => array(
				'search_term'     => 'three',
				'search_position' => 'start',
				'should_find'     => false,
			),

			// Test 'end' position.
			'end - match at start'              => array(
				'search_term'     => 'one',
				'search_position' => 'end',
				'should_find'     => false,
			),
			'end - match at middle'             => array(
				'search_term'     => 'two',
				'search_position' => 'end',
				'should_find'     => false,
			),
			'end - match at end'                => array(
				'search_term'     => 'three',
				'search_position' => 'end',
				'should_find'     => true,
			),

			// Test 'anywhere' position (default behavior).
			'anywhere - match at start'         => array(
				'search_term'     => 'one',
				'search_position' => 'anywhere',
				'should_find'     => true,
			),
			'anywhere - match at middle'        => array(
				'search_term'     => 'two',
				'search_position' => 'anywhere',
				'should_find'     => true,
			),
			'anywhere - match at end'           => array(
				'search_term'     => 'three',
				'search_position' => 'anywhere',
				'should_find'     => true,
			),

			// Test empty/default search_position (should act like 'anywhere').
			'default - match at start'          => array(
				'search_term'     => 'one',
				'search_position' => '',
				'should_find'     => true,
			),
			'default - match at middle'         => array(
				'search_term'     => 'two',
				'search_position' => '',
				'should_find'     => true,
			),
			'default - match at end'            => array(
				'search_term'     => 'three',
				'search_position' => '',
				'should_find'     => true,
			),

			// Test case sensitivity.
			'start - uppercase match'           => array(
				'search_term'     => 'ONE',
				'search_position' => 'start',
				'should_find'     => true,
			),
			'end - uppercase match'             => array(
				'search_term'     => 'THREE',
				'search_position' => 'end',
				'should_find'     => true,
			),
			'anywhere - uppercase match'        => array(
				'search_term'     => 'TWO',
				'search_position' => 'anywhere',
				'should_find'     => true,
			),

			// Test partial word matches.
			'start - partial word at start'     => array(
				'search_term'     => 'on',
				'search_position' => 'start',
				'should_find'     => true,
			),
			'end - partial word at end'         => array(
				'search_term'     => 'ree',
				'search_position' => 'end',
				'should_find'     => true,
			),
			'anywhere - partial word at middle' => array(
				'search_term'     => 'tw',
				'search_position' => 'anywhere',
				'should_find'     => true,
			),
		);
	}

	/**
	 * Tests that search_position works with search_columns parameter.
	 *
	 * @ticket 64250
	 */
	public function test_search_position_with_search_columns() {
		// Search at start of post_title only.
		$q = new WP_Query(
			array(
				's'               => 'one',
				'search_position' => 'start',
				'search_columns'  => array( 'post_title' ),
				'fields'          => 'ids',
			)
		);
		$this->assertContains( self::$post_id, $q->posts, 'Should find post when searching for "one" at start of post_title.' );

		// Search at start of post_content.
		$q = new WP_Query(
			array(
				's'               => 'four',
				'search_position' => 'start',
				'search_columns'  => array( 'post_content' ),
				'fields'          => 'ids',
			)
		);
		$this->assertContains( self::$post_id, $q->posts, 'Should find post when searching for "four" at start of post_content.' );

		// Search at start but term is in middle.
		$q = new WP_Query(
			array(
				's'               => 'two',
				'search_position' => 'start',
				'search_columns'  => array( 'post_title' ),
				'fields'          => 'ids',
			)
		);
		$this->assertNotContains( self::$post_id, $q->posts, 'Should not find post when searching for "two" at start (it is in middle).' );

		// Search at end of post_title.
		$q = new WP_Query(
			array(
				's'               => 'three',
				'search_position' => 'end',
				'search_columns'  => array( 'post_title' ),
				'fields'          => 'ids',
			)
		);
		$this->assertContains( self::$post_id, $q->posts, 'Should find post when searching for "three" at end of post_title.' );

		// Search at end of post_content.
		$q = new WP_Query(
			array(
				's'               => 'six',
				'search_position' => 'end',
				'search_columns'  => array( 'post_content' ),
				'fields'          => 'ids',
			)
		);
		$this->assertContains( self::$post_id, $q->posts, 'Should find post when searching for "six" at end of post_content.' );

		// Search a term that exists only in post_content, restricted to post_title.
		$q = new WP_Query(
			array(
				's'               => 'four',
				'search_position' => 'start',
				'search_columns'  => array( 'post_title' ),
				'fields'          => 'ids',
			)
		);
		$this->assertNotContains( self::$post_id, $q->posts, 'Should not find post when searching for "four" restricted to post_title.' );
	}

	/**
	 * Tests that invalid search_position values fall back to default behavior.
	 *
	 * @ticket 64250
	 *
	 * @dataProvider data_search_position_with_invalid_value
	 *
	 * @param mixed $search_position The invalid search_position value to use.
	 */
	public function test_search_position_with_invalid_value( $search_position ) {
		$q = new WP_Query(
			array(
				's'               => 'two',
				'search_position' => $search_position,
				'fields'          => 'ids',
			)
		);

		// Should fall back to default 'anywhere' behavior and find the post.
		$this->assertContains( self::$post_id, $q->posts, 'Should find post with invalid search_position (fallback to default).' );
		$this->assertSame( 'anywhere', $q->query_vars['search_position'], 'search_position should be set to "anywhere" (fallback to default).' );
	}

	/**
	 * Data provider for invalid search_position values.
	 *
	 * @return array
	 */
	public function data_search_position_with_invalid_value() {
		return array(
			'null'        => array( null ),
			'true'        => array( true ),
			'false'       => array( false ),
			'int'         => array( 123 ),
			'float'       => array( 123.456 ),
			'string'      => array( 'test' ),
			'empty'       => array( '' ),
			'zero'        => array( 0 ),
			'negative'    => array( -1 ),
			'array'       => array( array( 'test' ) ),
			'empty array' => array( array() ),
		);
	}

	/**
	 * Tests that search_position works with multiple search terms.
	 *
	 * @ticket 64250
	 */
	public function test_search_position_with_multiple_terms() {
		// Both terms at start should find the post.
		$q = new WP_Query(
			array(
				's'               => 'one two',
				'search_position' => 'start',
				'fields'          => 'ids',
			)
		);
		$this->assertNotContains( self::$post_id, $q->posts, 'Should not find post when searching for "one two" at start.' );

		// Mixed positions - one at start, one at end.
		$q = new WP_Query(
			array(
				's'               => 'one three',
				'search_position' => 'start',
				'fields'          => 'ids',
			)
		);
		// This behavior depends on implementation - assuming any term matching position finds the post.
		$this->assertNotContains( self::$post_id, $q->posts, 'Should not find post when any search term matches the position criteria.' );

		// Both terms at end using 'end' position.
		$q = new WP_Query(
			array(
				's'               => 'three',
				'search_position' => 'end',
				'fields'          => 'ids',
			)
		);
		$this->assertContains( self::$post_id, $q->posts, 'Should find post when searching for "three" at end.' );
	}

	/**
	 * Tests that search_position works with sentence search.
	 *
	 * @ticket 64250
	 */
	public function test_search_position_with_sentence() {
		// Exact phrase at start.
		$q = new WP_Query(
			array(
				's'               => 'one two',
				'search_position' => 'start',
				'sentence'        => true,
				'fields'          => 'ids',
			)
		);
		$this->assertContains( self::$post_id, $q->posts, 'Should find post when searching for phrase "one two" at start with sentence mode.' );

		// Exact phrase not at start.
		$q = new WP_Query(
			array(
				's'               => 'two three',
				'search_position' => 'start',
				'sentence'        => true,
				'fields'          => 'ids',
			)
		);
		$this->assertNotContains( self::$post_id, $q->posts, 'Should not find post when searching for phrase "two three" at start (phrase is in middle/end).' );

		// Exact phrase at end.
		$q = new WP_Query(
			array(
				's'               => 'two three',
				'search_position' => 'end',
				'sentence'        => true,
				'fields'          => 'ids',
			)
		);
		$this->assertContains( self::$post_id, $q->posts, 'Should find post when searching for phrase "two three" at end with sentence mode.' );
	}
}
