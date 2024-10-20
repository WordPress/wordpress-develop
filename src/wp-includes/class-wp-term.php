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
	 * Object's ID.
	 *
	 * Does not correspond to a database field.
	 * Previously a known, named dynamic property added by WP_Term_Query::get_terms().
	 *
	 * @since 6.7.0
	 * @var int
	 */
	public $object_id;

	/**
	 * Term's ID.
	 *
	 * Same as the WP_Term::$term_id property.
	 * Previously a known, named dynamic property added by wp_tag_cloud().
	 *
	 * @since 6.7.0
	 * @var int
	 */
	public $id;

	/**
	 * Term's link.
	 *
	 * Does not correspond to a database field.
	 * Previously a known, named dynamic property added by wp_tag_cloud().
	 *
	 * @since 6.7.0
	 * @var string
	 */
	public $link;

	/**
	 * Category's ID.
	 *
	 * Same as the WP_Term::$term_id property.
	 * Previously a known, named dynamic property added by _make_cat_compat().
	 *
	 * @since 6.7.0
	 * @var int
	 */
	public $cat_ID;

	/**
	 * The category's name.
	 *
	 * Same as the WP_Term::$name property.
	 * Previously a known, named dynamic property added by _make_cat_compat().
	 *
	 * @since 6.7.0
	 * @var string
	 */
	public $cat_name = '';

	/**
	 * The category's nicename.
	 *
	 * Same as the WP_Term::$slug property.
	 * Previously a known, named dynamic property added by _make_cat_compat().
	 *
	 * @since 6.7.0
	 * @var string
	 */
	public $category_nicename = '';

	/**
	 * The category's description.
	 *
	 * Same as the WP_Term::$description property.
	 * Previously a known, named dynamic property added by _make_cat_compat().
	 *
	 * @since 6.7.0
	 * @var string
	 */
	public $category_description = '';

	/**
	 * ID of a category's parent term.
	 *
	 * Same as the WP_Term::$parent property.
	 * Previously a known, named dynamic property added by _make_cat_compat().
	 *
	 * @since 6.7.0
	 * @var int
	 */
	public $category_parent = 0;

	/**
	 * Cached object count for this category.
	 *
	 * Same as the WP_Term::$count property.
	 * Previously a known, named dynamic property added by _make_cat_compat().
	 *
	 * @since 6.7.0
	 * @var int
	 */
	public $category_count = 0;

	/**
	 * Value of the menu's auto_add setting.
	 *
	 * Does not correspond to a database field.
	 * Previously a known, named dynamic property added by WP_REST_Menus_Controller::get_term().
	 *
	 * @since 6.7.0
	 * @var bool
	 */
	public $auto_add = false;

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
	 * @since 6.7.0 Getting a dynamic property is deprecated.
	 *
	 * @param string $key Property to get.
	 * @return mixed Property value.
	 */
	public function __get( $name ) {
		if ( 'data' === $name ) {
			$data    = new stdClass();
			$columns = array( 'term_id', 'name', 'slug', 'term_group', 'term_taxonomy_id', 'taxonomy', 'description', 'parent', 'count' );
			foreach ( $columns as $column ) {
				$data->{$column} = isset( $this->{$column} ) ? $this->{$column} : null;
			}

			return sanitize_term( $data, $data->taxonomy, 'raw' );
		}

		wp_trigger_error(
			__METHOD__,
			"The property `{$name}` is not declared. Getting a dynamic property is " .
			'deprecated since version 6.7.0! Instead, declare the property on the class.',
			E_USER_DEPRECATED
		);
		return null;
	}
}
