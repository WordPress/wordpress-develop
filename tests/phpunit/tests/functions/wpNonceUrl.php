<?php

/**
 * @group functions.php
 *
 * @covers ::wp_nonce_url
 */
class Tests_Functions_WpNonceUrl extends WP_UnitTestCase {
	/**
	 * Tests that wp_nonce_url() appends the nonce name and value to the URL.
	 *
	 * @ticket 54870
	 *
	 * @dataProvider data_should_append_nonce_name_and_value
	 *
	 * @param string     $actionurl URL to add nonce action.
	 * @param int|string $action    Optional. Nonce action name. Default -1.
	 * @param string     $name      Optional. Nonce name. Default '_wpnonce'.
	 */
	public function test_should_append_nonce_name_and_value( $actionurl, $action = -1, $name = '_wpnonce' ) {
		$actual        = wp_nonce_url( $actionurl, $action, $name );
		$url_with_name = "$actionurl?$name=";
		$nonce         = str_replace( $url_with_name, '', $actual );

		$this->assertStringContainsString(
			$url_with_name,
			$actual,
			'The URL did not contain the action URL and the nonce name'
		);

		$this->assertNotFalse(
			wp_verify_nonce( $nonce, $action ),
			'The nonce is invalid'
		);
	}

	/**
	 * Data provider for test_should_append_nonce_name_and_value().
	 *
	 * @return array
	 */
	public function data_should_append_nonce_name_and_value() {
		return array(
			'http:// and default action/name'             => array(
				'actionurl' => 'http://example.org/',
			),
			'http:// and a custom nonce action'           => array(
				'actionurl' => 'http://example.org/',
				'action'    => 'my_action',
			),
			'http:// and a custom nonce name'             => array(
				'actionurl' => 'http://example.org/',
				'action'    => -1,
				'name'      => 'my_nonce',
			),
			'http:// and a custom nonce action and name'  => array(
				'actionurl' => 'http://example.org/',
				'action'    => 'my_action',
				'name'      => 'my_nonce',
			),
			'https:// and default action/name'            => array(
				'actionurl' => 'https://example.org/',
			),
			'https:// and a custom nonce action'          => array(
				'actionurl' => 'https://example.org/',
				'action'    => 'my_action',
			),
			'https:// and a custom nonce name'            => array(
				'actionurl' => 'https://example.org/',
				'action'    => -1,
				'name'      => 'my_nonce',
			),
			'https:// and a custom nonce action and name' => array(
				'actionurl' => 'https://example.org/',
				'action'    => 'my_action',
				'name'      => 'my_nonce',
			),
			'/ and default nonce action/name'             => array(
				'actionurl' => '/',
			),
			'/ and a custom nonce action'                 => array(
				'actionurl' => '/',
				'action'    => 'my_action',
			),
			'/ and a custom nonce name'                   => array(
				'actionurl' => '/',
				'action'    => -1,
				'name'      => 'my_nonce',
			),
			'/ and a custom nonce action and name'        => array(
				'actionurl' => '/',
				'action'    => 'my_action',
				'name'      => 'my_nonce',
			),
		);
	}

	/**
	 * Tests that wp_nonce_url() handles existing query args.
	 *
	 * @ticket 54870
	 *
	 * @dataProvider data_should_handle_existing_query_args
	 *
	 * @param string $actionurl URL to add nonce action.
	 * @param string $expected  The expected result.
	 */
	public function test_should_handle_existing_query_args( $actionurl, $expected ) {
		$actual = wp_nonce_url( $actionurl );

		$this->assertStringStartsWith(
			$expected,
			$actual,
			'The nonced URL did not start with the expected value.'
		);

		$this->assertSame(
			strlen( $expected ) + 10,
			strlen( $actual ),
			'The nonced URL was not the expected length.'
		);
	}

	/**
	 * Data provider for test_should_handle_existing_query_args().
	 *
	 * @return array
	 */
	public function data_should_handle_existing_query_args() {
		return array(
			'one query arg'            => array(
				'actionurl' => 'http://example.org/?hello=world',
				'expected'  => 'http://example.org/?hello=world&amp;_wpnonce=',
			),
			'two query args'           => array(
				'actionurl' => 'http://example.org/?hello=world&howdy=admin',
				'expected'  => 'http://example.org/?hello=world&amp;howdy=admin&amp;_wpnonce=',
			),
			'two query args and &amp;' => array(
				'actionurl' => 'http://example.org/?hello=world&amp;howdy=admin',
				'expected'  => 'http://example.org/?hello=world&amp;howdy=admin&amp;_wpnonce=',
			),
		);
	}
}
