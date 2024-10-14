<?php
/**
 * Taxonomy API: WP_Term class
 *
 * @package WordPress
 * @subpackage Taxonomy
 * @since 4.4.0
 */

/**
 * Core class used to implement the WP_Term object.
 *
 * @since 4.4.0
 *
 * @property-read object $data Sanitized term data.
 */
#[AllowDynamicProperties]
final class WP_Term {

	/**
	 * Term ID.
	 *
	 * @since 4.4.0
	 * @var int
	 */
	public $term_id;

	/**
	 * The term's name.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	public $name = '';

	/**
	 * The term's slug.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	public $slug = '';

	/**
	 * The term's term_group.
	 *
	 * @since 4.4.0
	 * @var int
	 */
	public $term_group = '';

	/**
	 * Term Taxonomy ID.
	 *
	 * @since 4.4.0
	 * @var int
	 */
	public $term_taxonomy_id = 0;

	/**
	 * The term's taxonomy name.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	public $taxonomy = '';

	/**
	 * The term's description.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	public $description = '';

	/**
	 * ID of a term's parent term.
	 *
	 * @since 4.4.0
	 * @var int
	 */
	public $parent = 0;

	/**
	 * Cached object count for this term.
	 *
	 * @since 4.4.0
	 * @var int
	 */
	public $count = 0;

	/**
	 * Stores the term object's sanitization level.
	 *
	 * Does not correspond to a database field.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	public $filter = 'raw';

	/**
	 * Stores the object ID.
	 *
	 * @since 6.6.0
	 * @var int|null
	 */
	public $object_id;

	/**
	 * Stores the category ID.
	 *
	 * @since 6.6.0
	 * @var int|null
	 */
	public $cat_ID;

	/**
	 * Stores the category count.
	 *
	 * @since 6.6.0
	 * @var int|null
	 */
	public $category_count;

	/**
	 * Stores the description of the category.
	 *
	 * @since 6.6.0
	 * @var string|null
	 */
	public $category_description;

	/**
	 * Stores the name of the category.
	 *
	 * @since 6.6.0
	 * @var string|null
	 */
	public $cat_name;

	/**
	 * Stores the 'nice' name of the category (used in URLs).
	 *
	 * @since 6.6.0
	 * @var string|null
	 */
	public $category_nicename;

	/**
	 * Stores the ID of the parent category.
	 *
	 * @since 6.6.0
	 * @var int|null
	 */
	public $category_parent;

	/**
	 * Stores the link associated with the term.
	 *
	 * @since 6.6.0
	 * @var string|null
	 */
	public $link;

	/**
	 * Stores the term ID.
	 *
	 * @since 6.6.0
	 * @var int|null
	 */
	public $id;

	/**
	 * Indicates whether the menu should automatically add new top-level pages.
	 *
	 * @since 6.6.0
	 * @var bool|null
	 */
	public $auto_add;

	/**
	 * Retrieve WP_Term instance.
	 *
	 * @since 4.4.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Optional. Limit matched terms to those matching `$taxonomy`. Only used for
	 *                         disambiguating potentially shared terms.
	 * @return WP_Term|WP_Error|false Term object, if found. WP_Error if `$term_id` is shared between taxonomies and
	 *                                there's insufficient data to distinguish which term is intended.
	 *                                False for other failures.
	 */
	public static function get_instance( $term_id, $taxonomy = null ) {
		global $wpdb;

		$term_id = (int) $term_id;
		if ( ! $term_id ) {
			return false;
		}

		$_term = wp_cache_get( $term_id, 'terms' );

		// If there isn't a cached version, hit the database.
		if ( ! $_term || ( $taxonomy && $taxonomy !== $_term->taxonomy ) ) {
			// Any term found in the cache is not a match, so don't use it.
			$_term = false;

			// Grab all matching terms, in case any are shared between taxonomies.
			$terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE t.term_id = %d", $term_id ) );
			if ( ! $terms ) {
				return false;
			}

			// If a taxonomy was specified, find a match.
			if ( $taxonomy ) {
				foreach ( $terms as $match ) {
					if ( $taxonomy === $match->taxonomy ) {
						$_term = $match;
						break;
					}
				}

				// If only one match was found, it's the one we want.
			} elseif ( 1 === count( $terms ) ) {
				$_term = reset( $terms );

				// Otherwise, the term must be shared between taxonomies.
			} else {
				// If the term is shared only with invalid taxonomies, return the one valid term.
				foreach ( $terms as $t ) {
					if ( ! taxonomy_exists( $t->taxonomy ) ) {
						continue;
					}

					// Only hit if we've already identified a term in a valid taxonomy.
					if ( $_term ) {
						return new WP_Error( 'ambiguous_term_id', __( 'Term ID is shared between multiple taxonomies' ), $term_id );
					}

					$_term = $t;
				}
			}

			if ( ! $_term ) {
				return false;
			}

			// Don't return terms from invalid taxonomies.
			if ( ! taxonomy_exists( $_term->taxonomy ) ) {
				return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
			}

			$_term = sanitize_term( $_term, $_term->taxonomy, 'raw' );

			// Don't cache terms that are shared between taxonomies.
			if ( 1 === count( $terms ) ) {
				wp_cache_add( $term_id, $_term, 'terms' );
			}
		}

		$term_obj = new WP_Term( $_term );
		$term_obj->filter( $term_obj->filter );

		return $term_obj;
	}

