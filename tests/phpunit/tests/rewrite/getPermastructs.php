<?php

/**
 * Tests for the structures of the authors, searches, pages, feeds and comment feeds of WP_Rewrite.
 *
 * @group rewrite
 *
 * @covers WP_Rewrite::get_author_permastruct
 * @covers WP_Rewrite::get_search_permastruct
 * @covers WP_Rewrite::get_page_permastruct
 * @covers WP_Rewrite::get_feed_permastruct
 * @covers WP_Rewrite::get_comment_feed_permastruct
 */
class Tests_Rewrite_GetPermastructs extends WP_UnitTestCase {

	/**
	 * Returns a WP_Rewrite object that is initialized for a permalink structure.
	 *
	 * @param string $structure Permalink structure.
	 * @param array  $bases     Optional. Properties of the object to set, such as the bases.
	 * @return WP_Rewrite The object.
	 */
	private function get_rewrite( $structure, $bases = array() ) {
		update_option( 'permalink_structure', $structure );

		$wp_rewrite = new WP_Rewrite();
		$wp_rewrite->init();

		foreach ( $bases as $property => $value ) {
			$wp_rewrite->$property = $value;
		}

		return $wp_rewrite;
	}

	/**
	 * Tests the structure that each method returns, for permalink structures and bases.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_structures
	 *
	 * @param string $method    Name of the method.
	 * @param string $structure Permalink structure.
	 * @param array  $bases     Properties of the object to set.
	 * @param string $expected  Expected structure.
	 */
	public function test_should_return_the_structure( $method, $structure, $bases, $expected ) {
		$this->assertSame( $expected, $this->get_rewrite( $structure, $bases )->$method() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_structures() {
		return array(
			'author'                         => array( 'get_author_permastruct', '/%postname%/', array(), '/author/%author%' ),
			'author with a front'            => array( 'get_author_permastruct', '/blog/%postname%/', array(), '/blog/author/%author%' ),
			'author with index.php'          => array( 'get_author_permastruct', '/index.php/%postname%/', array(), '/index.php/author/%author%' ),
			'author with another base'       => array( 'get_author_permastruct', '/%postname%/', array( 'author_base' => 'writers' ), '/writers/%author%' ),

			'search'                         => array( 'get_search_permastruct', '/%postname%/', array(), 'search/%search%' ),
			'search ignores the front'       => array( 'get_search_permastruct', '/blog/%postname%/', array(), 'search/%search%' ),
			'search with index.php'          => array( 'get_search_permastruct', '/index.php/%postname%/', array(), 'index.php/search/%search%' ),
			'search with another base'       => array( 'get_search_permastruct', '/%postname%/', array( 'search_base' => 'find' ), 'find/%search%' ),

			'page'                           => array( 'get_page_permastruct', '/%postname%/', array(), '%pagename%' ),
			'page ignores the front'         => array( 'get_page_permastruct', '/blog/%postname%/', array(), '%pagename%' ),
			'page with index.php'            => array( 'get_page_permastruct', '/index.php/%postname%/', array(), 'index.php/%pagename%' ),

			'feed'                           => array( 'get_feed_permastruct', '/%postname%/', array(), 'feed/%feed%' ),
			'feed ignores the front'         => array( 'get_feed_permastruct', '/blog/%postname%/', array(), 'feed/%feed%' ),
			'feed with index.php'            => array( 'get_feed_permastruct', '/index.php/%postname%/', array(), 'index.php/feed/%feed%' ),
			'feed with another base'         => array( 'get_feed_permastruct', '/%postname%/', array( 'feed_base' => 'rss' ), 'rss/%feed%' ),

			'comment feed'                   => array( 'get_comment_feed_permastruct', '/%postname%/', array(), 'comments/feed/%feed%' ),
			'comment feed ignores the front' => array( 'get_comment_feed_permastruct', '/blog/%postname%/', array(), 'comments/feed/%feed%' ),
			'comment feed with index.php'    => array( 'get_comment_feed_permastruct', '/index.php/%postname%/', array(), 'index.php/comments/feed/%feed%' ),
			'comment feed with other bases'  => array(
				'get_comment_feed_permastruct',
				'/%postname%/',
				array(
					'comments_base' => 'remarks',
					'feed_base'     => 'rss',
				),
				'remarks/rss/%feed%',
			),
		);
	}

	/**
	 * Tests that false is returned without permalinks, and that the structure is then empty.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_methods_and_properties
	 *
	 * @param string $method   Name of the method.
	 * @param string $property Name of the property that holds the structure.
	 */
	public function test_should_return_false_without_permalinks( $method, $property ) {
		$wp_rewrite = $this->get_rewrite( '' );

		$this->assertFalse( $wp_rewrite->$method() );
		$this->assertSame( '', $wp_rewrite->$property );
	}

	/**
	 * Tests that the structure is kept after it was built, and is built again when the object is initialized again.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_methods_and_properties
	 *
	 * @param string $method    Name of the method.
	 * @param string $property  Name of the property that holds the structure.
	 * @param bool   $uses_base Whether the structure has a base.
	 */
	public function test_should_keep_the_structure_until_the_object_is_initialized_again( $method, $property, $uses_base ) {
		$wp_rewrite = $this->get_rewrite( '/%postname%/' );
		$first      = $wp_rewrite->$method();

		$this->assertSame( $first, $wp_rewrite->$property, 'The structure should be stored in the property.' );

		$wp_rewrite->author_base         = 'changed';
		$wp_rewrite->search_base         = 'changed';
		$wp_rewrite->feed_base           = 'changed';
		$wp_rewrite->comments_base       = 'changed';
		$wp_rewrite->root                = 'changed/';
		$wp_rewrite->permalink_structure = '/index.php/%postname%/';

		$this->assertSame( $first, $wp_rewrite->$method(), 'The structure should not change after it was built.' );

		update_option( 'permalink_structure', '/index.php/%postname%/' );
		$wp_rewrite->init();

		$this->assertNotSame( $first, $wp_rewrite->$method(), 'The structure should be built again after the object is initialized again.' );

		if ( $uses_base ) {
			$this->assertStringContainsString( 'changed', $wp_rewrite->$method(), 'The bases of the object should be used.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_methods_and_properties() {
		return array(
			'author'       => array( 'get_author_permastruct', 'author_structure', true ),
			'search'       => array( 'get_search_permastruct', 'search_structure', true ),
			'page'         => array( 'get_page_permastruct', 'page_structure', false ),
			'feed'         => array( 'get_feed_permastruct', 'feed_structure', true ),
			'comment feed' => array( 'get_comment_feed_permastruct', 'comment_feed_structure', true ),
		);
	}
}
