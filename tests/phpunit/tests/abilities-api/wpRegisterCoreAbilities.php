<?php

declare( strict_types=1 );

/**
 * Tests for the core abilities shipped with the Abilities API.
 *
 * @covers wp_register_core_ability_categories
 * @covers wp_register_core_abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreAbilities extends WP_UnitTestCase {

	/**
	 * Set up before the class.
	 *
	 * @since 6.9.0
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// Ensure core abilities are registered for these tests.
		// Temporarily remove the unhook functions so we can register core abilities.
		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		// Add the core registration hooks and fire the actions.
		add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
	}

	/**
	 * Tear down after the class.
	 *
	 * @since 6.9.0
	 */
	public static function tear_down_after_class(): void {
		// Re-add the unhook functions for subsequent tests.
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		// Remove the core abilities and their categories.
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		parent::tear_down_after_class();
	}

	/**
	 * Tests that the `core/get-site-info` ability is registered with the expected schema.
	 * @ticket 64146
	 */
	public function test_core_get_site_info_ability_is_registered(): void {
		$ability = wp_get_ability( 'core/get-site-info' );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ) );

		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();

		$this->assertSame( 'object', $input_schema['type'] );
		$this->assertArrayHasKey( 'default', $input_schema );
		$this->assertSame( array(), $input_schema['default'] );

		// Input schema should have optional fields array.
		$this->assertArrayHasKey( 'fields', $input_schema['properties'] );
		$this->assertSame( 'array', $input_schema['properties']['fields']['type'] );
		$this->assertContains( 'name', $input_schema['properties']['fields']['items']['enum'] );

		// Output schema should have all fields documented.
		$this->assertArrayHasKey( 'name', $output_schema['properties'] );
		$this->assertArrayHasKey( 'url', $output_schema['properties'] );
		$this->assertArrayHasKey( 'version', $output_schema['properties'] );
	}

	/**
	 * Tests executing the `core/get-site-info` ability returns all fields by default.
	 * @ticket 64146
	 */
	public function test_core_get_site_info_executes(): void {
		// Requires manage_options.
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( 'core/get-site-info' );

		// Test without fields parameter - should return all fields.
		$result = $ability->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'version', $result );
		$this->assertSame( get_bloginfo( 'name' ), $result['name'] );

		// Test with fields parameter - should return only requested fields.
		$result = $ability->execute(
			array(
				'fields' => array( 'name', 'url' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertSame( get_bloginfo( 'name' ), $result['name'] );
		$this->assertSame( get_bloginfo( 'url' ), $result['url'] );
	}

	/**
	 * Tests that executing the current user info ability requires authentication.
	 * @ticket 64146
	 */
	public function test_core_get_current_user_info_requires_authentication(): void {
		$ability = wp_get_ability( 'core/get-user-info' );

		$this->assertFalse( $ability->check_permissions() );

		$result = $ability->execute();
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Tests executing the current user info ability as an authenticated user.
	 * @ticket 64146
	 */
	public function test_core_get_current_user_info_returns_user_data(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'   => 'subscriber',
				'locale' => 'fr_FR',
			)
		);

		wp_set_current_user( $user_id );

		$ability = wp_get_ability( 'core/get-user-info' );

		$this->assertTrue( $ability->check_permissions() );

		$result = $ability->execute();
		$this->assertSame( $user_id, $result['id'] );
		$this->assertSame( 'fr_FR', $result['locale'] );
		$this->assertSame( 'subscriber', $result['roles'][0] );
		$this->assertSame( get_userdata( $user_id )->display_name, $result['display_name'] );
	}

	/**
	 * Tests executing the environment info ability.
	 *
	 * @ticket 64146
	 * @ticket 65232
	 */
	public function test_core_get_environment_info_executes(): void {
		// Requires manage_options.
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability     = wp_get_ability( 'core/get-environment-info' );
		$environment = wp_get_environment_type();

		$ability_data = $ability->execute();

		$this->assertIsArray( $ability_data );
		$this->assertArrayHasKey( 'environment', $ability_data );
		$this->assertArrayHasKey( 'php_version', $ability_data );
		$this->assertArrayHasKey( 'db_server_info', $ability_data );
		$this->assertArrayHasKey( 'wp_version', $ability_data );
		$this->assertArrayHasKey( 'site_health', $ability_data );
		$this->assertSame( $environment, $ability_data['environment'] );
	}

	/**
	 * Tests that the `fields` input limits `core/get-environment-info` output.
	 *
	 * @ticket 65232
	 */
	public function test_core_get_environment_info_fields_filtering(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability       = wp_get_ability( 'core/get-environment-info' );
		$filtered_data = $ability->execute( array( 'fields' => array( 'php_version', 'site_health' ) ) );

		$this->assertCount( 2, $filtered_data );
		$this->assertArrayHasKey( 'php_version', $filtered_data );
		$this->assertArrayHasKey( 'site_health', $filtered_data );
		$this->assertArrayNotHasKey( 'environment', $filtered_data );
		$this->assertArrayNotHasKey( 'db_server_info', $filtered_data );
		$this->assertArrayNotHasKey( 'wp_version', $filtered_data );
	}

	/**
	 * Tests `site_health` in `core/get-environment-info` when no Site Health cache exists.
	 *
	 * @ticket 65232
	 */
	public function test_core_get_environment_info_site_health_without_cache(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		delete_transient( 'health-check-site-status-result' );

		$ability     = wp_get_ability( 'core/get-environment-info' );
		$site_health = $ability->execute( array( 'fields' => array( 'site_health' ) ) )['site_health'];

		$this->assertSame( 'unknown', $site_health['status'] );
		$this->assertSameSetsWithIndex(
			array(
				'good'        => 0,
				'recommended' => 0,
				'critical'    => 0,
			),
			$site_health['counts']
		);
		$this->assertSame( array(), $site_health['issues'] );
		$this->assertFalse( $site_health['truncated'] );
		$this->assertSame( 0, $site_health['timestamp'] );
	}

	/**
	 * Tests `site_health` in `core/get-environment-info` when cached Site Health results exist.
	 *
	 * @ticket 65232
	 */
	public function test_core_get_environment_info_site_health_from_cache(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$cached_data = array(
			'good'        => 8,
			'recommended' => 1,
			'critical'    => 1,
			'issues'      => array(
				array(
					'test'        => 'wordpress_version',
					'label'       => 'WordPress update available',
					'status'      => 'recommended',
					'description' => '<p>A new version of WordPress is available.</p>',
				),
				array(
					'test'        => 'authorization_header',
					'label'       => 'Authorization header missing',
					'status'      => 'critical',
					'description' => '<p>The authorization header is missing.</p>',
				),
			),
			'timestamp'   => 1715714399,
		);
		set_transient( 'health-check-site-status-result', wp_json_encode( $cached_data ) );

		$ability     = wp_get_ability( 'core/get-environment-info' );
		$site_health = $ability->execute( array( 'fields' => array( 'site_health' ) ) )['site_health'];

		$this->assertSame( 'critical', $site_health['status'] );
		$this->assertSame( 8, $site_health['counts']['good'] );
		$this->assertSame( 1, $site_health['counts']['recommended'] );
		$this->assertSame( 1, $site_health['counts']['critical'] );
		$this->assertSame( 1715714399, $site_health['timestamp'] );
		$this->assertFalse( $site_health['truncated'] );
		$this->assertSame(
			array(
				'test'           => 'wordpress_version',
				'label'          => 'WordPress update available',
				'severity'       => 'recommended',
				'recommendation' => 'A new version of WordPress is available.',
			),
			$site_health['issues'][0]
		);
	}

	/**
	 * Tests that only actionable Site Health issues are exposed and capped.
	 *
	 * @ticket 65232
	 */
	public function test_core_get_environment_info_site_health_issues_are_actionable_and_truncated(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$issues = array(
			array(
				'test'        => 'passing_test',
				'label'       => 'Passing test',
				'status'      => 'good',
				'description' => 'This should not be exposed.',
			),
			array(
				'test'        => 'unknown_status',
				'label'       => 'Unknown status',
				'status'      => 'invalid',
				'description' => 'This should not be exposed.',
			),
		);

		for ( $i = 0; $i < 11; $i++ ) {
			$issues[] = array(
				'test'        => 'test_' . $i,
				'label'       => 'Issue ' . $i,
				'status'      => 'recommended',
				'description' => 'Description ' . $i,
			);
		}

		set_transient(
			'health-check-site-status-result',
			wp_json_encode(
				array(
					'good'        => 1,
					'recommended' => 11,
					'critical'    => 0,
					'issues'      => $issues,
					'timestamp'   => 1715714399,
				)
			)
		);

		$ability     = wp_get_ability( 'core/get-environment-info' );
		$site_health = $ability->execute( array( 'fields' => array( 'site_health' ) ) )['site_health'];

		$this->assertTrue( $site_health['truncated'] );
		$this->assertCount( 10, $site_health['issues'] );
		$this->assertSame( 'test_0', $site_health['issues'][0]['test'] );
		$this->assertSame( 'test_9', $site_health['issues'][9]['test'] );
	}

	/**
	 * Tests that malformed cached Site Health data is treated as unknown.
	 *
	 * @ticket 65232
	 */
	public function test_core_get_environment_info_site_health_with_malformed_cache(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		set_transient( 'health-check-site-status-result', '{not-json' );

		$ability     = wp_get_ability( 'core/get-environment-info' );
		$site_health = $ability->execute( array( 'fields' => array( 'site_health' ) ) )['site_health'];

		$this->assertSame( 'unknown', $site_health['status'] );
		$this->assertSame( array(), $site_health['issues'] );
	}

	/**
	 * Tests that the environment info ability reads cached Site Health data only.
	 *
	 * @ticket 65232
	 */
	public function test_core_get_environment_info_site_health_does_not_run_site_health_tests(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$fail_on_test_discovery = function ( $tests ) {
			$this->fail( 'core/get-environment-info must not run or discover Site Health tests.' );
			return $tests;
		};

		add_filter( 'site_status_tests', $fail_on_test_discovery );

		$ability     = wp_get_ability( 'core/get-environment-info' );
		$site_health = $ability->execute( array( 'fields' => array( 'site_health' ) ) )['site_health'];

		remove_filter( 'site_status_tests', $fail_on_test_discovery );

		$this->assertIsArray( $site_health );
	}

	/**
	 * Tests that the environment info ability documents the Site Health schema precisely.
	 *
	 * @ticket 65232
	 */
	public function test_core_get_environment_info_site_health_schema(): void {
		$ability      = wp_get_ability( 'core/get-environment-info' );
		$site_health  = $ability->get_output_schema()['properties']['site_health'];
		$issue_schema = $site_health['properties']['issues']['items'];

		$this->assertSame( array( 'unknown', 'good', 'recommended', 'critical' ), $site_health['properties']['status']['enum'] );
		$this->assertFalse( $site_health['additionalProperties'] );
		$this->assertFalse( $site_health['properties']['counts']['additionalProperties'] );
		$this->assertSame( array( 'recommended', 'critical' ), $issue_schema['properties']['severity']['enum'] );
		$this->assertFalse( $issue_schema['additionalProperties'] );
	}

	/**
	 * Tests that all core ability schemas only use valid JSON Schema keywords.
	 *
	 * This prevents regressions where invalid keywords like 'examples' are used
	 * in schema properties (not valid in JSON Schema draft-04 used by WordPress).
	 *
	 * @ticket 64384
	 */
	public function test_core_abilities_schemas_use_only_valid_keywords(): void {
		$allowed_keywords = rest_get_allowed_schema_keywords();
		// Add 'required' which is valid at the property level for draft-04.
		$allowed_keywords[] = 'required';

		$abilities = wp_get_abilities();

		$this->assertNotEmpty( $abilities, 'Core abilities should be registered.' );

		foreach ( $abilities as $ability ) {
			$this->assert_schema_uses_valid_keywords(
				$ability->get_input_schema(),
				$allowed_keywords,
				$ability->get_name() . ' input_schema'
			);
			$this->assert_schema_uses_valid_keywords(
				$ability->get_output_schema(),
				$allowed_keywords,
				$ability->get_name() . ' output_schema'
			);
		}
	}

	/**
	 * Recursively validates that a schema only uses allowed keywords.
	 *
	 * @param array|null $schema           The schema to validate.
	 * @param string[]   $allowed_keywords List of allowed schema keywords.
	 * @param string     $context          Context for error messages.
	 */
	private function assert_schema_uses_valid_keywords( ?array $schema, array $allowed_keywords, string $context ): void {
		if ( null === $schema ) {
			return;
		}

		foreach ( $schema as $key => $value ) {
			// Skip integer keys (array indices).
			if ( is_int( $key ) ) {
				continue;
			}

			// These keywords contain nested schemas that we recurse into.
			$nesting_keywords = array( 'properties', 'items', 'additionalProperties', 'patternProperties', 'anyOf', 'oneOf' );

			if ( ! in_array( $key, $nesting_keywords, true ) && ! in_array( $key, $allowed_keywords, true ) ) {
				$this->fail( "Invalid schema keyword '{$key}' found in {$context}. Valid keywords are: " . implode( ', ', $allowed_keywords ) );
			}

			// Recursively check nested schemas.
			if ( 'properties' === $key && is_array( $value ) ) {
				foreach ( $value as $prop_name => $prop_schema ) {
					$this->assert_schema_uses_valid_keywords(
						$prop_schema,
						$allowed_keywords,
						"{$context}.properties.{$prop_name}"
					);
				}
			} elseif ( 'items' === $key && is_array( $value ) ) {
				$this->assert_schema_uses_valid_keywords(
					$value,
					$allowed_keywords,
					"{$context}.items"
				);
			} elseif ( ( 'anyOf' === $key || 'oneOf' === $key ) && is_array( $value ) ) {
				foreach ( $value as $index => $sub_schema ) {
					$this->assert_schema_uses_valid_keywords(
						$sub_schema,
						$allowed_keywords,
						"{$context}.{$key}[{$index}]"
					);
				}
			} elseif ( 'additionalProperties' === $key && is_array( $value ) ) {
				$this->assert_schema_uses_valid_keywords(
					$value,
					$allowed_keywords,
					"{$context}.additionalProperties"
				);
			}
		}
	}
}
