<?php

/**
 * @group post
 *
 * @covers ::_truncate_post_slug
 */
class Tests_Post_TruncatePostSlug extends WP_UnitTestCase {

	/**
	 * Tests that _truncate_post_slug() correctly truncates slugs.
	 *
	 * @ticket 56868
	 *
	 * @dataProvider data_truncate_post_slug_should_truncate
	 *
	 * @param string $slug     The slug to truncate.
	 * @param int    $length   Max length of the slug.
	 * @param string $expected The expected truncated slug.
	 * @param string $message  Test feedback message.
	 */
	public function test_truncate_post_slug_should_truncate( $slug, $length, $expected, $message ) {
		$this->assertSame( $expected, _truncate_post_slug( $slug, $length ), $message );
	}

	/**
	 * Data provider for test_truncate_post_slug_should_truncate().
	 *
	 * @return array[]
	 */
	public function data_truncate_post_slug_should_truncate() {
		return array(
			'a slug that is too long'                      => array(
				'slug'     => 'truncated slug',
				'length'   => 9,
				'expected' => 'truncated',
				'message'  => '"truncated slug" should have been truncated to "truncated".',
			),
			'a slug that is too long and ends with a dash' => array(
				'slug'     => 'truncated-slug',
				'length'   => 10,
				'expected' => 'truncated',
				'message'  => '"truncated-slug" should have been truncated to "truncated".',
			),

			// URL-encoded characters.
			'URL-encoded characters and "length" includes the first URL-encoded character' => array(
				'slug'     => 'myslug%2F',
				'length'   => 7,
				'expected' => 'myslug',
				'message'  => '"myslug%2F" should have been truncated to "myslug".',
			),
			'URL-encoded characters and "length" includes the second URL-encoded character' => array(
				'slug'     => 'myslug%2F',
				'length'   => 8,
				'expected' => 'myslug',
				'message'  => '"myslug%2F" should have been truncated to "myslug".',
			),
			'URL-encoded characters and "length" includes the third URL-encoded character' => array(
				'slug'     => 'myslug%2F',
				'length'   => 9,
				'expected' => 'myslug%2F',
				'message'  => '"myslug%2F" should have been truncated to "myslug%2F".',
			),

			// URL-encoded accent characters.
			'URL-encoded accent characters and "length" includes the first URL-encoded character' => array(
				'slug'     => 'myslug%C4%85',
				'length'   => 7,
				'expected' => 'myslug',
				'message'  => '"myslug%C4%85" should have been truncated to "myslug".',
			),
			'URL-encoded accent characters and "length" includes the second URL-encoded character' => array(
				'slug'     => 'myslug%C4%85',
				'length'   => 8,
				'expected' => 'myslug',
				'message'  => '"myslug%C4%85" should have been truncated to "myslug".',
			),
			'URL-encoded accent characters and "length" includes the third URL-encoded character' => array(
				'slug'     => 'myslug%C4%85',
				'length'   => 9,
				'expected' => 'myslug',
				'message'  => '"myslug%C4%85" should have been truncated to "myslug".',
			),
			'URL-encoded accent characters and "length" includes the fourth URL-encoded character' => array(
				'slug'     => 'myslug%C4%85',
				'length'   => 10,
				'expected' => 'myslug',
				'message'  => '"myslug%C4%85" should have been truncated to "myslug".',
			),
			'URL-encoded accent characters and "length" includes the fifth URL-encoded character' => array(
				'slug'     => 'myslug%C4%85',
				'length'   => 11,
				'expected' => 'myslug',
				'message'  => '"myslug%C4%85" should have been truncated to "myslug".',
			),
			'URL-encoded accent characters and "length" includes the fifth URL-encoded character' => array(
				'slug'     => 'myslug%C4%85',
				'length'   => 12,
				'expected' => 'myslug%C4%85',
				'message'  => '"myslug%C4%85" should have been truncated to "myslug%C4%85".',
			),
		);
	}
}
