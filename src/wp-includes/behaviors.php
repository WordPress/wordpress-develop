<?php
/**
 * Block Behaviors.
 *
 * @package WordPress
 * @since 6.3.0
 *
 */

/**
 * Updates the block editor settings with the theme's behaviors.
 *
 * @since 6.3.0
 * @param array $editor_settings The array of editor settings.
 * @return array A filtered array of editor settings.
 */
function wp_add_behaviors( $settings )
{
	$theme_data = WP_Theme_JSON_Resolver::get_merged_data()->get_data();
	if (array_key_exists('behaviors', $theme_data)) {
		$settings['behaviors'] = $theme_data['behaviors'];
	}
	return $settings;
}
