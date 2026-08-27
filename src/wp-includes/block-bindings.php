<?php
/**
 * Block Bindings API
 *
 * Contains functions for managing block bindings in WordPress.
 *
 * @package WordPress
 * @subpackage Block Bindings
 * @since 6.5.0
 */

/**
 * Registers a new block bindings source.
 *
 * Registering a source consists of defining a **name** for that source and a callback function specifying
 * how to get a value from that source and pass it to a block attribute.
 *
 * Once a source is registered, any block that supports the Block Bindings API can use a value
 * from that source by setting its `metadata.bindings` attribute to a value that refers to the source.
 *
 * Note that `register_block_bindings_source()` should be called from a handler attached to the `init` hook.
 *
 *
 * ## Example
 *
 * ### Registering a source
 *
 * First, you need to define a function that will be used to get the value from the source.
 *
 *     function my_plugin_get_custom_source_value( array $source_args, $block_instance, string $attribute_name ) {
 *       // Your custom logic to get the value from the source.
 *       // For example, you can use the `$source_args` to look up a value in a custom table or get it from an external API.
 *       $value = $source_args['key'];
 *
 *       return "The value passed to the block is: $value"
 *     }
 *
 * The `$source_args` will contain the arguments passed to the source in the block's
 * `metadata.bindings` attribute. See the example in the "Usage in a block" section below.
 *
 *     function my_plugin_register_block_bindings_sources() {
 *       register_block_bindings_source( 'my-plugin/my-custom-source', array(
 *         'label'              => __( 'My Custom Source', 'my-plugin' ),
 *         'get_value_callback' => 'my_plugin_get_custom_source_value',
 *       ) );
 *     }
 *     add_action( 'init', 'my_plugin_register_block_bindings_sources' );
 *
 * ### Usage in a block
 *
 * In a block's `metadata.bindings` attribute, you can specify the source and
 * its arguments. Such a block will use the source to override the block
 * attribute's value. For example:
 *
 *     <!-- wp:paragraph {
 *       "metadata": {
 *         "bindings": {
 *           "content": {
 *             "source": "my-plugin/my-custom-source",
 *             "args": {
 *               "key": "you can pass any custom arguments here"
 *             }
 *           }
 *         }
 *       }
 *     } -->
 *     <p>Fallback text that gets replaced.</p>
 *     <!-- /wp:paragraph -->
 *
 * @since 6.5.0
 *
 * @param string $source_name       The name of the source. It must be a string containing a namespace prefix, i.e.
 *                                  `my-plugin/my-custom-source`. It must only contain lowercase alphanumeric
 *                                  characters, the forward slash `/` and dashes.
 * @param array  $source_properties {
 *     The array of arguments that are used to register a source.
 *
 *     @type string   $label              The label of the source.
 *     @type callable $get_value_callback A callback executed when the source is processed during block rendering.
 *                                        The callback should have the following signature:
 *
 *                                        `function( $source_args, $block_instance, $attribute_name ): mixed`
 *                                            - @param array    $source_args    Array containing source arguments
 *                                                                              used to look up the override value,
 *                                                                              i.e. {"key": "foo"}.
 *                                            - @param WP_Block $block_instance The block instance.
 *                                            - @param string   $attribute_name The name of an attribute.
 *                                        The callback has a mixed return type; it may return a string to override
 *                                        the block's original value, null, false to remove an attribute, etc.
 *     @type string[] $uses_context       Optional. Array of values to add to block `uses_context` needed by the source.
 * }
 * @return WP_Block_Bindings_Source|false Source when the registration was successful, or `false` on failure.
 */
function register_block_bindings_source( string $source_name, array $source_properties ) {
	return WP_Block_Bindings_Registry::get_instance()->register( $source_name, $source_properties );
}

/**
 * Unregisters a block bindings source.
 *
 * @since 6.5.0
 *
 * @param string $source_name Block bindings source name including namespace.
 * @return WP_Block_Bindings_Source|false The unregistered block bindings source on success and `false` otherwise.
 */
