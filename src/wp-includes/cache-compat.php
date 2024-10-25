<?php
/**
 * Object Cache API functions missing from 3rd party object caches.
 *
 * @link https://developer.wordpress.org/reference/classes/wp_object_cache/
 *
 * @package WordPress
 * @subpackage Cache
 */

if ( ! function_exists( 'wp_cache_add_multiple' ) ) :
	/**
	 * Adds multiple values to the cache in one call, if the cache keys don't already exist.
	 *
	 * Compat function to mimic wp_cache_add_multiple().
	 *
	 * @ignore
	 * @since 6.0.0
	 *
	 * @see wp_cache_add_multiple()
	 *
	 * @param array  $data   Array of keys and values to be added.
	 * @param string $group  Optional. Where the cache contents are grouped. Default empty.
	 * @param int    $expire Optional. When to expire the cache contents, in seconds.
	 *                       Default 0 (no expiration).
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false if cache key and group already exist.
	 */
	function wp_cache_add_multiple( array $data, $group = '', $expire = 0 ) {
		$values = array();

		foreach ( $data as $key => $value ) {
			$values[ $key ] = wp_cache_add( $key, $value, $group, $expire );
		}

		return $values;
	}
endif;

if ( ! function_exists( 'wp_cache_set_multiple' ) ) :
	/**
	 * Sets multiple values to the cache in one call.
	 *
	 * Differs from wp_cache_add_multiple() in that it will always write data.
	 *
	 * Compat function to mimic wp_cache_set_multiple().
	 *
	 * @ignore
	 * @since 6.0.0
	 *
	 * @see wp_cache_set_multiple()
	 *
	 * @param array  $data   Array of keys and values to be set.
	 * @param string $group  Optional. Where the cache contents are grouped. Default empty.
	 * @param int    $expire Optional. When to expire the cache contents, in seconds.
	 *                       Default 0 (no expiration).
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false on failure.
	 */
	function wp_cache_set_multiple( array $data, $group = '', $expire = 0 ) {
		$values = array();

		foreach ( $data as $key => $value ) {
			$values[ $key ] = wp_cache_set( $key, $value, $group, $expire );
		}

		return $values;
	}
endif;

if ( ! function_exists( 'wp_cache_get_multiple' ) ) :
	/**
	 * Retrieves multiple values from the cache in one call.
	 *
	 * Compat function to mimic wp_cache_get_multiple().
	 *
	 * @ignore
	 * @since 5.5.0
	 * @since 6.3.0 Added the `$found` parameter.
	 *
	 * @see wp_cache_get_multiple()
	 *
	 * @param array  $keys  Array of keys under which the cache contents are stored.
	 * @param string $group Optional. Where the cache contents are grouped. Default empty.
	 * @param bool   $force Optional. Whether to force an update of the local cache
	 *                      from the persistent cache. Default false.
	 * @param array  $found Optional. Whether array of keys were found in the cache (passed by reference).
	 *                      Disambiguates a return of false, a storable value. Default empty array.
	 * @return array Array of return values, grouped by key. Each value is either
	 *               the cache contents on success, or false on failure.
	 */
	function wp_cache_get_multiple( $keys, $group = '', $force = false, &$found = array() ) {
		$values = array();

		foreach ( $keys as $key ) {
			$values[ $key ] = wp_cache_get( $key, $group, $force, $found[ $key ] );
		}

		return $values;
	}
endif;

if ( ! function_exists( 'wp_cache_delete_multiple' ) ) :
	/**
	 * Deletes multiple values from the cache in one call.
	 *
	 * Compat function to mimic wp_cache_delete_multiple().
	 *
	 * @ignore
	 * @since 6.0.0
	 *
	 * @see wp_cache_delete_multiple()
	 *
	 * @param array  $keys  Array of keys under which the cache to deleted.
	 * @param string $group Optional. Where the cache contents are grouped. Default empty.
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false if the contents were not deleted.
	 */
	function wp_cache_delete_multiple( array $keys, $group = '' ) {
		$values = array();

		foreach ( $keys as $key ) {
			$values[ $key ] = wp_cache_delete( $key, $group );
		}

		return $values;
	}
endif;

if ( ! function_exists( 'wp_cache_flush_runtime' ) ) :
	/**
	 * Removes all cache items from the in-memory runtime cache.
	 *
	 * Compat function to mimic wp_cache_flush_runtime().
	 *
	 * @ignore
	 * @since 6.0.0
	 *
	 * @see wp_cache_flush_runtime()
	 *
	 * @return bool True on success, false on failure.
	 */
	function wp_cache_flush_runtime() {
		if ( ! wp_cache_supports( 'flush_runtime' ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				__( 'Your object cache implementation does not support flushing the in-memory runtime cache.' ),
				'6.1.0'
			);

			return false;
		}

		return wp_cache_flush();
	}
endif;

if ( ! function_exists( 'wp_cache_flush_group' ) ) :
	/**
	 * Removes all cache items in a group, if the object cache implementation supports it.
	 *
	 * Before calling this function, always check for group flushing support using the
	 * `wp_cache_supports( 'flush_group' )` function.
	 *
	 * @since 6.1.0
	 *
	 * @see WP_Object_Cache::flush_group()
	 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
	 *
	 * @param string $group Name of group to remove from cache.
	 * @return bool True if group was flushed, false otherwise.
	 */
	function wp_cache_flush_group( $group ) {
		global $wp_object_cache;

		if ( ! wp_cache_supports( 'flush_group' ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				__( 'Your object cache implementation does not support flushing individual groups.' ),
				'6.1.0'
			);

			return false;
		}

		return $wp_object_cache->flush_group( $group );
	}
endif;

if ( ! function_exists( 'wp_cache_supports' ) ) :
	/**
	 * Determines whether the object cache implementation supports a particular feature.
	 *
	 * @since 6.1.0
	 *
	 * @param string $feature Name of the feature to check for. Possible values include:
	 *                        'add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple',
	 *                        'flush_runtime', 'flush_group'.
	 * @return bool True if the feature is supported, false otherwise.
	 */
	function wp_cache_supports( $feature ) {
		return false;
	}
endif;
