<?php
/**
 * Tests for Distributed Editing presence storage helpers.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_Presence_Storage extends WP_UnitTestCase {

	protected $table_name;

	public function set_up() {
		parent::set_up();

		$this->table_name = wp_de_rtc_get_presence_table_name();
		delete_option( 'wp_de_rtc_presence_schema_version' );
		$this->drop_presence_table();
	}

	public function tear_down() {
		$this->drop_presence_table();
		delete_option( 'wp_de_rtc_presence_schema_version' );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * @covers ::wp_de_rtc_get_presence_table_name
	 * @covers ::wp_de_rtc_get_presence_cleanup_batch_limit
	 * @covers ::wp_de_rtc_get_presence_storage_schema
	 */
	public function test_presence_storage_schema_is_privacy_safe_and_bounded() {
		global $wpdb;

		$schema = wp_de_rtc_get_presence_storage_schema(
			array(
				'host_profile' => 'cheap_shared_host',
			)
		);

		$this->assertSame( $wpdb->prefix . 'de_rtc_presence', $schema['table_name'] );
		$this->assertSame( 'de-rtc-presence-storage-v1', $schema['schema'] );
		$this->assertSame( 'dedicated_presence_table', $schema['storage_kind'] );
		$this->assertSame( 'presence_id', $schema['primary_key'] );
		$this->assertContains( 'session_key_hash', $schema['columns'] );
		$this->assertContains( 'actor_hash', $schema['columns'] );
		$this->assertContains( 'post_id_last_seen_gmt', $schema['indexes'] );
		$this->assertContains( 'session_key_hash', $schema['indexes'] );
		$this->assertContains( 'expires_at_gmt', $schema['indexes'] );
		$this->assertSame( 25, $schema['cleanup_batch_limit'] );
		$this->assertSame( 900, $schema['retention_seconds'] );
		$this->assertTrue( $schema['cleanup_bounded'] );
		$this->assertFalse( $schema['privacy_filters']['raw_content_included'] );
		$this->assertFalse( $schema['privacy_filters']['exposes_user_ids'] );
		$this->assertFalse( $schema['privacy_filters']['exposes_logins'] );
		$this->assertFalse( $schema['privacy_filters']['exposes_email'] );
		$this->assertFalse( $schema['privacy_filters']['exposes_cursor_offset'] );
		$this->assertFalse( $schema['privacy_filters']['exposes_selection'] );
		$this->assertFalse( $schema['heartbeat_writes_enabled_now'] );
		$this->assertFalse( $schema['records_presence_heartbeat_now'] );
		$this->assertFalse( $schema['runtime_polling_enabled_now'] );
		$this->assertTrue( $schema['repeated_refresh_optional'] );
		$this->assertFalse( $schema['transport_required_for_correctness'] );
		$this->assertFalse( $schema['table_exists_required_for_save_correctness'] );
		$this->assertFalse( $schema['calls_save'] );
		$this->assertFalse( $schema['mutates_post_content'] );
		$this->assertFalse( $schema['creates_revision'] );
		$this->assertFalse( $schema['changes_post_lock'] );
		$this->assertFalse( $schema['claims_absence'] );
		$this->assertFalse( $schema['claims_saved'] );
		$this->assertStringContainsString( 'CREATE TABLE ' . $schema['table_name'], $schema['create_sql'] );
		$this->assertStringNotContainsString( 'post_content', $schema['create_sql'] );
		$this->assertStringNotContainsString( 'user_id ', $schema['create_sql'] );
		$this->assertStringNotContainsString( 'user_email', $schema['create_sql'] );
		$this->assertStringNotContainsString( 'cursor', $schema['create_sql'] );
		$this->assertStringNotContainsString( 'selection', $schema['create_sql'] );
	}

	/**
	 * @covers ::wp_de_rtc_get_presence_schema_version
	 * @covers ::wp_de_rtc_get_presence_storage_schema
	 * @covers ::wp_de_rtc_presence_table_exists
	 * @covers ::wp_de_rtc_install_presence_table
	 */
	public function test_presence_storage_install_uses_dbdelta_and_is_idempotent() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC presence install post',
				'post_content' => '<!-- wp:paragraph --><p>Presence install.</p><!-- /wp:paragraph -->',
			)
		);
		$before_content   = get_post_field( 'post_content', $post_id );
		$before_revisions = wp_get_post_revisions( $post_id );

		$this->assertFalse( wp_de_rtc_presence_table_exists() );

		$install = wp_de_rtc_install_presence_table(
			array(
				'host_profile' => 'cheap_shared_host',
			)
		);

		$this->assertSame( 'presence_table_installed', $install['result'] );
		$this->assertSame( $this->table_name, $install['table_name'] );
		$this->assertSame( '1', $install['schema_version'] );
		$this->assertFalse( $install['table_exists_before'] );
		$this->assertTrue( $install['table_exists_after'] );
		$this->assertTrue( $install['uses_db_delta'] );
		$this->assertTrue( $install['option_updated'] );
		$this->assertTrue( $install['install_triggered_by_explicit_call'] );
		$this->assertFalse( $install['automatic_per_request_install'] );
		$this->assertFalse( $install['install_triggered_by_save'] );
		$this->assertFalse( $install['install_triggered_by_presence_read'] );
		$this->assertFalse( $install['install_triggered_by_cleanup'] );
		$this->assertFalse( $install['install_triggered_by_heartbeat_write'] );
		$this->assertFalse( $install['heartbeat_writes_enabled_now'] );
		$this->assertFalse( $install['runtime_polling_enabled_now'] );
		$this->assertTrue( $install['repeated_refresh_optional'] );
		$this->assertFalse( $install['transport_required_for_correctness'] );
		$this->assertFalse( $install['table_exists_required_for_save_correctness'] );
		$this->assertFalse( $install['calls_save'] );
		$this->assertFalse( $install['mutates_post_content'] );
		$this->assertFalse( $install['creates_revision'] );
		$this->assertFalse( $install['changes_post_lock'] );
		$this->assertFalse( $install['claims_absence'] );
		$this->assertFalse( $install['claims_saved'] );
		$this->assertTrue( wp_de_rtc_presence_table_exists() );
		$this->assertSame( '1', get_option( 'wp_de_rtc_presence_schema_version' ) );
		$this->assertSame( $before_content, get_post_field( 'post_content', $post_id ) );
		$this->assertSame( array_keys( $before_revisions ), array_keys( wp_get_post_revisions( $post_id ) ) );

		$second_install = wp_de_rtc_install_presence_table();

		$this->assertSame( 'presence_table_upgrade_checked', $second_install['result'] );
		$this->assertTrue( $second_install['table_exists_before'] );
		$this->assertTrue( $second_install['table_exists_after'] );
		$this->assertTrue( $second_install['uses_db_delta'] );
		$this->assertSame( '1', get_option( 'wp_de_rtc_presence_schema_version' ) );
	}

	/**
	 * @covers ::wp_de_rtc_get_presence_storage_schema
	 * @covers ::wp_de_rtc_presence_table_exists
	 * @covers ::wp_de_rtc_get_presence_storage_readiness
	 */
	public function test_presence_storage_readiness_reports_setup_required_without_installing_or_mutating() {
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC presence readiness post',
				'post_content' => '<!-- wp:paragraph --><p>Presence readiness.</p><!-- /wp:paragraph -->',
			)
		);
		$before_content   = get_post_field( 'post_content', $post_id );
		$before_revisions = wp_get_post_revisions( $post_id );

		$this->assertFalse( wp_de_rtc_presence_table_exists() );

		$readiness = wp_de_rtc_get_presence_storage_readiness(
			array(
				'feature_enabled' => true,
				'host_profile'    => 'cheap_shared_host',
			)
		);

		$this->assertSame( 'presence_storage_setup_required', $readiness['result'] );
		$this->assertSame( 'setup_required', $readiness['status'] );
		$this->assertSame( $this->table_name, $readiness['tableName'] );
		$this->assertFalse( $readiness['tableExists'] );
		$this->assertSame( '1', $readiness['schemaVersionExpected'] );
		$this->assertNull( $readiness['schemaVersionInstalled'] );
		$this->assertFalse( $readiness['schemaCurrent'] );
		$this->assertSame( 'degraded', $readiness['expectedStartupHeartbeatStatus'] );
		$this->assertTrue( $readiness['setupRequired'] );
		$this->assertTrue( $readiness['manualSetupRequired'] );
		$this->assertTrue( $readiness['canRetryAfterInstall'] );
		$this->assertSame( 'call_wp_de_rtc_install_presence_table', $readiness['setupAction'] );
		$this->assertSame( 'explicit_setup_only', $readiness['setupTrigger'] );
		$this->assertTrue( $readiness['diagnosticOnly'] );
		$this->assertTrue( $readiness['contentFree'] );
		$this->assertFalse( $readiness['installsPresenceTable'] );
		$this->assertFalse( $readiness['automaticPerRequestInstall'] );
		$this->assertFalse( $readiness['installTriggeredByPresenceRead'] );
		$this->assertFalse( $readiness['installTriggeredByHeartbeatWrite'] );
		$this->assertFalse( $readiness['writesPresence'] );
		$this->assertFalse( $readiness['recordsPresenceHeartbeat'] );
		$this->assertFalse( $readiness['startsPolling'] );
		$this->assertFalse( $readiness['callsSave'] );
		$this->assertFalse( $readiness['mutatesPostContent'] );
		$this->assertFalse( $readiness['createsRevision'] );
		$this->assertFalse( $readiness['changesPostLock'] );
		$this->assertFalse( $readiness['claimsAbsence'] );
		$this->assertFalse( $readiness['claimsSaved'] );
		$this->assertFalse( $readiness['exposesRawContent'] );
		$this->assertFalse( $readiness['exposesUserIds'] );
		$this->assertFalse( $readiness['exposesCursorOffset'] );
		$this->assertFalse( $readiness['exposesSelection'] );
		$this->assertTrue( $readiness['correctnessIndependentOfTransport'] );
		$this->assertFalse( $readiness['transportRequiredForCorrectness'] );
		$this->assertFalse( $readiness['tableExistsRequiredForSaveCorrectness'] );
		$this->assertFalse( wp_de_rtc_presence_table_exists() );
		$this->assertSame( $before_content, get_post_field( 'post_content', $post_id ) );
		$this->assertSame( array_keys( $before_revisions ), array_keys( wp_get_post_revisions( $post_id ) ) );
	}

	/**
	 * @covers ::wp_de_rtc_get_presence_storage_schema
	 * @covers ::wp_de_rtc_presence_table_exists
	 * @covers ::wp_de_rtc_install_presence_table
	 * @covers ::wp_de_rtc_get_presence_storage_readiness
	 */
	public function test_presence_storage_readiness_reports_ready_after_explicit_install() {
		wp_de_rtc_install_presence_table(
			array(
				'host_profile' => 'cheap_shared_host',
			)
		);

		$readiness = wp_de_rtc_get_presence_storage_readiness(
			array(
				'feature_enabled' => true,
				'host_profile'    => 'cheap_shared_host',
			)
		);

		$this->assertSame( 'presence_storage_ready', $readiness['result'] );
		$this->assertSame( 'ready', $readiness['status'] );
		$this->assertTrue( $readiness['tableExists'] );
		$this->assertSame( '1', $readiness['schemaVersionExpected'] );
		$this->assertSame( '1', $readiness['schemaVersionInstalled'] );
		$this->assertTrue( $readiness['schemaCurrent'] );
		$this->assertSame( 'sent', $readiness['expectedStartupHeartbeatStatus'] );
		$this->assertFalse( $readiness['setupRequired'] );
		$this->assertFalse( $readiness['manualSetupRequired'] );
		$this->assertFalse( $readiness['canRetryAfterInstall'] );
		$this->assertFalse( $readiness['installsPresenceTable'] );
		$this->assertFalse( $readiness['callsSave'] );
		$this->assertFalse( $readiness['changesPostLock'] );
		$this->assertTrue( $readiness['contentFree'] );
		$this->assertTrue( $readiness['correctnessIndependentOfTransport'] );
	}

	/**
	 * @covers ::wp_de_rtc_get_presence_storage_setup_capability
	 * @covers ::wp_de_rtc_get_presence_storage_setup_nonce_action
	 * @covers ::wp_de_rtc_get_presence_storage_setup_action_url
	 * @covers ::wp_de_rtc_get_presence_storage_setup_admin_action_state
	 */
	public function test_presence_storage_setup_admin_action_state_exposes_guarded_action_without_installing() {
		$admin_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_id );
		update_option( 'wp_de_rtc_enabled', true );

		$state = wp_de_rtc_get_presence_storage_setup_admin_action_state(
			array(
				'redirect_url' => admin_url( 'options-writing.php' ),
			)
		);

		$this->assertSame( 'presence_storage_setup_action_available', $state['result'] );
		$this->assertSame( 'available', $state['status'] );
		$this->assertSame( 'wp_de_rtc_setup_presence_storage', $state['actionName'] );
		$this->assertSame( 'admin_post_nonce_url', $state['method'] );
		$this->assertStringContainsString( 'admin-post.php', $state['url'] );
		$this->assertStringContainsString( 'action=wp_de_rtc_setup_presence_storage', $state['url'] );
		$this->assertStringContainsString( '_wpnonce=', $state['url'] );
		$this->assertSame( 'wp_de_rtc_setup_presence_storage', $state['nonceAction'] );
		$this->assertTrue( $state['requiresNonce'] );
		$this->assertTrue( $state['requiresCapability'] );
		$this->assertSame( 'manage_options', $state['requiredCapability'] );
		$this->assertTrue( $state['currentUserCanSetup'] );
		$this->assertTrue( $state['setupRequired'] );
		$this->assertTrue( $state['contentFree'] );
		$this->assertFalse( $state['installsPresenceTable'] );
		$this->assertFalse( $state['automaticPerRequestInstall'] );
		$this->assertFalse( $state['installTriggeredByEditorLoad'] );
		$this->assertFalse( $state['installTriggeredBySave'] );
		$this->assertFalse( $state['installTriggeredByPresenceRead'] );
		$this->assertFalse( $state['installTriggeredByHeartbeatWrite'] );
		$this->assertFalse( $state['callsSave'] );
		$this->assertFalse( $state['mutatesPostContent'] );
		$this->assertFalse( $state['createsRevision'] );
		$this->assertFalse( $state['changesPostLock'] );
		$this->assertFalse( $state['claimsAbsence'] );
		$this->assertFalse( $state['claimsSaved'] );
		$this->assertFalse( $state['exposesRawContent'] );
		$this->assertFalse( $state['exposesUserIds'] );
		$this->assertTrue( $state['correctnessIndependentOfTransport'] );
		$this->assertFalse( $state['transportRequiredForCorrectness'] );
		$this->assertFalse( wp_de_rtc_presence_table_exists() );
	}

	/**
	 * @covers ::wp_de_rtc_get_presence_storage_setup_admin_action_state
	 * @covers ::wp_de_rtc_run_presence_storage_setup_admin_action
	 */
	public function test_presence_storage_setup_admin_action_requires_nonce_and_capability_before_install() {
		$subscriber_id = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		update_option( 'wp_de_rtc_enabled', true );

		wp_set_current_user( $subscriber_id );

		$missing_nonce = wp_de_rtc_run_presence_storage_setup_admin_action(
			array(
				'nonce_verified' => false,
			)
		);

		$this->assertWPError( $missing_nonce );
		$this->assertSame( 'de_rtc_presence_storage_setup_nonce_required', $missing_nonce->get_error_code() );
		$this->assertFalse( wp_de_rtc_presence_table_exists() );

		$forbidden = wp_de_rtc_run_presence_storage_setup_admin_action(
			array(
				'nonce_verified' => true,
			)
		);

		$this->assertWPError( $forbidden );
		$this->assertSame( 'de_rtc_presence_storage_setup_forbidden', $forbidden->get_error_code() );
		$this->assertFalse( wp_de_rtc_presence_table_exists() );
	}

	/**
	 * @covers ::wp_de_rtc_get_presence_storage_setup_admin_action_state
	 * @covers ::wp_de_rtc_run_presence_storage_setup_admin_action
	 * @covers ::wp_de_rtc_install_presence_table
	 * @covers ::wp_de_rtc_get_presence_storage_readiness
	 */
	public function test_presence_storage_setup_admin_action_installs_after_nonce_and_capability_without_save_side_effects() {
		$admin_id         = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC admin presence setup post',
				'post_content' => '<!-- wp:paragraph --><p>Admin setup.</p><!-- /wp:paragraph -->',
			)
		);
		$before_content   = get_post_field( 'post_content', $post_id );
		$before_revisions = wp_get_post_revisions( $post_id );

		wp_set_current_user( $admin_id );
		update_option( 'wp_de_rtc_enabled', true );

		$result = wp_de_rtc_run_presence_storage_setup_admin_action(
			array(
				'nonce_verified' => true,
				'host_profile'    => 'cheap_shared_host',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'presence_storage_setup_completed', $result['result'] );
		$this->assertSame( 'wp_de_rtc_setup_presence_storage', $result['actionName'] );
		$this->assertTrue( $result['nonceVerified'] );
		$this->assertTrue( $result['requiresNonce'] );
		$this->assertTrue( $result['requiresCapability'] );
		$this->assertSame( 'manage_options', $result['requiredCapability'] );
		$this->assertSame( 'setup_required', $result['beforeStatus'] );
		$this->assertSame( 'ready', $result['afterStatus'] );
		$this->assertFalse( $result['tableExistsBefore'] );
		$this->assertTrue( $result['tableExistsAfter'] );
		$this->assertTrue( $result['schemaCurrentAfter'] );
		$this->assertSame( 'presence_table_installed', $result['installResult'] );
		$this->assertTrue( $result['usesDbDelta'] );
		$this->assertTrue( $result['installTriggeredByExplicitAdminAction'] );
		$this->assertFalse( $result['automaticPerRequestInstall'] );
		$this->assertFalse( $result['installTriggeredByEditorLoad'] );
		$this->assertFalse( $result['installTriggeredBySave'] );
		$this->assertFalse( $result['installTriggeredByPresenceRead'] );
		$this->assertFalse( $result['installTriggeredByHeartbeatWrite'] );
		$this->assertFalse( $result['writesPresence'] );
		$this->assertFalse( $result['startsPolling'] );
		$this->assertFalse( $result['callsSave'] );
		$this->assertFalse( $result['mutatesPostContent'] );
		$this->assertFalse( $result['createsRevision'] );
		$this->assertFalse( $result['changesPostLock'] );
		$this->assertFalse( $result['claimsAbsence'] );
		$this->assertFalse( $result['claimsSaved'] );
		$this->assertFalse( $result['exposesRawContent'] );
		$this->assertFalse( $result['exposesUserIds'] );
		$this->assertTrue( $result['correctnessIndependentOfTransport'] );
		$this->assertFalse( $result['transportRequiredForCorrectness'] );
		$this->assertTrue( wp_de_rtc_presence_table_exists() );
		$this->assertSame( '1', get_option( 'wp_de_rtc_presence_schema_version' ) );
		$this->assertSame( $before_content, get_post_field( 'post_content', $post_id ) );
		$this->assertSame( array_keys( $before_revisions ), array_keys( wp_get_post_revisions( $post_id ) ) );
	}

	/**
	 * @covers ::wp_de_rtc_get_presence_storage_schema
	 * @covers ::wp_de_rtc_presence_table_exists
	 * @covers ::wp_de_rtc_install_presence_table
	 * @covers ::wp_de_rtc_cleanup_presence_records
	 */
	public function test_presence_cleanup_is_bounded_and_deletes_only_expired_rows() {
		global $wpdb;

		$this->create_presence_table();

		$this->insert_presence_row( 101, 'old-a', '2026-05-15 11:00:00' );
		$this->insert_presence_row( 102, 'old-b', '2026-05-15 11:30:00' );
		$this->insert_presence_row( 103, 'fresh', '2026-05-15 13:00:00' );

		$cleanup = wp_de_rtc_cleanup_presence_records(
			array(
				'batch_limit'      => 1,
				'current_time_gmt' => '2026-05-15 12:00:00',
			)
		);

		$this->assertSame( 'presence_cleanup_completed', $cleanup['result'] );
		$this->assertSame( 1, $cleanup['batch_limit'] );
		$this->assertSame( 1, $cleanup['deleted_count'] );
		$this->assertTrue( $cleanup['bounded'] );
		$this->assertFalse( $cleanup['records_presence_heartbeat'] );
		$this->assertFalse( $cleanup['heartbeat_writes_enabled_now'] );
		$this->assertFalse( $cleanup['runtime_polling_enabled_now'] );
		$this->assertTrue( $cleanup['repeated_refresh_optional'] );
		$this->assertFalse( $cleanup['transport_required_for_correctness'] );
		$this->assertFalse( $cleanup['calls_save'] );
		$this->assertFalse( $cleanup['mutates_post_content'] );
		$this->assertFalse( $cleanup['creates_revision'] );
		$this->assertFalse( $cleanup['changes_post_lock'] );
		$this->assertFalse( $cleanup['claims_absence'] );
		$this->assertFalse( $cleanup['claims_saved'] );
		$this->assertSame( 2, $this->get_presence_row_count() );
		$this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table_sql()} WHERE session_key_hash = 'old-b'" ) );
		$this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table_sql()} WHERE session_key_hash = 'fresh'" ) );

		$cleanup = wp_de_rtc_cleanup_presence_records(
			array(
				'batch_limit'      => 25,
				'current_time_gmt' => '2026-05-15 12:00:00',
			)
		);

		$this->assertSame( 1, $cleanup['deleted_count'] );
		$this->assertSame( 1, $this->get_presence_row_count() );
		$this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table_sql()} WHERE session_key_hash = 'fresh'" ) );
	}

	/**
	 * @covers ::wp_de_rtc_presence_table_exists
	 * @covers ::wp_de_rtc_cleanup_presence_records
	 */
	public function test_presence_cleanup_missing_table_is_noop() {
		$cleanup = wp_de_rtc_cleanup_presence_records(
			array(
				'batch_limit'      => 25,
				'current_time_gmt' => '2026-05-15 12:00:00',
			)
		);

		$this->assertSame( 'presence_table_missing', $cleanup['result'] );
		$this->assertSame( 0, $cleanup['deleted_count'] );
		$this->assertSame( 25, $cleanup['batch_limit'] );
		$this->assertTrue( $cleanup['bounded'] );
		$this->assertFalse( $cleanup['records_presence_heartbeat'] );
		$this->assertFalse( $cleanup['calls_save'] );
		$this->assertFalse( $cleanup['mutates_post_content'] );
		$this->assertFalse( $cleanup['creates_revision'] );
		$this->assertFalse( $cleanup['changes_post_lock'] );
		$this->assertFalse( $cleanup['claims_absence'] );
		$this->assertFalse( $cleanup['claims_saved'] );
	}

	protected function create_presence_table() {
		$install = wp_de_rtc_install_presence_table();

		$this->assertTrue( $install['table_exists_after'] );
	}

	protected function insert_presence_row( $post_id, $session_key_hash, $expires_at_gmt ) {
		global $wpdb;

		$wpdb->insert(
			$this->table_name,
			array(
				'post_id'          => $post_id,
				'session_key_hash' => $session_key_hash,
				'actor_hash'       => hash( 'sha256', $session_key_hash ),
				'display_name'     => 'Presence Test',
				'freshness'        => 'active',
				'last_seen_gmt'    => '2026-05-15 11:45:00',
				'expires_at_gmt'   => $expires_at_gmt,
				'created_at_gmt'   => '2026-05-15 11:00:00',
				'updated_at_gmt'   => '2026-05-15 11:45:00',
			)
		);
	}

	protected function get_presence_row_count() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table_sql()}" );
	}

	protected function get_table_sql() {
		return '`' . str_replace( '`', '``', $this->table_name ) . '`';
	}

	protected function drop_presence_table() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$this->get_table_sql()}" );
	}
}
