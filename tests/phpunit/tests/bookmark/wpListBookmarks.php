<?php

/**
 * Test wp_list_bookmarks().
 *
 * @group bookmark
 * @covers ::wp_list_bookmarks
 */
class Tests_Functions_wpListBookmarks extends WP_UnitTestCase {

	/**
	 * Test that wp_list_bookmarks adds "noopener" to the "rel" attribute.
	 *
	 * @dataProvider data_wp_list_bookmarks_adds_noopener
	 *
	 * @ticket 53839
	 *
	 * @param array $args      The arguments to create the bookmark.
	 * @param string $expected Expected string to test.
	 */
	public function test_wp_list_bookmarks_adds_noopener( $args, $expected ) {
		self::factory()->bookmark->create( $args );
		$this->assertStringContainsString( $expected, wp_list_bookmarks( 'echo=0' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_wp_list_bookmarks_adds_noopener() {
		return array(
			'target as "_blank"'                         => array(
				'args'     => array(
					'link_name'   => 'With _blank',
					'link_url'    => 'https://www.wordpress.org',
					'link_target' => '_blank',
				),
				'expected' => 'rel="noopener"',
			),
			'target as "_blank" and a link relationship' => array(
				'args'     => array(
					'link_name'   => 'With _blank and a link relationship',
					'link_url'    => 'https://www.wordpress.org',
					'link_target' => '_blank',
					'link_rel'    => 'me',
				),
				'expected' => 'rel="me noopener"',
			),
			'target as "_top"'                           => array(
				'args'     => array(
					'link_name'   => 'With _top',
					'link_url'    => 'https://www.wordpress.org',
					'link_target' => '_top',
				),
				'expected' => 'rel="noopener"',
			),
			'target as "_top" and a link relationship'   => array(
				'args'     => array(
					'link_name'   => 'With _top and a link relationship',
					'link_url'    => 'https://www.wordpress.org',
					'link_target' => '_top',
					'link_rel'    => 'me',
				),
				'expected' => 'rel="me noopener"',
			),
		);
	}

	/**
	 * Test that wp_list_bookmarks does not add "noopener" to the "rel" attribute.
	 *
	 * @dataProvider data_wp_list_bookmarks_does_not_add_noopener
	 *
	 * @ticket 53839
	 *
	 * @param array $args The arguments to create the bookmark.
	 */
	public function test_wp_list_bookmarks_does_not_add_noopener( $args ) {
		self::factory()->bookmark->create( $args );
		$this->assertStringNotContainsString( 'noopener', wp_list_bookmarks( 'echo=0' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_wp_list_bookmarks_does_not_add_noopener() {
		return array(
			'target as "_none"'                         => array(
				'args' => array(
					'link_name'   => 'With _blank',
					'link_url'    => 'https://www.wordpress.org',
					'link_target' => '_none',
				),
			),
			'target as "_none" and a link relationship' => array(
				'args' => array(
					'link_name'   => 'With _blank and a link relationship',
					'link_url'    => 'https://www.wordpress.org',
					'link_target' => '_none',
					'link_rel'    => 'me',
				),
			),
		);
	}
}
