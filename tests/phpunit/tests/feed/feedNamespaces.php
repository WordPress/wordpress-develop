<?php

/**
 * Tests for the wp_get_feed_namespaces() and wp_feed_namespaces() functions.
 *
 * @group feed
 *
 * @covers ::wp_get_feed_namespaces
 * @covers ::wp_feed_namespaces
 */
class Tests_Feed_FeedNamespaces extends WP_UnitTestCase {

	/**
	 * @ticket 65785
	 */
	public function test_should_return_default_rss2_namespaces() {
		$namespaces = wp_get_feed_namespaces( 'rss2' );

		$this->assertSameSetsWithIndex(
			array(
				'content' => 'http://purl.org/rss/1.0/modules/content/',
				'wfw'     => 'http://wellformedweb.org/CommentAPI/',
				'dc'      => 'http://purl.org/dc/elements/1.1/',
				'atom'    => 'http://www.w3.org/2005/Atom',
				'sy'      => 'http://purl.org/rss/1.0/modules/syndication/',
				'slash'   => 'http://purl.org/rss/1.0/modules/slash/',
			),
			$namespaces
		);
	}

	/**
	 * @ticket 65785
	 */
	public function test_should_return_default_atom_namespaces() {
		$namespaces = wp_get_feed_namespaces( 'atom' );

		$this->assertSameSetsWithIndex(
			array(
				'thr' => 'http://purl.org/syndication/thread/1.0',
			),
			$namespaces
		);
	}

	/**
	 * @ticket 65785
	 */
	public function test_should_return_namespace_added_via_filter() {
		add_filter(
			'wp_feed_namespaces',
			static function ( array $namespaces ): array {
				$namespaces['source'] = 'http://source.scripting.com/';
				return $namespaces;
			}
		);

		$namespaces = wp_get_feed_namespaces( 'rss2' );

		$this->assertArrayHasKey( 'source', $namespaces );
		$this->assertSame( 'http://source.scripting.com/', $namespaces['source'] );
	}

	/**
	 * The feed type is passed to the filter, so namespaces can be added to
	 * specific feeds only.
	 *
	 * @ticket 65785
	 */
	public function test_filter_should_receive_the_feed_type() {
		add_filter(
			'wp_feed_namespaces',
			static function ( array $namespaces, string $type ): array {
				if ( 'rss2' === $type ) {
					$namespaces['source'] = 'http://source.scripting.com/';
				}
				return $namespaces;
			},
			10,
			2
		);

		$this->assertArrayHasKey( 'source', wp_get_feed_namespaces( 'rss2' ) );
		$this->assertArrayNotHasKey( 'source', wp_get_feed_namespaces( 'atom' ) );
	}

	/**
	 * The bundled feed templates use the default namespaces in their static
	 * markup, so a filter must not be able to remove them.
	 *
	 * @ticket 65785
	 */
	public function test_should_not_allow_removing_default_namespaces() {
		add_filter(
			'wp_feed_namespaces',
			static function () {
				return array( 'media' => 'http://search.yahoo.com/mrss/' );
			}
		);

		$namespaces = wp_get_feed_namespaces( 'rss2' );

		$this->assertArrayHasKey( 'media', $namespaces );
		$this->assertArrayHasKey( 'content', $namespaces );
		$this->assertArrayHasKey( 'atom', $namespaces );
	}

	/**
	 * A filter callback without a return value must not break the feed.
	 *
	 * @ticket 65785
	 */
	public function test_should_handle_a_non_array_filter_return() {
		add_filter( 'wp_feed_namespaces', '__return_null' );

		$namespaces = wp_get_feed_namespaces( 'rss2' );

		$this->assertArrayHasKey( 'content', $namespaces );
	}

