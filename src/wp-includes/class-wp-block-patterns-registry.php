<?php
/**
 * Blocks API: WP_Block_Patterns_Registry class
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.5.0
 */

/**
 * Class used for interacting with block patterns.
 *
 * @since 5.5.0
 */
#[AllowDynamicProperties]
final class WP_Block_Patterns_Registry {
	/**
	 * Registered block patterns array.
	 *
	 * @since 5.5.0
	 * @var array[]
	 */
	private $registered_patterns = array();

	/**
	 * Patterns registered outside the `init` action.
	 *
	 * @since 6.0.0
	 * @var array[]
	 */
	private $registered_patterns_outside_init = array();

	/**
	 * Container for the main instance of the class.
	 *
	 * @since 5.5.0
	 * @var WP_Block_Patterns_Registry|null
	 */
	private static $instance = null;

	/**
	 * Registers a block pattern.
	 *
	 * @since 5.5.0
	 * @since 5.8.0 Added support for the `blockTypes` property.
	 * @since 6.1.0 Added support for the `postTypes` property.
	 * @since 6.2.0 Added support for the `templateTypes` property.
	 *
	 * @param string $pattern_name       Block pattern name including namespace.
	 * @param array  $pattern_properties {
	 *     List of properties for the block pattern.
	 *
	 *     @type string   $title         Required. A human-readable title for the pattern.
	 *     @type string   $content       Required. Block HTML markup for the pattern.
	 *     @type string   $description   Optional. Visually hidden text used to describe the pattern
	 *                                   in the inserter. A description is optional, but is strongly
	 *                                   encouraged when the title does not fully describe what the
	 *                                   pattern does. The description will help users discover the
	 *                                   pattern while searching.
	 *     @type int      $viewportWidth Optional. The intended width of the pattern to allow for a scaled
	 *                                   preview within the pattern inserter.
	 *     @type bool     $inserter      Optional. Determines whether the pattern is visible in inserter.
	 *                                   To hide a pattern so that it can only be inserted programmatically,
	 *                                   set this to false. Default true.
	 *     @type string[] $categories    Optional. A list of registered pattern categories used to group
	 *                                   block patterns. Block patterns can be shown on multiple categories.
	 *                                   A category must be registered separately in order to be used here.
	 *     @type string[] $keywords      Optional. A list of aliases or keywords that help users discover
	 *                                   the pattern while searching.
	 *     @type string[] $blockTypes    Optional. A list of block names including namespace that could use
	 *                                   the block pattern in certain contexts (placeholder, transforms).
	 *                                   The block pattern is available in the block editor inserter
	 *                                   regardless of this list of block names.
	 *                                   Certain blocks support further specificity besides the block name
	 *                                   (e.g. for `core/template-part` you can specify areas
	 *                                   like `core/template-part/header` or `core/template-part/footer`).
	 *     @type string[] $postTypes     Optional. An array of post types that the pattern is restricted
	 *                                   to be used with. The pattern will only be available when editing one
	 *                                   of the post types passed on the array. For all the other post types
	 *                                   not part of the array the pattern is not available at all.
	 *     @type string[] $templateTypes Optional. An array of template types where the pattern fits.
	 * }
	 * @return bool True if the pattern was registered with success and false otherwise.
	 */
	public function register( $pattern_name, $pattern_properties ) {
		if ( ! isset( $pattern_name ) || ! is_string( $pattern_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Pattern name must be a string.' ),
				'5.5.0'
			);
			return false;
		}

		if ( ! isset( $pattern_properties['title'] ) || ! is_string( $pattern_properties['title'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Pattern title must be a string.' ),
				'5.5.0'
			);
			return false;
		}

		if ( ! isset( $pattern_properties['content'] ) || ! is_string( $pattern_properties['content'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Pattern content must be a string.' ),
				'5.5.0'
			);
			return false;
		}

		$pattern = array_merge(
			$pattern_properties,
			array( 'name' => $pattern_name )
		);

		$this->registered_patterns[ $pattern_name ] = $pattern;

		// If the pattern is registered inside an action other than `init`, store it
		// also to a dedicated array. Used to detect deprecated registrations inside
		// `admin_init` or `current_screen`.
		if ( current_action() && 'init' !== current_action() ) {
			$this->registered_patterns_outside_init[ $pattern_name ] = $pattern;
		}

		return true;
	}

	/**
	 * Unregisters a block pattern.
	 *
	 * @since 5.5.0
	 *
	 * @param string $pattern_name Block pattern name including namespace.
	 * @return bool True if the pattern was unregistered with success and false otherwise.
	 */
	public function unregister( $pattern_name ) {
		if ( ! $this->is_registered( $pattern_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Pattern name. */
				sprintf( __( 'Pattern "%s" not found.' ), $pattern_name ),
				'5.5.0'
			);
			return false;
		}

		unset( $this->registered_patterns[ $pattern_name ] );
		unset( $this->registered_patterns_outside_init[ $pattern_name ] );

		return true;
	}

	/**
	 * Prepares the content of a block pattern. If hooked blocks are registered, they get injected into the pattern,
	 * when they met the defined criteria.
	 *
	 * @since 6.4.0
	 *
	 * @param array $pattern       Registered pattern properties.
	 * @param array $hooked_blocks The list of hooked blocks.
	 * @param int   $at            Byte offset into pattern at which to start looking for anchor block.
	 * @return string The content of the block pattern.
	 */
	private function prepare_content( $pattern, $hooked_blocks, $at = 0 ) {
		$content = $pattern['content'];

		$next_block_boundary = static function ( $text, $at ) {
			$block_pattern = '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s';

			if ( ! preg_match( $block_pattern, $text, $block_match, PREG_OFFSET_CAPTURE, $at ) ) {
				// No more blocks.
				return;
			}

			return array(
				'at'     => $block_match[0][1],
				'length' => strlen( $block_match[0][0] ),
				'name'   => ( $block_match['namespace'][0] ?? 'core/' ) . $block_match['name'][0],
				'attrs'  => $block_match['attrs'][0],
				'type'   => '/' === $block_match['void'][0] ? 'void' : ( '/' === $block_match['closer'][0] ? 'closer' : 'opener' ),
			);
		};

		$at     = 0;
		$budget = 10000;
		$stack  = array();
		while ( $budget-- ) {
			$block = $next_block_boundary( $content, $at );
			if ( null === $block ) {
				break;
			}

			$at = $block['at'];

			if ( 'closer' === $block['type'] ) {
				continue;
			}

			// @todo: Allow for core blocks without "core/" namespace.
			if ( ! isset( $hooked_blocks[ $block['name'] ] ) ) {
				continue;
			}

			$block_opener    = $block;

			if ( 'void' === $block['type'] ) {
				// Is Void Block; no inner blocks.
				break;
			}

			$block = $next_block_boundary( $content, $at );
			if ( null === $block ) {
				break;
			}

			$at = $block['at'];

			if ( 'closer' === $block['type'] ) {
				$block_closer      = $block;
				$first_inner_block = null;
				$last_inner_block  = null;
				break;
			}

			$first_inner_block = $block;

			$stack[]          = $first_inner_block;
			$last_inner_block = $first_inner_block;
			while ( 0 < count( $stack ) ) {
				// do this thing to scan in here
				$block = $next_block_boundary( $content, $at );
				if ( null === $block ) {
					break;
				}

				$at = $block['at'];

				if ( 'void' === $block['type'] ) {
					$last_inner_block = $block;
					continue;
				}

				if ( 'opener' === $block['type'] ) {
					$stack[] = $block;

					/**
					 * <!-- anchor1 -->
					 *     <!-- inner1 /-->
				     *     <!-- inner2 -->
			 		 *     <!-- /inner2 -->
					 * <!-- /anchor1 -->
					 *
					 *
					 * <!-- anchor1 -->
					 *      <!-- inner1 /-->
					 *      <!-- anchor2 --> <<<< restart here
					 *          <!-- inner3 /-->
					 *      <!-- /anchor2 --> >>>> return, replaced entire region with XYZ
					 *  <!-- /anchor1 -->
					 */
					if ( isset( $hooked_blocks[ $block['name'] ] ) ) {
						// recurse.
						$new_region      = recurse( $pattern, $hooked_blocks, $block['at'] );
						$content         = (
							substr( $content, 0, $block['at'] ) .
							$new_region .
							substr( $content, $closing_block['at'] + $closing_block['length'] )
						);
						$block['length'] = strlen( $new_region );
						$at              = $block['at'] + $block['length'];
					}
				}

				// @todo Check if this matches the name of the bottom stack item.
				array_pop( $stack );

				$last_inner_block = $block;
			}

			// @todo we may have bailed by now so this could be wrong.
			$closing_block = $block;
		}

		// Logic for insertion goes here.

		list( $position, $blocks_to_insert ) = $hooked_blocks[ $block_opener['name'] ];

		$block_html = implode(
			'',
			array_map(
				'get_comment_delimited_block_content',
				$blocks_to_insert,
				array_fill( 0, count( $blocks_to_insert ), array() )
			)
		);

		$next_content = '';
		switch ( $position ) {
			case 'before':
				$point = $block_opener['at'];
				break;

			case 'after':
				$point = $block_closer['at'] + $block_closer['length'];
				break;

			case 'first_child':
				$point = $first_inner_block['at'];
				break;

			case 'last_child':
				$point = $last_inner_block['at'] + $last_inner_block['length'];
				break;
		}

		// @todo fix all this recursive funny business.
		$next_content = substr( $content, 0, $point ) . $block_html . substr( $content, $point );
		$at           = $first_inner_block['at'] + $first_inner_block['length'];
		if ( $at < strlen( $content ) ) {
			return $this->prepare_content( array_merge( $pattern, array( 'content' => $next_content ) ), $hooked_blocks, $at );
		}

		$before_block_visitor = '_inject_theme_attribute_in_template_part_block';
		$after_block_visitor  = null;
		if ( ! empty( $hooked_blocks ) || has_filter( 'hooked_block_types' ) ) {
			$before_block_visitor = make_before_block_visitor( $hooked_blocks, $pattern );
			$after_block_visitor  = make_after_block_visitor( $hooked_blocks, $pattern );
		}
		$blocks  = parse_blocks( $content );
		$content = traverse_and_serialize_blocks( $blocks, $before_block_visitor, $after_block_visitor );

		return $content;
	}

	/**
	 * Retrieves an array containing the properties of a registered block pattern.
	 *
	 * @since 5.5.0
	 *
	 * @param string $pattern_name Block pattern name including namespace.
	 * @return array Registered pattern properties.
	 */
	public function get_registered( $pattern_name ) {
		if ( ! $this->is_registered( $pattern_name ) ) {
			return null;
		}

		$pattern            = $this->registered_patterns[ $pattern_name ];
		$pattern['content'] = $this->prepare_content( $pattern, get_hooked_blocks() );

		return $pattern;
	}

	/**
	 * Retrieves all registered block patterns.
	 *
	 * @since 5.5.0
	 *
	 * @param bool $outside_init_only Return only patterns registered outside the `init` action.
	 * @return array[] Array of arrays containing the registered block patterns properties,
	 *                 and per style.
	 */
	public function get_all_registered( $outside_init_only = false ) {
		$patterns      = array_values(
			$outside_init_only
				? $this->registered_patterns_outside_init
				: $this->registered_patterns
		);
		$hooked_blocks = get_hooked_blocks();
		foreach ( $patterns as $index => $pattern ) {
			$patterns[ $index ]['content'] = $this->prepare_content( $pattern, $hooked_blocks );
		}
		return $patterns;
	}

	/**
	 * Checks if a block pattern is registered.
	 *
	 * @since 5.5.0
	 *
	 * @param string $pattern_name Block pattern name including namespace.
	 * @return bool True if the pattern is registered, false otherwise.
	 */
	public function is_registered( $pattern_name ) {
		return isset( $this->registered_patterns[ $pattern_name ] );
	}

	public function __wakeup() {
		if ( ! $this->registered_patterns ) {
			return;
		}
		if ( ! is_array( $this->registered_patterns ) ) {
			throw new UnexpectedValueException();
		}
		foreach ( $this->registered_patterns as $value ) {
			if ( ! is_array( $value ) ) {
				throw new UnexpectedValueException();
			}
		}
		$this->registered_patterns_outside_init = array();
	}

	/**
	 * Utility method to retrieve the main instance of the class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @since 5.5.0
	 *
	 * @return WP_Block_Patterns_Registry The main instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

/**
 * Registers a new block pattern.
 *
 * @since 5.5.0
 *
 * @param string $pattern_name       Block pattern name including namespace.
 * @param array  $pattern_properties List of properties for the block pattern.
 *                                   See WP_Block_Patterns_Registry::register() for accepted arguments.
 * @return bool True if the pattern was registered with success and false otherwise.
 */
function register_block_pattern( $pattern_name, $pattern_properties ) {
	return WP_Block_Patterns_Registry::get_instance()->register( $pattern_name, $pattern_properties );
}

/**
 * Unregisters a block pattern.
 *
 * @since 5.5.0
 *
 * @param string $pattern_name Block pattern name including namespace.
 * @return bool True if the pattern was unregistered with success and false otherwise.
 */
function unregister_block_pattern( $pattern_name ) {
	return WP_Block_Patterns_Registry::get_instance()->unregister( $pattern_name );
}
