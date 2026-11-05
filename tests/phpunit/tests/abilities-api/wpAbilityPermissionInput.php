<?php declare( strict_types=1 );
/**
 * Tests for input preparation before ability permission checks.
 *
 * @group abilities-api
 * @ticket 66482
 * @covers WP_Ability
 */
class Tests_Abilities_API_WpAbilityPermissionInput extends WP_UnitTestCase {

	/**
	 * Returns arguments for an ability that echoes its integer input.
	 *
	 * @param array $overrides Properties to override.
	 * @return array Ability properties.
	 */
	private function get_args( array $overrides = array() ): array {
		return array_merge(
			array(
				'label'               => 'Permission input',
				'description'         => 'Checks input before execution.',
				'category'            => 'test',
				'input_schema'        => array( 'type' => 'integer' ),
				'output_schema'       => array( 'type' => 'integer' ),
				'permission_callback' => '__return_true',
				'execute_callback'    => static function ( $input ) {
					return $input;
				},
			),
			$overrides
		);
	}

	/**
	 * Tests input preparation before direct permission checks.
	 *
	 * @dataProvider data_permission_input
	 *
	 * @param array $schema   Input schema.
	 * @param mixed $input    Raw input.
	 * @param mixed $expected Prepared input.
	 */
	public function test_direct_permission_check_prepares_input( array $schema, $input, $expected ): void {
		$received = null;
		$ability  = new WP_Ability(
			'test/permission-input',
			$this->get_args(
				array(
					'input_schema'        => $schema,
					'permission_callback' => static function ( $value ) use ( &$received ) {
						$received = $value;
						return true;
					},
				)
			)
		);

		$this->assertTrue( $ability->check_permissions( $input ) );
		$this->assertSame( $expected, $received );
	}

