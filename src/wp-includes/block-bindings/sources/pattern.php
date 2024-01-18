<?php
/**
 * Add the metadata source to the Block Bindings API.
 *
 * @since 6.5.0
 * @package WordPress
 */
function pattern_source_callback( $source_attrs, $block_instance, $attribute_name ) {
	if ( ! _wp_array_get( $block_instance->attributes, array( 'metadata', 'id' ), false ) ) {
		return null;
	}
		$block_id = $block_instance->attributes['metadata']['id'];
		return _wp_array_get( $block_instance->context, array( 'pattern/overrides', $block_id, $attribute_name ), null );
}

wp_block_bindings_register_source(
	'pattern_attributes',
	__( 'Pattern Attributes' ),
	'pattern_source_callback'
);
