<?php
/**
 * Tests for WP_Block_Patterns_Registry::is_registered().
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.9.0
 *
 * @group block-patterns
 *
 * @covers WP_Block_Patterns_Registry::is_registered
 */
class Tests_Block_Pattern_WPBlockPatternsRegistry extends WP_UnitTestCase {

	/**
	 * Instance of the registry.
	 *
	 * @var WP_Block_Patterns_Registry
	 */
	protected $registry;

	public function set_up() {
		parent::set_up();
		$this->registry = WP_Block_Patterns_Registry::get_instance();
	}

	public function tear_down() {
		$this->registry = null;

		parent::tear_down();
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_for_unregistered_pattern() {
		$this->assertFalse( $this->registry->is_registered( 'my/pattern' ) );
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_true_for_registered_pattern() {
		$this->registry->register(
			'my/pattern',
			array(
				'title'   => 'My Pattern',
				'content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertTrue( $this->registry->is_registered( 'my/pattern' ) );
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_returns_false_after_unregistering_pattern() {
		$this->registry->register(
			'my/pattern',
			array(
				'title'   => 'My Pattern',
				'content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->',
			)
		);

		$this->registry->unregister( 'my/pattern' );

		$this->assertFalse( $this->registry->is_registered( 'my/pattern' ) );
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_with_invalid_pattern_name() {
		$this->assertFalse( $this->registry->is_registered( '' ) );
		$this->assertFalse( $this->registry->is_registered( null ) );
	}
}
