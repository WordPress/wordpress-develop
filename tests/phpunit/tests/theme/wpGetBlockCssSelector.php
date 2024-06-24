<?php
/**
 * Tests wp_get_block_css_selector().
 *
 * @since 6.3.0
 *
 * @group themes
 *
 * @covers ::wp_get_block_css_selector
 */

class Tests_Theme_WpGetBlockCssSelector extends WP_Theme_UnitTestCase {
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

	private function register_test_block( $name, $selectors = null, $supports = null ) {
		$this->test_block_name = $name;

		return register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 2,
				'attributes'  => array(),
				'selectors'   => $selectors,
				'supports'    => $supports,
			)
		);
	}

	/**
	* @ticket 58586
	*/
	public function test_get_root_selector_via_selectors_api() {
		$block_type = self::register_test_block(
			'test/block-with-selectors',
			array( 'root' => '.wp-custom-block-class' )
		);

		$selector = wp_get_block_css_selector( $block_type );
		$this->assertSame( '.wp-custom-block-class', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_get_root_selector_via_experimental_property() {
		$block_type = self::register_test_block(
			'test/block-without-selectors',
			null,
			array( '__experimentalSelector' => '.experimental-selector' )
		);

		$selector = wp_get_block_css_selector( $block_type );
		$this->assertSame( '.experimental-selector', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_default_root_selector_generation_for_core_block() {
		$block_type = self::register_test_block(
			'core/without-selectors-or-supports',
			null,
			null
		);

		$selector = wp_get_block_css_selector( $block_type );
		$this->assertSame( '.wp-block-without-selectors-or-supports', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_default_root_selector_generation() {
		$block_type = self::register_test_block(
			'test/without-selectors-or-supports',
			null,
			null
		);

		$selector = wp_get_block_css_selector( $block_type );
		$this->assertSame( '.wp-block-test-without-selectors-or-supports', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_get_feature_selector_via_selectors_api() {
		$block_type = self::register_test_block(
			'test/feature-selector',
			array( 'typography' => array( 'root' => '.typography' ) ),
			null
		);

		$selector = wp_get_block_css_selector( $block_type, 'typography' );
		$this->assertSame( '.typography', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_get_feature_selector_via_selectors_api_shorthand_property() {
		$block_type = self::register_test_block(
			'test/shorthand-feature-selector',
			array( 'typography' => '.typography' ),
			null
		);

		$selector = wp_get_block_css_selector( $block_type, 'typography' );
		$this->assertSame( '.typography', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_no_feature_level_selector_via_selectors_api() {
		$block_type = self::register_test_block(
			'test/null-feature-selector',
			array( 'root' => '.fallback-root-selector' ),
			null
		);

		$selector = wp_get_block_css_selector( $block_type, 'typography' );
		$this->assertSame( null, $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_fallback_feature_level_selector_via_selectors_api_to_generated_class() {
		$block_type = self::register_test_block(
			'test/fallback-feature-selector',
			array(),
			null
		);

		$selector = wp_get_block_css_selector( $block_type, 'typography', true );
		$this->assertSame( '.wp-block-test-fallback-feature-selector', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_fallback_feature_level_selector_via_selectors_api() {
		$block_type = self::register_test_block(
			'test/fallback-feature-selector',
			array( 'root' => '.fallback-root-selector' ),
			null
		);

		$selector = wp_get_block_css_selector( $block_type, 'typography', true );
		$this->assertSame( '.fallback-root-selector', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_get_feature_selector_via_experimental_property() {
		$block_type = self::register_test_block(
			'test/experimental-feature-selector',
			null,
			array(
				'typography' => array(
					'__experimentalSelector' => '.experimental-typography',
				),
			)
		);

		$selector = wp_get_block_css_selector( $block_type, 'typography' );
		$this->assertSame( '.wp-block-test-experimental-feature-selector .experimental-typography', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_fallback_feature_selector_via_experimental_property() {
		$block_type = self::register_test_block(
			'test/fallback-feature-selector',
			null,
			array()
		);

		$selector = wp_get_block_css_selector( $block_type, 'typography', true );
		$this->assertSame( '.wp-block-test-fallback-feature-selector', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_no_feature_selector_via_experimental_property() {
		$block_type = self::register_test_block(
			'test/null-experimental-feature-selector',
			null,
			array()
		);

		$selector = wp_get_block_css_selector( $block_type, 'typography' );
		$this->assertSame( null, $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_get_subfeature_selector_via_selectors_api() {
		$block_type = self::register_test_block(
			'test/subfeature-selector',
			array(
				'typography' => array(
					'textDecoration' => '.root .typography .text-decoration',
				),
			),
			null
		);

		$selector = wp_get_block_css_selector(
			$block_type,
			array( 'typography', 'textDecoration' )
		);

		$this->assertSame( '.root .typography .text-decoration', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_fallback_subfeature_selector_via_selectors_api() {
		$block_type = self::register_test_block(
			'test/subfeature-selector',
			array(
				'typography' => array( 'root' => '.root .typography' ),
			),
			null
		);

		$selector = wp_get_block_css_selector(
			$block_type,
			array( 'typography', 'textDecoration' ),
			true
		);

		$this->assertSame( '.root .typography', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_no_subfeature_level_selector_via_selectors_api() {
		$block_type = self::register_test_block(
			'test/null-subfeature-selector',
			array(),
			null
		);

		$selector = wp_get_block_css_selector( $block_type, array( 'typography', 'fontSize' ) );
		$this->assertSame( null, $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_fallback_subfeature_selector_via_experimental_property() {
		$block_type = self::register_test_block(
			'test/fallback-subfeature-selector',
			null,
			array()
		);

		$selector = wp_get_block_css_selector(
			$block_type,
			array( 'typography', 'fontSize' ),
			true
		);
		$this->assertSame( '.wp-block-test-fallback-subfeature-selector', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_no_subfeature_selector_via_experimental_property() {
		$block_type = self::register_test_block(
			'test/null-experimental-subfeature-selector',
			null,
			array()
		);

		$selector = wp_get_block_css_selector(
			$block_type,
			array( 'typography', 'fontSize' )
		);
		$this->assertSame( null, $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_empty_target_returns_null() {
		$block_type = self::register_test_block(
			'test/null-experimental-subfeature-selector',
			null,
			array()
		);

		$selector = wp_get_block_css_selector( $block_type, array() );
		$this->assertSame( null, $selector );

		$selector = wp_get_block_css_selector( $block_type, '' );
		$this->assertSame( null, $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_string_targets_for_features() {
		$block_type = self::register_test_block(
			'test/target-types-for-features',
			array( 'typography' => '.found' ),
			null
		);

		$selector = wp_get_block_css_selector( $block_type, 'typography' );
		$this->assertSame( '.found', $selector );

		$selector = wp_get_block_css_selector( $block_type, array( 'typography' ) );
		$this->assertSame( '.found', $selector );
	}

	/**
	 * @ticket 58586
	 */
	public function test_string_targets_for_subfeatures() {
		$block_type = self::register_test_block(
			'test/target-types-for-features',
			array(
				'typography' => array( 'fontSize' => '.found' ),
			),
			null
		);

		$selector = wp_get_block_css_selector( $block_type, 'typography.fontSize' );
		$this->assertSame( '.found', $selector );

		$selector = wp_get_block_css_selector( $block_type, array( 'typography', 'fontSize' ) );
		$this->assertSame( '.found', $selector );
	}
}
