<?php

/**
 * Unit test factory for networks.
 *
 * Note: The below @method notation is defined solely for the benefit of IDEs,
 * as a way to indicate the expected return value from the given factory method.
 *
 * @method WP_Network create_and_get( $args = array(), $generation_definitions = null )
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
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array<string, mixed> $args Arguments for the network object.
	 * @return positive-int The network ID.
	 * @throws WP_UnitTest_Factory_Exception When the network could not be created.
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

		$network_id = is_wp_error( $result ) ? $result : (int) $args['network_id'];

		$this->assert_valid_object_id( $network_id, 'Unable to create the network' );

		return $network_id;
	}

	/**
	 * Updates a network object.
	 *
	 * Not implemented. This throws rather than doing nothing so that an after-create
	 * callback, whose result create() feeds through here, cannot look as though it was
	 * applied when nothing was written.
	 *
	 * @todo Implement via a direct update of the site table, so that after-create callbacks work with this factory.
	 *
	 * @since 3.9.0
	 * @since 7.2.0 Throws an exception instead of silently doing nothing.
	 *
	 * @param int                  $network_id ID of the network to update.
	 * @param array<string, mixed> $fields     The fields to update.
	 * @return never
	 * @throws WP_UnitTest_Factory_Exception Always, since updating a network is not supported.
	 */
	public function update_object( $network_id, $fields ) {
		throw new WP_UnitTest_Factory_Exception(
			'Updating a network is not implemented in ' . __CLASS__ . '.'
		);
	}

	/**
	 * Retrieves a network by a given ID.
	 *
	 * @since 3.9.0
	 * @since 7.2.0 Throws an exception instead of returning null when the object cannot be retrieved.
	 *
	 * @param int $network_id ID of the network to retrieve.
	 * @return WP_Network The network object.
	 * @throws WP_UnitTest_Factory_Exception When the network could not be retrieved.
	 */
	public function get_object_by_id( $network_id ) {
		$network = get_network( $network_id );

		$this->assert_valid_object( $network, $network_id, WP_Network::class );

		return $network;
	}
}
