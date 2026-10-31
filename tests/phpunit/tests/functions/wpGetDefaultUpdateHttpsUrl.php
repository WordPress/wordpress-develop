<?php

/**
 * Tests for wp_get_default_update_https_url().
 *
 * @group functions
 *
 * @covers ::wp_get_default_update_https_url
 */
class Tests_Functions_WpGetDefaultUpdateHttpsUrl extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_localized_https_documentation_url() {
		$translated = 'https://example.test/update-https-localized/';

		$filter = static function ( $translation, $text ) use ( $translated ) {
			if ( 'https://developer.wordpress.org/advanced-administration/security/https/' === $text ) {
				return $translated;
			}

			return $translation;
		};

		add_filter( 'gettext', $filter, 10, 2 );

		$this->assertSame( $translated, wp_get_default_update_https_url() );

		remove_filter( 'gettext', $filter, 10 );
	}
}
