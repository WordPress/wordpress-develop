<?php

/**
 * Tests for wp_roles().
 *
 * @group user
 * @group capabilities
 *
 * @covers ::wp_roles
 */
class Tests_User_wpRoles extends WP_UnitTestCase {

	/**
	 * Original roles object.
	 *
	 * @var WP_Roles|null
	 */
	private $original_wp_roles;

	public function set_up() {
		parent::set_up();

		$this->original_wp_roles = $GLOBALS['wp_roles'] ?? null;
	}

	public function tear_down() {
		$GLOBALS['wp_roles'] = $this->original_wp_roles;

		parent::tear_down();
	}

	/**
	 * Tests that the roles object is instantiated when the global is not set.
	 *
	 * @ticket 65819
	 */
	public function test_should_instantiate_roles_when_global_is_not_set() {
		unset( $GLOBALS['wp_roles'] );

		$roles = wp_roles();

		$this->assertInstanceOf( WP_Roles::class, $roles, 'A WP_Roles instance should be returned.' );
		$this->assertSame( $roles, $GLOBALS['wp_roles'], 'The instance should be stored in the global.' );
	}

	/**
	 * Tests that an existing roles object is returned as is.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_existing_roles_instance() {
		$roles               = new WP_Roles();
		$GLOBALS['wp_roles'] = $roles;

		$this->assertSame( $roles, wp_roles(), 'The existing instance should be returned.' );
		$this->assertSame( $roles, $GLOBALS['wp_roles'], 'The global should not be replaced.' );
	}

	/**
	 * Tests that a global which is not a roles object is replaced.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_values_other_than_roles_instance
	 *
	 * @param mixed $value Value the global was overwritten with.
	 */
	public function test_should_replace_global_which_is_not_roles_instance( $value ) {
		$GLOBALS['wp_roles'] = $value;

		$roles = wp_roles();

		$this->assertInstanceOf( WP_Roles::class, $roles, 'A WP_Roles instance should be returned.' );
		$this->assertSame( $roles, $GLOBALS['wp_roles'], 'The instance should be stored in the global.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_other_than_roles_instance() {
		return array(
			'a role name'    => array( 'administrator' ),
			'another object' => array( new stdClass() ),
		);
	}
}
