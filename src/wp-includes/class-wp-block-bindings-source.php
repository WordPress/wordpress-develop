<?php
/**
 * Block Bindings API: WP_Block_Bindings_Source class.
 *
 *
 * @package WordPress
 * @subpackage Block Bindings
 * @since 6.5.0
 */

/**
 * Class representing block bindings source.
 *
 * This class is designed for internal use by the Block Bindings registry.
 *
 * @since 6.5.0
 * @access private
 *
 * @see WP_Block_Bindings_Registry
 */
final class WP_Block_Bindings_Source {

	public function __construct( string $name, array $source_properties ) {
		$this->name = $name;

		/* Validate that the source properties contain the label */
		if ( ! isset( $source_properties['label'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'The source properties must contain a label.' ),
				'6.5.0'
			);
			return;
		}

		/* Validate that the source properties contain the get_value_callback */
		if ( ! isset( $source_properties['get_value_callback'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'The source properties must contain a get_value_callback.' ),
				'6.5.0'
			);
			return;
		}

		$this->label    = $source_properties['label'];
		$this->callback = $source_properties['get_value_callback'];
	}

	/**
	 * The name of the source.
	 *
	 * @since 6.5.0
	 * @var string
	 */
	public $name;

	/**
	 * The label of the source.
	 *
	 * @since 6.5.0
	 * @var string
	 */
	public $label;


	/**
	 * The function used to get the value of the source.
	 *
	 * @since 6.5.0
	 * @var callable
	 */
	public $callback;

	/**
	 * The source properties used to register a source.
	 *
	 * @since 6.5.0
	 * @var array  $source_properties {
	 *     @type string   $label              The label of the source.
	 *     @type callback $get_value_callback A callback executed when the source is processed during block rendering.
	 *                                        The callback should have the following signature:
	 *
	 *                                        `function ($source_args, $block_instance, $attribute_name): mixed`
	 *                                            - @param array    $source_args    Array containing source arguments
	 *                                                                              used to look up the override value,
	 *                                                                              i.e. {"key": "foo"}.
	 *                                            - @param WP_Block $block_instance The block instance.
	 *                                            - @param string   $attribute_name The name of the target attribute.
	 *                                        The callback has a mixed return type; it may return a string to override
	 *                                        the block's original value, null, false to remove an attribute, etc.
	 * }
	 */
	public $source_properties;


	public function get_value( $source_properties, $block_instance, $attribute_name ) {
		if ( ! isset( $source_properties['get_value_callback'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'There is no ' ),
				'6.5.0'
			);
			return null;
		}

		return call_user_func( $this->callback, $source_properties, $block_instance, $attribute_name );
	}
}
