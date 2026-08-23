<?php
/**
 * Comment API: WP_Comment_Type class
 *
 * @package WordPress
 * @subpackage Comments
 * @since 7.1.0
 */

/**
 * Core class used for interacting with comment types.
 *
 * @since 7.1.0
 *
 * @see register_comment_type()
 */
#[AllowDynamicProperties]
final class WP_Comment_Type {
	/**
	 * Comment type key.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	public $name;

	/**
	 * Name of the comment type. Usually plural.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	public $label;

	/**
	 * Labels object for this comment type.
	 *
	 * If not set, the default comment labels are used.
	 *
	 * @see get_comment_type_labels()
	 *
	 * @since 7.1.0
	 * @var stdClass
	 */
	public $labels;

	/**
	 * Default labels.
	 *
	 * @since 7.1.0
	 * @var (string|null)[][] $default_labels
	 */
	protected static $default_labels = array();

	/**
	 * A short descriptive summary of what the comment type is for.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	public $description = '';

	/**
	 * Whether a comment type is intended for use publicly either via the admin interface or by front-end users.
	 *
	 * Core does not currently act on this property, but it is the intended default
	 * for future visibility-related arguments. It defaults to true so that
	 * registering a type in order to provide labels never hides comments that are
	 * already publicly visible.
	 *
	 * Default true.
	 *
	 * @since 7.1.0
	 * @var bool
	 */
	public $public = true;

	/**
	 * Whether the comment type is for internal use only.
	 *
	 * Analogous to the `internal` argument of register_post_status(). Internal types
	 * are meant to be excluded from comment queries and counts by default. Core does
	 * not currently act on this flag.
	 *
	 * Default false.
	 *
	 * @since 7.1.0
	 * @var bool
	 */
	public $internal = false;

	/**
	 * Whether this comment type is a native or "built-in" comment type.
	 *
	 * Default false.
	 *
	 * @since 7.1.0
	 * @var bool
	 */
	public $_builtin = false;

	/**
	 * Whether the comment type is hierarchical.
	 *
	 * Comment types are never hierarchical. This property exists so the shared label
	 * helper {@see _get_custom_object_labels()} can resolve default labels, and
	 * set_props() forces it to false so a provided value cannot resolve them to null.
	 *
	 * @since 7.1.0
	 * @var bool
	 */
	public $hierarchical = false;

	/**
	 * Constructor.
	 *
	 * See the register_comment_type() function for accepted arguments for `$args`.
	 *
	 * Will populate object properties from the provided arguments and assign other
	 * default properties based on that information.
	 *
	 * @since 7.1.0
	 *
	 * @see register_comment_type()
	 *
	 * @param string       $comment_type Comment type key.
	 * @param array|string $args         Optional. Array or string of arguments for registering a comment type.
	 *                                   Default empty array.
	 */
	public function __construct( $comment_type, $args = array() ) {
		$this->name = $comment_type;

		$this->set_props( $args );
	}

	/**
	 * Sets comment type properties.
	 *
	 * See the register_comment_type() function for accepted arguments for `$args`.
	 *
	 * @since 7.1.0
	 *
	 * @param array|string $args Array or string of arguments for registering a comment type.
	 */
	public function set_props( $args ) {
		$args = wp_parse_args( $args );

		/**
		 * Filters the arguments for registering a comment type.
		 *
		 * @since 7.1.0
		 *
		 * @param array  $args         Array of arguments for registering a comment type.
		 *                             See the register_comment_type() function for accepted arguments.
		 * @param string $comment_type Comment type key.
		 */
		$args = apply_filters( 'register_comment_type_args', $args, $this->name );

		$comment_type = $this->name;

		/**
		 * Filters the arguments for registering a specific comment type.
		 *
		 * The dynamic portion of the filter name, `$comment_type`, refers to the comment type key.
		 *
		 * Possible hook names include:
		 *
		 *  - `register_comment_comment_type_args`
		 *  - `register_pingback_comment_type_args`
		 *
		 * @since 7.1.0
		 *
		 * @param array  $args         Array of arguments for registering a comment type.
		 *                             See the register_comment_type() function for accepted arguments.
		 * @param string $comment_type Comment type key.
		 */
		$args = apply_filters( "register_{$comment_type}_comment_type_args", $args, $this->name );

		/*
		 * Note: 'label' is intentionally omitted from the defaults. Leaving the property
		 * unset (null) lets get_comment_type_labels() fall back to the default labels, the
		 * same way WP_Post_Type and WP_Taxonomy behave. A 'label' default of false would be
		 * treated as a provided value and overwrite the default name with false.
		 */
		$defaults = array(
			'labels'      => array(),
			'description' => '',
			'public'      => true,
			'internal'    => false,
			'_builtin'    => false,
		);

		$args = array_merge( $defaults, $args );

		$args['name'] = $this->name;

		/*
		 * Comment types are never hierarchical. The property exists only so the shared
		 * label helper can pick a slot, and the hierarchical slot is deliberately null,
		 * so honoring a provided value would resolve every default label to null.
		 */
		$args['hierarchical'] = false;

		foreach ( $args as $property_name => $property_value ) {
			$this->$property_name = $property_value;
		}

		$this->labels = get_comment_type_labels( $this );
		$this->label  = $this->labels->name;
	}

	/**
	 * Returns the default labels for comment types.
	 *
	 * @since 7.1.0
	 *
	 * @return (string|null)[][] The default labels for comment types.
	 */
	public static function get_default_labels() {
		if ( ! empty( self::$default_labels ) ) {
			return self::$default_labels;
		}

		self::$default_labels = array(
			'name'          => array( _x( 'Comments', 'comment type general name' ), null ),
			'singular_name' => array( _x( 'Comment', 'comment type singular name' ), null ),
		);

		return self::$default_labels;
	}

	/**
	 * Resets the cache for the default labels.
	 *
	 * @since 7.1.0
	 */
	public static function reset_default_labels() {
		self::$default_labels = array();
	}
}
