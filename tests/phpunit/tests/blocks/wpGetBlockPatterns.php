<?php
/**
 * Tests for WP_Theme::get_block_patterns.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.4.0
 *
 * @group blocks
 *
 * @covers WP_Theme::get_block_patterns
 */
class Tests_Blocks_WPThemeGetBlockPatterns extends WP_UnitTestCase {

	/**
	 * Test helper to access the private get_pattern_cache method of a theme.
	 *
	 * @param WP_Theme $wp_theme A WP_Theme object.
	 * @return array|false Returns an array of patterns if cache is found, otherwise false.
	 */
	public function get_pattern_cache( $wp_theme ) {
		$reflection = new ReflectionMethod( $wp_theme, 'get_pattern_cache' );
		$reflection->setAccessible( true );

		$pattern_cache = $reflection->invoke( $wp_theme, 'get_pattern_cache' );
		$reflection->setAccessible( false );

		return $pattern_cache;
	}

	/**
	 * @ticket 59490
	 *
	 * @dataProvider data_wp_get_block_patterns
	 *
	 * @param string $theme_slug The theme's slug.
	 * @param array  $expected   The expected pattern data.
	 */
	public function test_should_return_block_patterns( $theme_slug, $expected ) {
		$theme    = wp_get_theme( $theme_slug );
		$patterns = $theme->get_block_patterns();
		$this->assertSameSets( $expected, $patterns );
	}

	/**
	 * @ticket 59490
	 *
	 * @covers WP_Theme::delete_pattern_cache
	 */
	public function test_delete_pattern_cache() {
		$theme = wp_get_theme( 'block-theme-patterns' );
		$theme->get_block_patterns();
		$this->assertSameSets(
			array(
				'cta.php' => array(
					'title'       => 'Centered Call To Action',
					'slug'        => 'block-theme-patterns/cta',
					'description' => '',
					'categories'  => array( 'call-to-action' ),
				),
			),
			$this->get_pattern_cache( $theme ),
			'The transient for block theme patterns should be set'
		);
		$theme->delete_pattern_cache();
		$this->assertFalse(
			$this->get_pattern_cache( $theme ),
			'The transient for block theme patterns should have been cleared'
		);
	}

	/**
	 * @ticket 59490
	 */
	public function test_should_clear_transient_after_switching_theme() {
		switch_theme( 'block-theme' );
		$theme1 = wp_get_theme();
		$theme1->get_block_patterns();
		$this->assertSameSets(
			array(),
			$this->get_pattern_cache( $theme1 ),
			'The transient for block theme should be set'
		);
		switch_theme( 'block-theme-patterns' );
		$this->assertFalse( $this->get_pattern_cache( $theme1 ), 'Transient should not be set for block theme after switch theme' );
		$theme2 = wp_get_theme();
		$this->assertFalse( $this->get_pattern_cache( $theme2 ), 'Transient should not be set for block theme patterns before being requested' );
		$theme2->get_block_patterns( $theme2 );
		$this->assertSameSets(
			array(
				'cta.php' => array(
					'title'       => 'Centered Call To Action',
					'slug'        => 'block-theme-patterns/cta',
					'description' => '',
					'categories'  => array( 'call-to-action' ),
				),

			),
			$this->get_pattern_cache( $theme2 ),
			'The transient for block theme patterns should be set'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_get_block_patterns() {
		return array(
			array(
				'theme'    => 'block-theme',
				'patterns' => array(),
			),
			array(
				'theme'    => 'block-theme-child',
				'patterns' => array(),
			),
			array(
				'theme'    => 'block-theme-patterns',
				'patterns' => array(
					'cta.php' => array(
						'title'       => 'Centered Call To Action',
						'slug'        => 'block-theme-patterns/cta',
						'description' => '',
						'categories'  => array( 'call-to-action' ),
					),
				),
			),
			array(
				'theme'    => 'broken-theme',
				'patterns' => array(),
			),
			array(
				'theme'    => 'invalid',
				'patterns' => array(),
			),
		);
	}

	/**
	 * Tests that _wp_get_block_patterns() clears existing transient when in theme development mode.
	 *
	 * @ticket 59591
	 */
	public function test_should_clear_existing_transient_when_in_development_mode() {
		$theme = wp_get_theme( 'block-theme-patterns' );

		// Calling the function should set the cache.
		$theme->get_block_patterns();
		$this->assertSameSets(
			array(
				'cta.php' => array(
					'title'       => 'Centered Call To Action',
					'slug'        => 'block-theme-patterns/cta',
					'description' => '',
					'categories'  => array( 'call-to-action' ),
				),
			),
			$this->get_pattern_cache( $theme ),
			'The transient for block theme patterns should be set'
		);

		// Calling the function while in theme development mode should clear the cache.
		$GLOBALS['_wp_tests_development_mode'] = 'theme';
		$theme->get_block_patterns( $theme );
		unset( $GLOBALS['_wp_tests_development_mode'] ); // Reset to not pollute other tests.
		$this->assertFalse(
			$this->get_pattern_cache( $theme ),
			'The transient for block theme patterns should have been cleared due to theme development mode'
		);
	}
}
