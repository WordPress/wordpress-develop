<?php
/**
 * Class 'WP_View_Transition_Animation'.
 *
 * @package WordPress
 * @subpackage View Transitions
 * @since 6.9.0
 */

/**
 * Class representing a view transition animation.
 *
 * @since 6.9.0
 * @access private
 */
final class WP_View_Transition_Animation {

	/**
	 * The unique animation slug.
	 *
	 * @since 6.9.0
	 * @var string
	 */
	private $slug;

	/**
	 * Unique aliases for the animation, if any.
	 *
	 * @since 6.9.0
	 * @var string[]
	 */
	private $aliases = array();

	/**
	 * Whether the animation uses a stylesheet.
	 *
	 * If so, the stylesheet will be `/wp-includes/css/view-transitions-animation-{$slug}.css`.
	 *
	 * @since 6.9.0
	 * @var bool
	 */
	private $use_stylesheet = false;

	/**
	 * Whether to apply the global view transition names while using this animation.
	 *
	 * @since 6.9.0
	 * @var bool|callable
	 */
	private $use_global_transition_names = true;

	/**
	 * Whether to apply the post specific view transition names while using this animation.
	 *
	 * @since 6.9.0
	 * @var bool|callable
	 */
	private $use_post_transition_names = true;

	/**
	 * Callback to get the stylesheet for the animation, as inline CSS.
	 *
	 * This can be used if the animation CSS requires further preparation other than simply loading its stylesheet from
	 * the animation's corresponding CSS file.
	 *
	 * If the animation is configured with `$use_stylesheet = true`, the callback will receive the CSS from that file,
	 * and the `$alias` and `$args` used as parameters. Otherwise, the callback will receive the `$alias` and `$args`
	 * used as parameters.
	 *
	 * @since 6.9.0
	 * @var callable|null
	 */
	private $get_stylesheet_callback = null;

	/**
	 * Default animation arguments.
	 *
	 * These are provided during registration, and they are used if no specific arguments are provided when using the
	 * animation.
	 *
	 * @since 6.9.0
	 * @var array<string, mixed>
	 */
	private $default_args = array();

