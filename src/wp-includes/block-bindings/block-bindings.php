<?php
/**
 * Block Bindings API
 *
 * This file contains functions for managing block bindings in WordPress.
 *
 * @since 6.5.0
 * @package WordPress
 */


/**
 * Registers a new source for block bindings.
 *
 * @since 6.5.0
 *
 * @param string   $source_name The name of the source.
 * @param array    $source_properties   The array of arguments that are used to register a source. The array has two elements:
 *                                1. string   $label        The label of the source.
 *                                2. callback $apply        A callback
 *                                executed when the source is processed during
 *                                block rendering. The callback should have the
 *                                following signature:
 *
 *                                  `function (object $source_attrs, object $block_instance, string $attribute_name): string`
 *                                          - @param object $source_attrs: Object containing source ID used to look up the override value, i.e. {"value": "{ID}"}.
 *                                          - @param object $block_instance: The block instance.
 *                                          - @param string $attribute_name: The name of an attribute used to retrieve an override value from the block context.
 *                                 The callback should return a string that will be used to override the block's original value.
 *
 * @return void
 */
function wp_block_bindings_register_source( $source_name, array $source_properties ) {
	WP_Block_Bindings_Registry::get_instance()->register( $source_name, $source_properties );
}


/**
 * Retrieves the list of registered block sources.
 *
 * @since 6.5.0
 *
 * @return array The list of registered block sources.
 */
function wp_block_bindings_get_all_registered() {
	return WP_Block_Bindings_Registry::get_instance()->get_all_registered();
}
