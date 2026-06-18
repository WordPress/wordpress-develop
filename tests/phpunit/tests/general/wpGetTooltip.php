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
		$html = wp_get_tooltip( 'Helpful text.', array( 'id' => 'my-tip' ) );

		// Toggle is a button that controls the popover and describes it.
		$this->assertStringContainsString( '<button type="button" class="wp-tooltip__toggle"', $html );
		$this->assertStringContainsString( 'popovertarget="my-tip"', $html );
		$this->assertStringContainsString( 'aria-describedby="my-tip-text"', $html );

		// The bubble is a popover holding a text-only described element.
		$this->assertStringContainsString( '<span popover="auto" id="my-tip" class="wp-tooltip__bubble">', $html );
		$this->assertStringContainsString( '<span id="my-tip-text" class="wp-tooltip__text">Helpful text.</span>', $html );

		// A native close button that hides the popover.
		$this->assertStringContainsString( 'class="wp-tooltip__close"', $html );
		$this->assertStringContainsString( 'popovertargetaction="hide"', $html );
	}

	/**
	 * Tests that disallowed roles and attributes are omitted.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_tooltip_omits_disallowed_attributes() {
		$html = wp_get_tooltip( 'Helpful text.' );

		$this->assertStringNotContainsString( 'role="tooltip"', $html );
		$this->assertStringNotContainsString( 'aria-haspopup', $html );
		$this->assertStringNotContainsString( 'aria-live', $html );
		$this->assertStringNotContainsString( 'title=', $html );
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
	 * Tests that the accessible labels are output and escaped in attributes.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_tooltip_outputs_labels() {
		$html = wp_get_tooltip(
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

		$this->assertStringContainsString( 'class="wp-tooltip my-wrap"', $html );
		$this->assertStringContainsString( 'class="dashicons dashicons-info"', $html );
	}

	/**
	 * Tests that a generated ID is used when none is supplied, and that the
	 * describedby target matches the bubble ID.
	 *
	 * @ticket 55343
	 */
	public function test_wp_get_tooltip_generates_unique_id() {
		$html = wp_get_tooltip( 'Helpful text.' );

		$this->assertSame( 1, preg_match( '/id="(wp-tooltip-\d+)"/', $html, $matches ) );

		$id = $matches[1];
		$this->assertStringContainsString( 'popovertarget="' . $id . '"', $html );
		$this->assertStringContainsString( 'aria-describedby="' . $id . '-text"', $html );
		$this->assertStringContainsString( 'id="' . $id . '-text"', $html );
	}
}
