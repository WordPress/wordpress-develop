<?php
/**
 * Collects the types of the values WordPress functions are given and return.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Counts the types of the arguments and return values of functions, for the types report.
 *
 * It counts them with two catch-all hooks of the gameshark extension, with the
 * target `*`: one before every call to a user-space function in the given
 * directories, for its arguments, and one after, for its return value. The
 * hooks are plain closures, not invariants: they run on every call, and report
 * nothing but counts.
 *
 * Each value is counted by its type, separately for calls from WordPress and
 * calls from tests:
 *
 * - `true` and `false` are counted apart; the report shows `bool` once both are seen.
 * - Up to STRING_LITERALS distinct strings shorter than STRING_LENGTH bytes are
 *   counted as literals, such as `'post'`. A longer string, or one more distinct
 *   string, widens the slot to `string`.
 * - An array is `array{}` when empty, a `list<…>` of the types of its first
 *   LIST_SAMPLE elements, or a record of its keys and the types of their values.
 *   Arrays with more than RECORD_KEYS elements, or a slot whose records have had
 *   more than RECORD_KEYS distinct keys, are plain `array`.
 * - A stdClass object is a record of its properties, like an array. Other objects
 *   are their class.
 *
 * @see WP_Invariant_Types_Report
 */
final class WP_Invariant_Types {

	/**
	 * Most distinct strings a slot counts as literals.
	 */
	const STRING_LITERALS = 10;

	/**
	 * Strings this long, or longer, are not literals.
	 */
	const STRING_LENGTH = 32;

	/**
	 * Most keys an array or stdClass record has, and most distinct keys a slot's records have.
	 */
	const RECORD_KEYS = 64;

	/**
	 * Most distinct types a record's field has before it is `mixed`.
	 */
	const FIELD_TYPES = 8;

	/**
	 * Elements of a list whose types are counted.
	 */
	const LIST_SAMPLE = 16;

	/**
	 * Index of the counts of calls from WordPress, and from tests.
	 */
	const CORE = 0;
	const TEST = 1;

	/**
	 * What was collected, by target: `calls`, `arguments` (count of arguments => counts) and `slots`.
	 *
	 * @var array[]
	 */
	private static $profile = array();

	/**
	 * The functions and methods which are hooked, from WP_Invariant::functions_in().
	 *
	 * @var array[]
	 */
	private static $targets = array();

	/**
	 * Directories which hold test code, each ending in a slash.
	 *
	 * @var string[]
	 */
	private static $test_directories = array();

	/**
	 * Whether each calling file holds test code.
	 *
	 * @var bool[]
	 */
	private static $test_files = array();

