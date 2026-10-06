<?php
/**
 * Prototype: parser-based declaration-list filter for style attributes.
 *
 * Provisional file and function names. Not wired into safecss_filter_attr().
 *
 * @package WordPress
 * @subpackage CSS-API
 */

/**
 * Filters a CSS declaration list for a style attribute.
 *
 * Parses the list with WP_HTML_Style_Attribute_Processor, drops declarations
 * the policy rejects, and serializes the rest as `property:value;`. The
 * rejection unit is one declaration. Declarations CSS drops at parse time
 * never reach the policy because the processor does not expose them.
 *
 * Policy:
 * - The property name is in the `safe_style_css` list, or `--*` is in the
 *   list and the name matches `^--[a-zA-Z0-9_-]+$`.
 * - Every function at any depth is in the function allowlist.
 * - Every URL at any depth is non-empty and unchanged by wp_kses_bad_protocol().
 * - Escapes are decoded before checks. Comments are never emitted.
 *
 * An empty `safe_style_css` list disables the checks; the output is still
 * re-serialized from the parsed declarations.
 *
 * @param string $css Decoded CSS declaration list.
 * @return string Filtered declaration list.
 */
function wp_filter_style_declaration_list( string $css ): string {
	/** This filter is documented in wp-includes/kses.php */
	$allowed_properties = apply_filters( 'safe_style_css', wp_filter_style_default_allowed_properties() );
	$check              = ! empty( $allowed_properties );
	$allow_custom       = in_array( '--*', $allowed_properties, true );
	$allowed_protocols  = wp_allowed_protocols();

	$processor = WP_HTML_Style_Attribute_Processor::create( $css );
	$output    = '';

	while ( $processor->next_declaration() ) {
		$name   = $processor->get_property_name();
		$tokens = $processor->get_value_tokens();

		if ( $check ) {
			$is_custom = '--' === substr( $name, 0, 2 );
			if ( $is_custom ) {
				if ( ! $allow_custom || ! preg_match( '/^--[a-zA-Z0-9_-]+$/', $name ) ) {
					continue;
				}
			} elseif ( ! in_array( $name, $allowed_properties, true ) ) {
				continue;
			}

			if ( ! wp_filter_style_is_value_allowed( $css, $tokens, $allowed_protocols ) ) {
				continue;
			}

			/*
			 * The legacy `safecss_filter_attr_allow_css` filter fired here with a
			 * test string stripped of url() and allowed functions. The new
			 * implementation has no hook.
			 */
		}

		$output .= WP_CSS_Builder::ident( $name ) . ':' . wp_filter_style_serialize_value( $css, $tokens );
		if ( $processor->is_important() ) {
			$output .= ' !important';
		}
		$output .= ';';
	}

	return $output;
}

/**
 * Checks a declaration value's tokens against the function and URL policy.
 *
 * @param string $css               Decoded CSS the token offsets index into.
 * @param array  $tokens            Value tokens from the processor.
 * @param array  $allowed_protocols Allowed URL protocols.
 * @return bool Whether every function and URL in the value is allowed.
 */
