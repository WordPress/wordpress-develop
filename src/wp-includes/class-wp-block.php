<?php
/**
 * Blocks API: WP_Block class
 *
 * @package WordPress
 * @since 5.5.0
 */

/**
 * Class representing a parsed instance of a block.
 *
 * @since 5.5.0
 */
class WP_Block {

	/**
	 * Original parsed array representation of block.
	 *
	 * @since 5.5.0
	 * @var array
	 */
	public $parsed_block;

	/**
	 * All available context of the current hierarchy.
	 *
	 * @since 5.5.0
	 * @var array
	 * @access protected
	 */
	protected $available_context;

	/**
	 * Block type registry to use.
	 *
	 * @since 5.7.0
	 * @var WP_Block_Type_Registry
	 * @access protected
	 */
	protected $registry;

	/**
	 * Constructor options to use when creating nested WP_Block objects.
	 *
	 * @since 5.7.0
	 * @var array
	 * @access protected
	 */
	protected $options;

	/**
	 * Name of block.
	 *
	 * @example "core/paragraph"
	 *
	 * @since 5.5.0
	 * @var string
	 */
	public $name;

	/**
	 * Block type associated with the instance.
	 *
	 * @since 5.5.0
	 * @var WP_Block_Type
	 */
	public $block_type;

	/**
	 * Block attributes.
	 *
	 * @since 5.5.0
	 * @var array
	 */
	public $attributes;

	/**
	 * Block context values.
	 *
	 * @since 5.5.0
	 * @var array
	 */
	public $context;

	/**
	 * List of inner blocks (of this same class)
	 *
	 * @since 5.5.0
	 * @var WP_Block[]
	 */
	public $inner_blocks;

	/**
	 * Resultant HTML from inside block comment delimiters after removing inner
	 * blocks.
	 *
	 * @example "...Just <!-- wp:test /--> testing..." -> "Just testing..."
	 *
	 * @since 5.5.0
	 * @var string
	 */
	public $inner_html;

	/**
	 * List of string fragments and null markers where inner blocks were found
	 *
	 * @example array(
	 *   'inner_html'    => 'BeforeInnerAfter',
	 *   'inner_blocks'  => array( block, block ),
	 *   'inner_content' => array( 'Before', null, 'Inner', null, 'After' ),
	 * )
	 *
	 * @since 5.5.0
	 * @var array
	 */
	public $inner_content;

	/**
	 * Constructor.
	 *
	 * Populates object properties from the provided block instance argument.
	 *
	 * The given array of context values will not necessarily be available on
	 * the instance itself, but is treated as the full set of values provided by
	 * the block's ancestry. This is assigned to the private `available_context`
	 * property. Only values which are configured to consumed by the block via
	 * its registered type will be assigned to the block's `context` property.
	 *
	 * @since 5.5.0
	 *
	 * @param array                  $block             Array of parsed block properties.
	 * @param array                  $available_context Optional array of ancestry context values.
	 * @param WP_Block_Type_Registry $registry          Optional block type registry.
	 * @param array                  $options {
	 *   Optional options object.
	 *
	 *   @type bool $is_eager When false, none of the derived block properties are set until `render()` is called. Defaults to true. See `set_derived_properties()` for more info.
	 * }
	 */
	public function __construct( $parsed_block, $available_context = array(), $registry = null, $options = null ) {
		$options = wp_parse_args(
			$options,
			array(
				'is_eager' => true,
			)
		);

		$this->parsed_block      = $parsed_block;
		$this->available_context = $available_context;
		$this->registry          = $registry ? $registry : WP_Block_Type_Registry::get_instance();
		$this->options           = $options;

		if ( $this->options['is_eager'] ) {
			$this->set_derived_properties();
		}
	}

