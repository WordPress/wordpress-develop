<?php
/**
 * Test wp_get_tooltip().
 *
 * @group general
 * @group template
 * @group tooltip
 *
 * @covers ::wp_get_tooltip
 */
class Tests_General_wpGetTooltip extends WP_UnitTestCase {

	/**
	 * Tests that an empty content value returns an empty string.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_tooltip_returns_empty_string_without_content() {
		$this->assertSame( '', wp_get_tooltip( '' ) );
		$this->assertSame( '', wp_get_tooltip( '   ' ) );
	}

	/**
	 * Tests that the markup contains the expected accessible structure.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_tooltip_returns_accessible_markup() {
		$tooltip = wp_get_tooltip( 'Helpful text.', array( 'id' => 'my-tip' ) );

		// Tooltip output should include a toggle button, but tooltips do not use a popover trigger.
		$this->assertStringContainsString( '<button', $tooltip );
		$this->assertStringContainsString( 'class="wp-tooltip__toggle"', $tooltip );
		$this->assertStringContainsString( 'type="button"', $tooltip );
		$this->assertStringNotContainsString( 'popovertarget="my-tip"', $tooltip );

		// The bubble is a popover holding a text-only described element.
		$this->assertStringContainsString( '<span popover="hint" id="my-tip" class="wp-tooltip__bubble" role="tooltip">', $tooltip );
		$this->assertStringContainsString( '<span id="my-tip-text" class="wp-tooltip__text">Helpful text.</span>', $tooltip );

		// Ensure the tooltip model does not include a close button.
		$this->assertStringNotContainsString( 'class="wp-tooltip__close"', $tooltip );
		$this->assertStringNotContainsString( 'popovertargetaction="hide"', $tooltip );

		$toggletip = wp_get_toggletip(
			'Helpful text.',
			array(
				'id' => 'my-tip',
			)
		);
		// Ensure the toggle tip does contain a close button.
		$this->assertStringContainsString( 'popovertarget="my-tip"', $toggletip );
		$this->assertStringContainsString( 'class="wp-tooltip__close"', $toggletip );
		$this->assertStringContainsString( 'popovertargetaction="hide"', $toggletip );
	}

	/**
	 * Tests that content is escaped.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_tooltip_escapes_content() {
		$html = wp_get_tooltip( '<script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * Tests that custom markup that does not contain valid control markup is ignored.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_tooltip_ignores_invalid_markup() {
		$html = wp_get_tooltip(
			'Helpful text.',
			array(
				'button' => '<div role="button" aria-expanded="false">Contains text</div>',
			)
		);

		$this->assertStringNotContainsString( 'div role="button"', $html, 'String does not contain element from custom HTML.' );
		$this->assertStringContainsString( '<button', $html, 'String does contain a button element.' );
	}

	/**
	 * Tests that custom button markup is retained, and has required attributes.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_tooltip_has_custom_button_markup() {
		$html = wp_get_tooltip(
			'Helpful text.',
			array(
				'button' => '<button type="button" aria-expanded="false">Contains text</button>',
			)
		);

		$this->assertStringContainsString( 'aria-expanded="false"', $html, 'String contains attribute not supported in function.' );
		$this->assertStringNotContainsString( 'dashicons', $html, 'String does not contain content of default button.' );

		$html2 = wp_get_tooltip(
			'Helpful text.',
			array(
				'button' => '<a href="#">Contains text</a>',
			)
		);

		$this->assertStringContainsString( '<a ', $html2, 'String contains anchor element.' );
		$this->assertStringNotContainsString( '<button', $html2, 'String does not contain button element.' );
	}

	/**
	 * Tests that the accessible labels are output and escaped in attributes.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_tooltip_outputs_labels() {
		$html = wp_get_toggletip(
			'Helpful text.',
			array(
				'label'       => 'About this field',
				'close_label' => 'Dismiss',
			)
		);

		$this->assertStringContainsString( 'aria-label="About this field"', $html );
		$this->assertStringContainsString( 'aria-label="Dismiss"', $html );
	}

	/**
	 * Tests that a custom icon class and wrapper class are applied.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_tooltip_applies_icon_and_class() {
		$html = wp_get_tooltip(
			'Helpful text.',
			array(
				'icon'  => 'dashicons-info',
				'class' => 'my-wrap',
			)
		);

		$this->assertStringContainsString( 'class="wp-tooltip wp-is-tooltip my-wrap"', $html );
		$this->assertStringContainsString( 'class="dashicons dashicons-info"', $html );
	}

	/**
	 * Tests that a generated ID is used when none is supplied, and that the
	 * popovertarget matches the bubble ID.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_toggletip_generates_unique_id() {
		$args = array(
			'label'       => 'About this field',
			'close_label' => 'Dismiss',
		);
		$html = wp_get_toggletip( 'Helpful text.', $args );

		$this->assertSame( 1, preg_match( '/id="(wp-tooltip-\d+)"/', $html, $matches ) );

		$id = $matches[1];
		$this->assertStringContainsString( 'popovertarget="' . $id . '"', $html );
		$this->assertStringContainsString( 'id="' . $id . '-text"', $html );
	}

	/**
	 * Tests that the markup consists only of phrasing content so it can be nested
	 * inside a paragraph (or other phrasing context) without the parser closing
	 * the enclosing element and leaving a stray empty paragraph behind.
	 *
	 * @ticket 65660
	 *
	 * @dataProvider data_tooltip_types
	 *
	 * @param string $type The tooltip type, 'tooltip' or 'toggletip'.
	 */
	public function test_wp_get_tooltip_markup_is_phrasing_content( $type ) {
		$html = ( 'toggletip' === $type )
			? wp_get_toggletip( 'Helpful text.', array( 'id' => 'my-tip' ) )
			: wp_get_tooltip( 'Helpful text.', array( 'id' => 'my-tip' ) );

		// The wrapper and popover must not use flow-content elements.
		$this->assertStringNotContainsString( '<div', $html, 'The markup should not contain a div element.' );
		$this->assertStringNotContainsString( '<dialog', $html, 'The markup should not contain a dialog element.' );

		// The wrapper is an inline span.
		$this->assertStringContainsString( '<span class="wp-tooltip ', $html );
	}

	/**
	 * Tests that the toggletip popover preserves dialog semantics and focus
	 * handling after moving away from the native dialog element.
	 *
	 * @ticket 65660
	 */
	public function test_wp_get_toggletip_bubble_uses_dialog_role_and_autofocus() {
		$html = wp_get_toggletip( 'Helpful text.', array( 'id' => 'my-tip' ) );

		// The bubble is a span popover exposing a dialog role.
		$this->assertStringContainsString( '<span popover="auto" id="my-tip" class="wp-tooltip__bubble" role="dialog"', $html );

		// Focus is moved into the popover when opened, matching the native dialog behavior.
		$this->assertStringContainsString( 'tabindex="-1" autofocus>', $html );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_tooltip_types() {
		return array(
			'tooltip'   => array( 'tooltip' ),
			'toggletip' => array( 'toggletip' ),
		);
	}
}
