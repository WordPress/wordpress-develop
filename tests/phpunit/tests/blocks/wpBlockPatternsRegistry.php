<?php
/**
 * Tests for WP_Block_Patterns_Registry.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.4.0
 *
 * @group blocks
 */
class Tests_Blocks_wpBlockPatternsRegistry extends WP_UnitTestCase {

	/**
	 * Fake block patterns registry.
	 *
	 * @since 6.4.0
	 * @var WP_Block_Patterns_Registry
	 */
	private $registry = null;

	/**
	 * Original registered patterns.
	 * This is the value from the internal private property.
	 *
	 * @since 6.5.0
	 * @var array
	 */
	private $original_registered_patterns = null;

	/**
	 * Set up each test method.
	 *
	 * @since 6.4.0
	 */
	public function set_up() {
		parent::set_up();

		$this->registry                     = new WP_Block_Patterns_Registry();
		$this->original_registered_patterns = $this->get_registered_patterns_variable_value();
	}

	/**
	 * Tear down each test method.
	 *
	 * @since 6.4.0
	 */
	public function tear_down() {
		$this->registry = null;

		$registry = WP_Block_Type_Registry::get_instance();

		if ( $registry->is_registered( 'tests/my-block' ) ) {
			$registry->unregister( 'tests/my-block' );
		}

		$this->set_registered_patterns_variable_value( $this->original_registered_patterns );
		parent::tear_down();
	}

	/**
	 * Should reject missing pattern name.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Patterns_Registry::register
	 */
	public function test_missing_name() {
		$name     = null;
		$settings = array(
			'title'   => 'Test Pattern',
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);

		$success = $this->registry->register( $name, $settings );
		$this->assertFalse( $success );
	}

	/**
	 * Should reject non-string name.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Patterns_Registry::register
	 */
	public function test_invalid_non_string_name() {
		$name     = 123;
		$settings = array(
			'title'   => 'Test Pattern',
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);

		$success = $this->registry->register( $name, $settings );
		$this->assertFalse( $success );
	}

	/**
	 * Should missing title.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Patterns_Registry::register
	 */
	public function test_missing_title() {
		$name     = 'test/pattern';
		$settings = array(
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);

		$success = $this->registry->register( $name, $settings );
		$this->assertFalse( $success );
	}

	/**
	 * Should reject non-string title.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Patterns_Registry::register
	 */
	public function test_invalid_non_string_title() {
		$name     = 'test/pattern';
		$settings = array(
			'title'   => 456,
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);

		$success = $this->registry->register( $name, $settings );
		$this->assertFalse( $success );
	}

	/**
	 * Should reject missing content.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Patterns_Registry::register
	 */
	public function test_missing_content() {
		$name     = 'Test Pattern';
		$settings = array(
			'title' => 'Test Pattern',
		);

		$success = $this->registry->register( $name, $settings );
		$this->assertFalse( $success );
	}

	/**
	 * Should reject non-string content.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Block_Patterns_Registry::register
	 */
	public function test_invalid_non_string_content() {
		$name     = 'Test Pattern';
		$settings = array(
			'title'   => 'Test Pattern',
			'content' => 789,
		);

		$success = $this->registry->register( $name, $settings );
		$this->assertFalse( $success );
	}

	/**
	 * Should accept valid pattern.
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 *
	 * @ticket 59476
	 */
	public function test_register_block_pattern() {
		$name     = 'test/pattern';
		$settings = array(
			'title'   => 'Pattern One',
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);

		$success = $this->registry->register( $name, $settings );
		$this->assertTrue( $success );
	}

	/**
	 * Unregistering should fail if a pattern is not registered.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::unregister
	 *
	 * @expectedIncorrectUsage WP_Block_Patterns_Registry::unregister
	 */
	public function test_unregister_not_registered_block() {
		$success = $this->registry->unregister( 'test/unregistered' );
		$this->assertFalse( $success );
	}

	/**
	 * Should unregister existing patterns.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::unregister
	 */
	public function test_unregister_block_pattern() {
		$name     = 'test/pattern';
		$settings = array(
			'title'   => 'Pattern One',
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);

		$this->registry->register( $name, $settings );
		$success = $this->registry->unregister( $name );
		$this->assertTrue( $success );
	}

