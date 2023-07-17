<?php

/**
 * Test the block WP_Duotone class.
 *
 * @package WordPress
 */

class Tests_Block_Supports_DuoTones extends WP_UnitTestCase {
	/**
	 * Cleans up CSS added to block-supports from duotone styles. We neeed to do this
	 * in order to avoid impacting other tests.
	 */
	public static function wpTearDownAfterClass() {
		WP_Style_Engine_CSS_Rules_Store::remove_all_stores();
	}

	/**
	 * Tests whether the duotone preset class is added to the block.
	 *
	 * @ticket 58555
	 *
	 * @covers ::render_duotone_support
	 */
	public function test_render_duotone_support_preset() {
		$block         = array(
			'blockName' => 'core/image',
			'attrs'     => array( 'style' => array( 'color' => array( 'duotone' => 'var:preset|duotone|blue-orange' ) ) ),
		);
		$wp_block      = new WP_Block( $block );
		$block_content = '<figure class="wp-block-image size-full"><img src="/my-image.jpg" /></figure>';
		$expected      = '<figure class="wp-block-image size-full wp-duotone-blue-orange"><img src="/my-image.jpg" /></figure>';
		$duotone       = new WP_Duotone();
		$this->assertSame( $expected, $duotone->render_duotone_support( $block_content, $block, $wp_block ) );
	}

	/**
	 * Tests whether the duotone unset class is added to the block.
	 *
	 * @ticket 58555
	 *
	 * @covers ::render_duotone_support
	 */
	public function test_render_duotone_support_css() {
		$block         = array(
			'blockName' => 'core/image',
			'attrs'     => array( 'style' => array( 'color' => array( 'duotone' => 'unset' ) ) ),
		);
		$wp_block      = new WP_Block( $block );
		$block_content = '<figure class="wp-block-image size-full"><img src="/my-image.jpg" /></figure>';
		$expected      = '/<figure class="wp-block-image size-full wp-duotone-unset-\d+"><img src="\\/my-image.jpg" \\/><\\/figure>/';
		$duotone       = new WP_Duotone();
		$this->assertMatchesRegularExpression( $expected, $duotone->render_duotone_support( $block_content, $block, $wp_block ) );
	}

	/**
	 * Tests whether the duotone custom class is added to the block.
	 *
	 * @covers ::render_duotone_support
	 */
	public function test_render_duotone_support_custom() {
		$block         = array(
			'blockName' => 'core/image',
			'attrs'     => array( 'style' => array( 'color' => array( 'duotone' => array( '#FFFFFF', '#000000' ) ) ) ),
		);
		$wp_block      = new WP_Block( $block );
		$block_content = '<figure class="wp-block-image size-full"><img src="/my-image.jpg" /></figure>';
		$expected      = '/<figure class="wp-block-image size-full wp-duotone-ffffff-000000-\d+"><img src="\\/my-image.jpg" \\/><\\/figure>/';
		$duotone       = new WP_Duotone();
		$this->assertMatchesRegularExpression( $expected, $duotone->render_duotone_support( $block_content, $block, $wp_block ) );
	}

	/**
	 * Tests whether the slug is extracted from the attribute.
	 *
	 * @dataProvider data_get_slug_from_attribute
	 * @covers ::get_slug_from_attribute
	 */
	public function test_get_slug_from_attribute( $data_attr, $expected ) {
		$duotone   = new WP_Duotone();
		$reflection = new ReflectionMethod( $duotone, 'get_slug_from_attribute' );
		$reflection->setAccessible( true );

		$this->assertSame( $expected, $reflection->invoke( $duotone, $data_attr ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_get_slug_from_attribute() {
		return array(
			'pipe-slug'                       => array( 'var:preset|duotone|blue-orange', 'blue-orange' ),
			'css-var'                         => array( 'var(--wp--preset--duotone--blue-orange)', 'blue-orange' ),
			'css-var-invalid-slug-chars'      => array( 'var(--wp--preset--duotone--.)', '.' ),
			'css-var-missing-end-parenthesis' => array( 'var(--wp--preset--duotone--blue-orange', '' ),
			'invalid'                         => array( 'not a valid attribute', '' ),
			'css-var-no-value'                => array( 'var(--wp--preset--duotone--)', '' ),
			'pipe-slug-no-value'              => array( 'var:preset|duotone|', '' ),
			'css-var-spaces'                  => array( 'var(--wp--preset--duotone--    ', '' ),
			'pipe-slug-spaces'                => array( 'var:preset|duotone|  ', '' ),
		);
	}

	/**
	 * @dataProvider data_is_preset
	 */
	public function test_is_preset( $data_attr, $expected ) {
		$duotone   = new WP_Duotone();
		$reflection = new ReflectionMethod( $duotone, 'is_preset' );
		$reflection->setAccessible( true );

		$this->assertSame( $expected, $reflection->invoke( $duotone, $data_attr ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_is_preset() {
		return array(
			'pipe-slug'                       => array( 'var:preset|duotone|blue-orange', true ),
			'css-var'                         => array( 'var(--wp--preset--duotone--blue-orange)', true ),
			'css-var-invalid-slug-chars'      => array( 'var(--wp--preset--duotone--.)', false ),
			'css-var-missing-end-parenthesis' => array( 'var(--wp--preset--duotone--blue-orange', false ),
			'invalid'                         => array( 'not a valid attribute', false ),
		);
	}
}
