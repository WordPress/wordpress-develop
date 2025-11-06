<?php
/**
 * @group dependencies
 * @group i18n
 */
class Tests_Dependencies_StylesLtrSupport extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		switch_to_locale( 'fa_IR' ); // RTL language
		wp_styles()->registered = array();
	}

	public function tear_down(): void {
		restore_previous_locale();
		parent::tear_down();
	}

	public function test_ltr_css_replace_data_is_set() {
		$handle = 'sample-style';
		wp_register_style( $handle, 'https://example.com/style.css' );
		wp_style_add_data( $handle, 'ltr', 'replace' );

		$styles = wp_styles();
		$this->assertArrayHasKey( 'ltr', $styles->registered[ $handle ]->extra );
		$this->assertSame( 'replace', $styles->registered[ $handle ]->extra['ltr'] );
	}

	public function test_ltr_css_suffix_data_is_set() {
		$handle = 'sample-style-suffix';
		wp_register_style( $handle, 'https://example.com/style.min.css' );
		wp_style_add_data( $handle, 'ltr', 'suffix' );

		$styles = wp_styles();
		$this->assertArrayHasKey( 'ltr', $styles->registered[ $handle ]->extra );
		$this->assertSame( 'suffix', $styles->registered[ $handle ]->extra['ltr'] );
	}

	public function test_no_ltr_data_for_ltr_locale() {
		restore_previous_locale();
		switch_to_locale( 'en_US' ); // LTR language

		$handle = 'sample-style-ltr';
		wp_register_style( $handle, 'https://example.com/style.css' );
		wp_style_add_data( $handle, 'ltr', 'replace' );

		$styles = wp_styles();
		$this->assertArrayHasKey( 'ltr', $styles->registered[ $handle ]->extra );
		$this->assertSame( 'replace', $styles->registered[ $handle ]->extra['ltr'] );
	}
}
