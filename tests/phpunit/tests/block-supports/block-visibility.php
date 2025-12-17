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
	/**
	 * @var string|null
	 */
	private $test_block_name;

	public function set_up() {
		parent::set_up();
		$this->test_block_name = null;
	}

	public function tear_down() {
		unregister_block_type( $this->test_block_name );
		$this->test_block_name = null;
		parent::tear_down();
	}

	/**
	 * Registers a new block for testing block visibility support.
	 *
	 * @param string $block_name Name for the test block.
	 * @param array  $supports   Array defining block support configuration.
	 *
	 * @return WP_Block_Type The block type for the newly registered test block.
	 */
	private function register_visibility_block_with_support( $block_name, $supports = array() ) {
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

		return $registry->get_registered( $this->test_block_name );
	}

	/**
	 * Tests that block visibility support renders empty string when block is hidden
	 * and blockVisibility support is opted in.
	 *
	 * @ticket 64061
	 */
	public function test_block_visibility_support_hides_block_when_visibility_false() {
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
	public function test_block_visibility_support_shows_block_when_support_not_opted_in() {
		$this->register_visibility_block_with_support(
			'test/visibility-block',
			array( 'visibility' => false )
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

		$this->assertSame( $block_content, $result, 'Block content should remain unchanged when blockVisibility support is not opted in.' );
	}

	/*
	 * @ticket 64414
	 */
	public function test_block_visibility_support_no_visibility_attribute() {
		$this->register_visibility_block_with_support(
			'test/block-visibility-none',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/block-visibility-none',
			'attrs'     => array(),
		);

		$block_content = '<div>Test content</div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( $block_content, $result );
	}

	/*
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_mobile_breakpoint() {
		$this->register_visibility_block_with_support(
			'test/responsive-mobile',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/responsive-mobile',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'mobile' => false,
					),
				),
			),
		);

		$block_content = '<div>Test content</div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertStringContainsString( 'wp-block-hidden-mobile', $result, 'Block should have the visibility class for the mobile breakpoint.' );
	}

	/*
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_multiple_breakpoints() {
		$this->register_visibility_block_with_support(
			'test/responsive-multiple',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/responsive-multiple',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'mobile'  => false,
						'desktop' => false,
					),
				),
			),
		);

		$block_content = '<div>Test content</div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertStringContainsString( 'wp-block-hidden-desktop-mobile', $result, 'Block should have the visibility class for both breakpoints (sorted alphabetically).' );
	}

	/*
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_tablet_breakpoint() {
		$this->register_visibility_block_with_support(
			'test/responsive-tablet',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/responsive-tablet',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'tablet' => false,
					),
				),
			),
		);

		$block_content = '<div class="existing-class">Test content</div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertStringContainsString( 'existing-class', $result, 'Block should have the existing class.' );
		$this->assertStringContainsString( 'wp-block-hidden-tablet', $result, 'Block should have the visibility class for the tablet breakpoint.' );
	}

	/*
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_all_breakpoints_visible() {
		$this->register_visibility_block_with_support(
			'test/responsive-all-visible',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/responsive-all-visible',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'mobile'  => true,
						'tablet'  => true,
						'desktop' => true,
					),
				),
			),
		);

		$block_content = '<div>Test content</div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( $block_content, $result, 'Block content should remain unchanged when all breakpoints are visible.' );
	}

	/*
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_all_breakpoints_hidden() {
		$this->register_visibility_block_with_support(
			'test/viewport-all-hidden',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/viewport-all-hidden',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'mobile'  => false,
						'tablet'  => false,
						'desktop' => false,
					),
				),
			),
		);

		$block_content = '<div>Test content</div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( '', $result, 'Block content should be empty when all breakpoints are hidden.' );
	}

	/*
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_empty_object() {
		$this->register_visibility_block_with_support(
			'test/responsive-empty',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/responsive-empty',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(),
				),
			),
		);

		$block_content = '<div>Test content</div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( $block_content, $result, 'Block content should remain unchanged when blockVisibility is an empty array.' );
	}

	/*
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_unknown_breakpoints_ignored() {
		$this->register_visibility_block_with_support(
			'test/responsive-unknown-breakpoints',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/responsive-unknown-breakpoints',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'mobile'       => false,
						'unknownBreak' => false,
						'largeScreen'  => false,
					),
				),
			),
		);

		$block_content = '<div>Test content</div>';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertStringContainsString( 'wp-block-hidden-mobile', $result, 'Block should have the visibility class for the mobile breakpoint.' );
		$this->assertStringNotContainsString( 'unknownBreak', $result, 'Unknown breakpoints should not appear in the class name.' );
		$this->assertStringNotContainsString( 'largeScreen', $result, 'Large screen breakpoints should not appear in the class name.' );
	}

	/*
	 * @ticket 64414
	 */
	public function test_block_visibility_support_generated_css_with_empty_content() {
		$this->register_visibility_block_with_support(
			'test/empty-content',
			array( 'visibility' => true )
		);

		$block = array(
			'blockName' => 'test/empty-content',
			'attrs'     => array(
				'metadata' => array(
					'blockVisibility' => array(
						'mobile' => false,
					),
				),
			),
		);

		$block_content = '';
		$result        = wp_render_block_visibility_support( $block_content, $block );

		$this->assertSame( '', $result, 'Block content should be empty when there is no content.' );
	}
}
