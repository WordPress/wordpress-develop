<?php

/**
 * Tests for the `wp_get_chromium_major_version()` function.
 *
 * @group media
 * @covers ::wp_get_chromium_major_version
 */
class Tests_Media_wpGetChromiumMajorVersion extends WP_UnitTestCase {

	/**
	 * Original HTTP_USER_AGENT value.
	 *
	 * @var string|null
	 */
	private $original_user_agent;

	public function set_up() {
		parent::set_up();
		$this->original_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
	}

	public function tear_down() {
		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}
		parent::tear_down();
	}

	/**
	 * @ticket 64766
	 */
	public function test_returns_null_when_no_user_agent() {
		unset( $_SERVER['HTTP_USER_AGENT'] );
		$this->assertNull( wp_get_chromium_major_version() );
	}

	/**
	 * @ticket 64766
	 *
	 * @dataProvider data_user_agents
	 *
	 * @param string   $user_agent The user agent string.
	 * @param int|null $expected   The expected Chromium major version, or null.
	 */
	public function test_returns_expected_version( $user_agent, $expected ) {
		$_SERVER['HTTP_USER_AGENT'] = $user_agent;
		$this->assertSame( $expected, wp_get_chromium_major_version() );
	}

	/**
	 * Data provider for test_returns_expected_version.
	 *
	 * @return array[]
	 */
	public function data_user_agents() {
		return array(
			'empty user agent'   => array( '', null ),
			'Firefox'            => array( 'Mozilla/5.0 (Windows NT 10.0; rv:128.0) Gecko/20100101 Firefox/128.0', null ),
			'Safari'             => array( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', null ),
			'Chrome 137'         => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 137 ),
			'Edge 137'           => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', 137 ),
			'Opera (Chrome 136)' => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 OPR/122.0.0.0', 136 ),
			'Chrome 100'         => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.75 Safari/537.36', 100 ),
		);
	}
}
