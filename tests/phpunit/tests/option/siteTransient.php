<?php

/**
 * @group option
 */
class Tests_Option_SiteTransient extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}
	}

	/**
	 * @covers ::get_site_transient
	 * @covers ::set_site_transient
	 * @covers ::delete_site_transient
	 */
	public function test_the_basics() {
		$key    = 'key1';
		$value  = 'value1';
		$value2 = 'value2';

		$this->assertFalse( get_site_transient( 'doesnotexist' ) );
		$this->assertTrue( set_site_transient( $key, $value ) );
		$this->assertSame( $value, get_site_transient( $key ) );
		$this->assertFalse( set_site_transient( $key, $value ) );
		$this->assertTrue( set_site_transient( $key, $value2 ) );
		$this->assertSame( $value2, get_site_transient( $key ) );
		$this->assertTrue( delete_site_transient( $key ) );
		$this->assertFalse( get_site_transient( $key ) );
		$this->assertFalse( delete_site_transient( $key ) );
	}

	/**
	 * @covers ::get_site_transient
	 * @covers ::set_site_transient
	 * @covers ::delete_site_transient
	 */
	public function test_serialized_data() {
		$key   = __FUNCTION__;
		$value = array(
			'foo' => true,
			'bar' => true,
		);

		$this->assertTrue( set_site_transient( $key, $value ) );
		$this->assertSame( $value, get_site_transient( $key ) );

		$value = (object) $value;
		$this->assertTrue( set_site_transient( $key, $value ) );
		$this->assertEquals( $value, get_site_transient( $key ) );
		$this->assertTrue( delete_site_transient( $key ) );
	}

	/**
	 * @ticket 22846
	 * @group ms-excluded
	 *
	 * @covers ::set_site_transient
	 * @covers ::wp_load_alloptions
	 */
	public function test_set_site_transient_is_not_stored_as_autoload_option() {
		$key = 'not_autoloaded';

		set_site_transient( $key, 'Not an autoload option' );

		$options = wp_load_alloptions();

		$this->assertArrayNotHasKey( '_site_transient_' . $key, $options );
	}

	/**
	 * Ensure site transients are stored in the options table on single site installations.
	 *
	 * @group ms-excluded
	 *
	 * @covers ::set_site_transient
	 */
	public function test_site_transient_stored_in_options_on_single_site() {
		global $wpdb;
		$key   = 'test_site_transient_stored_in_options_on_single_site';
		$value = 'Test Site Transient Value';

		set_site_transient( $key, $value );

		$option = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_name, option_value from {$wpdb->options} WHERE option_name = %s",
				'_site_transient_' . $key
			)
		);
		$this->assertEquals(
			(object) array(
				'option_name'  => '_site_transient_' . $key,
				'option_value' => $value,
			),
			$option,
			'Site transient should be stored in the options table on single site installations.'
		);
	}

	/**
	 * Ensure site transients are stored in the sitemeta table on multisite.
	 *
	 * @group ms-required
	 *
	 * @covers ::set_site_transient
	 */
	public function test_site_transients_stored_in_site_meta_on_ms() {
		global $wpdb;
		$key   = 'test_site_transient_stored_in_site_meta_on_ms';
		$value = 'Test Site Transient Value';

		set_site_transient( $key, $value );

		$option = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta_key, meta_value from {$wpdb->sitemeta} WHERE meta_key = %s",
				'_site_transient_' . $key
			)
		);
		$this->assertEquals(
			(object) array(
				'meta_key'   => '_site_transient_' . $key,
				'meta_value' => $value,
			),
			$option,
			'Site transient should be stored in sitemeta table on multisite.'
		);
	}

	/**
	 * Ensure site transients are not stored in the options table on multisite.
	 *
	 * @group ms-required
	 *
	 * @covers ::set_site_transient
	 */
	public function test_site_transients_not_stored_in_options_table_on_ms() {
		global $wpdb;
		$key   = 'test_site_transients_not_stored_in_options_table_on_ms';
		$value = 'Test Site Transient Value';

		set_site_transient( $key, $value );

		$option = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_name, option_value from {$wpdb->options} WHERE option_name = %s",
				'_site_transient_' . $key
			)
		);

		$this->assertNull( $option, 'Querying option table should not return transient on multisite.' );
	}

	/**
	 * Tests that delete_site_transient() removes an orphaned timeout row.
	 *
	 * @ticket 65863
	 */
	public function test_delete_site_transient_removes_orphaned_timeout() {
		$key = 'orphaned_timeout';

		set_site_transient( $key, 'value', 3600 );

		// Remove the value row, leaving only the timeout row.
		delete_site_option( '_site_transient_' . $key );

		$this->assertFalse( get_site_option( '_site_transient_' . $key ) );
		$this->assertNotFalse( get_site_option( '_site_transient_timeout_' . $key ) );

		delete_site_transient( $key );

		$this->assertFalse( get_site_option( '_site_transient_' . $key ) );
		$this->assertFalse( get_site_option( '_site_transient_timeout_' . $key ) );
	}

	/**
	 * Tests that delete_expired_transients() removes expired orphaned site transient timeouts on single site.
	 *
	 * @ticket 65863
	 * @group ms-excluded
	 *
	 * @covers ::delete_expired_transients
	 */
	public function test_delete_expired_site_transients_orphaned_timeouts_single_site() {
		global $wpdb;

		$now = time();

		// Expired orphaned timeout (should be deleted).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				'_site_transient_timeout_expired_orphan',
				$now - 100
			)
		);

		// Unexpired orphaned timeout (should be kept).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				'_site_transient_timeout_unexpired_orphan',
				$now + 100
			)
		);

		// Expired pair (should be deleted).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				'_site_transient_expired_pair',
				'value'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				'_site_transient_timeout_expired_pair',
				$now - 100
			)
		);

		// Unexpired pair (should be kept).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				'_site_transient_unexpired_pair',
				'value'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				'_site_transient_timeout_unexpired_pair',
				$now + 100
			)
		);

		delete_expired_transients( true );

		$this->assertNull(
			$wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = '_site_transient_timeout_expired_orphan'" ),
			'Expired orphan site transient timeout should be deleted.'
		);
		$this->assertNotNull(
			$wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = '_site_transient_timeout_unexpired_orphan'" ),
			'Unexpired orphan site transient timeout should be retained.'
		);

		$this->assertNull(
			$wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = '_site_transient_expired_pair'" ),
			'Expired site transient value should be deleted.'
		);
		$this->assertNull(
			$wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = '_site_transient_timeout_expired_pair'" ),
			'Expired site transient timeout should be deleted.'
		);

		$this->assertSame(
			'value',
			$wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = '_site_transient_unexpired_pair'" ),
			'Unexpired site transient value should be retained.'
		);
		$this->assertNotNull(
			$wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = '_site_transient_timeout_unexpired_pair'" ),
			'Unexpired site transient timeout should be retained.'
		);
	}

	/**
	 * Tests that delete_expired_transients() removes expired orphaned site transient timeouts on multisite.
	 *
	 * @ticket 65863
	 * @group ms-required
	 *
	 * @covers ::delete_expired_transients
	 */
	public function test_delete_expired_site_transients_orphaned_timeouts_multisite() {
		global $wpdb;

		$now     = time();
		$site_id = get_current_network_id();

		// Expired orphaned timeout (should be deleted).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$site_id,
				'_site_transient_timeout_expired_orphan',
				$now - 100
			)
		);

		// Unexpired orphaned timeout (should be kept).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$site_id,
				'_site_transient_timeout_unexpired_orphan',
				$now + 100
			)
		);

		// Expired pair (should be deleted).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$site_id,
				'_site_transient_expired_pair',
				'value'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$site_id,
				'_site_transient_timeout_expired_pair',
				$now - 100
			)
		);

		// Unexpired pair (should be kept).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$site_id,
				'_site_transient_unexpired_pair',
				'value'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$site_id,
				'_site_transient_timeout_unexpired_pair',
				$now + 100
			)
		);

		delete_expired_transients( true );

		$this->assertNull(
			$wpdb->get_var( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = '_site_transient_timeout_expired_orphan'" ),
			'Expired orphan site transient timeout should be deleted in multisite.'
		);
		$this->assertNotNull(
			$wpdb->get_var( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = '_site_transient_timeout_unexpired_orphan'" ),
			'Unexpired orphan site transient timeout should be retained in multisite.'
		);

		$this->assertNull(
			$wpdb->get_var( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = '_site_transient_expired_pair'" ),
			'Expired site transient value should be deleted in multisite.'
		);
		$this->assertNull(
			$wpdb->get_var( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = '_site_transient_timeout_expired_pair'" ),
			'Expired site transient timeout should be deleted in multisite.'
		);

		$this->assertSame(
			'value',
			$wpdb->get_var( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = '_site_transient_unexpired_pair'" ),
			'Unexpired site transient value should be retained in multisite.'
		);
		$this->assertNotNull(
			$wpdb->get_var( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = '_site_transient_timeout_unexpired_pair'" ),
			'Unexpired site transient timeout should be retained in multisite.'
		);
	}

	/**
	 * Tests that delete_expired_transients() does not delete transient rows belonging to other networks.
	 *
	 * @ticket 65863
	 * @ticket 65969
	 * @group ms-required
	 *
	 * @covers ::delete_expired_transients
	 */
	public function test_delete_expired_site_transients_does_not_affect_other_networks() {
		global $wpdb;

		$now       = time();
		$network_1 = get_current_network_id();
		$network_2 = self::factory()->network->create();

		// Network 1: Unexpired Valid Pair (should be kept).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$network_1,
				'_site_transient_crossnet',
				'value'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$network_1,
				'_site_transient_timeout_crossnet',
				$now + 3600
			)
		);

		// Network 2: Expired Orphaned Timeout (should be deleted).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$network_2,
				'_site_transient_timeout_crossnet',
				$now - 100
			)
		);

		delete_expired_transients( true );

		// Network 2's expired orphan timeout should be deleted.
		$this->assertNull(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key = '_site_transient_timeout_crossnet'",
					$network_2
				)
			),
			"Network 2's expired orphan timeout should be deleted."
		);

		// Network 1's valid pair must remain completely untouched!
		$this->assertSame(
			'value',
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key = '_site_transient_crossnet'",
					$network_1
				)
			),
			"Network 1's valid transient value should be retained."
		);
		$this->assertNotNull(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key = '_site_transient_timeout_crossnet'",
					$network_1
				)
			),
			"Network 1's unexpired transient timeout should be retained."
		);
	}
}
