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
			'android'    => array( 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.120 Safari/537.36' ),
			// Isolated tokens so each detector is covered without earlier Mobile/Android matches.
			'silk'       => array( 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.0 Safari/537.36' ),
			'kindle'     => array( 'Kindle/3.0+' ),
			'blackberry' => array( 'BlackBerry9000/4.6.0.167 Profile/MIDP-2.0 Configuration/CLDC-1.1 VendorID/102' ),
			'opera mini' => array( 'Opera/9.80 (Series 60; Opera Mini/7.1.32444/35.2346; U; en) Presto/2.5.25 Version/10.54' ),
			'opera mobi' => array( 'Opera/9.80 (S60; SymbOS; Opera Mobi/275; U; en) Presto/2.4.15 Version/10.00' ),
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
