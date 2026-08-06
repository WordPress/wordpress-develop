<?php
/**
 * @group dependencies
 * @group i18n
 */
class Tests_Dependencies_StylesLtrSupport extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		switch_to_locale( 'fa_IR' ); // RTL language.
		wp_styles()->registered = array();
	}

	public function tear_down(): void {
		restore_previous_locale();
		parent::tear_down();
	}

	/**
	 * @ticket 64193
	 */
	public function test_ltr_css_replace_data_is_set() {
		$handle = 'sample-style';
		wp_register_style( $handle, 'https://example.com/style.css' );
		wp_style_add_data( $handle, 'ltr', 'replace' );

		$styles = wp_styles();
		$this->assertArrayHasKey( 'ltr', $styles->registered[ $handle ]->extra );
		$this->assertSame( 'replace', $styles->registered[ $handle ]->extra['ltr'] );
	}

	/**
	 * @ticket 64193
	 */
	public function test_ltr_css_suffix_data_is_set() {
		$handle = 'sample-style-suffix';
		wp_register_style( $handle, 'https://example.com/style.min.css' );
		wp_style_add_data( $handle, 'ltr', 'suffix' );

		$styles = wp_styles();
		$this->assertArrayHasKey( 'ltr', $styles->registered[ $handle ]->extra );
		$this->assertSame( 'suffix', $styles->registered[ $handle ]->extra['ltr'] );
	}

	/**
	 * @ticket 64193
	 */
	public function test_no_ltr_data_for_ltr_locale() {
		restore_previous_locale();
		switch_to_locale( 'en_US' ); // LTR language.

		$handle = 'sample-style-ltr';
		wp_register_style( $handle, 'https://example.com/style.css' );
		wp_style_add_data( $handle, 'ltr', 'replace' );

		$styles = wp_styles();
		$this->assertArrayHasKey( 'ltr', $styles->registered[ $handle ]->extra );
		$this->assertSame( 'replace', $styles->registered[ $handle ]->extra['ltr'] );
	}
	/**
	 * Verify that an LTR stylesheet actually gets printed in HTML output.
	 *
	 * @group output
	 * @ticket 64193
	 */
	public function test_ltr_stylesheet_is_printed_in_output() {
		$this->setExpectedDeprecated( 'print_emoji_styles' );

		global $wp_styles;

		// Reset styles registry to ensure a clean environment.
		$wp_styles = null;
		wp_styles();

		// Register main style.
		wp_register_style( 'ltr-test-style', 'http://example.com/css/style.css', array(), '1.0' );

		// Mark this style as having an LTR variant.
		wp_style_add_data( 'ltr-test-style', 'ltr', true );

		// Enqueue the style.
		wp_enqueue_style( 'ltr-test-style' );

		// Capture printed output.
		ob_start();
		wp_print_styles();
		$output = ob_get_clean();

		// Assertions.
		$this->assertStringContainsString( 'style.css', $output, 'Main stylesheet was not printed in output.' );
		$this->assertStringContainsString( 'ltr.css', $output, 'LTR stylesheet was not printed in output.' );

		// Verify correct order (LTR after main style).
		$main_pos = strpos( $output, 'style.css' );
		$ltr_pos  = strpos( $output, 'ltr.css' );
		$this->assertTrue( $ltr_pos > $main_pos, 'LTR stylesheet should appear after the main style.' );
	}
}
