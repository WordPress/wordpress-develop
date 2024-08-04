<?php

/**
 * Tests for the links_add_base_url function.
 *
 * @group formatting
 *
 * @covers ::links_add_base_url
 */
class Tests_formatting_linksAddBaseUrl extends WP_UnitTestCase {

	/**
	 * @ticket 60389
	 *
	 * @dataProvider data_links_add_base_url
	 */
	public function test_links_add_base_url( $content, $base, $attrs, $expected ) {
		$this->assertSame( $expected, links_add_base_url( $content, $base, $attrs ) );
	}

	public function data_links_add_base_url() {
		return array(
			'default' => array(
				'content' => '<a href="url" />',
				'base' => 'https://localhost',
				'attrs' => null,
				'expected' => '<a href="https://localhost/url" />',
			),
			'empty_array' => array(
				'content' => '<a href="url" target="_blank" />',
				'base' => 'https://localhost',
				'attrs' => array(),
				'expected' => '<a href="https://localhost/url" target="_blank" />',
			),
			'data_url' => array(
				'content' => '<a href="url" data-url="url" />',
				'base' => 'https://localhost',
				'attrs' => array( 'data-url', 'href' ),
				'expected' => '<a href="https://localhost/url" data-url="https://localhost/url" />',
			),
			'not relative' => array(
				'content' => '<a href="https://localhost/url" />',
				'base' => 'https://localbase',
				'attrs' => null,
				'expected' => '<a href="https://localhost/url" />',
			),
			'no_href' => array(
				'content' => '<a data-url="/url" />',
				'base' => 'https://localhost',
				'attrs' => null,
				'expected' => '<a data-url="/url" />',
			),
			'img' => array(
				'content' => '<img src="/url" />',
				'base' => 'https://localhost',
				'attrs' => null,
				'expected' => '<img src="https://localhost/url" />',
			),
		);
	}
}
