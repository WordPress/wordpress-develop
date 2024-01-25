<?php
/**
 * Block Bindings API: WP_Block_Bindings class.
 *
 * Support for overriding content in blocks by connecting them to different sources.
 *
 * @package WordPress
 * @subpackage Block Bindings
 */

/**
 * Core class used to define supported blocks, register sources, and populate HTML with content from those sources.
 *
 *  @since 6.5.0
 */
class WP_Block_Bindings {

	/**
	 * Holds the registered block bindings sources, keyed by source identifier.
	 *
	 * @since 6.5.0
	 *
	 * @var array
	 */
	private $sources = array();

	/**
	 * Function to register a new block binding source.
	 *
	 * Sources are used to override block's original attributes with a value
	 * coming from the source. Once a source is registered, it can be used by a
	 * block by setting its `metadata.bindings` attribute to a value that refers
	 * to the source.
	 *
	 * @since 6.5.0
	 *
	 * @param string   $source_name   The name of the source.
	 * @param array    $source_args   The array of arguments that are used to register a source. The array has two elements:
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
	public function register_source( string $source_name, array $source_args ) {
		$this->sources[ $source_name ] = $source_args;
	}

	/**
	 * Processes the block bindings in block's attributes.
	 *
	 * A block might contain bindings in its attributes. Bindings are mappings
	 * between an attribute of the block and a source. A "source" is a function
	 * registered with `wp_block_bindings_register_source()` that defines how to
	 * retrieve a value from outside the block, e.g. from post meta.
	 *
	 * This function will process those bindings and replace the HTML with the value of the binding.
	 * The value is retrieved from the source of the binding.
	 *
	 * ### Example
	 *
	 * The "bindings" property for an Image block might look like this:
	 *
	 * ```json
	 * {
	 *   "metadata": {
	 *     "bindings": {
	 *       "title": {
	 *         "source": {
	 *           "name": "post_meta",
	 *           "attributes": { "value": "text_custom_field" }
	 *         }
	 *       },
	 *       "url": {
	 *         "source": {
	 *           "name": "post_meta",
	 *           "attributes": { "value": "url_custom_field" }
	 *         }
	 *       }
	 *     }
	 *   }
	 * }
	 * ```
	 *
	 * The above example will replace the `title` and `url` attributes of the Image
	 * block with the values of the `text_custom_field` and `url_custom_field` post meta.
	 *
	 * @access private
	 * @since 6.5.0
	 *
	 * @param string   $block_content Block content.
	 * @param array    $block The full block, including name and attributes.
	 * @param WP_Block $block_instance The block instance.
	 */
	private function process( $block_content, $block, $block_instance ) {

		// Allowed blocks that support block bindings.
		// TODO: Look for a mechanism to opt-in for this. Maybe adding a property to block attributes?
		$allowed_blocks = array(
			'core/paragraph' => array( 'content' ),
			'core/heading'   => array( 'content' ),
			'core/image'     => array( 'url', 'title', 'alt' ),
			'core/button'    => array( 'url', 'text' ),
		);

		// If the block doesn't have the bindings property or isn't one of the allowed block types, return.
		if ( ! isset( $block['attrs']['metadata']['bindings'] ) || ! isset( $allowed_blocks[ $block_instance->name ] ) ) {
			return $block_content;
		}

		$modified_block_content = $block_content;
		foreach ( $block['attrs']['metadata']['bindings'] as $binding_attribute => $binding_source ) {

			// If the attribute is not in the list, process next attribute.
			if ( ! in_array( $binding_attribute, $allowed_blocks[ $block_instance->name ], true ) ) {
				continue;
			}
			// If no source is provided, or that source is not registered, process next attribute.
			if ( ! isset( $binding_source['source'] ) || ! isset( $binding_source['source']['name'] ) || ! isset( $this->sources[ $binding_source['source']['name'] ] ) ) {
				continue;
			}

			$source_callback = $this->sources[ $binding_source['source']['name'] ]['apply'];
			// Get the value based on the source.
			if ( ! isset( $binding_source['source']['attributes'] ) ) {
				$source_args = array();
			} else {
				$source_args = $binding_source['source']['attributes'];
			}
			$source_value = $source_callback( $source_args, $block_instance, $binding_attribute );
			// If the value is null, process next attribute.
			if ( is_null( $source_value ) ) {
				continue;
			}

			// Process the HTML based on the block and the attribute.
			$modified_block_content = $this->replace_html( $modified_block_content, $block_instance->name, $binding_attribute, $source_value );
		}
		return $modified_block_content;
	}

