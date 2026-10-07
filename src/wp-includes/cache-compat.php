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
	 *
	 * @see wp_cache_get_multiple()
	 *
	 * @param array  $keys  Array of keys under which the cache contents are stored.
	 * @param string $group Optional. Where the cache contents are grouped. Default empty.
	 * @param bool   $force Optional. Whether to force an update of the local cache
	 *                      from the persistent cache. Default false.
	 * @return array Array of return values, grouped by key. Each value is either
	 *               the cache contents on success, or false on failure.
	 */
	function wp_cache_get_multiple( $keys, $group = '', $force = false ) {
		$values = array();

		foreach ( $keys as $key ) {
			$values[ $key ] = wp_cache_get( $key, $group, $force );
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

if ( ! function_exists( 'wp_cache_get_salted' ) ) :
	/**
	 * Retrieves cached data if valid and unchanged.
	 *
	 * @since 6.9.0
	 *
	 * @param string          $cache_key The cache key used for storage and retrieval.
	 * @param string          $group     The cache group used for organizing data.
	 * @param string|string[] $salt      The timestamp (or multiple timestamps if an array) indicating when the cache group(s) were last updated.
	 * @return mixed|false The cached data if valid, or false if the cache does not exist or is outdated.
	 */
	function wp_cache_get_salted( $cache_key, $group, $salt ) {
		$salt  = is_array( $salt ) ? implode( ':', $salt ) : $salt;
		$cache = wp_cache_get( $cache_key, $group );

		if ( ! is_array( $cache ) ) {
			return false;
		}

		if ( ! isset( $cache['salt'] ) || ! isset( $cache['data'] ) || $salt !== $cache['salt'] ) {
			return false;
		}

		return $cache['data'];
	}
endif;

if ( ! function_exists( 'wp_cache_set_salted' ) ) :
	/**
	 * Stores salted data in the cache.
	 *
	 * @since 6.9.0
	 *
	 * @param string          $cache_key The cache key under which to store the data.
	 * @param mixed           $data      The data to be cached.
	 * @param string          $group     The cache group to which the data belongs.
	 * @param string|string[] $salt      The timestamp (or multiple timestamps if an array) indicating when the cache group(s) were last updated.
	 * @param int             $expire    Optional. When to expire the cache contents, in seconds.
	 *                                   Default 0 (no expiration).
	 * @return bool True on success, false on failure.
	 */
	function wp_cache_set_salted( $cache_key, $data, $group, $salt, $expire = 0 ) {
		$salt = is_array( $salt ) ? implode( ':', $salt ) : $salt;
		return wp_cache_set(
			$cache_key,
			array(
				'data' => $data,
				'salt' => $salt,
			),
			$group,
			$expire
		);
	}
endif;

if ( ! function_exists( 'wp_cache_get_multiple_salted' ) ) :
	/**
	 * Retrieves multiple items from the cache, only considering valid and unchanged items.
	 *
	 * @since 6.9.0
	 *
	 * @param array           $cache_keys Array of cache keys to retrieve.
	 * @param string          $group      The group of the cache to check.
	 * @param string|string[] $salt       The timestamp (or multiple timestamps if an array) indicating when the cache group(s) were last updated.
	 * @return array An associative array containing cache values. Values are `false` if they are not found or outdated.
	 */
	function wp_cache_get_multiple_salted( $cache_keys, $group, $salt ) {
		$salt  = is_array( $salt ) ? implode( ':', $salt ) : $salt;
		$cache = wp_cache_get_multiple( $cache_keys, $group );

		foreach ( $cache as $key => $value ) {
			if ( ! is_array( $value ) ) {
				$cache[ $key ] = false;
				continue;
			}
			if ( ! isset( $value['salt'], $value['data'] ) || $salt !== $value['salt'] ) {
				$cache[ $key ] = false;
				continue;
			}
			$cache[ $key ] = $value['data'];
		}

		return $cache;
	}
endif;

if ( ! function_exists( 'wp_cache_set_multiple_salted' ) ) :
	/**
	 * Stores multiple pieces of salted data in the cache.
	 *
	 * @since 6.9.0
	 *
	 * @param mixed           $data   Data to be stored in the cache for all keys.
	 * @param string          $group  Group to which the cached data belongs.
	 * @param string|string[] $salt   The timestamp (or multiple timestamps if an array) indicating when the cache group(s) were last updated.
	 * @param int             $expire Optional. When to expire the cache contents, in seconds.
	 *                                Default 0 (no expiration).
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false on failure.
	 */
	function wp_cache_set_multiple_salted( $data, $group, $salt, $expire = 0 ) {
		$salt      = is_array( $salt ) ? implode( ':', $salt ) : $salt;
		$new_cache = array();
		foreach ( $data as $key => $value ) {
			$new_cache[ $key ] = array(
				'data' => $value,
				'salt' => $salt,
			);
		}
		return wp_cache_set_multiple( $new_cache, $group, $expire );
	}
endif;

if ( ! function_exists( 'wp_cache_get_valid' ) ) :
	/**
	 * Retrieves the cache contents by key and group, only if they are valid.
	 *
	 * A validating wrapper around wp_cache_get(). The cache contents are returned
	 * only when they match an expected `$type` and, if a `$callback` is provided,
	 * the callback returns `true` for them. Otherwise `false` is returned, which
	 * callers should treat as a cache miss: regenerate the value and store it
	 * with wp_cache_set().
	 *
	 * The `$valid` parameter, passed by reference, is `true` only when the
	 * contents were found and passed validation. Unlike the `$found` parameter
	 * of wp_cache_get(), it is `false` for contents that were found but failed
	 * validation. When the cached value itself can be `false` (the 'bool' type),
	 * the return value is ambiguous, so check `$valid` instead. When a
	 * not-found sentinel such as `false` or `-1` is cached alongside regular
	 * contents, include the sentinel's type in `$type`.
	 *
	 * The callback receives one argument, the cache contents, and must return a
	 * boolean. It may assume the contents matched an expected type, and must
	 * tolerate any contents of those types without throwing. It must not access
	 * the object cache or have side effects.
	 *
	 * Invalid contents are left in the cache by default, to be overwritten when
	 * the caller regenerates the value. Passing `true` for `$delete_invalid`
	 * deletes them instead, for callers that do not regenerate immediately.
	 * Nothing is deleted on a plain cache miss or on incorrect usage of this
	 * function.
	 *
	 * This is not a security boundary. A persistent cache backend unserializes
	 * contents before this check runs, and passing validation does not make the
	 * contents trustworthy.
	 *
	 * @link https://core.trac.wordpress.org/ticket/66005
	 *
	 * @since 7.2.0
	 *
	 * @param int|string      $key            The key under which the cache contents are stored.
	 * @param string          $group          Where the cache contents are grouped.
	 * @param string|string[] $type           The expected type of the cache contents, or a list of
	 *                                        types of which the contents must match at least one.
	 *                                        Accepts 'array', 'object', 'string', 'int', 'integer',
	 *                                        'float', 'double', 'bool', 'boolean', or 'numeric'.
	 * @param callable|string $callback       Optional. Callback to further validate the cache contents
	 *                                        beyond the type check. Default empty string (no callback).
	 * @param bool            $force          Optional. Whether to force an update of the local cache
	 *                                        from the persistent cache. Default false.
	 * @param bool            $delete_invalid Optional. Whether to delete the cache contents when they
	 *                                        are found but fail validation. Default false.
	 * @param bool|null       $valid          Optional. Whether the contents were found and passed
	 *                                        validation (passed by reference). Disambiguates a return
	 *                                        of false, a storable value. Default null.
	 * @param-out bool $valid
	 * @return mixed|false The cache contents on success, false if the contents are missing or invalid.
	 */
	function wp_cache_get_valid( $key, $group, $type, $callback = '', bool $force = false, bool $delete_invalid = false, ?bool &$valid = null ) {
		$valid = false;

		$type_checks = array(
			'array'   => 'is_array',
			'object'  => 'is_object',
			'string'  => 'is_string',
			'int'     => 'is_int',
			'integer' => 'is_int',
			'float'   => 'is_float',
			'double'  => 'is_float',
			'bool'    => 'is_bool',
			'boolean' => 'is_bool',
			'numeric' => 'is_numeric',
		);

		if ( ! is_array( $type ) ) {
			$type = array( $type );
		}

		if ( array() === $type ) {
			if ( ! function_exists( '__' ) ) {
				wp_load_translations_early();
			}

			_doing_it_wrong(
				__FUNCTION__,
				__( 'At least one expected type must be provided.' ),
				'7.2.0'
			);

			return false;
		}

		foreach ( $type as $expected_type ) {
			if ( ! is_string( $expected_type ) || ! isset( $type_checks[ $expected_type ] ) ) {
				if ( ! function_exists( '__' ) ) {
					wp_load_translations_early();
				}

				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: %s: List of valid cache content types. */
						__( 'Each expected type must be one of: %s.' ),
						"'" . implode( "', '", array_keys( $type_checks ) ) . "'"
					),
					'7.2.0'
				);

				return false;
			}
		}

		if ( '' !== $callback && ! is_callable( $callback ) ) {
			if ( ! function_exists( '__' ) ) {
				wp_load_translations_early();
			}

			_doing_it_wrong(
				__FUNCTION__,
				__( 'The validation callback is not callable.' ),
				'7.2.0'
			);

			return false;
		}

		$found = false;
		$value = wp_cache_get( $key, $group, $force, $found );

		if ( ! $found ) {
			return false;
		}

		$type_matched = false;

		foreach ( $type as $expected_type ) {
			if ( call_user_func( $type_checks[ $expected_type ], $value ) ) {
				$type_matched = true;
				break;
			}
		}

		if ( ! $type_matched ) {
			/**
			 * Fires when cache contents were found but failed validation.
			 *
			 * Unless deletion was requested via the `$delete_invalid` parameter
			 * of wp_cache_get_valid(), the invalid contents remain in the cache
			 * until the caller overwrites them with wp_cache_set().
			 *
			 * @since 7.2.0
			 *
			 * @param int|string $key    The key under which the cache contents are stored.
			 * @param string     $group  Where the cache contents are grouped.
			 * @param mixed      $value  The invalid cache contents.
			 * @param string     $reason Why validation failed: 'type' if the contents were not
			 *                           of an expected type, 'callback' if the validation
			 *                           callback did not return true.
			 */
			do_action( 'wp_cache_get_valid_failed', $key, $group, $value, 'type' );

			if ( $delete_invalid ) {
				wp_cache_delete( $key, $group );
			}

			return false;
		}

		if ( '' !== $callback ) {
			$callback_result = call_user_func( $callback, $value );

			if ( ! is_bool( $callback_result ) ) {
				if ( ! function_exists( '__' ) ) {
					wp_load_translations_early();
				}

				_doing_it_wrong(
					__FUNCTION__,
					__( 'The validation callback must return a boolean.' ),
					'7.2.0'
				);

				return false;
			}

			if ( true !== $callback_result ) {
				/** This action is documented in wp-includes/cache-compat.php */
				do_action( 'wp_cache_get_valid_failed', $key, $group, $value, 'callback' );

				if ( $delete_invalid ) {
					wp_cache_delete( $key, $group );
				}

				return false;
			}
		}

		$valid = true;

		return $value;
	}
endif;

if ( ! function_exists( 'wp_cache_switch_to_blog' ) ) :
	/**
	 * Used when switch_to_blog() and restore_current_blog() are called, but
	 * only when a persistent object cache drop-in plugin has omitted the
	 * wp_cache_switch_to_blog() function that was introduced in 3.5.0.
	 *
	 * @link https://core.trac.wordpress.org/ticket/23290
	 *
	 * @since 7.0.0
	 *
	 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
	 *
	 * @param int $blog_id Site ID.
	 */
	function wp_cache_switch_to_blog( $blog_id ): void {
		global $wp_object_cache;

		// Attempt to use the drop-in object cache method if it exists.
		if ( is_object( $wp_object_cache ) && method_exists( $wp_object_cache, 'switch_to_blog' ) ) {
			$wp_object_cache->switch_to_blog( $blog_id );
			return;
		}

		/*
		 * Perform a fallback blog switch, which will reinitialize the caches
		 * for the new blog ID.
		 */
		wp_cache_switch_to_blog_fallback();
	}
endif;
