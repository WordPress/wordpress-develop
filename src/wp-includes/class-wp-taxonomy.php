<?php
/**
 * Taxonomy API: WP_Taxonomy class
 *
 * @package WordPress
 * @subpackage Taxonomy
 * @since 4.7.0
 */

/**
 * Core class used for interacting with taxonomies.
 *
 * @since 4.7.0
 */
#[AllowDynamicProperties]
final class WP_Taxonomy {
	/**
	 * Taxonomy key.
	 *
	 * @since 4.7.0
	 * @var string
	 */
	public $name;

	/**
	 * Name of the taxonomy shown in the menu. Usually plural.
	 *
	 * @since 4.7.0
	 * @var string
	 */
	public $label;

	/**
	 * Labels object for this taxonomy.
	 *
	 * If not set, tag labels are inherited for non-hierarchical types
	 * and category labels for hierarchical ones.
	 *
	 * @see get_taxonomy_labels()
	 *
	 * @since 4.7.0
	 * @var stdClass
	 */
	public $labels;

	/**
	 * Default labels.
	 *
	 * @since 6.0.0
	 * @var (string|null)[][] $default_labels
	 */
	protected static $default_labels = array();

	/**
	 * A short descriptive summary of what the taxonomy is for.
	 *
	 * @since 4.7.0
	 * @var string
	 */
	public $description = '';

	/**
	 * Whether a taxonomy is intended for use publicly either via the admin interface or by front-end users.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	public $public = true;

	/**
	 * Whether the taxonomy is publicly queryable.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	public $publicly_queryable = true;

	/**
	 * Whether the taxonomy is hierarchical.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	public $hierarchical = false;

	/**
	 * Whether to generate and allow a UI for managing terms in this taxonomy in the admin.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	public $show_ui = true;

	/**
	 * Whether to show the taxonomy in the admin menu.
	 *
	 * If true, the taxonomy is shown as a submenu of the object type menu. If false, no menu is shown.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	public $show_in_menu = true;

	/**
	 * Whether the taxonomy is available for selection in navigation menus.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	public $show_in_nav_menus = true;

	/**
	 * Whether to list the taxonomy in the tag cloud widget controls.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	public $show_tagcloud = true;

	/**
	 * Whether to show the taxonomy in the quick/bulk edit panel.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	public $show_in_quick_edit = true;

	/**
	 * Whether to display a column for the taxonomy on its post type listing screens.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	public $show_admin_column = false;

	/**
	 * The callback function for the meta box display.
	 *
	 * @since 4.7.0
	 * @var bool|callable
	 */
	public $meta_box_cb = null;

	/**
	 * The callback function for sanitizing taxonomy data saved from a meta box.
	 *
	 * @since 5.1.0
	 * @var callable
	 */
	public $meta_box_sanitize_cb = null;

	/**
	 * An array of object types this taxonomy is registered for.
	 *
	 * @since 4.7.0
	 * @var string[]
	 */
	public $object_type = null;

	/**
	 * Capabilities for this taxonomy.
	 *
	 * @since 4.7.0
	 * @var stdClass
	 */
	public $cap;

	/**
	 * Rewrites information for this taxonomy.
	 *
	 * @since 4.7.0
	 * @var array|false
	 */
	public $rewrite;

	/**
	 * Query var string for this taxonomy.
	 *
	 * @since 4.7.0
	 * @var string|false
	 */
	public $query_var;

	/**
	 * Function that will be called when the count is updated.
	 *
	 * @since 4.7.0
	 * @var callable
	 */
	public $update_count_callback;

	/**
	 * Function that will be called when the count is modified by an amount.
	 *
	 * @since 5.9.0
	 * @var callable
	 */
	public $update_count_by_callback;

	/**
	 * Whether this taxonomy should appear in the REST API.
	 *
	 * Default false. If true, standard endpoints will be registered with
	 * respect to $rest_base and $rest_controller_class.
	 *
	 * @since 4.7.4
	 * @var bool $show_in_rest
	 */
	public $show_in_rest;

