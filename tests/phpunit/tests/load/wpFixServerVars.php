<?php

/**
 * Tests for wp_fix_server_vars().
 *
 * @group load
 *
 * @covers ::wp_fix_server_vars
 */
class Tests_Load_WpFixServerVars extends WP_UnitTestCase {

	/**
	 * Original $_SERVER superglobal.
	 *
	 * @var array
	 */
	private $original_server;

	/**
	 * Original $PHP_SELF global.
	 *
	 * @var string|null
	 */
	private $original_php_self;

	public function set_up() {
		parent::set_up();

		$this->original_server   = $_SERVER;
		$this->original_php_self = $GLOBALS['PHP_SELF'] ?? null;
	}

	public function tear_down() {
		$_SERVER = $this->original_server;

		if ( null === $this->original_php_self ) {
			unset( $GLOBALS['PHP_SELF'] );
		} else {
			$GLOBALS['PHP_SELF'] = $this->original_php_self;
		}

		parent::tear_down();
	}

	/**
	 * Tests that the default values are added for missing keys.
	 *
	 * @ticket 44766
	 */
	public function test_adds_default_server_values() {
		$_SERVER = array( 'PHP_SELF' => '/index.php' );

		wp_fix_server_vars();

		$this->assertSame( '', $_SERVER['SERVER_SOFTWARE'], 'SERVER_SOFTWARE should default to an empty string.' );
		$this->assertSame( '', $_SERVER['REQUEST_URI'], 'REQUEST_URI should default to an empty string.' );
	}

