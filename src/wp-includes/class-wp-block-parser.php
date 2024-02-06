<?php
/**
 * Block Serialization Parser
 *
 * @package WordPress
 */

class WP_Span {
	public $at;
	public $length;

	public function __construct( $at, $length ) {
		$this->at = $at;
		$this->length = $length;
	}
}

class WP_Parsed_Block implements ArrayAccess {
	/**
	 * Offset into name list where block name starts.
	 *
	 * @var int
	 */
	public $name_at;

	/**
	 * Override when setting block name.
	 *
	 * @var string|null
	 */
	private $name = null;

	/**
	 * Source of block attributes
	 *
	 * @var WP_Span|null
	 */
	public $attrs;

	/**
	 * Override when setting attributes.
	 *
	 * @var array|null
	 */
	public $parsed_attrs = null;

	/**
	 * List of inner content.
	 *
	 * @var array
	 */
	public $inner_content = array();

	/**
	 * Refers to the parent post for this block.
	 *
	 * @var WP_Parsed_Blocks
	 */
	public $post;

	/**
	 * Constructor function.
	 *
	 * @param WP_Parsed_Blocks $post Parent post for this block.
	 */
	public function __construct( $post ) {
		$this->post = $post;
	}

	#[ReturnTypeWillChange]
	public function &offsetGet( $offset ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		switch ( $offset ) {
			case 'blockName':
				if ( null !== $this->name ) {
					return $this->name;
				}

				$name_end = strpos( $this->post->seen_block_types, "\x00", $this->name_at );
				$name     = substr( $this->post->seen_block_types, $this->name_at, $name_end - $this->name_at );
				return $name;

			case 'attrs':
				if ( null !== $this->parsed_attrs ) {
					return $this->parsed_attrs;
				}

				if ( ! isset( $this->attrs ) || 0 === $this->attrs->length ) {
					$this->parsed_attrs = null;
				} else {
					$this->parsed_attrs = json_decode( substr( $this->post->html, $this->attrs->at, $this->attrs->length ), true );
				}

				if ( null === $this->parsed_attrs ) {
					$this->parsed_attrs = array();
				}

				return $this->parsed_attrs;

			case 'innerHTML':
				$html = '';
				foreach ( $this->inner_content as $chunk ) {
					if ( $chunk instanceof WP_Span ) {
						$html .= substr( $this->post->html, $chunk->at, $chunk->length );
					}
				}
				return $html;

			case 'innerBlocks':
				$blocks = array();
				foreach ( $this->inner_content as $chunk ) {
					if ( $chunk instanceof WP_Parsed_Block ) {
						$blocks[] = $chunk;
					}
				}
				return $blocks;

			case 'innerContent':
				$chunks = array();
				foreach ( $this->inner_content as $chunk ) {
					if ( $chunk instanceof WP_Span ) {
						$chunks[] = substr( $this->post->html, $chunk->at, $chunk->length );
					} elseif ( $chunk instanceof WP_Parsed_Block ) {
						$chunks[] = null;
					}
				}

				return $chunks;
		}
	}

	#[ReturnTypeWillChange]
	public function offsetExists( $offset ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		// TODO: Implement offsetExists() method.
		return (
			'blockName' === $offset ||
			'attrs' === $offset ||
			'innerHTML' === $offset ||
			'innerBlocks' === $offset ||
			'innerContent' === $offset
		);
	}

	#[ReturnTypeWillChange]
	public function offsetSet( $offset, $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		switch ( $offset ) {
			case 'blockName':
				if ( $value !== $this['blockName'] ) {
					$this->name = $value;
				}
				break;

			default:
				throw new Exception( 'Does any code modify these?' );
		}
	}

	#[ReturnTypeWillChange]
	public function offsetUnset( $offset ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		// TODO: Implement offsetUnset() method.
	}
}

class WP_Parsed_Blocks {
	/**
	 * Original HTML from which the blocks were parsed.
	 *
	 * @var string
	 */
	public $html;

	/**
	 * Tracks internal pointer into HTML.
	 *
	 * @var int
	 */
	public $at = 0;

	/**
	 * Concatenated block names, as parsed. Used for quick lookup
	 * of existing names.
	 *
	 * @var string
	 */
	public $seen_block_types = "\x00";

	/**
	 * Tracks blocks while parsing.
	 *
	 * @var array
	 */
	public $stack;

	/**
	 * @var WP_Parsed_Block
	 */
	public $root;

	public function __construct( $html ) {
		$this->html  = $html;
		$this->root  = new WP_Parsed_Block( $this );
		$this->root->inner_content = array();
		$this->stack = array( $this->root );
	}

	private function add_inner_chunk( $chunk ) {
		$bottom = end( $this->stack );
		$bottom->inner_content[] = $chunk;
	}

	/**
	 * Generator function which returns each block and the stack as it parses.
	 */
	public function step() {
		if ( $this->at >= strlen( $this->html ) ) {
			return false;
		}

		$has_match = preg_match(
			'/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s',
			$this->html,
			$matches,
			PREG_OFFSET_CAPTURE,
			$this->at
		);

		if ( ! $has_match ) {
			$this->at = strlen( $this->html );
			return false;
		}

		list( $match, $started_at ) = $matches[0];

		$is_closer = isset( $matches['closer'] ) && -1 !== $matches['closer'][1];
		$is_void   = isset( $matches['void'] ) && -1 !== $matches['void'][1];
		$namespace = $matches['namespace'];
		$namespace = ( isset( $namespace ) && -1 !== $namespace[1] ) ? $namespace[0] : 'core/';
		$name      = $namespace . $matches['name'][0];
		$has_attrs = isset( $matches['attrs'] ) && -1 !== $matches['attrs'][1];

		if ( $started_at > $this->at ) {
			$this->add_inner_chunk( new WP_Span( $this->at, $started_at - $this->at ) );
		}

		$this->at = $started_at + strlen( $match );

		if ( $is_closer ) {
			array_pop( $this->stack );
			return true;
		}

		$block = new WP_Parsed_Block( $this );

		// Block name.
		$name_search  = "\x00{$name}\x00";
		$seen_name_at = strpos( $this->seen_block_types, $name_search );
		if ( false === $seen_name_at ) {
			$block->name_at          = strlen( $this->seen_block_types );
			$this->seen_block_types .= "{$name}\x00";
		} else {
			$block->name_at = $seen_name_at + 1;
		}

		// Block attrs.
		if ( $has_attrs ) {
			$block->attrs = new WP_Span( $matches['attrs'][1], strlen( $matches['attrs'][0] ) );
		}

		$this->add_inner_chunk( $block );

		if ( ! $is_void ) {
			$this->stack[] = $block;
		}
		return true;
	}
}

/**
 * Class WP_Block_Parser
 *
 * Parses a document and constructs a list of parsed block objects
 *
 * @since 5.0.0
 * @since 4.0.0 returns arrays not objects, all attributes are arrays
 */
class WP_Block_Parser {
	/**
	 * Parses blocks from an HTML string, deferring computation where possible.
	 *
	 * @param string $html Contains block content.
	 * @return array[] List of blocks in the HTML.
	 */
	public function parse( $html ) {
		$lazy = new WP_Parsed_Blocks( $html );
		while ( $lazy->step() ) {
			continue;
		}

		return $lazy->root['innerBlocks'];
	}
}
