<?php

/**
 * Unit test factory for sites on a multisite network.
 *
 * Note: The below @method notation is defined solely for the benefit of IDEs,
 * as a way to indicate the expected return value from the given factory method.
 *
 * @method WP_Site create_and_get( $args = array(), $generation_definitions = null )
 */
class WP_UnitTest_Factory_For_Blog extends WP_UnitTest_Factory_For_Thing {

	public function __construct( $factory = null ) {
		global $current_site, $base;
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'domain'     => $current_site->domain,
			'path'       => new WP_UnitTest_Generator_Sequence( $base . 'testpath%s' ),
			'title'      => new WP_UnitTest_Generator_Sequence( 'Site %s' ),
			'network_id' => $current_site->id,
		);
	}

	/**
	 * Creates a site object.
	 *
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array<string, mixed> $args Arguments for the site object.
	 * @return positive-int The site ID.
	 * @throws WP_UnitTest_Factory_Exception When the site could not be created.
	 */
	public function create_object( $args ) {
		global $wpdb;

		// Map some arguments for backward compatibility with `wpmu_create_blog()` previously used here.
		if ( isset( $args['site_id'] ) ) {
			$args['network_id'] = $args['site_id'];
			unset( $args['site_id'] );
		}

		if ( isset( $args['meta'] ) ) {
			// The `$allowed_data_fields` matches the one used in `wpmu_create_blog()`.
			$allowed_data_fields = array( 'public', 'archived', 'mature', 'spam', 'deleted', 'lang_id' );

			foreach ( $args['meta'] as $key => $value ) {
				// Promote allowed keys to top-level arguments, add others to the options array.
				if ( in_array( $key, $allowed_data_fields, true ) ) {
					$args[ $key ] = $value;
				} else {
					$args['options'][ $key ] = $value;
				}
			}

			unset( $args['meta'] );
		}

		// Temporary tables will trigger DB errors when we attempt to reference them as new temporary tables.
		$suppress = $wpdb->suppress_errors();

		$blog = wp_insert_site( $args );

		$wpdb->suppress_errors( $suppress );

		// Tell WP we're done installing.
		wp_installing( false );

		$this->assert_valid_object_id( $blog, 'Unable to create the site' );

		return $blog;
	}

	/**
	 * Updates a site object.
	 *
	 * Not implemented. This throws rather than doing nothing so that an after-create
	 * callback, whose result create() feeds through here, cannot look as though it was
	 * applied when nothing was written.
	 *
	 * @todo Implement via wp_update_site(), so that after-create callbacks work with this factory.
	 *
	 * @since 7.2.0 Throws an exception instead of silently doing nothing.
	 *
	 * @param int                  $blog_id ID of the site to update.
	 * @param array<string, mixed> $fields  The fields to update.
	 * @return never
	 * @throws WP_UnitTest_Factory_Exception Always, since updating a site is not supported.
	 */
	public function update_object( $blog_id, $fields ) {
		throw new WP_UnitTest_Factory_Exception(
			'Updating a site is not implemented in ' . __CLASS__ . '.'
		);
	}

	/**
	 * Retrieves a site by a given ID.
	 *
	 * @since 7.2.0 Throws an exception instead of returning null when the object cannot be retrieved.
	 *
	 * @param int $blog_id ID of the site to retrieve.
	 * @return WP_Site The site object.
	 * @throws WP_UnitTest_Factory_Exception When the site could not be retrieved.
	 */
	public function get_object_by_id( $blog_id ) {
		$site = get_site( $blog_id );

		$this->assert_valid_object( $site, $blog_id, WP_Site::class );

		return $site;
	}
}
