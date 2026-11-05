<?php

/**
 * Tests for WP_Rewrite::using_permalinks() and WP_Rewrite::using_mod_rewrite_permalinks().
 *
 * @group rewrite
 *
 * @covers WP_Rewrite::using_permalinks
 * @covers WP_Rewrite::using_mod_rewrite_permalinks
 */
class Tests_Rewrite_UsingPermalinks extends WP_UnitTestCase {

	/**
	 * Returns a WP_Rewrite object with a permalink structure.
	 *
	 * @param string $structure Permalink structure.
	 * @return WP_Rewrite The object.
	 */
	private function get_rewrite( $structure ) {
		$wp_rewrite                      = new WP_Rewrite();
		$wp_rewrite->permalink_structure = $structure;

		return $wp_rewrite;
	}

	/**
	 * Tests whether pretty permalinks are used, for each structure.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_structures
	 *
	 * @param string $structure       Permalink structure.
	 * @param bool   $using_permalinks Whether permalinks are used.
	 */
	public function test_using_permalinks( $structure, $using_permalinks ) {
		$this->assertSame( $using_permalinks, $this->get_rewrite( $structure )->using_permalinks() );
	}

	/**
	 * Tests whether mod_rewrite permalinks are used, for each structure.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_structures
	 *
	 * @param string $structure         Permalink structure.
	 * @param bool   $using_permalinks  Whether permalinks are used.
	 * @param bool   $using_mod_rewrite Whether mod_rewrite permalinks are used.
	 */
	public function test_using_mod_rewrite_permalinks( $structure, $using_permalinks, $using_mod_rewrite ) {
		$this->assertSame( $using_mod_rewrite, $this->get_rewrite( $structure )->using_mod_rewrite_permalinks() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_structures() {
		return array(
			'plain permalinks'                     => array( '', false, false ),
			'post name'                            => array( '/%postname%/', true, true ),
			'day and name'                         => array( '/%year%/%monthnum%/%day%/%postname%/', true, true ),
			'post ID'                              => array( '/archives/%post_id%', true, true ),
			'index at the start'                   => array( '/index.php/%postname%/', true, false ),
			'index at the start, no leading slash' => array( 'index.php/%postname%/', true, false ),
			'index at the start, many slashes'     => array( '//index.php/%year%/%postname%/', true, false ),
			'index later in the structure'         => array( '/%year%/index.php/%postname%/', true, true ),
			'index in a longer name'               => array( '/my-index.php/%postname%/', true, true ),
		);
	}

	/**
	 * Tests that both methods return a boolean.
	 *
	 * @ticket 66496
	 */
	public function test_should_return_booleans() {
		$this->assertIsBool( $this->get_rewrite( '/%postname%/' )->using_permalinks() );
		$this->assertIsBool( $this->get_rewrite( '' )->using_permalinks() );
		$this->assertIsBool( $this->get_rewrite( '/%postname%/' )->using_mod_rewrite_permalinks() );
		$this->assertIsBool( $this->get_rewrite( '' )->using_mod_rewrite_permalinks() );
	}

	/**
	 * Tests that the name of the index file is used when it is not the default.
	 *
	 * @ticket 66496
	 */
	public function test_should_use_the_index_of_the_object() {
		$wp_rewrite        = $this->get_rewrite( '/front.php/%postname%/' );
		$wp_rewrite->index = 'front.php';

		$this->assertTrue( $wp_rewrite->using_permalinks() );
		$this->assertFalse( $wp_rewrite->using_mod_rewrite_permalinks() );

		$wp_rewrite->index = 'index.php';

		$this->assertTrue( $wp_rewrite->using_mod_rewrite_permalinks() );
	}

	/**
	 * Tests the global object, after the permalink structure is set.
	 *
	 * @ticket 66496
	 */
	public function test_should_follow_the_permalink_structure_of_the_site() {
		global $wp_rewrite;

		$this->set_permalink_structure( '/%postname%/' );
		$this->assertTrue( $wp_rewrite->using_permalinks() );
		$this->assertTrue( $wp_rewrite->using_mod_rewrite_permalinks() );

		$this->set_permalink_structure( '/index.php/%postname%/' );
		$this->assertTrue( $wp_rewrite->using_permalinks() );
		$this->assertFalse( $wp_rewrite->using_mod_rewrite_permalinks() );

		$this->set_permalink_structure( '' );
		$this->assertFalse( $wp_rewrite->using_permalinks() );
		$this->assertFalse( $wp_rewrite->using_mod_rewrite_permalinks() );
	}
}
