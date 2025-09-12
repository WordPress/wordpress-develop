<?php
/**
 * Diff API: WP_Text_Diff_Renderer_inline class
 *
 * @package WordPress
 * @subpackage Diff
 * @since 4.7.0
 */

/**
 * Better word splitting than the PEAR package provides.
 *
 * @since 2.6.0
 * @uses Text_Diff_Renderer_inline Extends
 */
#[AllowDynamicProperties]
class WP_Text_Diff_Renderer_inline extends Text_Diff_Renderer_inline {

	/**
	 * @ignore
	 * @since 2.6.0
	 *
	 * @param string $string
	 * @param string $newlineEscape
	 * @return string
	 */
	public function _splitOnWords( $string, $newlineEscape = "\n" ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound,WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		$string = str_replace( "\0", '', $string );
		if ( _wp_can_use_pcre_u() ) {
			$words = preg_split( '/([^\w])/u', $string, -1, PREG_SPLIT_DELIM_CAPTURE );
		} else {
			if ( function_exists( 'mb_str_split' ) ) {
				$chars = mb_str_split( $string, 1, 'UTF-8' );
			} else {
				$chars = str_split( $string );
			}
			$words        = array();
			$current_word = '';

			foreach ( $chars as $char ) {
				// Simple heuristic: letters, numbers, underscore = word characters
				if ( ctype_alnum( $char ) || '_' === $char || ord( $char ) > 127 ) {
					$current_word .= $char;
				} else {
					if ( '' !== $current_word ) {
						$words[]      = $current_word;
						$current_word = '';
					}
					$words[] = $char; // Capture delimiter
				}
			}
			if ( '' !== $current_word ) {
				$words[] = $current_word;
			}
		}

		$words = str_replace( "\n", $newlineEscape, $words ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		return $words;
	}
}
