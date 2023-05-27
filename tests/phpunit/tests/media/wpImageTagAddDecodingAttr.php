<?php

/**
 * Tests for the `wp_img_tag_add_decoding_attr()` function.
 *
 * @group media
 * @covers ::wp_img_tag_add_decoding_attr
 */
class Tests_Media_Wp_Img_Tag_Add_Decoding_Attr extends WP_UnitTestCase {
	/**
	 * Tests that the `wp_img_tag_add_decoding_attr()` function should add
	 * the 'decoding' attribute.
	 *
	 * @ticket 53232
	 *
	 * @dataProvider data_should_add_decoding_attr
	 *
	 * @param string $image    The HTML `img` tag where the attribute should be added.
	 * @param string $context  Additional context to pass to the filters.
	 * @param string $decoding The value for the 'decoding' attribute. 'no value' for default.
	 * @param string $expected The expected `img` tag.
	 */
	public function test_should_add_decoding_attr( $image, $context, $decoding, $expected ) {
		// Falsey values are allowed in the filter, cannot use `null` or `false` here.
		if ( 'no value' !== $decoding ) {
			add_filter(
				'wp_img_tag_add_decoding_attr',
				static function( $value ) use ( $decoding ) {
					return $decoding;
				}
			);
		}

		$this->assertSame( $expected, wp_img_tag_add_decoding_attr( $image, $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_add_decoding_attr() {
		return array(
			'default' => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => 'no value',
				'expected' => '<img decoding="async" src="my-image.png">',
			),
			'async'   => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => 'async',
				'expected' => '<img decoding="async" src="my-image.png">',
			),
			'sync'    => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => 'sync',
				'expected' => '<img decoding="sync" src="my-image.png">',
			),
			'auto'    => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => 'auto',
				'expected' => '<img decoding="auto" src="my-image.png">',
			),
		);
	}

	/**
	 * Tests that the `wp_img_tag_add_decoding_attr()` function should not add
	 * the 'decoding' attribute.
	 *
	 * @ticket 53232
	 *
	 * @dataProvider data_should_not_add_decoding_attr
	 *
	 * @param string $image    The HTML `img` tag where the attribute should be added.
	 * @param string $context  Additional context to pass to the filters.
	 * @param mixed  $decoding The value for the 'decoding' attribute. 'no value' for default.
	 * @param string $expected The expected `img` tag.
	 */
	public function test_should_not_add_decoding_attr( $image, $context, $decoding, $expected ) {
		// Falsey values are allowed in the filter, cannot use `null` or `false` here.
		if ( 'no value' !== $decoding ) {
			add_filter(
				'wp_img_tag_add_decoding_attr',
				static function( $value ) use ( $decoding ) {
					return $decoding;
				}
			);
		}

		$this->assertSame( $expected, wp_img_tag_add_decoding_attr( $image, $context, $expected ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_not_add_decoding_attr() {
		return array(
			// Unhappy paths.
			'lazy (unaccepted value)' => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => 'lazy',
				'expected' => '<img src="my-image.png">',
			),
			'a non-string value'      => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => array( 'sync' ),
				'expected' => '<img src="my-image.png">',
			),

			// Falsey values.
			'false'                   => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => false,
				'expected' => '<img src="my-image.png">',
			),
			'null'                    => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => null,
				'expected' => '<img src="my-image.png">',
			),
			'empty string'            => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => '',
				'expected' => '<img src="my-image.png">',
			),
			'empty array'             => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => array(),
				'expected' => '<img src="my-image.png">',
			),
			'0 int'                   => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => 0,
				'expected' => '<img src="my-image.png">',
			),
			'0 string'                => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => '0',
				'expected' => '<img src="my-image.png">',
			),
			'0.0 float'               => array(
				'image'    => '<img src="my-image.png">',
				'context'  => '',
				'decoding' => 0.0,
				'expected' => '<img src="my-image.png">',
			),
		);
	}
}