	/**
	 * Should find all registered patterns.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 * @covers WP_Block_Patterns_Registry::get_all_registered
	 */
	public function test_get_all_registered() {
		$pattern_one = array(
			'title'   => 'Pattern One',
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);
		$this->registry->register( 'test/one', $pattern_one );

		$pattern_two = array(
			'title'   => 'Pattern Two',
			'content' => '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->',
		);
		$this->registry->register( 'test/two', $pattern_two );

		$pattern_three = array(
			'title'   => 'Pattern Three',
			'content' => '<!-- wp:paragraph --><p>Three</p><!-- /wp:paragraph -->',
		);
		$this->registry->register( 'test/three', $pattern_three );

		$pattern_one['name']   = 'test/one';
		$pattern_two['name']   = 'test/two';
		$pattern_three['name'] = 'test/three';

		$expected = array(
			$pattern_one,
			$pattern_two,
			$pattern_three,
		);

		$registered = $this->registry->get_all_registered();
		$this->assertSame( $expected, $registered );
	}

	/**
	 * Should not find pattern that's not registered.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 * @covers WP_Block_Patterns_Registry::get_registered
	 */
	public function test_get_registered_rejects_unknown_pattern_name() {
		$pattern_one = array(
			'title'   => 'Pattern One',
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);
		$this->registry->register( 'test/one', $pattern_one );

		$pattern_two = array(
			'title'   => 'Pattern Two',
			'content' => '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->',
		);
		$this->registry->register( 'test/two', $pattern_two );

		$pattern = $this->registry->get_registered( 'test/three' );
		$this->assertNull( $pattern );
	}

	/**
	 * Should find registered pattern by name.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 * @covers WP_Block_Patterns_Registry::get_registered
	 */
	public function test_get_registered() {
		$pattern_one = array(
			'title'   => 'Pattern One',
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);
		$this->registry->register( 'test/one', $pattern_one );

		$pattern_two = array(
			'title'   => 'Pattern Two',
			'content' => '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->',
		);
		$this->registry->register( 'test/two', $pattern_two );

		$pattern_three = array(
			'title'   => 'Pattern Three',
			'content' => '<!-- wp:paragraph --><p>Three</p><!-- /wp:paragraph -->',
		);
		$this->registry->register( 'test/three', $pattern_three );

		$pattern_two['name'] = 'test/two';

		$pattern = $this->registry->get_registered( 'test/two' );
		$this->assertSame( $pattern_two, $pattern );
	}

	/**
	 * Should insert a theme attribute into Template Part blocks in registered patterns.
	 *
	 * @ticket 59583
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 * @covers WP_Block_Patterns_Registry::get_all_registered
	 */
	public function test_get_all_registered_includes_theme_attribute() {
		$test_pattern = array(
			'title'   => 'Test Pattern',
			'content' => '<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header"} /-->',
		);
		$this->registry->register( 'test/pattern', $test_pattern );

		$expected = sprintf(
			'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header","theme":"%s"} /-->',
			get_stylesheet()
		);
		$patterns = $this->registry->get_all_registered();
		$this->assertSame( $expected, $patterns[0]['content'] );
	}

