<?php

/**
 * @group rewrite
 */
class Tests_Rewrite_Permastructs extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * @covers ::add_permastruct
	 */
	public function test_add_permastruct() {
		global $wp_rewrite;

		add_permastruct( 'foo', 'bar/%foo%' );
		$this->assertSameSetsWithIndex(
			array(
				'with_front'  => true,
				'ep_mask'     => EP_NONE,
				'paged'       => true,
				'feed'        => true,
				'walk_dirs'   => true,
				'endpoints'   => true,
				'forcomments' => false,
				'struct'      => '/bar/%foo%',
			),
			$wp_rewrite->extra_permastructs['foo']
		);
	}

	/**
	 * @covers ::remove_permastruct
	 */
	public function test_remove_permastruct() {
		global $wp_rewrite;

		add_permastruct( 'foo', 'bar/%foo%' );
		$this->assertIsArray( $wp_rewrite->extra_permastructs['foo'] );
		$this->assertSame( '/bar/%foo%', $wp_rewrite->extra_permastructs['foo']['struct'] );

		remove_permastruct( 'foo' );
		$this->assertArrayNotHasKey( 'foo', $wp_rewrite->extra_permastructs );
	}
}
