<?php

declare( strict_types=1 );

/**
 * Tests for wp_is_writable_through_abilities().
 *
 * @covers ::wp_is_writable_through_abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpIsWritableThroughAbilities extends WP_UnitTestCase {

	/**
	 * Returns `show_in_abilities` values that allow writes.
	 *
	 * @return array<string, array{show_in_abilities: mixed}> Writable test cases.
	 */
	public static function data_writable_values(): array {
		return array(
			'true'                                 => array(
				'show_in_abilities' => true,
			),
			'readonly false'                       => array(
				'show_in_abilities' => array( 'readonly' => false ),
			),
			'setting array with a name and schema' => array(
				'show_in_abilities' => array(
					'name'   => 'custom_name',
					'schema' => array( 'type' => 'string' ),
				),
			),
			'setting array with readonly null'     => array(
				'show_in_abilities' => array(
					'name'     => 'custom_name',
					'readonly' => null,
				),
			),
			'setting array with an empty readonly' => array(
				'show_in_abilities' => array(
					'schema'   => array( 'type' => 'string' ),
					'readonly' => '',
				),
			),
		);
	}

	/**
	 * Tests that `true` and arrays without a truthy `readonly` key allow writes.
	 *
	 * @ticket 66844
	 * @dataProvider data_writable_values
	 * @since 7.3.0
	 *
	 * @param mixed $show_in_abilities The `show_in_abilities` value.
	 */
	public function test_allows_writes( $show_in_abilities ): void {
		$this->assertTrue( wp_is_writable_through_abilities( $show_in_abilities ) );
	}

	/**
	 * Returns `show_in_abilities` values that do not allow writes.
	 *
	 * @return array<string, array{show_in_abilities: mixed}> Read only and unexposed test cases.
	 */
	public static function data_values_that_are_not_writable(): array {
		return array(
			'readonly true'                    => array(
				'show_in_abilities' => array( 'readonly' => true ),
			),
			'setting array with readonly true' => array(
				'show_in_abilities' => array(
					'name'     => 'custom_name',
					'schema'   => array( 'type' => 'string' ),
					'readonly' => true,
				),
			),
			'readonly as the string "false"'   => array(
				'show_in_abilities' => array( 'readonly' => 'false' ),
			),
			'readonly as integer 1'            => array(
				'show_in_abilities' => array( 'readonly' => 1 ),
			),
			'false'                            => array(
				'show_in_abilities' => false,
			),
			'null'                             => array(
				'show_in_abilities' => null,
			),
			'empty array'                      => array(
				'show_in_abilities' => array(),
			),
			'integer 1'                        => array(
				'show_in_abilities' => 1,
			),
			'string "true"'                    => array(
				'show_in_abilities' => 'true',
			),
			'object with readonly false'       => array(
				'show_in_abilities' => (object) array( 'readonly' => false ),
			),
		);
	}

	/**
	 * Tests that arrays with a truthy `readonly` key and values that do not expose an object
	 * do not allow writes.
	 *
	 * @ticket 66844
	 * @dataProvider data_values_that_are_not_writable
	 * @since 7.3.0
	 *
	 * @param mixed $show_in_abilities The `show_in_abilities` value.
	 */
	public function test_does_not_allow_writes( $show_in_abilities ): void {
		$this->assertFalse( wp_is_writable_through_abilities( $show_in_abilities ) );
	}

	/**
	 * Returns `show_in_abilities` values for a post type, and whether abilities may write
	 * its posts.
	 *
	 * @return array<string, array{show_in_abilities: mixed, expected: bool}> Post type test cases.
	 */
	public static function data_post_type_values(): array {
		return array(
			'true'                   => array(
				'show_in_abilities' => true,
				'expected'          => true,
			),
			'readonly false'         => array(
				'show_in_abilities' => array( 'readonly' => false ),
				'expected'          => true,
			),
			'readonly true'          => array(
				'show_in_abilities' => array( 'readonly' => true ),
				'expected'          => false,
			),
			'non-boolean readonly'   => array(
				'show_in_abilities' => array( 'readonly' => 0 ),
				'expected'          => false,
			),
			'array without readonly' => array(
				'show_in_abilities' => array( 'create' ),
				'expected'          => false,
			),
			'false'                  => array(
				'show_in_abilities' => false,
				'expected'          => false,
			),
		);
	}

	/**
	 * Tests the helper with the value a registered post type holds.
	 *
	 * @ticket 66844
	 * @dataProvider data_post_type_values
	 * @since 7.3.0
	 *
	 * @param mixed $show_in_abilities The `show_in_abilities` value to register the post type with.
	 * @param bool  $expected          Whether abilities may write the post type's posts.
	 */
	public function test_post_type_value( $show_in_abilities, bool $expected ): void {
		register_post_type(
			'writable_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => $show_in_abilities,
			)
		);

		$this->assertSame( $expected, wp_is_writable_through_abilities( get_post_type_object( 'writable_cpt' )->show_in_abilities ) );
	}
}
