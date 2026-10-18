<?php

/**
 * Tests for wp_is_mobile().
 *
 * @group load
 *
 * @covers ::wp_is_mobile
 */
class Tests_Load_WpIsMobile extends WP_UnitTestCase {

	/**
	 * Original HTTP_USER_AGENT value.
	 *
	 * @var string|null
	 */
	private $original_user_agent;

	/**
	 * Original HTTP_SEC_CH_UA_MOBILE value.
	 *
	 * @var string|null
	 */
	private $original_sec_ch_ua_mobile;

	public function set_up() {
		parent::set_up();

		$this->original_user_agent       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
		$this->original_sec_ch_ua_mobile = isset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) ? $_SERVER['HTTP_SEC_CH_UA_MOBILE'] : null;
	}

	public function tear_down() {
		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		if ( null === $this->original_sec_ch_ua_mobile ) {
			unset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] );
		} else {
			$_SERVER['HTTP_SEC_CH_UA_MOBILE'] = $this->original_sec_ch_ua_mobile;
		}

		parent::tear_down();
	}

	/**
	 * Tests detection via the Sec-CH-UA-Mobile client hint.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_for_sec_ch_ua_mobile_hint() {
		unset( $_SERVER['HTTP_USER_AGENT'] );
		$_SERVER['HTTP_SEC_CH_UA_MOBILE'] = '?1';

		$this->assertTrue( wp_is_mobile() );
	}

	/**
	 * Tests that a non-mobile Sec-CH-UA-Mobile hint is respected.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_mobile_sec_ch_ua_mobile_hint() {
		$_SERVER['HTTP_SEC_CH_UA_MOBILE'] = '?0';
		$_SERVER['HTTP_USER_AGENT']       = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) Mobile/15E148';

		$this->assertFalse( wp_is_mobile() );
	}

	/**
	 * Tests that an empty user agent is not treated as mobile.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_when_user_agent_is_empty() {
		unset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] );
		unset( $_SERVER['HTTP_USER_AGENT'] );

		$this->assertFalse( wp_is_mobile() );
	}

	/**
	 * Tests mobile user agent detection.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_mobile_user_agents
	 *
	 * @param string $user_agent Mobile user agent string.
	 */
	public function test_returns_true_for_mobile_user_agents( $user_agent ) {
		unset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] );
		$_SERVER['HTTP_USER_AGENT'] = $user_agent;

		$this->assertTrue( wp_is_mobile() );
	}

	/**
	 * Data provider for mobile user agents.
	 *
	 * @return array[]
	 */
	public static function data_mobile_user_agents() {
		return array(
			'iphone'     => array( 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148' ),
			'android'    => array( 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.120 Mobile Safari/537.36' ),
			'silk'       => array( 'Mozilla/5.0 (Linux; U; Android 4.0.3; en-us; KFTT Build/IML74K) AppleWebKit/535.19 (KHTML, like Gecko) Silk/2.9' ),
			'kindle'     => array( 'Mozilla/5.0 (X11; U; Linux armv7l like Android; en-us) AppleWebKit/531.2+ (KHTML, like Gecko) Version/5.0 Safari/533.2+ Kindle/3.0+' ),
			'blackberry' => array( 'Mozilla/5.0 (BlackBerry; U; BlackBerry 9900; en) AppleWebKit/534.11+ (KHTML, like Gecko) Version/7.1.0.346 Mobile Safari/534.11+' ),
			'opera mini' => array( 'Opera/9.80 (Android; Opera Mini/36.2.2254/119.132; U; en) Presto/2.12.423 Version/12.16' ),
			'opera mobi' => array( 'Opera/9.80 (Android 2.3.3; Linux; Opera Mobi/ADR-1111101157; U; es-ES) Presto/2.9.201 Version/11.50' ),
		);
	}

	/**
	 * Tests that a desktop user agent is not treated as mobile.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_desktop_user_agent() {
		unset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] );
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

		$this->assertFalse( wp_is_mobile() );
	}

	/**
	 * Tests that the wp_is_mobile filter can override the result.
	 *
	 * @ticket 65819
	 */
	public function test_wp_is_mobile_filter() {
		unset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] );
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

		add_filter( 'wp_is_mobile', '__return_true' );

		$this->assertTrue( wp_is_mobile() );
	}
}
