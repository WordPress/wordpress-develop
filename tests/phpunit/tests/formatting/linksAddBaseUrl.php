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
	 * @ticket 60391
	 * @ticket 65984
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
	 * Tests that _links_add_base() triggers a deprecation warning and returns expected result.
	 *
	 * @ticket 65984
	 *
	 * @expectedDeprecated _links_add_base
	 */
	public function test_deprecated_links_add_base() {
		global $_links_add_base;
		$_links_add_base = 'https://localhost';

		$match = array(
			'href="url"',
			'href',
			'"',
			'url',
		);

		$this->assertSame( 'href="https://localhost/url"', _links_add_base( $match ) );
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
	public static function data_links_add_base_url() {
		return array(
			'https'                       => array(
				'content'  => '<a href="url" />',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<a href="https://localhost/url" />',
			),
			'http'                        => array(
				'content'  => '<a href="url" />',
				'base'     => 'http://localhost',
				'attrs'    => null,
				'expected' => '<a href="http://localhost/url" />',
			),
			'relative scheme'             => array(
				'content'  => '<a href="//localhost/url" />',
				'base'     => 'http://localhost',
				'attrs'    => null,
				'expected' => '<a href="http://localhost/url" />',
			),
			'empty array'                 => array(
				'content'  => '<a href="url" target="_blank" />',
				'base'     => 'https://localhost',
				'attrs'    => array(),
				'expected' => '<a href="https://localhost/url" target="_blank" />',
			),
			'data-url'                    => array(
				'content'  => '<a href="url" data-url="url" />',
				'base'     => 'https://localhost',
				'attrs'    => array( 'data-url', 'href' ),
				'expected' => '<a href="https://localhost/url" data-url="https://localhost/url" />',
			),
			'not relative'                => array(
				'content'  => '<a href="https://localhost/url" />',
				'base'     => 'https://localbase',
				'attrs'    => null,
				'expected' => '<a href="https://localhost/url" />',
			),
			'no href'                     => array(
				'content'  => '<a data-url="/url" />',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<a data-url="/url" />',
			),
			'img'                         => array(
				'content'  => '<img src="/url" />',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<img src="https://localhost/url" />',
			),
			'ftp'                         => array(
				'content'  => '<a href="/url" >sss</a>',
				'base'     => 'ftp://localhost',
				'attrs'    => null,
				'expected' => '<a href="ftp://localhost/url" >sss</a>',
			),
			'ftps'                        => array(
				'content'  => '<a href="/url" >sss</a>',
				'base'     => 'ftps://localhost',
				'attrs'    => null,
				'expected' => '<a href="ftps://localhost/url" >sss</a>',
			),
			'single quotes'               => array(
				'content'  => "<a href='url' />",
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<a href="https://localhost/url" />',
			),
			'uppercase tag and attribute' => array(
				'content'  => '<A HREF="/url">Link</A>',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<A href="https://localhost/url">Link</A>',
			),
			'html comment ignored'        => array(
				'content'  => '<!-- <a href="/url">Link</a> -->',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<!-- <a href="/url">Link</a> -->',
			),
			'script tag ignored'          => array(
				'content'  => '<script><a href="/url">Link</a></script>',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<script><a href="/url">Link</a></script>',
			),
			'style tag ignored'           => array(
				'content'  => '<style><a href="/url">Link</a></style>',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<style><a href="/url">Link</a></style>',
			),
			'plain text match ignored'    => array(
				'content'  => '<p>Mentioning href="/url" in plain text</p>',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<p>Mentioning href="/url" in plain text</p>',
			),
			'single string attr'          => array(
				'content'  => '<a href="/link"><img src="/image.jpg" /></a>',
				'base'     => 'https://localhost',
				'attrs'    => 'src',
				'expected' => '<a href="/link"><img src="https://localhost/image.jpg" /></a>',
			),
			'fragment identifier'         => array(
				'content'  => '<a href="#section">Section</a>',
				'base'     => 'https://localhost/plugins/my-plugin/',
				'attrs'    => null,
				'expected' => '<a href="https://localhost/plugins/my-plugin/#section">Section</a>',
			),
			'query string relative'       => array(
				'content'  => '<a href="?tab=faq">FAQ</a>',
				'base'     => 'https://localhost/plugins/my-plugin/',
				'attrs'    => null,
				'expected' => '<a href="https://localhost/plugins/my-plugin/?tab=faq">FAQ</a>',
			),
			'mailto allowed protocol'     => array(
				'content'  => '<a href="mailto:admin@example.com">Email</a>',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<a href="mailto:admin@example.com">Email</a>',
			),
			'tel allowed protocol'        => array(
				'content'  => '<a href="tel:+1234567890">Call</a>',
				'base'     => 'https://localhost',
				'attrs'    => null,
				'expected' => '<a href="tel:+1234567890">Call</a>',
			),
		);
	}
}
