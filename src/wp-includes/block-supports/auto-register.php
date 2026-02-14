<?php
/**
 * Auto-register block support.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Mark user-defined attributes for auto-generated inspector controls.
 *
 * This filter runs during block type registration, before the WP_Block_Type
 * is instantiated. Block supports add their attributes AFTER the block type
 * is created (via WP_Block_Supports::register_attributes()), so any attributes
 * present at this stage are user-defined.
 *
 * The marker tells generateFieldsFromAttributes() which attributes should
 * get auto-generated inspector controls. Attributes are excluded if they:
 * - Have a 'source' (HTML-derived, edited inline not via inspector)
 * - Have role 'local' (internal state, not user-configurable)
 * - Were added by block supports (added after this filter runs)
 *
 * @since 7.0.0
 * @access private
 *
 * @param array  $args       Array of arguments for registering a block type.
 * @param string $block_type Block type name including namespace.
 * @return array Modified block type arguments.
 */
function wp_mark_auto_generate_control_attributes( array $args, string $block_type ): array {
	if ( empty( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
		return $args;
	}

	$has_auto_register = ! empty( $args['supports']['auto_register'] );
	if ( ! $has_auto_register ) {
		return $args;
	}

	foreach ( $args['attributes'] as $name => $def ) {
		// Skip HTML-derived attributes (edited inline, not via inspector).
		if ( ! empty( $def['source'] ) ) {
			continue;
		}
		// Skip internal attributes (not user-configurable).
		if ( isset( $def['role'] ) && 'local' === $def['role'] ) {
			continue;
		}
		$args['attributes'][ $name ]['autoGenerateControl'] = true;
	}

	return $args;
}

// Priority 5 to mark original attributes before other filters (priority 10+) might add their own.
add_filter( 'register_block_type_args', 'wp_mark_auto_generate_control_attributes', 5, 2 );
