<?php
/**
 * Tests for Distributed Editing settings.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_Settings extends WP_UnitTestCase {

	public function tear_down() {
		global $wp_registered_settings, $wp_settings_fields;

		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_false' );
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );

		delete_option( 'wp_de_rtc_enabled' );
		delete_option( 'wp_de_rtc_presence_schema_version' );
		unregister_setting( 'writing', 'wp_de_rtc_enabled' );
		unset( $wp_registered_settings['wp_de_rtc_enabled'] );
		wp_set_current_user( 0 );

		if ( isset( $wp_settings_fields['writing']['default']['wp_de_rtc_enabled'] ) ) {
			unset( $wp_settings_fields['writing']['default']['wp_de_rtc_enabled'] );
		}

		parent::tear_down();
	}

	/**
	 * @covers ::wp_de_rtc_register_settings
	 * @covers ::wp_de_rtc_sanitize_enabled_setting
	 */
	public function test_registers_disabled_by_default_writing_setting() {
		global $wp_registered_settings;

		wp_de_rtc_register_settings();

		$this->assertArrayHasKey( 'wp_de_rtc_enabled', $wp_registered_settings );
		$this->assertSame( 'writing', $wp_registered_settings['wp_de_rtc_enabled']['group'] );
		$this->assertSame( 'boolean', $wp_registered_settings['wp_de_rtc_enabled']['type'] );
		$this->assertFalse( $wp_registered_settings['wp_de_rtc_enabled']['show_in_rest'] );
		$this->assertFalse( get_option( 'wp_de_rtc_enabled' ) );
	}

	/**
	 * @covers ::wp_de_rtc_is_enabled
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_site_option_controls_post_enablement() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC settings post',
				'post_content' => '<!-- wp:paragraph --><p>Settings.</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertFalse( wp_de_rtc_is_enabled() );
		$this->assertFalse( wp_de_rtc_is_enabled_for_post( $post_id ) );

		update_option( 'wp_de_rtc_enabled', true );

		$this->assertTrue( wp_de_rtc_is_enabled() );
		$this->assertTrue( wp_de_rtc_is_enabled_for_post( $post_id ) );

		update_option( 'wp_de_rtc_enabled', false );

		$this->assertFalse( wp_de_rtc_is_enabled() );
		$this->assertFalse( wp_de_rtc_is_enabled_for_post( $post_id ) );
	}

	/**
	 * @covers ::wp_de_rtc_is_enabled
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_site_option_remains_authoritative_before_post_filter() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled site filter post',
				'post_content' => '<!-- wp:paragraph --><p>Disabled site filter.</p><!-- /wp:paragraph -->',
			)
		);

		update_option( 'wp_de_rtc_enabled', false );
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );

		$this->assertFalse( wp_de_rtc_is_enabled() );
		$this->assertFalse( wp_de_rtc_is_enabled_for_post( $post_id ) );
	}

	/**
	 * @covers ::wp_de_rtc_is_enabled
	 * @covers ::wp_de_rtc_is_enabled_for_post
	 */
	public function test_post_filter_can_disable_enabled_site_option() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC enabled site disabled post filter',
				'post_content' => '<!-- wp:paragraph --><p>Enabled site disabled post filter.</p><!-- /wp:paragraph -->',
			)
		);

		update_option( 'wp_de_rtc_enabled', true );
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_false' );

		$this->assertTrue( wp_de_rtc_is_enabled() );
		$this->assertFalse( wp_de_rtc_is_enabled_for_post( $post_id ) );
	}

	/**
	 * @covers ::wp_de_rtc_register_settings
	 * @covers ::wp_de_rtc_render_enabled_setting
	 */
	public function test_registers_writing_settings_field_with_guidance() {
		global $wp_settings_fields;

		if ( ! function_exists( 'add_settings_field' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}

		wp_de_rtc_register_settings();

		$this->assertArrayHasKey( 'wp_de_rtc_enabled', $wp_settings_fields['writing']['default'] );

		ob_start();
		call_user_func( $wp_settings_fields['writing']['default']['wp_de_rtc_enabled']['callback'] );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="wp_de_rtc_enabled"', $output );
		$this->assertStringContainsString( 'Distributed Editing is experimental', $output );
		$this->assertStringContainsString( 'constrained hosting', $output );
	}

	/**
	 * @covers ::wp_de_rtc_render_enabled_setting
	 * @covers ::wp_de_rtc_get_presence_storage_setup_admin_action_state
	 * @covers ::wp_de_rtc_get_presence_storage_setup_action_url
	 */
	public function test_writing_settings_field_exposes_guarded_presence_storage_setup_action() {
		global $wpdb;

		$admin_id   = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$table_name = wp_de_rtc_get_presence_table_name();
		$table_sql  = '`' . str_replace( '`', '``', $table_name ) . '`';

		$wpdb->query( "DROP TABLE IF EXISTS $table_sql" );
		delete_option( 'wp_de_rtc_presence_schema_version' );
		update_option( 'wp_de_rtc_enabled', true );
		wp_set_current_user( $admin_id );

		ob_start();
		wp_de_rtc_render_enabled_setting();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-wp-de-rtc-presence-storage-status="setup_required"', $output );
		$this->assertStringContainsString( 'Presence storage: Setup needed.', $output );
		$this->assertStringContainsString( 'Automatic presence updates will degrade until setup is run deliberately.', $output );
		$this->assertStringContainsString( 'data-wp-de-rtc-presence-storage-setup-action="wp_de_rtc_setup_presence_storage"', $output );
		$this->assertStringContainsString( 'data-wp-de-rtc-presence-storage-setup-requires-nonce="true"', $output );
		$this->assertStringContainsString( 'data-wp-de-rtc-presence-storage-setup-requires-capability="manage_options"', $output );
		$this->assertStringContainsString( 'admin-post.php', $output );
		$this->assertStringContainsString( 'action=wp_de_rtc_setup_presence_storage', $output );
		$this->assertStringContainsString( '_wpnonce=', $output );
		$this->assertStringContainsString( 'Set up Distributed Editing presence storage', $output );
		$this->assertFalse( wp_de_rtc_presence_table_exists() );
	}

	/**
	 * @covers ::wp_de_rtc_add_block_editor_settings
	 */
	public function test_block_editor_settings_expose_disabled_post_gate_by_default() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled editor settings post',
				'post_content' => '<!-- wp:paragraph --><p>Disabled.</p><!-- /wp:paragraph -->',
			)
		);
		$context = new WP_Block_Editor_Context(
			array(
				'post' => get_post( $post_id ),
			)
		);

		$settings = wp_de_rtc_add_block_editor_settings( array(), $context );

		$this->assertSame(
			array(
				'enabled'                  => false,
				'retrySaveHandoff'         => false,
				'initialPresenceRoster'    => wp_de_rtc_get_empty_post_presence_roster(),
				'presenceStorageReadiness' => wp_de_rtc_get_presence_storage_readiness(
					array(
						'feature_enabled' => false,
					)
				),
			),
			$settings['distributedEditing']
		);
	}

	/**
	 * @covers ::wp_de_rtc_add_block_editor_settings
	 * @covers ::wp_de_rtc_get_empty_post_presence_roster
	 * @covers ::wp_de_rtc_get_post_presence_roster
	 */
	public function test_block_editor_settings_expose_enabled_post_gate_after_opt_in() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC enabled editor settings post',
				'post_content' => '<!-- wp:paragraph --><p>Enabled.</p><!-- /wp:paragraph -->',
			)
		);
		$context = new WP_Block_Editor_Context(
			array(
				'post' => get_post( $post_id ),
			)
		);

		update_option( 'wp_de_rtc_enabled', true );

		$settings = wp_de_rtc_add_block_editor_settings( array(), $context );

		$this->assertSame(
			array(
				'enabled'                  => true,
				'retrySaveHandoff'         => true,
				'initialPresenceRoster'    => wp_de_rtc_get_empty_post_presence_roster(),
				'presenceStorageReadiness' => wp_de_rtc_get_presence_storage_readiness(
					array(
						'feature_enabled' => true,
					)
				),
			),
			$settings['distributedEditing']
		);

		$this->assertSame( 'setup_required', $settings['distributedEditing']['presenceStorageReadiness']['status'] );
		$this->assertSame( 'degraded', $settings['distributedEditing']['presenceStorageReadiness']['expectedStartupHeartbeatStatus'] );
		$this->assertFalse( $settings['distributedEditing']['presenceStorageReadiness']['installsPresenceTable'] );
		$this->assertFalse( $settings['distributedEditing']['presenceStorageReadiness']['callsSave'] );
	}

	/**
	 * @covers ::wp_de_rtc_add_block_editor_settings
	 * @covers ::wp_de_rtc_get_post_presence_roster
	 * @covers ::wp_de_rtc_get_post_lock_presence_entry
	 */
	public function test_block_editor_settings_expose_other_editor_presence_from_post_lock() {
		$other_user_id = self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Mira Presence',
			)
		);
		$post_id       = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC presence editor settings post',
				'post_content' => '<!-- wp:paragraph --><p>Presence.</p><!-- /wp:paragraph -->',
			)
		);
		$context       = new WP_Block_Editor_Context(
			array(
				'post' => get_post( $post_id ),
			)
		);

		update_option( 'wp_de_rtc_enabled', true );
		update_post_meta( $post_id, '_edit_lock', time() . ':' . $other_user_id );

		$settings = wp_de_rtc_add_block_editor_settings( array(), $context );
		$roster   = $settings['distributedEditing']['initialPresenceRoster'];

		$this->assertTrue( $settings['distributedEditing']['enabled'] );
		$this->assertSame( 'recent', $roster['status'] );
		$this->assertSame( 'recent', $roster['freshness'] );
		$this->assertSame( 1, $roster['visibleCount'] );
		$this->assertSame( 1, $roster['totalKnownCount'] );
		$this->assertFalse( $roster['claimsAbsence'] );
		$this->assertFalse( $roster['callsSave'] );
		$this->assertFalse( $roster['changesPostLock'] );
		$this->assertFalse( $roster['claimsSaved'] );
		$this->assertCount( 1, $roster['entries'] );
		$this->assertSame( 'Mira Presence', $roster['entries'][0]['displayName'] );
		$this->assertSame( 'other_user', $roster['entries'][0]['relationship'] );
		$this->assertSame( 'recent', $roster['entries'][0]['freshness'] );
		$this->assertFalse( $roster['entries'][0]['exposesUserId'] );
		$this->assertArrayNotHasKey( 'userId', $roster['entries'][0] );
	}

	/**
	 * @covers ::wp_de_rtc_add_block_editor_settings
	 */
	public function test_block_editor_settings_keep_non_post_editor_gate_disabled() {
		$context = new WP_Block_Editor_Context(
			array(
				'name' => 'core/edit-widgets',
			)
		);

		update_option( 'wp_de_rtc_enabled', true );

		$settings = wp_de_rtc_add_block_editor_settings( array(), $context );

		$this->assertSame(
			array(
				'enabled'                  => false,
				'retrySaveHandoff'         => false,
				'initialPresenceRoster'    => wp_de_rtc_get_empty_post_presence_roster(),
				'presenceStorageReadiness' => wp_de_rtc_get_presence_storage_readiness(
					array(
						'feature_enabled' => false,
					)
				),
			),
			$settings['distributedEditing']
		);
	}
}