function unregister_block_bindings_source( string $source_name ) {
	return WP_Block_Bindings_Registry::get_instance()->unregister( $source_name );
}

/**
 * Retrieves the list of all registered block bindings sources.
 *
 * @since 6.5.0
 *
 * @return WP_Block_Bindings_Source[] The array of registered block bindings sources.
 */
function get_all_registered_block_bindings_sources() {
	return WP_Block_Bindings_Registry::get_instance()->get_all_registered();
}

/**
 * Retrieves a registered block bindings source.
 *
 * @since 6.5.0
 *
 * @param string $source_name The name of the source.
 * @return WP_Block_Bindings_Source|null The registered block bindings source, or `null` if it is not registered.
 */
function get_block_bindings_source( string $source_name ) {
	return WP_Block_Bindings_Registry::get_instance()->get_registered( $source_name );
}

/**
 * Retrieves the list of block attributes supported by block bindings.
 *
 * @since 6.9.0
 * @since 7.1.0 Added support for the List Item block.
 *
 * @param string $block_type The block type whose supported attributes are being retrieved.
 * @return array The list of block attributes that are supported by block bindings.
 */
function get_block_bindings_supported_attributes( $block_type ) {
	$block_bindings_supported_attributes = array(
		'core/paragraph'          => array( 'content' ),
		'core/heading'            => array( 'content' ),
		'core/list-item'          => array( 'content' ),
		'core/image'              => array( 'id', 'url', 'title', 'alt', 'caption' ),
		'core/button'             => array( 'url', 'text', 'linkTarget', 'rel' ),
		'core/post-date'          => array( 'datetime' ),
		'core/navigation-link'    => array( 'url' ),
		'core/navigation-submenu' => array( 'url' ),
	);

	$supported_block_attributes =
		isset( $block_type, $block_bindings_supported_attributes[ $block_type ] ) ?
			$block_bindings_supported_attributes[ $block_type ] :
			array();

	/**
	 * Filters the supported block attributes for block bindings.
	 *
	 * @since 6.9.0
	 *
	 * @param string[] $supported_block_attributes The block's attributes that are supported by block bindings.
	 * @param string   $block_type                 The block type whose attributes are being filtered.
	 */
	$supported_block_attributes = apply_filters(
		'block_bindings_supported_attributes',
		$supported_block_attributes,
		$block_type
	);

	/**
	 * Filters the supported block attributes for block bindings.
	 *
	 * The dynamic portion of the hook name, `$block_type`, refers to the block type
	 * whose attributes are being filtered.
	 *
	 * @since 6.9.0
	 *
	 * @param string[] $supported_block_attributes The block's attributes that are supported by block bindings.
	 */
	$supported_block_attributes = apply_filters(
		"block_bindings_supported_attributes_{$block_type}",
		$supported_block_attributes
	);

	return $supported_block_attributes;
}

/*
 * Substitutes a block's inner blocks from a bound source on the server.
 *
 * When a block declares an `innerBlocks` binding in its
 * `metadata.bindings`, the bound source supplies the block's child tree as a
 * serialized block-markup string. The filters below resolve that value before
 * the block renders and replace the parsed block's `innerBlocks` and
 * `innerContent`, so the frontend renders the source-supplied tree (with nested
 * bindings resolved recursively by Core) instead of the block's own serialized
 * children.
 */

if ( ! defined( 'WP_BLOCK_BINDINGS_INNER_BLOCKS_ANCESTRY' ) ) {
	/**
	 * Context key carrying the guard keys of the bound inner-block areas that are
	 * ancestors of the block currently being rendered.
	 *
	 * Ancestry travels inside block context, so it is naturally scoped to the
	 * currently-rendering subtree: it dies with the subtree and cannot leak
	 * request-wide, and it survives third-party `render_block_data` filters that
	 * rebuild the parsed-block array, because it lives on the `WP_Block`
	 * instance's context rather than on the parsed block.
	 *
	 * @since 7.1.0
	 */
	define( 'WP_BLOCK_BINDINGS_INNER_BLOCKS_ANCESTRY', 'core/innerBlocksBindingAncestry' );
}

