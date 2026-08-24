<?php
/**
 * Test cases for _walk_bookmarks().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 2.1.0
 *
 * @group bookmark
 * @group template
 *
 * @covers ::_walk_bookmarks
 */
class Tests_walk_bookmarks extends WP_UnitTestCase {

	/**
	 * Tests formatted output of a list of bookmarks with various arguments and configurations.
	 *
	 * @ticket 65957
	 *
	 * @dataProvider data_walk_bookmarks
	 *
	 * @param array        $bookmarks_data List of bookmark field arrays to create objects for.
	 * @param array|string $args           Arguments passed to _walk_bookmarks.
	 * @param string       $expected       Expected HTML output.
	 */
	public function test_walk_bookmarks( array $bookmarks_data, $args, string $expected ): void {
		$bookmarks = array();
		foreach ( $bookmarks_data as $data ) {
			$bookmarks[] = (object) $data;
		}

		$this->assertSame( $expected, _walk_bookmarks( $bookmarks, $args ) );
	}

	/**
	 * Data provider for test_walk_bookmarks.
	 *
	 * @return array<string, array{
	 *     bookmarks_data: array<int, array<string, mixed>>,
	 *     args: array<string, mixed>|string,
	 *     expected: string,
	 * }>
	 */
	public function data_walk_bookmarks(): array {
		return array(
			'empty bookmarks array'                    => array(
				'bookmarks_data' => array(),
				'args'           => array(),
				'expected'       => '',
			),
			'default arguments with standard bookmark' => array(
				'bookmarks_data' => array(
					array(
						'link_id'          => 1,
						'link_name'        => 'Example Site',
						'link_url'         => 'https://example.org',
						'link_description' => 'A great website',
						'link_image'       => '',
						'link_target'      => '',
						'link_rel'         => '',
						'link_rating'      => 0,
					),
				),
				'args'           => array(),
				'expected'       => "<li><a href=\"https://example.org\" title=\"A great website\">Example Site</a></li>\n",
			),
			'empty link_url falls back to hash'        => array(
				'bookmarks_data' => array(
					array(
						'link_id'          => 2,
						'link_name'        => 'No URL Link',
						'link_url'         => '',
						'link_description' => '',
						'link_image'       => '',
						'link_target'      => '',
						'link_rel'         => '',
						'link_rating'      => 0,
					),
				),
				'args'           => array(),
				'expected'       => "<li><a href=\"#\">No URL Link</a></li>\n",
			),
			'with link_rel and link_target'            => array(
				'bookmarks_data' => array(
					array(
						'link_id'          => 3,
						'link_name'        => 'External Link',
						'link_url'         => 'https://example.com',
						'link_description' => 'External resource',
						'link_image'       => '',
						'link_target'      => '_blank',
						'link_rel'         => 'nofollow noopener',
						'link_rating'      => 0,
					),
				),
				'args'           => array(),
				'expected'       => "<li><a href=\"https://example.com\" rel=\"nofollow noopener\" title=\"External resource\" target=\"_blank\">External Link</a></li>\n",
			),
			'show_description and show_rating with custom between' => array(
				'bookmarks_data' => array(
					array(
						'link_id'          => 4,
						'link_name'        => 'Rated Link',
						'link_url'         => 'https://example.org/rated',
						'link_description' => 'High quality site',
						'link_image'       => '',
						'link_target'      => '',
						'link_rel'         => '',
						'link_rating'      => 5,
					),
				),
				'args'           => array(
					'show_description' => 1,
					'show_rating'      => 1,
					'between'          => ' - ',
				),
				'expected'       => "<li><a href=\"https://example.org/rated\" title=\"High quality site\">Rated Link</a> - High quality site - 5</li>\n",
			),
			'show_images with absolute http url and show_name disabled' => array(
				'bookmarks_data' => array(
					array(
						'link_id'          => 5,
						'link_name'        => 'Image Link',
						'link_url'         => 'https://example.org/img',
						'link_description' => 'Site with logo',
						'link_image'       => 'https://example.org/logo.png',
						'link_target'      => '',
						'link_rel'         => '',
						'link_rating'      => 0,
					),
				),
				'args'           => array(
					'show_images' => 1,
					'show_name'   => 0,
				),
				'expected'       => "<li><a href=\"https://example.org/img\" title=\"Site with logo\"><img src=\"https://example.org/logo.png\" alt=\"Image Link\" title=\"Site with logo\" /></a></li>\n",
			),
			'show_images with absolute http url and show_name enabled' => array(
				'bookmarks_data' => array(
					array(
						'link_id'          => 6,
						'link_name'        => 'Image Link With Name',
						'link_url'         => 'https://example.org/img2',
						'link_description' => '',
						'link_image'       => 'https://example.org/logo2.png',
						'link_target'      => '',
						'link_rel'         => '',
						'link_rating'      => 0,
					),
				),
				'args'           => array(
					'show_images' => 1,
					'show_name'   => 1,
				),
				'expected'       => "<li><a href=\"https://example.org/img2\"><img src=\"https://example.org/logo2.png\" alt=\"Image Link With Name\" /> Image Link With Name</a></li>\n",
			),
			'show_images disabled when image exists'   => array(
				'bookmarks_data' => array(
					array(
						'link_id'          => 7,
						'link_name'        => 'Text Only Link',
						'link_url'         => 'https://example.org/text',
						'link_description' => '',
						'link_image'       => 'https://example.org/logo.png',
						'link_target'      => '',
						'link_rel'         => '',
						'link_rating'      => 0,
					),
				),
				'args'           => array(
					'show_images' => 0,
				),
				'expected'       => "<li><a href=\"https://example.org/text\">Text Only Link</a></li>\n",
			),
			'custom before, after, link_before, and link_after wrappers' => array(
				'bookmarks_data' => array(
					array(
						'link_id'          => 8,
						'link_name'        => 'Wrapped Link',
						'link_url'         => 'https://example.org/wrapped',
						'link_description' => '',
						'link_image'       => '',
						'link_target'      => '',
						'link_rel'         => '',
						'link_rating'      => 0,
					),
				),
				'args'           => array(
					'before'      => '<div class="bookmark-item">',
					'after'       => '</div>',
					'link_before' => '<span class="link-text">',
					'link_after'  => '</span>',
				),
				'expected'       => "<div class=\"bookmark-item\"><a href=\"https://example.org/wrapped\"><span class=\"link-text\">Wrapped Link</span></a></div>\n",
			),
		);
	}

