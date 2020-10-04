<?php
/**
 * Plugin API: WP_Hook class
 *
 * @package WordPress
 * @subpackage Plugin
 * @since 4.7.0
 */

/**
 * Core class used to implement action and filter hook functionality.
 *
 * @since 4.7.0
 *
 * @see Iterator
 * @see ArrayAccess
 */
final class WP_Hook implements Iterator, ArrayAccess {

	/**
	 * Hook callbacks.
	 *
	 * @since 4.7.0
	 * @var array
	 */
	public $callbacks = array();

	/**
	 * The priority keys of actively running iterations of a hook.
	 *
	 * @since 4.7.0
	 * @var array
	 */
	private $iterations = array();

	/**
	 * The current priority of actively running iterations of a hook.
	 *
	 * @since 4.7.0
	 * @var array
	 */
	private $current_priority = array();

	/**
	 * Number of levels this hook can be recursively called.
	 *
	 * @since 4.7.0
	 * @var int
	 */
	private $nesting_level = 0;

	/**
	 * Flag for if we're current doing an action, rather than a filter.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	private $doing_action = false;

	/**
	 * Hooks a function or method to a specific filter action.
	 *
	 * @param string   $tag             The name of the filter to hook the $function_to_add callback to.
	 * @param callable $function_to_add The callback to be run when the filter is applied.
	 * @param int      $priority        The order in which the functions associated with a particular action
	 *                                  are executed. Lower numbers correspond with earlier execution,
	 *                                  and functions with the same priority are executed in the order
	 *                                  in which they were added to the action.
	 * @param int      $accepted_args   The number of arguments the function accepts.
	 * @param string   $callback_id     An unique ID for the callback.
	 *                                  If provided can be used to check or remove the hook in place
	 *                                  of the function itself.
	 *                                  Used **only** if $function_to_add is or contain an object instance,
	 *                                  and that includes anonymous functions.
	 *
	 * @since 4.7.0
	 * @since 5.6.0 Added $callback_id parameter
	 *
	 */
	public function add_filter( $tag, $function_to_add, $priority, $accepted_args, $callback_id = '' ) {
		list ( $function_key, $object_hash ) = _wp_filter_build_callback_key_and_hash( $function_to_add );

		$can_doing_it_wrong = function_exists( '_doing_it_wrong' );

		if ( ! $function_key ) {
			if ( $can_doing_it_wrong ) {
				_doing_it_wrong(
					'WP_Hook::add_filter',
					// translators: 1: hook name
					sprintf( 'Invalid hook callback for %1$s.', $tag ),
					'5.6'
				);
			}

			return;
		}

		// If the callback doesn't contain objects user-provided custom ids are not supported.
		if ( ! $object_hash && $callback_id ) {
			if ( $can_doing_it_wrong ) {
				_doing_it_wrong(
					'WP_Hook::add_filter',
					'Custom hook callback ids are ignored if the callback does not contain object instances.',
					'5.6'
				);
			}

			$callback_id = '';
		}

		if ( $callback_id ) {
			if ( false !== strpos( $callback_id, '##' ) ) {
				// We use the presence of '##' to distinguish generated callback ids.
				if ( $can_doing_it_wrong ) {
					_doing_it_wrong(
						'WP_Hook::add_filter',
						'Custom hook callback identifiers can\'t contain "##".',
						'5.6'
					);
				}
			} else {
				// If the callback contains an object, and the user provided a custom id, let's use it.
				// Note: using the provided custom id will be the *only* way to remove/check the filter.
				$function_key = $callback_id;
			}
		}

		$priority_existed = isset( $this->callbacks[ $priority ] );

		$this->callbacks[ $priority ][ $function_key ] = array(
			'function'      => $function_to_add,
			'accepted_args' => $accepted_args,
			'object_hash'   => $object_hash,
		);

		// If we're adding a new priority to the list, put them back in sorted order.
		if ( ! $priority_existed && count( $this->callbacks ) > 1 ) {
			ksort( $this->callbacks, SORT_NUMERIC );
		}

		if ( $this->nesting_level > 0 ) {
			$this->resort_active_iterations( $priority, $priority_existed );
		}
	}

