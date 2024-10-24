<?php
/**
 * Nav Menu API: Template functions
 *
 * @package WordPress
 * @subpackage Nav_Menus
 * @since 3.0.0
 */

// Don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/** Walker_Nav_Menu class */
require_once ABSPATH . WPINC . '/class-walker-nav-menu.php';

/**
 * Displays a navigation menu.
 *
 * @since 3.0.0
 * @since 4.7.0 Added the `item_spacing` argument.
 * @since 5.5.0 Added the `container_aria_label` argument.
 *
 * @param array $args {
 *     Optional. Array of nav menu arguments.
 *
 *     @type int|string|WP_Term $menu                 Desired menu. Accepts a menu ID, slug, name, or object.
 *                                                    Default empty.
 *     @type string             $menu_class           CSS class to use for the ul element which forms the menu.
 *                                                    Default 'menu'.
 *     @type string             $menu_id              The ID that is applied to the ul element which forms the menu.
 *                                                    Default is the menu slug, incremented.
 *     @type string             $container            Whether to wrap the ul, and what to wrap it with.
 *                                                    Default 'div'.
 *     @type string             $container_class      Class that is applied to the container.
 *                                                    Default 'menu-{menu slug}-container'.
 *     @type string             $container_id         The ID that is applied to the container. Default empty.
 *     @type string             $container_aria_label The aria-label attribute that is applied to the container
 *                                                    when it's a nav element. Default empty.
 *     @type callable|false     $fallback_cb          If the menu doesn't exist, a callback function will fire.
 *                                                    Default is 'wp_page_menu'. Set to false for no fallback.
 *     @type string             $before               Text before the link markup. Default empty.
 *     @type string             $after                Text after the link markup. Default empty.
 *     @type string             $link_before          Text before the link text. Default empty.
 *     @type string             $link_after           Text after the link text. Default empty.
 *     @type bool               $echo                 Whether to echo the menu or return it. Default true.
 *     @type int                $depth                How many levels of the hierarchy are to be included.
 *                                                    0 means all. Default 0.
 *                                                    Default 0.
 *     @type object             $walker               Instance of a custom walker class. Default empty.
 *     @type string             $theme_location       Theme location to be used. Must be registered with
 *                                                    register_nav_menu() in order to be selectable by the user.
 *     @type string             $items_wrap           How the list items should be wrapped. Uses printf() format with
 *                                                    numbered placeholders. Default is a ul with an id and class.
 *     @type string             $item_spacing         Whether to preserve whitespace within the menu's HTML.
 *                                                    Accepts 'preserve' or 'discard'. Default 'preserve'.
 * }
 * @return void|string|false Void if 'echo' argument is true, menu output if 'echo' is false.
 *                           False if there are no items or no menu was found.
 */
