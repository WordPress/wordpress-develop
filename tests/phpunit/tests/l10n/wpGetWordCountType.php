<?php

/**
 * @group formatting
 *
 * @covers ::wp_get_word_count_type
 */
class Tests_L10n_WpGetWordCountType extends WP_UnitTestCase {
	/**
	 * Confirms the function returns a value when the $wp_locale global is not set.
	 * @ticket 56698
	 */
	public function test_should_return_default_if_locale_unset() {
		global $wp_locale;
		$locale = $wp_locale;
		$wp_locale = null;
		$this->assertEquals( 'words', wp_get_word_count_type() );
		$wp_locale = $locale;
	}
}
