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
	public function get_data_to_test_wp_is_mobile(): array {
		return array(
			'mobile client hint'  => array(
				'headers'  => array(
					'HTTP_SEC_CH_UA_MOBILE' => '?1',
				),
				'expected' => true,
			),
			'desktop client hint' => array(
				'headers'  => array(
					'HTTP_SEC_CH_UA_MOBILE' => '?0',
				),
				'expected' => false,
			),
			'no user agent'       => array(
				'headers'  => array(),
				'expected' => false,
			),
			'desktop safari'      => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_5_2) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Safari/605.1.15',
				),
				'expected' => false,
			),
			'mobile safari'       => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1',
				),
				'expected' => true,
			),
			'mobile android'      => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.5938.60 Mobile Safari/537.36',
				),
				'expected' => true,
			),
			'silk'                => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; U; Android 4.4.3; KFTHWI Build/KTU84M) AppleWebKit/537.36 (KHTML, like Gecko) Silk/44.1.54 like Chrome/44.0.2403.63 Mobile Safari/537.36',
				),
				'expected' => true,
			),
			'kindle'              => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Mozilla/4.0 (compatible; Linux 2.6.10) NetFront/3.3 Kindle/1.0 (screen 600x800)',
				),
				'expected' => true,
			),
			'blackberry'          => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 8.1.0; BlackBerry BBB100-4 Build/OPM1.171019.026) IPTV Pro/7.0.6',
				),
				'expected' => true,
			),
			'opera mini'          => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Opera/9.80 (Android; Opera Mini/69.0.2254/191.303; U; en) Presto/2.12.423 Version/12.16',
				),
				'expected' => true,
			),
			'opera mobi'          => array(
				'headers'  => array(
					'HTTP_USER_AGENT' => 'Opera/9.80 (Linux i686; Opera Mobi/1038; U; en) Presto/2.5.24 Version/10.00',
				),
				'expected' => true,
			),
		);
	}

	/**
	 * @ticket 59370
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
	 * @covers ::wp_is_mobile
	 */
	public function test_wp_is_mobile_is_false_with_filter() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.5938.60 Mobile Safari/537.36';
		$this->assertTrue( wp_is_mobile() );
		add_filter( 'wp_is_mobile', '__return_false' );
		$this->assertFalse( wp_is_mobile() );
	}
}
