<?php

/**
 * Core JavaScript Interoperability functions.
 *
 * @package WordPress
 * @subpackage JS-Interop
 */

/**
 * Return the corresponding JavaScript `dataset` name for an attribute
 * if it represents a custom data attribute, or `null` if not.
 *
 * Custom data attributes appear in an element's `dataset` property in a
 * browser, but there's a specific way the names are translated from HTML
 * into JavaScript. This function indicates how the name would appear in
 * JavaScript if a browser would recognize it as a custom data attribute.
 *
 * Example:
 *
 *     // Dash-letter pairs turn into capital letters.
 *     'postId'       === wp_js_dataset_name( 'data-post-id' );
 *     'Before'       === wp_js_dataset_name( 'data--before' );
 *     '-One--Two---' === wp_js_dataset_name( 'data---one---two---' );
 *
 *     // Not every attribute name will be interpreted as a custom data attribute.
 *     null === wp_js_dataset_name( 'post-id' );
 *     null === wp_js_dataset_name( 'data' );
 *
 *     // Some very surprising names will; for example, a property whose name is the empty string.
 *     '' === wp_js_dataset_name( 'data-' );
 *     0  === strlen( wp_js_dataset_name( 'data-' ) );
 *
 * @since 6.9.0
 *
 * @see https://html.spec.whatwg.org/#concept-domstringmap-pairs
 * @see wp_html_custom_data_attribute_name()
 *
 * @param string $html_attribute_name Raw attribute name as found in the source HTML.
 * @return string|null Transformed `dataset` name, if interpretable as a custom data attribute, else `null`.
 */
function wp_js_dataset_name( string $html_attribute_name ): ?string {
	if ( 0 !== substr_compare( $html_attribute_name, 'data-', 0, 5, true ) ) {
		return null;
	}

	$end = strlen( $html_attribute_name );

	/*
	 * If it contains characters which would end the attribute name parsing then
	 * something else is wrong and this contains more than just an attribute name.
	 */
	if ( ( $end - 5 ) !== strcspn( $html_attribute_name, "=/> \t\f\r\n", 5 ) ) {
		return null;
	}

	/*
	 * > For each name in list, for each U+002D HYPHEN-MINUS character (-)
	 * > in the name that is followed by an ASCII lower alpha, remove the
	 * > U+002D HYPHEN-MINUS character (-) and replace the character that
	 * > followed it by the same character converted to ASCII uppercase.
	 *
	 * @link https://html.spec.whatwg.org/#concept-domstringmap-pairs
	 */
	$custom_name = '';
	$at          = 5;
	$was_at      = $at;

	while ( $at < $end ) {
		$next_dash_at = strpos( $html_attribute_name, '-', $at );
		if ( false === $next_dash_at || $next_dash_at === $end - 1 ) {
			break;
		}

		// Transform `-a` to `A`, for example.
		$c = $html_attribute_name[ $next_dash_at + 1 ];
		if ( ( $c >= 'A' && $c <= 'Z' ) || ( $c >= 'a' && $c <= 'z' ) ) {
			$prefix       = substr( $html_attribute_name, $was_at, $next_dash_at - $was_at );
			$custom_name .= strtolower( $prefix );
			$custom_name .= strtoupper( $c );
			$at           = $next_dash_at + 2;
			$was_at       = $at;
			continue;
		}

		$at = $next_dash_at + 1;
	}

	// If nothing has been added it means there are no dash-letter pairs; return the name as-is.
	return '' === $custom_name
		? strtolower( substr( $html_attribute_name, 5 ) )
		: ( $custom_name . strtolower( substr( $html_attribute_name, $was_at ) ) );
}

/**
 * Returns a corresponding HTML attribute name for the given name,
 * if that name were found in a JS element’s `dataset` property.
 *
 * Example:
 *
 *     'data-post-id'        === wp_html_custom_data_attribute_name( 'postId' );
 *     'data--before'        === wp_html_custom_data_attribute_name( 'Before' );
 *     'data---one---two---' === wp_html_custom_data_attribute_name( '-One--Two---' );
 *
 *     // Not every attribute name will be interpreted as a custom data attribute.
 *     null === wp_html_custom_data_attribute_name( '/not-an-attribute/' );
 *     null === wp_html_custom_data_attribute_name( 'no spaces' );
 *
 *     // Some very surprising names will; for example, a property whose name is the empty string.
 *     'data-' === wp_html_custom_data_attribute_name( '' );
 *
 * @since 6.9.0
 *
 * @see https://html.spec.whatwg.org/#concept-domstringmap-pairs
 * @see wp_js_dataset_name()
 *
 * @param string $js_dataset_name Name of JS `dataset` property to transform.
 * @return string|null Corresponding name of an HTML custom data attribute for the given dataset name,
 *                     if possible to represent in HTML, otherwise `null`.
 */
function wp_html_custom_data_attribute_name( string $js_dataset_name ): ?string {
	$end = strlen( $js_dataset_name );
	if ( 0 === $end ) {
		return 'data-';
	}

	/*
	 * If it contains characters which would end the attribute name parsing then
	 * something it’s not possible to represent this in HTML.
	 */
	if ( strcspn( $js_dataset_name, "=/> \t\f\r\n" ) !== $end ) {
		return null;
	}

	$html_name = 'data-';
	$at        = 0;
	$was_at    = $at;

	while ( $at < $end ) {
		$next_upper_after = strcspn( $js_dataset_name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', $at );
		$next_upper_at    = $at + $next_upper_after;
		if ( $next_upper_at >= $end ) {
			break;
		}

		$prefix     = substr( $js_dataset_name, $was_at, $next_upper_at - $was_at );
		$html_name .= strtolower( $prefix );
		$html_name .= '-' . strtolower( $js_dataset_name[ $next_upper_at ] );
		$at         = $next_upper_at + 1;
		$was_at     = $at;
	}

	if ( $was_at < $end ) {
		$html_name .= strtolower( substr( $js_dataset_name, $was_at ) );
	}

	return $html_name;
}