	/**
	 * Handles resetting callback priority keys mid-iteration.
	 *
	 * @since 4.7.0
	 *
	 * @param bool|int $new_priority     Optional. The priority of the new filter being added. Default false,
	 *                                   for no priority being added.
	 * @param bool     $priority_existed Optional. Flag for whether the priority already existed before the new
	 *                                   filter was added. Default false.
	 */
	private function resort_active_iterations( $new_priority = false, $priority_existed = false ) {
		$new_priorities = array_keys( $this->callbacks );

		// If there are no remaining hooks, clear out all running iterations.
		if ( ! $new_priorities ) {
			foreach ( $this->iterations as $index => $iteration ) {
				$this->iterations[ $index ] = $new_priorities;
			}
			return;
		}

		$min = min( $new_priorities );
		foreach ( $this->iterations as $index => &$iteration ) {
			$current = current( $iteration );
			// If we're already at the end of this iteration, just leave the array pointer where it is.
			if ( false === $current ) {
				continue;
			}

			$iteration = $new_priorities;

			if ( $current < $min ) {
				array_unshift( $iteration, $current );
				continue;
			}

			while ( current( $iteration ) < $current ) {
				if ( false === next( $iteration ) ) {
					break;
				}
			}

			// If we have a new priority that didn't exist, but ::apply_filters() or ::do_action() thinks it's the current priority...
			if ( $new_priority === $this->current_priority[ $index ] && ! $priority_existed ) {
				/*
				 * ...and the new priority is the same as what $this->iterations thinks is the previous
				 * priority, we need to move back to it.
				 */

				if ( false === current( $iteration ) ) {
					// If we've already moved off the end of the array, go back to the last element.
					$prev = end( $iteration );
				} else {
					// Otherwise, just go back to the previous element.
					$prev = prev( $iteration );
				}
				if ( false === $prev ) {
					// Start of the array. Reset, and go about our day.
					reset( $iteration );
				} elseif ( $new_priority !== $prev ) {
					// Previous wasn't the same. Move forward again.
					next( $iteration );
				}
			}
		}
		unset( $iteration );
	}

	/**
	 * Unhooks a function or method from a specific filter action.
	 *
	 * @since 4.7.0
	 *
	 * @param string   $tag                The filter hook to which the function to be removed is hooked.
	 * @param callable $function_to_remove The callback to be removed from running when the filter is applied.
	 * @param int      $priority           The exact priority used when adding the original filter callback.
	 * @return bool Whether the callback existed before it was removed.
	 */
	public function remove_filter( $tag, $function_to_remove, $priority ) {
		list ( $function_key, $object_hash ) = _wp_filter_build_callback_key_and_hash( $function_to_remove );

		if ( ! $function_key || ! isset( $this->callbacks[ $priority ] ) ) {
			return false;
		}

		$callbacks = $this->callbacks[ $priority ];

		// Back compat: support passing spl_object_hash + method (or just hash for closures)
		list( $key_by_legacy, $id_by_legacy ) = $this->find_callback_keys_by_legacy_id( $function_to_remove, $callbacks );
		if ( $key_by_legacy && $id_by_legacy ) {
			$function_key = $key_by_legacy;
			$function_to_remove = $id_by_legacy;
			$use_strict = true;
		}

		if ( ! isset( $callbacks[ $function_key ] ) ) {
			return false;
		}

		if ( ! isset( $use_strict ) ) {
			$use_strict = ! is_string( $function_to_remove ) || ( false !== strpos( $function_to_remove, '##' ) );
		}

		// When using strict check, that is when either an ID is passed as string including a '##'
		// or when an object-including callback is passed as-is, we not only check for the callback
		// id, but also for "object_hash" stored as part of the hook data array.
		if ( $object_hash && $use_strict && $this->callbacks[ $priority ][ $function_key ]['object_hash'] !== $object_hash ) {
			return false;
		}

		unset( $this->callbacks[ $priority ][ $function_key ] );
		if ( ! $this->callbacks[ $priority ] ) {
			unset( $this->callbacks[ $priority ] );
			if ( $this->nesting_level > 0 ) {
				$this->resort_active_iterations();
			}
		}

		return true;
	}

