<?php

require_once __DIR__ . '/Admin_Includes_Schema_TestCase.php';

/**
 * @group admin
 *
 * @covers ::populate_options
 */
class Admin_Includes_Schema_PopulateOptions_Test extends Admin_Includes_Schema_TestCase {

	/**
	 * @ticket 44893
	 * @dataProvider data_populate_options
	 */
	public function test_populate_options( $options, $expected ) {
		global $wpdb;

		$orig_options  = $wpdb->options;
		$wpdb->options = self::$options;

		populate_options( $options );

		wp_cache_delete( 'alloptions', 'options' );

		$results = array();
		foreach ( $expected as $option => $value ) {
			$results[ $option ] = get_option( $option );
		}

		$wpdb->query( "TRUNCATE TABLE {$wpdb->options}" );

		$wpdb->options = $orig_options;

		$this->assertSame( $expected, $results );
	}

	public function data_populate_options() {
		return array(
			array(
				array(),
				array(
					// Random options to check.
					'posts_per_rss'    => '10',
					'rss_use_excerpt'  => '0',
					'mailserver_url'   => 'mail.example.com',
					'mailserver_login' => 'login@example.com',
					'mailserver_pass'  => 'password',
				),
			),
			array(
				array(
					'posts_per_rss'   => '7',
					'rss_use_excerpt' => '1',
				),
				array(
					// Random options to check.
					'posts_per_rss'    => '7',
					'rss_use_excerpt'  => '1',
					'mailserver_url'   => 'mail.example.com',
					'mailserver_login' => 'login@example.com',
					'mailserver_pass'  => 'password',
				),
			),
			array(
				array(
					'custom_option' => '1',
				),
				array(
					// Random options to check.
					'custom_option'    => '1',
					'posts_per_rss'    => '10',
					'rss_use_excerpt'  => '0',
					'mailserver_url'   => 'mail.example.com',
					'mailserver_login' => 'login@example.com',
					'mailserver_pass'  => 'password',
				),
			),
			array(
				array(
					'use_quicktags' => '1',
				),
				array(
					// This option is disallowed and should never exist.
					'use_quicktags' => false,
				),
			),
			array(
				array(
					'rss_0123456789abcdef0123456789abcdef' => '1',
					'rss_0123456789abcdef0123456789abcdef_ts' => '1',
				),
				array(
					// These options would be obsolete magpie cache data and should never exist.
					'rss_0123456789abcdef0123456789abcdef' => false,
					'rss_0123456789abcdef0123456789abcdef_ts' => false,
				),
			),
		);
	}

	/**
	 * Ensures that deprecated timezone strings set as a default in a translation are handled correctly.
	 *
	 * @ticket 56468
	 */
	public function test_populate_options_when_locale_uses_deprecated_timezone_string() {
		global $wpdb;

		// Back up.
		$orig_options  = $wpdb->options;
		$wpdb->options = self::$options;

		// Set the "default" value for the timezone to a deprecated timezone.
		add_filter(
			'gettext_with_context',
			static function ( $translation, $text, $context ) {
				if ( '0' === $text && 'default GMT offset or timezone string' === $context ) {
					return 'America/Buenos_Aires';
				}

				return $translation;
			},
			10,
			3
		);

		// Test.
		populate_options();

		wp_cache_delete( 'alloptions', 'options' );

		$result = get_option( 'timezone_string' );

		// Reset.
		$wpdb->query( "TRUNCATE TABLE {$wpdb->options}" );
		$wpdb->options = $orig_options;

		// Assert.
		$this->assertSame( 'America/Buenos_Aires', $result );
	}
}
