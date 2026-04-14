<?php
/**
 * WordPress Options Administration API.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 4.4.0
 */

/**
 * Render the site charset setting.
 *
 * @since 3.5.0
 */
function options_reading_blog_charset() {
	echo '<input name="blog_charset" type="text" id="blog_charset" value="' . esc_attr( get_option( 'blog_charset' ) ) . '" class="regular-text" />';
	echo '<p class="description">' . __( 'The <a href="https://wordpress.org/documentation/article/wordpress-glossary/#character-set">character encoding</a> of your site (UTF-8 is recommended)' ) . '</p>';
}
