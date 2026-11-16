<?php

/**
 * Tests for the Hello Dolly bundled plugin.
 *
 * @group plugins
 *
 * @covers ::hello_dolly
 */
class Tests_Plugins_HelloDolly extends WP_UnitTestCase {

	/**
	 * Tests that hello_dolly() uses the 'hello-dolly' text domain.
	 *
	 * @ticket 65676
	 */
	public function test_hello_dolly_uses_hello_dolly_textdomain() {
		require_once ABSPATH . 'wp-content/plugins/hello.php';

		$passed_domain = null;
		$filter        = function ( $translation, $text, $domain ) use ( &$passed_domain ) {
			if ( 'Quote from Hello Dolly song, by Jerry Herman:' === $text ) {
				$passed_domain = $domain;
			}
			return $translation;
		};

		add_filter( 'gettext', $filter, 10, 3 );
		ob_start();
		hello_dolly();
		ob_end_clean();
		remove_filter( 'gettext', $filter, 10 );

		$this->assertSame( 'hello-dolly', $passed_domain );
	}
}
