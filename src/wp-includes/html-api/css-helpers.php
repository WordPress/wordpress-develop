<?php
/**
 * HTML API: Utility functions for working with CSS and CSS-adjacent content.
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.9.0
 */

/**
 * Iterates over the class names represented by an HTML `class` attribute value.
 *
 * Because this is aware of the HTML and CSS semantics, duplicate values will be
 * skipped and classes will be properly split according to the whitespace rules.
 * Because this comes without a parent document, it will be parsed as NO QUIRKS
 * mode and therefore treats class names as byte-identical names rather than as
 * case-insensitive names.
 *
 * Example:
 *
 *     foreach ( wp_split_css_class_list( 'alignleft wide alignleft' ) as $name ) {
 *         echo $name . ' ';
 *     }
 *     // "alignleft wide "
 *
 *     // The missing semicolon demonstrates one way this follows HTML semantics.
 *     foreach ( wp_split_css_class_list( "One one ONE &#x80" ) as $name ) {
 *         echo $name . ' ';
 *     }
 *     // "One one ONE € "
 *
 *     // It can also be converted into an array.
 *     $names = iterator_to_array( wp_split_css_class_list( "one &#x61nd two ze&#x00;\x00ro" ) );
 *     $names === array( 'one', 'and', 'two', 'ze��ro' );
 *
 *     // Can be used to join classes uniquely.
 *     $combined = wp_split_css_class_list( "{$existing} {$new}" );
 *     $combined = implode( ' ', iterator_to_array( $combined ) );
 *
 * @since 6.9.0
 *
 * @param string $class_attribute_string Class names as found in an HTML `class` attribute.
 * @return Generator<non-empty-string> Use this in a foreach loop to iterate over the class names.
 */
function wp_split_css_class_list( $class_attribute_string ): Generator {
	if ( '' === $class_attribute_string || ! is_string( $class_attribute_string ) ) {
		return;
	}

	// Get these from the HTML API to avoid ad-hoc parsing HTML or CSS class names.
	$processor = new WP_HTML_Tag_Processor( '<wp-noop>' );
	$processor->next_token();
	$processor->set_attribute( 'class', $class_attribute_string );

	foreach ( $processor->class_list() as $class_name ) {
		yield $class_name;
	}
}
