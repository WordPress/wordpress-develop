<?php
/**
 * Duotone block support flag.
 *
 * Parts of this source were derived and modified from TinyColor,
 * released under the MIT license.
 *
 * https://github.com/bgrins/TinyColor
 *
 * Copyright (c), Brian Grinstead, http://briangrinstead.com
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @package WordPress
 * @since 5.8.0
 */

/**
 * Takes input from [0, n] and returns it as [0, 1].
 *
 * Direct port of TinyColor's function, lightly simplified to maintain
 * consistency with TinyColor.
 *
 * @see https://github.com/bgrins/TinyColor
 *
 * @deprecated 6.3.0
 *
 * @since 5.8.0
 * @access private
 *
 * @param mixed $n   Number of unknown type.
 * @param int   $max Upper value of the range to bound to.
 * @return float Value in the range [0, 1].
 */
function wp_tinycolor_bound01( $n, $max ) {
	_deprecated_function( __FUNCTION__, '6.3.0' );
	if ( 'string' === gettype( $n ) && str_contains( $n, '.' ) && 1 === (float) $n ) {
		$n = '100%';
	}

	$n = min( $max, max( 0, (float) $n ) );

	// Automatically convert percentage into number.
	if ( 'string' === gettype( $n ) && str_contains( $n, '%' ) ) {
		$n = (int) ( $n * $max ) / 100;
	}

	// Handle floating point rounding errors.
	if ( ( abs( $n - $max ) < 0.000001 ) ) {
		return 1.0;
	}

	// Convert into [0, 1] range if it isn't already.
	return ( $n % $max ) / (float) $max;
}

/**
 * Direct port of tinycolor's boundAlpha function to maintain consistency with
 * how tinycolor works.
 *
 * @see https://github.com/bgrins/TinyColor
 *
 * @deprecated 6.3.0
 *
 * @since 5.9.0
 * @access private
 *
 * @param mixed $n Number of unknown type.
 * @return float Value in the range [0,1].
 */
function _wp_tinycolor_bound_alpha( $n ) {
	_deprecated_function( __FUNCTION__, '6.3.0' );

	if ( is_numeric( $n ) ) {
		$n = (float) $n;
		if ( $n >= 0 && $n <= 1 ) {
			return $n;
		}
	}
	return 1;
}

/**
 * Rounds and converts values of an RGB object.
 *
 * Direct port of TinyColor's function, lightly simplified to maintain
 * consistency with TinyColor.
 *
 * @see https://github.com/bgrins/TinyColor
 * 
 * @deprecated 6.3.0
 *
 * @since 5.8.0
 * @access private
 *
 * @param array $rgb_color RGB object.
 * @return array Rounded and converted RGB object.
 */
function wp_tinycolor_rgb_to_rgb( $rgb_color ) {
	_deprecated_function( __FUNCTION__, '6.3.0' );

	return array(
		'r' => wp_tinycolor_bound01( $rgb_color['r'], 255 ) * 255,
		'g' => wp_tinycolor_bound01( $rgb_color['g'], 255 ) * 255,
		'b' => wp_tinycolor_bound01( $rgb_color['b'], 255 ) * 255,
	);
}

/**
 * Helper function for hsl to rgb conversion.
 *
 * Direct port of TinyColor's function, lightly simplified to maintain
 * consistency with TinyColor.
 *
 * @see https://github.com/bgrins/TinyColor
 * 
 * @deprecated 6.3.0
 *
 * @since 5.8.0
 * @access private
 *
 * @param float $p first component.
 * @param float $q second component.
 * @param float $t third component.
 * @return float R, G, or B component.
 */
function wp_tinycolor_hue_to_rgb( $p, $q, $t ) {
	_deprecated_function( __FUNCTION__, '6.3.0' );

	if ( $t < 0 ) {
		++$t;
	}
	if ( $t > 1 ) {
		--$t;
	}
	if ( $t < 1 / 6 ) {
		return $p + ( $q - $p ) * 6 * $t;
	}
	if ( $t < 1 / 2 ) {
		return $q;
	}
	if ( $t < 2 / 3 ) {
		return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
	}
	return $p;
}

/**
 * Converts an HSL object to an RGB object with converted and rounded values.
 *
 * Direct port of TinyColor's function, lightly simplified to maintain
 * consistency with TinyColor.
 *
 * @see https://github.com/bgrins/TinyColor
 *
 * @deprecated 6.3.0
 *
 * @since 5.8.0
 * @access private
 *
 * @param array $hsl_color HSL object.
 * @return array Rounded and converted RGB object.
 */