	/**
	 * Tests how REQUEST_URI is rebuilt for IIS and for requests without a REQUEST_URI.
	 *
	 * The `cgi-fcgi` SAPI exclusion cannot be tested, because PHP_SAPI is a constant.
	 *
	 * @ticket 44766
	 *
	 * @dataProvider data_request_uri
	 *
	 * @param array  $server               Values for $_SERVER.
	 * @param string $expected_request_uri Expected value of $_SERVER['REQUEST_URI'].
	 */
	public function test_request_uri( array $server, $expected_request_uri ) {
		$_SERVER = $server;

		wp_fix_server_vars();

		$this->assertSame( $expected_request_uri, $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Data provider for test_request_uri().
	 *
	 * @return array[]
	 */
	public static function data_request_uri() {
		return array(
			'IIS 6.0 with a mod-rewrite URL'           => array(
				array(
					'SERVER_SOFTWARE'     => 'Microsoft-IIS/6.0',
					'REQUEST_URI'         => '/index.php',
					'HTTP_X_ORIGINAL_URL' => '/original/url/',
					'PHP_SELF'            => '/index.php',
				),
				'/original/url/',
			),
			'IIS with an Isapi_Rewrite URL'            => array(
				array(
					'SERVER_SOFTWARE'    => 'Microsoft-IIS/10.0',
					'REQUEST_URI'        => '/index.php',
					'HTTP_X_REWRITE_URL' => '/rewritten/url/',
					'PHP_SELF'           => '/index.php',
				),
				'/rewritten/url/',
			),
			'IIS prefers the original URL over the rewrite URL' => array(
				array(
					'SERVER_SOFTWARE'     => 'Microsoft-IIS/7.5',
					'REQUEST_URI'         => '/index.php',
					'HTTP_X_ORIGINAL_URL' => '/original/',
					'HTTP_X_REWRITE_URL'  => '/rewritten/',
					'PHP_SELF'            => '/index.php',
				),
				'/original/',
			),
			'IIS 6.0 with PATH_INFO'                   => array(
				array(
					'SERVER_SOFTWARE' => 'Microsoft-IIS/6.0',
					'REQUEST_URI'     => '/ignored',
					'SCRIPT_NAME'     => '/index.php',
					'PATH_INFO'       => '/2026/11/hello/',
					'PHP_SELF'        => '/index.php',
				),
				'/index.php/2026/11/hello/',
			),
			'IIS with PATH_INFO equal to SCRIPT_NAME'  => array(
				array(
					'SERVER_SOFTWARE' => 'Microsoft-IIS/6.0',
					'REQUEST_URI'     => '/ignored',
					'SCRIPT_NAME'     => '/index.php',
					'PATH_INFO'       => '/index.php',
					'PHP_SELF'        => '/index.php',
				),
				'/index.php',
			),
			'IIS with ORIG_PATH_INFO and no PATH_INFO' => array(
				array(
					'SERVER_SOFTWARE' => 'Microsoft-IIS/6.0',
					'REQUEST_URI'     => '/ignored',
					'SCRIPT_NAME'     => '/index.php',
					'ORIG_PATH_INFO'  => '/foo/bar',
					'PHP_SELF'        => '/index.php',
				),
				'/index.php/foo/bar',
			),
			'IIS with PATH_INFO and a query string'    => array(
				array(
					'SERVER_SOFTWARE' => 'Microsoft-IIS/6.0',
					'REQUEST_URI'     => '/ignored',
					'SCRIPT_NAME'     => '/index.php',
					'PATH_INFO'       => '/foo/',
					'QUERY_STRING'    => 'a=1&b=2',
					'PHP_SELF'        => '/index.php',
				),
				'/index.php/foo/?a=1&b=2',
			),
			'IIS with a REQUEST_URI that already has the query string' => array(
				array(
					'SERVER_SOFTWARE' => 'Microsoft-IIS/10.0',
					'REQUEST_URI'     => '/foo/?a=1&b=2',
					'SCRIPT_NAME'     => '/index.php',
					'QUERY_STRING'    => 'a=1&b=2',
					'PHP_SELF'        => '/index.php',
				),
				'/foo/?a=1&b=2',
			),
			'IIS with an empty query string'           => array(
				array(
					'SERVER_SOFTWARE' => 'Microsoft-IIS/6.0',
					'REQUEST_URI'     => '/ignored',
					'SCRIPT_NAME'     => '/index.php',
					'PATH_INFO'       => '/foo/',
					'QUERY_STRING'    => '',
					'PHP_SELF'        => '/index.php',
				),
				'/index.php/foo/',
			),
			'empty REQUEST_URI on a non-IIS server uses the original URL' => array(
				array(
					'SERVER_SOFTWARE'     => 'Apache/2.4.58',
					'REQUEST_URI'         => '',
					'HTTP_X_ORIGINAL_URL' => '/original/url/',
					'PHP_SELF'            => '/index.php',
				),
				'/original/url/',
			),
			'missing REQUEST_URI uses PATH_INFO and SCRIPT_NAME' => array(
				array(
					'SERVER_SOFTWARE' => 'nginx/1.27',
					'SCRIPT_NAME'     => '/index.php',
					'PATH_INFO'       => '/foo/',
					'PHP_SELF'        => '/index.php',
				),
				'/index.php/foo/',
			),
			'non-IIS server keeps its REQUEST_URI'     => array(
				array(
					'SERVER_SOFTWARE'     => 'Apache/2.4.58',
					'REQUEST_URI'         => '/keep/me/?x=1',
					'HTTP_X_ORIGINAL_URL' => '/original/url/',
					'HTTP_X_REWRITE_URL'  => '/rewritten/url/',
					'SCRIPT_NAME'         => '/index.php',
					'PATH_INFO'           => '/foo/',
					'QUERY_STRING'        => 'x=1',
					'PHP_SELF'            => '/index.php',
				),
				'/keep/me/?x=1',
			),
			'server software only matches IIS at the start' => array(
				array(
					'SERVER_SOFTWARE'     => 'Proxy for Microsoft-IIS/6.0',
					'REQUEST_URI'         => '/keep/me/',
					'HTTP_X_ORIGINAL_URL' => '/original/url/',
					'PHP_SELF'            => '/index.php',
				),
				'/keep/me/',
			),
		);
	}

	/**
	 * Tests that ORIG_PATH_INFO is copied to PATH_INFO only when PATH_INFO is not set.
	 *
	 * @ticket 44766
	 */
	public function test_orig_path_info_does_not_override_path_info() {
		$_SERVER = array(
			'SERVER_SOFTWARE' => 'Microsoft-IIS/6.0',
			'REQUEST_URI'     => '/ignored',
			'SCRIPT_NAME'     => '/index.php',
			'PATH_INFO'       => '/real/',
			'ORIG_PATH_INFO'  => '/orig/',
			'PHP_SELF'        => '/index.php',
		);

		wp_fix_server_vars();

		$this->assertSame( '/real/', $_SERVER['PATH_INFO'], 'PATH_INFO should not be overwritten.' );
		$this->assertSame( '/index.php/real/', $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Tests that ORIG_PATH_INFO is used to set PATH_INFO when it is missing.
	 *
	 * @ticket 44766
	 */
	public function test_orig_path_info_populates_missing_path_info() {
		$_SERVER = array(
			'SERVER_SOFTWARE' => 'Microsoft-IIS/6.0',
			'REQUEST_URI'     => '/ignored',
			'SCRIPT_NAME'     => '/index.php',
			'ORIG_PATH_INFO'  => '/orig/',
			'PHP_SELF'        => '/index.php',
		);

		wp_fix_server_vars();

		$this->assertSame( '/orig/', $_SERVER['PATH_INFO'] );
	}

	/**
	 * Tests the fix for PHP as CGI hosts reporting `php.cgi` as the SCRIPT_FILENAME.
	 *
	 * @ticket 44766
	 */
	public function test_script_filename_ending_in_php_cgi_uses_path_translated() {
		$_SERVER = array(
			'SERVER_SOFTWARE' => 'Apache/2.4.58',
			'REQUEST_URI'     => '/index.php',
			'SCRIPT_FILENAME' => '/home/user/cgi-bin/php.cgi',
			'PATH_TRANSLATED' => '/home/user/public_html/index.php',
			'PHP_SELF'        => '/index.php',
		);

		wp_fix_server_vars();

		$this->assertSame( '/home/user/public_html/index.php', $_SERVER['SCRIPT_FILENAME'] );
	}

	/**
	 * Tests that a regular SCRIPT_FILENAME is left untouched.
	 *
	 * @ticket 44766
	 */
	public function test_regular_script_filename_is_not_changed() {
		$_SERVER = array(
			'SERVER_SOFTWARE' => 'Apache/2.4.58',
			'REQUEST_URI'     => '/index.php',
			'SCRIPT_FILENAME' => '/var/www/index.php',
			'PATH_TRANSLATED' => '/var/www/other.php',
			'PHP_SELF'        => '/index.php',
		);

		wp_fix_server_vars();

		$this->assertSame( '/var/www/index.php', $_SERVER['SCRIPT_FILENAME'] );
	}

	/**
	 * Tests the Dreamhost fix, which removes PATH_INFO when SCRIPT_NAME contains `php.cgi`.
	 *
	 * @ticket 44766
	 */
	public function test_script_name_containing_php_cgi_unsets_path_info() {
		$_SERVER = array(
			'SERVER_SOFTWARE' => 'Apache/2.4.58',
			'REQUEST_URI'     => '/index.php',
			'SCRIPT_NAME'     => '/cgi-bin/php.cgi',
			'PATH_INFO'       => '/foo/',
			'PHP_SELF'        => '/index.php',
		);

		wp_fix_server_vars();

		$this->assertArrayNotHasKey( 'PATH_INFO', $_SERVER );
	}

	/**
	 * Tests that PATH_INFO is kept when SCRIPT_NAME does not contain `php.cgi`.
	 *
	 * @ticket 44766
	 */
	public function test_script_name_without_php_cgi_keeps_path_info() {
		$_SERVER = array(
			'SERVER_SOFTWARE' => 'Apache/2.4.58',
			'REQUEST_URI'     => '/index.php',
			'SCRIPT_NAME'     => '/index.php',
			'PATH_INFO'       => '/foo/',
			'PHP_SELF'        => '/index.php',
		);

		wp_fix_server_vars();

		$this->assertSame( '/foo/', $_SERVER['PATH_INFO'] );
	}

	/**
	 * Tests that an empty PHP_SELF is derived from REQUEST_URI without the query string.
	 *
	 * @ticket 44766
	 *
	 * @dataProvider data_empty_php_self
	 *
	 * @param string $request_uri  Value for $_SERVER['REQUEST_URI'].
	 * @param string $expected     Expected PHP_SELF.
	 */
	public function test_empty_php_self_is_derived_from_request_uri( $request_uri, $expected ) {
		$_SERVER = array(
			'SERVER_SOFTWARE' => 'Apache/2.4.58',
			'REQUEST_URI'     => $request_uri,
			'PHP_SELF'        => '',
		);

		wp_fix_server_vars();

		$this->assertSame( $expected, $_SERVER['PHP_SELF'], '$_SERVER[\'PHP_SELF\'] is incorrect.' );
		$this->assertSame( $expected, $GLOBALS['PHP_SELF'], 'The $PHP_SELF global is incorrect.' );
	}

	/**
	 * Data provider for test_empty_php_self_is_derived_from_request_uri().
	 *
	 * @return array[]
	 */
	public static function data_empty_php_self() {
		return array(
			'path only'         => array( '/wp-admin/index.php', '/wp-admin/index.php' ),
			'path with a query' => array( '/wp-admin/index.php?page=foo&x=1', '/wp-admin/index.php' ),
			'root with a query' => array( '/?p=1', '/' ),
		);
	}

	/**
	 * Tests that an existing PHP_SELF is preserved and assigned to the global.
	 *
	 * @ticket 44766
	 */
	public function test_existing_php_self_is_preserved() {
		$_SERVER = array(
			'SERVER_SOFTWARE' => 'Apache/2.4.58',
			'REQUEST_URI'     => '/other/path.php?x=1',
			'PHP_SELF'        => '/index.php',
		);

		wp_fix_server_vars();

		$this->assertSame( '/index.php', $_SERVER['PHP_SELF'] );
		$this->assertSame( '/index.php', $GLOBALS['PHP_SELF'] );
	}

	/**
	 * Tests that basic authentication credentials are populated from the Authorization header.
	 *
	 * @ticket 44766
	 */
	public function test_populates_basic_auth_from_authorization_header() {
		$_SERVER = array(
			'SERVER_SOFTWARE'    => 'Apache/2.4.58',
			'REQUEST_URI'        => '/index.php',
			'PHP_SELF'           => '/index.php',
			'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode( 'user:pa:ss' ),
		);

		wp_fix_server_vars();

		$this->assertSame( 'user', $_SERVER['PHP_AUTH_USER'] );
		$this->assertSame( 'pa:ss', $_SERVER['PHP_AUTH_PW'] );
	}
}