function wp_nav_menu( $args = array() ) {
	static $menu_id_slugs = array();

	$defaults = array(
		'menu'                 => '',
		'container'            => 'div',
		'container_class'      => '',
		'container_id'         => '',
		'container_aria_label' => '',
		'menu_class'           => 'menu',
		'menu_id'              => '',
		'echo'                 => true,
		'fallback_cb'          => 'wp_page_menu',
		'before'               => '',
		'after'                => '',
		'link_before'          => '',
		'link_after'           => '',
		'items_wrap'           => '<ul id="%1$s" class="%2$s">%3$s</ul>',
		'item_spacing'         => 'preserve',
		'depth'                => 0,
		'walker'               => '',
		'theme_location'       => '',
	);

	$args = wp_parse_args( $args, $defaults );

	if ( ! in_array( $args['item_spacing'], array( 'preserve', 'discard' ), true ) ) {
		// Invalid value, fall back to default.
		$args['item_spacing'] = $defaults['item_spacing'];
	}

	/**
	 * Filters the arguments used to display a navigation menu.
	 *
	 * @since 3.0.0
	 *
	 * @see wp_nav_menu()
	 *
	 * @param array $args Array of wp_nav_menu() arguments.
	 */
	$args = apply_filters( 'wp_nav_menu_args', $args );
	$args = (object) $args;

	/**
	 * Filters whether to short-circuit the wp_nav_menu() output.
	 *
	 * Returning a non-null value from the filter will short-circuit wp_nav_menu(),
	 * echoing that value if $args->echo is true, returning that value otherwise.
	 *
	 * @since 3.9.0
	 *
	 * @see wp_nav_menu()
	 *
	 * @param string|null $output Nav menu output to short-circuit with. Default null.
	 * @param stdClass    $args   An object containing wp_nav_menu() arguments.
	 */
	$nav_menu = apply_filters( 'pre_wp_nav_menu', null, $args );

	if ( null !== $nav_menu ) {
		if ( $args->echo ) {
			echo $nav_menu;
			return;
		}

		return $nav_menu;
	}

	// Get the nav menu based on the requested menu.
	$menu = wp_get_nav_menu_object( $args->menu );

	// Get the nav menu based on the theme_location.
	$locations = get_nav_menu_locations();
	if ( ! $menu && $args->theme_location && $locations && isset( $locations[ $args->theme_location ] ) ) {
		$menu = wp_get_nav_menu_object( $locations[ $args->theme_location ] );
	}

	// Get the first menu that has items if we still can't find a menu.
	if ( ! $menu && ! $args->theme_location ) {
		$menus = wp_get_nav_menus();
		foreach ( $menus as $menu_maybe ) {
			$menu_items = wp_get_nav_menu_items( $menu_maybe->term_id, array( 'update_post_term_cache' => false ) );
			if ( $menu_items ) {
				$menu = $menu_maybe;
				break;
			}
		}
	}

	if ( empty( $args->menu ) ) {
		$args->menu = $menu;
	}

	// If the menu exists, get its items.
	if ( $menu && ! is_wp_error( $menu ) && ! isset( $menu_items ) ) {
		$menu_items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
	}

	/*
	 * If no menu was found:
	 *  - Fall back (if one was specified), or bail.
	 *
	 * If no menu items were found:
	 *  - Fall back, but only if no theme location was specified.
	 *  - Otherwise, bail.
	 */
	if ( ( ! $menu || is_wp_error( $menu ) || ( isset( $menu_items ) && empty( $menu_items ) && ! $args->theme_location ) )
		&& isset( $args->fallback_cb ) && $args->fallback_cb && is_callable( $args->fallback_cb ) ) {
			return call_user_func( $args->fallback_cb, (array) $args );
	}

	if ( ! $menu || is_wp_error( $menu ) ) {
		return false;
	}

	$nav_menu = '';
	$items    = '';

	$show_container = false;
	if ( $args->container ) {
		/**
		 * Filters the list of HTML tags that are valid for use as menu containers.
		 *
		 * @since 3.0.0
		 *
		 * @param string[] $tags The acceptable HTML tags for use as menu containers.
		 *                       Default is array containing 'div' and 'nav'.
		 */
		$allowed_tags = apply_filters( 'wp_nav_menu_container_allowedtags', array( 'div', 'nav' ) );

		if ( is_string( $args->container ) && in_array( $args->container, $allowed_tags, true ) ) {
			$show_container = true;
			$class          = $args->container_class ? ' class="' . esc_attr( $args->container_class ) . '"' : ' class="menu-' . $menu->slug . '-container"';
			$id             = $args->container_id ? ' id="' . esc_attr( $args->container_id ) . '"' : '';
			$aria_label     = ( 'nav' === $args->container && $args->container_aria_label ) ? ' aria-label="' . esc_attr( $args->container_aria_label ) . '"' : '';
			$nav_menu      .= '<' . $args->container . $id . $class . $aria_label . '>';
		}
	}

	// Set up the $menu_item variables.
	_wp_menu_item_classes_by_context( $menu_items );

	$sorted_menu_items        = array();
	$menu_items_with_children = array();
	foreach ( (array) $menu_items as $menu_item ) {
		/*
		 * Fix invalid `menu_item_parent`. See: https://core.trac.wordpress.org/ticket/56926.
		 * Compare as strings. Plugins may change the ID to a string.
		 */
		if ( (string) $menu_item->ID === (string) $menu_item->menu_item_parent ) {
			$menu_item->menu_item_parent = 0;
		}

		$sorted_menu_items[ $menu_item->menu_order ] = $menu_item;
		if ( $menu_item->menu_item_parent ) {
			$menu_items_with_children[ $menu_item->menu_item_parent ] = true;
		}
	}

	// Add the menu-item-has-children class where applicable.
	if ( $menu_items_with_children ) {
		foreach ( $sorted_menu_items as &$menu_item ) {
			if ( isset( $menu_items_with_children[ $menu_item->ID ] ) ) {
				$menu_item->classes[] = 'menu-item-has-children';
			}
		}
	}

	unset( $menu_items, $menu_item );

	/**
	 * Filters the sorted list of menu item objects before generating the menu's HTML.
	 *
	 * @since 3.1.0
	 *
	 * @param array    $sorted_menu_items The menu items, sorted by each menu item's menu order.
	 * @param stdClass $args              An object containing wp_nav_menu() arguments.
	 */
	$sorted_menu_items = apply_filters( 'wp_nav_menu_objects', $sorted_menu_items, $args );

	$items .= walk_nav_menu_tree( $sorted_menu_items, $args->depth, $args );
	unset( $sorted_menu_items );

	// Attributes.
	if ( ! empty( $args->menu_id ) ) {
		$wrap_id = $args->menu_id;
	} else {
		$wrap_id = 'menu-' . $menu->slug;

		while ( in_array( $wrap_id, $menu_id_slugs, true ) ) {
			if ( preg_match( '#-(\d+)$#', $wrap_id, $matches ) ) {
				$wrap_id = preg_replace( '#-(\d+)$#', '-' . ++$matches[1], $wrap_id );
			} else {
				$wrap_id = $wrap_id . '-1';
			}
		}
	}
	$menu_id_slugs[] = $wrap_id;

	$wrap_class = $args->menu_class ? $args->menu_class : '';

	/**
	 * Filters the HTML list content for navigation menus.
	 *
	 * @since 3.0.0
	 *
	 * @see wp_nav_menu()
	 *
	 * @param string   $items The HTML list content for the menu items.
	 * @param stdClass $args  An object containing wp_nav_menu() arguments.
	 */
	$items = apply_filters( 'wp_nav_menu_items', $items, $args );
	/**
	 * Filters the HTML list content for a specific navigation menu.
	 *
	 * @since 3.0.0
	 *
	 * @see wp_nav_menu()
	 *
	 * @param string   $items The HTML list content for the menu items.
	 * @param stdClass $args  An object containing wp_nav_menu() arguments.
	 */
	$items = apply_filters( "wp_nav_menu_{$menu->slug}_items", $items, $args );

	// Don't print any markup if there are no items at this point.
	if ( empty( $items ) ) {
		return false;
	}

	$nav_menu .= sprintf( $args->items_wrap, esc_attr( $wrap_id ), esc_attr( $wrap_class ), $items );
	unset( $items );

	if ( $show_container ) {
		$nav_menu .= '</' . $args->container . '>';
	}

	/**
	 * Filters the HTML content for navigation menus.
	 *
	 * @since 3.0.0
	 *
	 * @see wp_nav_menu()
	 *
	 * @param string   $nav_menu The HTML content for the navigation menu.
	 * @param stdClass $args     An object containing wp_nav_menu() arguments.
	 */
	$nav_menu = apply_filters( 'wp_nav_menu', $nav_menu, $args );

	if ( $args->echo ) {
		echo $nav_menu;
	} else {
		return $nav_menu;
	}
}

