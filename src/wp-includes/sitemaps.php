/**
 * Retrieves the full URL for a sitemap.
 *
 * @since 5.5.1
 *
 * @param string $name         The sitemap name.
 * @param string $subtype_name The sitemap subtype name.  Default empty string.
 * @param int    $page         The page of the sitemap.  Default 1.
 * @return string|false The sitemap URL or false if the sitemap doesn't exist.
 */
function get_sitemap_url( $name, $subtype_name = '', $page = 1 ) {
	/**
	 * Short-circuits the retrieval of the sitemap URL.
	 *
	 * @since 5.5.2
	 *
	 * @param string $name         The sitemap name.
	 * @param string $subtype_name The sitemap subtype name.
	 * @param int    $page         The page of the sitemap.
	 */
	$check = apply_filters( 'pre_get_sitemap_url', null, $name, $subtype_name, $page )
	if ( null !== $check ) {
	    return $check;
	}

	$sitemaps = wp_sitemaps_get_server();
	if ( ! $sitemaps ) {
		return false;
	}

	if ( 'index' === $name ) {
		return $sitemaps->index->get_index_url();
	}

	$provider = $sitemaps->registry->get_provider( $name );
	if ( ! $provider ) {
		return false;
	}

	if ( $subtype_name && ! in_array( $subtype_name, array_keys( $provider->get_object_subtypes() ), true ) ) {
		return false;
	}

	$page = absint( $page );
	if ( 0 >= $page ) {
		$page = 1;
	}

	return $provider->get_sitemap_url( $subtype_name, $page );
}
