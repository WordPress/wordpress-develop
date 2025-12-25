<?php
/**
 * Block support flags.
 *
 * @package WordPress
 *
 * @since 5.6.0
 */

/**
 * Class encapsulating and implementing Block Supports.
 *
 * @since 5.6.0
 *
 * @access private
 */
#[AllowDynamicProperties]
class WP_Block_Supports {

	/**
	 * Config.
	 *
	 * @since 5.6.0
	 * @var array
	 */
	private $block_supports = array();

	/**
	 * Tracks the current block to be rendered.
	 *
	 * @since 5.6.0
	 * @var array
	 */
	public static $block_to_render = null;

	/**
	 * Container for the main instance of the class.
	 *
	 * @since 5.6.0
	 * @var WP_Block_Supports|null
	 */
	private static $instance = null;

	/**
	 * Utility method to retrieve the main instance of the class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @since 5.6.0
	 *
	 * @return WP_Block_Supports The main instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initializes the block supports. It registers the block supports block attributes.
	 *
	 * @since 5.6.0
	 */
	public static function init() {
		$instance = self::get_instance();
		$instance->register_attributes();
	}

	/**
	 * Registers a block support.
	 *
	 * @since 5.6.0
	 *
	 * @link https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/
	 *
	 * @param string $block_support_name   Block support name.
	 * @param array  $block_support_config Array containing the properties of the block support.
	 */
	public function register( $block_support_name, $block_support_config ) {
		$this->block_supports[ $block_support_name ] = array_merge(
			$block_support_config,
			array( 'name' => $block_support_name )
		);
	}

	/**
	 * Generates an array of HTML attributes, such as classes, by applying to
	 * the given block all of the features that the block supports.
	 *
	 * @since 5.6.0
	 *
	 * @return string[] Array of HTML attribute values keyed by their name.
	 */
	public function apply_block_supports() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered(
			self::$block_to_render['blockName']
		);

		// If no render_callback, assume styles have been previously handled.
		if ( ! $block_type || empty( $block_type ) ) {
			return array();
		}

		$block_attributes = array_key_exists( 'attrs', self::$block_to_render ) && is_array( self::$block_to_render['attrs'] )
			? $block_type->prepare_attributes_for_render( self::$block_to_render['attrs'] )
			: array();

		$output = array();
		foreach ( $this->block_supports as $block_support_config ) {
			if ( ! isset( $block_support_config['apply'] ) ) {
				continue;
			}

			$new_attributes = call_user_func(
				$block_support_config['apply'],
				$block_type,
				$block_attributes
			);

			if ( ! empty( $new_attributes ) ) {
				foreach ( $new_attributes as $attribute_name => $attribute_value ) {
					if ( ! array_key_exists( $attribute_name, $output ) || '' === (string) $output[ $attribute_name ] ) {
						$output[ $attribute_name ] = $attribute_value;
					} else {
						$output[ $attribute_name ] .= " $attribute_value";
					}
				}
			}
		}

		return $output;
	}

	/**
	 * Registers the block attributes required by the different block supports.
	 *
	 * @since 5.6.0
	 */
	private function register_attributes() {
		$block_registry         = WP_Block_Type_Registry::get_instance();
		$registered_block_types = $block_registry->get_all_registered();
		foreach ( $registered_block_types as $block_type ) {
			if ( ! ( $block_type instanceof WP_Block_Type ) ) {
				continue;
			}
			if ( ! $block_type->attributes ) {
				$block_type->attributes = array();
			}

			foreach ( $this->block_supports as $block_support_config ) {
				if ( ! isset( $block_support_config['register_attribute'] ) ) {
					continue;
				}

				call_user_func(
					$block_support_config['register_attribute'],
					$block_type
				);
			}
		}
	}
}
