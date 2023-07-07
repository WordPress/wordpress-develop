<?php

/**
 * Unit test factory for networks.
 *
 * Note: The below @method notations are defined solely for the benefit of IDEs,
 * as a way to indicate expected return values from the given factory methods.
 *
 * @method int|WP_Error        create( $args = array(), $generation_definitions = null )
 * @method WP_Network|WP_Error create_and_get( $args = array(), $generation_definitions = null )
 * @method (int|WP_Error)[]    create_many( $count, $args = array(), $generation_definitions = null )
 */
class WP_UnitTest_Factory_For_Network extends WP_UnitTest_Factory_For_Thing {

	public function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'domain'            => WP_TESTS_DOMAIN,
			'title'             => new WP_UnitTest_Generator_Sequence( 'Network %s' ),
			'path'              => new WP_UnitTest_Generator_Sequence( '/testpath%s/' ),
			'network_id'        => new WP_UnitTest_Generator_Sequence( '%s', 2 ),
			'subdomain_install' => false,
		);
	}

	/**
	 * Creates a network object.
	 *
	 * @since 3.9.0
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 *
	 * @param array $args Arguments for the network object.
	 *
	 * @return int|WP_Error The network ID on success, WP_Error object on failure.
	 */
	public function create_object( $args ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( ! isset( $args['user'] ) ) {
			$email = WP_TESTS_EMAIL;
		} else {
			$email = get_userdata( $args['user'] )->user_email;
		}

		$result = populate_network(
			$args['network_id'],
			$args['domain'],
			$email,
			$args['title'],
			$args['path'],
			$args['subdomain_install']
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return (int) $args['network_id'];
	}

	/**
	 * Updates a network object. Not implemented.
	 *
	 * @since 3.9.0
	 *
	 * @param int   $network_id ID of the network to update.
	 * @param array $fields  The fields to update.
	 *
	 * @return void
	 */
	public function update_object( $network_id, $fields ) {}

	/**
	 * Retrieves a network by a given ID.
	 *
	 * @since 3.9.0
	 *
	 * @param int $network_id ID of the network to retrieve.
	 *
	 * @return WP_Network|null The network object on success, null on failure.
	 */
	public function get_object_by_id( $network_id ) {
		return get_network( $network_id );
	}
}