	/**
	 * The base path for this taxonomy's REST API endpoints.
	 *
	 * @since 4.7.4
	 * @var string|bool $rest_base
	 */
	public $rest_base;

	/**
	 * The namespace for this taxonomy's REST API endpoints.
	 *
	 * @since 5.9.0
	 * @var string|bool $rest_namespace
	 */
	public $rest_namespace;

	/**
	 * The controller for this taxonomy's REST API endpoints.
	 *
	 * Custom controllers must extend WP_REST_Controller.
	 *
	 * @since 4.7.4
	 * @var string|bool $rest_controller_class
	 */
	public $rest_controller_class;

	/**
	 * The controller instance for this taxonomy's REST API endpoints.
	 *
	 * Lazily computed. Should be accessed using {@see WP_Taxonomy::get_rest_controller()}.
	 *
	 * @since 5.5.0
	 * @var WP_REST_Controller $rest_controller
	 */
	public $rest_controller;

	/**
	 * The default term name for this taxonomy. If you pass an array you have
	 * to set 'name' and optionally 'slug' and 'description'.
	 *
	 * @since 5.5.0
	 * @var array|string
	 */
	public $default_term;

	/**
	 * Whether terms in this taxonomy should be sorted in the order they are provided to `wp_set_object_terms()`.
	 *
	 * Use this in combination with `'orderby' => 'term_order'` when fetching terms.
	 *
	 * @since 2.5.0
	 * @var bool|null
	 */
	public $sort = null;

	/**
	 * Array of arguments to automatically use inside `wp_get_object_terms()` for this taxonomy.
	 *
	 * @since 2.6.0
	 * @var array|null
	 */
	public $args = null;

	/**
	 * Whether it is a built-in taxonomy.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	public $_builtin;

	/**
	 * Constructor.
	 *
	 * See the register_taxonomy() function for accepted arguments for `$args`.
	 *
	 * @since 4.7.0
	 *
	 * @param string       $taxonomy    Taxonomy key, must not exceed 32 characters.
	 * @param array|string $object_type Name of the object type for the taxonomy object.
	 * @param array|string $args        Optional. Array or query string of arguments for registering a taxonomy.
	 *                                  See register_taxonomy() for information on accepted arguments.
	 *                                  Default empty array.
	 */
	public function __construct( $taxonomy, $object_type, $args = array() ) {
		$this->name = $taxonomy;

		$this->set_props( $object_type, $args );
	}

