<?php
/**
 * Test functions in wp-includes/vars.php
 *
 * @group vars
 */
class Tests_Vars extends WP_UnitTestCase {

	/**
	 * Backup of $_SERVER.
	 *
	 * @var array
	 */
	protected $server_vars = array();

	/**
	 * Set up.
	 */
	public function set_up() {
		$this->server_vars = $_SERVER;
		parent::set_up();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		$_SERVER = $this->server_vars;
		parent::tear_down();
	}

	/**
	 * Data provider to test wp_is_mobile().
	 *
	 * @return array
	 */
	public static function get_data_to_test_wp_is_mobile(): array {
		return array(
			'mobile client hint'                 => array(
				'headers'  => array(
					'HTTP_SEC_CH_UA_MOBILE' => '?1',
				),
				'expected' => true,
			),
			'desktop client hint'                => array(
				'headers'  => array(
					'HTTP_SEC_CH_UA_MOBILE' => '?0',
				),
				'expected' => false,
			),
			'desktop client hint with mobile ua' => array(
				'headers'  => array(
					'HTTP_SEC_CH_UA_MOBILE' => '?0',
					'HTTP_USER_AGENT'       => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) Mobile/15E148',
				),
				'expected' => false,
			),
			'no user agent'                      => array(
				'headers'  => array(),
				'expected' => false,
			),
			'desktop safari'                     => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_5_2) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Safari/605.1.15',
				),
				'expected' => false,
			),
			'mobile safari'                      => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1',
				),
				'expected' => true,
			),
			'mobile android'                     => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.5938.60 Safari/537.36',
				),
				'expected' => true,
			),
			// Isolated tokens so each detector is covered without earlier Mobile/Android matches.
			'silk'                               => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.0 Safari/537.36',
				),
				'expected' => true,
			),
			'kindle'                             => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Kindle/3.0+',
				),
				'expected' => true,
			),
			'blackberry'                         => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'BlackBerry9000/4.6.0.167 Profile/MIDP-2.0 Configuration/CLDC-1.1 VendorID/102',
				),
				'expected' => true,
			),
			'opera mini'                         => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Opera/9.80 (Series 60; Opera Mini/7.1.32444/35.2346; U; en) Presto/2.5.25 Version/10.54',
				),
				'expected' => true,
			),
			'opera mobi'                         => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Opera/9.80 (S60; SymbOS; Opera Mobi/275; U; en) Presto/2.4.15 Version/10.00',
				),
				'expected' => true,
			),
		);
	}

	/**
	 * @ticket 59370
	 * @ticket 65819
	 *
	 * @covers ::wp_is_mobile
	 *
	 * @dataProvider get_data_to_test_wp_is_mobile
	 *
	 * @param array $headers  Headers in $_SERVER.
	 * @param bool  $expected Whether expected.
	 */
	public function test_wp_is_mobile( array $headers, bool $expected ) {
		foreach ( $headers as $key => $value ) {
			$_SERVER[ $key ] = $value;
		}
		$this->assertSame( $expected, wp_is_mobile() );
	}

	/**
	 * Tests that filter can override output of wp_is_mobile() to be true.
	 *
	 * @ticket 59370
	 *
	 * @covers ::wp_is_mobile
	 */
	public function test_wp_is_mobile_is_true_with_filter() {
		$this->assertFalse( wp_is_mobile() );
		add_filter( 'wp_is_mobile', '__return_true' );
		$this->assertTrue( wp_is_mobile() );
	}

	/**
	 * Tests that filter can override output of wp_is_mobile() to be false.
	 *
	 * @ticket 59370
	 *
	 * @covers ::wp_is_mobile
	 */
	public function test_wp_is_mobile_is_false_with_filter() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.5938.60 Mobile Safari/537.36';
		$this->assertTrue( wp_is_mobile() );
		add_filter( 'wp_is_mobile', '__return_false' );
		$this->assertFalse( wp_is_mobile() );
	}
}
