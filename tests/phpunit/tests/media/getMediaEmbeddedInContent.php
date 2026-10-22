<?php

/**
 * Tests for the `get_media_embedded_in_content()` function.
 *
 * @group media
 * @covers ::get_media_embedded_in_content
 */
class Tests_Media_GetMediaEmbeddedInContent extends WP_UnitTestCase {

	/**
	 * @ticket 65931
	 *
	 * @dataProvider data_content
	 *
	 * @param string        $content  Content to search for media elements.
	 * @param string[]|null $types    Media types to limit the search to.
	 * @param string[]      $expected Expected media elements.
	 */
	public function test_returns_expected_media( $content, $types, $expected ) {
		$this->assertSame( $expected, get_media_embedded_in_content( $content, $types ) );
	}

	/**
	 * Data provider for test_returns_expected_media.
	 *
	 * @return array[]
	 */
	public function data_content() {
		$audio  = '<audio preload="none"><source /></audio>';
		$video  = '<video preload="none"><source /></video>';
		$iframe = '<iframe src="https://example.com/embed" width="700"></iframe>';
		$embed  = '<embed src="movie.mp4" />';
		$object = '<object data="movie.swf"><param name="quality" /></object>';

		return array(
			'no types given'                   => array(
				"<p>Before.</p>$object$embed$iframe$audio$video<p>After.</p>",
				null,
				array( $object, $embed, $iframe, $audio, $video ),
			),
			'an empty array of types'          => array(
				"<p>Before.</p>$audio<p>After.</p>",
				array(),
				array( $audio ),
			),
			'repeated elements of one type'    => array(
				"<div>$iframe<p>Between.</p>$iframe</div>",
				'iframe',
				array( $iframe, $iframe ),
			),
			'both self-closing forms'          => array(
				'<div><embed src="one.mp4"/><embed src="two.mp4" /></div>',
				'embed',
				array( '<embed src="one.mp4"/>', '<embed src="two.mp4" />' ),
			),
			'an element left open'             => array(
				'<div><audio preload="none"></div>',
				'audio',
				array(),
			),
			'a less-than sign in an attribute' => array(
				'<div><iframe title="7 < 8" src="https://example.com/embed"></iframe></div>',
				'iframe',
				array(),
			),
		);
	}

	/**
	 * @ticket 65931
	 */
	public function test_allowed_types_filter_can_narrow_the_types() {
		$video = '<video preload="none"><source /></video>';

		add_filter(
			'media_embedded_in_content_allowed_types',
			static function () {
				return array( 'video' );
			}
		);

		$this->assertSame(
			array( $video ),
			get_media_embedded_in_content( '<div><audio preload="none"></audio>' . $video . '</div>' )
		);
	}

	/**
	 * @ticket 65931
	 */
	public function test_allowed_types_filter_can_add_a_type() {
		$img = '<img src="image.jpg" />';

		add_filter(
			'media_embedded_in_content_allowed_types',
			static function ( $allowed_media_types ) {
				$allowed_media_types[] = 'img';
				return $allowed_media_types;
			}
		);

		$this->assertSame( array( $img ), get_media_embedded_in_content( "<p>Text.</p>$img" ) );
	}
}
