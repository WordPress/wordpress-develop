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
		$block_type = $this->register_visibility_block_with_support(
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
		$block_type = $this->register_visibility_block_with_support(
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
}
