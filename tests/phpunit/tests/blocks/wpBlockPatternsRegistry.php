<?php
/**
 * Tests for WP_Block_Patterns_Registry.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.2.0
 *
 * @group blocks
 */
class Tests_Blocks_wpBlockPatternsRegistry extends WP_UnitTestCase {
	/**
	 * Fake block patterns registry.
	 *
	 * @since 6.2.0
	 * @var WP_Block_Patterns_Registry
	 */
	private $registry = null;

	/**
	 * Set up each test method.
	 *
	 * @since 6.2.0
	 */
	public function set_up() {
		parent::set_up();

		$this->registry = new WP_Block_Patterns_Registry();
	}

	/**
	 * Tear down each test method.
	 *
	 * @since 6.2.0
	 */
	public function tear_down() {
		$this->registry = null;

		parent::tear_down();
	}

	/**
	 * Tests that WP_Block_Patterns_Registry::register
	 * throws a _doing_it_wrong() when expected.
	 *
	 * @ticket 55655
	 *
	 * @dataProvider data_register_should_throw_doing_it_wrong
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 * @covers ::register_block_pattern
	 *
	 * @expectedIncorrectUsage WP_Block_Patterns_Registry::register
	 *
	 * @param string $pattern_name       Block pattern name including namespace.
	 * @param array  $pattern_properties List of properties for the block pattern.
	 * @param string $hook               Optional. The name of the hook to use for registration. Default: 'init'.
	 */
	public function test_register_should_throw_doing_it_wrong( $pattern_name, $pattern_properties, $hook = 'init' ) {
		add_action(
			$hook,
			function() use ( $pattern_name, $pattern_properties ) {
				$this->assertFalse( $this->registry->register( $pattern_name, $pattern_properties ) );
			}
		);

		do_action( $hook );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_register_should_throw_doing_it_wrong() {
		return array(
			'registration on a hook other than "init"' => array(
				'pattern_name'       => 'wptests/custom-block-pattern',
				'pattern_properties' => array(
					'title'   => 'A custom block pattern',
					'content' => '<!-- wp:paragraph --><p>This is a custom block pattern</p><!-- /wp:paragraph -->',
				),
				'hook'               => 'current_screen',
			),
		);
	}
}
