<?php
/**
 * Unit tests covering the wp_get_icon() function.
 *
 * @package WordPress
 * @subpackage Icons
 * @since 7.1.0
 *
 * @group icons
 *
 * @covers ::wp_get_icon
 */
class Tests_Icons_WpGetIcon extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		/*
		 * Other suites reset the icon registries, wiping collections and icons that
		 * `init` only registers once. Re-register them when missing so order-dependent
		 * tests pass.
		 */
		if ( ! WP_Icon_Collections_Registry::get_instance()->is_registered( 'core' ) ) {
			_wp_register_default_icon_collections();
		}
		if ( ! WP_Icons_Registry::get_instance()->is_registered( 'core/plus' ) ) {
			_wp_register_default_icons();
		}
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_returns_svg_for_known_icon() {
		$output = wp_get_icon( 'core/plus' );
		$this->assertStringStartsWith( '<svg ', $output );
		$this->assertStringContainsString( '</svg>', $output );
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_returns_empty_string_for_unknown_icon() {
		$output = wp_get_icon( 'this-icon-does-not-exist' );
		$this->assertSame( '', $output );
	}

	/**
	 * @ticket 64847
	 * @ticket 66338
	 */
	public function test_wp_get_icon_default_attributes() {
		$output = wp_get_icon( 'core/plus' );
		$this->assertStringContainsStringIgnoringCase( 'viewbox="0 0 24 24"', $output );
		$this->assertStringContainsString( 'width="24"', $output );
		$this->assertStringContainsString( 'height="24"', $output );
		$this->assertStringContainsString( 'aria-hidden="true"', $output );
		$this->assertStringContainsString( 'focusable="false"', $output );
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_custom_size() {
		$output = wp_get_icon( 'core/plus', array( 'size' => 32 ) );
		$this->assertStringContainsString( 'width="32"', $output );
		$this->assertStringContainsString( 'height="32"', $output );
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_size_null_leaves_dimensions_untouched() {
		$output    = wp_get_icon( 'core/plus', array( 'size' => null ) );
		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'svg' ) );
		$this->assertNull( $processor->get_attribute( 'width' ) );
		$this->assertNull( $processor->get_attribute( 'height' ) );
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_size_zero_outputs_zero_dimensions() {
		$output = wp_get_icon( 'core/plus', array( 'size' => 0 ) );
		$this->assertStringContainsString( 'width="0"', $output );
		$this->assertStringContainsString( 'height="0"', $output );
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_custom_class() {
		$output = wp_get_icon( 'core/plus', array( 'class' => 'my-button-icon' ) );
		$this->assertStringContainsString( 'class="my-button-icon"', $output );
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_multiple_classes() {
		$output = wp_get_icon( 'core/plus', array( 'class' => 'foo bar baz' ) );
		$this->assertStringContainsString( 'class="foo bar baz"', $output );
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_with_label() {
		$output = wp_get_icon( 'core/plus', array( 'label' => 'Add item' ) );
		$this->assertStringContainsString( 'role="img"', $output );
		$this->assertStringContainsString( 'aria-label="Add item"', $output );
		$this->assertStringNotContainsString( 'aria-hidden', $output );
		$this->assertStringNotContainsString( 'focusable', $output );
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_without_label_is_hidden() {
		$output = wp_get_icon( 'core/plus' );
		$this->assertStringContainsString( 'aria-hidden="true"', $output );
		$this->assertStringContainsString( 'focusable="false"', $output );
		$this->assertStringNotContainsString( 'role="img"', $output );
		$this->assertStringNotContainsString( 'aria-label', $output );
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_contains_svg_content() {
		$output = wp_get_icon( 'core/plus' );
		$this->assertStringContainsString( '<path ', $output );
	}

	/**
	 * @ticket 66344
	 */
	public function test_wp_get_icon_filter_can_customize_core_admin_icons() {
		$filter = static function ( $svg, $name ) {
			if ( 'core-admin/wordpress' === $name ) {
				return '<svg><circle cx="12" cy="12" r="10" /></svg>';
			}

			return $svg;
		};

		add_filter( 'wp_get_icon', $filter, 10, 2 );

		try {
			$output = wp_get_icon( 'core-admin/wordpress' );
		} finally {
			remove_filter( 'wp_get_icon', $filter, 10 );
		}

		$this->assertSame( '<svg><circle cx="12" cy="12" r="10" /></svg>', $output );
	}

	/**
	 * @ticket 66344
	 * @dataProvider data_pre_wp_get_icon_output
	 *
	 * @param string $expected The short-circuited output.
	 */
	public function test_pre_wp_get_icon_bypasses_lookup_and_output_filter( $expected ) {
		$args          = array( 'size' => 32 );
		$filter        = function ( $pre, $name, $received_args ) use ( $expected, $args ) {
			$this->assertNull( $pre );
			$this->assertSame( 'unregistered/icon', $name );
			$this->assertSame( $args, $received_args );

			return $expected;
		};
		$output_filter = static function () {
			return 'The output filter should not run.';
		};

		add_filter( 'pre_wp_get_icon', $filter, 10, 3 );
		add_filter( 'wp_get_icon', $output_filter );

		try {
			$this->assertSame( $expected, wp_get_icon( 'unregistered/icon', $args ) );
		} finally {
			remove_filter( 'pre_wp_get_icon', $filter, 10 );
			remove_filter( 'wp_get_icon', $output_filter );
		}
	}

	/**
	 * Data provider for test_pre_wp_get_icon_bypasses_lookup_and_output_filter().
	 *
	 * @return array[]
	 */
	public function data_pre_wp_get_icon_output() {
		return array(
			'custom SVG' => array( '<svg><circle cx="12" cy="12" r="10" /></svg>' ),
			'no icon'    => array( '' ),
		);
	}

	/**
	 * @ticket 66344
	 */
	public function test_pre_wp_get_icon_null_preserves_normal_processing() {
		$expected = wp_get_icon( 'core/plus', array( 'size' => 32 ) );
		add_filter( 'pre_wp_get_icon', '__return_null' );

		try {
			$this->assertSame( $expected, wp_get_icon( 'core/plus', array( 'size' => 32 ) ) );
		} finally {
			remove_filter( 'pre_wp_get_icon', '__return_null' );
		}
	}

	/**
	 * @ticket 64847
	 */
	public function test_wp_get_icon_escapes_attributes() {
		$output = wp_get_icon( 'core/plus', array( 'class' => '"><script>alert(1)</script>' ) );
		$this->assertStringNotContainsString( '<script>', $output );
	}
}
