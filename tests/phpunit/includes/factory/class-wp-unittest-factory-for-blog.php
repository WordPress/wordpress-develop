<?php

/**
 * Unit test factory for sites on a multisite network.
 *
 * Note: The below @method notations are defined solely for the benefit of IDEs,
 * as a way to indicate expected return values from the given factory methods.
 *
 * @method int create( $args = array(), $generation_definitions = null )
 * @method WP_Site create_and_get( $args = array(), $generation_definitions = null )
 * @method int[] create_many( $count, $args = array(), $generation_definitions = null )
 */
class WP_UnitTest_Factory_For_Blog extends WP_UnitTest_Factory_For_Thing {

	public function __construct( $factory = null ) {
		global $current_site, $base;
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'domain'  => $current_site->domain,
			'path'    => new WP_UnitTest_Generator_Sequence( $base . 'testpath%s' ),
			'title'   => new WP_UnitTest_Generator_Sequence( 'Site %s' ),
			'site_id' => $current_site->id,
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
		$meta    = isset( $args['meta'] ) ? $args['meta'] : array( 'public' => 1 );
		$user_id = isset( $args['user_id'] ) ? $args['user_id'] : get_current_user_id();
		// temp tables will trigger db errors when we attempt to reference them as new temp tables
		$suppress = $wpdb->suppress_errors();
		$blog     = wpmu_create_blog( $args['domain'], $args['path'], $args['title'], $user_id, $meta, $args['site_id'] );
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
