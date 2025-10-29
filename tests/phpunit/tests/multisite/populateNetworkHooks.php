<?php

if ( is_multisite() ) :

	/**
	 * Tests for the populate_network hooks.
	 *
	 * @group ms-network
	 * @group ms-populate-network
	 * @group multisite
	 */
	class Tests_Multisite_PopulateNetworkHooks extends WP_UnitTestCase {
		protected $action_counts = array(
			'before_populate_network'    => 0,
			'after_upgrade_to_multisite' => 0,
			'after_populate_network'     => 0,
		);

		protected $action_args = array();

		/**
		 * Flag to track if hook was called.
		 */
		public $hook_called = false;

		public function hook_action_counter( $network_id, $domain, $email, $site_name, $path, $subdomain_install ) {
			$action = current_filter();
			++$this->action_counts[ $action ];
			$this->action_args[ $action ] = array(
				'network_id'        => $network_id,
				'domain'            => $domain,
				'email'             => $email,
				'site_name'         => $site_name,
				'path'              => $path,
				'subdomain_install' => $subdomain_install,
			);
		}

		/**
		 * Test that the before_populate_network hook fires.
		 *
		 * @ticket 27289
		 */
		public function test_before_populate_network_hook() {
			$this->action_counts = array_fill_keys( array_keys( $this->action_counts ), 0 );
			$this->action_args   = array();

			add_action( 'before_populate_network', array( $this, 'hook_action_counter' ), 10, 6 );
			add_action( 'after_populate_network', array( $this, 'hook_action_counter' ), 10, 6 );

			$domain     = 'example' . time() . '.org';
			$network_id = self::factory()->network->create(
				array(
					'domain' => $domain,
					'path'   => '/',
				)
			);

			$this->assertSame( 1, $this->action_counts['before_populate_network'], 'before_populate_network action should fire once' );
			$this->assertSame( 1, $this->action_counts['after_populate_network'], 'after_populate_network action should fire once' );

			$this->assertEquals( $network_id, $this->action_args['before_populate_network']['network_id'], 'Network ID should match in before_populate_network hook' );
			$this->assertEquals( $domain, $this->action_args['before_populate_network']['domain'], 'Domain should match in before_populate_network hook' );
			$this->assertEquals( $network_id, $this->action_args['after_populate_network']['network_id'], 'Network ID should match in after_populate_network hook' );
			$this->assertEquals( $domain, $this->action_args['after_populate_network']['domain'], 'Domain should match in after_populate_network hook' );

			remove_action( 'before_populate_network', array( $this, 'hook_action_counter' ), 10 );
			remove_action( 'after_populate_network', array( $this, 'hook_action_counter' ), 10 );

			global $wpdb;
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d", $network_id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->site} WHERE id = %d", $network_id ) );
		}

		/**
		 * Test that the hooks can modify parameters.
		 *
		 * @ticket 27289
		 */
		public function test_populate_network_hook_filter() {
			$this->hook_called = false;

			add_action( 'before_populate_network', array( $this, 'modify_domain_hook' ), 10, 6 );

			$domain     = 'example' . time() . '.org';
			$network_id = self::factory()->network->create(
				array(
					'domain' => $domain,
					'path'   => '/',
				)
			);

			$this->assertTrue( $this->hook_called, 'The modify_domain_hook action should have been called' );

			remove_action( 'before_populate_network', array( $this, 'modify_domain_hook' ), 10 );

			global $wpdb;
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d", $network_id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->site} WHERE id = %d", $network_id ) );
		}

		/**
		 * Action to track if hooks are being executed.
		 */
		public function modify_domain_hook( $network_id, $domain, $email, $site_name, $path, $subdomain_install ) {
			$this->hook_called = true;
		}
	}

endif;
