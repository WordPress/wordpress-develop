<?php

/**
 * Tests for WP_Rewrite::get_extra_permastruct(), get_category_permastruct() and get_tag_permastruct().
 *
 * @group rewrite
 *
 * @covers WP_Rewrite::get_extra_permastruct
 * @covers WP_Rewrite::get_category_permastruct
 * @covers WP_Rewrite::get_tag_permastruct
 */
class Tests_Rewrite_GetExtraPermastruct extends WP_UnitTestCase {

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
	 * Tests that the structure of an extra structure is returned.
	 *
	 * @ticket 66496
	 */
	public function test_should_return_the_structure_of_an_extra_permastruct() {
		$wp_rewrite                            = $this->get_rewrite( '/%postname%/' );
		$wp_rewrite->extra_permastructs['foo'] = array(
			'struct'  => '/foo/%foo%',
			'ep_mask' => EP_NONE,
		);

		$this->assertSame( '/foo/%foo%', $wp_rewrite->get_extra_permastruct( 'foo' ) );
	}

	/**
	 * Tests that false is returned for a name that does not exist.
	 *
	 * @ticket 66496
	 */
	public function test_should_return_false_for_a_name_that_does_not_exist() {
		$wp_rewrite = $this->get_rewrite( '/%postname%/' );

		$this->assertFalse( $wp_rewrite->get_extra_permastruct( 'a-name-that-does-not-exist' ) );
	}

	/**
	 * Tests that false is returned without permalinks, even when the structure exists.
	 *
	 * @ticket 66496
	 */
	public function test_should_return_false_without_permalinks() {
		$wp_rewrite                            = $this->get_rewrite( '' );
		$wp_rewrite->extra_permastructs['foo'] = array( 'struct' => '/foo/%foo%' );

		$this->assertFalse( $wp_rewrite->get_extra_permastruct( 'foo' ) );
		$this->assertFalse( $wp_rewrite->get_category_permastruct() );
		$this->assertFalse( $wp_rewrite->get_tag_permastruct() );
	}

	/**
	 * Tests that the structures of the categories and the tags are the extra structures with their names.
	 *
	 * @ticket 66496
	 */
	public function test_should_use_the_extra_structures_of_categories_and_tags() {
		$wp_rewrite                                 = $this->get_rewrite( '/%postname%/' );
		$wp_rewrite->extra_permastructs['category'] = array( 'struct' => '/topics/%category%' );
		$wp_rewrite->extra_permastructs['post_tag'] = array( 'struct' => '/labels/%post_tag%' );

		$this->assertSame( '/topics/%category%', $wp_rewrite->get_category_permastruct() );
		$this->assertSame( '/labels/%post_tag%', $wp_rewrite->get_tag_permastruct() );
		$this->assertSame( $wp_rewrite->get_extra_permastruct( 'category' ), $wp_rewrite->get_category_permastruct() );
		$this->assertSame( $wp_rewrite->get_extra_permastruct( 'post_tag' ), $wp_rewrite->get_tag_permastruct() );
	}

	/**
	 * Tests that false is returned for the categories and tags when their structures do not exist.
	 *
	 * @ticket 66496
	 */
	public function test_should_return_false_for_categories_and_tags_without_a_structure() {
		$wp_rewrite = $this->get_rewrite( '/%postname%/' );
		unset( $wp_rewrite->extra_permastructs['category'], $wp_rewrite->extra_permastructs['post_tag'] );

		$this->assertFalse( $wp_rewrite->get_category_permastruct() );
		$this->assertFalse( $wp_rewrite->get_tag_permastruct() );
	}

	/**
	 * Tests the structures of the categories and the tags of the site.
	 *
	 * @ticket 66496
	 */
	public function test_should_return_the_structures_of_the_categories_and_tags_of_the_site() {
		global $wp_rewrite;

		$this->set_permalink_structure( '/%postname%/' );

		$this->assertStringContainsString( '%category%', $wp_rewrite->get_category_permastruct() );
		$this->assertStringContainsString( '%post_tag%', $wp_rewrite->get_tag_permastruct() );
		$this->assertStringContainsString( 'category', $wp_rewrite->get_category_permastruct() );
		$this->assertStringContainsString( 'tag', $wp_rewrite->get_tag_permastruct() );
	}
}
