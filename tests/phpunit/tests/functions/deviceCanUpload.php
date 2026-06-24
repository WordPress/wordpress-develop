<?php

/**
 * @group functions
 *
 * @covers ::_device_can_upload
 */
class Tests_Functions_DeviceCanUpload extends WP_UnitTestCase {
	/**
	 * @dataProvider data_device_can_upload
	 */
	public function test_device_can_upload( $user_agent, $expected ) {
		$_SERVER['HTTP_USER_AGENT'] = $user_agent;
		$actual                     = _device_can_upload();
		unset( $_SERVER['HTTP_USER_AGENT'] );
		$this->assertSame( $expected, $actual );
	}

	public function data_device_can_upload() {
		return array(
			// iPhone iOS 5.0.1, Safari 5.1.
			array(
				'Mozilla/5.0 (iPhone; CPU iPhone OS 5_0_1 like Mac OS X) AppleWebKit/534.46 (KHTML, like Gecko) Mobile/9A406)',
				false,
			),
			// iPad iOS 3.2, Safari 4.0.4.
			array(
				'Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.10',
				false,
			),
			// iPod iOS 4.3.3, Safari 5.0.2.
			array(
				'Mozilla/5.0 (iPod; U; CPU iPhone OS 4_3_3 like Mac OS X; ja-jp) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5',
				false,
			),
			// iPhone iOS 6.0.0, Safari 6.0.
			array(
				'Mozilla/5.0 (iPhone; CPU iPhone OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5376e Safari/8536.25',
				true,
			),
			// iPad iOS 6.0.0, Safari 6.0.
			array(
				'Mozilla/5.0 (iPad; CPU OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5376e Safari/8536.25',
				true,
			),
			// Android 2.2, Android Webkit Browser.
			array(
				'Mozilla/5.0 (Android 2.2; Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4',
				true,
			),
			// BlackBerry 9900, BlackBerry browser.
			array(
				'Mozilla/5.0 (BlackBerry; U; BlackBerry 9900; en) AppleWebKit/534.11+ (KHTML, like Gecko) Version/7.1.0.346 Mobile Safari/534.11+',
				true,
			),
			// Windows Phone 8.0, Internet Explorer 10.0.
			array(
				'Mozilla/5.0 (compatible; MSIE 10.0; Windows Phone 8.0; Trident/6.0; IEMobile/10.0; ARM; Touch; NOKIA; Lumia 920)',
				true,
			),
			// Ubuntu desktop, Firefox 41.0.
			array(
				'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:41.0) Gecko/20100101 Firefox/41.0',
				true,
			),
		);
	}
}