if ( ! defined( 'WP_BLOCK_BINDINGS_MAX_BOUND_DEPTH' ) ) {
	/**
	 * Hard cap on the nesting depth of bound inner-block areas.
	 *
	 * Context-carried ancestry cannot cross context boundaries (`do_blocks()`,
	 * `core/post-template`'s per-iteration render), so a request-global depth
	 * counter guarantees termination for cycles that cross them.
	 *
	 * @since 7.1.0
	 */
	define( 'WP_BLOCK_BINDINGS_MAX_BOUND_DEPTH', 32 );
}

/**
 * Returns a block's `innerBlocks` binding descriptor, or null when absent/invalid.
 *
 * The same check gates the substitution, the top-level takeover, and the
 * depth-counter decrement, so pairing between them is re-derived from the
 * block's own attributes and survives parsed-block rebuilds by third-party
 * `render_block_data` filters.
 *
 * @since 7.1.0
 *
 * @param array $parsed_block The parsed block.
 * @return array|null The binding descriptor (with a non-empty string `source`), or null.
 */
function _block_bindings_get_inner_blocks_binding( $parsed_block ) {
	$binding = $parsed_block['attrs']['metadata']['bindings']['innerBlocks'] ?? null;
	if ( ! is_array( $binding ) || empty( $binding['source'] ) || ! is_string( $binding['source'] ) ) {
		return null;
	}
	return $binding;
}

/**
 * Builds the recursion-guard key for an `innerBlocks` binding.
 *
 * The key combines the source name, the binding arguments, and the resolved
 * values of the context the source declares in `uses_context`. A nested binding
 * is a cycle only when all three match: a context-dependent source (e.g. keyed
 * by `postId`) legitimately appears twice when a bound area renders another post
 * containing a block bound to the same source and args, and must not be flagged.
 * A source with empty `uses_context` is context-independent by declaration, so
 * the same source and args nested is a true cycle.
 *
 * @since 7.1.0
 *
 * @param string                        $source_name       The binding source name.
 * @param array                         $source_args       The binding arguments.
 * @param WP_Block_Bindings_Source      $source            The registered source.
 * @param array                         $available_context The bound block's available context.
 * @return string The guard key.
 */
function _block_bindings_get_binding_guard_key( $source_name, $source_args, $source, $available_context ) {
	$context_subset = array();
	if ( ! empty( $source->uses_context ) ) {
		foreach ( $source->uses_context as $context_name ) {
			if ( array_key_exists( $context_name, $available_context ) ) {
				$context_subset[ $context_name ] = $available_context[ $context_name ];
			}
		}
	}

	ksort( $source_args );
	ksort( $context_subset );

	return $source_name . '|' . wp_json_encode( $source_args ) . '|' . wp_json_encode( $context_subset );
}

/**
 * Returns (by reference) the number of bound inner-block areas currently rendering.
 *
 * Incremented by `_block_bindings_replace_inner_blocks()` for every
 * block carrying an `innerBlocks` binding descriptor and decremented by
 * `_block_bindings_close_bound_block()` once such a block has rendered.
 * During a runaway descent no render completes, so no decrements occur and the
 * counter grows with bound depth, guaranteeing a hard stop at
 * `WP_BLOCK_BINDINGS_MAX_BOUND_DEPTH` even when the cycle crosses context
 * boundaries the ancestry cannot follow.
 *
 * @since 7.1.0
 *
 * @return int The current bound-render depth.
 */
function &_block_bindings_bound_render_depth() {
	static $depth = 0;
	return $depth;
}