	/**
	 * Depending on the block attributes, replace the HTML based on the value returned by the source.
	 *
	 * @since 6.5.0
	 *
	 * @param string $block_content Block content.
	 * @param string $block_name The name of the block to process.
	 * @param string $block_attr The attribute of the block we want to process.
	 * @param string $source_value The value used to replace the HTML.
	 */
	private function replace_html( string $block_content, string $block_name, string $block_attr, string $source_value ) {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		if ( null === $block_type || ! isset( $block_type->attributes[ $block_attr ] ) ) {
			return $block_content;
		}

		// Depending on the attribute source, the processing will be different.
		switch ( $block_type->attributes[ $block_attr ]['source'] ) {
			case 'html':
			case 'rich-text':
				$block_reader = new WP_HTML_Tag_Processor( $block_content );

				// TODO: Support for CSS selectors whenever they are ready in the HTML API.
				// In the meantime, support comma-separated selectors by exploding them into an array.
				$selectors = explode( ',', $block_type->attributes[ $block_attr ]['selector'] );
				// Add a bookmark to the first tag to be able to iterate over the selectors.
				$block_reader->next_tag();
				$block_reader->set_bookmark( 'iterate-selectors' );

				// TODO: This shouldn't be needed when the `set_inner_html` function is ready.
				// Store the parent tag and its attributes to be able to restore them later in the button.
				// The button block has a wrapper while the paragraph and heading blocks don't.
				if ( 'core/button' === $block_name ) {
					$button_wrapper                 = $block_reader->get_tag();
					$button_wrapper_attribute_names = $block_reader->get_attribute_names_with_prefix( '' );
					$button_wrapper_attrs           = array();
					foreach ( $button_wrapper_attribute_names as $name ) {
						$button_wrapper_attrs[ $name ] = $block_reader->get_attribute( $name );
					}
				}

				foreach ( $selectors as $selector ) {
					// If the parent tag, or any of its children, matches the selector, replace the HTML.
					if ( strcasecmp( $block_reader->get_tag( $selector ), $selector ) === 0 || $block_reader->next_tag(
						array(
							'tag_name' => $selector,
						)
					) ) {
						$block_reader->release_bookmark( 'iterate-selectors' );

						// TODO: Use `set_inner_html` method whenever it's ready in the HTML API.
						// Until then, it is hardcoded for the paragraph, heading, and button blocks.
						// Store the tag and its attributes to be able to restore them later.
						$selector_attribute_names = $block_reader->get_attribute_names_with_prefix( '' );
						$selector_attrs           = array();
						foreach ( $selector_attribute_names as $name ) {
							$selector_attrs[ $name ] = $block_reader->get_attribute( $name );
						}
						$selector_markup = "<$selector>" . wp_kses_post( $source_value ) . "</$selector>";
						$amended_content = new WP_HTML_Tag_Processor( $selector_markup );
						$amended_content->next_tag();
						foreach ( $selector_attrs as $attribute_key => $attribute_value ) {
							$amended_content->set_attribute( $attribute_key, $attribute_value );
						}
						if ( 'core/paragraph' === $block_name || 'core/heading' === $block_name ) {
							return $amended_content->get_updated_html();
						}
						if ( 'core/button' === $block_name ) {
							$button_markup  = "<$button_wrapper>{$amended_content->get_updated_html()}</$button_wrapper>";
							$amended_button = new WP_HTML_Tag_Processor( $button_markup );
							$amended_button->next_tag();
							foreach ( $button_wrapper_attrs as $attribute_key => $attribute_value ) {
								$amended_button->set_attribute( $attribute_key, $attribute_value );
							}
							return $amended_button->get_updated_html();
						}
					} else {
						$block_reader->seek( 'iterate-selectors' );
					}
				}
				$block_reader->release_bookmark( 'iterate-selectors' );
				return $block_content;

			case 'attribute':
				$amended_content = new WP_HTML_Tag_Processor( $block_content );
				if ( ! $amended_content->next_tag(
					array(
						// TODO: build the query from CSS selector.
						'tag_name' => $block_type->attributes[ $block_attr ]['selector'],
					)
				) ) {
					return $block_content;
				}
				$amended_content->set_attribute( $block_type->attributes[ $block_attr ]['attribute'], esc_attr( $source_value ) );
				return $amended_content->get_updated_html();
				break;

			default:
				return $block_content;
				break;
		}
		return;
	}

	/**
	 * Retrieves the list of registered block sources.
	 *
	 * @since 6.5.0
	 *
	 * @return array The array of registered sources.
	 */
	public function get_sources() {
		return $this->sources;
	}

	/**
	 * Wrapper for the WP_Block_Bindings process method, which is used
	 * process mappings between an attribute of a block and a source.
	 * Please see the WP_Block_Bindings::process method for more details.
	 *
	 * @access public
	 * @since 6.5.0
	 *
	 * @param string   $block_content Block content.
	 * @param array    $block The full block, including name and attributes.
	 * @param WP_Block $block_instance The block instance.
	 */
	public function process_bindings( $block_content, $block, $block_instance ) {
		return $this->process( $block_content, $block, $block_instance );
	}

}
