<?php

/**
 * Tests for wp_get_default_update_php_url().
 *
 * @group functions
 *
 * @covers ::wp_get_default_update_php_url
 */
class Tests_Functions_WpGetDefaultUpdatePhpUrl extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_contextually_localized_php_upgrade_url() {
		$translated = 'https://example.test/update-php-localized/';

		$filter = static function ( $translation, $text, $context ) use ( $translated ) {
			if (
				'https://wordpress.org/support/update-php/' === $text
				&& 'localized PHP upgrade information page' === $context
			) {
				return $translated;
			}

			return $translation;
		};

		add_filter( 'gettext_with_context', $filter, 10, 3 );

		$this->assertSame( $translated, wp_get_default_update_php_url() );

		remove_filter( 'gettext_with_context', $filter, 10 );
	}
}