	/**
	 * Tests show_images with a relative image URL.
	 *
	 * @ticket 65957
	 */
	public function test_walk_bookmarks_relative_image_url(): void {
		$bookmark = (object) array(
			'link_id'          => 10,
			'link_name'        => 'Relative Image Link',
			'link_url'         => 'https://example.org',
			'link_description' => '',
			'link_image'       => '/images/logo.png',
			'link_target'      => '',
			'link_rel'         => '',
			'link_rating'      => 0,
		);

		$expected = '<li><a href="https://example.org"><img src="' . get_option( 'siteurl' ) . '/images/logo.png" alt="Relative Image Link" /></a></li>' . "\n";

		$this->assertSame( $expected, _walk_bookmarks( array( $bookmark ), array( 'show_images' => 1 ) ) );
	}

	/**
	 * Tests show_updated functionality with recently_updated bookmarks.
	 *
	 * @ticket 65957
	 */
	public function test_walk_bookmarks_show_updated(): void {
		$timestamp = strtotime( '2026-08-24 12:00:00' );

		$bookmark_recent = (object) array(
			'link_id'          => 11,
			'link_name'        => 'Recent Link',
			'link_url'         => 'https://example.org/recent',
			'link_description' => 'Description',
			'link_image'       => '',
			'link_target'      => '',
			'link_rel'         => '',
			'link_rating'      => 0,
			'recently_updated' => true,
			'link_updated_f'   => (string) $timestamp,
		);

		$bookmark_not_recent = (object) array(
			'link_id'          => 12,
			'link_name'        => 'Old Link',
			'link_url'         => 'https://example.org/old',
			'link_description' => '',
			'link_image'       => '',
			'link_target'      => '',
			'link_rel'         => '',
			'link_rating'      => 0,
			'recently_updated' => false,
			'link_updated_f'   => '0000-00-00 00:00:00',
		);

		$date_format   = get_option( 'links_updated_date_format' );
		$gmt_offset    = (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$expected_date = gmdate( $date_format, $timestamp + $gmt_offset );

		$output = _walk_bookmarks(
			array( $bookmark_recent, $bookmark_not_recent ),
			array(
				'show_updated' => 1,
			)
		);

		$this->assertStringContainsString( '<em><a href="https://example.org/recent"', $output );
		$this->assertStringContainsString( 'Last updated: ' . $expected_date, $output );
		$this->assertStringContainsString( '</a></em>', $output );
		$this->assertStringNotContainsString( '<em><a href="https://example.org/old"', $output );
	}
}