	/**
	 * Returns the functions and methods to profile, from a cache when the files have not changed.
	 *
	 * @param string[] $directories Directories to profile.
	 * @param string[] $exclude     Directories and files inside them to leave out.
	 * @param string   $cache       File to cache them in.
	 * @return array[] As WP_Invariant::functions_in() returns them.
	 */
	public static function targets( array $directories, array $exclude, $cache ) {
		$key = array( $directories, $exclude );
		foreach ( $directories as $directory ) {
			if ( ! is_dir( $directory ) ) {
				continue;
			}
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
				if ( 'php' === $file->getExtension() ) {
					$key[] = $file->getPathname() . ':' . $file->getMTime();
				}
			}
		}
		$key = md5( serialize( $key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Only to compare.

		$cached = is_readable( $cache ) ? unserialize( file_get_contents( $cache ), array( 'allowed_classes' => false ) ) : null; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- A cache this class wrote.
		if ( is_array( $cached ) && isset( $cached['key'], $cached['targets'] ) && $cached['key'] === $key ) {
			return $cached['targets'];
		}

		$targets = WP_Invariant::functions_in( $directories, $exclude );
		if ( is_dir( dirname( $cache ) ) || mkdir( dirname( $cache ), 0777, true ) ) {
			$cached = array(
				'key'     => $key,
				'targets' => $targets,
			);
			file_put_contents( $cache, serialize( $cached ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- A cache for this class.
		}

		return $targets;
	}

	/**
	 * Returns gameshark hook specs which count the types of the arguments and return values of the targets.
	 *
	 * They are two catch-all specs, with the target `*`, which the extension runs
	 * for every user-space function declared in the given directories, and which
	 * count the calls to the targets among them.
	 *
	 * @param array[]  $targets          As WP_Invariant::functions_in() returns them.
	 * @param string[] $directories      Directories the targets were found in.
	 * @param string[] $test_directories Directories which hold test code.
	 * @return array[] Gameshark specs, with ids starting `types/`.
	 */
	public static function specs( array $targets, array $directories, array $test_directories ) {
		self::$test_directories = array_map(
			static function ( $directory ) {
				$real = realpath( $directory );
				return rtrim( false === $real ? $directory : $real, '/' ) . '/';
			},
			$test_directories
		);

		// A function declared in two files, as some fallbacks are, is still one target. The extension names it as declared.
		foreach ( $targets as $target ) {
			if ( ! isset( self::$targets[ strtolower( $target['target'] ) ] ) ) {
				self::$targets[ strtolower( $target['target'] ) ] = $target;
			}
		}

		// PHP may know a file by the path it was included with, or by its real path.
		$files = array();
		foreach ( $directories as $directory ) {
			$files[] = rtrim( $directory, '/' ) . '/';
			$real    = realpath( $directory );
			if ( false !== $real ) {
				$files[] = rtrim( $real, '/' ) . '/';
			}
		}
		$files = array_values( array_unique( $files ) );

		return array(
			array(
				'id'     => 'types/arguments',
				'target' => '*',
				'when'   => 'pre',
				'files'  => $files,
				'hook'   => static function ( $callee, array $args ) {
					$key = strtolower( $callee );
					if ( isset( self::$targets[ $key ] ) ) {
						self::count_arguments( self::$targets[ $key ]['target'], $args, self::caller_kind() );
					}
				},
			),
			array(
				'id'     => 'types/returns',
				'target' => '*',
				'when'   => 'post',
				'files'  => $files,
				'hook'   => static function ( $callee, $return_value ) {
					$key = strtolower( $callee );
					if ( isset( self::$targets[ $key ] ) ) {
						self::count_value( self::$targets[ $key ]['target'], 'return', $return_value, self::caller_kind() );
					}
				},
			),
		);
	}

	/**
	 * Returns whether the hooked call came from test code or not.
	 *
	 * The call came from the first frame with a file at or after the target's own
	 * frame: when PHP called the target, as with call_user_func(), the frame which
	 * called that function.
	 *
	 * @internal
	 *
	 * @return int self::TEST or self::CORE.
	 */
	public static function caller_kind() {
		// This method, the hook's closure, then the target.
		$frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 );
		$count  = count( $frames );
		for ( $i = 2; $i < $count; $i++ ) {
			if ( isset( $frames[ $i ]['file'] ) ) {
				$file = $frames[ $i ]['file'];
				if ( ! isset( self::$test_files[ $file ] ) ) {
					self::$test_files[ $file ] = false;
					foreach ( self::$test_directories as $directory ) {
						if ( 0 === strpos( $file, $directory ) ) {
							self::$test_files[ $file ] = true;
						}
					}
				}

				return self::$test_files[ $file ] ? self::TEST : self::CORE;
			}
		}

		return self::CORE;
	}

	/**
	 * Counts a call and the types of its arguments.
	 *
	 * @internal
	 *
	 * @param string $target    The hooked function or method.
	 * @param array  $arguments The arguments, in order.
	 * @param int    $kind      self::CORE or self::TEST.
	 */
	public static function count_arguments( $target, array $arguments, $kind ) {
		if ( ! isset( self::$profile[ $target ] ) ) {
			self::$profile[ $target ] = array(
				'calls'     => array( 0, 0 ),
				'arguments' => array(),
				'slots'     => array(),
			);
		}

		$profile = &self::$profile[ $target ];
		++$profile['calls'][ $kind ];

		$count = count( $arguments );
		if ( ! isset( $profile['arguments'][ $count ] ) ) {
			$profile['arguments'][ $count ] = array( 0, 0 );
		}
		++$profile['arguments'][ $count ][ $kind ];

		foreach ( array_values( $arguments ) as $position => $argument ) {
			self::count_value( $target, $position, $argument, $kind );
		}
	}

	/**
	 * Counts the type of one value: an argument, or a return value.
	 *
	 * @internal
	 *
	 * @param string     $target The hooked function or method.
	 * @param int|string $slot   The argument's position, or `return`.
	 * @param mixed      $value  The value.
	 * @param int        $kind   self::CORE or self::TEST.
	 */
	public static function count_value( $target, $slot, $value, $kind ) {
		if ( ! isset( self::$profile[ $target ] ) ) {
			self::$profile[ $target ] = array(
				'calls'     => array( 0, 0 ),
				'arguments' => array(),
				'slots'     => array(),
			);
		}
		if ( ! isset( self::$profile[ $target ]['slots'][ $slot ] ) ) {
			self::$profile[ $target ]['slots'][ $slot ] = self::empty_slot();
		}

		self::add_to_slot( self::$profile[ $target ]['slots'][ $slot ], $value, $kind );
	}

	/**
	 * Returns everything collected, by target.
	 *
	 * @return array[]
	 */
	public static function profile() {
		return self::$profile;
	}

	/**
	 * Returns the functions and methods which are hooked.
	 *
	 * @return array[] As WP_Invariant::functions_in() returns them.
	 */
	public static function hooked() {
		return array_values( self::$targets );
	}

	/**
	 * Tells processes which PHPUnit starts for tests in separate processes where to leave what they collect.
	 *
	 * Child processes inherit the environment, and save_for_parent() reads it.
	 *
	 * @param string $directory Directory for what they collect.
	 */
	public static function collect_from_processes( $directory ) {
		putenv( 'WP_INVARIANTS_TYPES_PARENT=' . getmypid() );
		putenv( "WP_INVARIANTS_TYPES_PROCESSES={$directory}" );
	}

	/**
	 * Saves what a separate process collected, for the PHPUnit process which started it to merge.
	 *
	 * It runs at shutdown, and does nothing in the PHPUnit process itself.
	 *
	 * @internal
	 */
	public static function save_for_parent() {
		$parent    = getenv( 'WP_INVARIANTS_TYPES_PARENT' );
		$directory = getenv( 'WP_INVARIANTS_TYPES_PROCESSES' );
		if ( ! $parent || ! $directory || getmypid() === (int) $parent || array() === self::$profile ) {
			return;
		}

		if ( is_dir( $directory ) || mkdir( $directory, 0777, true ) ) {
			file_put_contents( "{$directory}/" . getmypid() . '.serialized', serialize( self::$profile ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- For WP_Invariant_Types_Report to read.
		}
	}

	/**
	 * Sets what was collected, for the self-tests.
	 *
	 * @param array[] $profile By target.
	 */
	public static function set_profile( array $profile ) {
		self::$profile = $profile;
	}

	/**
	 * Returns a slot which has counted nothing yet.
	 *
	 * @return array
	 */
	public static function empty_slot() {
		return array(
			'types'   => array(),
			'strings' => array(
				'widened' => false,
				'total'   => array( 0, 0 ),
				'values'  => array(),
			),
			'records' => array(),
		);
	}

	/**
	 * Counts a value in a slot.
	 *
	 * @param array $slot  The slot, from empty_slot().
	 * @param mixed $value The value.
	 * @param int   $kind  self::CORE or self::TEST.
	 */
	public static function add_to_slot( array &$slot, $value, $kind ) {
		if ( is_string( $value ) ) {
			$strings = &$slot['strings'];
			++$strings['total'][ $kind ];
			if ( $strings['widened'] ) {
				return;
			}

			if ( isset( $strings['values'][ $value ] ) ) {
				++$strings['values'][ $value ][ $kind ];
			} elseif ( strlen( $value ) < self::STRING_LENGTH && count( $strings['values'] ) < self::STRING_LITERALS ) {
				$strings['values'][ $value ]          = array( 0, 0 );
				$strings['values'][ $value ][ $kind ] = 1;
			} else {
				$strings['widened'] = true;
				$strings['values']  = array();
			}
			return;
		}

		$record = null;
		if ( is_array( $value ) ) {
			$count = count( $value );
			if ( 0 === $count ) {
				$type = 'array{}';
			} elseif ( array_is_list( $value ) ) {
				$type = self::list_type( $value );
			} elseif ( $count > self::RECORD_KEYS ) {
				$type = 'array';
			} else {
				$type   = 'array';
				$record = $value;
			}
		} elseif ( $value instanceof stdClass ) {
			$type   = 'stdClass';
			$record = get_object_vars( $value );
			if ( count( $record ) > self::RECORD_KEYS ) {
				$record = null;
			}
		} else {
			$type = self::shallow_type( $value );
		}

		if ( null !== $record && self::add_to_record( $slot, $type, $record ) ) {
			$type = "{$type}{...}";
		}

		if ( ! isset( $slot['types'][ $type ] ) ) {
			$slot['types'][ $type ] = array( 0, 0 );
		}
		++$slot['types'][ $type ][ $kind ];
	}

	/**
	 * Counts the keys of an array or stdClass in a slot's record of them.
	 *
	 * Once the slot's records have had more than RECORD_KEYS distinct keys, the
	 * record is dropped, and the values are counted as plain arrays or objects.
	 *
	 * @param array  $slot   The slot.
	 * @param string $type   `array` or `stdClass`.
	 * @param array  $fields The value's keys and values.
	 * @return bool Whether the value was counted in the record.
	 */
	private static function add_to_record( array &$slot, $type, array $fields ) {
		if ( ! isset( $slot['records'][ $type ] ) ) {
			$slot['records'][ $type ] = array(
				'widened' => false,
				'count'   => 0,
				'keys'    => array(),
			);
		}

		$record = &$slot['records'][ $type ];
		if ( $record['widened'] ) {
			return false;
		}
		if ( count( $record['keys'] + $fields ) > self::RECORD_KEYS ) {
			$record['widened'] = true;
			$record['keys']    = array();

			// What the record counted is counted as plain values from now on.
			$recorded = isset( $slot['types'][ "{$type}{...}" ] ) ? $slot['types'][ "{$type}{...}" ] : array( 0, 0 );
			unset( $slot['types'][ "{$type}{...}" ] );
			$slot['types'][ $type ] = isset( $slot['types'][ $type ] ) ? array( $slot['types'][ $type ][0] + $recorded[0], $slot['types'][ $type ][1] + $recorded[1] ) : $recorded;
			return false;
		}

		++$record['count'];
		foreach ( $fields as $key => $field ) {
			if ( ! isset( $record['keys'][ $key ] ) ) {
				$record['keys'][ $key ] = array(
					'present' => 0,
					'types'   => array(),
				);
			}

			$entry = &$record['keys'][ $key ];
			++$entry['present'];
			if ( isset( $entry['types']['mixed'] ) ) {
				continue;
			}

			$field_type                    = self::shallow_type( $field );
			$entry['types'][ $field_type ] = isset( $entry['types'][ $field_type ] ) ? $entry['types'][ $field_type ] + 1 : 1;
			if ( count( $entry['types'] ) > self::FIELD_TYPES ) {
				$entry['types'] = array( 'mixed' => array_sum( $entry['types'] ) );
			}
		}

		return true;
	}

	/**
	 * Returns the type of a list, from the types of its first elements.
	 *
	 * @param array $elements A list with at least one element.
	 * @return string Such as `list<int>` or `list<WP_Post|null>`.
	 */
	private static function list_type( array $elements ) {
		$types = array();
		foreach ( array_slice( $elements, 0, self::LIST_SAMPLE ) as $element ) {
			$types[ self::shallow_type( $element ) ] = true;
		}
		$types = self::join_types( array_keys( $types ) );

		return "list<{$types}>";
	}

	/**
	 * Returns the type of a value without looking inside it, except for whether an array is empty or a list.
	 *
	 * @param mixed $value Any value.
	 * @return string
	 */
	public static function shallow_type( $value ) {
		if ( null === $value ) {
			return 'null';
		}
		if ( true === $value ) {
			return 'true';
		}
		if ( false === $value ) {
			return 'false';
		}
		if ( is_int( $value ) ) {
			return 'int';
		}
		if ( is_float( $value ) ) {
			return 'float';
		}
		if ( is_string( $value ) ) {
			return 'string';
		}
		if ( is_array( $value ) ) {
			if ( array() === $value ) {
				return 'array{}';
			}
			return array_is_list( $value ) ? 'list' : 'array';
		}
		if ( is_object( $value ) ) {
			return get_class( $value );
		}

		return 'resource';
	}

	/**
	 * Joins types into a union, with `true` and `false` as `bool`.
	 *
	 * @param string[] $types Types.
	 * @return string
	 */
	public static function join_types( array $types ) {
		if ( in_array( 'true', $types, true ) && in_array( 'false', $types, true ) ) {
			$types = array_values( array_diff( $types, array( 'true', 'false' ) ) );
			array_unshift( $types, 'bool' );
		}

		return implode( '|', $types );
	}

	/**
	 * Merges what another process collected into what this one collected, by the same rules.
	 *
	 * @param array[] $into  By target.
	 * @param array[] $other By target.
	 * @return array[] By target.
	 */
	public static function merge_profiles( array $into, array $other ) {
		foreach ( $other as $target => $profile ) {
			if ( ! isset( $into[ $target ] ) ) {
				$into[ $target ] = $profile;
				continue;
			}

			$merged          = &$into[ $target ];
			$merged['calls'] = self::add_counts( $merged['calls'], $profile['calls'] );
			foreach ( $profile['arguments'] as $count => $counts ) {
				$merged['arguments'][ $count ] = isset( $merged['arguments'][ $count ] ) ? self::add_counts( $merged['arguments'][ $count ], $counts ) : $counts;
			}
			foreach ( $profile['slots'] as $slot => $counted ) {
				$merged['slots'][ $slot ] = isset( $merged['slots'][ $slot ] ) ? self::merge_slots( $merged['slots'][ $slot ], $counted ) : $counted;
			}
			unset( $merged );
		}

		return $into;
	}

	/**
	 * Merges two slots, by the rules which counted them.
	 *
	 * @param array $into  A slot.
	 * @param array $other Another slot.
	 * @return array
	 */
	public static function merge_slots( array $into, array $other ) {
		foreach ( $other['types'] as $type => $counts ) {
			$into['types'][ $type ] = isset( $into['types'][ $type ] ) ? self::add_counts( $into['types'][ $type ], $counts ) : $counts;
		}

		$strings          = &$into['strings'];
		$strings['total'] = self::add_counts( $strings['total'], $other['strings']['total'] );
		if ( ! $strings['widened'] && ! $other['strings']['widened'] ) {
			foreach ( $other['strings']['values'] as $value => $counts ) {
				$strings['values'][ $value ] = isset( $strings['values'][ $value ] ) ? self::add_counts( $strings['values'][ $value ], $counts ) : $counts;
			}
		}
		if ( $other['strings']['widened'] || count( $strings['values'] ) > self::STRING_LITERALS ) {
			$strings['widened'] = true;
		}
		if ( $strings['widened'] ) {
			$strings['values'] = array();
		}
		unset( $strings );

		foreach ( $other['records'] as $type => $record ) {
			if ( ! isset( $into['records'][ $type ] ) ) {
				$into['records'][ $type ] = $record;
				continue;
			}

			$merged = &$into['records'][ $type ];
			if ( ! $merged['widened'] && ! $record['widened'] ) {
				$merged['count'] += $record['count'];
				foreach ( $record['keys'] as $key => $entry ) {
					if ( ! isset( $merged['keys'][ $key ] ) ) {
						$merged['keys'][ $key ] = $entry;
						continue;
					}
					$merged['keys'][ $key ]['present'] += $entry['present'];
					foreach ( $entry['types'] as $field_type => $count ) {
						$merged['keys'][ $key ]['types'][ $field_type ] = ( isset( $merged['keys'][ $key ]['types'][ $field_type ] ) ? $merged['keys'][ $key ]['types'][ $field_type ] : 0 ) + $count;
					}
					if ( isset( $merged['keys'][ $key ]['types']['mixed'] ) || count( $merged['keys'][ $key ]['types'] ) > self::FIELD_TYPES ) {
						$merged['keys'][ $key ]['types'] = array( 'mixed' => array_sum( $merged['keys'][ $key ]['types'] ) );
					}
				}
			}

			if ( $record['widened'] || count( $merged['keys'] ) > self::RECORD_KEYS ) {
				$merged['widened'] = true;
			}
			if ( $merged['widened'] ) {
				$merged['keys'] = array();

				// The records of both are counted as plain values.
				$recorded = isset( $into['types'][ "{$type}{...}" ] ) ? $into['types'][ "{$type}{...}" ] : array( 0, 0 );
				unset( $into['types'][ "{$type}{...}" ] );
				$into['types'][ $type ] = isset( $into['types'][ $type ] ) ? self::add_counts( $into['types'][ $type ], $recorded ) : $recorded;
			}
			unset( $merged );
		}

		return $into;
	}

	/**
	 * Adds two pairs of counts, from WordPress and from tests.
	 *
	 * @param int[] $a Counts.
	 * @param int[] $b Counts.
	 * @return int[]
	 */
	private static function add_counts( array $a, array $b ) {
		return array( $a[0] + $b[0], $a[1] + $b[1] );
	}
}