/**
 * Returns the default context used when render_block() renders a top-level block.
 *
 * Mirrors core's context setup in render_block(): the global-post defaults plus
 * the `render_block_context` filter. Only used as a fallback when a nested bound
 * block's `WP_Block` instance cannot be located on its parent (top-level blocks
 * are handled by `_block_bindings_render_top_level_bound_block()`,
 * which applies the filter itself). This fallback is the sole remaining spot
 * where `render_block_context` may run an extra time for a block; the branch is
 * practically unreachable in core's render loop.
 *
 * @since 7.1.0
 *
 * @param array $parsed_block The parsed block being rendered.
 * @return array Default block context.
 */
function _block_bindings_get_default_context( $parsed_block ) {
	global $post;

	$default_context = array();

	if ( $post instanceof WP_Post ) {
		$default_context = array(
			'postId'   => $post->ID,
			'postType' => $post->post_type,
		);
	}

	/** This filter is documented in wp-includes/blocks.php */
	$default_context = apply_filters( 'render_block_context', $default_context, $parsed_block, null );

	return is_array( $default_context ) ? $default_context : array();
}

/**
 * Reads a WP_Block instance's inherited available context.
 *
 * @since 7.1.0
 *
 * @param WP_Block $block The block instance.
 * @return array The block's available context.
 */
function _block_bindings_get_available_context( $block ) {
	static $get_available_context = null;

	if ( ! $block instanceof WP_Block ) {
		return array();
	}

	if ( null === $get_available_context ) {
		/*
		 * Sources can request context inherited through ancestors that the bound
		 * block itself does not declare in `uses_context`. The protected
		 * `available_context` property is the faithful source for those values.
		 */
		$get_available_context = Closure::bind(
			static function ( $block_instance ) {
				return $block_instance->available_context;
			},
			null,
			WP_Block::class
		);
	}

	$available_context = $get_available_context( $block );
	return is_array( $available_context ) ? $available_context : array();
}

/**
 * Finds the WP_Block instance currently being filtered inside its parent.
 *
 * `WP_Block_List::offsetGet()` caches the constructed instance, so the object
 * returned here is the same object core's render loop renders — mutating its
 * context propagates to the block's (substituted) children.
 *
 * @since 7.1.0
 *
 * @param array    $source_block The unmodified parsed block.
 * @param WP_Block $parent_block The parent block instance.
 * @return WP_Block|null The matching child instance, or null.
 */
function _block_bindings_find_inner_block_instance( $source_block, $parent_block ) {
	if ( ! $parent_block instanceof WP_Block || empty( $parent_block->inner_blocks ) ) {
		return null;
	}

	foreach ( $parent_block->inner_blocks as $inner_block ) {
		if ( $inner_block instanceof WP_Block && $inner_block->parsed_block === $source_block ) {
			return $inner_block;
		}
	}

	return null;
}

/**
 * Rebuilds a block's `innerContent` template around a new set of inner blocks.
 *
 * A block's `innerContent` is a template array that interleaves the block's own
 * static HTML chunks (strings) with one `null` placeholder per inner block, in
 * render order. For example `core/group` parses to
 * `array( '<div…>', null, null, '</div>' )` (the wrapper chunks plus one `null`
 * per child) and `core/list-item` to `array( '<li>…', null, '</li>' )`.
 *
 * When inner blocks are substituted from a binding source, the host block's own
 * static chunks (its chrome, such as the `<div>`/`</div>` or `<li>`/`</li>`
 * wrapper) must be preserved while the `null` placeholders are replaced to match
 * the NEW inner blocks (whose count may differ from the original). This keeps the
 * leading static chunk(s) before the first placeholder and the trailing static
 * chunk(s) after the last placeholder, emitting exactly one `null` per new inner
 * block in between.
 *
 * Edge cases:
 *
 * - Original template with no `null` placeholder (e.g. an empty container that
 *   parsed to a single closed-wrapper chunk): inserts the new placeholders before
 *   the final closing tag when one can be identified, so sourced children still
 *   render inside the host wrapper. If no closing tag is available, the new
 *   placeholders are appended after all existing static chunks.
 * - Original template that is empty (no static chunks and no placeholders): falls
 *   back to one `null` per new inner block, with no surrounding static chunks.
 * - Zero new inner blocks (an intentionally-empty area): only the host's static
 *   chunks remain, so the wrapper renders empty.
 *
 * @since 7.1.0
 *
 * @param array $original_inner_content The host block's existing `innerContent` template.
 * @param int   $new_block_count        The number of substituted inner blocks.
 * @return array The rebuilt `innerContent` template: the host's static chunks with
 *               exactly `$new_block_count` `null` placeholders in inner-block position.
 */
