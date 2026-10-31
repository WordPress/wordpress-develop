<?php

/**
 * Tests for the `get_search_link()` function.
 *
 * @group link
 * @covers ::get_search_link
 */
class Tests_Link_GetSearchLink extends WP_UnitTestCase {

	/**
	 * Original search query global.
	 *
	 * @var string
	 */
	private static $original_search_query;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$original_search_query = $GLOBALS['wp_query']->query_vars['s'] ?? '';
	}

	public function tear_down() {
		$GLOBALS['wp_query']->query_vars['s'] = self::$original_search_query;
		parent::tear_down();
	}

	/**
	 * Tests get_search_link() with plain permalinks and explicit query.
	 *
	 * @ticket 65819
	 * @dataProvider data_plain_permastruct_queries
	 *
	 * @param string $query    Search query string.
	 * @param string $expected Expected query parameter.
	 */
	public function test_get_search_link_plain_permastruct( $query, $expected ) {
		$this->set_permalink_structure( '' );

		$this->assertSame( home_url( '?s=' . $expected ), get_search_link( $query ) );
	}

	/**
	 * Data provider for test_get_search_link_plain_permastruct.
	 *
	 * @return array
	 */
	public static function data_plain_permastruct_queries() {
		return array(
			'special characters'     => array( 'core & test', 'core+%26+test' ),
			'slashes to be stripped' => array( "single\'quote", 'single%27quote' ),
		);
	}

	/**
	 * Tests get_search_link() with pretty permalinks.
	 *
	 * @ticket 65819
	 * @dataProvider data_pretty_permastruct_queries
	 *
	 * @param string $query    Search query string.
	 * @param string $expected Expected path relative to home_url().
	 */
	public function test_get_search_link_pretty_permastruct( $query, $expected ) {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertSame( home_url( $expected ), get_search_link( $query ) );
	}

	/**
	 * Data provider for test_get_search_link_pretty_permastruct.
	 *
	 * @return array
	 */
	public static function data_pretty_permastruct_queries() {
		return array(
			'words with spaces'  => array( 'hello world', '/search/hello+world/' ),
			'forward slash kept' => array( 'tag/subtag', '/search/tag/subtag/' ),
		);
	}

	/**
	 * Tests that get_search_link() defaults to the current query when $query is empty.
	 *
	 * @ticket 65819
	 */
	public function test_get_search_link_defaults_to_current_query() {
		$this->set_permalink_structure( '' );

		$GLOBALS['wp_query']->query_vars['s'] = 'current search';

		$this->assertSame( home_url( '?s=current+search' ), get_search_link() );
	}

	/**
	 * Tests that get_search_link() applies the 'search_link' filter.
	 *
	 * @ticket 65819
	 */
	public function test_get_search_link_applies_filter() {
		$this->set_permalink_structure( '' );

		add_filter(
			'search_link',
			static function ( $link, $search ) {
				return $link . '&filtered=' . $search;
			},
			10,
			2
		);

		$result = get_search_link( 'test' );

		$this->assertSame( home_url( '?s=test&filtered=test' ), $result );
	}
}
