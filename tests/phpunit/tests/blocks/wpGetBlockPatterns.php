<?php
/**
 * Tests for _wp_get_block_patterns.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.4.0
 *
 * @group blocks
 *
 * @covers ::_wp_get_block_patterns
 */
class Tests_Blocks_WpGetBlockPatterns extends WP_UnitTestCase {
	/**
	 * @ticket 59490
	 *
	 * @dataProvider data_wp_get_block_patterns
	 *
	 * @param string $theme    The theme's slug.
	 * @param array  $expected The expected pattern data.
	 */
	public function test_should_return_block_patterns( $theme, $expected ) {
		$patterns = _wp_get_block_patterns( wp_get_theme( $theme ) );
		$this->assertSameSets( $expected, $patterns );
	}

	/**
	 * @ticket 59490
	 */
	public function test_delete_theme_cache() {
		$theme = wp_get_theme( 'block-theme-patterns' );
		_wp_get_block_patterns( $theme );
		$this->assertSameSets(
			array(
				'cta.php' => array(
					'title'       => 'Centered Call To Action',
					'slug'        => 'block-theme-patterns/cta',
					'description' => '',
					'categories'  => array( 'call-to-action' ),
				),
			),
			$theme->get_pattern_cache(),
			'The transient for block theme patterns should be set'
		);
		$theme->delete_pattern_cache();
		$this->assertFalse(
			$theme->get_pattern_cache(),
			'The transient for block theme patterns should have been cleared'
		);
	}

	/**
	 * @ticket 59490
	 */
	public function test_should_clear_transient_after_switching_theme() {
		switch_theme( 'block-theme' );
		$theme1 = wp_get_theme();
		_wp_get_block_patterns( $theme1 );
		$this->assertSameSets(
			array(),
			$theme1->get_pattern_cache(),
			'The transient for block theme should be set'
		);
		switch_theme( 'block-theme-patterns' );
		$this->assertFalse( $theme1->get_pattern_cache(), 'Transient should not be set for block theme after switch theme' );
		$theme2 = wp_get_theme();
		$this->assertFalse( $theme2->get_pattern_cache(), 'Transient should not be set for block theme patterns before being requested' );
		_wp_get_block_patterns( $theme2 );
		$this->assertSameSets(
			array(
				'cta.php' => array(
					'title'       => 'Centered Call To Action',
					'slug'        => 'block-theme-patterns/cta',
					'description' => '',
					'categories'  => array( 'call-to-action' ),
				),

			),
			$theme2->get_pattern_cache(),
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
		_wp_get_block_patterns( $theme );
		$this->assertSameSets(
			array(
				'cta.php' => array(
					'title'       => 'Centered Call To Action',
					'slug'        => 'block-theme-patterns/cta',
					'description' => '',
					'categories'  => array( 'call-to-action' ),
				),
			),
			$theme->get_pattern_cache(),
			'The transient for block theme patterns should be set'
		);

		// Calling the function while in theme development mode should clear the cache.
		$GLOBALS['_wp_tests_development_mode'] = 'theme';
		_wp_get_block_patterns( $theme );
		unset( $GLOBALS['_wp_tests_development_mode'] ); // Reset to not pollute other tests.
		$this->assertFalse(
			$theme->get_pattern_cache(),
			'The transient for block theme patterns should have been cleared due to theme development mode'
		);
	}
}
