<?php

/**
 * Tests for the `wp_get_chrome_major_version()` function.
 *
 * @group media
 * @covers ::wp_get_chrome_major_version
 */
class Tests_Media_wpGetChromeMajorVersion extends WP_UnitTestCase {

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

	public function test_returns_null_when_no_user_agent() {
		unset( $_SERVER['HTTP_USER_AGENT'] );
		$this->assertNull( wp_get_chrome_major_version() );
	}

	public function test_returns_null_for_empty_user_agent() {
		$_SERVER['HTTP_USER_AGENT'] = '';
		$this->assertNull( wp_get_chrome_major_version() );
	}

	public function test_returns_null_for_firefox() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; rv:128.0) Gecko/20100101 Firefox/128.0';
		$this->assertNull( wp_get_chrome_major_version() );
	}

	public function test_returns_null_for_safari() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';
		$this->assertNull( wp_get_chrome_major_version() );
	}

	public function test_returns_version_for_chrome() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';
		$this->assertSame( 137, wp_get_chrome_major_version() );
	}

	public function test_returns_version_for_edge() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0';
		$this->assertSame( 137, wp_get_chrome_major_version() );
	}

	public function test_returns_version_for_opera() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 OPR/122.0.0.0';
		$this->assertSame( 136, wp_get_chrome_major_version() );
	}

	public function test_returns_version_for_older_chrome() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.75 Safari/537.36';
		$this->assertSame( 100, wp_get_chrome_major_version() );
	}
}
