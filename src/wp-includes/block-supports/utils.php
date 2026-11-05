<?php
/**
 * Block support utility functions.
 *
 * @package WordPress
 * @subpackage Block Supports
 * @since 6.0.0
 */

/**
 * Checks whether serialization of the current block's supported properties
 * should occur.
 *
 * @since 6.0.0
 * @access private
 *
 * @param WP_Block_Type $block_type  Block type.
 * @param string        $feature_set Name of block support feature set..
 * @param string        $feature     Optional name of individual feature to check.
 * @return bool Whether to serialize block support styles & classes.
 */
function wp_should_skip_block_supports_serialization( $block_type, $feature_set, $feature = null ) {
	if ( ! is_object( $block_type ) || ! $feature_set ) {
		return false;
	}

	$path               = array( $feature_set, '__experimentalSkipSerialization' );
	$skip_serialization = _wp_array_get( $block_type->supports, $path, false );

	if ( is_array( $skip_serialization ) ) {
		return in_array( $feature, $skip_serialization, true );
	}

	return $skip_serialization;
}

/**
 * Returns the combined classes and inline styles of the background, color,
 * border, shadow and spacing block supports for block attributes. Does not
 * check block support or skipped serialization.
 *
 * @since 7.3.0
 *
 * @param array      $block_attributes Block attributes.
 * @param array|null $supports         Optional. Supports to include: any of `background`, `color`,
 *                                     `border`, `shadow` and `spacing`. Default null, for all of them.
 * @return array Array with `class` and `style` keys, each present only when non-empty.
 */
function wp_get_block_supports_classes_and_styles( $block_attributes, $supports = null ) {
	// Follows the style engine's group order.
	$getters = array(
		'background' => 'wp_get_background_classes_and_styles',
		'color'      => 'wp_get_color_classes_and_styles',
		'border'     => 'wp_get_border_classes_and_styles',
		'shadow'     => 'wp_get_shadow_classes_and_styles',
		'spacing'    => 'wp_get_spacing_classes_and_styles',
	);

	if ( is_array( $supports ) ) {
		$getters = array_intersect_key( $getters, array_flip( $supports ) );
	}

	$classes = array();
	$styles  = '';

	foreach ( $getters as $getter ) {
		$classes_and_styles = $getter( $block_attributes );

		if ( ! empty( $classes_and_styles['class'] ) ) {
			$classes[] = $classes_and_styles['class'];
		}

		if ( ! empty( $classes_and_styles['style'] ) ) {
			$styles .= $classes_and_styles['style'];
		}
	}

	$attributes = array();

	if ( ! empty( $classes ) ) {
		// Background and color can both add `has-background`.
		$attributes['class'] = implode( ' ', array_unique( explode( ' ', implode( ' ', $classes ) ) ) );
	}

	if ( '' !== $styles ) {
		$attributes['style'] = $styles;
	}

	return $attributes;
}
