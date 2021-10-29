<?php

/**
 * Class WP_Sitemaps_Large_Test_Provider.
 *
 * Provides test data for additional registered providers.
 */
class WP_Sitemaps_Large_Test_Provider extends WP_Sitemaps_Provider {
	/**
	 * Number of entries in the sitemap the provider produces.
	 *
	 * @var integer
	 */
	public $num_entries = 1;

	/**
	 * WP_Sitemaps_Large_Test_Provider constructor.
	 *
	 * @param int $num_entries Optional. Number of entries in in the sitemap.
	 */
	public function __construct( $num_entries = 50001 ) {
		$this->name        = 'tests';
		$this->object_type = 'test';

		$this->num_entries = $num_entries;
	}

	/**
	 * Gets a URL list for a sitemap.
	 *
	 * @param int    $page_num       Page of results.
	 * @param string $object_subtype Optional. Object subtype name. Default empty.
	 * @return array List of URLs for a sitemap.
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		return array_fill( 0, $this->num_entries, array( 'loc' => home_url( '/' ) ) );
	}

	/**
	 * Lists sitemap pages exposed by this provider.
	 *
	 * The returned data is used to populate the sitemap entries of the index.
	 *
	 * @return array[] Array of sitemap entries.
	 */
	public function get_sitemap_entries() {
		return array_fill( 0, $this->num_entries, array( 'loc' => home_url( '/' ) ) );
	}

	/**
	 * Query for determining the number of pages.
	 *
	 * @param string $object_subtype Optional. Object subtype. Default empty.
	 * @return int Total number of pages.
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		return $this->num_entries;
	}
}
