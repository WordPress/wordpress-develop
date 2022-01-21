<?php

/**
 * @group functions.php
 *
 * @covers ::wp_nonce_url
 */
class Tests_Functions_wp_nonce_url extends WP_UnitTestCase {


	/**
	 * @dataProvider data_wp_nonce_url
	 *
	 * @param $expected
	 * @param $action
	 * @param $url
	 * @param $name
	 *
	 * @return void
	 */
	public function test_wp_nonce_url( $expected, $actionurl, $action = -1, $name = '_wpnonce' ) {

		$this->assertMatchesRegularExpression( $expected, wp_nonce_url( $actionurl, $action, $name ) );
	}

	public function data_wp_nonce_url() {
		return array(
			array(
				'expected'  => '/^http:\/\/example\.org\/\?_wpnonce=.{10}$/',
				'actionurl' => 'http://example.org/',
				'action'    => '12345',
				'name'      => '_wpnonce',
			),
			array(
				'expected'  => '/^https:\/\/example\.org\/\?my_nonce=.{10}$/',
				'actionurl' => 'https://example.org/',
				'action'    => '12345',
				'name'      => 'my_nonce',
			),
			array(
				'expected'  => '/\/\?_wpnonce=.{10}$/',
				'actionurl' => '/',
				'action'    => '12345',
				'name'      => '_wpnonce',
			),
			array(
				'expected'  => '/\?_wpnonce=.{10}$/',
				'actionurl' => '/',
			),
		);
	}
}