	/**
	 * Constructor.
	 *
	 * @since 6.9.0
	 *
	 * @param string               $slug         Unique animation slug.
	 * @param array<string, mixed> $config       {
	 *     Animation configuration.
	 *
	 *     @type string[]      $aliases                     Unique aliases for the animation, if any. Default empty
	 *                                                      array.
	 *     @type bool          $use_stylesheet              Whether the animation uses a stylesheet. Default false.
	 *     @type bool|callable $use_global_transition_names Whether to apply the global view transition names while
	 *                                                      using this animation. Alternatively to a concrete value, a
	 *                                                      callback can be specified to determine it dynamically.
	 *                                                      Default true.
	 *     @type bool|callable $use_post_transition_names   Whether to apply the post specific view transition names
	 *                                                      while using this animation. Alternatively to a concrete
	 *                                                      value, acallback can be specified to determine it
	 *                                                      dynamically. Default true.
	 *     @type callable|null $get_stylesheet_callback     Callback to get the stylesheet for the animation, as
	 *                                                      inline CSS. This can be used if the animation CSS requires
	 *                                                      further preparation other than simply loading its
	 *                                                      stylesheet from the animation's corresponding CSS file.
	 *                                                      Default null.
	 * }
	 * @param array<string, mixed> $default_args Optional. Default animation arguments. Default empty array.
	 *
	 * @throws InvalidArgumentException Thrown if the slug or an alias is invalid.
	 */
	public function __construct( string $slug, array $config, array $default_args = array() ) {
		if ( ! $this->is_valid_slug( $slug ) ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: invalid slug */
					__( 'The animation slug "%s" is invalid.' ),
					esc_html( $slug )
				)
			);
		}

		$this->slug = $slug;

		$this->apply_config( $config );

		$this->default_args = $default_args;
	}

	/**
	 * Gets the unique animation slug.
	 *
	 * @since 6.9.0
	 *
	 * @return string Unique animation slug.
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Gets the unique aliases for the animation, if any.
	 *
	 * @since 6.9.0
	 *
	 * @return string[] Unique aliases for the animation, or empty array if none.
	 */
	public function get_aliases(): array {
		return $this->aliases;
	}

	/**
	 * Gets the animation stylesheet, as inline CSS.
	 *
	 * @since 6.9.0
	 *
	 * @param string               $alias Optional. Slug or alias to reference the animation with. May be used to alter
	 *                                    the animation's behavior. Default is the animation's slug.
	 * @param array<string, mixed> $args  Optional. Animation arguments. Default is the animation's default arguments.
	 * @return string Animation stylesheet, as inline CSS, or empty string if none.
	 */
	public function get_stylesheet( string $alias = '', array $args = array() ): string {
		if ( $this->use_stylesheet ) {
			$suffix = wp_scripts_get_suffix();
			$css    = file_get_contents( ABSPATH . WPINC . "/css/view-transitions-animation-{$this->slug}{$suffix}.css" );
		}
		if ( is_callable( $this->get_stylesheet_callback ) ) {
			if ( ! $alias ) {
				$alias = $this->slug;
			}
			$args = wp_parse_args( $args, $this->default_args );
			return (string) call_user_func_array(
				$this->get_stylesheet_callback,
				isset( $css ) ? array( $css, $alias, $args ) : array( $alias, $args )
			);
		}
		return '';
	}

	/**
	 * Returns whether to apply the global view transition names while using this animation.
	 *
	 * @since 6.9.0
	 *
	 * @param string               $alias Optional. Slug or alias to reference the animation with. May be used to alter
	 *                                    the animation's behavior. Default is the animation's slug.
	 * @param array<string, mixed> $args  Optional. Animation arguments. Default is the animation's default arguments.
	 * @return bool True if the global view transition names should be applied, false otherwise.
	 */
	public function use_global_transition_names( string $alias = '', array $args = array() ): bool {
		if ( is_bool( $this->use_global_transition_names ) ) {
			return $this->use_global_transition_names;
		}
		if ( ! $alias ) {
			$alias = $this->slug;
		}
		$args = wp_parse_args( $args, $this->default_args );
		return call_user_func( $this->use_global_transition_names, $alias, $args );
	}

	/**
	 * Returns whether to apply the post specific view transition names while using this animation.
	 *
	 * @since 6.9.0
	 *
	 * @param string               $alias Optional. Slug or alias to reference the animation with. May be used to alter
	 *                                    the animation's behavior. Default is the animation's slug.
	 * @param array<string, mixed> $args  Optional. Animation arguments. Default is the animation's default arguments.
	 * @return bool True if the post specific view transition names should be applied, false otherwise.
	 */
	public function use_post_transition_names( string $alias = '', array $args = array() ): bool {
		if ( is_bool( $this->use_post_transition_names ) ) {
			return $this->use_post_transition_names;
		}
		if ( ! $alias ) {
			$alias = $this->slug;
		}
		$args = wp_parse_args( $args, $this->default_args );
		return call_user_func( $this->use_post_transition_names, $alias, $args );
	}

	/**
	 * Applies the given configuration to the class properties.
	 *
	 * @since 6.9.0
	 *
	 * @param array<string, mixed> $config Animation configuration. See
	 *                                     {@see WP_View_Transition_Animation::__construct()} for possible values.
	 */
	private function apply_config( array $config ): void {
		if ( isset( $config['aliases'] ) ) {
			$this->aliases = (array) $config['aliases'];
			foreach ( $this->aliases as $alias ) {
				if ( ! $this->is_valid_slug( $alias ) ) {
					throw new InvalidArgumentException(
						sprintf(
							/* translators: %s: invalid alias */
							__( 'The animation alias "%s" is invalid.' ),
							esc_html( $alias )
						)
					);
				}
			}
		}
		if ( isset( $config['use_stylesheet'] ) ) {
			$this->use_stylesheet = (bool) $config['use_stylesheet'];
		}
		if ( isset( $config['use_global_transition_names'] ) ) {
			$this->use_global_transition_names = is_callable( $config['use_global_transition_names'] ) ?
				$config['use_global_transition_names'] :
				(bool) $config['use_global_transition_names'];
		}
		if ( isset( $config['use_post_transition_names'] ) ) {
			$this->use_post_transition_names = is_callable( $config['use_post_transition_names'] ) ?
				$config['use_post_transition_names'] :
				(bool) $config['use_post_transition_names'];
		}
		if ( isset( $config['get_stylesheet_callback'] ) && is_callable( $config['get_stylesheet_callback'] ) ) {
			$this->get_stylesheet_callback = $config['get_stylesheet_callback'];
		}
	}

	/**
	 * Checks whether the given slug (or alias) is valid.
	 *
	 * @since 6.9.0
	 *
	 * @param string $slug Animation slug or alias.
	 * @return bool True if the ID is valid, false otherwise.
	 */
	private function is_valid_slug( string $slug ): bool {
		return (bool) preg_match( '/^[a-z][a-z0-9_-]+$/', $slug );
	}
}
