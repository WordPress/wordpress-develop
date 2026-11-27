<?php
/**
 * What the request being handled sent, for invariants which follow request values.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Finds the values a request sent, so that invariants can tell where they end up.
 *
 * The values are the strings in $_GET, $_POST, $_COOKIE and $_REQUEST, the URI,
 * query string and headers in $_SERVER, and the parameters of the REST requests
 * being dispatched. Each is given as sent and with slashes removed, as
 * wp_magic_quotes() adds them to the superglobals.
 *
 * Only values of at least MIN_LENGTH bytes count, so that short values such as
 * `1` or `on`, which appear everywhere, are not followed.
 */
final class WP_Invariant_Request {

	/**
	 * Shortest value followed, in bytes.
	 */
	const MIN_LENGTH = 4;

	/**
	 * Most values followed for one request.
	 */
	const MAX_VALUES = 200;

	/**
	 * Parameters of the REST requests being dispatched, innermost last.
	 *
	 * @var array[]
	 */
	private static $rest = array();

	/**
	 * The values last found, and what they were found from.
	 *
	 * @var array
	 */
	private static $cache = array(
		'key'    => null,
		'values' => array(),
	);

	/**
	 * Returns the values the request sent which hold any of some characters.
	 *
	 * @param string $characters Characters, as for strpbrk(), such as `<"'`. Empty for every value.
	 * @return string[] Distinct values.
	 */
	public static function values( $characters = '' ) {
		$values = self::all_values();
		if ( '' === $characters ) {
			return $values;
		}

		return array_values(
			array_filter(
				$values,
				static function ( $value ) use ( $characters ) {
					return false !== strpbrk( $value, $characters );
				}
			)
		);
	}

	/**
	 * Returns the byte offsets where a value appears in a string.
	 *
	 * @param string $haystack String to look in.
	 * @param string $needle   Value to look for.
	 * @param int    $limit    Most offsets to return.
	 * @return int[]
	 */
	public static function occurrences( $haystack, $needle, $limit = 20 ) {
		$offsets = array();
		$at      = strpos( $haystack, $needle );
		while ( false !== $at && count( $offsets ) < $limit ) {
			$offsets[] = $at;
			$at        = strpos( $haystack, $needle, $at + 1 );
		}

		return $offsets;
	}

	/**
	 * Notes that a REST request is being dispatched, so that its parameters are values the request sent.
	 *
	 * @param array $params Its parameters.
	 */
	public static function start_rest_request( array $params ) {
		self::$rest[] = $params;
	}

	/**
	 * Notes that the innermost REST request has been dispatched.
	 */
	public static function end_rest_request() {
		array_pop( self::$rest );
	}

	/**
	 * Forgets the REST requests being dispatched, as a test starts.
	 */
	public static function reset() {
		self::$rest = array();
	}

	/**
	 * Returns every value the request sent, from a cache while the request has not changed.
	 *
	 * @return string[]
	 */
	private static function all_values() {
		$sources = array( $_GET, $_POST, $_COOKIE, $_REQUEST, self::$rest ); // phpcs:ignore WordPress.Security.NonceVerification -- Only read, to follow the values.
		foreach ( array( 'REQUEST_URI', 'QUERY_STRING' ) as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				$sources[] = $_SERVER[ $key ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only read, to follow the values.
			}
		}
		foreach ( $_SERVER as $key => $value ) {
			if ( 0 === strncmp( $key, 'HTTP_', 5 ) ) {
				$sources[] = $value;
			}
		}

		$key = md5( serialize( $sources ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Only to compare.
		if ( $key === self::$cache['key'] ) {
			return self::$cache['values'];
		}

		$values = array();
		foreach ( $sources as $source ) {
			foreach ( WP_Invariant::strings_in( $source, self::MAX_VALUES ) as $value ) {
				foreach ( array( $value, stripslashes( $value ) ) as $form ) {
					if ( strlen( $form ) >= self::MIN_LENGTH && count( $values ) < self::MAX_VALUES ) {
						$values[ $form ] = true;
					}
				}
			}
		}

		self::$cache = array(
			'key'    => $key,
			'values' => array_map( 'strval', array_keys( $values ) ),
		);

		return self::$cache['values'];
	}
}
