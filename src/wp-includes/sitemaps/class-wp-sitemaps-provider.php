<?php
/**
 * Sitemaps: WP_Sitemaps_Provider class
 *
 * This class is a base class for other sitemap providers to extend and contains shared functionality.
 *
 * @package WordPress
 * @subpackage Sitemaps
 * @since 5.5.0
 */

/**
 * Class WP_Sitemaps_Provider.
 *
 * @since 5.5.0
 */
#[AllowDynamicProperties]
abstract class WP_Sitemaps_Provider {
	/**
	 * Provider name.
	 *
	 * This will also be used as the public-facing name in URLs.
	 *
	 * @since 5.5.0
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Object type name (e.g. 'post', 'term', 'user').
	 *
	 * @since 5.5.0
	 *
	 * @var string
	 */
	protected $object_type = '';

	/**
	 * Gets a URL list for a sitemap.
	 *
	 * @since 5.5.0
	 *
	 * @param int    $page_num       Page of results.
	 * @param string $object_subtype Optional. Object subtype name. Default empty.
	 * @return array[] Array of URL information for a sitemap.
	 */
	abstract public function get_url_list( $page_num, $object_subtype = '' );

	/**
	 * Gets the max number of pages available for the object type.
	 *
	 * @since 5.5.0
	 *
	 * @param string $object_subtype Optional. Object subtype. Default empty.
	 * @return int Total number of pages.
	 */
	abstract public function get_max_num_pages( $object_subtype = '' );

	/**
	 * Gets data about each sitemap type.
	 *
	 * @since 5.5.0
	 *
	 * @return array[] Array of sitemap types including object subtype name and number of pages.
	 */
	public function get_sitemap_type_data() {
		$sitemap_data = array();

		$object_subtypes = $this->get_object_subtypes();

		/*
		 * If there are no object subtypes, include a single sitemap for the
		 * entire object type.
		 */
		if ( empty( $object_subtypes ) ) {
			$sitemap_data[] = array(
				'name'  => '',
				'pages' => $this->get_max_num_pages(),
			);
			return $sitemap_data;
		}

		// Otherwise, include individual sitemaps for every object subtype.
		foreach ( $object_subtypes as $object_subtype_name => $data ) {
			$object_subtype_name = (string) $object_subtype_name;

			$sitemap_data[] = array(
				'name'  => $object_subtype_name,
				'pages' => $this->get_max_num_pages( $object_subtype_name ),
			);
		}

		return $sitemap_data;
	}

	/**
	 * Lists sitemap pages exposed by this provider.
	 *
	 * The returned data is used to populate the sitemap entries of the index.
	 *
	 * @since 5.5.0
	 * @since 7.1.0 Added $format parameter.
	 *
	 * @param string $format The format for the sitemap index.  Accepts 'xml', 'html'.
	 * @return array[] Array of sitemap entries.
	 */
	public function get_sitemap_entries( $format ) {
		$sitemaps = array();

		if ( ! in_array( $format, array( 'xml', 'html' ), true ) ) {
			$format = 'xml';
		}

		$sitemap_types = $this->get_sitemap_type_data();

		foreach ( $sitemap_types as $type ) {
			for ( $page = 1; $page <= $type['pages']; $page++ ) {
				$sitemap_entry = array(
					'loc' => $this->get_sitemap_url( $type['name'], $page, $format ),
				);

				/**
				 * Filters the sitemap entry for the sitemap index.
				 *
				 * @since 5.5.0
				 * @since 7.1.0 Added $format parameter.
				 *
				 * @param array  $sitemap_entry  Sitemap entry for the post.
				 * @param string $object_type    Object empty name.
				 * @param string $object_subtype Object subtype name.
				 *                               Empty string if the object type does not support subtypes.
				 * @param int    $page           Page number of results.
				 * @param string $format         The format for the sitemap index.  Accepts 'xml', 'html'.
				 */
				$sitemap_entry = apply_filters( 'wp_sitemaps_index_entry', $sitemap_entry, $this->object_type, $type['name'], $page, $format );

				$sitemaps[] = $sitemap_entry;
			}
		}

		return $sitemaps;
	}

	/**
	 * Gets the URL of a sitemap entry.
	 *
	 * @since 5.5.0
	 * @since 7.1.0 Added $format parameter.
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 *
	 * @param string $name   The name of the sitemap.
	 * @param int    $page   The page of the sitemap.
	 * @param string $format The format for the sitemap index.  Accepts 'xml', 'html'.
	 * @return string The composed URL for a sitemap entry.
	 */
	public function get_sitemap_url( $name, $page, $format ) {
		global $wp_rewrite;

		// Accounts for cases where name is not included, ex: sitemaps-users-1.xml.
		$params = array_filter(
			array(
				'sitemap'         => $this->name,
				'sitemap-subtype' => $name,
				'paged'           => $page,
			)
		);

		if ( ! in_array( $format, array( 'xml', 'html' ), true ) ) {
			$format = 'xml';
		}

		$basename = sprintf(
			'/wp-sitemap-%1$s.%2$s',
			implode( '-', $params ),
			$format
		);

		if ( ! $wp_rewrite->using_permalinks() ) {
			$params['sitemap-format'] = $format;

			$basename = '/?' . http_build_query( $params, '', '&' );
		}

		return home_url( $basename );
	}

	/**
	 * Returns the list of supported object subtypes exposed by the provider.
	 *
	 * @since 5.5.0
	 *
	 * @return array List of object subtypes objects keyed by their name.
	 */
	public function get_object_subtypes() {
		return array();
	}
}
