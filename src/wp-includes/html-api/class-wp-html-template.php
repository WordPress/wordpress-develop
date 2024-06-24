<?php
/**
 * HTML API: WP_HTML_Template helper class
 *
 * Provides the rendering code for the WP_HTML class. This needs to exist separately as
 * implemented so that it can subclass the WP_HTML_Tag_Processor class and gain access
 * to the bookmarks and lexical updates, which it uses to perform string operations.
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.5.0
 */

/**
 * WP_HTML_Template class.
 *
 * To be used only by the WP_HTML class.
 *
 * @since 6.5.0
 *
 * @access private
 */
class WP_HTML_Template extends WP_HTML_Tag_Processor {
	/**
	 * Renders an HTML template, replacing the placeholders with the provided values.
	 *
	 * This function looks for placeholders in the template string and will replace
	 * them with appropriately-escaped substitutions from the given arguments, if
	 * provided and if those arguments are strings or valid attribute values.
	 *
	 * Example:
	 *
	 *     echo WP_HTML_Template::render(
	 *         '<a href="</%profile_url>"></%name></a>',
	 *         array(
	 *             'profile_url' => 'https://profiles.example.com/username',
	 *             'name'        => $user->display_name
	 *         )
	 *     );
	 *     // Outputs: <a href="https://profiles.example.com/username">Bobby Tables</a>
	 *
	 * Do not escape the values supplied to the argument array! This function will escape each
	 * parameter's value as needed and additional manual escaping may lead to incorrect output.
	 *
	 * ## Syntax.
	 *
	 * ### Substitution Placeholders.
	 *
	 *  - `</%named_arg>` finds `named_arg` in the arguments array, escapes its value if possible,
	 *    and replaces the placeholder with the escaped value. These may exist inside double-quoted
	 *    HTML tag attributes or in HTML text content between tags. They cannot be used to output a tag
	 *    name or content inside a comment.
	 *
	 * ### Spread Attributes.
	 *
	 *  - `...named_arg` when found within an HTML tag will lookup `named_arg` in the arguments array
	 *    and, if it's an array, will set the attribute on the tag for each key/value pair whose value
	 *    is a string, boolean, or `null`.
	 *
	 * ## Notes.
	 *
	 *  - Attributes may only be supplied for a limited set of types: a string value assigns a double-quoted
	 *    attribute value; `true` sets the attribute as a boolean attribute; `null` removes the attribute.
	 *    If provided any other type of value the attribute will be ignored and its existing value persists.
	 *
	 *  - If multiple HTML attributes are specified for a given tag they will be applied as if calling
	 *    `set_attribute()` in the order they are specified in the template. This includes any attributes
	 *    assigned through the attribute spread syntax.
	 *
	 *  - Substitutions in text nodes may only contain string values. If provided any other type of value
	 *    the placeholder will be removed with nothing in its place.
	 *
	 *  - This function currently escapes all value provided in the arguments array. In the future
	 *    it may provide the ability to nest pre-rendered HTML into the template, but this functionality
	 *    is deferred for a future update.
	 *
	 *  - This function will not replace content inside of SCRIPT, or STYLE elements.
	 *
	 * @since 6.5.0
	 *
	 * @access private
	 *
	 * @param string $template The HTML template.
	 * @param string $args     Array of key/value pairs providing substitue values for the placeholders.
	 * @return string The rendered HTML.
	 */
	public static function render( $template, $args = array() ) {
		$processor = new self( $template );
		while ( $processor->next_token() ) {
			$type = $processor->get_token_type();
			$text = $processor->get_modifiable_text();

			// Replace placeholders that are found inside #text nodes.
			if ( '#funky-comment' === $type && strlen( $text ) > 0 && '%' === $text[0] ) {
				$name  = substr( $text, 1 );
				$value = isset( $args[ $name ] ) && is_string( $args[ $name ] ) ? $args[ $name ] : null;
				$processor->set_bookmark( 'here' );
				$processor->lexical_updates[] = new WP_HTML_Text_Replacement(
					$processor->bookmarks['here']->start,
					$processor->bookmarks['here']->length,
					null === $value ? '' : esc_html( $value )
				);
				continue;
			}

			// For every tag, scan the attributes to look for placeholders.
			if ( '#tag' === $type ) {
				foreach ( $processor->get_attribute_names_with_prefix( '' ) ?? array() as $attribute_name ) {
					if ( str_starts_with( $attribute_name, '...' ) ) {
						$spread_name = substr( $attribute_name, 3 );
						if ( isset( $args[ $spread_name ] ) && is_array( $args[ $spread_name ] ) ) {
							foreach ( $args[ $spread_name ] as $key => $value ) {
								if ( true === $value || false === $value || null === $value || is_string( $value ) ) {
									$processor->set_attribute( $key, $value );
								}
							}
						}
						$processor->remove_attribute( $attribute_name );
					}

					$value = $processor->get_attribute( $attribute_name );

					if ( ! is_string( $value ) ) {
						continue;
					}

					// Replace entire attributes if their content is exclusively a placeholder, e.g. `title="</%title>"`.
					$full_match = null;
					if ( preg_match( '~^</%([^>]+)>$~', $value, $full_match ) ) {
						$name = $full_match[1];

						if ( array_key_exists( $name, $args ) ) {
							$value = $args[ $name ];
							if ( false === $value || null === $value ) {
								$processor->remove_attribute( $attribute_name );
							} elseif ( true === $value ) {
								$processor->set_attribute( $attribute_name, true );
							} elseif ( is_string( $value ) ) {
								$processor->set_attribute( $attribute_name, $args[ $name ] );
							} else {
								$processor->remove_attribute( $attribute_name );
							}
						} else {
							$processor->remove_attribute( $attribute_name );
						}

						continue;
					}

					// Replace placeholders embedded in otherwise-static attribute values, e.g. `title="Post: </%title>"`.
					$new_value = preg_replace_callback(
						'~</%([^>]+)>~',
						static function ( $matches ) use ( $args ) {
							return is_string( $args[ $matches[1] ] )
								? esc_attr( $args[ $matches[1] ] )
								: '';
						},
						$value
					);

					if ( $new_value !== $value ) {
						$processor->set_attribute( $attribute_name, $new_value );
					}
				}

				// Update TEXTAREA and TITLE contents.
				$tag_name = $processor->get_tag();
				if ( 'TEXTAREA' === $tag_name || 'TITLE' === $tag_name ) {
					// Replace placeholders inside these RCDATA tags.
					$new_text = preg_replace_callback(
						'~</%([^>]+)>~',
						static function ( $matches ) use ( $args ) {
							return is_string( $args[ $matches[1] ] )
								? $args[ $matches[1] ]
								: '';
						},
						$text
					);

					if ( $new_text !== $text ) {
						$processor->set_modifiable_text( $new_text );
					}
				}
			}
		}

		return $processor->get_updated_html();
	}
}
