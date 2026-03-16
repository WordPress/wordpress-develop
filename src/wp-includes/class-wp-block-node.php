<?php

/**
 * Core class representing a Block in memory, comprising its type, attributes,
 * and inner content, but not the supporting implementation to render it.
 *
 * This record-style class represents the fundamental composition of a block
 * in an implementation-agnostic way. Blocks may contain attribute values that
 * are deprecated, inner HTML which might be manually modified or corrupted,
 * and inner blocks that aren’t supported. This is a container for the block
 * object in its most raw form, to be used for passing around and processing.
 *
 * @since 7.0.0
 */
final class WP_Block_Node {
	/**
	 * Fully-qualified block type, comprising a namespace and a block name,
	 * or `null` when the block node represents freeform HTML content.
	 *
	 * Example:
	 *
	 *     "core/paragraph"
	 *
	 * @see self::parse_block_type()
	 *
	 * @since 7.0
	 */
	public ?string $blockName = null;

	public ?array $attrs = null;

	public string $innerHTML = '';

	public ?array $innerBlocks = null;

	public array $innerContent = array();

	private static ?string $last_error = null;

	private function __construct() {
		// Callees should call the constructor functions to prevent creating invalid block nodes.
	}

	public static function make( string $block_type, ?array $attributes, ...$inner_content ): ?self {
		self::$last_error = null;

		$parsed_block_type = self::parse_block_type( $block_type );
		if ( null === $parsed_block_type ) {
			return null;
		}

		$block_node             = new self();
		$block_node->blockName  = $parsed_block_type;
		$block_node->attrs = $attributes;

		$inner_blocks = array();
		foreach ( $inner_content as $chunk ) {
			if ( is_string( $chunk ) ) {
				$block_node->innerHTML .= $chunk;

				if ( is_string( end( $block_node->innerContent ) ) ) {
					$block_node->innerContent[ count( $block_node->innerContent ) - 1 ] .= $chunk;
					_doing_it_wrong(
						__METHOD__,
						'Do not split innerHTML where there are no blocks.',
						'7.0'
					);
				} else {
					$block_node->innerContent[] = $chunk;
				}
			} elseif ( is_array( $chunk ) ) {
				$inner_block = self::from_block_array( $chunk );
				if ( null === $inner_block ) {
					return null;
				}

				$inner_blocks[]             = $inner_block;
				$block_node->innerContent[] = null;
			} elseif ( $chunk instanceof self ) {
				$inner_blocks[]             = $chunk;
				$block_node->innerContent[] = null;
			} else {
				self::$last_error = self::ERROR_INNER_BLOCK_DATA_TYPE;
				return null;
			}
		}

		if ( 0 < count( $inner_blocks ) ) {
			$block_node->innerBlocks = $inner_blocks;
		}

		return $block_node;
	}

	public static function make_freeform( string $html ): self {
		self::$last_error          = null;
		$block_node                = new self();
		$block_node->innerHTML    = $html;
		$block_node->innerContent = array( $html );

		return $block_node;
	}

	public static function from_block_array( array $block_array ): ?self {
		self::$last_error = null;
		$block_type       = $block_array['blockName'] ?? null;
		$attributes       = $block_array['attrs'] ?? null;
		$inner_html       = $block_array['innerHTML'] ?? null;
		$inner_blocks     = $block_array['innerBlocks'] ?? null;
		$inner_content    = $block_array['innerContent'] ?? null;

		// Freeform blocks may only contain inner HTML.
		if ( null === $block_type ) {
			if (
				isset( $attributes ) ||
				isset( $inner_blocks ) ||
				in_array( null, $inner_content, true ) ||
				! is_string( $inner_html )
			) {
				self::$last_error = self::ERROR_FREEFORM_DATA_TYPE;
				return null;
			}

			$block_node            = new self();
			$block_node->innerHTML = $inner_html;

			return $block_node;
		}

		// Inner blocks must be an array if present.
		if ( isset( $inner_blocks ) && ! is_array( $inner_blocks ) ) {
			self::$last_error = self::ERROR_INNER_BLOCK_DATA_TYPE;
			return null;
		}

		// Inner content must be an array if present.
		if ( isset( $inner_content ) && ! is_array( $inner_content ) ) {
			self::$last_error = self::ERROR_INNER_CONTENT;
			return null;
		}

		// If inner blocks are present, inner content must also be present.
		if ( isset( $inner_blocks ) && ! isset( $inner_content ) ) {
			self::$last_error = self::ERROR_INNER_CONTENT;
			return null;
		}

		// If inner content is present, it must match the inner HTML and inner blocks.
		$generated_inner_html    = '';
		$generated_inner_content = array();
		$remaining_inner_blocks  = isset( $inner_blocks ) ? count( $inner_blocks ) : 0;
		if ( isset( $inner_content ) ) {
			foreach ( $inner_content as $chunk ) {
				if ( is_string( $chunk ) ) {
					if ( is_string( end( $generated_inner_content ) ) ) {
						$generated_inner_content[ count( $generated_inner_content ) - 1 ] .= $chunk;
					} else {
						$generated_inner_html     .= $chunk;
						$generated_inner_content[] = $chunk;
					}
				} elseif ( is_array( $chunk ) ) {
					if ( --$remaining_inner_blocks < 0 ) {
						self::$last_error = self::ERROR_INNER_CONTENT;
						return null;
					}
					$inner_block = self::from_block_array( $chunk );
					if ( null === $inner_block ) {
						return null;
					}

					$generated_inner_content[] = null;
				} elseif ( $chunk instanceof self ) {
					if ( --$remaining_inner_blocks < 0 ) {
						self::$last_error = self::ERROR_INNER_CONTENT;
						return null;
					}

					$generated_inner_content[] = null;
				} else {
					self::$last_error = self::ERROR_INNER_CONTENT;
					return null;
				}
			}

			if ( isset( $inner_html ) && $inner_html !== $generated_inner_html ) {
				self::$last_error = self::ERROR_INNER_HTML;
				return null;
			}

			return self::make( $block_type, $attributes, ...$generated_inner_content );
		}

		// Any other variation here means something was incorrect.
		return null;
	}

