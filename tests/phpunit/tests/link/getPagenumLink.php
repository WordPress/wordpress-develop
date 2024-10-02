<?php

/**
 * @group link
 * @covers ::get_pagenum_link
 */
class Tests_Link_GetPagenumLink extends WP_UnitTestCase {

	/**
	 * The original value of `$_SERVER['REQUEST_URI']`.
	 *
	 * @var string|null
	 */
	protected static $request_uri_original;

	/**
	 * Backs up the value of `$_SERVER['REQUEST_URI']` before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			self::$request_uri_original = $_SERVER['REQUEST_URI'];
		}
	}

	/**
	 * Restores the value of `$_SERVER['REQUEST_URI']` after each test runs.
	 */
	public function tear_down() {
		if ( null === self::$request_uri_original ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = self::$request_uri_original;
		}

		parent::tear_down();
	}

	/**
	 * @ticket 8847
	 */
	public function test_get_pagenum_link_case_insensitivity() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		add_filter( 'home_url', array( $this, 'get_pagenum_link_cb' ) );
		$_SERVER['REQUEST_URI'] = '/woohoo';
		$paged                  = get_pagenum_link( 2 );

		remove_filter( 'home_url', array( $this, 'get_pagenum_link_cb' ) );
		$this->assertSame( $paged, home_url( '/WooHoo/page/2/' ) );
	}

	/**
	 * Appends '/WooHoo' to the provided URL.
	 *
	 * Callback for the 'home_url' filter hook.
	 *
	 * @param string $url The base URL.
	 * @return string The base URL with '/WooHoo' appended.
	 */
	public function get_pagenum_link_cb( $url ) {
		return $url . '/WooHoo';
	}

	/**
	 * Tests that a trailing slash is not added to the link.
	 *
	 * @ticket 2877
	 *
	 * @dataProvider data_get_pagenum_link_plain_permalinks
	 * @dataProvider data_get_pagenum_link
	 *
	 * @param string $permalink_structure The structure to use for permalinks.
	 * @param string $request_uri         The value for `$_SERVER['REQUEST_URI']`.
	 * @param int    $pagenum             The page number to get the link for.
	 * @param string $expected            The expected relative URL.
	 */
	public function test_get_pagenum_link_should_not_add_trailing_slash( $permalink_structure, $request_uri, $pagenum, $expected ) {
		$this->set_permalink_structure( $permalink_structure );
		$_SERVER['REQUEST_URI'] = $request_uri;
		$paged                  = get_pagenum_link( $pagenum );

		$this->assertSame( home_url( $expected ), $paged );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_pagenum_link_plain_permalinks() {
		return array(
			'page 1 and plain permalinks' => array(
				'permalink_structure' => '',
				'request_uri'         => '/?paged=2',
				'pagenum'             => 1,
				'expected'            => '/',
			),
			'page 2 and plain permalinks' => array(
				'permalink_structure' => '',
				'request_uri'         => '/',
				'pagenum'             => 2,
				'expected'            => '/?paged=2',
			),
		);
	}

	/**
	 * Tests that a trailing slash is added to the link when a trailing slash
	 * exists in the permalink structure.
	 *
	 * @ticket 2877
	 *
	 * @dataProvider data_get_pagenum_link
	 *
	 * @param string $permalink_structure The structure to use for permalinks.
	 * @param string $request_uri         The value for `$_SERVER['REQUEST_URI']`.
	 * @param int    $pagenum             The page number to get the link for.
	 * @param string $expected            The expected relative URL.
	 */
	public function test_get_pagenum_link_should_add_trailing_slash( $permalink_structure, $request_uri, $pagenum, $expected ) {
		// Ensure the permalink structure has a trailing slash.
		$permalink_structure = trailingslashit( $permalink_structure );

		// Ensure the expected value has a trailing slash at the appropriate position.
		if ( str_contains( $expected, '?' ) ) {
			// Contains query args.
			$parts    = explode( '?', $expected, 2 );
			$expected = trailingslashit( $parts[0] ) . '?' . $parts[1];
		} else {
			$expected = trailingslashit( $expected );
		}

		$this->set_permalink_structure( $permalink_structure );
		$_SERVER['REQUEST_URI'] = $request_uri;
		$paged                  = get_pagenum_link( $pagenum );

		$this->assertSame( home_url( $expected ), $paged );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_pagenum_link() {
		return array(
			'page 1 and index.php'                  => array(
				'permalink_structure' => '/index.php/%year%/%monthnum%/%day%/%postname%',
				'request_uri'         => '/index.php/woohoo/page/2/',
				'pagenum'             => 1,
				'expected'            => '/index.php/woohoo',
			),
			'page 2 and index.php'                  => array(
				'permalink_structure' => '/index.php/%year%/%monthnum%/%day%/%postname%',
				'request_uri'         => '/index.php/woohoo/page/2/',
				'pagenum'             => 2,
				'expected'            => '/index.php/woohoo/page/2',
			),
			'page 1 with date-based permalinks'     => array(
				'permalink_structure' => '/%year%/%monthnum%/%day%/%postname%',
				'request_uri'         => '/woohoo/page/2/',
				'pagenum'             => 1,
				'expected'            => '/woohoo',
			),
			'page 2 with date-based permalinks'     => array(
				'permalink_structure' => '/%year%/%monthnum%/%day%/%postname%',
				'request_uri'         => '/woohoo',
				'pagenum'             => 2,
				'expected'            => '/woohoo/page/2',
			),
			'page 1 with postname-based permalinks' => array(
				'permalink_structure' => '/%postname%',
				'request_uri'         => '/woohoo/page/2',
				'pagenum'             => 1,
				'expected'            => '/woohoo',
			),
			'page 2 with postname-based permalinks' => array(
				'permalink_structure' => '/%postname%',
				'request_uri'         => '/woohoo',
				'pagenum'             => 2,
				'expected'            => '/woohoo/page/2',
			),
			'page 1 with postname-based permalinks and query args' => array(
				'permalink_structure' => '/%postname%',
				'request_uri'         => '/woohoo/page/2?test=1234',
				'pagenum'             => 1,
				'expected'            => '/woohoo?test=1234',
			),
			'page 2 with postname-based permalinks and query args' => array(
				'permalink_structure' => '/%postname%',
				'request_uri'         => '/woohoo?test=1234',
				'pagenum'             => 2,
				'expected'            => '/woohoo/page/2?test=1234',
			),
		);
	}
}