/**
 * Adds the class property classes for the current context, if applicable.
 *
 * @access private
 * @since 3.0.0
 *
 * @global WP_Query   $wp_query   WordPress Query object.
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @param array $menu_items The current menu item objects to which to add the class property information.
 */
function _wp_menu_item_classes_by_context( &$menu_items ) {
	global $wp_query, $wp_rewrite;

	$queried_object    = $wp_query->get_queried_object();
	$queried_object_id = (int) $wp_query->queried_object_id;

	$active_object               = '';
	$active_ancestor_item_ids    = array();
	$active_parent_item_ids      = array();
	$active_parent_object_ids    = array();
	$possible_taxonomy_ancestors = array();
	$possible_object_parents     = array();
	$home_page_id                = (int) get_option( 'page_for_posts' );

	if ( $wp_query->is_singular && ! empty( $queried_object->post_type ) && ! is_post_type_hierarchical( $queried_object->post_type ) ) {
		foreach ( (array) get_object_taxonomies( $queried_object->post_type ) as $taxonomy ) {
			if ( is_taxonomy_hierarchical( $taxonomy ) ) {
				$term_hierarchy = _get_term_hierarchy( $taxonomy );
				$terms          = wp_get_object_terms( $queried_object_id, $taxonomy, array( 'fields' => 'ids' ) );
				if ( is_array( $terms ) ) {
					$possible_object_parents = array_merge( $possible_object_parents, $terms );
					$term_to_ancestor        = array();
					foreach ( (array) $term_hierarchy as $ancestor => $descendents ) {
						foreach ( (array) $descendents as $desc ) {
							$term_to_ancestor[ $desc ] = $ancestor;
						}
					}

					foreach ( $terms as $desc ) {
						do {
							$possible_taxonomy_ancestors[ $taxonomy ][] = $desc;
							if ( isset( $term_to_ancestor[ $desc ] ) ) {
								$_desc = $term_to_ancestor[ $desc ];
								unset( $term_to_ancestor[ $desc ] );
								$desc = $_desc;
							} else {
								$desc = 0;
							}
						} while ( ! empty( $desc ) );
					}
				}
			}
		}
	} elseif ( ! empty( $queried_object->taxonomy ) && is_taxonomy_hierarchical( $queried_object->taxonomy ) ) {
		$term_hierarchy   = _get_term_hierarchy( $queried_object->taxonomy );
		$term_to_ancestor = array();
		foreach ( (array) $term_hierarchy as $ancestor => $descendents ) {
			foreach ( (array) $descendents as $desc ) {
				$term_to_ancestor[ $desc ] = $ancestor;
			}
		}
		$desc = $queried_object->term_id;
		do {
			$possible_taxonomy_ancestors[ $queried_object->taxonomy ][] = $desc;
			if ( isset( $term_to_ancestor[ $desc ] ) ) {
				$_desc = $term_to_ancestor[ $desc ];
				unset( $term_to_ancestor[ $desc ] );
				$desc = $_desc;
			} else {
				$desc = 0;
			}
		} while ( ! empty( $desc ) );
	}

	$possible_object_parents = array_filter( $possible_object_parents );

	$front_page_url         = home_url();
	$front_page_id          = (int) get_option( 'page_on_front' );
	$privacy_policy_page_id = (int) get_option( 'wp_page_for_privacy_policy' );

	foreach ( (array) $menu_items as $key => $menu_item ) {

		$menu_items[ $key ]->current = false;

		$classes   = (array) $menu_item->classes;
		$classes[] = 'menu-item';
		$classes[] = 'menu-item-type-' . $menu_item->type;
		$classes[] = 'menu-item-object-' . $menu_item->object;

		// This menu item is set as the 'Front Page'.
		if ( 'post_type' === $menu_item->type && $front_page_id === (int) $menu_item->object_id ) {
			$classes[] = 'menu-item-home';
		}

		// This menu item is set as the 'Privacy Policy Page'.
		if ( 'post_type' === $menu_item->type && $privacy_policy_page_id === (int) $menu_item->object_id ) {
			$classes[] = 'menu-item-privacy-policy';
		}

		// If the menu item corresponds to a taxonomy term for the currently queried non-hierarchical post object.
		if ( $wp_query->is_singular && 'taxonomy' === $menu_item->type
			&& in_array( (int) $menu_item->object_id, $possible_object_parents, true )
		) {
			$active_parent_object_ids[] = (int) $menu_item->object_id;
			$active_parent_item_ids[]   = (int) $menu_item->db_id;
			$active_object              = $queried_object->post_type;

			// If the menu item corresponds to the currently queried post or taxonomy object.
		} elseif (
			(int) $menu_item->object_id === $queried_object_id
			&& (
				( ! empty( $home_page_id ) && 'post_type' === $menu_item->type
					&& $wp_query->is_home && $home_page_id === (int) $menu_item->object_id )
				|| ( 'post_type' === $menu_item->type && $wp_query->is_singular )
				|| ( 'taxonomy' === $menu_item->type
					&& ( $wp_query->is_category || $wp_query->is_tag || $wp_query->is_tax )
					&& $queried_object->taxonomy === $menu_item->object )
			)
		) {
			$classes[]                   = 'current-menu-item';
			$menu_items[ $key ]->current = true;
			$ancestor_id                 = (int) $menu_item->db_id;

			while (
				( $ancestor_id = (int) get_post_meta( $ancestor_id, '_menu_item_menu_item_parent', true ) )
				&& ! in_array( $ancestor_id, $active_ancestor_item_ids, true )
			) {
				$active_ancestor_item_ids[] = $ancestor_id;
			}

			if ( 'post_type' === $menu_item->type && 'page' === $menu_item->object ) {
				// Back compat classes for pages to match wp_page_menu().
				$classes[] = 'page_item';
				$classes[] = 'page-item-' . $menu_item->object_id;
				$classes[] = 'current_page_item';
			}

			$active_parent_item_ids[]   = (int) $menu_item->menu_item_parent;
			$active_parent_object_ids[] = (int) $menu_item->post_parent;
			$active_object              = $menu_item->object;

			// If the menu item corresponds to the currently queried post type archive.
		} elseif (
			'post_type_archive' === $menu_item->type
			&& is_post_type_archive( array( $menu_item->object ) )
		) {
			$classes[]                   = 'current-menu-item';
			$menu_items[ $key ]->current = true;
			$ancestor_id                 = (int) $menu_item->db_id;

			while (
				( $ancestor_id = (int) get_post_meta( $ancestor_id, '_menu_item_menu_item_parent', true ) )
				&& ! in_array( $ancestor_id, $active_ancestor_item_ids, true )
			) {
				$active_ancestor_item_ids[] = $ancestor_id;
			}

			$active_parent_item_ids[] = (int) $menu_item->menu_item_parent;

			// If the menu item corresponds to the currently requested URL.
		} elseif ( 'custom' === $menu_item->object && isset( $_SERVER['HTTP_HOST'] ) ) {
			$_root_relative_current = untrailingslashit( $_SERVER['REQUEST_URI'] );

			// If it's the customize page then it will strip the query var off the URL before entering the comparison block.
			if ( is_customize_preview() ) {
				$_root_relative_current = strtok( untrailingslashit( $_SERVER['REQUEST_URI'] ), '?' );
			}

			$current_url        = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_root_relative_current );
			$raw_item_url       = strpos( $menu_item->url, '#' ) ? substr( $menu_item->url, 0, strpos( $menu_item->url, '#' ) ) : $menu_item->url;
			$item_url           = set_url_scheme( untrailingslashit( $raw_item_url ) );
			$_indexless_current = untrailingslashit( preg_replace( '/' . preg_quote( $wp_rewrite->index, '/' ) . '$/', '', $current_url ) );

			$matches = array(
				$current_url,
				urldecode( $current_url ),
				$_indexless_current,
				urldecode( $_indexless_current ),
				$_root_relative_current,
				urldecode( $_root_relative_current ),
			);

			if ( $raw_item_url && in_array( $item_url, $matches, true ) ) {
				$classes[]                   = 'current-menu-item';
				$menu_items[ $key ]->current = true;
				$ancestor_id                 = (int) $menu_item->db_id;

				while (
					( $ancestor_id = (int) get_post_meta( $ancestor_id, '_menu_item_menu_item_parent', true ) )
					&& ! in_array( $ancestor_id, $active_ancestor_item_ids, true )
				) {
					$active_ancestor_item_ids[] = $ancestor_id;
				}

				if ( in_array( home_url(), array( untrailingslashit( $current_url ), untrailingslashit( $_indexless_current ) ), true ) ) {
					// Back compat for home link to match wp_page_menu().
					$classes[] = 'current_page_item';
				}
				$active_parent_item_ids[]   = (int) $menu_item->menu_item_parent;
				$active_parent_object_ids[] = (int) $menu_item->post_parent;
				$active_object              = $menu_item->object;

				// Give front page item the 'current-menu-item' class when extra query arguments are involved.
			} elseif ( $item_url === $front_page_url && is_front_page() ) {
				$classes[] = 'current-menu-item';
			}

			if ( untrailingslashit( $item_url ) === home_url() ) {
				$classes[] = 'menu-item-home';
			}
		}

		// Back-compat with wp_page_menu(): add "current_page_parent" to static home page link for any non-page query.
		if ( ! empty( $home_page_id ) && 'post_type' === $menu_item->type
			&& empty( $wp_query->is_page ) && $home_page_id === (int) $menu_item->object_id
		) {
			$classes[] = 'current_page_parent';
		}

		$menu_items[ $key ]->classes = array_unique( $classes );
	}
	$active_ancestor_item_ids = array_filter( array_unique( $active_ancestor_item_ids ) );
	$active_parent_item_ids   = array_filter( array_unique( $active_parent_item_ids ) );
	$active_parent_object_ids = array_filter( array_unique( $active_parent_object_ids ) );

	// Set parent's class.
	foreach ( (array) $menu_items as $key => $parent_item ) {
		$classes                                   = (array) $parent_item->classes;
		$menu_items[ $key ]->current_item_ancestor = false;
		$menu_items[ $key ]->current_item_parent   = false;

		if (
			isset( $parent_item->type )
			&& (
				// Ancestral post object.
				(
					'post_type' === $parent_item->type
					&& ! empty( $queried_object->post_type )
					&& is_post_type_hierarchical( $queried_object->post_type )
					&& in_array( (int) $parent_item->object_id, $queried_object->ancestors, true )
					&& (int) $parent_item->object_id !== $queried_object->ID
				) ||

				// Ancestral term.
				(
					'taxonomy' === $parent_item->type
					&& isset( $possible_taxonomy_ancestors[ $parent_item->object ] )
					&& in_array( (int) $parent_item->object_id, $possible_taxonomy_ancestors[ $parent_item->object ], true )
					&& (
						! isset( $queried_object->term_id ) ||
						(int) $parent_item->object_id !== $queried_object->term_id
					)
				)
			)
		) {
			if ( ! empty( $queried_object->taxonomy ) ) {
				$classes[] = 'current-' . $queried_object->taxonomy . '-ancestor';
			} else {
				$classes[] = 'current-' . $queried_object->post_type . '-ancestor';
			}
		}

		if ( in_array( (int) $parent_item->db_id, $active_ancestor_item_ids, true ) ) {
			$classes[] = 'current-menu-ancestor';

			$menu_items[ $key ]->current_item_ancestor = true;
		}
		if ( in_array( (int) $parent_item->db_id, $active_parent_item_ids, true ) ) {
			$classes[] = 'current-menu-parent';

			$menu_items[ $key ]->current_item_parent = true;
		}
		if ( in_array( (int) $parent_item->object_id, $active_parent_object_ids, true ) ) {
			$classes[] = 'current-' . $active_object . '-parent';
		}

		if ( 'post_type' === $parent_item->type && 'page' === $parent_item->object ) {
			// Back compat classes for pages to match wp_page_menu().
			if ( in_array( 'current-menu-parent', $classes, true ) ) {
				$classes[] = 'current_page_parent';
			}
			if ( in_array( 'current-menu-ancestor', $classes, true ) ) {
				$classes[] = 'current_page_ancestor';
			}
		}

		$menu_items[ $key ]->classes = array_unique( $classes );
	}
}