function _block_bindings_rebuild_inner_content( $original_inner_content, $new_block_count ) {
	if ( ! is_array( $original_inner_content ) ) {
		$original_inner_content = array();
	}

	$placeholders = array_fill( 0, $new_block_count, null );

	// Locate the original inner-block placeholders (the `null` slots).
	$null_positions = array();
	foreach ( $original_inner_content as $index => $chunk ) {
		if ( null === $chunk ) {
			$null_positions[] = $index;
		}
	}

	/*
	 * No placeholder to anchor on. Empty containers such as an empty Group parse
	 * to a single closed-wrapper chunk (`<div ...></div>`), so insert the new
	 * slots before the final closing tag when possible. This preserves the host
	 * wrapper around source-supplied children even when the serialized fallback
	 * originally had no children.
	 */
	if ( empty( $null_positions ) ) {
		for ( $index = count( $original_inner_content ) - 1; $index >= 0; $index-- ) {
			$chunk = $original_inner_content[ $index ];
			if ( ! is_string( $chunk ) || ! preg_match( '#</[A-Za-z][^>]*>\s*$#', $chunk, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			$closing_offset = $matches[0][1];
			$leading        = array_slice( $original_inner_content, 0, $index );
			$before_closer  = substr( $chunk, 0, $closing_offset );
			$after_closer   = substr( $chunk, $closing_offset );
			if ( '' !== $before_closer ) {
				$leading[] = $before_closer;
			}

			$trailing = array();
			if ( '' !== $after_closer ) {
				$trailing[] = $after_closer;
			}
			$trailing = array_merge( $trailing, array_slice( $original_inner_content, $index + 1 ) );

			return array_merge( $leading, $placeholders, $trailing );
		}

		// No usable closing tag: keep any static chunks, then append the new slots.
		return array_merge( array_values( $original_inner_content ), $placeholders );
	}

	$first_null = $null_positions[0];
	$last_null  = $null_positions[ count( $null_positions ) - 1 ];

	// Preserve the leading static chunks (before the first placeholder) and the
	// trailing static chunks (after the last placeholder); replace everything in
	// between with one placeholder per new inner block.
	$leading  = array_slice( $original_inner_content, 0, $first_null );
	$trailing = array_slice( $original_inner_content, $last_null + 1 );

	return array_merge( $leading, $placeholders, $trailing );
}

/**
 * Resolves a block's `innerBlocks` binding and substitutes the parsed block's
 * inner blocks.
 *
 * Shared by the nested-block substitution
 * (`_block_bindings_replace_inner_blocks()`) and the top-level takeover
 * (`_block_bindings_render_top_level_bound_block()`). Constructs a
 * transient `WP_Block` (so the source can read both the block's attributes and
 * context), calls the source's `get_value()` for the `innerBlocks` attribute,
 * and substitutes the result:
 *
 * - A non-empty string is parsed with `parse_blocks()` and becomes the block's
 *   inner blocks; `innerContent` is rebuilt around the host's own static chunks
 *   so the substituted children render IN PLACE (the host's chrome, such as a
 *   `<div>`/`<li>` wrapper, is preserved and the children render exactly once).
 * - An empty string `''` is an intentionally-empty area: the inner blocks are
 *   cleared and only the host's static chunks remain, so the wrapper renders
 *   empty (no fallback children).
 * - `null` (absence) leaves the parsed block untouched, so the block's own
 *   serialized children render as a fallback.
 *
 * Recursion guard: a source can supply markup containing another block bound to
 * the same source (directly, or through a cycle of sources), so the substitution
 * is skipped when the binding's guard key — source, args, and the resolved
 * values of the context the source consumes — already appears in the
 * currently-rendering bound ancestry, or when nesting exceeds the hard depth
 * cap. The returned `ancestry` is non-null only when substitution actually
 * produced children; the caller must place it under the
 * `WP_BLOCK_BINDINGS_INNER_BLOCKS_ANCESTRY` context key of the block
 * about to render so the substituted children inherit it.
 *
 * @since 7.1.0
 *
 * @param array $parsed_block      The parsed block carrying the binding.
 * @param array $available_context The context available to the block.
 * @return array {
 *     The resolution result.
 *
 *     @type array      $parsed_block The parsed block, with substituted inner blocks
 *                                    when a value resolved.
 *     @type array|null $ancestry     The full ancestry list (ancestors plus this
 *                                    binding's guard key) to propagate to the
 *                                    substituted children, or null when nothing
 *                                    needs propagating.
 * }
 */
function _block_bindings_resolve_inner_blocks( $parsed_block, $available_context ) {
	$unchanged = array(
		'parsed_block' => $parsed_block,
		'ancestry'     => null,
	);

	$binding = _block_bindings_get_inner_blocks_binding( $parsed_block );
	if ( null === $binding ) {
		return $unchanged;
	}

	$source = get_block_bindings_source( $binding['source'] );
	if ( null === $source ) {
		return $unchanged;
	}

	$source_args = ! empty( $binding['args'] ) && is_array( $binding['args'] ) ? $binding['args'] : array();
	$guard_key   = _block_bindings_get_binding_guard_key( $binding['source'], $source_args, $source, $available_context );

	$ancestry = $available_context[ WP_BLOCK_BINDINGS_INNER_BLOCKS_ANCESTRY ] ?? array();
	if ( ! is_array( $ancestry ) ) {
		$ancestry = array();
	}

	if ( in_array( $guard_key, $ancestry, true ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				sprintf(
					/* translators: %s: The bound source name. */
					__( 'The "innerBlocks" binding source "%s" supplies a block bound to itself; the nested binding is skipped to prevent infinite recursion.' ),
					$binding['source']
				)
			),
			'7.1.0'
		);
		return $unchanged;
	}

	/*
	 * The depth counter (not just the ancestry length) must be checked here:
	 * cycles crossing context boundaries (`do_blocks()`, `core/post-template`)
	 * re-enter as top-level blocks with fresh context, so the counter is the
	 * only state that survives them.
	 */
	if (
		count( $ancestry ) >= WP_BLOCK_BINDINGS_MAX_BOUND_DEPTH ||
		_block_bindings_bound_render_depth() >= WP_BLOCK_BINDINGS_MAX_BOUND_DEPTH
	) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				sprintf(
					/* translators: %d: The maximum nesting depth of bound inner-block areas. */
					__( 'Bound inner blocks are nested more than %d levels deep; the binding is skipped to prevent infinite recursion.' ),
					WP_BLOCK_BINDINGS_MAX_BOUND_DEPTH
				)
			),
			'7.1.0'
		);
		return $unchanged;
	}

	$block_instance = new WP_Block( $parsed_block, $available_context );

	if ( ! empty( $source->uses_context ) ) {
		foreach ( $source->uses_context as $context_name ) {
			if ( array_key_exists( $context_name, $available_context ) ) {
				$block_instance->context[ $context_name ] = $available_context[ $context_name ];
			}
		}
	}

	$source_value = $source->get_value( $source_args, $block_instance, 'innerBlocks' );

	// Absence: leave the parsed block untouched so the serialized fallback renders.
	if ( null === $source_value ) {
		return $unchanged;
	}

	if ( ! is_string( $source_value ) ) {
		// Surface a misuse instead of silently parsing a non-string value.
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				sprintf(
					/* translators: %s: The bound source name. */
					__( 'The "innerBlocks" binding source "%s" must return a serialized block-markup string, an empty string, or null.' ),
					$binding['source']
				)
			),
			'7.1.0'
		);
		return $unchanged;
	}

	if ( '' === $source_value ) {
		// Intentionally empty area: no inner blocks, but keep the host's static
		// chunks so its wrapper renders empty (no fallback children). No children
		// means nothing can recurse, so no ancestry needs propagating.
		$parsed_block['innerContent'] = _block_bindings_rebuild_inner_content( $parsed_block['innerContent'] ?? array(), 0 );
		$parsed_block['innerBlocks']  = array();
		return array(
			'parsed_block' => $parsed_block,
			'ancestry'     => null,
		);
	}

	// A non-empty string: parse it and rebuild inner content around the host's
	// own static chunks so the substituted children render in place.
	$parsed_block['innerBlocks']  = parse_blocks( $source_value );
	$parsed_block['innerContent'] = _block_bindings_rebuild_inner_content(
		$parsed_block['innerContent'] ?? array(),
		count( $parsed_block['innerBlocks'] )
	);

	return array(
		'parsed_block' => $parsed_block,
		'ancestry'     => array_merge( $ancestry, array( $guard_key ) ),
	);
}

