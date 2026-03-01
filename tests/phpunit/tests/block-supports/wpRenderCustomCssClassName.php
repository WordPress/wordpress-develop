<?php

/**
 * @group block-supports
 *
 * @covers ::wp_render_custom_css_class_name
 */
class Tests_Block_Supports_WpRenderCustomCssClassName extends WP_UnitTestCase {

	/**
	 * Tests that the custom CSS class name is applied to block content.
	 *
	 * @ticket 64544
	 *
	 * @covers ::wp_render_custom_css_class_name
	 *
	 * @dataProvider data_adds_class_to_content
	 *
	 * @param string $block_content  The rendered block content.
	 * @param array  $block          The block data.
	 * @param string $expected_class The expected class in the output.
	 */
	public function test_adds_class_to_content( $block_content, $block, $expected_class ) {
		$result = wp_render_custom_css_class_name( $block_content, $block );

		$this->assertStringContainsString( $expected_class, $result, 'Custom CSS class should be present in the output.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_adds_class_to_content() {
		return array(
			'class is added to block content'           => array(
				'block_content'  => '<div class="wp-block-paragraph">Test content</div>',
				'block'          => array(
					'blockName' => 'core/paragraph',
					'attrs'     => array(
						'className' => 'wp-custom-css-123abc',
					),
				),
				'expected_class' => 'wp-custom-css-123abc',
			),
			'class is extracted from mixed class names' => array(
				'block_content'  => '<p>Test content</p>',
				'block'          => array(
					'blockName' => 'core/paragraph',
					'attrs'     => array(
						'className' => 'my-class wp-custom-css-mixed123 another-class',
					),
				),
				'expected_class' => 'wp-custom-css-mixed123',
			),
		);
	}

	/**
	 * Tests that existing classes are preserved when the custom CSS class is added.
	 *
	 * @ticket 64544
	 *
	 * @covers ::wp_render_custom_css_class_name
	 */
	public function test_preserves_existing_classes() {
		$block_content = '<div class="existing-class another-class">Test content</div>';
		$block         = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(
				'className' => 'wp-custom-css-456def',
			),
		);

		$result = wp_render_custom_css_class_name( $block_content, $block );

		$this->assertStringContainsString( 'existing-class', $result, 'Existing classes should be preserved.' );
		$this->assertStringContainsString( 'another-class', $result, 'All existing classes should be preserved.' );
		$this->assertStringContainsString( 'wp-custom-css-456def', $result, 'Custom CSS class should be added.' );
	}

	/**
	 * Tests that block content is returned unchanged when no custom CSS class should be applied.
	 *
	 * @ticket 64544
	 *
	 * @covers ::wp_render_custom_css_class_name
	 *
	 * @dataProvider data_returns_unchanged_content
	 *
	 * @param string $block_content The rendered block content.
	 * @param array  $block         The block data.
	 */
	public function test_returns_unchanged_content( $block_content, $block ) {
		$result = wp_render_custom_css_class_name( $block_content, $block );

		$this->assertSame( $block_content, $result, 'Block content should remain unchanged.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_returns_unchanged_content() {
		return array(
			'no custom CSS class in attrs'  => array(
				'block_content' => '<div class="wp-block-paragraph">Test content</div>',
				'block'         => array(
					'blockName' => 'core/paragraph',
					'attrs'     => array(
						'className' => 'some-other-class',
					),
				),
			),
			'className is not set in attrs' => array(
				'block_content' => '<div class="wp-block-paragraph">Test content</div>',
				'block'         => array(
					'blockName' => 'core/paragraph',
					'attrs'     => array(),
				),
			),
			'block content is empty'        => array(
				'block_content' => '',
				'block'         => array(
					'blockName' => 'core/paragraph',
					'attrs'     => array(
						'className' => 'wp-custom-css-789ghi',
					),
				),
			),
		);
	}
}