function wp_tinycolor_hsl_to_rgb( $hsl_color ) {
	_deprecated_function( __FUNCTION__, '6.3.0' );

	$h = wp_tinycolor_bound01( $hsl_color['h'], 360 );
	$s = wp_tinycolor_bound01( $hsl_color['s'], 100 );
	$l = wp_tinycolor_bound01( $hsl_color['l'], 100 );

	if ( 0 === $s ) {
		// Achromatic.
		$r = $l;
		$g = $l;
		$b = $l;
	} else {
		$q = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
		$p = 2 * $l - $q;
		$r = wp_tinycolor_hue_to_rgb( $p, $q, $h + 1 / 3 );
		$g = wp_tinycolor_hue_to_rgb( $p, $q, $h );
		$b = wp_tinycolor_hue_to_rgb( $p, $q, $h - 1 / 3 );
	}

	return array(
		'r' => $r * 255,
		'g' => $g * 255,
		'b' => $b * 255,
	);
}

/**
 * Parses hex, hsl, and rgb CSS strings using the same regex as TinyColor v1.4.2
 * used in the JavaScript. Only colors output from react-color are implemented.
 *
 * Direct port of TinyColor's function, lightly simplified to maintain
 * consistency with TinyColor.
 *
 * @see https://github.com/bgrins/TinyColor
 * @see https://github.com/casesandberg/react-color/
 *
 * @deprecated 6.3.0
 *
 * @since 5.8.0
 * @since 5.9.0 Added alpha processing.
 * @access private
 *
 * @param string $color_str CSS color string.
 * @return array RGB object.
 */
function wp_tinycolor_string_to_rgb( $color_str ) {
	_deprecated_function( __FUNCTION__, '6.3.0' );

	$color_str = strtolower( trim( $color_str ) );

	$css_integer = '[-\\+]?\\d+%?';
	$css_number  = '[-\\+]?\\d*\\.\\d+%?';

	$css_unit = '(?:' . $css_number . ')|(?:' . $css_integer . ')';

	$permissive_match3 = '[\\s|\\(]+(' . $css_unit . ')[,|\\s]+(' . $css_unit . ')[,|\\s]+(' . $css_unit . ')\\s*\\)?';
	$permissive_match4 = '[\\s|\\(]+(' . $css_unit . ')[,|\\s]+(' . $css_unit . ')[,|\\s]+(' . $css_unit . ')[,|\\s]+(' . $css_unit . ')\\s*\\)?';

	$rgb_regexp = '/^rgb' . $permissive_match3 . '$/';
	if ( preg_match( $rgb_regexp, $color_str, $match ) ) {
		$rgb = wp_tinycolor_rgb_to_rgb(
			array(
				'r' => $match[1],
				'g' => $match[2],
				'b' => $match[3],
			)
		);

		$rgb['a'] = 1;

		return $rgb;
	}

	$rgba_regexp = '/^rgba' . $permissive_match4 . '$/';
	if ( preg_match( $rgba_regexp, $color_str, $match ) ) {
		$rgb = wp_tinycolor_rgb_to_rgb(
			array(
				'r' => $match[1],
				'g' => $match[2],
				'b' => $match[3],
			)
		);

		$rgb['a'] = _wp_tinycolor_bound_alpha( $match[4] );

		return $rgb;
	}

	$hsl_regexp = '/^hsl' . $permissive_match3 . '$/';
	if ( preg_match( $hsl_regexp, $color_str, $match ) ) {
		$rgb = wp_tinycolor_hsl_to_rgb(
			array(
				'h' => $match[1],
				's' => $match[2],
				'l' => $match[3],
			)
		);

		$rgb['a'] = 1;

		return $rgb;
	}

	$hsla_regexp = '/^hsla' . $permissive_match4 . '$/';
	if ( preg_match( $hsla_regexp, $color_str, $match ) ) {
		$rgb = wp_tinycolor_hsl_to_rgb(
			array(
				'h' => $match[1],
				's' => $match[2],
				'l' => $match[3],
			)
		);

		$rgb['a'] = _wp_tinycolor_bound_alpha( $match[4] );

		return $rgb;
	}

	$hex8_regexp = '/^#?([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/';
	if ( preg_match( $hex8_regexp, $color_str, $match ) ) {
		$rgb = wp_tinycolor_rgb_to_rgb(
			array(
				'r' => base_convert( $match[1], 16, 10 ),
				'g' => base_convert( $match[2], 16, 10 ),
				'b' => base_convert( $match[3], 16, 10 ),
			)
		);

		$rgb['a'] = _wp_tinycolor_bound_alpha(
			base_convert( $match[4], 16, 10 ) / 255
		);

		return $rgb;
	}

	$hex6_regexp = '/^#?([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/';
	if ( preg_match( $hex6_regexp, $color_str, $match ) ) {
		$rgb = wp_tinycolor_rgb_to_rgb(
			array(
				'r' => base_convert( $match[1], 16, 10 ),
				'g' => base_convert( $match[2], 16, 10 ),
				'b' => base_convert( $match[3], 16, 10 ),
			)
		);

		$rgb['a'] = 1;

		return $rgb;
	}

	$hex4_regexp = '/^#?([0-9a-fA-F]{1})([0-9a-fA-F]{1})([0-9a-fA-F]{1})([0-9a-fA-F]{1})$/';
	if ( preg_match( $hex4_regexp, $color_str, $match ) ) {
		$rgb = wp_tinycolor_rgb_to_rgb(
			array(
				'r' => base_convert( $match[1] . $match[1], 16, 10 ),
				'g' => base_convert( $match[2] . $match[2], 16, 10 ),
				'b' => base_convert( $match[3] . $match[3], 16, 10 ),
			)
		);

		$rgb['a'] = _wp_tinycolor_bound_alpha(
			base_convert( $match[4] . $match[4], 16, 10 ) / 255
		);

		return $rgb;
	}

	$hex3_regexp = '/^#?([0-9a-fA-F]{1})([0-9a-fA-F]{1})([0-9a-fA-F]{1})$/';
	if ( preg_match( $hex3_regexp, $color_str, $match ) ) {
		$rgb = wp_tinycolor_rgb_to_rgb(
			array(
				'r' => base_convert( $match[1] . $match[1], 16, 10 ),
				'g' => base_convert( $match[2] . $match[2], 16, 10 ),
				'b' => base_convert( $match[3] . $match[3], 16, 10 ),
			)
		);

		$rgb['a'] = 1;

		return $rgb;
	}

	/*
	 * The JS color picker considers the string "transparent" to be a hex value,
	 * so we need to handle it here as a special case.
	 */
	if ( 'transparent' === $color_str ) {
		return array(
			'r' => 0,
			'g' => 0,
			'b' => 0,
			'a' => 0,
		);
	}
}

