<?php

/**
 * Tests for the wp_check_browser_version function.
 *
 * @group dashboard
 *
 * @covers ::wp_check_browser_version
 */
class Tests_dashboard_wp_check_browser_version extends WP_UnitTestCase {

	public function set_up() {
		/** Load WordPress dashboard API */
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
	}

	/**
	 * @ticket 60828
	 */
	public function test_wp_check_browser_version_no_agent() {
		$this->assertFalse( wp_check_browser_version() );
	}

	/**
	 * @ticket 60828
	 */
	public function test_wp_check_browser_version() {
		$_SERVER['HTTP_USER_AGENT'] = 'sssssssssssss';

		$expected = array(
			'name'            => 'unknown',
			'version'         => '',
			'platform'        => '',
			'update_url'      => '',
			'img_src'         => '',
			'img_src_ssl'     => '',
			'current_version' => '',
			'upgrade'         => false,
			'insecure'        => false,
			'mobile'          => false,
		);
		$this->assertSame( $expected, wp_check_browser_version() );
	}

	/**
	 * @ticket 60828
	 */
	public function test_wp_check_browser_version_Google_Chrome_on_Windows() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36';

		$expected = array(
			'name'            => 'Chrome',
			'version'         => '119.0.0.0',
			'platform'        => 'Windows',
			'update_url'      => 'https://www.google.com/chrome',
			'img_src'         => 'http://s.w.org/images/browsers/chrome.png?1',
			'img_src_ssl'     => 'https://s.w.org/images/browsers/chrome.png?1',
			'current_version' => '18',
			'upgrade'         => false,
			'insecure'        => false,
			'mobile'          => false,
		);

		$this->assertSame( $expected, wp_check_browser_version() );
	}

	/**
	 * @ticket 60828
	 */
	public function test_wp_check_browser_version_Google_Chrome_on_Android() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36';

		$expected = array(
			'name'            => 'Chrome',
			'version'         => '114.0.0.0',
			'platform'        => 'Android',
			'update_url'      => '',
			'img_src'         => '',
			'img_src_ssl'     => '',
			'current_version' => '',
			'upgrade'  => false,
			'insecure' => false,
			'mobile'   => true,
		);

		$this->assertSame( $expected, wp_check_browser_version() );
	}
}
