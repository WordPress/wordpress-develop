<?php
/**
 * Tests for WP_AI_Client_Cache.
 *
 * @group ai-client
 * @covers WP_AI_Client_Cache
 */
class Tests_AI_Client_Cache extends WP_UnitTestCase {

	/**
	 * Cache instance under test.
	 *
	 * @var WP_AI_Client_Cache
	 */
	private $cache;

	/**
	 * Sets up a fresh cache instance before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->cache = new WP_AI_Client_Cache();
	}

	/**
	 * Test that the cache implements the scoped PSR-16 CacheInterface.
	 *
	 * @ticket 64591
	 */
	public function test_implements_cache_interface() {
		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface::class,
			$this->cache
		);
	}

	/**
	 * Test that get returns default value on cache miss.
	 *
	 * @ticket 64591
	 */
	public function test_get_returns_default_on_miss() {
		$this->assertNull( $this->cache->get( 'nonexistent' ) );
		$this->assertSame( 'fallback', $this->cache->get( 'nonexistent', 'fallback' ) );
	}

	/**
	 * Test set and get round-trip.
	 *
	 * @ticket 64591
	 */
	public function test_set_and_get() {
		$this->assertTrue( $this->cache->set( 'key1', 'value1' ) );
		$this->assertSame( 'value1', $this->cache->get( 'key1' ) );
	}

	/**
	 * Test delete removes cached item.
	 *
	 * @ticket 64591
	 */
	public function test_delete() {
		$this->cache->set( 'key1', 'value1' );
		$this->assertTrue( $this->cache->delete( 'key1' ) );
		$this->assertNull( $this->cache->get( 'key1' ) );
	}

	/**
	 * Test has returns false on cache miss.
	 *
	 * @ticket 64591
	 */
	public function test_has_returns_false_on_miss() {
		$this->assertFalse( $this->cache->has( 'nonexistent' ) );
	}

	/**
	 * Test has returns true on cache hit.
	 *
	 * @ticket 64591
	 */
	public function test_has_returns_true_on_hit() {
		$this->cache->set( 'key1', 'value1' );
		$this->assertTrue( $this->cache->has( 'key1' ) );
	}

	/**
	 * Test getMultiple returns values and defaults.
	 *
	 * @ticket 64591
	 */
	public function test_get_multiple() {
		$this->cache->set( 'key1', 'value1' );
		$this->cache->set( 'key2', 'value2' );

		$result = $this->cache->getMultiple( array( 'key1', 'key2', 'key3' ), 'default' );

		$this->assertSame( 'value1', $result['key1'] );
		$this->assertSame( 'value2', $result['key2'] );
		$this->assertSame( 'default', $result['key3'] );
	}

	/**
	 * Test setMultiple stores multiple values.
	 *
	 * @ticket 64591
	 */
	public function test_set_multiple() {
		$this->assertTrue(
			$this->cache->setMultiple(
				array(
					'key1' => 'value1',
					'key2' => 'value2',
				)
			)
		);

		$this->assertSame( 'value1', $this->cache->get( 'key1' ) );
		$this->assertSame( 'value2', $this->cache->get( 'key2' ) );
	}

	/**
	 * Test deleteMultiple removes multiple items.
	 *
	 * @ticket 64591
	 */
	public function test_delete_multiple() {
		$this->cache->set( 'key1', 'value1' );
		$this->cache->set( 'key2', 'value2' );

		$this->assertTrue( $this->cache->deleteMultiple( array( 'key1', 'key2' ) ) );
		$this->assertNull( $this->cache->get( 'key1' ) );
		$this->assertNull( $this->cache->get( 'key2' ) );
	}

	/**
	 * Test clear flushes the cache group.
	 *
	 * @ticket 64591
	 */
	public function test_clear() {
		$this->cache->set( 'key1', 'value1' );

		// WordPress default object cache supports flush_group.
		$result = $this->cache->clear();

		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			$this->assertTrue( $result );
			$this->assertNull( $this->cache->get( 'key1' ) );
		} else {
			$this->assertFalse( $result );
		}
	}

	/**
	 * Test that get returns a stored false value instead of the default.
	 *
	 * @ticket 64591
	 */
	public function test_get_returns_stored_false() {
		$this->cache->set( 'key_false', false );
		$this->assertFalse( $this->cache->get( 'key_false', 'default' ) );
	}

	/**
	 * Test that get returns a stored null value instead of the default.
	 *
	 * @ticket 64591
	 */
	public function test_get_returns_stored_null() {
		$this->cache->set( 'key_null', null );
		$this->assertNull( $this->cache->get( 'key_null', 'default' ) );
	}

	/**
	 * Test that getMultiple returns a stored false value instead of the default.
	 *
	 * @ticket 64591
	 */
	public function test_get_multiple_returns_stored_false() {
		$this->cache->set( 'key_false', false );

		$result = $this->cache->getMultiple( array( 'key_false' ), 'default' );

		$this->assertFalse( $result['key_false'] );
	}

	/**
	 * Test that getMultiple returns a stored null value instead of the default.
	 *
	 * @ticket 64591
	 */
	public function test_get_multiple_returns_stored_null() {
		$this->cache->set( 'key_null', null );

		$result = $this->cache->getMultiple( array( 'key_null' ), 'default' );

		$this->assertNull( $result['key_null'] );
	}

	/**
	 * Test set with integer TTL.
	 *
	 * @ticket 64591
	 */
	public function test_ttl_with_integer() {
		$this->assertTrue( $this->cache->set( 'key1', 'value1', 3600 ) );
		$this->assertSame( 'value1', $this->cache->get( 'key1' ) );
	}

	/**
	 * Test set with DateInterval TTL.
	 *
	 * @ticket 64591
	 */
	public function test_ttl_with_date_interval() {
		$ttl = new DateInterval( 'PT1H' );
		$this->assertTrue( $this->cache->set( 'key1', 'value1', $ttl ) );
		$this->assertSame( 'value1', $this->cache->get( 'key1' ) );
	}
}
