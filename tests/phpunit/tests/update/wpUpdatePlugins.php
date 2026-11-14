<?php

/**
 * @group update
 *
 * @covers ::wp_update_plugins
 * @covers ::wp_update_themes
 * @covers ::_wp_is_wporg_update_uri
 */
class Tests_Update_WpUpdatePlugins extends WP_UnitTestCase {

	/**
	 * Request bodies captured by the HTTP mock.
	 *
	 * @var array[]
	 */
	private $requests = array();

	public function set_up() {
		parent::set_up();

		$this->requests = array();

		add_filter( 'pre_http_request', array( $this, 'mock_update_check' ), 10, 3 );
		delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		wp_cache_delete( 'plugins', 'plugins' );
		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	public function mock_update_check( $preempt, $args, $url ) {
		if ( ! str_contains( $url, 'api.wordpress.org/plugins/update-check/' ) ) {
			return $preempt;
		}

		$this->requests[] = $args['body'];

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'plugins'      => array(),
					'translations' => array(),
					'no_update'    => array(),
				)
			),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Primes the plugins cache so that get_plugins() returns the given headers.
	 *
	 * @param string[] $update_uris Map of plugin file => `Update URI` header value.
	 */
	private function set_installed_plugins( array $update_uris ) {
		$plugins = array();

		foreach ( $update_uris as $file => $update_uri ) {
			$plugins[ $file ] = array(
				'Name'      => $file,
				'Version'   => '1.0.0',
				'UpdateURI' => $update_uri,
			);
		}

		wp_cache_set( 'plugins', array( '' => $plugins ), 'plugins' );
		update_option( 'active_plugins', array_keys( $update_uris ) );
	}

	public function test_plugins_with_third_party_update_uri_are_not_sent() {
		$this->set_installed_plugins(
			array(
				'no-header/no-header.php' => '',
				'dotorg/dotorg.php'       => 'https://wordpress.org/plugins/dotorg/',
				'short/short.php'         => 'w.org/plugins/short',
				'github/github.php'       => 'https://github.com/example/github',
				'disabled/disabled.php'   => 'false',
				'lookalike/lookalike.php' => 'https://wordpress.org.example.com/plugins/lookalike/',
			)
		);

		wp_update_plugins();

		$this->assertCount( 1, $this->requests, 'Expected a single update check request.' );

		$sent = json_decode( $this->requests[0]['plugins'], true );

		$this->assertSame(
			array( 'no-header/no-header.php', 'dotorg/dotorg.php', 'short/short.php' ),
			array_keys( $sent['plugins'] ),
			'Only plugins that may be updated from WordPress.org should be sent.'
		);
		$this->assertSame(
			array( 'no-header/no-header.php', 'dotorg/dotorg.php', 'short/short.php' ),
			$sent['active'],
			'Omitted plugins should not appear in the list of active plugins.'
		);
	}

	public function test_omitted_plugins_are_still_recorded_as_checked() {
		$this->set_installed_plugins(
			array(
				'github/github.php' => 'https://github.com/example/github',
			)
		);

		wp_update_plugins();

		$current = get_site_transient( 'update_plugins' );

		$this->assertArrayHasKey( 'github/github.php', $current->checked );
	}

	public function test_update_uri_hook_still_runs_for_omitted_plugins() {
		$this->set_installed_plugins(
			array(
				'github/github.php' => 'https://github.com/example/github',
			)
		);

		$hook = new MockAction();
		add_filter( 'update_plugins_github.com', array( $hook, 'filter' ), 10, 2 );

		wp_update_plugins();

		$this->assertSame( 1, $hook->get_call_count(), 'The `update_plugins_{$hostname}` filter should still be applied.' );
	}

	public function test_themes_with_third_party_update_uri_are_not_sent() {
		$sent = null;

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$sent ) {
				if ( ! str_contains( $url, 'api.wordpress.org/themes/update-check/' ) ) {
					return $preempt;
				}

				$sent = json_decode( $args['body']['themes'], true );

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'themes'       => array(),
							'translations' => array(),
							'no_update'    => array(),
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		delete_site_transient( 'update_themes' );
		wp_update_themes();

		$this->assertIsArray( $sent, 'Expected an update check request.' );
		$this->assertNotEmpty( $sent['themes'], 'Themes without an `Update URI` should still be sent.' );

		foreach ( $sent['themes'] as $theme_data ) {
			$this->assertTrue(
				empty( $theme_data['UpdateURI'] ) || _wp_is_wporg_update_uri( $theme_data['UpdateURI'], 'theme' ),
				'Only themes that may be updated from WordPress.org should be sent.'
			);
		}
	}

	/**
	 * @dataProvider data_wporg_update_uri
	 */
	public function test_wp_is_wporg_update_uri( $uri, $type, $expected ) {
		$this->assertSame( $expected, _wp_is_wporg_update_uri( $uri, $type ) );
	}

	public function data_wporg_update_uri() {
		return array(
			'plugin, canonical URL'     => array( 'https://wordpress.org/plugins/example/', 'plugin', true ),
			'plugin, no trailing slash' => array( 'https://wordpress.org/plugins/example', 'plugin', true ),
			'plugin, short ID'          => array( 'w.org/plugins/example', 'plugin', true ),
			'theme, canonical URL'      => array( 'https://wordpress.org/themes/example/', 'theme', true ),
			'theme, short ID'           => array( 'w.org/themes/example', 'theme', true ),
			'plugin URI for a theme'    => array( 'https://wordpress.org/plugins/example/', 'theme', false ),
			'github'                    => array( 'https://github.com/example/example', 'plugin', false ),
			'false'                     => array( 'false', 'plugin', false ),
			'lookalike host'            => array( 'https://wordpress.org.example.com/plugins/example/', 'plugin', false ),
			'extra path'                => array( 'https://wordpress.org/plugins/example/extra', 'plugin', false ),
			'empty'                     => array( '', 'plugin', false ),
		);
	}
}
