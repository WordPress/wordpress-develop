<?php

/**
 * @group bookmark
 * @group template
 *
 * @covers ::_walk_bookmarks
 */
class Tests_Bookmark_WalkBookmarks extends WP_UnitTestCase {

	/**
	 * Builds a bookmark object with the given field overrides.
	 *
	 * @param array $fields Field values to set on the bookmark object.
	 * @return stdClass Bookmark object.
	 */
	private function make_bookmark( $fields = array() ) {
		$bookmark = (object) array_merge(
			array(
				'link_id'          => 0,
				'link_url'         => 'http://example.com/',
				'link_name'        => 'Example',
				'link_description' => '',
				'link_rel'         => '',
				'link_target'      => '',
				'link_image'       => '',
				'recently_updated' => false,
			),
			$fields
		);

		return $bookmark;
	}

	/**
	 * The link_image field is stored without URL validation, so a hostile value
	 * must not be able to break out of the img src attribute on the front end.
	 */
	public function test_link_image_is_escaped_in_img_src() {
		$image    = 'http://example.com/x.png" onerror="alert(1)';
		$bookmark = $this->make_bookmark( array( 'link_image' => $image ) );

		$output = _walk_bookmarks( array( $bookmark ), array( 'show_images' => 1 ) );

		$this->assertStringNotContainsString( '" onerror="alert(1)', $output, 'Unescaped link_image broke out of the src attribute.' );
		$this->assertStringContainsString( '<img src="' . esc_url( $image ) . '"', $output );
	}

	/**
	 * A relative link_image is prefixed with the site URL and must still be escaped.
	 */
	public function test_relative_link_image_is_escaped_in_img_src() {
		$image    = '/x.png" onerror="alert(1)';
		$bookmark = $this->make_bookmark( array( 'link_image' => $image ) );

		$output = _walk_bookmarks( array( $bookmark ), array( 'show_images' => 1 ) );

		$this->assertStringNotContainsString( '" onerror="alert(1)', $output );
		$this->assertStringContainsString( esc_url( get_option( 'siteurl' ) . $image ), $output );
	}

	/**
	 * The link_target field is emitted into a target attribute and must be escaped
	 * like the sibling rel attribute in the same function.
	 */
	public function test_link_target_is_escaped_in_target_attribute() {
		$target   = '_blank" onmouseover="alert(1)';
		$bookmark = $this->make_bookmark( array( 'link_target' => $target ) );

		$output = _walk_bookmarks( array( $bookmark ) );

		$this->assertStringNotContainsString( '" onmouseover="alert(1)', $output );
		$this->assertStringContainsString( ' target="' . esc_attr( $target ) . '"', $output );
	}
}