	/**
	 * Sets taxonomy properties.
	 *
	 * See the register_taxonomy() function for accepted arguments for `$args`.
	 *
	 * @since 4.7.0
	 *
	 * @param string|string[] $object_type Name or array of names of the object types for the taxonomy.
	 * @param array|string    $args        Array or query string of arguments for registering a taxonomy.
	 */
	public function set_props( $object_type, $args ) {
		$args = wp_parse_args( $args );

		/**
		 * Filters the arguments for registering a taxonomy.
		 *
		 * @since 4.4.0
		 *
		 * @param array    $args        Array of arguments for registering a taxonomy.
		 *                              See the register_taxonomy() function for accepted arguments.
		 * @param string   $taxonomy    Taxonomy key.
		 * @param string[] $object_type Array of names of object types for the taxonomy.
		 */
		$args = apply_filters( 'register_taxonomy_args', $args, $this->name, (array) $object_type );

		$taxonomy = $this->name;

		/**
		 * Filters the arguments for registering a specific taxonomy.
		 *
		 * The dynamic portion of the filter name, `$taxonomy`, refers to the taxonomy key.
		 *
		 * Possible hook names include:
		 *
		 *  - `register_category_taxonomy_args`
		 *  - `register_post_tag_taxonomy_args`
		 *
		 * @since 6.0.0
		 *
		 * @param array    $args        Array of arguments for registering a taxonomy.
		 *                              See the register_taxonomy() function for accepted arguments.
		 * @param string   $taxonomy    Taxonomy key.
		 * @param string[] $object_type Array of names of object types for the taxonomy.
		 */
		$args = apply_filters( "register_{$taxonomy}_taxonomy_args", $args, $this->name, (array) $object_type );

		$defaults = array(
			'labels'                   => array(),
			'description'              => '',
			'public'                   => true,
			'publicly_queryable'       => null,
			'hierarchical'             => false,
			'show_ui'                  => null,
			'show_in_menu'             => null,
			'show_in_nav_menus'        => null,
			'show_tagcloud'            => null,
			'show_in_quick_edit'       => null,
			'show_admin_column'        => false,
			'meta_box_cb'              => null,
			'meta_box_sanitize_cb'     => null,
			'capabilities'             => array(),
			'rewrite'                  => true,
			'query_var'                => $this->name,
			'update_count_callback'    => '',
			'update_count_by_callback' => '',
			'show_in_rest'             => false,
			'rest_base'                => false,
			'rest_namespace'           => false,
			'rest_controller_class'    => false,
			'default_term'             => null,
			'sort'                     => null,
			'args'                     => null,
			'_builtin'                 => false,
		);

		$args = array_merge( $defaults, $args );

		// If not set, default to the setting for 'public'.
		if ( null === $args['publicly_queryable'] ) {
			$args['publicly_queryable'] = $args['public'];
		}

		if ( false !== $args['query_var'] && ( is_admin() || false !== $args['publicly_queryable'] ) ) {
			if ( true === $args['query_var'] ) {
				$args['query_var'] = $this->name;
			} else {
				$args['query_var'] = sanitize_title_with_dashes( $args['query_var'] );
			}
		} else {
			// Force 'query_var' to false for non-public taxonomies.
			$args['query_var'] = false;
		}

		if ( false !== $args['rewrite'] && ( is_admin() || get_option( 'permalink_structure' ) ) ) {
			$args['rewrite'] = wp_parse_args(
				$args['rewrite'],
				array(
					'with_front'   => true,
					'hierarchical' => false,
					'ep_mask'      => EP_NONE,
				)
			);

			if ( empty( $args['rewrite']['slug'] ) ) {
				$args['rewrite']['slug'] = sanitize_title_with_dashes( $this->name );
			}
		}

		// If not set, default to the setting for 'public'.
		if ( null === $args['show_ui'] ) {
			$args['show_ui'] = $args['public'];
		}

		// If not set, default to the setting for 'show_ui'.
		if ( null === $args['show_in_menu'] || ! $args['show_ui'] ) {
			$args['show_in_menu'] = $args['show_ui'];
		}

		// If not set, default to the setting for 'public'.
		if ( null === $args['show_in_nav_menus'] ) {
			$args['show_in_nav_menus'] = $args['public'];
		}

		// If not set, default to the setting for 'show_ui'.
		if ( null === $args['show_tagcloud'] ) {
			$args['show_tagcloud'] = $args['show_ui'];
		}

		// If not set, default to the setting for 'show_ui'.
		if ( null === $args['show_in_quick_edit'] ) {
			$args['show_in_quick_edit'] = $args['show_ui'];
		}

		// If not set, default rest_namespace to wp/v2 if show_in_rest is true.
		if ( false === $args['rest_namespace'] && ! empty( $args['show_in_rest'] ) ) {
			$args['rest_namespace'] = 'wp/v2';
		}

		$default_caps = array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		);

		$args['cap'] = (object) array_merge( $default_caps, $args['capabilities'] );
		unset( $args['capabilities'] );

		$args['object_type'] = array_unique( (array) $object_type );

		// If not set, use the default meta box.
		if ( null === $args['meta_box_cb'] ) {
			if ( $args['hierarchical'] ) {
				$args['meta_box_cb'] = 'post_categories_meta_box';
			} else {
				$args['meta_box_cb'] = 'post_tags_meta_box';
			}
		}

		$args['name'] = $this->name;

