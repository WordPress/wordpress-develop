<?php

class WP_CSS {
	/**
	 * Generator function that allows iterating over the list of unique
	 * CSS class names found within a given already-HTML-decoded string.
	 *
	 * Names are provided in a lower-case form to aid processing in calling
	 * code since CSS class names are case-insensitive on US-ASCII letters.
	 *
	 * @since 6.4.0
	 *
	 * @param string $class_string String containing a list of CSS class names, e.g. from an HTML "class" attribute.
	 * @return Generator|void Generator to iterate over unique CSS class names, or null if not given a string.
	 */
	public static function class_list( $class_string ) {
		if ( ! is_string( $class_string ) ) {
			return;
		}

		$seen = array();

		$at = 0;
		while ( $at < strlen( $class_string ) ) {
			// Skip past any initial boundary characters.
			$at += strspn( $class_string, " \t\f\r\n", $at );
			if ( $at >= strlen( $class_string ) ) {
				 return;
			}

			// Find the byte length until the next boundary.
			$length = strcspn( $class_string, " \t\f\r\n", $at );
			if ( 0 === $length ) {
				 return;
			}

			/*
			 * CSS class names are case-insensitive in the ASCII range.
			 *
			 * @see https://www.w3.org/TR/CSS2/syndata.html#x1
			 */
			$name = strtolower( substr( $class_string, $at, $length ) );
			$at  += $length;

			/*
			 * It's expected that the number of class names for a given tag is relatively small.
			 * Given this, it is probably faster overall to scan an array for a value rather
			 * than to use the class name as a key and check if it's a key of $seen.
			 */
			if ( in_array( $name, $seen, true ) ) {
				 continue;
			}

			$seen[] = $name;
			yield $name;
		}
	}
}
