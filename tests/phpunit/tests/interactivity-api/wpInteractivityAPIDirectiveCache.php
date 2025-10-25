<?php
/**
 * Tests for WP_Interactivity_API_Directive_Cache
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @group interactivity-api
 *
 * @covers WP_Interactivity_API_Directive_Cache
 */
class Tests_Interactivity_API_Directive_Cache extends WP_UnitTestCase {
	/**
	 * Instance of WP_Interactivity_API_Directive_Cache for testing.
	 *
	 * @var WP_Interactivity_API_Directive_Cache
	 */
	private $cache;

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->cache = new WP_Interactivity_API_Directive_Cache();
	}

	/**
	 * Tests that cache initially has no entries.
	 *
	 * @ticket 64093
	 */
	public function test_cache_initially_empty() {
		$this->assertSame( 0, $this->cache->get_cache_size() );
	}

	/**
	 * Tests caching directive entries.
	 *
	 * @ticket 64093
	 */
	public function test_get_or_parse_entries_caches_result() {
		$tag_index = 0;
		$prefix    = 'text';
		$call_count = 0;

		$parser = function ( $idx, $pfx ) use ( &$call_count ) {
			$call_count++;
			return array(
				array(
					'namespace' => 'test-plugin',
					'value'     => 'context.value',
					'suffix'    => null,
					'unique_id' => null,
				),
			);
		};

		// First call should invoke the parser
		$result1 = $this->cache->get_or_parse_entries( $tag_index, $prefix, $parser );
		$this->assertSame( 1, $call_count );
		$this->assertIsArray( $result1 );
		$this->assertCount( 1, $result1 );

		// Second call should use cached value, not invoke parser again
		$result2 = $this->cache->get_or_parse_entries( $tag_index, $prefix, $parser );
		$this->assertSame( 1, $call_count, 'Parser should not be called again when using cached value' );
		$this->assertSame( $result1, $result2 );
	}

	/**
	 * Tests that different tag indices have separate cache entries.
	 *
	 * @ticket 64093
	 */
	public function test_different_tag_indices_cached_separately() {
		$parser = function ( $idx, $pfx ) {
			return array(
				array(
					'namespace' => 'test-plugin',
					'value'     => "value-for-tag-{$idx}",
					'suffix'    => null,
					'unique_id' => null,
				),
			);
		};

		$result_tag_0 = $this->cache->get_or_parse_entries( 0, 'text', $parser );
		$result_tag_1 = $this->cache->get_or_parse_entries( 1, 'text', $parser );

		$this->assertSame( 'value-for-tag-0', $result_tag_0[0]['value'] );
		$this->assertSame( 'value-for-tag-1', $result_tag_1[0]['value'] );
		$this->assertSame( 2, $this->cache->get_cache_size() );
	}

	/**
	 * Tests that different directive prefixes have separate cache entries.
	 *
	 * @ticket 64093
	 */
	public function test_different_prefixes_cached_separately() {
		$parser = function ( $idx, $pfx ) {
			return array(
				array(
					'namespace' => 'test-plugin',
					'value'     => "value-for-{$pfx}",
					'suffix'    => null,
					'unique_id' => null,
				),
			);
		};

		$result_text  = $this->cache->get_or_parse_entries( 0, 'text', $parser );
		$result_bind  = $this->cache->get_or_parse_entries( 0, 'bind', $parser );
		$result_class = $this->cache->get_or_parse_entries( 0, 'class', $parser );

		$this->assertSame( 'value-for-text', $result_text[0]['value'] );
		$this->assertSame( 'value-for-bind', $result_bind[0]['value'] );
		$this->assertSame( 'value-for-class', $result_class[0]['value'] );
		$this->assertSame( 3, $this->cache->get_cache_size() );
	}

	/**
	 * Tests checking if entries are cached.
	 *
	 * @ticket 64093
	 */
	public function test_has_cached_entries() {
		$parser = function ( $idx, $pfx ) {
			return array();
		};

		$this->assertFalse( $this->cache->has_cached_entries( 0, 'text' ) );

		$this->cache->get_or_parse_entries( 0, 'text', $parser );

		$this->assertTrue( $this->cache->has_cached_entries( 0, 'text' ) );
		$this->assertFalse( $this->cache->has_cached_entries( 1, 'text' ) );
		$this->assertFalse( $this->cache->has_cached_entries( 0, 'bind' ) );
	}

	/**
	 * Tests caching directive attribute names.
	 *
	 * @ticket 64093
	 */
	public function test_cache_directive_attributes() {
		$attributes = array( 'data-wp-text', 'data-wp-bind--href', 'data-wp-class--active' );

		$this->cache->cache_directive_attributes( 0, $attributes );

		$cached = $this->cache->get_directive_attributes( 0 );
		$this->assertSame( $attributes, $cached );
	}

	/**
	 * Tests getting uncached directive attributes returns null.
	 *
	 * @ticket 64093
	 */
	public function test_get_uncached_directive_attributes_returns_null() {
		$this->assertNull( $this->cache->get_directive_attributes( 0 ) );
		$this->assertNull( $this->cache->get_directive_attributes( 999 ) );
	}

	/**
	 * Tests clearing the cache.
	 *
	 * @ticket 64093
	 */
	public function test_clear_cache() {
		$parser = function ( $idx, $pfx ) {
			return array( array( 'value' => 'test' ) );
		};

		$this->cache->get_or_parse_entries( 0, 'text', $parser );
		$this->cache->cache_directive_attributes( 0, array( 'data-wp-text' ) );

		$this->assertSame( 1, $this->cache->get_cache_size() );
		$this->assertNotNull( $this->cache->get_directive_attributes( 0 ) );

		$this->cache->clear();

		$this->assertSame( 0, $this->cache->get_cache_size() );
		$this->assertNull( $this->cache->get_directive_attributes( 0 ) );
	}

	/**
	 * Tests that cache works correctly with realistic directive entries.
	 *
	 * @ticket 64093
	 */
	public function test_cache_realistic_directive_entries() {
		$parser = function ( $idx, $pfx ) {
			if ( 'text' === $pfx ) {
				return array(
					array(
						'namespace' => 'my-plugin',
						'value'     => 'context.item.title',
						'suffix'    => null,
						'unique_id' => null,
					),
				);
			} elseif ( 'bind' === $pfx ) {
				return array(
					array(
						'namespace' => 'my-plugin',
						'value'     => 'context.item.link',
						'suffix'    => 'href',
						'unique_id' => null,
					),
				);
			}
			return array();
		};

		// Cache multiple directive types for multiple tags
		$text_0 = $this->cache->get_or_parse_entries( 0, 'text', $parser );
		$bind_0 = $this->cache->get_or_parse_entries( 0, 'bind', $parser );
		$text_1 = $this->cache->get_or_parse_entries( 1, 'text', $parser );

		$this->assertSame( 3, $this->cache->get_cache_size() );
		$this->assertSame( 'context.item.title', $text_0[0]['value'] );
		$this->assertSame( 'context.item.link', $bind_0[0]['value'] );
		$this->assertSame( 'href', $bind_0[0]['suffix'] );
	}

	/**
	 * Tests that cache handles empty results correctly.
	 *
	 * @ticket 64093
	 */
	public function test_cache_handles_empty_results() {
		$parser = function ( $idx, $pfx ) {
			return array(); // No directives of this type
		};

		$result = $this->cache->get_or_parse_entries( 0, 'text', $parser );

		$this->assertIsArray( $result );
		$this->assertCount( 0, $result );
		$this->assertTrue( $this->cache->has_cached_entries( 0, 'text' ) );
	}
}

