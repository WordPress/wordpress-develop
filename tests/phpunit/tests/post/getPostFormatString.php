<?php

/**
 * Tests for get_post_format_string() and get_post_format_slugs().
 *
 * @group post
 * @group post-formats
 */
class Tests_Post_GetPostFormatString extends WP_UnitTestCase {

	/**
	 * Tests that get_post_format_string() returns the translated label for a slug.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_format_string
	 *
	 * @dataProvider data_post_format_strings
	 *
	 * @param string $slug     Post format slug.
	 * @param string $expected Translated format label.
	 */
	public function test_get_post_format_string( $slug, $expected ) {
		$this->assertSame( $expected, get_post_format_string( $slug ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_post_format_strings() {
		return array(
			'empty slug is standard' => array( '', 'Standard' ),
			'known slug'             => array( 'aside', 'Aside' ),
			'unknown slug'           => array( 'nonexistent', '' ),
		);
	}

	/**
	 * Tests that get_post_format_slugs() returns slugs as both keys and values.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_post_format_slugs
	 */
	public function test_get_post_format_slugs() {
		$slugs = get_post_format_slugs();

		$this->assertSameSetsWithIndex(
			array(
				'standard' => 'standard',
				'aside'    => 'aside',
				'chat'     => 'chat',
				'gallery'  => 'gallery',
				'link'     => 'link',
				'image'    => 'image',
				'quote'    => 'quote',
				'status'   => 'status',
				'video'    => 'video',
				'audio'    => 'audio',
			),
			$slugs
		);
	}
}
