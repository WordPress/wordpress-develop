<?php

/**
 * Tests for the `wp_get_tooltip_helper()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::wp_get_tooltip_helper
 */
class Tests_General_WpGetTooltipHelper extends WP_UnitTestCase {

	/**
	 * Tests that empty or whitespace content returns an empty string.
	 *
	 * @ticket 66618
	 */
	public function test_wp_get_tooltip_helper_empty_content() {
		$this->assertSame( '', wp_get_tooltip_helper( '' ) );
		$this->assertSame( '', wp_get_tooltip_helper( '   ' ) );
	}

	/**
	 * Tests default tooltip markup and attributes.
	 *
	 * @ticket 66618
	 */
	public function test_wp_get_tooltip_helper_default_structure() {
		$markup = wp_get_tooltip_helper( 'Help text content.' );

		$this->assertStringContainsString( 'class="wp-tooltip wp-is-tooltip"', $markup );
		$this->assertStringContainsString( 'class="wp-tooltip__toggle"', $markup );
		$this->assertStringContainsString( 'class="dashicons dashicons-editor-help"', $markup );
		$this->assertStringContainsString( 'popover="hint"', $markup );
		$this->assertStringContainsString( 'role="tooltip"', $markup );
		$this->assertStringContainsString( 'class="wp-tooltip__text">Help text content.</span>', $markup );
	}

	/**
	 * Tests toggletip type markup and attributes.
	 *
	 * @ticket 66618
	 */
	public function test_wp_get_tooltip_helper_toggletip_type() {
		$markup = wp_get_tooltip_helper(
			'Toggletip message',
			array(
				'type'  => 'toggletip',
				'label' => 'Information',
				'id'    => 'my-toggletip',
			)
		);

		$this->assertStringContainsString( 'class="wp-tooltip wp-is-toggletip"', $markup );
		$this->assertStringContainsString( 'aria-haspopup="dialog"', $markup );
		$this->assertStringContainsString( 'popovertarget="my-toggletip"', $markup );
		$this->assertStringContainsString( 'id="my-toggletip"', $markup );
	}

	/**
	 * Tests using custom button markup.
	 *
	 * @ticket 66618
	 */
	public function test_wp_get_tooltip_helper_custom_button() {
		$custom_button = '<button type="button" class="my-custom-btn"><span>Click</span></button>';
		$markup        = wp_get_tooltip_helper(
			'Help text',
			array(
				'button' => $custom_button,
			)
		);

		$this->assertStringContainsString( 'class="my-custom-btn wp-tooltip__toggle"', $markup );
	}

	/**
	 * Tests custom class, id, and icon arguments.
	 *
	 * @ticket 66618
	 */
	public function test_wp_get_tooltip_helper_custom_class_id_icon() {
		$markup = wp_get_tooltip_helper(
			'Detailed explanation',
			array(
				'id'    => 'custom-help-id',
				'class' => 'extra-tooltip-style',
				'icon'  => 'dashicons-info',
			)
		);

		$this->assertStringContainsString( 'extra-tooltip-style', $markup );
		$this->assertStringContainsString( 'id="custom-help-id"', $markup );
		$this->assertStringContainsString( 'dashicons dashicons-info', $markup );
	}
}