/**
 * Returns the prefixed id for the duotone filter for use as a CSS id.
 *
 * @since 5.9.1
 * @access private
 *
 * @param array $preset Duotone preset value as seen in theme.json.
 * @return string Duotone filter CSS id.
 */
function wp_get_duotone_filter_id( $preset ) {
	if ( ! isset( $preset['slug'] ) ) {
		return '';
	}

	return 'wp-duotone-' . $preset['slug'];
}

/**
 * Returns the CSS filter property url to reference the rendered SVG.
 *
 * @since 5.9.0
 * @since 6.1.0 Allow unset for preset colors.
 * @access private
 *
 * @param array $preset Duotone preset value as seen in theme.json.
 * @return string Duotone CSS filter property url value.
 */
function wp_get_duotone_filter_property( $preset ) {
	if ( isset( $preset['colors'] ) && 'unset' === $preset['colors'] ) {
		return 'none';
	}
	$filter_id = wp_get_duotone_filter_id( $preset );
	return "url('#" . $filter_id . "')";
}

/**
 * Returns the duotone filter SVG string for the preset.
 *
 * @since 5.9.1
 * @access private
 *
 * @deprecated 6.3.0
 *
 * @param array $preset Duotone preset value as seen in theme.json.
 * @return string Duotone SVG filter.
 */
function wp_get_duotone_filter_svg( $preset ) {_deprecated_function( __FUNCTION__, '6.3.0' );
	return WP_Duotone::get_filter_svg_from_preset( $preset );
}

/**
 * Registers the style and colors block attributes for block types that support it.
 *
 * @since 5.8.0
 * @access private
 *
 * @param WP_Block_Type $block_type Block Type.
 */
function wp_register_duotone_support( $block_type ) {
	$has_duotone_support = false;
	if ( property_exists( $block_type, 'supports' ) ) {
		$has_duotone_support = _wp_array_get( $block_type->supports, array( 'color', '__experimentalDuotone' ), false );
	}

	if ( $has_duotone_support ) {
		if ( ! $block_type->attributes ) {
			$block_type->attributes = array();
		}

		if ( ! array_key_exists( 'style', $block_type->attributes ) ) {
			$block_type->attributes['style'] = array(
				'type' => 'object',
			);
		}
	}
}

/**
 * Renders out the duotone stylesheet and SVG.
 *
 * @since 5.8.0
 * @since 6.1.0 Allow unset for preset colors.
 * @access private
 *
 * @param string $block_content Rendered block content.
 * @param array  $block         Block object.
 * @deprecated    6.3.0          Use WP_Duotone::render_duotone_support() instead.
 * @return string Filtered block content.
 */
function wp_render_duotone_support( $block_content, $block ) {
	_deprecated_function( __FUNCTION__, '6.3.0', 'WP_Duotone::render_duotone_support' );
	return WP_Duotone::render_duotone_support( $block_content, $block );
}

// Register the block support.
WP_Block_Supports::get_instance()->register(
	'duotone',
	array(
		'register_attribute' => 'wp_register_duotone_support',
	)
);
add_action( 'wp_loaded', array( 'WP_Duotone', 'set_global_styles_presets' ), 10 );
add_action( 'wp_loaded', array( 'WP_Duotone', 'set_global_style_block_names' ), 10 );
add_filter( 'render_block', array( 'WP_Duotone', 'render_duotone_support' ), 10, 2 );
add_action( 'wp_enqueue_scripts', array( 'WP_Duotone', 'output_global_styles' ), 11 );
add_action( 'wp_footer', array( 'WP_Duotone', 'output_footer_assets' ), 10 );
add_filter( 'block_editor_settings_all', array( 'WP_Duotone', 'add_editor_settings' ), 10 );