	public function to_block_array(): array {
		return array(
			'blockName'    => $this->blockName,
			'attrs'        => $this->attrs ?? array(),
			'innerHTML'    => $this->innerHTML,
			'innerBlocks'  => $this->innerBlocks ?? array(),
			'innerContent' => $this->innerContent,
		);
	}

	public function get_block_type(): ?string {
		return $this->blockName;
	}

	public function get_printable_block_type(): string {
		return $this->blockName ?? 'core/freeform';
	}

	public function is_freeform(): bool {
		return ! isset( $this->blockName );
	}

	public function is_block(): bool {
		return isset( $this->blockName );
	}

	public function is_block_type( ?string $block_type ): bool {
		if ( ! isset( $this->blockName, $block_type ) ) {
			return $this->blockName === $block_type;
		}

		$parsed_block_type = self::parse_block_type( $block_type );
		self::$last_error  = null;

		return $this->blockName === $parsed_block_type;
	}

	public function get_attributes(): array {
		return $this->attrs ?? array();
	}

	public function get_attribute( string $name ): mixed {
		return $this->attrs[ $name ] ?? null;
	}

	public function has_attribute( string $name ): bool {
		return isset( $this->attrs[ $name ] );
	}

	public function get_inner_html(): string {
		return $this->innerHTML;
	}

	public function get_inner_blocks(): array {
		return $this->innerBlocks ?? array();
	}

	public function get_inner_content(): array {
		return $this->innerContent ?? array();
	}

	public function set_inner_content( ...$inner_content ): bool {
		$faux_block = self::make( 'core/faux', null, ...$inner_content );
		if ( null === $faux_block ) {
			return false;
		}

		$this->innerHTML     = $faux_block->innerHTML;
		$this->innerBlocks   = $faux_block->innerBlocks;
		$this->innerContent = $faux_block->innerContent;

		return true;
	}

	public static function get_last_error(): ?string {
		return self::$last_error;
	}

	public static function parse_block_type( string $given_type, ?int $offset = 0, ?int $length = PHP_INT_MAX ): ?string {
		self::$last_error = null;
		$end              = min( strlen( $given_type ), $offset + $length );
		$at               = $offset;
		$separator_offset = strcspn( $given_type, '/', $at, $end - $at );
		$has_namespace    = $separator_offset > 0 && ( $at + $separator_offset < $end );
		$implicitly_core  = false;

		// Parse the namespace and normalize for the implicit Core namespace.
		if ( $has_namespace ) {
			$start_of_namespace = $given_type[ $at ];

			// Namespaces must begin with a lowercase ASCII letter.
			if ( $start_of_namespace < 'a' || $start_of_namespace > 'z' ) {
				self::$last_error = self::ERROR_BLOCK_NAMESPACE;
				return null;
			}

			// Namespaces must form from lowercase ASCII letters, numbers, and - or _.
			$namespace_length = 1 + strspn( $given_type, 'abcdefghijklmnopqrstuvwxyz0123456789-_', $at + 1, $end - $at - 1 );
			if ( $namespace_length !== $separator_offset ) {
				self::$last_error = self::ERROR_BLOCK_NAMESPACE;
				return null;
			}

			// Advance the parser past the namespace and separator.
			$at += $namespace_length + 1;
		} else {
			$implicitly_core = true;
		}

		// Every block requires a block name.
		if ( $at >= $end ) {
			self::$last_error = self::ERROR_BLOCK_TYPE;
			return null;
		}

		// Parse the name, which applies the same rules as for the namespace.
		$start_of_name = $given_type[ $at ];
		if ( $start_of_name < 'a' || $start_of_name > 'z' ) {
			self::$last_error = self::ERROR_BLOCK_NAME;
			return null;
		}

		$name_length = 1 + strspn( $given_type, 'abcdefghijklmnopqrstuvwxyz0123456789-_', $at + 1, $end - $at - 1 );
		if ( $at + $name_length !== $end ) {
			self::$last_error = self::ERROR_BLOCK_TYPE;
			return null;
		}

		$needs_allocation = $implicitly_core || $offset !== 0 || $length !== PHP_INT_MAX;
		$block_type       = $needs_allocation ? substr( $given_type, $offset, $length ) : $given_type;

		return $implicitly_core ? "core/{$block_type}" : $block_type;
	}

	const ERROR_BLOCK_NAME = 'ERROR_BLOCK_NAME';
	const ERROR_BLOCK_NAMESPACE = 'ERROR_BLOCK_NAMESPACE';
	const ERROR_BLOCK_TYPE = 'ERROR_BLOCK_TYPE';
	const ERROR_INNER_HTML = 'ERROR_INNER_HTML';
	const ERROR_FREEFORM_DATA_TYPE = 'ERROR_FREEFORM_DATA_TYPE';
	const ERROR_INNER_BLOCK_DATA_TYPE = 'ERROR_INNER_BLOCK_DATA_TYPE';
	const ERROR_INNER_CONTENT = 'ERROR_INNER_CONTENT';
}

function wp_create_block( string $block_type, ?array $attributes, ...$inner_content ): ?WP_Block_Node {
	return WP_Block_Node::make( $block_type, $attributes, ...$inner_content );
}

function wp_create_freeform_block( string $html ): WP_Block_Node {
	return WP_Block_Node::make_freeform( $html );
}