/**
 * Retrieves the HTML list content for nav menu items.
 *
 * @uses Walker_Nav_Menu to create HTML list content.
 * @since 3.0.0
 *
 * @param array    $items The menu items, sorted by each menu item's menu order.
 * @param int      $depth Depth of the item in reference to parents.
 * @param stdClass $args  An object containing wp_nav_menu() arguments.
 * @return string The HTML list content for the menu items.
 */
function walk_nav_menu_tree( $items, $depth, $args ) {
	$walker = ( empty( $args->walker ) ) ? new Walker_Nav_Menu() : $args->walker;

	return $walker->walk( $items, $depth, $args );
}

/**
 * Prevents a menu item ID from being used more than once.
 *
 * @since 3.0.1
 * @access private
 *
 * @param string $id
 * @param object $item
 * @return string
 */
function _nav_menu_item_id_use_once( $id, $item ) {
	static $_used_ids = array();

	if ( in_array( $item->ID, $_used_ids, true ) ) {
		return '';
	}

	$_used_ids[] = $item->ID;

	return $id;
}

/**
 * Remove the `menu-item-has-children` class from bottom level menu items.
 *
 * This runs on the {@see 'nav_menu_css_class'} filter. The $args and $depth
 * parameters were added after the filter was originally introduced in
 * WordPress 3.0.0 so this needs to allow for cases in which the filter is
 * called without them.
 *
 * @see https://core.trac.wordpress.org/ticket/56926
 *
 * @since 6.2.0
 *
 * @param string[]       $classes   Array of the CSS classes that are applied to the menu item's `<li>` element.
 * @param WP_Post        $menu_item The current menu item object.
 * @param stdClass|false $args      An object of wp_nav_menu() arguments. Default false ($args unspecified when filter is called).
 * @param int|false      $depth     Depth of menu item. Default false ($depth unspecified when filter is called).
 * @return string[] Modified nav menu classes.
 */
