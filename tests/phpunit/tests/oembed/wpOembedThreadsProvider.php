<?php

/**
 * Tests for the Threads oEmbed provider.
 *
 * @group oembed
 *
 * @covers WP_oEmbed::get_provider
 */
class Tests_oEmbed_wpOembedThreadsProvider extends WP_UnitTestCase {

	protected WP_oEmbed $oembed;

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . WPINC . '/class-wp-oembed.php';
		$this->oembed = _wp_oembed_get_object();
	}

	/**
	 * @ticket 64858
	 *
	 * @dataProvider data_threads_provider_urls
	 *
	 * @param string $url      The URL to test.
	 * @param string $expected The expected oEmbed provider URL.
	 */
	public function test_threads_provider_url( string $url, string $expected ): void {
		$provider = $this->oembed->get_provider( $url, array( 'discover' => false ) );
		$this->assertSame( $expected, $provider );
	}

	/**
	 * Data provider for valid Threads URLs.
	 *
	 * @return array<string, array{ 0: string, 1: string }>
	 */
	public function data_threads_provider_urls(): array {
		return array(
			// threads.com post URLs.
			'threads.com post URL'         => array(
				'https://www.threads.com/@zuck/post/C1234567890',
				'https://graph.threads.com/oembed',
			),
			'threads.com post URL no www'  => array(
				'https://threads.com/@zuck/post/C1234567890',
				'https://graph.threads.com/oembed',
			),
			'threads.com post URL http'    => array(
				'http://www.threads.com/@zuck/post/C1234567890',
				'https://graph.threads.com/oembed',
			),

			// threads.com short URLs.
			'threads.com short URL'        => array(
				'https://www.threads.com/t/C1234567890',
				'https://graph.threads.com/oembed',
			),
			'threads.com short URL no www' => array(
				'https://threads.com/t/C1234567890',
				'https://graph.threads.com/oembed',
			),
			'threads.com short URL http'   => array(
				'http://www.threads.com/t/C1234567890',
				'https://graph.threads.com/oembed',
			),

			// threads.net post URLs.
			'threads.net post URL'         => array(
				'https://www.threads.net/@zuck/post/C1234567890',
				'https://graph.threads.com/oembed',
			),
			'threads.net post URL no www'  => array(
				'https://threads.net/@zuck/post/C1234567890',
				'https://graph.threads.com/oembed',
			),

			// threads.net short URLs.
			'threads.net short URL'        => array(
				'https://www.threads.net/t/C1234567890',
				'https://graph.threads.com/oembed',
			),
			'threads.net short URL no www' => array(
				'https://threads.net/t/C1234567890',
				'https://graph.threads.com/oembed',
			),
		);
	}

	/**
	 * @ticket 64858
	 *
	 * @dataProvider data_threads_non_matching_urls
	 *
	 * @param string $url The URL to test.
	 */
	public function test_threads_provider_does_not_match_non_post_urls( string $url ): void {
		$provider = $this->oembed->get_provider( $url, array( 'discover' => false ) );
		$this->assertFalse( $provider );
	}

	/**
	 * Data provider for URLs that should not match the Threads provider.
	 *
	 * @return array<string, array{ 0: string }>
	 */
	public function data_threads_non_matching_urls(): array {
		return array(
			'threads.com profile URL'              => array(
				'https://www.threads.com/@zuck',
			),
			'threads.com homepage'                 => array(
				'https://www.threads.com/',
			),
			'threads.com search'                   => array(
				'https://www.threads.com/search',
			),
			'non-threads URL with threads in path' => array(
				'https://example.com/threads/post/123',
			),
		);
	}
}