/**
 * Substitutes a nested block's inner blocks from its `innerBlocks` binding source.
 *
 * Hooked on `render_block_data`, which fires for every block (including each
 * inner block) before it renders, with the signature
 * `( $parsed_block, $source_block, $parent_block )`.
 *
 * Top-level blocks (`$parent_block === null`) only get depth accounting here;
 * their substitution is handled by
 * `_block_bindings_render_top_level_bound_block()`, which applies this
 * very filter before resolving.
 *
 * @since 7.1.0
 *
 * @param array         $parsed_block The parsed block being filtered.
 * @param array         $source_block An unmodified copy of the parsed block.
 * @param WP_Block|null $parent_block The parent block instance, or null at the top level.
 * @return array The parsed block, with substituted inner blocks when a value resolves.
 */
function _block_bindings_replace_inner_blocks( $parsed_block, $source_block, $parent_block ) {
	if ( null === _block_bindings_get_inner_blocks_binding( $parsed_block ) ) {
		return $parsed_block;
	}

	/*
	 * Every block carrying the binding descriptor — any parent, any outcome —
	 * increments the depth counter, symmetric by construction with the
	 * decrement in `_block_bindings_close_bound_block()`, which pairs
	 * on the same re-derivable descriptor check.
	 */
	$depth = &_block_bindings_bound_render_depth();
	++$depth;

	if ( ! $parent_block instanceof WP_Block ) {
		return $parsed_block;
	}

	/*
	 * Prefer the actual WP_Block instance being rendered so source callbacks
	 * receive inherited context through arbitrary ancestry, even through
	 * intermediate blocks that neither use nor provide that context.
	 */
	$source_instance   = _block_bindings_find_inner_block_instance( $source_block, $parent_block );
	$available_context = $source_instance instanceof WP_Block
		? _block_bindings_get_available_context( $source_instance )
		: _block_bindings_get_default_context( $parsed_block );

	$result = _block_bindings_resolve_inner_blocks( $parsed_block, $available_context );

	/*
	 * Propagate the bound ancestry to the substituted children by mutating the
	 * cached instance's context: core's render loop detects the context change
	 * against its pre-filter snapshot and rebuilds the children with it merged
	 * into their available context. When the instance could not be located
	 * (practically unreachable in core's loop), cycle detection for this
	 * subtree degrades to the global depth cap.
	 */
	if ( null !== $result['ancestry'] && $source_instance instanceof WP_Block ) {
		$source_instance->context[ WP_BLOCK_BINDINGS_INNER_BLOCKS_ANCESTRY ] = $result['ancestry'];
	}

	return $result['parsed_block'];
}
add_filter( 'render_block_data', '_block_bindings_replace_inner_blocks', 10, 3 );