function wp_filter_style_is_value_allowed( string $css, array $tokens, array $allowed_protocols ): bool {
	static $allowed_functions = array(
		// General purpose value functions.
		'var',
		'calc',
		'min',
		'max',
		'minmax',
		'clamp',
		'repeat',
		// Transform functions.
		'matrix',
		'matrix3d',
		'perspective',
		'rotate',
		'rotate3d',
		'rotatex',
		'rotatey',
		'rotatez',
		'scale',
		'scale3d',
		'scalex',
		'scaley',
		'scalez',
		'skew',
		'skewx',
		'skewy',
		'translate',
		'translate3d',
		'translatex',
		'translatey',
		'translatez',
		// Basic shape functions.
		'circle',
		'ellipse',
		'inset',
		'path',
		'polygon',
		'rect',
		'shape',
		'xywh',
		// Gradients.
		'linear-gradient',
		'radial-gradient',
		'conic-gradient',
		'repeating-linear-gradient',
		'repeating-radial-gradient',
		'repeating-conic-gradient',
		// Color functions.
		'rgb',
		'rgba',
		'hsl',
		'hsla',
		'hwb',
		'lab',
		'lch',
		'oklab',
		'oklch',
		'color',
		'color-mix',
		'light-dark',
	);

	$count = count( $tokens );
	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( WP_CSS_Token_Processor::TOKEN_URL === $token['type'] ) {
			if ( ! wp_filter_style_is_url_allowed( $token['value'], $allowed_protocols ) ) {
				return false;
			}
			continue;
		}

		if ( WP_CSS_Token_Processor::TOKEN_FUNCTION !== $token['type'] ) {
			continue;
		}

		$function_name = strtolower( (string) $token['value'] );

		if ( 'url' === $function_name || 'src' === $function_name ) {
			/*
			 * url("...") tokenizes as a function with one string argument.
			 * Anything else inside is invalid CSS and the declaration is dropped.
			 */
			$j = $i + 1;
			while ( $j < $count && wp_filter_style_is_trivia( $tokens[ $j ] ) ) {
				++$j;
			}
			if ( $j >= $count || WP_CSS_Token_Processor::TOKEN_STRING !== $tokens[ $j ]['type'] ) {
				return false;
			}
			$string = $tokens[ $j ];
			++$j;
			while ( $j < $count && wp_filter_style_is_trivia( $tokens[ $j ] ) ) {
				++$j;
			}
			if ( $j < $count && WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN !== $tokens[ $j ]['type'] ) {
				return false;
			}
			if ( ! wp_filter_style_is_url_allowed( $string['value'], $allowed_protocols ) ) {
				return false;
			}
			$i = $j;
			continue;
		}

		if ( ! in_array( $function_name, $allowed_functions, true ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Checks a decoded URL against the allowed protocols.
 *
 * @param string|null $url               Decoded URL.
 * @param array       $allowed_protocols Allowed URL protocols.
 * @return bool Whether the URL is non-empty and passes wp_kses_bad_protocol().
 */
function wp_filter_style_is_url_allowed( ?string $url, array $allowed_protocols ): bool {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return false;
	}

	return wp_kses_bad_protocol( $url, $allowed_protocols ) === $url;
}

/**
 * Whether a token is whitespace or a comment.
 *
 * @param array $token Token.
 * @return bool Whether the token is trivia.
 */
function wp_filter_style_is_trivia( array $token ): bool {
	return WP_CSS_Token_Processor::TOKEN_WHITESPACE === $token['type'] || WP_CSS_Token_Processor::TOKEN_COMMENT === $token['type'];
}

/**
 * Serializes a declaration value from its tokens.
 *
 * Whitespace and comment runs become one space. Strings and URLs are
 * re-escaped from their decoded values. Identifiers and function names are
 * re-escaped from their decoded names. Other tokens are copied from source.
 * Blocks left open at the end of the value are closed, so the output never
 * absorbs the following `;`.
 *
 * @param string $css    Decoded CSS the token offsets index into.
 * @param array  $tokens Value tokens from the processor.
 * @return string Serialized value.
 */
function wp_filter_style_serialize_value( string $css, array $tokens ): string {
	$output        = '';
	$pending_space = false;
	$open_blocks   = array();

	foreach ( $tokens as $token ) {
		if ( wp_filter_style_is_trivia( $token ) ) {
			$pending_space = '' !== $output;
			continue;
		}

		if ( $pending_space ) {
			$output       .= ' ';
			$pending_space = false;
		}

		switch ( $token['type'] ) {
			case WP_CSS_Token_Processor::TOKEN_LEFT_PAREN:
				$open_blocks[] = ')';
				break;
			case WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET:
				$open_blocks[] = ']';
				break;
			case WP_CSS_Token_Processor::TOKEN_LEFT_BRACE:
				$open_blocks[] = '}';
				break;
			case WP_CSS_Token_Processor::TOKEN_FUNCTION:
				$open_blocks[] = ')';
				break;
			case WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN:
			case WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET:
			case WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE:
				// The processor rejected values with unmatched closers.
				array_pop( $open_blocks );
				break;
		}

		switch ( $token['type'] ) {
			case WP_CSS_Token_Processor::TOKEN_STRING:
				$output .= WP_CSS_Builder::string( (string) $token['value'] );
				break;

			case WP_CSS_Token_Processor::TOKEN_URL:
				$output .= 'url(' . WP_CSS_Builder::string( (string) $token['value'] ) . ')';
				break;

			case WP_CSS_Token_Processor::TOKEN_FUNCTION:
				$output .= WP_CSS_Builder::ident( (string) $token['value'] ) . '(';
				break;

			case WP_CSS_Token_Processor::TOKEN_IDENT:
				$output .= WP_CSS_Builder::ident( (string) $token['value'] );
				break;

			default:
				$output .= substr( $css, $token['start'], $token['length'] );
				break;
		}
	}

	return $output . implode( '', array_reverse( $open_blocks ) );
}

/**
 * Default allowed properties, copied from safecss_filter_attr().
 *
 * The prototype duplicates the list so kses.php stays untouched. The final
 * implementation shares one list.
 *
 * @return string[] Allowed property names.
 */
function wp_filter_style_default_allowed_properties(): array {
	return array(
		'background',
		'background-color',
		'background-image',
		'background-position',
		'background-repeat',
		'background-size',
		'background-attachment',
		'background-blend-mode',

		'border',
		'border-radius',
		'border-width',
		'border-color',
		'border-style',
		'border-right',
		'border-right-color',
		'border-right-style',
		'border-right-width',
		'border-bottom',
		'border-bottom-color',
		'border-bottom-left-radius',
		'border-bottom-right-radius',
		'border-bottom-style',
		'border-bottom-width',
		'border-bottom-right-radius',
		'border-bottom-left-radius',
		'border-left',
		'border-left-color',
		'border-left-style',
		'border-left-width',
		'border-top',
		'border-top-color',
		'border-top-left-radius',
		'border-top-right-radius',
		'border-top-style',
		'border-top-width',
		'border-top-left-radius',
		'border-top-right-radius',

		'border-spacing',
		'border-collapse',
		'caption-side',

		'columns',
		'column-count',
		'column-fill',
		'column-gap',
		'column-rule',
		'column-span',
		'column-width',

		'display',

		'color',
		'filter',
		'font',
		'font-family',
		'font-size',
		'font-style',
		'font-variant',
		'font-weight',
		'letter-spacing',
		'line-height',
		'text-align',
		'text-decoration',
		'text-indent',
		'text-transform',
		'white-space',

		'height',
		'min-height',
		'max-height',

		'width',
		'min-width',
		'max-width',

		'margin',
		'margin-right',
		'margin-bottom',
		'margin-left',
		'margin-top',
		'margin-block-start',
		'margin-block-end',
		'margin-inline-start',
		'margin-inline-end',

		'padding',
		'padding-right',
		'padding-bottom',
		'padding-left',
		'padding-top',
		'padding-block-start',
		'padding-block-end',
		'padding-inline-start',
		'padding-inline-end',

		'flex',
		'flex-basis',
		'flex-direction',
		'flex-flow',
		'flex-grow',
		'flex-shrink',
		'flex-wrap',

		'gap',
		'column-gap',
		'row-gap',

		'grid-template-columns',
		'grid-auto-columns',
		'grid-column-start',
		'grid-column-end',
		'grid-column',
		'grid-column-gap',
		'grid-template-rows',
		'grid-auto-rows',
		'grid-row-start',
		'grid-row-end',
		'grid-row',
		'grid-row-gap',
		'grid-gap',

		'justify-content',
		'justify-items',
		'justify-self',
		'align-content',
		'align-items',
		'align-self',

		'clear',
		'cursor',
		'direction',
		'float',
		'list-style-type',
		'object-fit',
		'object-position',
		'opacity',
		'overflow',
		'vertical-align',
		'writing-mode',

		'position',
		'top',
		'right',
		'bottom',
		'left',
		'z-index',
		'box-shadow',
		'aspect-ratio',
		'container-type',

		'fill',
		'fill-opacity',
		'fill-rule',

		'stroke',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-miterlimit',
		'stroke-opacity',
		'stroke-width',

		'color-interpolation',
		'color-interpolation-filters',
		'paint-order',
		'stop-color',
		'stop-opacity',
		'flood-color',
		'flood-opacity',
		'lighting-color',

		'marker',
		'marker-end',
		'marker-mid',
		'marker-start',

		'clip-path',
		'clip-rule',
		'mask',
		'mask-type',

		'cx',
		'cy',
		'r',
		'rx',
		'ry',
		'x',
		'y',
		'd',

		'alignment-baseline',
		'baseline-shift',
		'dominant-baseline',
		'glyph-orientation-horizontal',
		'glyph-orientation-vertical',
		'text-anchor',
		'unicode-bidi',
		'word-spacing',

		'font-size-adjust',
		'font-stretch',

		'color-rendering',
		'image-rendering',
		'shape-rendering',
		'text-rendering',
		'vector-effect',

		'transform',
		'transform-origin',

		'pointer-events',
		'visibility',

		'anchor-name',
		'anchor-scope',
		'position-anchor',
		'position-area',
		'position-try',
		'position-try-fallbacks',
		'position-try-order',
		'position-visibility',

		// Custom CSS properties.
		'--*',
	);
}
