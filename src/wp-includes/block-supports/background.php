<?php
/**
 * Background block support flag.
 *
 * @package WordPress
 * @since 6.4.0
 */

/**
 * Registers the style block attribute for block types that support it.
 *
 * @since 6.4.0
 * @access private
 *
 * @param WP_Block_Type $block_type Block Type.
 */
function wp_register_background_support( $block_type ) {
	// Setup attributes and styles within that if needed.
	if ( ! $block_type->attributes ) {
		$block_type->attributes = array();
	}

	// Check for existing style attribute definition e.g. from block.json.
	if ( array_key_exists( 'style', $block_type->attributes ) ) {
		return;
	}

	$has_background_support = block_has_support( $block_type, array( 'background' ), false );

	if ( $has_background_support ) {
		$block_type->attributes['style'] = array(
			'type' => 'object',
		);
	}
}

/**
 * Renders the background styles to the block wrapper.
 * This block support uses the `render_block` hook to ensure that
 * it is also applied to non-server-rendered blocks.
 *
 * @since 6.4.0
 * @since 6.5.0 Added support for `backgroundPosition` and `backgroundRepeat` output.
 * @since 6.6.0 Removed requirement for `backgroundImage.source`. A file/url is the default.
 * @since 6.7.0 Added support for `backgroundAttachment` output.
 * @since 6.8.0 Added support for rendering background images as <img> elements.
 *
 * @access private
 *
 * @param  string $block_content Rendered block content.
 * @param  array  $block         Block object.
 * @return string Filtered block content.
 */
function wp_render_background_support( $block_content, $block ) {
	$block_type                   = WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );
	$block_attributes             = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) ? $block['attrs'] : array();
	$has_background_image_support = block_has_support( $block_type, array( 'background', 'backgroundImage' ), false );

	if (
		! $has_background_image_support ||
		wp_should_skip_block_supports_serialization( $block_type, 'background', 'backgroundImage' ) ||
		! isset( $block_attributes['style']['background'] )
	) {
		return $block_content;
	}

	$background_styles                         = array();
	$background_styles['backgroundImage']      = $block_attributes['style']['background']['backgroundImage'] ?? null;
	$background_styles['backgroundSize']       = $block_attributes['style']['background']['backgroundSize'] ?? null;
	$background_styles['backgroundPosition']   = $block_attributes['style']['background']['backgroundPosition'] ?? null;
	$background_styles['backgroundRepeat']     = $block_attributes['style']['background']['backgroundRepeat'] ?? null;
	$background_styles['backgroundAttachment'] = $block_attributes['style']['background']['backgroundAttachment'] ?? null;

	if ( ! empty( $background_styles['backgroundImage'] ) ) {
		$background_styles['backgroundSize'] = $background_styles['backgroundSize'] ?? 'cover';

		// If the background size is set to `contain` and no position is set, set the position to `center`.
		if ( 'contain' === $background_styles['backgroundSize'] && ! $background_styles['backgroundPosition'] ) {
			$background_styles['backgroundPosition'] = '50% 50%';
		}
	}

	/*
	 * Use an <img> element for media library images with cover/contain sizing and no tiling,
	 * so the browser can benefit from srcset, sizes, loading="lazy", and decoding="async".
	 * 'no-repeat' is treated the same as unset — both are compatible with object-fit.
	 * Only explicit tiling values (repeat, repeat-x, repeat-y) require CSS background-image.
	 */
	$attachment_id = is_array( $background_styles['backgroundImage'] )
		? (int) ( $background_styles['backgroundImage']['id'] ?? 0 )
		: 0;

	$use_img_element = (
		$attachment_id > 0 &&
		in_array( $background_styles['backgroundSize'], array( 'cover', 'contain' ), true ) &&
		( empty( $background_styles['backgroundRepeat'] ) || 'no-repeat' === $background_styles['backgroundRepeat'] ) &&
		empty( $background_styles['backgroundAttachment'] )
	);

	if ( $use_img_element ) {
		$object_fit      = $background_styles['backgroundSize'];
		$object_position = $background_styles['backgroundPosition'] ?? null;

		$img_style = 'position:absolute;top:0;left:0;right:0;bottom:0;margin:0;padding:0;width:100%;height:100%;max-width:none;max-height:none;pointer-events:none;object-fit:' . esc_attr( $object_fit ) . ';';
		if ( $object_position ) {
			$img_style .= 'object-position:' . esc_attr( $object_position ) . ';';
		}

		$img_attrs = array(
			'class'           => 'wp-block__background-image',
			'style'           => $img_style,
			'alt'             => '',
			'aria-hidden'     => 'true',
			'data-object-fit' => $object_fit,
			'loading'         => 'lazy',
			'decoding'        => 'async',
		);
		if ( $object_position ) {
			$img_attrs['data-object-position'] = $object_position;
		}

		$img_html = wp_get_attachment_image( $attachment_id, 'full', false, $img_attrs );

		if ( $img_html ) {
			$tags = new WP_HTML_Tag_Processor( $block_content );
			if ( $tags->next_tag() ) {
				$tag_name       = strtolower( $tags->get_tag() );
				$existing_style = $tags->get_attribute( 'style' );

				if ( is_string( $existing_style ) && '' !== $existing_style ) {
					$separator = str_ends_with( $existing_style, ';' ) ? '' : ';';
					// Only add position:relative when there is no existing position rule.
					if ( ! preg_match( '/(?:^|;)\s*position\s*:/', $existing_style ) ) {
						$wrapper_style = $existing_style . $separator . 'position:relative;';
					} else {
						$wrapper_style = rtrim( $existing_style, ';' ) . ';';
					}
				} else {
					$wrapper_style = 'position:relative;';
				}

				$tags->set_attribute( 'style', $wrapper_style );
				$tags->add_class( 'has-background' );
			}
			$modified_content = $tags->get_updated_html();

			// Insert the img as the first child of the wrapper element.
			$open_tag_pattern = sprintf( '/^(\s*<%s[^>]*>)/i', preg_quote( $tag_name, '/' ) );
			if ( 1 === preg_match( $open_tag_pattern, $modified_content, $matches, PREG_OFFSET_CAPTURE ) ) {
				$insert_at        = $matches[0][1] + strlen( $matches[0][0] );
				$modified_content = substr( $modified_content, 0, $insert_at ) . $img_html . substr( $modified_content, $insert_at );
			}

			return $modified_content;
		}
	}

	$styles = wp_style_engine_get_styles( array( 'background' => $background_styles ) );

	if ( ! empty( $styles['css'] ) ) {
		// Inject background styles to the first element, presuming it's the wrapper, if it exists.
		$tags = new WP_HTML_Tag_Processor( $block_content );

		if ( $tags->next_tag() ) {
			$existing_style = $tags->get_attribute( 'style' );
			if ( is_string( $existing_style ) && '' !== $existing_style ) {
				$separator     = str_ends_with( $existing_style, ';' ) ? '' : ';';
				$updated_style = "{$existing_style}{$separator}{$styles['css']}";
			} else {
				$updated_style = $styles['css'];
			}

			$tags->set_attribute( 'style', $updated_style );
			$tags->add_class( 'has-background' );
		}

		return $tags->get_updated_html();
	}

	return $block_content;
}

// Register the block support.
WP_Block_Supports::get_instance()->register(
	'background',
	array(
		'register_attribute' => 'wp_register_background_support',
	)
);

add_filter( 'render_block', 'wp_render_background_support', 10, 2 );