function wp_nav_menu_remove_menu_item_has_children_class( $classes, $menu_item, $args = false, $depth = false ) {
	/*
	 * Account for the filter being called without the $args or $depth parameters.
	 *
	 * This occurs when a theme uses a custom walker calling the `nav_menu_css_class`
	 * filter using the legacy formats prior to the introduction of the $args and
	 * $depth parameters.
	 *
	 * As both of these parameters are required for this function to determine
	 * both the current and maximum depth of the menu tree, the function does not
	 * attempt to remove the `menu-item-has-children` class if these parameters
	 * are not set.
	 */
	if ( false === $depth || false === $args ) {
		return $classes;
	}

	// Max-depth is 1-based.
	$max_depth = isset( $args->depth ) ? (int) $args->depth : 0;
	// Depth is 0-based so needs to be increased by one.
	$depth = $depth + 1;

	// Complete menu tree is displayed.
	if ( 0 === $max_depth ) {
		return $classes;
	}

	/*
	 * Remove the `menu-item-has-children` class from bottom level menu items.
	 * -1 is used to display all menu items in one level so the class should
	 * be removed from all menu items.
	 */
	if ( -1 === $max_depth || $depth >= $max_depth ) {
		$classes = array_diff( $classes, array( 'menu-item-has-children' ) );
	}

	return $classes;
}