	/**
	 * Provides inputs that need normalization or coercion.
	 *
	 * @return array Input preparation cases.
	 */
	public static function data_permission_input(): array {
		return array(
			'integer string' => array( array( 'type' => 'integer' ), '123', 123 ),
			'boolean string' => array( array( 'type' => 'boolean' ), 'false', false ),
			'default'        => array(
				array(
					'type'    => 'integer',
					'default' => '7',
				),
				null,
				7,
			),
			'object default' => array(
				array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'default'    => array( 'id' => '7' ),
				),
				null,
				array( 'id' => 7 ),
			),
		);
	}

	/**
	 * Tests invalid input does not reach permission callback or filter.
	 */
	public function test_invalid_input_does_not_reach_permission_callback_or_filter(): void {
		$calls   = 0;
		$ability = new WP_Ability(
			'test/permission-input',
			$this->get_args(
				array(
					'permission_callback' => static function () use ( &$calls ) {
						++$calls;
						return true;
					},
				)
			)
		);
		add_filter(
			'wp_ability_permission_result',
			static function () use ( &$calls ) {
				++$calls;
				return true;
			}
		);

		$result = $ability->check_permissions( 'invalid' );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
		$this->assertSame( 0, $calls );
	}

	/**
	 * Tests normalization error is preserved without validation.
	 */
	public function test_normalization_error_is_preserved_without_validation(): void {
		$error   = new WP_Error( 'normalization_failed', 'Normalization failed.', array( 'status' => 422 ) );
		$calls   = 0;
		$ability = new WP_Ability( 'test/permission-input', $this->get_args() );
		add_filter(
			'wp_ability_normalize_input',
			static function () use ( $error ) {
				return $error;
			}
		);
		add_filter(
			'wp_ability_validate_input',
			static function ( $valid ) use ( &$calls ) {
				++$calls;
				return $valid;
			}
		);

		$this->assertSame( $error, $ability->check_permissions( 1 ) );
		$this->assertSame( 0, $calls );
	}

	/**
	 * Tests validation filter error is preserved.
	 */
	public function test_validation_filter_error_is_preserved(): void {
		$error   = new WP_Error( 'custom_input_error', 'Invalid input.', array( 'status' => 422 ) );
		$ability = new WP_Ability( 'test/permission-input', $this->get_args() );
		add_filter(
			'wp_ability_validate_input',
			static function () use ( $error ) {
				return $error;
			}
		);

		$this->assertSame( $error, $ability->check_permissions( 1 ) );
	}

	/**
	 * Tests coercion failure does not reach permission callback.
	 */
	public function test_coercion_failure_does_not_reach_permission_callback(): void {
		$calls   = 0;
		$ability = new WP_Ability(
			'test/permission-input',
			$this->get_args(
				array(
					'input_schema'        => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'uniqueItems' => true,
					),
					'permission_callback' => static function () use ( &$calls ) {
						++$calls;
						return true;
					},
				)
			)
		);

		$result = $ability->check_permissions( array( '1', '01' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
		$this->assertSame( 0, $calls );
	}

	/**
	 * Tests permission filter receives prepared input and can deny.
	 */
	public function test_permission_filter_receives_prepared_input_and_can_deny(): void {
		$received = null;
		$error    = new WP_Error( 'permission_denied', 'Denied.' );
		$ability  = new WP_Ability( 'test/permission-input', $this->get_args() );
		add_filter(
			'wp_ability_permission_result',
			static function ( $permission, $name, $input ) use ( &$received, $error ) {
				$received = $input;
				return $error;
			},
			10,
			3
		);

		$this->assertSame( $error, $ability->check_permissions( '7' ) );
		$this->assertSame( 7, $received );
	}

	/**
	 * Tests each direct call prepares input again.
	 */
	public function test_each_direct_call_prepares_input_again(): void {
		$calls   = 0;
		$ability = new WP_Ability( 'test/permission-input', $this->get_args() );
		add_filter(
			'wp_ability_normalize_input',
			static function ( $input ) use ( &$calls ) {
				++$calls;
				return $input + 1;
			}
		);

		$this->assertTrue( $ability->check_permissions( 1 ) );
		$this->assertTrue( $ability->check_permissions( 1 ) );
		$this->assertSame( 2, $calls );
	}

	/**
	 * Tests reentrant permission check prepares its own input.
	 */
	public function test_reentrant_permission_check_prepares_its_own_input(): void {
		$calls    = 0;
		$nested   = false;
		$received = array();
		$ability  = new WP_Ability(
			'test/permission-input',
			$this->get_args(
				array(
					'permission_callback' => static function ( $input ) use ( &$ability, &$nested, &$received ) {
						$received[] = $input;
						if ( ! $nested ) {
							$nested = true;
							return $ability->check_permissions( 1 );
						}
						return true;
					},
				)
			)
		);
		add_filter(
			'wp_ability_normalize_input',
			static function ( $input ) use ( &$calls ) {
				++$calls;
				return $input + 1;
			}
		);

		$this->assertTrue( $ability->check_permissions( 1 ) );
		$this->assertSame( array( 2, 2 ), $received );
		$this->assertSame( 2, $calls );
	}

	/**
	 * Tests execute prepares input once.
	 */
	public function test_execute_prepares_input_once(): void {
		$normalized = 0;
		$validated  = 0;
		$received   = null;
		$ability    = new WP_Ability(
			'test/permission-input',
			$this->get_args(
				array(
					'permission_callback' => static function ( $input ) use ( &$received ) {
						$received = $input;
						return true;
					},
				)
			)
		);
		add_filter(
			'wp_ability_normalize_input',
			static function ( $input ) use ( &$normalized ) {
				++$normalized;
				return (string) ( $input + 1 );
			}
		);
		add_filter(
			'wp_ability_validate_input',
			static function ( $valid ) use ( &$validated ) {
				++$validated;
				return $valid;
			}
		);

		$this->assertSame( 2, $ability->execute( '1' ) );
		$this->assertSame( 2, $received );
		$this->assertSame( 1, $normalized );
		$this->assertSame( 1, $validated );
	}

	/**
	 * Tests execute preserves custom permission override.
	 */
	public function test_execute_preserves_custom_permission_override(): void {
		$ability = new class( 'test/permission-input', $this->get_args() ) extends WP_Ability {
			public $received;
			public function check_permissions( $input = null ) {
				$this->received = $input;
				return false;
			}
		};

		$result = $ability->execute( '1' );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertSame( 1, $ability->received );
	}

	/**
	 * Tests execute preserves override delegating to parent without repreparing.
	 */
	public function test_execute_preserves_override_delegating_to_parent_without_repreparing(): void {
		$calls   = 0;
		$ability = new class( 'test/permission-input', $this->get_args() ) extends WP_Ability {
			public $received;
			public function check_permissions( $input = null ) {
				$this->received = $input;
				return parent::check_permissions( $input );
			}
		};
		add_filter(
			'wp_ability_normalize_input',
			static function ( $input ) use ( &$calls ) {
				++$calls;
				return $input + 1;
			}
		);

		$this->assertSame( 2, $ability->execute( 1 ) );
		$this->assertSame( 2, $ability->received );
		$this->assertSame( 1, $calls );
	}

	/**
	 * Tests inherited permission method prepares input.
	 */
	public function test_inherited_permission_method_prepares_input(): void {
		$received = null;
		$ability  = new class(
			'test/permission-input',
			$this->get_args(
				array(
					'permission_callback' => static function ( $input ) use ( &$received ) {
						$received = $input;
						return true;
					},
				)
			)
		) extends WP_Ability {};

		$this->assertTrue( $ability->check_permissions( '7' ) );
		$this->assertSame( 7, $received );
	}

	/**
	 * Tests prepared input path does not run preparation filters.
	 */
	public function test_prepared_input_path_does_not_run_preparation_filters(): void {
		$calls   = 0;
		$ability = new WP_Ability( 'test/permission-input', $this->get_args() );
		add_filter(
			'wp_ability_normalize_input',
			static function ( $input ) use ( &$calls ) {
				++$calls;
				return $input + 1;
			}
		);

		$this->assertTrue( $ability->check_permissions_for_prepared_input( 1 ) );
		$this->assertSame( 0, $calls );
	}
}
