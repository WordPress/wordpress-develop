<?php
/**
 * HTML API: WP_HTML class
 *
 * Provides a public interface for HTML-related functionality in WordPress.
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.5.0
 */

/**
 * WP_HTML class.
 *
 * @since 6.5.0
 */
class WP_HTML {
	/**
	 * Renders an HTML template, replacing the placeholders with the provided values.
	 *
	 * This function looks for placeholders in the template string and will replace
	 * them with appropriately-escaped substitutions from the given arguments, if
	 * provided and if those arguments are strings or valid attribute values.
	 *
	 * Example:
	 *
	 *     echo WP_HTML::render(
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
	 *    is a string. The
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
	public static function render( $template, $args ) {
		return WP_HTML_Template::render( $template, $args );
	}
}
