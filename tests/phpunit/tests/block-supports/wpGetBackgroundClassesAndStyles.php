<?php
/**
 * @group block-supports
 *
 * @covers ::wp_get_background_classes_and_styles
 */
class Tests_Block_Supports_WpGetBackgroundClassesAndStyles extends WP_UnitTestCase {
	/**
	 * Tests that background classes and styles are generated from block attributes.
	 *
	 * @ticket 66269
	 *
	 * @dataProvider data_get_background_classes_and_styles
	 *
	 * @param mixed $block_attributes Block attributes.
	 * @param array $expected         Expected classes and styles.
	 */
	public function test_get_background_classes_and_styles( $block_attributes, $expected ) {
		$this->assertSame( $expected, wp_get_background_classes_and_styles( $block_attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_background_classes_and_styles() {
		return array(
			'custom image gets default size'       => array(
				'block_attributes' => array(
					'style' => array(
						'background' => array(
							'backgroundImage' => array( 'url' => 'https://example.com/image.jpg' ),
						),
					),
				),
				'expected'         => array(
					'class' => 'has-background',
					'style' => "background-image:url('https://example.com/image.jpg');background-size:cover;",
				),
			),
			'contain without position is centered' => array(
				'block_attributes' => array(
					'style' => array(
						'background' => array(
							'backgroundImage'      => array( 'url' => 'https://example.com/image.jpg' ),
							'backgroundSize'       => 'contain',
							'backgroundRepeat'     => 'no-repeat',
							'backgroundAttachment' => 'fixed',
						),
					),
				),
				'expected'         => array(
					'class' => 'has-background',
					'style' => "background-image:url('https://example.com/image.jpg');background-position:50% 50%;background-repeat:no-repeat;background-size:contain;background-attachment:fixed;",
				),
			),
			'clip alone has no class'              => array(
				'block_attributes' => array(
					'style' => array(
						'background' => array(
							'backgroundClip' => 'padding-box',
						),
					),
				),
				'expected'         => array(
					'style' => 'background-clip:padding-box;-webkit-text-fill-color:currentColor;',
				),
			),
			'image clipped to text has no class'   => array(
				'block_attributes' => array(
					'style' => array(
						'background' => array(
							'backgroundImage' => array( 'url' => 'https://example.com/image.jpg' ),
							'backgroundClip'  => 'text',
						),
					),
				),
				'expected'         => array(
					'style' => "background-image:url('https://example.com/image.jpg');background-size:cover;background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;",
				),
			),
			'background is not an array'           => array(
				'block_attributes' => array(
					'style' => array(
						'background' => 'linear-gradient(red, blue)',
					),
				),
				'expected'         => array(),
			),
			'attributes are a string'              => array(
				'block_attributes' => 'style',
				'expected'         => array(),
			),
			'attributes are an object'             => array(
				'block_attributes' => (object) array( 'style' => array() ),
				'expected'         => array(),
			),
		);
	}
}