	/**
	 * Checks if a specific action has been registered for this hook.
	 *
	 * @since 4.7.0
	 *
	 * @param string        $tag               Optional. The name of the filter hook. Default empty.
	 * @param callable|bool $function_to_check Optional. The callback to check for. Default false.
	 * @return bool|int The priority of that hook is returned, or false if the function is not attached.
	 */
	public function has_filter( $tag = '', $function_to_check = false ) {
		if ( false === $function_to_check ) {
			return $this->has_filters();
		}

		list ( $function_key, $object_hash ) = _wp_filter_build_callback_key_and_hash( $function_to_check );
		if ( ! $function_key ) {
			return false;
		}

		$built_key = $function_key;
		$built_hash = $object_hash;

		// When using strict check, that is when either an ID is passed as string including a '##'
		// or when an object-including callback is passed as-is, we not only check for the callback id,
		// but also for "object_hash" stored as part of the hook data array.
		$use_strict = ! is_string( $function_to_check ) || ( false !== strpos( $function_to_check, '##' ) );
		$orig_use_strict = $use_strict;

		foreach ( $this->callbacks as $priority => $callbacks ) {
			// Back compat: support passing spl_object_hash + method (or just spl_object_hash for closures)
			list( $key_by_legacy, $id_by_legacy, $hash_by_legacy ) = $this->find_callback_keys_by_legacy_id( $function_to_check, $callbacks );
			if ( $key_by_legacy && $id_by_legacy && $hash_by_legacy ) {
				$function_key = $key_by_legacy;
				$object_hash = $hash_by_legacy;
				$use_strict = true;
			}

			// Return if a callback with given key is found and we don't need to check hash, or the hash matches.
			if ( isset( $callbacks[ $function_key ] ) &&
			     (
			     	! ( $use_strict && $object_hash )
			        || $this->callbacks[ $priority ][ $function_key ]['object_hash'] === $object_hash
			     )
			) {
				return $priority;
			}

			// Restore in the case were replaced via find_callback_keys_by_legacy_id
			$function_key = $built_key;
			$object_hash = $built_hash;
			$use_strict = $orig_use_strict;
		}

		return false;
	}