		// Default meta box sanitization callback depends on the value of 'meta_box_cb'.
		if ( null === $args['meta_box_sanitize_cb'] ) {
			switch ( $args['meta_box_cb'] ) {
				case 'post_categories_meta_box':
					$args['meta_box_sanitize_cb'] = 'taxonomy_meta_box_sanitize_cb_checkboxes';
					break;

				case 'post_tags_meta_box':
				default:
					$args['meta_box_sanitize_cb'] = 'taxonomy_meta_box_sanitize_cb_input';
					break;
			}
		}

		// Default taxonomy term.
		if ( ! empty( $args['default_term'] ) ) {
			if ( ! is_array( $args['default_term'] ) ) {
				$args['default_term'] = array( 'name' => $args['default_term'] );
			}
			$args['default_term'] = wp_parse_args(
				$args['default_term'],
				array(
					'name'        => '',
					'slug'        => '',
					'description' => '',
				)
			);
		}

		// If generic update callback is defined but increment/decrement callback is not.
		if (
			! empty( $args['update_count_callback'] ) &&
			is_callable( $args['update_count_callback'] ) &&
			empty( $args['update_count_by_callback'] )
		) {
			$args['update_count_by_callback'] = function( $tt_ids, $taxonomy ) use ( $args ) {
				return call_user_func( $args['update_count_callback'], $tt_ids, $taxonomy );
			};
		}

		foreach ( $args as $property_name => $property_value ) {
			$this->$property_name = $property_value;
		}

