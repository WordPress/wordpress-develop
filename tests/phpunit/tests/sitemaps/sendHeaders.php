<?php

/**
 * Tests that HTTP headers can be added to core sitemap responses with the
 * existing `wp_headers` filter and `send_headers` action, combined with is_sitemap().
 *
 * WP::main() runs query_posts() before send_headers(), so conditional tags
 * work in both hooks. Both fire before `template_redirect`, where
 * WP_Sitemaps::render_sitemaps() sends its own Content-Type header and output.
 *
 * @group sitemaps
 * @group wp
 *
 * @covers WP::send_headers
 * @covers ::is_sitemap
 */
class Tests_Sitemaps_SendHeaders extends WP_UnitTestCase {

	/**
	 * Headers passed through the `wp_headers` filter on the last request.
	 *
	 * @var array<string, string>
	 */
	private array $headers = array();

	/**
	 * Order in which the observed events fired.
	 *
	 * @var string[]
	 */
	private array $events = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		$factory->post->create_many( 3 );
		$factory->post->create( array( 'post_type' => 'page' ) );
		$factory->user->create( array( 'role' => 'author' ) );
	}

	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '' );

		// Registering the sitemap rewrite tags adds the query vars to `$wp->public_query_vars`.
		wp_sitemaps_get_server();

		add_filter( 'wp_headers', array( $this, 'add_x_robots_tag_to_sitemaps' ) );
	}

	/**
	 * Example plugin callback: adds X-Robots-Tag to sitemap responses only.
	 *
	 * @param array<string, string> $headers Headers to send.
	 * @return array<string, string> Filtered headers.
	 */
	public function add_x_robots_tag_to_sitemaps( array $headers ): array {
		$this->events[] = 'wp_headers';

		if ( is_sitemap() ) {
			$headers['X-Robots-Tag'] = 'noindex, follow';
		}

		$this->headers = $headers;

		return $headers;
	}

	/**
	 * @ticket 60552
	 *
	 * @dataProvider data_sitemap_routes
	 *
	 * @param string $query_string Sitemap route query string.
	 */
	public function test_wp_headers_filter_can_add_header_to_sitemap_routes( string $query_string ): void {
		$this->go_to( home_url( '/' . $query_string ) );

		$this->assertTrue( is_sitemap(), 'is_sitemap() should be true for a sitemap route.' );
		$this->assertArrayHasKey( 'X-Robots-Tag', $this->headers, 'The header should have been added via wp_headers.' );
		$this->assertSame( 'noindex, follow', $this->headers['X-Robots-Tag'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ string }>
	 */
	public static function data_sitemap_routes(): array {
		return array(
			'index'               => array( '?sitemap=index' ),
			'posts: post'         => array( '?sitemap=posts&sitemap-subtype=post' ),
			'posts: page'         => array( '?sitemap=posts&sitemap-subtype=page' ),
			'posts: post, page 1' => array( '?sitemap=posts&sitemap-subtype=post&paged=1' ),
			'taxonomies'          => array( '?sitemap=taxonomies&sitemap-subtype=category' ),
			'users'               => array( '?sitemap=users' ),
		);
	}

	/**
	 * @ticket 60552
	 */
	public function test_wp_headers_filter_does_not_add_header_to_non_sitemap_routes(): void {
		$this->go_to( home_url( '/' ) );

		$this->assertFalse( is_sitemap() );
		$this->assertArrayNotHasKey( 'X-Robots-Tag', $this->headers );
	}

	/**
	 * The `send_headers` action (the approach in comment:6 of #60552) also sees is_sitemap().
	 *
	 * @ticket 60552
	 */
	public function test_send_headers_action_sees_is_sitemap(): void {
		$is_sitemap_in_send_headers = null;

		add_action(
			'send_headers',
			static function () use ( &$is_sitemap_in_send_headers ) {
				$is_sitemap_in_send_headers = is_sitemap();
			}
		);

		$this->go_to( home_url( '/?sitemap=posts&sitemap-subtype=post' ) );

		$this->assertTrue( $is_sitemap_in_send_headers );
	}

	/**
	 * Headers are filtered before the sitemap renderer runs and produces output.
	 *
	 * @ticket 60552
	 */
	public function test_headers_are_filtered_before_sitemap_is_rendered(): void {
		$sitemaps = wp_sitemaps_get_server();

		add_filter(
			'wp_sitemaps_posts_entry',
			function ( $entry ) {
				if ( ! in_array( 'render', $this->events, true ) ) {
					$this->events[] = 'render';
				}
				return $entry;
			}
		);

		// WP::main(), which fires `wp_headers` via WP::send_headers().
		$this->go_to( home_url( '/?sitemap=posts&sitemap-subtype=post' ) );

		// Then what `template_redirect` does on a real request.
		$output = get_echo( array( $sitemaps, 'render_sitemaps' ) );

		$this->assertStringContainsString( '<urlset', $output, 'The sitemap should have been rendered.' );
		$this->assertSame( array( 'wp_headers', 'render' ), $this->events, 'Headers should be filtered before rendering.' );
		$this->assertSame( 'noindex, follow', $this->headers['X-Robots-Tag'] );
		$this->assertFalse( is_404() );
	}

	/**
	 * Caveat: is_sitemap() is true for any request with the `sitemap` query var,
	 * including routes that render_sitemaps() later turns into a 404.
	 *
	 * @ticket 60552
	 */
	public function test_header_is_also_added_to_sitemap_routes_that_404(): void {
		$sitemaps = wp_sitemaps_get_server();

		$this->go_to( home_url( '/?sitemap=this-provider-does-not-exist' ) );

		get_echo( array( $sitemaps, 'render_sitemaps' ) );

		$this->assertArrayHasKey( 'X-Robots-Tag', $this->headers );
		$this->assertTrue( is_404() );
	}
}
