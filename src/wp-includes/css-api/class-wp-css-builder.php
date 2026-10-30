<?php
/**
 * CSS API: WP_CSS_Builder class
 *
 * @package WordPress
 * @subpackage CSS-API
 * @since 7.3.0
 */

/**
 * Writes PHP values as CSS text.
 *
 * Each method returns CSS text that reads back as the given value: a string
 * token, a `url()` function holding a string token, or an identifier. Combine
 * the results with literal CSS to write a value with parts that come from
 * input.
 *
 * Example:
 *
 * ```php interactive
 * $image_url = 'https://example.com/a (1) "b".jpg';
 * echo 'background-image: ' . WP_CSS_Builder::url( $image_url );
 * // Outputs: background-image: url("https://example.com/a (1) \22 b\22 .jpg")
 * ```
 *
 * The characters escaped beyond those CSS requires may change; the value
 * the text reads back as does not.
 *
 * @since 7.3.0
 *
 * @see WP_CSS_Token_Serializer
 */
class WP_CSS_Builder {
	/**
	 * Writes a value as a quoted CSS string.
	 *
	 * Quotes, `\` and control characters are hex-escaped, as are `< > & , ;
	 * { }`, which may be problematic in HTML or for rudimentary CSS
	 * processors. CSS cannot represent NULL, so it reads back as U+FFFD.
	 * Invalid UTF-8 reads back as U+FFFD.
	 *
	 * Example:
	 *
	 * ```php interactive
	 * echo 'content: ' . WP_CSS_Builder::string( 'Say "hi"' );
	 * // Outputs: content: "Say \22 hi\22 "
	 * ```
	 *
	 * @since 7.3.0
	 *
	 * @param string $value Value the string should read back as.
	 * @return string Quoted CSS string.
	 */
	public static function string( string $value ): string {
		return WP_CSS_Token_Serializer::escape_string( $value );
	}

	/**
	 * Writes a URL as a CSS `url()` function holding a quoted string.
	 *
	 * The URL is written as string() writes it. It is not validated or
	 * sanitized; pass it through esc_url() or sanitize_url() first when it
	 * comes from input.
	 *
	 * Example:
	 *
	 * ```php interactive
	 * echo WP_CSS_Builder::url( 'https://example.com/a(1).png' );
	 * // Outputs: url("https://example.com/a(1).png")
	 * ```
	 *
	 * @since 7.3.0
	 *
	 * @link https://www.w3.org/TR/css-values-4/#urls
	 *
	 * @param string $url URL the function should read back as.
	 * @return string CSS `url()` function.
	 */
	public static function url( string $url ): string {
		return 'url(' . self::string( $url ) . ')';
	}

	/**
	 * Writes a value as a CSS identifier.
	 *
	 * Code points that cannot appear in an identifier at their position are
	 * hex-escaped, as are the characters string() escapes. Use it for a
	 * property name, a custom property name in `var()`, or a keyword or
	 * family name written without quotes.
	 *
	 * An empty value returns an empty string, which is not an identifier.
	 *
	 * Example:
	 *
	 * ```php interactive
	 * echo 'font-family: ' . WP_CSS_Builder::ident( '1st Font' );
	 * // Outputs: font-family: \31 st\20 Font
	 * ```
	 *
	 * @since 7.3.0
	 *
	 * @link https://www.w3.org/TR/css-syntax-3/#ident-token-diagram
	 *
	 * @param string $value Value the identifier should read back as.
	 * @return string CSS identifier, or an empty string for an empty value.
	 */
	public static function ident( string $value ): string {
		return WP_CSS_Token_Serializer::escape_ident( $value );
	}
}