		$this->labels = get_taxonomy_labels( $this );
		$this->label  = $this->labels->name;
	}

	/**
	 * Adds the necessary rewrite rules for the taxonomy.
	 *
	 * @since 4.7.0
	 *
	 * @global WP $wp Current WordPress environment instance.
	 */
	public function add_rewrite_rules() {
		/* @var WP $wp */
		global $wp;

		// Non-publicly queryable taxonomies should not register query vars, except in the admin.
		if ( false !== $this->query_var && $wp ) {
			$wp->add_query_var( $this->query_var );
		}

		if ( false !== $this->rewrite && ( is_admin() || get_option( 'permalink_structure' ) ) ) {
			if ( $this->hierarchical && $this->rewrite['hierarchical'] ) {
				$tag = '(.+?)';
			} else {
				$tag = '([^/]+)';
			}

			add_rewrite_tag( "%$this->name%", $tag, $this->query_var ? "{$this->query_var}=" : "taxonomy=$this->name&term=" );
			add_permastruct( $this->name, "{$this->rewrite['slug']}/%$this->name%", $this->rewrite );
		}
	}

	/**
	 * Removes any rewrite rules, permastructs, and rules for the taxonomy.
	 *
	 * @since 4.7.0
	 *
	 * @global WP $wp Current WordPress environment instance.
	 */
	public function remove_rewrite_rules() {
		/* @var WP $wp */
		global $wp;

		// Remove query var.
		if ( false !== $this->query_var ) {
			$wp->remove_query_var( $this->query_var );
		}

		// Remove rewrite tags and permastructs.
		if ( false !== $this->rewrite ) {
			remove_rewrite_tag( "%$this->name%" );
			remove_permastruct( $this->name );
		}
	}

	/**
	 * Registers the ajax callback for the meta box.
	 *
	 * @since 4.7.0
	 */
	public function add_hooks() {
		add_filter( 'wp_ajax_add-' . $this->name, '_wp_ajax_add_hierarchical_term' );
	}

	/**
	 * Removes the ajax callback for the meta box.
	 *
	 * @since 4.7.0
	 */
	public function remove_hooks() {
		remove_filter( 'wp_ajax_add-' . $this->name, '_wp_ajax_add_hierarchical_term' );
	}

	/**
	 * Gets the REST API controller for this taxonomy.
	 *
	 * Will only instantiate the controller class once per request.
	 *
	 * @since 5.5.0
	 *
	 * @return WP_REST_Controller|null The controller instance, or null if the taxonomy
	 *                                 is set not to show in rest.
	 */
	public function get_rest_controller() {
		if ( ! $this->show_in_rest ) {
			return null;
		}

		$class = $this->rest_controller_class ? $this->rest_controller_class : WP_REST_Terms_Controller::class;

		if ( ! class_exists( $class ) ) {
			return null;
		}

		if ( ! is_subclass_of( $class, WP_REST_Controller::class ) ) {
			return null;
		}

		if ( ! $this->rest_controller ) {
			$this->rest_controller = new $class( $this->name );
		}

		if ( ! ( $this->rest_controller instanceof $class ) ) {
			return null;
		}

		return $this->rest_controller;
	}

	/**
	 * Returns the default labels for taxonomies.
	 *
	 * @since 6.0.0
	 *
	 * @return (string|null)[][] The default labels for taxonomies.
	 */
	public static function get_default_labels() {
		if ( ! empty( self::$default_labels ) ) {
			return self::$default_labels;
		}

		$name_field_description   = __( 'The name is how it appears on your site.' );
		$slug_field_description   = __( 'The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.' );
		$parent_field_description = __( 'Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band.' );
		$desc_field_description   = __( 'The description is not prominent by default; however, some themes may show it.' );

		self::$default_labels = array(
			'name'                       => array( _x( 'Tags', 'taxonomy general name' ), _x( 'Categories', 'taxonomy general name' ) ),
			'singular_name'              => array( _x( 'Tag', 'taxonomy singular name' ), _x( 'Category', 'taxonomy singular name' ) ),
			'search_items'               => array( __( 'Search Tags' ), __( 'Search Categories' ) ),
			'popular_items'              => array( __( 'Popular Tags' ), null ),
			'all_items'                  => array( __( 'All Tags' ), __( 'All Categories' ) ),
			'parent_item'                => array( null, __( 'Parent Category' ) ),
			'parent_item_colon'          => array( null, __( 'Parent Category:' ) ),
			'name_field_description'     => array( $name_field_description, $name_field_description ),
			'slug_field_description'     => array( $slug_field_description, $slug_field_description ),
			'parent_field_description'   => array( null, $parent_field_description ),
			'desc_field_description'     => array( $desc_field_description, $desc_field_description ),
			'edit_item'                  => array( __( 'Edit Tag' ), __( 'Edit Category' ) ),
			'view_item'                  => array( __( 'View Tag' ), __( 'View Category' ) ),
			'update_item'                => array( __( 'Update Tag' ), __( 'Update Category' ) ),
			'add_new_item'               => array( __( 'Add New Tag' ), __( 'Add New Category' ) ),
			'new_item_name'              => array( __( 'New Tag Name' ), __( 'New Category Name' ) ),
			'separate_items_with_commas' => array( __( 'Separate tags with commas' ), null ),
			'add_or_remove_items'        => array( __( 'Add or remove tags' ), null ),
			'choose_from_most_used'      => array( __( 'Choose from the most used tags' ), null ),
			'not_found'                  => array( __( 'No tags found.' ), __( 'No categories found.' ) ),
			'no_terms'                   => array( __( 'No tags' ), __( 'No categories' ) ),
			'filter_by_item'             => array( null, __( 'Filter by category' ) ),
			'items_list_navigation'      => array( __( 'Tags list navigation' ), __( 'Categories list navigation' ) ),
			'items_list'                 => array( __( 'Tags list' ), __( 'Categories list' ) ),
			/* translators: Tab heading when selecting from the most used terms. */
			'most_used'                  => array( _x( 'Most Used', 'tags' ), _x( 'Most Used', 'categories' ) ),
			'back_to_items'              => array( __( '&larr; Go to Tags' ), __( '&larr; Go to Categories' ) ),
			'item_link'                  => array(
				_x( 'Tag Link', 'navigation link block title' ),
				_x( 'Category Link', 'navigation link block title' ),
			),
			'item_link_description'      => array(
				_x( 'A link to a tag.', 'navigation link block description' ),
				_x( 'A link to a category.', 'navigation link block description' ),
			),
		);

		return self::$default_labels;
	}

	/**
	 * Resets the cache for the default labels.
	 *
	 * @since 6.0.0
	 */
	public static function reset_default_labels() {
		self::$default_labels = array();
	}
}
