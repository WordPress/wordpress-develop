<?php

/**
 * Unit test factory for sites on a multisite network.
 *
 * Note: The below @method notations are defined solely for the benefit of IDEs,
 * as a way to indicate expected return values from the given factory methods.
 *
 * @method int|WP_Error     create( $args = array(), $generation_definitions = null )
 * @method WP_Site|WP_Error create_and_get( $args = array(), $generation_definitions = null )
 * @method (int|WP_Error)[] create_many( $count, $args = array(), $generation_definitions = null )
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
	 * @param array $args Arguments for the site object.
	 *
	 * @return int|WP_Error The site ID on success, WP_Error object on failure.
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

		return $blog;
	}

	/**
	 * Updates a site object. Not implemented.
	 *
	 * @param int   $blog_id ID of the site to update.
	 * @param array $fields  The fields to update.
	 *
	 * @return void
	 */
	public function update_object( $blog_id, $fields ) {}

	/**
	 * Retrieves a site by a given ID.
	 *
	 * @param int $blog_id ID of the site to retrieve.
	 *
	 * @return WP_Site|null The site object on success, null on failure.
	 */
	public function get_object_by_id( $blog_id ) {
		return get_site( $blog_id );
	}
}
