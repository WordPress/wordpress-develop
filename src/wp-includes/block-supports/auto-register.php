<?php
/**
 * Auto-register block support.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Marks user-defined attributes for auto-generated inspector controls.
 *
 * This filter runs during block type registration, before the WP_Block_Type
 * is instantiated. Block supports add their attributes AFTER the block type
 * is created (via {@see WP_Block_Supports::register_attributes()}), so any attributes
 * present at this stage are user-defined.
 *
 * The marker tells generateFieldsFromAttributes() which attributes should
 * get auto-generated inspector controls. Attributes are excluded if they:
 * - Have a 'source' (HTML-derived, edited inline not via inspector)
 * - Have role 'local' (internal state, not user-configurable)
 * - Have an unsupported type (only 'string', 'number', 'integer', 'boolean' are supported)
 * - Were added by block supports (added after this filter runs)
 *
 * @since 7.0.0
 * @access private
 *
 * @param array<string, mixed> $args Array of arguments for registering a block type.
 * @return array<string, mixed> Modified block type arguments.
 */
function wp_mark_auto_generate_control_attributes( array $args ): array {
	if ( empty( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
		return $args;
	}

	$has_auto_register = ! empty( $args['supports']['autoRegister'] );
	if ( ! $has_auto_register ) {
		return $args;
	}

	foreach ( $args['attributes'] as $attr_key => $attr_schema ) {
		// Skip HTML-derived attributes (edited inline, not via inspector).
		if ( ! empty( $attr_schema['source'] ) ) {
			continue;
		}
		// Skip internal attributes (not user-configurable).
		if ( isset( $attr_schema['role'] ) && 'local' === $attr_schema['role'] ) {
			continue;
		}
		// Skip unsupported types (only 'string', 'number', 'integer', 'boolean' are supported).
		$type = $attr_schema['type'] ?? null;
		if ( ! in_array( $type, array( 'string', 'number', 'integer', 'boolean' ), true ) ) {
			continue;
		}
		$args['attributes'][ $attr_key ]['autoGenerateControl'] = true;
	}

	return $args;
}

// Priority 5 to mark original attributes before other filters (priority 10+) might add their own.
add_filter( 'register_block_type_args', 'wp_mark_auto_generate_control_attributes', 5 );

/**
 * Configures server-side rendering for auto-registered blocks with a pattern.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array<string, mixed> $args       Arguments passed to `register_block_type()`.
 * @param string               $block_name Block type name.
 * @return array<string, mixed> Filtered arguments.
 */
function wp_apply_pattern_block_rendering( array $args, string $block_name ): array {
	if (
		empty( $args['supports']['autoRegister'] ) ||
		! is_string( $args['pattern'] ?? null ) ||
		'' === $args['pattern']
	) {
		return $args;
	}

	if ( ! empty( $args['render_callback'] ) ) {
		_doing_it_wrong(
			'register_block_type',
			sprintf(
				/* translators: %s: Block name. */
				__( 'Block type "%s" was registered with both a pattern and a render callback. The pattern takes precedence, so the render callback is ignored.' ),
				$block_name
			),
			'7.1.0'
		);
	}

	// Pattern overrides use `content`, so replace any existing schema.
	$args['attributes']['content'] = array( 'type' => 'object' );
	if ( ! isset( $args['provides_context'] ) ) {
		$args['provides_context'] = array();
	}
	$args['provides_context']['pattern/overrides'] = 'content';

	// Disable HTML editing by default because the pattern is not saved with the block.
	if ( ! isset( $args['supports']['html'] ) ) {
		$args['supports']['html'] = false;
	}

	// Ignore inner blocks from saved content because only the registered pattern should render.
	$args['skip_inner_blocks'] = true;
	$args['render_callback']   = static function ( $attributes, $content, $block ) {
		// A pattern can contain another instance of the same block. Render that nested
		// instance as empty, matching `core/block`, to avoid infinite recursion.
		static $rendering = array();
		if ( isset( $rendering[ $block->name ] ) ) {
			return '';
		}
		$rendering[ $block->name ] = true;

		// Read the current pattern at render time, then rebuild its children so they
		// inherit the host block's override context.
		$pattern = $block->block_type->pattern;
		if ( ! is_string( $pattern ) || '' === $pattern ) {
			$pattern = '';
		}

		// `WP_Embed` processes embeds on `the_content` before `do_blocks()` runs,
		// so the pattern, injected during `do_blocks()`, never goes through those
		// filters and its embed URLs would render as plain links.
		global $wp_embed;
		$pattern = $wp_embed->run_shortcode( $pattern );
		$pattern = $wp_embed->autoembed( $pattern );

		$block->parsed_block['innerBlocks']  = parse_blocks( $pattern );
		$block->parsed_block['innerContent'] = array_fill(
			0,
			count( $block->parsed_block['innerBlocks'] ),
			null
		);
		// `refresh_context_dependents()` does not clear existing inner blocks
		// when the new parsed list is empty.
		$block->inner_blocks = array();
		$block->refresh_context_dependents();

		// Apply the same child filters as `WP_Block::render()` without rendering the
		// host block or applying its `render_block` filters a second time.
		$output = '';
		foreach ( $block->inner_blocks as $inner_block ) {
			/** This filter is documented in wp-includes/blocks.php */
			$pre_render = apply_filters( 'pre_render_block', null, $inner_block->parsed_block, $block );
			if ( null !== $pre_render ) {
				$output .= $pre_render;
				continue;
			}

			$source_block        = $inner_block->parsed_block;
			$inner_block_context = $inner_block->context;

			/** This filter is documented in wp-includes/blocks.php */
			$inner_block->parsed_block = apply_filters(
				'render_block_data',
				$inner_block->parsed_block,
				$source_block,
				$block
			);

			/** This filter is documented in wp-includes/blocks.php */
			$inner_block->context = apply_filters(
				'render_block_context',
				$inner_block->context,
				$inner_block->parsed_block,
				$block
			);

			if ( $inner_block->context !== $inner_block_context ) {
				$inner_block->refresh_context_dependents();
			} elseif ( $inner_block->parsed_block !== $source_block ) {
				$inner_block->refresh_parsed_block_dependents();
			}

			$output .= $inner_block->render();
		}

		unset( $rendering[ $block->name ] );

		return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes(), $output );
	};

	return $args;
}
add_filter( 'register_block_type_args', 'wp_apply_pattern_block_rendering', 10, 2 );
