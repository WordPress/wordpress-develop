<?php
/**
 * Tests for WP_Theme::get_block_patterns.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.4.0
 *
 * @group blocks
 * @group themes
 *
 * @covers WP_Theme::get_block_patterns
 */
class Tests_Theme_WPThemeGetBlockPatterns extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass() {
		// Ensure development mode is reset before running these tests.
		unset( $GLOBALS['_wp_tests_development_mode'] );
	}

	public static function wpTearDownAfterClass() {
		// Ensure development mode is reset after running these tests.
		unset( $GLOBALS['_wp_tests_development_mode'] );
	}

	/**
	 * Test helper to access the private get_pattern_cache method of a theme.
	 *
	 * @param WP_Theme $wp_theme A WP_Theme object.
	 * @return array|false Returns an array of patterns if cache is found, otherwise false.
	 */
	private function get_pattern_cache( $wp_theme ) {
		$reflection = new ReflectionMethod( $wp_theme, 'get_pattern_cache' );
		$reflection->setAccessible( true );

		$pattern_cache = $reflection->invoke( $wp_theme, 'get_pattern_cache' );
		$reflection->setAccessible( false );

		return $pattern_cache;
	}

	/**
	 * @ticket 59490
	 *
	 * @dataProvider data_get_block_patterns
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

		$this->assertTrue( $theme->exists(), 'The test theme could not be found.' );

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
			'The cache for block theme patterns should match the expected.'
		);
		$theme->delete_pattern_cache();
		$this->assertFalse(
			$this->get_pattern_cache( $theme ),
			'The cache for block theme patterns should have been cleared.'
		);
	}

	/**
	 * @ticket 59490
	 * @group ms-excluded
	 */
	public function test_should_clear_cache_after_switching_theme() {
		switch_theme( 'block-theme' );
		$theme1 = wp_get_theme();

		$this->assertTrue( $theme1->exists(), 'The block-theme test theme could not be found.' );

		$theme1->get_block_patterns();
		$this->assertSameSets(
			array(),
			$this->get_pattern_cache( $theme1 ),
			'The cache for block theme should be empty.'
		);

		switch_theme( 'block-theme-patterns' );

		$theme2 = wp_get_theme();
		$this->assertTrue( $theme2->exists(), 'The block-theme-patterns test theme could not be found.' );

		$this->assertFalse( $this->get_pattern_cache( $theme1 ), 'Cache should not be set for block theme after switch theme.' );
		$this->assertFalse( $this->get_pattern_cache( $theme2 ), 'Cache should not be set for block theme patterns before being requested.' );

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
			'The cache for block theme patterns should match the expected.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_block_patterns() {
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
	 * Tests that WP_Theme::get_block_patterns() clears existing cache when in theme development mode.
	 *
	 * @ticket 59591
	 */
	public function test_should_clear_existing_cache_when_in_development_mode() {
		$theme = wp_get_theme( 'block-theme-patterns' );

		$this->assertTrue( $theme->exists(), 'The test theme could not be found.' );

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
			'The cache for block theme patterns should be set.'
		);

		// Calling the function while in theme development mode should clear the cache.
		$GLOBALS['_wp_tests_development_mode'] = 'theme';
		$theme->get_block_patterns( $theme );
		unset( $GLOBALS['_wp_tests_development_mode'] ); // Reset to not pollute other tests.
		$this->assertFalse(
			$this->get_pattern_cache( $theme ),
			'The cache for block theme patterns should have been cleared due to theme development mode.'
		);
	}
}
