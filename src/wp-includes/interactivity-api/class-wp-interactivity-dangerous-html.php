<?php
/**
 * Interactivity API: WP_Interactivity_Dangerous_HTML class.
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 7.2.0
 */

/**
 * Opaque identity token for HTML registered by wp_interactivity_as_dangerous_html().
 *
 * Only that function creates instances that render; an instance made any other
 * way, such as with `new`, is not recognized. See it for how to use the token and
 * supply HTML safely.
 *
 * @since 7.2.0
 *
 * @see wp_interactivity_as_dangerous_html()
 */
final class WP_Interactivity_Dangerous_HTML {}
