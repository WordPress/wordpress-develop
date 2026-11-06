<?php

/**
 * Tests for WP_Rewrite::get_date_permastruct().
 *
 * @group rewrite
 *
 * @covers WP_Rewrite::get_date_permastruct
 */
class Tests_Rewrite_GetDatePermastruct extends WP_UnitTestCase {

	/**
	 * Returns a WP_Rewrite object that is initialized for a permalink structure.
	 *
	 * @param string $structure Permalink structure.
	 * @return WP_Rewrite The object.
	 */
	private function get_rewrite( $structure ) {
		update_option( 'permalink_structure', $structure );

		$wp_rewrite = new WP_Rewrite();
		$wp_rewrite->init();

		return $wp_rewrite;
	}

	/**
	 * Tests that the structure is the one of the date of the permalinks, or the default one.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_structures
	 *
	 * @param string $structure Permalink structure.
	 * @param string $expected  Expected structure of the dates.
	 */
	public function test_should_return_the_structure_of_the_date( $structure, $expected ) {
		$this->assertSame( $expected, $this->get_rewrite( $structure )->get_date_permastruct() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_structures() {
		return array(
			'post name uses the default order'     => array( '/%postname%/', '/%year%/%monthnum%/%day%' ),
			'year, month and day'                  => array( '/%year%/%monthnum%/%day%/%postname%/', '/%year%/%monthnum%/%day%' ),
			'day, month and year'                  => array( '/%day%/%monthnum%/%year%/%postname%/', '/%day%/%monthnum%/%year%' ),
			'month, day and year'                  => array( '/%monthnum%/%day%/%year%/%postname%/', '/%monthnum%/%day%/%year%' ),
			'only the year uses the default order' => array( '/%year%/%postname%/', '/%year%/%monthnum%/%day%' ),
			'a front before the date'              => array( '/blog/%year%/%monthnum%/%day%/%postname%/', '/blog/%year%/%monthnum%/%day%' ),
			'a front before the post name'         => array( '/blog/%postname%/', '/blog/%year%/%monthnum%/%day%' ),
			'index.php before the post name'       => array( '/index.php/%postname%/', '/index.php/%year%/%monthnum%/%day%' ),
		);
	}

	/**
	 * Tests that a "date/" part is added when the post ID is one of the first three tags.
	 *
	 * @ticket 66496
	 *
	 * @dataProvider data_structures_with_the_post_id
	 *
	 * @param string $structure Permalink structure.
	 * @param string $expected  Expected structure of the dates.
	 */
	public function test_should_add_a_date_part_when_the_post_id_is_one_of_the_first_three_tags( $structure, $expected ) {
		$this->assertSame( $expected, $this->get_rewrite( $structure )->get_date_permastruct() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_structures_with_the_post_id() {
		return array(
			'post ID first'        => array( '/archives/%post_id%', '/archives/date/%year%/%monthnum%/%day%' ),
			'post ID after a year' => array( '/%year%/%post_id%/', '/date/%year%/%monthnum%/%day%' ),
			'post ID third'        => array( '/%year%/%monthnum%/%post_id%/', '/date/%year%/%monthnum%/%day%' ),
			'post ID fourth'       => array( '/%year%/%monthnum%/%day%/%post_id%/', '/%year%/%monthnum%/%day%' ),
			'post ID fifth'        => array( '/%year%/%monthnum%/%day%/%hour%/%post_id%/', '/%year%/%monthnum%/%day%' ),
		);
	}

	/**
	 * Tests that false is returned without permalinks, and the structure is empty.
	 *
	 * @ticket 66496
	 */
	public function test_should_return_false_without_permalinks() {
		$wp_rewrite = $this->get_rewrite( '' );

		$this->assertFalse( $wp_rewrite->get_date_permastruct() );
		$this->assertSame( '', $wp_rewrite->date_structure );
	}

	/**
	 * Tests that the structure is kept after it was built.
	 *
	 * @ticket 66496
	 */
	public function test_should_keep_the_structure_after_it_was_built() {
		$wp_rewrite = $this->get_rewrite( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->assertSame( '/%year%/%monthnum%/%day%', $wp_rewrite->get_date_permastruct() );

		$wp_rewrite->permalink_structure = '/%day%/%monthnum%/%year%/%postname%/';

		$this->assertSame( '/%year%/%monthnum%/%day%', $wp_rewrite->get_date_permastruct(), 'The structure should come from the one that was built.' );
	}

	/**
	 * Tests that the structure is built again after the object is initialized again.
	 *
	 * @ticket 66496
	 */
	public function test_should_build_the_structure_again_after_init() {
		$wp_rewrite = $this->get_rewrite( '/%year%/%monthnum%/%day%/%postname%/' );
		$wp_rewrite->get_date_permastruct();

		update_option( 'permalink_structure', '/%day%/%monthnum%/%year%/%postname%/' );
		$wp_rewrite->init();

		$this->assertSame( '/%day%/%monthnum%/%year%', $wp_rewrite->get_date_permastruct() );
	}
}
