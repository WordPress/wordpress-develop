<?php

/**
 * @group admin
 *
 * @covers ::wp_admin_canonical_url
 */
class Tests_Admin_Includes_Misc_WpAdminCanonicalUrl_Test extends WP_UnitTestCase {

	/**
	 * Original `$_SERVER` values.
	 *
	 * @var array
	 */
	private $server_orig;

	public function set_up() {
		parent::set_up();

		$this->server_orig = $_SERVER;
	}

	public function tear_down() {
		$_SERVER = $this->server_orig;

		parent::tear_down();
	}

	/**
	 * Tests wp_admin_canonical_url().
	 *
	 * @ticket 65192
	 *
	 * @dataProvider data_wp_admin_canonical_url
	 *
	 * @param array  $server_vars `$_SERVER` variables to set.
	 * @param string $expected    Expected output substring.
	 */
	public function test_wp_admin_canonical_url( array $server_vars, $expected ) {
		foreach ( $server_vars as $key => $value ) {
			$_SERVER[ $key ] = $value;
		}

		ob_start();
		wp_admin_canonical_url();
		$output = ob_get_clean();

		$this->assertStringContainsString( $expected, $output );
		$this->assertStringContainsString( '<script>', $output );
	}

	/**
	 * Data provider for test_wp_admin_canonical_url().
	 *
	 * @return array<string, array{
	 *     server_vars: array<string, string>,
	 *     expected:    string
	 * }>
	 */
	public function data_wp_admin_canonical_url(): array {
		return array(
			'no removable query args'       => array(
				'server_vars' => array(
					'HTTP_HOST'   => 'example.org',
					'REQUEST_URI' => '/wp-admin/index.php',
				),
				'expected'    => 'href="http://example.org/wp-admin/index.php"',
			),
			'removable query args'          => array(
				'server_vars' => array(
					'HTTP_HOST'   => 'example.org',
					'REQUEST_URI' => '/wp-admin/index.php?settings-updated=true&other=arg',
				),
				'expected'    => 'href="http://example.org/wp-admin/index.php?other=arg"',
			),
			'multiple removable query args' => array(
				'server_vars' => array(
					'HTTP_HOST'   => 'example.org',
					'REQUEST_URI' => '/wp-admin/edit.php?trashed=1&locked=1&paged=2',
				),
				'expected'    => 'href="http://example.org/wp-admin/edit.php?paged=2"',
			),
			'https'                         => array(
				'server_vars' => array(
					'HTTP_HOST'   => 'example.org',
					'REQUEST_URI' => '/wp-admin/index.php',
					'HTTPS'       => 'on',
				),
				'expected'    => 'href="https://example.org/wp-admin/index.php"',
			),
		);
	}

	/**
	 * Tests wp_admin_canonical_url() when removable query args are filtered to be empty.
	 *
	 * @ticket 65192
	 */
	public function test_wp_admin_canonical_url_with_empty_removable_args() {
		add_filter( 'removable_query_args', '__return_empty_array' );

		ob_start();
		wp_admin_canonical_url();
		$output = ob_get_clean();

		remove_filter( 'removable_query_args', '__return_empty_array' );

		$this->assertEmpty( $output, 'Output should be empty when removable query args are filtered to be empty.' );
	}

	/**
	 * Tests the `wp_admin_canonical_url` filter.
	 *
	 * @ticket 65192
	 */
	public function test_wp_admin_canonical_url_filter() {
		$_SERVER['HTTP_HOST']   = 'example.org';
		$_SERVER['REQUEST_URI'] = '/wp-admin/index.php?settings-updated=true';

		add_action(
			'wp_admin_canonical_url',
			static function () {
				return 'https://custom.example.org/canonical';
			}
		);

		ob_start();
		wp_admin_canonical_url();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'href="https://custom.example.org/canonical"', $output );
	}
}