	/**
	 * Sets the derived block properties:
	 *
	 * - `$block->attributes`
	 * - `$block->context`
	 * - `$block->inner_blocks`
	 * - `$block->inner_html`
	 * - `$block->inner_content`
	 *
	 * They are called derived properties because their values are derived from
	 * `$block->parsed_block` and `$block->available_context`.
	 *
	 * By default, they are set when the block is constructed. By passing
	 * `'is_eager' => false`, however, they won't be set until `render()` is
	 * called.
	 *
	 * @since 5.7.0
	 * @access protected
	 */
	protected function set_derived_properties() {
		// Name.

		$this->name = $this->parsed_block['blockName'];

		// Block type.

		$this->block_type = $this->registry->get_registered( $this->name );

		// Attributes.

		$this->attributes = isset( $this->parsed_block['attrs'] ) ?
			$this->parsed_block['attrs'] :
			array();

		if ( ! is_null( $this->block_type ) ) {
			$this->attributes = $this->block_type->prepare_attributes_for_render( $this->attributes );
		}

		// Context.

		$this->context = array();

		if ( ! empty( $this->block_type->uses_context ) ) {
			foreach ( $this->block_type->uses_context as $context_name ) {
				if ( array_key_exists( $context_name, $this->available_context ) ) {
					$this->context[ $context_name ] = $this->available_context[ $context_name ];
				}
			}
		}

		// Inner blocks.

		$this->inner_blocks = array();

		if ( ! empty( $this->parsed_block['innerBlocks'] ) ) {
			$child_context = $this->available_context;

			if ( ! empty( $this->block_type->provides_context ) ) {
				foreach ( $this->block_type->provides_context as $context_name => $attribute_name ) {
					if ( array_key_exists( $attribute_name, $this->attributes ) ) {
						$child_context[ $context_name ] = $this->attributes[ $attribute_name ];
					}
				}
			}

			$this->inner_blocks = new WP_Block_List(
				$this->parsed_block['innerBlocks'],
				$child_context,
				$this->registry,
				$this->options
			);
		}

		// Inner HTML.

		$this->inner_html = '';

		if ( ! empty( $this->parsed_block['innerHTML'] ) ) {
			$this->inner_html = $this->parsed_block['innerHTML'];
		}

		// Inner content.

		$this->inner_content = array();

		if ( ! empty( $this->parsed_block['innerContent'] ) ) {
			$this->inner_content = $this->parsed_block['innerContent'];
		}
	}

	/**
	 * Generates the render output for the block.
	 *
	 * @since 5.5.0
	 *
	 * @param array $options {
	 *   Optional options object.
	 *
	 *   @type bool $dynamic     Defaults to true. Optionally set to false to avoid using the block's `render_callback`.
	 *   @type bool $filter_data Defaults to false. When true, `$parsed_block` and `$available_context` are filtered using `render_block_data` and `render_block_context` and the block's derived properties are set again.
	 * }
	 * @return string Rendered block output.
	 */
	public function render( $options = array() ) {
		global $post;

		/** This filter is documented in wp-includes/blocks.php */
		$pre_render = apply_filters( 'pre_render_block', null, $this->parsed_block );
		if ( ! is_null( $pre_render ) ) {
			return $pre_render;
		}

		$options = wp_parse_args(
			$options,
			array(
				'dynamic'     => true,
				'filter_data' => false,
			)
		);

		if ( $options['filter_data'] ) {
			$initial_parsed_block      = $this->parsed_block;
			$initial_available_context = $this->available_context;

			/**
			 * Filters a block which is to be rendered by render_block() or
			 * WP_Block::render().
			 *
			 * @since 5.1.0
			 *
			 * @param array $parsed_block The block being rendered.
			 * @param array $source_block An un-modified copy of $parsed_block, as it appeared in the source content.
			 */
			$this->parsed_block = apply_filters(
				'render_block_data',
				$this->parsed_block,
				$initial_parsed_block
			);

			/**
			 * Filters the default context of a block which is to be rendered by
			 * render_block() or WP_Block::render().
			 *
			 * @since 5.5.0
			 *
			 * @param array $available_context Default context.
			 * @param array $parsed_block      Block being rendered, filtered by `render_block_data`.
			 */
			$this->available_context = apply_filters(
				'render_block_context',
				$this->available_context,
				$this->parsed_block
			);

			if (
				$this->parsed_block !== $initial_parsed_block ||
				$this->available_context !== $initial_available_context
			) {
				$this->set_derived_properties();
			}
		}

		if ( ! isset( $this->name ) ) {
			$this->set_derived_properties();
		}

		$is_dynamic    = $options['dynamic'] && $this->name && null !== $this->block_type && $this->block_type->is_dynamic();
		$block_content = '';

		if ( ! $options['dynamic'] || empty( $this->block_type->skip_inner_blocks ) ) {
			$index = 0;
			foreach ( $this->inner_content as $chunk ) {
				$block_content .= is_string( $chunk ) ?
					$chunk :
					$this->inner_blocks[ $index++ ]->render( $options );
			}
		}

		if ( $is_dynamic ) {
			$global_post = $post;
			$parent      = WP_Block_Supports::$block_to_render;

			WP_Block_Supports::$block_to_render = $this->parsed_block;

			$block_content = (string) call_user_func( $this->block_type->render_callback, $this->attributes, $block_content, $this );

			WP_Block_Supports::$block_to_render = $parent;

			$post = $global_post;
		}

		if ( ! empty( $this->block_type->script ) ) {
			wp_enqueue_script( $this->block_type->script );
		}

		if ( ! empty( $this->block_type->style ) ) {
			wp_enqueue_style( $this->block_type->style );
		}

		/**
		 * Filters the content of a single block.
		 *
		 * @since 5.0.0
		 *
		 * @param string $block_content The block content about to be appended.
		 * @param array  $block         The full block, including name and attributes.
		 */
		return apply_filters( 'render_block', $block_content, $this->parsed_block );
	}

}