/**
 * Renders a top-level block carrying an `innerBlocks` binding, replacing core's
 * `render_block()` tail.
 *
 * Hooked on `pre_render_block` at `PHP_INT_MAX` so every other callback runs
 * first (a non-null value from one of them is passed through untouched). Core's
 * `render_block()` applies the `render_block_context` filter itself, but the
 * substitution has to run before the block is constructed — applying the filter
 * early AND letting core apply it again would run non-idempotent callbacks
 * twice. Short-circuiting `render_block()` and replicating its tail here runs
 * `render_block_data` and `render_block_context` exactly once, and resolution
 * sees the real, post-filter context. The `render_block` and
 * `render_block_{name}` filters still fire inside `WP_Block::render()`.
 *
 * @since 7.1.0
 *
 * @param string|null   $pre_render   The pre-rendered content, or null.
 * @param array         $parsed_block The parsed block being rendered.
 * @param WP_Block|null $parent_block The parent block instance, or null at the top level.
 * @return string|null The rendered block for top-level bound blocks; otherwise `$pre_render`.
 */
function _block_bindings_render_top_level_bound_block( $pre_render, $parsed_block, $parent_block ) {
	if (
		null !== $pre_render ||
		null !== $parent_block ||
		null === _block_bindings_get_inner_blocks_binding( $parsed_block )
	) {
		return $pre_render;
	}

	global $post;

	// Replicates the tail of render_block(), verified against 7.1-alpha.
	$source_block = $parsed_block;

	/** This filter is documented in wp-includes/blocks.php */
	$parsed_block = apply_filters( 'render_block_data', $parsed_block, $source_block, null );

	$context = array();

	if ( $post instanceof WP_Post ) {
		$context['postId']   = $post->ID;
		$context['postType'] = $post->post_type;
	}

	/** This filter is documented in wp-includes/blocks.php */
	$context = apply_filters( 'render_block_context', $context, $parsed_block, null );
	if ( ! is_array( $context ) ) {
		$context = array();
	}

	// The binding is re-read from the post-filter parsed block by the resolver;
	// a descriptor removed by a `render_block_data` filter resolves to a no-op.
	$result       = _block_bindings_resolve_inner_blocks( $parsed_block, $context );
	$parsed_block = $result['parsed_block'];
	if ( null !== $result['ancestry'] ) {
		$context[ WP_BLOCK_BINDINGS_INNER_BLOCKS_ANCESTRY ] = $result['ancestry'];
	}

	$block = new WP_Block( $parsed_block, $context );

	return $block->render();
}
add_filter( 'pre_render_block', '_block_bindings_render_top_level_bound_block', PHP_INT_MAX, 3 );

/**
 * Decrements the bound-render depth once a bound block has rendered.
 *
 * Companion to `_block_bindings_replace_inner_blocks()`: pairing is by
 * the same re-derivable descriptor check on the block's own attributes (which
 * survives parsed-block rebuilds by third-party filters), and the decrement is
 * clamped at zero so mispaired decrements from direct `WP_Block::render()`
 * calls cannot underflow the counter.
 *
 * @since 7.1.0
 *
 * @param string $block_content The rendered block content.
 * @param array  $parsed_block  The parsed block.
 * @return string The unchanged block content.
 */
function _block_bindings_close_bound_block( $block_content, $parsed_block ) {
	if ( null !== _block_bindings_get_inner_blocks_binding( $parsed_block ) ) {
		$depth = &_block_bindings_bound_render_depth();
		$depth = max( 0, $depth - 1 );
	}
	return $block_content;
}
add_filter( 'render_block', '_block_bindings_close_bound_block', 20, 2 );
