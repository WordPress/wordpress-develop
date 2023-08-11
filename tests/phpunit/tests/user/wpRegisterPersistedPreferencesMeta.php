<?php

/**
 * @group user
 *
 * @covers ::wp_register_persisted_preferences_meta
 */
class Tests_User_WpRegisterPersistedPreferencesMeta extends WP_UnitTestCase {

	/**
	 * Test that user persisted preferences meta is registered.
	 *
	 * @ticket 56467
	 */
	public function test_should_register_persisted_preferences_meta() {
		global $wpdb, $wp_meta_keys;
		$meta_key = $wpdb->get_blog_prefix() . 'persisted_preferences';

		// Test that meta key is registered.
		unregister_meta_key( 'user', $meta_key );
		wp_register_persisted_preferences_meta();

		$this->assertIsArray( $wp_meta_keys, 'No meta keys exist' );
		$this->assertArrayHasKey(
			$meta_key,
			$wp_meta_keys['user'][''],
			'The expected meta key was not registered'
		);

		// Test to detect changes in meta key structure.
		$this->assertSame(
			array(
				'type'              => 'object',
				'description'       => '',
				'single'            => true,
				'sanitize_callback' => null,
				'auth_callback'     => '__return_true',
				'show_in_rest'      => array(
					'name'   => 'persisted_preferences',
					'type'   => 'object',
					'schema' => array(
						'type'                 => 'object',
						'context'              => array( 'edit' ),
						'properties'           => array(
							'_modified' => array(
								'description' => __( 'The date and time the preferences were updated.' ),
								'type'        => 'string',
								'format'      => 'date-time',
								'readonly'    => false,
							),
						),
						'additionalProperties' => true,
					),
				),
			),
			$wp_meta_keys['user'][''][ $meta_key ],
			'The registered metadata did not have the expected structure'
		);
	}

}