	/**
	 * Constructor.
	 *
	 * @since 4.4.0
	 *
	 * @param WP_Term|object $term Term object.
	 */
	public function __construct( $term ) {
		foreach ( get_object_vars( $term ) as $key => $value ) {
			$this->$key = $value;
		}
	}

	/**
	 * Sanitizes term fields, according to the filter type provided.
	 *
	 * @since 4.4.0
	 *
	 * @param string $filter Filter context. Accepts 'edit', 'db', 'display', 'attribute', 'js', 'rss', or 'raw'.
	 */
	public function filter( $filter ) {
		sanitize_term( $this, $this->taxonomy, $filter );
	}

	/**
	 * Converts an object to array.
	 *
	 * @since 4.4.0
	 *
	 * @return array Object as array.
	 */
	public function to_array() {
		return get_object_vars( $this );
	}

	/**
	 * Getter.
	 *
	 * @since 4.4.0
	 * @since 6.6.0 Getting dynamic class properties is deprecated.
	 *
	 * @param string $key Property to get.
	 * @return object|null Property value.
	 */
	public function __get( $key ) {
		if ( 'data' !== $key ) {
			// Public class properties are not dynamic.
			//if ( ! static::check_if_public_class_property( $key ) ) {
				wp_trigger_error(
					__METHOD__,
					sprintf( 'Getting the dynamic property "%s" on %s is deprecated.', $key, __CLASS__ ),
					E_USER_DEPRECATED
				);
			//}
			//return null;
		}

		$data    = new stdClass();
		$columns = array(
			'term_id',
			'name',
			'slug',
			'term_group',
			'term_taxonomy_id',
			'taxonomy',
			'description',
			'parent',
			'count',
		);
		foreach ( $columns as $column ) {
			$data->{$column} = isset( $this->{$column} ) ? $this->{$column} : null;
		}

		return sanitize_term( $data, $data->taxonomy, 'raw' );
	}

	/**
	 * This method specifically returns true for the "data" property because it is read-only and
	 * is always defined. It returns false for any other properties to reflect that dynamic class
	 * properties are deprecated and not supported.
	 *
	 * @since 6.6.0
	 *
	 * @param string $name Property to check.
	 * @return bool True if the property exists, false otherwise.
	 */
	public function __isset( $name ) {
		// Only the "data" dynamic property is supported.
		return 'data' === $name;
	}

	/**
	 * Sets the "data" class property.
	 * Triggers an error when attempting to set a dynamic class property since dynamic class
	 * properties are deprecated.
	 *
	 * @since 6.6.0
	 *
	 * @param string $name  The name of the property to set.
	 * @param mixed  $value The value to set.
	 */
	public function __set( $name, $value ) {
		if ( 'data' === $name ) {
			// Since "data" is a read-only property, setting it should have no effect.
			return;
		}

		// Setting a public property should not generate errors.
//		if ( static::check_if_public_class_property( $name ) ) {
//			$this->$name = $value;
//			return;
//		}

		wp_trigger_error(
			__METHOD__,
			sprintf( 'Setting the dynamic property "%s" on %s is deprecated.', $name, __CLASS__ ),
			E_USER_DEPRECATED
		);
	}

	/**
	 * Unsets the "data" class property.
	 * Triggers an error when attempting to unset a dynamic class property since dynamic class
	 * properties are deprecated.
	 *
	 * @since 6.6.0
	 *
	 * @param string $name The name of the property to unset.
	 */
	public function __unset( $name ) {
		if ( 'data' === $name ) {
			// Since "data" is a read-only property, unsetting it should have no effect.
			return;
		}

		// Unsetting a public property should not generate errors.
//		if ( static::check_if_public_class_property( $name ) ) {
//			return;
//		}

		wp_trigger_error(
			__METHOD__,
			sprintf( 'Unsetting the dynamic property "%s" on %s is deprecated.', $name, __CLASS__ ),
			E_USER_DEPRECATED
		);
	}

	/**
	 * Checks whether a property is declared as public.
	 *
	 * @since 6.6.0
	 *
	 * @param string $name The name of the property to check.
	 * @return bool True if the property is public, false otherwise.
	 */
	private static function check_if_public_class_property( $name ) {
		// The Reflection API is not used here for performance reasons.
		// As the list is hardcoded, all newly declared public properties should be added to the list manually.
		$public_class_properties = array(
			'term_id',
			'name',
			'slug',
			'term_group',
			'term_taxonomy_id',
			'taxonomy',
			'description',
			'parent',
			'count',
			'filter',
			'object_id',
			'cat_ID',
			'category_count',
			'category_description',
			'cat_name',
			'category_nicename',
			'category_parent',
			'link',
			'id',
			'auto_add',
		);

		return in_array( $name, $public_class_properties, true );
	}
}
