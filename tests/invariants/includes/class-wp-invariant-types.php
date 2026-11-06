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
 * - Up to INT_LITERALS distinct ints are counted as literals, such as `0` or `-1`.
 *   One more distinct int widens the slot to `int`. Floats are always `float`.
 * - Up to STRING_LITERALS distinct strings shorter than STRING_LENGTH bytes are
 *   counted as literals, such as `'post'`. A longer string, or one more distinct
 *   string, widens the slot to `string`.
 * - Every int and string is also counted by its properties, such as whether an
 *   int is zero, or a string holds HTML, so that widened slots can be reported
 *   by them. See string_properties().
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
	 * Most distinct ints a slot counts as literals: the fifth makes them `int`.
	 */
	const INT_LITERALS = 4;

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
	 * Properties of a string, as bits. See string_properties().
	 */
	const STRING_EMPTY        = 1;
	const STRING_HTML         = 2;
	const STRING_REFERENCE    = 4;
	const STRING_SLASH        = 8;
	const STRING_UNSLASH      = 16;
	const STRING_URL          = 32;
	const STRING_PATH         = 64;
	const STRING_FILE         = 128;
	const STRING_INVALID_UTF8 = 256;
	const STRING_NUMERIC      = 512;

	/**
	 * Property of an int, as a bit: it is zero.
	 */
	const INT_ZERO = 1;

	/**
	 * The special schemes of WHATWG URLs, whose URLs have no opaque path.
	 */
	const SPECIAL_SCHEMES = array(
		'ftp'   => true,
		'file'  => true,
		'http'  => true,
		'https' => true,
		'ws'    => true,
		'wss'   => true,
	);

	/**
	 * Strings this long, or shorter, have their properties remembered.
	 */
	const REMEMBERED_LENGTH = 256;

	/**
	 * Most strings whose properties are remembered at once.
	 */
	const REMEMBERED_STRINGS = 8192;

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
	 * The properties of strings classified recently, by string.
	 *
	 * @var int[]
	 */
	private static $remembered = array();

	/**
	 * Whether each first segment of an absolute path is a directory at the root of the file system.
	 *
	 * @var bool[]
	 */
	private static $root_directories = array();

	/**
	 * Returns the functions and methods to profile, from a cache when the files have not changed.
	 *
	 * @param string[] $directories Directories to profile.
	 * @param string[] $exclude     Directories and files inside them to leave out.
	 * @param string   $cache       File to cache them in.
	 * @return array[] As WP_Invariant::functions_in() returns them.
	 */
	public static function targets( array $directories, array $exclude, $cache ) {
		$key = md5( serialize( array( $exclude, WP_Invariant::source_key( $directories ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Only to compare.

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
			'ints'    => self::empty_literals(),
			'strings' => self::empty_literals(),
			'records' => array(),
		);
	}

	/**
	 * Returns literals which have counted nothing yet.
	 *
	 * @return array `widened`, whether they are no longer counted apart; `total`, the counts of all
	 *               values; `values`, the counts of each value until widened; and `properties`, the
	 *               counts of all values by their properties, as bits.
	 */
	private static function empty_literals() {
		return array(
			'widened'    => false,
			'total'      => array( 0, 0 ),
			'values'     => array(),
			'properties' => array(),
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
		if ( is_int( $value ) ) {
			self::add_literal( $slot['ints'], $value, $kind, self::INT_LITERALS, true, 0 === $value ? self::INT_ZERO : 0 );
			return;
		}
		if ( is_string( $value ) ) {
			self::add_literal( $slot['strings'], $value, $kind, self::STRING_LITERALS, strlen( $value ) < self::STRING_LENGTH, self::string_properties( $value ) );
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
	 * Counts an int or string in a slot's literals of its type.
	 *
	 * @param array      $literals   The slot's literals, from empty_literals().
	 * @param int|string $value      The value.
	 * @param int        $kind       self::CORE or self::TEST.
	 * @param int        $limit      Most distinct values to count apart.
	 * @param bool       $fits       Whether the value may be a literal at all.
	 * @param int        $properties The value's properties, as bits.
	 */
	private static function add_literal( array &$literals, $value, $kind, $limit, $fits, $properties ) {
		++$literals['total'][ $kind ];
		if ( ! isset( $literals['properties'][ $properties ] ) ) {
			$literals['properties'][ $properties ] = array( 0, 0 );
		}
		++$literals['properties'][ $properties ][ $kind ];
		if ( $literals['widened'] ) {
			return;
		}

		if ( isset( $literals['values'][ $value ] ) ) {
			++$literals['values'][ $value ][ $kind ];
		} elseif ( $fits && count( $literals['values'] ) < $limit ) {
			$literals['values'][ $value ]          = array( 0, 0 );
			$literals['values'][ $value ][ $kind ] = 1;
		} else {
			$literals['widened'] = true;
			$literals['values']  = array();
		}
	}

	/**
	 * Returns the properties of a string, as bits.
	 *
	 * - STRING_EMPTY: it is empty, and has no other properties.
	 * - STRING_HTML: the HTML API's Tag Processor finds a token in it other than
	 *   text. A tag which the end of the string cuts off is text.
	 * - STRING_REFERENCE: it holds a character reference which decodes in text,
	 *   such as `&amp;` or a legacy `&amp`.
	 * - STRING_SLASH: wp_slash() would change it, as it holds a quote, a backslash or NUL.
	 * - STRING_UNSLASH: wp_unslash() would change it, as it holds a backslash.
	 * - STRING_URL: it parses as an absolute WHATWG URL.
	 * - STRING_PATH: it is probably a file path: an absolute path whose first
	 *   segment is a directory at the root of the file system, a Windows path
	 *   starting with a drive letter, or a relative one starting with `./` or
	 *   `../`, without a NUL, a newline, `<` or `://`.
	 * - STRING_FILE: it is probably a file path, and a file or directory exists there.
	 * - STRING_INVALID_UTF8: it is not valid UTF-8.
	 * - STRING_NUMERIC: it is a numeric string, as is_numeric() reads it, such as
	 *   `'42'`, `'-1.5'`, `'1e3'` or `' 7'`.
	 *
	 * It uses no PCRE function, which would change what preg_last_error() returns
	 * to the WordPress code being called.
	 *
	 * @param string $value A string.
	 * @return int Bits.
	 */
	public static function string_properties( $value ) {
		if ( '' === $value ) {
			return self::STRING_EMPTY;
		}

		$length = strlen( $value );
		if ( $length <= self::REMEMBERED_LENGTH && isset( self::$remembered[ $value ] ) ) {
			return self::with_file( $value, self::$remembered[ $value ] );
		}

		$properties = is_numeric( $value ) ? self::STRING_NUMERIC : 0;

		// Every token other than text starts with `<`.
		if ( false !== strpos( $value, '<' ) && class_exists( 'WP_HTML_Tag_Processor', false ) ) {
			$processor = new WP_HTML_Tag_Processor( $value );
			while ( $processor->next_token() ) {
				if ( '#text' !== $processor->get_token_type() ) {
					$properties |= self::STRING_HTML;
					break;
				}
			}
		}

		$at = strpos( $value, '&' );
		if ( false !== $at && class_exists( 'WP_HTML_Decoder', false ) && isset( $GLOBALS['html5_named_character_references'] ) ) {
			while ( false !== $at ) {
				if ( null !== WP_HTML_Decoder::read_character_reference( 'data', $value, $at ) ) {
					$properties |= self::STRING_REFERENCE;
					break;
				}
				$at = strpos( $value, '&', $at + 1 );
			}
		}

		if ( false !== strpbrk( $value, "'\"\\\0" ) ) {
			$properties |= self::STRING_SLASH;
			if ( false !== strpos( $value, '\\' ) ) {
				$properties |= self::STRING_UNSLASH;
			}
		}

		if ( self::parses_as_url( $value ) ) {
			$properties |= self::STRING_URL;
		}

		if ( self::looks_like_path( $value, $length ) ) {
			$properties |= self::STRING_PATH;
		}

		if ( function_exists( 'mb_check_encoding' ) ? ! mb_check_encoding( $value, 'UTF-8' ) : ( function_exists( 'wp_is_valid_utf8' ) && ! wp_is_valid_utf8( $value ) ) ) {
			$properties |= self::STRING_INVALID_UTF8;
		}

		if ( $length <= self::REMEMBERED_LENGTH ) {
			if ( count( self::$remembered ) >= self::REMEMBERED_STRINGS ) {
				self::$remembered = array();
			}
			self::$remembered[ $value ] = $properties;
		}

		return self::with_file( $value, $properties );
	}

	/**
	 * Adds STRING_FILE to the properties of a path when something exists there now.
	 *
	 * It is not remembered, as tests create and remove files.
	 *
	 * @param string $value      A string.
	 * @param int    $properties Its other properties, as bits.
	 * @return int Bits.
	 */
	private static function with_file( $value, $properties ) {
		if ( ( $properties & self::STRING_PATH ) && @file_exists( $value ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Only to look; WordPress's own calls report their warnings.
			$properties |= self::STRING_FILE;
		}

		return $properties;
	}

	/**
	 * Returns whether a string parses as an absolute WHATWG URL.
	 *
	 * Only a string which starts with a scheme and `:`, after what the parser
	 * removes from it, is given to the parser. A URL with a scheme which is not
	 * special and an opaque path, such as `mailto:a@example.org`, always parses,
	 * so it is not given to the parser either: PHP 8.5's parser keeps the memory
	 * of each such URL until the request ends.
	 *
	 * @param string $value A non-empty string.
	 * @return bool
	 */
	private static function parses_as_url( $value ) {
		if ( ! class_exists( 'Uri\WhatWg\Url', false ) ) {
			return false;
		}

		$url = ltrim( $value, "\x00..\x20" );
		if ( false !== strpbrk( $url, "\t\n\r" ) ) {
			$url = str_replace( array( "\t", "\n", "\r" ), '', $url );
		}

		$colon = strpos( $url, ':' );
		if ( false === $colon || 0 === $colon || ! ctype_alpha( $url[0] ) ) {
			return false;
		}
		if ( strspn( $url, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+-.', 0, $colon ) !== $colon ) {
			return false;
		}

		$scheme = strtolower( substr( $url, 0, $colon ) );
		if ( ! isset( self::SPECIAL_SCHEMES[ $scheme ] ) && ( ! isset( $url[ $colon + 1 ] ) || '/' !== $url[ $colon + 1 ] ) ) {
			return true;
		}

		return null !== Uri\WhatWg\Url::parse( $value );
	}

	/**
	 * Returns whether a string is probably a file path, as string_properties() describes.
	 *
	 * @param string $value  A non-empty string.
	 * @param int    $length Its length.
	 * @return bool
	 */
	private static function looks_like_path( $value, $length ) {
		if ( $length > PHP_MAXPATHLEN || false !== strpbrk( $value, "\0\n\r<" ) || false !== strpos( $value, '://' ) ) {
			return false;
		}

		if ( 0 === strpos( $value, './' ) || 0 === strpos( $value, '../' ) ) {
			return true;
		}

		if ( $length >= 3 && ':' === $value[1] && ( '\\' === $value[2] || '/' === $value[2] ) && ctype_alpha( $value[0] ) ) {
			return true;
		}

		// An absolute path, but not a URL path such as `/wp-admin/`, or a network path.
		if ( '/' !== $value[0] || ( $length > 1 && '/' === $value[1] ) ) {
			return false;
		}

		$segment = substr( $value, 1, strcspn( $value, '/', 1 ) );
		if ( '' === $segment || '.' === $segment || '..' === $segment ) {
			return false;
		}
		if ( ! isset( self::$root_directories[ $segment ] ) ) {
			self::$root_directories[ $segment ] = @is_dir( "/{$segment}" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Only to look.
		}

		return self::$root_directories[ $segment ];
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

		$into['ints']    = self::merge_literals( $into['ints'], $other['ints'], self::INT_LITERALS );
		$into['strings'] = self::merge_literals( $into['strings'], $other['strings'], self::STRING_LITERALS );

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
	 * Merges two slots' literals of one type, widening them past the limit.
	 *
	 * @param array $into  Literals, from empty_literals().
	 * @param array $other Other literals of the same type.
	 * @param int   $limit Most distinct values to count apart.
	 * @return array
	 */
	private static function merge_literals( array $into, array $other, $limit ) {
		$into['total'] = self::add_counts( $into['total'], $other['total'] );
		foreach ( $other['properties'] as $properties => $counts ) {
			$into['properties'][ $properties ] = isset( $into['properties'][ $properties ] ) ? self::add_counts( $into['properties'][ $properties ], $counts ) : $counts;
		}
		if ( ! $into['widened'] && ! $other['widened'] ) {
			foreach ( $other['values'] as $value => $counts ) {
				$into['values'][ $value ] = isset( $into['values'][ $value ] ) ? self::add_counts( $into['values'][ $value ], $counts ) : $counts;
			}
		}
		if ( $other['widened'] || count( $into['values'] ) > $limit ) {
			$into['widened'] = true;
			$into['values']  = array();
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
