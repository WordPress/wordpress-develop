<?php
/**
 * Tests for WP_Theme_JSON::sanitize().
 *
 * @group theme
 * @covers WP_Theme_JSON::sanitize
 */
class Tests_Theme_WPThemeJson_Sanitize extends WP_UnitTestCase {

	/**
	 * Helper to call the protected static sanitize method.
	 *
	 * @param array $input
	 * @param array $valid_block_names
	 * @param array $valid_element_names
	 * @param array $valid_variations
	 * @return array
	 */
	private function call_sanitize( $input, $valid_block_names = array(), $valid_element_names = array(), $valid_variations = array() ) {
		$ref = new ReflectionMethod( WP_Theme_JSON::class, 'sanitize' );
		$ref->setAccessible( true );
		return $ref->invoke( null, $input, $valid_block_names, $valid_element_names, $valid_variations );
	}

	public function test_sanitize_keeps_only_valid_top_level_keys_and_converts_vars() {
		$input = array(
			'version'    => WP_Theme_JSON::LATEST_SCHEMA,
			'title'      => 'Sample',
			'foo'        => 'bar', // invalid top-level key; should be removed.
			'styles'     => array(
				'color' => array(
					// Internal var: format should be converted to var(--wp--preset--color--black).
					'text' => 'var:preset|color|black',
				),
			),
			'settings'   => array(
				'color' => array(
					'custom' => true,
				),
			),
		);

		$result = $this->call_sanitize(
			$input,
			array(),                         // No specific blocks needed for this test.
			array( 'link' ),                 // Limit elements to 'link'.
			array()                          // No style variations in this test.
		);

		// Only valid top-level keys remain.
		$this->assertArrayHasKey( 'version', $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'styles', $result );
		$this->assertArrayHasKey( 'settings', $result );
		$this->assertArrayNotHasKey( 'foo', $result );

		// var:preset format should be converted.
		$this->assertSame(
			'var(--wp--preset--color--black)',
			$result['styles']['color']['text']
		);

		// A simple valid settings path should remain.
		$this->assertTrue( $result['settings']['color']['custom'] );
	}

	public function test_sanitize_filters_elements_and_allows_valid_pseudo_selectors() {
		$input = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'elements' => array(
					'link' => array(
						'color'          => array( 'text' => '#000' ),
						':hover'         => array( 'color' => array( 'text' => '#111' ) ),
						':focus-visible' => array( 'color' => array( 'text' => '#222' ) ),
						':unknown'       => array( 'color' => array( 'text' => 'red' ) ), // Not in allow-list; should be removed.
					),
					'fake' => array(
						'color' => array( 'text' => '#123456' ), // Not a valid element name; should be removed.
					),
				),
			),
		);

		$result = $this->call_sanitize(
			$input,
			array(), // no blocks
			// Allow only 'link' per WP_Theme_JSON::ELEMENTS keys.
			array( 'link' ),
			array()
		);

		// 'fake' element should be removed.
		$this->assertArrayHasKey( 'styles', $result );
		$this->assertArrayHasKey( 'elements', $result['styles'] );
		$this->assertArrayHasKey( 'link', $result['styles']['elements'] );
		$this->assertArrayNotHasKey( 'fake', $result['styles']['elements'] );

		// Base element styles should remain.
		$this->assertSame( '#000', $result['styles']['elements']['link']['color']['text'] );

		// Allowed pseudo selectors for link should remain.
		$this->assertArrayHasKey( ':hover', $result['styles']['elements']['link'] );
		$this->assertArrayHasKey( ':focus-visible', $result['styles']['elements']['link'] );

		// Unrecognized pseudo selector should be removed.
		$this->assertArrayNotHasKey( ':unknown', $result['styles']['elements']['link'] );
	}

	public function test_sanitize_non_array_returns_empty_array() {
		$this->assertSame(
			array(),
			$this->call_sanitize( null, array(), array(), array() )
		);

		$this->assertSame(
			array(),
			$this->call_sanitize( 'not-an-array', array(), array(), array() )
		);
	}

	public function test_sanitize_caching_returns_identical_results() {
		$input = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'color' => array( 'text' => '#000' ),
			),
		);

		$first  = $this->call_sanitize( $input, array(), array( 'link' ), array() );
		$second = $this->call_sanitize( $input, array(), array( 'link' ), array() );

		$this->assertSame( $first, $second, 'Sanitize results should be identical (cache hit).' );
	}
	public function test_sanitize_cache_populates_and_resets_correctly() {
		// Ensure a clean slate.
		WP_Theme_JSON::reset_sanitize_input_cache();

		// Reflect the private static cache property.
		$prop = new ReflectionProperty( WP_Theme_JSON::class, 'sanitize_input_cache' );
		$prop->setAccessible( true );

		$cache = $prop->getValue();
		$this->assertIsArray( $cache );
		$this->assertCount( 0, $cache, 'Cache should start empty after reset.' );

		$input1 = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'color' => array( 'text' => '#111' ),
			),
		);
		$input2 = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'color' => array( 'text' => '#222' ),
			),
		);

		// First sanitize call should populate cache with one entry.
		$this->call_sanitize( $input1, array(), array( 'link' ), array() );
		$cache = $prop->getValue();
		$this->assertCount( 1, $cache, 'Cache should have one entry after first sanitize.' );

		// Second sanitize with the same input should not add a new entry.
		$this->call_sanitize( $input1, array(), array( 'link' ), array() );
		$cache = $prop->getValue();
		$this->assertCount( 1, $cache, 'Cache should still have one entry after identical sanitize call.' );

		// Sanitizing a different input should add a second entry.
		$this->call_sanitize( $input2, array(), array( 'link' ), array() );
		$cache = $prop->getValue();
		$this->assertCount( 2, $cache, 'Cache should have two entries after sanitizing a different input.' );

		// Reset cache and verify it's cleared.
		WP_Theme_JSON::reset_sanitize_input_cache();
		$cache = $prop->getValue();
		$this->assertCount( 0, $cache, 'Cache should be empty after reset.' );
	}
}