	/**
	 * @ticket 65785
	 */
	public function test_should_skip_invalid_prefixes() {
		add_filter(
			'wp_feed_namespaces',
			static function ( array $namespaces ): array {
				$namespaces['']            = 'http://example.org/empty';
				$namespaces['foo bar']     = 'http://example.org/space';
				$namespaces['"onload="x"'] = 'http://example.org/attack';
				$namespaces['0numeric']    = 'http://example.org/numeric';
				$namespaces['xmlns']       = 'http://example.org/reserved';
				$namespaces['XML']         = 'http://example.org/reserved-too';
				$namespaces['empty-uri']   = '';
				return $namespaces;
			}
		);

		$namespaces = wp_get_feed_namespaces( 'rss2' );

		$this->assertArrayNotHasKey( '', $namespaces );
		$this->assertArrayNotHasKey( 'foo bar', $namespaces );
		$this->assertArrayNotHasKey( '"onload="x"', $namespaces );
		$this->assertArrayNotHasKey( '0numeric', $namespaces );
		$this->assertArrayNotHasKey( 'xmlns', $namespaces );
		$this->assertArrayNotHasKey( 'XML', $namespaces );
		$this->assertArrayNotHasKey( 'empty-uri', $namespaces );
	}

	/**
	 * A prefix ending in a newline is a distinct array key from the same prefix
	 * without one, so allowing it through would print the same attribute name
	 * twice: XML permits whitespace between the name and the `=`.
	 *
	 * @ticket 65785
	 */
	public function test_should_skip_a_prefix_with_a_trailing_newline() {
		add_filter(
			'wp_feed_namespaces',
			static function ( array $namespaces ): array {
				$namespaces["content\n"] = 'http://example.org/duplicate';
				return $namespaces;
			}
		);

		$namespaces = wp_get_feed_namespaces( 'rss2' );

		$this->assertArrayNotHasKey( "content\n", $namespaces );
		$this->assertSame( 'http://purl.org/rss/1.0/modules/content/', $namespaces['content'] );
	}

	/**
	 * Prefixes are XML names: mixed case like `creativeCommons` and
	 * non-ASCII letters are valid and must be preserved.
	 *
	 * @ticket 65785
	 */
	public function test_should_preserve_valid_prefixes() {
		add_filter(
			'wp_feed_namespaces',
			static function ( array $namespaces ): array {
				$namespaces['creativeCommons'] = 'http://backend.userland.com/creativeCommonsRssModule';
				$namespaces['média']           = 'http://example.org/media';
				$namespaces['tag-uri']         = 'tag:example.org,2004:ns';
				return $namespaces;
			}
		);

		$namespaces = wp_get_feed_namespaces( 'rss2' );

		$this->assertArrayHasKey( 'creativeCommons', $namespaces );
		$this->assertArrayHasKey( 'média', $namespaces );
		// Namespace URIs are identifiers, not links, so non-http schemes must survive.
		$this->assertSame( 'tag:example.org,2004:ns', $namespaces['tag-uri'] );
	}

	/**
	 * @ticket 65785
	 */
	public function test_should_return_an_empty_array_for_an_unknown_type() {
		$this->assertSame( array(), wp_get_feed_namespaces( 'unknown' ) );
	}

	/**
	 * @ticket 65785
	 */
	public function test_feed_namespaces_should_print_default_namespaces() {
		$output = get_echo( 'wp_feed_namespaces', array( 'rss2' ) );

		$this->assertStringContainsString( 'xmlns:content="http://purl.org/rss/1.0/modules/content/"', $output );
		$this->assertStringContainsString( 'xmlns:slash="http://purl.org/rss/1.0/modules/slash/"', $output );
	}

	/**
	 * @ticket 65785
	 */
	public function test_feed_namespaces_should_escape_the_namespace_uri() {
		add_filter(
			'wp_feed_namespaces',
			static function ( array $namespaces ): array {
				$namespaces['evil'] = 'http://example.org/"><script>';
				return $namespaces;
			}
		);

		$output = get_echo( 'wp_feed_namespaces', array( 'rss2' ) );

		$this->assertStringNotContainsString( '"><script>', $output );
		$this->assertStringContainsString( 'xmlns:evil=', $output );
	}

	/**
	 * Two callbacks adding the same prefix must not result in a duplicate
	 * attribute.
	 *
	 * @ticket 65785
	 */
	public function test_feed_namespaces_should_not_print_duplicate_prefixes() {
		$add_source_ns = static function ( array $namespaces ): array {
			$namespaces['source'] = 'http://source.scripting.com/';
			return $namespaces;
		};
		add_filter( 'wp_feed_namespaces', $add_source_ns, 10 );
		add_filter( 'wp_feed_namespaces', $add_source_ns, 11 );

		$output = get_echo( 'wp_feed_namespaces', array( 'rss2' ) );

		$this->assertSame( 1, substr_count( $output, 'xmlns:source=' ) );
	}
}
