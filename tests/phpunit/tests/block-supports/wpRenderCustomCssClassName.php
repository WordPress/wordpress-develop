<?php

/**
 * @group block-supports
 *
 * @covers ::wp_render_custom_css_class_name
 */
class Tests_Block_Supports_WpRenderCustomCssClassName extends WP_UnitTestCase {
	/**
	 * @var string|null
	 */
	private $test_block_name;

	public function set_up() {
		parent::set_up();
		$this->test_block_name = null;

		global $wp_styles, $wp_script_modules;
		$wp_styles         = null;
		$wp_script_modules = null;
	}

	public function tear_down() {
		if ( $this->test_block_name ) {
			unregister_block_type( $this->test_block_name );
		}
		$this->test_block_name = null;

		global $wp_styles, $wp_script_modules;
		$wp_styles         = null;
		$wp_script_modules = null;

		parent::tear_down();
	}

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
	 * @return array<string, array{
	 *     block_content: string,
	 *     block: array{
	 *         blockName: string,
	 *         attrs: array{
	 *             className: string,
	 *         },
	 *     },
	 *     expected_class: string,
	 * }>
	 */
	public static function data_adds_class_to_content(): array {
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
			'class between whitespace is added'         => array(
				'block_content'  => '<div class="wp-block-paragraph">Test content</div>',
				'block'          => array(
					'blockName' => 'core/paragraph',
					'attrs'     => array(
						'className' => "\twp-custom-css-123abc\t",
					),
				),
				'expected_class' => 'wp-custom-css-123abc',
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
	public static function data_returns_unchanged_content() {
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
			'prefixed custom CSS class'     => array(
				'block_content' => '<div class="wp-block-paragraph">Test content</div>',
				'block'         => array(
					'blockName' => 'core/paragraph',
					'attrs'     => array(
						'className' => 'my-wp-custom-css-456def',
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

	/**
	 * Returns the custom CSS class name found in a string of rendered markup.
	 *
	 * @param string $markup Rendered block markup.
	 * @return string|null The custom CSS class name, or null if there is none.
	 */
	private function get_custom_css_class_name_from_markup( $markup ) {
		return preg_match( '/\bwp-custom-css-[0-9a-f]+\b/', $markup, $matches ) ? $matches[0] : null;
	}

	/**
	 * Returns the custom CSS rules currently attached to the custom CSS stylesheet.
	 *
	 * @return string The inline custom CSS.
	 */
	private function get_enqueued_custom_css() {
		$after = wp_styles()->get_data( 'wp-block-custom-css', 'after' );

		return is_array( $after ) ? implode( "\n", $after ) : '';
	}

	/**
	 * Tests that the class name and stylesheet are still generated for blocks that
	 * are rendered without the `render_block_data` filter running.
	 *
	 * Direct `WP_Block::render()` calls skip `render_block_data` for the block
	 * itself, as when `core/navigation` renders a `wp_navigation` post's blocks.
	 *
	 * @ticket 66345
	 *
	 * @covers ::wp_render_custom_css_class_name
	 */
	public function test_generates_class_when_render_block_data_skipped() {
		$this->test_block_name = 'test/custom-css-skipped-block-data';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array( 'customCSS' => true ),
			)
		);

		$block = array(
			'blockName' => 'test/custom-css-skipped-block-data',
			'attrs'     => array(
				'style' => array(
					'css' => 'padding: 0;',
				),
			),
		);

		$result     = wp_render_custom_css_class_name( '<li class="wp-block-navigation-item">Home</li>', $block );
		$class_name = $this->get_custom_css_class_name_from_markup( $result );

		$this->assertNotNull( $class_name, 'A custom CSS class should be added even though render_block_data did not run.' );
		$this->assertStringContainsString( 'has-custom-css', $result, 'The has-custom-css class should be added.' );
		$this->assertStringContainsString( 'wp-block-navigation-item', $result, 'Existing classes should be preserved.' );
		$this->assertStringContainsString(
			'.' . $class_name,
			$this->get_enqueued_custom_css(),
			'The custom CSS stylesheet should contain a rule for the generated class.'
		);
	}

	/**
	 * Tests that a block is not given a second class name when `render_block_data`
	 * has already generated one.
	 *
	 * @ticket 66345
	 *
	 * @covers ::wp_render_custom_css_class_name
	 */
	public function test_does_not_regenerate_existing_class() {
		$this->test_block_name = 'test/custom-css-existing-class';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array( 'customCSS' => true ),
			)
		);

		$parsed_block = array(
			'blockName' => 'test/custom-css-existing-class',
			'attrs'     => array(
				'style' => array(
					'css' => 'padding: 0;',
				),
			),
		);

		$filtered = wp_render_custom_css_support_styles( $parsed_block );
		$result   = wp_render_custom_css_class_name( '<p>Test content</p>', $filtered );

		$this->assertSame(
			1,
			preg_match_all( '/wp-custom-css-[0-9a-f]+/', $result ),
			'Only the class name generated by render_block_data should be added.'
		);
		$this->assertStringContainsString(
			$filtered['attrs']['className'],
			$result,
			'The class name generated by render_block_data should be the one added to the markup.'
		);
	}

	/**
	 * Tests that blocks inside a navigation menu get their custom CSS on the front end.
	 *
	 * @ticket 66345
	 *
	 * @covers ::wp_render_custom_css_class_name
	 */
	public function test_custom_css_is_rendered_for_blocks_inside_a_navigation_menu() {
		/*
		 * Custom CSS is stripped on save for users without the `edit_css` capability,
		 * which the default test user does not have. Hooks are restored on tear down.
		 */
		remove_filter( 'content_save_pre', 'wp_strip_custom_css_from_blocks', 8 );

		$navigation_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_title'   => 'Custom CSS Menu',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom","isTopLevelLink":true,"style":{"css":"padding: 0;"}} /-->',
			)
		);

		$rendered = do_blocks( '<!-- wp:navigation {"ref":' . $navigation_id . '} /-->' );

		$class_name = $this->get_custom_css_class_name_from_markup( $rendered );

		$this->assertNotNull( $class_name, 'The navigation link should be given a custom CSS class.' );
		$this->assertStringContainsString(
			'.' . $class_name,
			$this->get_enqueued_custom_css(),
			'The custom CSS stylesheet should contain a rule for the navigation link.'
		);
	}
}
