<?php

/**
 * Tests for the links_add_base_url() function.
 *
 * @group formatting
 *
 * @covers ::links_add_base_url
 */
class Tests_Formatting_LinksAddBaseUrl extends WP_UnitTestCase {

	/**
	 * @ticket 60389
	 *
	 * @dataProvider data_links_add_base_url
	 */
	public function test_links_add_base_url( $content, $base, $attrs, $expected ) {
		if ( is_null( $attrs ) ) {
			$this->assertSame( $expected, links_add_base_url( $content, $base ) );
		} else {
			$this->assertSame( $expected, links_add_base_url( $content, $base, $attrs ) );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string     $content  String to search for links in.
	 *         @type string     $base     The base URL to prefix to links.
	 *         @type array|null $attrs    The attributes which should be processed.
	 *         @type string     $expected Expected output.
	 *     }
	 * }
	 */
	public function data_links_add_base_url() {
		return array(
			'https'           => array(
				'content'  => '<a href="url" />',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<a href="https://localhost/url" />',
			),
			'http'            => array(
				'content'  => '<a href="url" />',
				'base'     => 'http://localhost',
				'attrs'    => null,
				'expected' => '<a href="http://localhost/url" />',
			),
			'relative scheme' => array(
				'content'  => '<a href="//localhost/url" />',
				'base'     => 'http://localhost',
				'attrs'    => null,
				'expected' => '<a href="http://localhost/url" />',
			),
			'empty array'     => array(
				'content'  => '<a href="url" target="_blank" />',
				'base'     => 'https://localhost',
				'attrs'    => array(),
				'expected' => '<a href="https://localhost/url" target="https://localhost/_blank" />',
			),
			'data-url'        => array(
				'content'  => '<a href="url" data-url="url" />',
				'base'     => 'https://localhost',
				'attrs'    => array( 'data-url', 'href' ),
				'expected' => '<a href="https://localhost/url" data-url="https://localhost/url" />',
			),
			'not relative'    => array(
				'content'  => '<a href="https://localhost/url" />',
				'base'     => 'https://localbase',
				'attrs'    => null,
				'expected' => '<a href="https://localhost/url" />',
			),
			'no href'         => array(
				'content'  => '<a data-url="/url" />',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<a data-url="/url" />',
			),
			'img'             => array(
				'content'  => '<img src="/url" />',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<img src="https://localhost/url" />',
			),
			'ftp'             => array(
				'content'  => '<a href="/url" >sss</a>',
				'base'     => 'ftp://localhost',
				'attrs'    => null,
				'expected' => '<a href="ftp://localhost/url" >sss</a>',
			),
			'ftps'            => array(
				'content'  => '<a href="/url" >sss</a>',
				'base'     => 'ftps://localhost',
				'attrs'    => null,
				'expected' => '<a href="ftps://localhost/url" >sss</a>',
			),
		);
	}
}
