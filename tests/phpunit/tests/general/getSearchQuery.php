<?php

/**
 * Tests for the `get_search_query()` and `the_search_query()` functions.
 *
 * @group general
 * @group template
 * @group query
 */
class Tests_General_GetSearchQuery extends WP_UnitTestCase {

	/**
	 * Tests that get_search_query() returns the escaped search query by default.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_search_query
	 */
	public function test_get_search_query_escapes_by_default() {
		set_query_var( 's', 'Tom & "Jerry" <b>' );

		$this->assertSame( 'Tom &amp; &quot;Jerry&quot; &lt;b&gt;', get_search_query() );
	}

	/**
	 * Tests that get_search_query() returns the raw search query when $escaped is false.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_search_query
	 */
	public function test_get_search_query_unescaped() {
		set_query_var( 's', 'Tom & "Jerry" <b>' );

		$this->assertSame( 'Tom & "Jerry" <b>', get_search_query( false ) );
	}

	/**
	 * Tests that get_search_query() returns an empty string without a search.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_search_query
	 */
	public function test_get_search_query_without_search() {
		$this->assertSame( '', get_search_query() );
	}

	/**
	 * Tests that get_search_query() reads the search query from the main query.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_search_query
	 */
	public function test_get_search_query_reads_main_query() {
		$this->go_to( home_url( '/?s=pizza+night' ) );

		$this->assertSame( 'pizza night', get_search_query() );
	}

	/**
	 * Tests that the 'get_search_query' filter receives the raw query and its result is escaped.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_search_query
	 */
	public function test_get_search_query_filter() {
		set_query_var( 's', 'Tom & Jerry' );

		$filter = new MockAction();
		add_filter( 'get_search_query', array( $filter, 'filter' ) );
		add_filter(
			'get_search_query',
			static function ( $query ) {
				return '<i>' . $query . '</i>';
			},
			20
		);

		$this->assertSame( '&lt;i&gt;Tom &amp; Jerry&lt;/i&gt;', get_search_query(), 'The filtered query should be escaped.' );
		$this->assertSame( array( 'Tom & Jerry' ), $filter->get_args()[0], 'The filter should receive the raw query.' );
		$this->assertSame( '<i>Tom & Jerry</i>', get_search_query( false ), 'The filtered query should be returned unescaped.' );
	}

	/**
	 * Tests that the_search_query() displays the escaped search query.
	 *
	 * @ticket 65819
	 *
	 * @covers ::the_search_query
	 */
	public function test_the_search_query_displays_escaped_query() {
		set_query_var( 's', 'Tom & "Jerry" <b>' );

		$this->expectOutputString( 'Tom &amp; &quot;Jerry&quot; &lt;b&gt;' );

		the_search_query();
	}

	/**
	 * Tests the 'the_search_query' filter: it receives the unescaped query from the
	 * 'get_search_query' filter, and its result is escaped.
	 *
	 * @ticket 65819
	 *
	 * @covers ::the_search_query
	 */
	public function test_the_search_query_filter() {
		set_query_var( 's', 'Tom & Jerry' );

		add_filter(
			'get_search_query',
			static function ( $query ) {
				return $query . ' <3';
			}
		);

		$filter = new MockAction();
		add_filter( 'the_search_query', array( $filter, 'filter' ) );
		add_filter(
			'the_search_query',
			static function ( $query ) {
				return '"' . $query . '"';
			},
			20
		);

		$this->expectOutputString( '&quot;Tom &amp; Jerry &lt;3&quot;' );

		the_search_query();

		$this->assertSame( array( 'Tom & Jerry <3' ), $filter->get_args()[0], 'The filter should receive the unescaped query.' );
	}
}