	/**
	 * Should insert hooked blocks into registered patterns.
	 *
	 * @ticket 59476
	 * @ticket 60008
	 * @ticket 60506
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 * @covers WP_Block_Patterns_Registry::get_all_registered
	 */
	public function test_get_all_registered_includes_hooked_blocks() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/paragraph' => 'after',
				),
			)
		);

		$pattern_one = array(
			'title'   => 'Pattern One',
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);
		$this->registry->register( 'test/one', $pattern_one );

		$pattern_two = array(
			'title'   => 'Pattern Two',
			'content' => '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->',
		);
		$this->registry->register( 'test/two', $pattern_two );

		$pattern_three = array(
			'title'   => 'Pattern Three',
			'content' => '<!-- wp:paragraph --><p>Three</p><!-- /wp:paragraph -->',
		);
		$this->registry->register( 'test/three', $pattern_three );

		$pattern_one['name']       = 'test/one';
		$pattern_two['name']       = 'test/two';
		$pattern_two['content']   .= '<!-- wp:tests/my-block /-->';
		$pattern_three['name']     = 'test/three';
		$pattern_three['content'] .= '<!-- wp:tests/my-block /-->';

		$registered = $this->registry->get_all_registered();
		$this->assertCount( 3, $registered );
		$this->assertStringEndsWith( '<!-- wp:tests/my-block /-->', $registered[1]['content'] );
		$this->assertStringEndsWith( '<!-- wp:tests/my-block /-->', $registered[2]['content'] );
	}

	/**
	 * Should insert a theme attribute into Template Part blocks in registered patterns.
	 *
	 * @ticket 59583
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 * @covers WP_Block_Patterns_Registry::get_registered
	 */
	public function test_get_registered_includes_theme_attribute() {
		$test_pattern = array(
			'title'   => 'Test Pattern',
			'content' => '<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header"} /-->',
		);
		$this->registry->register( 'test/pattern', $test_pattern );

		$expected = sprintf(
			'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header","theme":"%s"} /-->',
			get_stylesheet()
		);
		$pattern  = $this->registry->get_registered( 'test/pattern' );
		$this->assertSame( $expected, $pattern['content'] );
	}

	/**
	 * Should insert hooked blocks into registered patterns.
	 *
	 * @ticket 59476
	 * @ticket 60008
	 * @ticket 60506
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 * @covers WP_Block_Patterns_Registry::get_registered
	 */
	public function test_get_registered_includes_hooked_blocks() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/heading' => 'before',
				),
			)
		);

		$pattern_one = array(
			'title'   => 'Pattern One',
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);
		$this->registry->register( 'test/one', $pattern_one );

		$pattern_two = array(
			'title'   => 'Pattern Two',
			'content' => '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->',
		);
		$this->registry->register( 'test/two', $pattern_two );

		$pattern = $this->registry->get_registered( 'test/one' );
		$this->assertStringStartsWith( '<!-- wp:tests/my-block /-->', $pattern['content'] );
	}

	/**
	 * Should return false for pattern that's not registered.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 * @covers WP_Block_Patterns_Registry::is_registered
	 */
	public function test_is_registered_for_unknown_pattern() {
		$pattern = $this->registry->is_registered( 'test/one' );
		$this->assertFalse( $pattern );
	}

	/**
	 * Should return true if pattern is registered.
	 *
	 * @ticket 59476
	 *
	 * @covers WP_Block_Patterns_Registry::register
	 * @covers WP_Block_Patterns_Registry::is_registered
	 */
	public function test_is_registered_for_known_pattern() {
		$pattern_one = array(
			'title'   => 'Pattern One',
			'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
		);
		$this->registry->register( 'test/one', $pattern_one );

		$result = $this->registry->is_registered( 'test/one' );
		$this->assertTrue( $result );
	}

	/**
	 * Ensures theme patterns are registered on init.
	 *
	 * @ticket 59723
	 *
	 * @covers ::_register_theme_block_patterns
	 */
	public function test_register_theme_block_patterns_on_init() {
		// This test needs to use access static class properties.
		$registry = WP_Block_Patterns_Registry::get_instance();

		// Ensure we're using a theme with patterns.
		switch_theme( 'twentytwentythree' );

		$theme          = wp_get_theme();
		$theme_patterns = array_values( wp_list_pluck( $theme->get_block_patterns(), 'slug' ) );

		// This helper is fired on the init hook.
		_register_theme_block_patterns();

		$registered = wp_list_pluck( $registry->get_all_registered(), 'name' );

		// Cleanup patterns registry.
		foreach ( $theme_patterns as $pattern ) {
			$registry->unregister( $pattern );
		}

		$this->assertSameSets( $theme_patterns, array_intersect( $theme_patterns, $registered ), 'Could not confirm theme patterns were registered.' );
	}

	/**
	 * Ensures theme patterns are not registered when no themes are active and valid.
	 *
	 * @ticket 59723
	 *
	 * @covers ::_register_theme_block_patterns
	 */
	public function test_register_theme_block_patterns_on_init_skipped_during_install() {
		// This test needs to use access static class properties.
		$registry = WP_Block_Patterns_Registry::get_instance();

		// Ensure we're using a theme with patterns.
		switch_theme( 'twentytwentythree' );

		$theme          = wp_get_theme();
		$theme_patterns = array_values( wp_list_pluck( $theme->get_block_patterns(), 'slug' ) );

		/*
		 * This will short-circuit theme activation.
		 * @see wp_get_active_and_valid_themes().
		 */
		wp_installing( true );

		// This helper is fired on the init hook.
		_register_theme_block_patterns();

		$registered = wp_list_pluck( $registry->get_all_registered(), 'name' );

		// Cleanup.
		wp_installing( false );

		$this->assertEmpty( array_intersect( $theme_patterns, $registered ), 'Theme patterns were were incorrectly registered.' );
	}

	/**
	 * Ensures theme patterns are lazy loaded.
	 *
	 * @ticket 59532
	 *
	 * @covers WP_Block_Patterns_Registry::get_all_registered
	 */
	public function test_lazy_loading_block_patterns_get_all_registered() {
		// This test needs to use access static class properties.
		$registry = WP_Block_Patterns_Registry::get_instance();

		// Testing only the first pattern loaded from the theme.
		$pattern_name = 'twentytwentythree/footer-default';

		// Ensure we're using a theme with patterns.
		switch_theme( 'twentytwentythree' );

		// This helper is fired on the init hook.
		_register_theme_block_patterns();

		// Get the value of the private property.
		$registered_patterns = $this->get_registered_patterns_variable_value();

		$this->assertTrue(
			isset( $registered_patterns[ $pattern_name ]['filePath'] ) &&
			! isset( $registered_patterns[ $pattern_name ]['content'] ),
			'Pattern was not lazy loaded.'
		);

		$all_patterns = $registry->get_all_registered();

		$loaded_pattern = array_values(
			array_filter(
				$all_patterns,
				function ( $pattern ) use ( $pattern_name ) {
					return $pattern['name'] === $pattern_name;
				}
			)
		);

		$this->assertTrue(
			! empty( $loaded_pattern[0]['content'] ),
			'Content not loaded.'
		);

		// Check if the original property was updated.
		$registered_patterns = $this->get_registered_patterns_variable_value();

		$this->assertTrue(
			! empty( $registered_patterns[ $pattern_name ]['content'] ),
			'Content not updated.'
		);
	}

	/**
	 * Ensures theme patterns are lazy loaded.
	 *
	 * @ticket 59532
	 *
	 * @covers WP_Block_Patterns_Registry::get_registered
	 */
	public function test_lazy_loading_block_patterns_get_registered() {
		// This test needs to use access static class properties.
		$registry = WP_Block_Patterns_Registry::get_instance();

		// Testing only the first pattern loaded from the theme.
		$pattern_name = 'twentytwentythree/footer-default';

		// Ensure we're using a theme with patterns.
		switch_theme( 'twentytwentythree' );

		// This helper is fired on the init hook.
		_register_theme_block_patterns();

		// Get the value of the private property.
		$registered_patterns = $this->get_registered_patterns_variable_value();

		$this->assertTrue(
			isset( $registered_patterns[ $pattern_name ]['filePath'] ) &&
			! isset( $registered_patterns[ $pattern_name ]['content'] ),
			'Pattern was not lazy loaded.'
		);

		$loaded_pattern = $registry->get_registered( $pattern_name );

		$this->assertTrue(
			! empty( $loaded_pattern['content'] ),
			'Content not loaded.'
		);

		// Check if the original property was updated.
		$registered_patterns = $this->get_registered_patterns_variable_value();

		$this->assertTrue(
			! empty( $registered_patterns[ $pattern_name ]['content'] ),
			'Content not updated.'
		);
	}

	/**
	 * Get the value of the `$registered_patterns` private property.
	 *
	 * @return array
	 */
	private function get_registered_patterns_variable_value() {
		$registry = WP_Block_Patterns_Registry::get_instance();
		// Use Reflection to access private property.
		$reflection = new ReflectionClass( $registry );
		$property   = $reflection->getProperty( 'registered_patterns' );
		$property->setAccessible( true );

		// Get the value of the private property.
		$registered_patterns = $property->getValue( $registry );
		$property->setAccessible( false );

		return $registered_patterns;
	}

	/**
	 * Set the value of the `$registered_patterns` private property.
	 *
	 * @param array $value The value to set.
	 */
	private function set_registered_patterns_variable_value( $value ) {
		$registry = WP_Block_Patterns_Registry::get_instance();
		// Use Reflection to access private property.
		$reflection = new ReflectionClass( $registry );
		$property   = $reflection->getProperty( 'registered_patterns' );
		$property->setAccessible( true );

		// Set the value of the private property.
		$property->setValue( $registry, $value );
		$property->setAccessible( false );
	}
}