	/**
	 * Checks if any callbacks have been registered for this hook.
	 *
	 * @since 4.7.0
	 *
	 * @return bool True if callbacks have been registered for the current hook, otherwise false.
	 */
	public function has_filters() {
		foreach ( $this->callbacks as $callbacks ) {
			if ( $callbacks ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Removes all callbacks from the current filter.
	 *
	 * @since 4.7.0
	 *
	 * @param int|bool $priority Optional. The priority number to remove. Default false.
	 */
	public function remove_all_filters( $priority = false ) {
		if ( ! $this->callbacks ) {
			return;
		}

		if ( false === $priority ) {
			$this->callbacks = array();
		} elseif ( isset( $this->callbacks[ $priority ] ) ) {
			unset( $this->callbacks[ $priority ] );
		}

		if ( $this->nesting_level > 0 ) {
			$this->resort_active_iterations();
		}
	}

	/**
	 * Calls the callback functions that have been added to a filter hook.
	 *
	 * @since 4.7.0
	 *
	 * @param mixed $value The value to filter.
	 * @param array $args  Additional parameters to pass to the callback functions.
	 *                     This array is expected to include $value at index 0.
	 * @return mixed The filtered value after all hooked functions are applied to it.
	 */
	public function apply_filters( $value, $args ) {
		if ( ! $this->callbacks ) {
			return $value;
		}

		$nesting_level = $this->nesting_level++;

		$this->iterations[ $nesting_level ] = array_keys( $this->callbacks );
		$num_args                           = count( $args );

		do {
			$this->current_priority[ $nesting_level ] = current( $this->iterations[ $nesting_level ] );
			$priority                                 = $this->current_priority[ $nesting_level ];

			foreach ( $this->callbacks[ $priority ] as $the_ ) {
				if ( ! $this->doing_action ) {
					$args[0] = $value;
				}

				// Avoid the array_slice() if possible.
				if ( 0 == $the_['accepted_args'] ) {
					$value = call_user_func( $the_['function'] );
				} elseif ( $the_['accepted_args'] >= $num_args ) {
					$value = call_user_func_array( $the_['function'], $args );
				} else {
					$value = call_user_func_array( $the_['function'], array_slice( $args, 0, (int) $the_['accepted_args'] ) );
				}
			}
		} while ( false !== next( $this->iterations[ $nesting_level ] ) );

		unset( $this->iterations[ $nesting_level ] );
		unset( $this->current_priority[ $nesting_level ] );

		$this->nesting_level--;

		return $value;
	}

	/**
	 * Calls the callback functions that have been added to an action hook.
	 *
	 * @since 4.7.0
	 *
	 * @param array $args Parameters to pass to the callback functions.
	 */
	public function do_action( $args ) {
		$this->doing_action = true;
		$this->apply_filters( '', $args );

		// If there are recursive calls to the current action, we haven't finished it until we get to the last one.
		if ( ! $this->nesting_level ) {
			$this->doing_action = false;
		}
	}

	/**
	 * Processes the functions hooked into the 'all' hook.
	 *
	 * @since 4.7.0
	 *
	 * @param array $args Arguments to pass to the hook callbacks. Passed by reference.
	 */
	public function do_all_hook( &$args ) {
		$nesting_level                      = $this->nesting_level++;
		$this->iterations[ $nesting_level ] = array_keys( $this->callbacks );

		do {
			$priority = current( $this->iterations[ $nesting_level ] );
			foreach ( $this->callbacks[ $priority ] as $the_ ) {
				call_user_func_array( $the_['function'], $args );
			}
		} while ( false !== next( $this->iterations[ $nesting_level ] ) );

		unset( $this->iterations[ $nesting_level ] );
		$this->nesting_level--;
	}

	/**
	 * Return the current priority level of the currently running iteration of the hook.
	 *
	 * @since 4.7.0
	 *
	 * @return int|false If the hook is running, return the current priority level. If it isn't running, return false.
	 */
	public function current_priority() {
		if ( false === current( $this->iterations ) ) {
			return false;
		}

		return current( current( $this->iterations ) );
	}

	/**
	 * Normalizes filters set up before WordPress has initialized to WP_Hook objects.
	 *
	 * @since 4.7.0
	 *
	 * @param array $filters Filters to normalize.
	 * @return WP_Hook[] Array of normalized filters.
	 */
	public static function build_preinitialized_hooks( $filters ) {
		/** @var WP_Hook[] $normalized */
		$normalized = array();

		foreach ( $filters as $tag => $callback_groups ) {
			if ( is_object( $callback_groups ) && $callback_groups instanceof WP_Hook ) {
				$normalized[ $tag ] = $callback_groups;
				continue;
			}
			$hook = new WP_Hook();

			// Loop through callback groups.
			foreach ( $callback_groups as $priority => $callbacks ) {

				// Loop through callbacks.
				foreach ( $callbacks as $cb ) {
					$hook->add_filter( $tag, $cb['function'], $priority, $cb['accepted_args'] );
				}
			}
			$normalized[ $tag ] = $hook;
		}
		return $normalized;
	}

	/**
	 * Determines whether an offset value exists.
	 *
	 * @since 4.7.0
	 *
	 * @link https://www.php.net/manual/en/arrayaccess.offsetexists.php
	 *
	 * @param mixed $offset An offset to check for.
	 * @return bool True if the offset exists, false otherwise.
	 */
	public function offsetExists( $offset ) {
		return isset( $this->callbacks[ $offset ] );
	}

	/**
	 * Retrieves a value at a specified offset.
	 *
	 * @since 4.7.0
	 *
	 * @link https://www.php.net/manual/en/arrayaccess.offsetget.php
	 *
	 * @param mixed $offset The offset to retrieve.
	 * @return mixed If set, the value at the specified offset, null otherwise.
	 */
	public function offsetGet( $offset ) {
		return isset( $this->callbacks[ $offset ] ) ? $this->callbacks[ $offset ] : null;
	}

	/**
	 * Sets a value at a specified offset.
	 *
	 * @since 4.7.0
	 *
	 * @link https://www.php.net/manual/en/arrayaccess.offsetset.php
	 *
	 * @param mixed $offset The offset to assign the value to.
	 * @param mixed $value The value to set.
	 */
	public function offsetSet( $offset, $value ) {
		if ( is_null( $offset ) ) {
			$this->callbacks[] = $value;
		} else {
			$this->callbacks[ $offset ] = $value;
		}
	}

	/**
	 * Unsets a specified offset.
	 *
	 * @since 4.7.0
	 *
	 * @link https://www.php.net/manual/en/arrayaccess.offsetunset.php
	 *
	 * @param mixed $offset The offset to unset.
	 */
	public function offsetUnset( $offset ) {
		unset( $this->callbacks[ $offset ] );
	}

	/**
	 * Returns the current element.
	 *
	 * @since 4.7.0
	 *
	 * @link https://www.php.net/manual/en/iterator.current.php
	 *
	 * @return array Of callbacks at current priority.
	 */
	public function current() {
		return current( $this->callbacks );
	}

	/**
	 * Moves forward to the next element.
	 *
	 * @since 4.7.0
	 *
	 * @link https://www.php.net/manual/en/iterator.next.php
	 *
	 * @return array Of callbacks at next priority.
	 */
	public function next() {
		return next( $this->callbacks );
	}

	/**
	 * Returns the key of the current element.
	 *
	 * @since 4.7.0
	 *
	 * @link https://www.php.net/manual/en/iterator.key.php
	 *
	 * @return mixed Returns current priority on success, or NULL on failure
	 */
	public function key() {
		return key( $this->callbacks );
	}

	/**
	 * Checks if current position is valid.
	 *
	 * @since 4.7.0
	 *
	 * @link https://www.php.net/manual/en/iterator.valid.php
	 *
	 * @return boolean
	 */
	public function valid() {
		return key( $this->callbacks ) !== null;
	}

	/**
	 * Rewinds the Iterator to the first element.
	 *
	 * @since 4.7.0
	 *
	 * @link https://www.php.net/manual/en/iterator.rewind.php
	 */
	public function rewind() {
		reset( $this->callbacks );
	}

	/**
	 * Find the function_key, function_id, and object hash given the "legacy" callback identifier
	 * made with spl_object_hash.
	 *
	 * @since 5.6.0
	 *
	 * @param string $legacy_id Legacy callback id made using spl_object_hash
	 * @param array $callbacks  Callbacks data where to search for the object hash
	 *
	 * @return array
	 */
	private function find_callback_keys_by_legacy_id( $legacy_id, $callbacks ) {

        if ( ! is_string ( $legacy_id ) || ! preg_match( '/^([a-f0-9]{32})(.+?)?$/', $legacy_id, $matches ) ) {
            return array( null, null, null );
        }

        $object_hash = $matches[1];
        $search_for = empty( $matches[2] ) ? array ( 'function()', 'class()', '->__invoke' ) : array ( '->' . $matches[2] );

		$function_id = null;
		$function_key = null;
		$function_hash = null;
		foreach ( $callbacks as $key => $data ) {
            if ( $data['object_hash'] !== $object_hash ) {
                continue;
            }

            foreach ( $search_for as $search_for_key ) {
                if ( false !== strpos( $key, $search_for_key ) ) {
                    $function_key = $key;
                    $function_id = $key . '##' . $object_hash;
                    $function_hash = $object_hash;
                    break;
                }
            }
		}

		return array( $function_key, $function_id, $function_hash );
	}
}
