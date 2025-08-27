<?php

/**
 * @group l10n
 * @group i18n
 *
 * @covers ::wp_get_list_item_separator
 */
class Tests_L10n_wpGetListItemSeparator extends WP_UnitTestCase {

	/**
	 * Tests that the function returns a value when the $wp_locale global is not set.
	 *
	 * @ticket 56698
	 */
	public function test_should_return_default_value_if_wp_locale_is_not_set() {
		global $wp_locale;

		$original_locale = $wp_locale;
		$wp_locale       = null;

		$actual = wp_get_list_item_separator();

		$wp_locale = $original_locale;

		$this->assertSame( __( ', ' ), $actual );
	}
}
