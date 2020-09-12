<?php

/**
 * @group l10n
 * @group i18n
 */
class Tests_L10n_loadScriptTextdomain extends WP_UnitTestCase {
	public function site_url_subdirectory( $site_url ) {
		return $site_url . '/wp';
	}

	public function relative_path_from_cdn( $relative, $src ) {
		if ( 0 === strpos( $src, 'https://my-cdn.com/wordpress/' ) ) {
			return substr( $src, strlen( 'https://my-cdn.com/wordpress/' ) );
		}

		return $relative;
	}

	public function plugins_url_custom_domain() {
		return 'https://plugins.example.com';
	}

	public function content_url_custom_domain_with_no_path() {
		return 'https://content.example.com';
	}

	/**
	 * @ticket 45528
	 */
	public function test_resolve_relative_path() {
		$json_translations = file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' );

		wp_enqueue_script( 'test-example-root', '/wp-includes/js/script.js', array(), null );
		$this->assertSame( $json_translations, load_script_textdomain( 'test-example-root', 'default', DIR_TESTDATA . '/languages' ) );

		// Assets on a CDN.
		add_filter( 'load_script_textdomain_relative_path', array( $this, 'relative_path_from_cdn' ), 10, 2 );
		wp_enqueue_script( 'test-example-cdn', 'https://my-cdn.com/wordpress/wp-includes/js/script.js', array(), null );
		$this->assertSame( $json_translations, load_script_textdomain( 'test-example-cdn', 'default', DIR_TESTDATA . '/languages' ) );
		remove_filter( 'load_script_textdomain_relative_path', array( $this, 'relative_path_from_cdn' ) );

		// Test for WordPress installs in a subdirectory.
		add_filter( 'site_url', array( $this, 'site_url_subdirectory' ) );
		wp_enqueue_script( 'test-example-subdir', '/wp/wp-includes/js/script.js', array(), null );
		$this->assertSame( $json_translations, load_script_textdomain( 'test-example-subdir', 'default', DIR_TESTDATA . '/languages' ) );
		remove_filter( 'site_url', array( $this, 'site_url_subdirectory' ) );
	}

	/**
	 * @ticket 46336
	 */
	public function test_resolve_relative_path_custom_plugins_url() {
		$json_translations = file_get_contents( DIR_TESTDATA . '/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json' );

		add_filter( 'plugins_url', array( $this, 'plugins_url_custom_domain' ) );
		wp_enqueue_script( 'plugin-example-1', 'https://plugins.example.com/my-plugin/js/script.js', array(), null );
		$this->assertSame( $json_translations, load_script_textdomain( 'plugin-example-1', 'internationalized-plugin', DIR_TESTDATA . '/languages' ) );
		remove_filter( 'plugins_url', array( $this, 'plugins_url_custom_domain' ) );
	}

	/**
	 * @ticket 46387
	 */
	public function test_resolve_relative_path_custom_content_url() {
		$json_translations = file_get_contents( DIR_TESTDATA . '/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json' );

		add_filter( 'content_url', array( $this, 'content_url_custom_domain_with_no_path' ) );
		wp_enqueue_script( 'plugin-example-2', 'https://content.example.com/plugins/my-plugin/js/script.js', array(), null );
		$this->assertSame( $json_translations, load_script_textdomain( 'plugin-example-2', 'internationalized-plugin', DIR_TESTDATA . '/languages' ) );
		remove_filter( 'content_url', array( $this, 'content_url_custom_domain_with_no_path' ) );
	}
}
