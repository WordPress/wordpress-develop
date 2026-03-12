<?php
/**
 * Test the block visibility block support.
 *
 * @package WordPress
 * @subpackage Block Supports
 * @since 6.9.0
 *
 * @group block-supports
 *
 * @covers ::wp_render_block_visibility_support
 */
class Tests_Block_Supports_Block_Visibility extends WP_UnitTestCase {

	private ?string $test_block_name;

	public function set_up(): void {
		parent::set_up();
		$this->test_block_name = null;
	}

	public function tear_down(): void {
		if ( $this->test_block_name ) {
			unregister_block_type( $this->test_block_name );
		}
		$this->test_block_name = null;
		parent::tear_down();
	}

	/**
	 * Registers a new block for testing block visibility support.
	 *
	 * @param string              $block_name Name for the test block.
	 * @param array<string, bool> $supports   Array defining block support configuration.
	 */
	private function register_visibility_block_with_support( string $block_name, array $supports = array() ): void {
		$this->test_block_name = $block_name;
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'metadata' => array(
						'type' => 'object',
					),
				),
				'supports'    => $supports,
			)
		);
		$registry = WP_Block_Type_Registry::get_instance();

		$registry->get_registered( $this->test_block_name );
	}

	/**
	 * Tests that block visibility support renders empty string when block is hidden
	 * and blockVisibility support is opted in.
	 *
	 * @ticket 64061
	 */
	public function test_block_visibility_support_hides_block_when_visibility_false(): void {
		$this->register_visibility_block_with_support(
			'test/visibility-block',
			array( 'visibility' => true )
		);

		$block_content = '<p>This is a test block.</p>';
		$block         = array(
			'blockName' => 'test/visibility-block',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => false,
				),
			),
		);

		$result = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( '', $result, 'Block content should be empty when blockVisibility is false and support is opted in.' );
	}

	/**
	 * Tests that block visibility support renders block normally when visibility is false
	 * but blockVisibility support is not opted in.
	 *
	 * @ticket 64061
	 */
	public function test_block_visibility_support_shows_block_when_support_not_opted_in(): void {
		$this->register_visibility_block_with_support(
			'test/visibility-block',
			array( 'visibility' => false )
		);

		$block_content = '<div>Test content <img src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>';
		$block         = array(
			'blockName' => 'test/visibility-block',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => false,
				),
			),
		);

		$result = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( $block_content, $result, 'Block content should remain unchanged when blockVisibility support is not opted in.' );
	}

	/**
	 * @ticket 64414
	 */
	public function test_block_visibility_support_no_visibility_attribute(): void {
		$this->register_visibility_block_with_support(
			'test/block-visibility-none',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/block-visibility-none',
			'attrs'     => array(),
		);

		$block_content = '<div>Test content <img src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( $block_content, $result, 'Block content should remain unchanged when no visibility attribute is present.' );
	}

	/**
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_mobile_viewport_size(): void {
		$this->register_visibility_block_with_support(
			'test/viewport-mobile',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/viewport-mobile',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'viewport' => array(
							'mobile' => false,
						),
					),
				),
			),
		);

		$block_content = '<div>Test content <img src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertStringContainsString( 'wp-block-hidden-mobile', $result, 'Block should have the visibility class for the mobile breakpoint.' );

		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertSame(
			'@media (width <= 480px){.wp-block-hidden-mobile{display:none !important;}}',
			$actual_stylesheet,
			'CSS should contain mobile visibility rule'
		);
	}

	/**
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_tablet_viewport_size(): void {
		$this->register_visibility_block_with_support(
			'test/viewport-tablet',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/viewport-tablet',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'viewport' => array(
							'tablet' => false,
						),
					),
				),
			),
		);

		$block_content = '<div class="existing-class">Test content <img src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertEqualHTML(
			'<div class="existing-class wp-block-hidden-tablet">Test content <img fetchpriority="auto" src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>',
			$result,
			'<body>',
			'Block should have the existing class and the visibility class for the tablet breakpoint in the class attribute.'
		);

		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertSame(
			'@media (480px < width <= 782px){.wp-block-hidden-tablet{display:none !important;}}',
			$actual_stylesheet,
			'CSS should contain tablet visibility rule'
		);
	}

	/**
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_desktop_breakpoint(): void {
		$this->register_visibility_block_with_support(
			'test/viewport-desktop',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/viewport-desktop',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'viewport' => array(
							'desktop' => false,
						),
					),
				),
			),
		);

		$block_content = '<div class="existing-class">Test content <img src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertEqualHTML(
			'<div class="existing-class wp-block-hidden-desktop">Test content <img fetchpriority="auto" src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>',
			$result,
			'<body>',
			'Block should have the visibility class for the desktop breakpoint in the class attribute.'
		);

		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertSame(
			'@media (width > 782px){.wp-block-hidden-desktop{display:none !important;}}',
			$actual_stylesheet,
			'CSS should contain desktop visibility rule'
		);
	}

	/**
	 * @ticket 64414
	 * @ticket 64823
	 */
	public function test_block_visibility_support_generated_css_with_two_viewport_sizes(): void {
		$this->register_visibility_block_with_support(
			'test/viewport-two',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/viewport-two',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'viewport' => array(
							'mobile'  => false,
							'desktop' => false,
						),
					),
				),
			),
		);

		$block_content = '<div>Test content <img src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertEqualHTML(
			'<div class="wp-block-hidden-desktop wp-block-hidden-mobile">Test content <img fetchpriority="auto" src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>',
			$result,
			'<body>',
			'Block should have both visibility classes in the class attribute, and the IMG should have fetchpriority=auto.'
		);

		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertSame(
			'@media (width > 782px){.wp-block-hidden-desktop{display:none !important;}}@media (width <= 480px){.wp-block-hidden-mobile{display:none !important;}}',
			$actual_stylesheet,
			'CSS should contain desktop and mobile visibility rules'
		);
	}

	/**
	 * @ticket 64414
	 * @ticket 64823
	 */
	public function test_block_visibility_support_generated_css_with_all_viewport_sizes_visible(): void {
		$this->register_visibility_block_with_support(
			'test/viewport-all-visible',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/viewport-all-visible',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'viewport' => array(
							'mobile'  => true,
							'tablet'  => true,
							'desktop' => true,
						),
					),
				),
			),
		);

		$block_content = '<div>Test content <img src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( $block_content, $result, 'Block content should remain unchanged when all breakpoints are visible.' );
	}

	/**
	 * @ticket 64414
	 * @ticket 64823
	 */
	public function test_block_visibility_support_generated_css_with_all_viewport_sizes_hidden(): void {
		$this->register_visibility_block_with_support(
			'test/viewport-all-hidden',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/viewport-all-hidden',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'viewport' => array(
							'mobile'  => false,
							'tablet'  => false,
							'desktop' => false,
						),
					),
				),
			),
		);

		$block_content = '<div>Test content <img src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertEqualHTML(
			'<div class="wp-block-hidden-desktop wp-block-hidden-mobile wp-block-hidden-tablet">Test content <img fetchpriority="auto" src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>',
			$result,
			'<body>',
			'Block content should have the visibility classes for all viewport sizes in the class attribute, and an IMG should get fetchpriority=auto.'
		);
	}

	/**
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_empty_object(): void {
		$this->register_visibility_block_with_support(
			'test/viewport-empty',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/viewport-empty',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(),
				),
			),
		);

		$block_content = '<div>Test content <img src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( $block_content, $result, 'Block content should remain unchanged when blockVisibility is an empty array.' );
	}

	/**
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_unknown_viewport_sizes_ignored(): void {
		$this->register_visibility_block_with_support(
			'test/viewport-unknown-viewport-sizes',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/viewport-unknown-viewport-sizes',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'viewport' => array(
							'mobile'       => false,
							'unknownBreak' => false,
							'largeScreen'  => false,
						),
					),
				),
			),
		);

		$block_content = '<div>Test content <img src="https://example.com/image.jpg" width="1000" height="1000" alt=""></div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertStringContainsString(
			'class="wp-block-hidden-mobile"',
			$result,
			'Block should have the visibility class for the mobile breakpoint in the class attribute'
		);
	}

	/**
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_empty_content(): void {
		$this->register_visibility_block_with_support(
			'test/viewport-empty-content',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/viewport-empty-content',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'viewport' => array(
							'mobile' => false,
						),
					),
				),
			),
		);

		$block_content = '';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( '', $result, 'Block content should be empty when there is no content.' );
	}
}
