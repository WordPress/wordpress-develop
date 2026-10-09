<?php

/**
 * Tests for the lazy loading of the `caps`, `roles`, and `allcaps` properties of WP_User,
 * for instances initialized with `$short_init`, such as those returned by get_authordata().
 *
 * @group user
 * @group capabilities
 *
 * @ticket 58001
 *
 * @covers WP_User
 */
class Tests_User_WpUserLazyCapabilities extends WP_UnitTestCase {

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Creates the shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id = $factory->user->create(
			array(
				'role'       => 'editor',
				'first_name' => 'Edith',
			)
		);
	}

	/**
	 * Returns a short-init WP_User object for the editor, with the user meta cache emptied.
	 *
	 * @return WP_User
	 */
	private function get_fresh_editor() {
		wp_cache_delete( self::$editor_id, 'user_meta' );

		return get_authordata( self::$editor_id );
	}

	/**
	 * @ticket 58001
	 */
	public function test_capability_data_is_loaded_eagerly_by_default() {
		wp_cache_delete( self::$editor_id, 'user_meta' );

		$user = new WP_User( self::$editor_id );

		$this->assertIsArray( wp_cache_get( self::$editor_id, 'user_meta' ), 'User meta should be loaded when the user is constructed.' );
		$this->assertSame( array( 'editor' ), get_object_vars( $user )['roles'], 'get_object_vars() should include the roles without prior access.' );
		$this->assertSame( array( 'editor' ), ( (array) $user )['roles'], 'An array cast should include the roles without prior access.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_init_with_short_init_defers_loading() {
		$user = new WP_User();
		$user->init( WP_User::get_data_by( 'id', self::$editor_id ), 0, true );

		$this->assertArrayNotHasKey( 'roles', get_object_vars( $user ), 'Roles should not be loaded before first access.' );
		$this->assertSame( array( 'editor' ), $user->roles, 'Roles should be loaded on first access.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_user_meta_is_not_loaded_until_capability_data_is_accessed() {
		$user = $this->get_fresh_editor();

		$this->assertNotEmpty( $user->user_login, 'User data should be available.' );
		$this->assertFalse( wp_cache_get( self::$editor_id, 'user_meta' ), 'User meta should not be loaded when the user is constructed.' );

		$this->assertSame( array( 'editor' ), $user->roles, 'User should have the editor role.' );
		$this->assertIsArray( wp_cache_get( self::$editor_id, 'user_meta' ), 'User meta should be loaded once the roles are accessed.' );
	}

	/**
	 * @ticket 58001
	 *
	 * @dataProvider data_capability_properties
	 *
	 * @param string $property Property name.
	 */
	public function test_capability_property_is_loaded_on_access( $property ) {
		$user = $this->get_fresh_editor();

		$this->assertTrue( isset( $user->$property ), "The {$property} property should be set." );
		$this->assertIsArray( $user->$property, "The {$property} property should be an array." );
		$this->assertNotEmpty( $user->$property, "The {$property} property should not be empty." );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_capability_properties() {
		return array(
			'caps'    => array( 'caps' ),
			'roles'   => array( 'roles' ),
			'allcaps' => array( 'allcaps' ),
		);
	}

	/**
	 * @ticket 58001
	 */
	public function test_capability_data_is_correct() {
		$user = $this->get_fresh_editor();

		$this->assertSame( array( 'editor' => true ), $user->caps, 'User caps should only contain the role.' );
		$this->assertSame( array( 'editor' ), $user->roles, 'User roles should match.' );
		$this->assertSame( wp_roles()->get_role( 'editor' )->capabilities + array( 'editor' => true ), $user->allcaps, 'User allcaps should contain the role capabilities.' );
		$this->assertTrue( $user->has_cap( 'edit_others_posts' ), 'User should have the edit_others_posts capability.' );
		$this->assertFalse( $user->has_cap( 'manage_options' ), 'User should not have the manage_options capability.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_has_cap_loads_capability_data() {
		$user = $this->get_fresh_editor();

		$this->assertTrue( $user->has_cap( 'edit_posts' ), 'User should have the edit_posts capability.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_get_role_caps_loads_capability_data() {
		$user = $this->get_fresh_editor();

		$role_caps = $user->get_role_caps();

		$this->assertIsArray( $role_caps, 'User role capabilities should be an array.' );
		$this->assertArrayHasKey( 'edit_others_posts', $role_caps, 'User role capabilities should contain the edit_others_posts capability.' );
		$this->assertSame( $role_caps, $user->allcaps, 'The returned capabilities should match allcaps.' );
	}

	/**
	 * @ticket 58001
	 *
	 * @dataProvider data_set_capability_property
	 *
	 * @param string $property Property name.
	 * @param array  $value    Value to set.
	 */
	public function test_set_capability_property( $property, $value ) {
		$user = $this->get_fresh_editor();

		$user->$property = $value;

		$this->assertSame( $value, $user->$property, "The {$property} property should be set to the new value." );
		$this->assertSameSets( array( 'caps', 'roles', 'allcaps' ), array_intersect( array( 'caps', 'roles', 'allcaps' ), array_keys( get_object_vars( $user ) ) ), 'All capability properties should be loaded.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: array}>
	 */
	public function data_set_capability_property() {
		return array(
			'caps'    => array( 'caps', array( 'foo' => true ) ),
			'roles'   => array( 'roles', array( 'editor' ) ),
			'allcaps' => array( 'allcaps', array( 'foo' => true ) ),
		);
	}

	/**
	 * @ticket 58001
	 *
	 * @dataProvider data_capability_properties
	 *
	 * @param string $property Property name.
	 */
	public function test_unset_capability_property( $property ) {
		$user = $this->get_fresh_editor();

		unset( $user->$property );

		$this->assertFalse( isset( $user->$property ), "The {$property} property should not be set." );
		$this->assertNull( $user->$property, "The {$property} property should be null." );
	}

	/**
	 * @ticket 58001
	 */
	public function test_array_modification_after_access() {
		$user = $this->get_fresh_editor();
		$user->roles;

		$user->roles[]        = 'author';
		$user->allcaps['foo'] = true;
		array_shift( $user->roles );

		$this->assertSame( array( 'author' ), $user->roles, 'User roles should be modifiable.' );
		$this->assertTrue( $user->allcaps['foo'], 'User allcaps should be modifiable.' );
	}

	/**
	 * Tests that modifying a capability property in place works before it has been loaded.
	 *
	 * @ticket 58001
	 *
	 * @dataProvider data_array_modification_before_access
	 *
	 * @param string   $property Property name.
	 * @param callable $modify   Callback that modifies the property in place.
	 * @param array    $expected Expected value of the property.
	 */
	public function test_array_modification_before_access( $property, $modify, $expected ) {
		$user = $this->get_fresh_editor();

		$modify( $user );

		$this->assertSame( $expected, $user->$property, "The {$property} property should be modified in place." );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: callable, 2: array}>
	 */
	public function data_array_modification_before_access() {
		$editor_allcaps = wp_roles()->get_role( 'editor' )->capabilities + array( 'editor' => true );

		return array(
			'append to roles'       => array(
				'roles',
				static function ( $user ) {
					$user->roles[] = 'author';
				},
				array( 'editor', 'author' ),
			),
			'set roles element'     => array(
				'roles',
				static function ( $user ) {
					$user->roles[0] = 'author';
				},
				array( 'author' ),
			),
			'array_shift roles'     => array(
				'roles',
				static function ( $user ) {
					array_shift( $user->roles );
				},
				array(),
			),
			'set allcaps element'   => array(
				'allcaps',
				static function ( $user ) {
					$user->allcaps['wibble'] = true;
				},
				$editor_allcaps + array( 'wibble' => true ),
			),
			'unset allcaps element' => array(
				'allcaps',
				static function ( $user ) {
					unset( $user->allcaps['editor'] );
				},
				wp_roles()->get_role( 'editor' )->capabilities,
			),
			'set caps element'      => array(
				'caps',
				static function ( $user ) {
					$user->caps['foo'] = false;
				},
				array(
					'editor' => true,
					'foo'    => false,
				),
			),
		);
	}

	/**
	 * @ticket 58001
	 */
	public function test_json_encode_includes_capability_data() {
		$expected_user = $this->get_fresh_editor();
		$expected_user->roles;
		$expected = wp_json_encode( get_object_vars( $expected_user ) );

		$actual = wp_json_encode( $this->get_fresh_editor() );

		$this->assertSame( $expected, $actual, 'JSON encoding should include the capability data.' );

		$decoded = json_decode( $actual, true );
		$this->assertSame( array( 'editor' ), $decoded['roles'], 'JSON encoded roles should match.' );
		$this->assertArrayNotHasKey( 'site_id', $decoded, 'JSON encoding should not include private properties.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_serialize_includes_capability_data() {
		$user = unserialize( serialize( $this->get_fresh_editor() ) );

		$this->assertInstanceOf( WP_User::class, $user, 'Unserialized value should be a WP_User.' );
		$this->assertSame( self::$editor_id, $user->ID, 'Unserialized user ID should match.' );
		$this->assertSame( array( 'editor' ), $user->roles, 'Unserialized roles should match.' );
		$this->assertSame( get_current_blog_id(), $user->get_site_id(), 'Unserialized site ID should match.' );
		$this->assertArrayHasKey( 'allcaps', get_object_vars( $user ), 'Unserialized user should have allcaps set.' );
		$this->assertTrue( $user->has_cap( 'edit_others_posts' ), 'Unserialized user should have the edit_others_posts capability.' );
	}

	/**
	 * Tests that WP_User objects serialized before the introduction of WP_User::__serialize() can be unserialized.
	 *
	 * @ticket 58001
	 */
	public function test_unserialize_legacy_format() {
		$serialized_string = static function ( $value ) {
			return sprintf( 's:%d:"%s";', strlen( $value ), $value );
		};

		$properties = array(
			'data'               => serialize( (object) array( 'ID' => (string) self::$editor_id ) ),
			'ID'                 => serialize( self::$editor_id ),
			'caps'               => serialize( array( 'author' => true ) ),
			'cap_key'            => serialize( 'wptests_capabilities' ),
			'roles'              => serialize( array( 'author' ) ),
			'allcaps'            => serialize( array( 'edit_posts' => true ) ),
			'filter'             => serialize( null ),
			"\0WP_User\0site_id" => serialize( 7 ),
		);

		$legacy = sprintf( 'O:7:"WP_User":%d:{', count( $properties ) );
		foreach ( $properties as $name => $value ) {
			$legacy .= $serialized_string( $name ) . $value;
		}
		$legacy .= '}';

		$user = unserialize( $legacy );

		$this->assertInstanceOf( WP_User::class, $user, 'Unserialized value should be a WP_User.' );
		$this->assertSame( array( 'author' ), $user->roles, 'Unserialized roles should match the serialized data.' );
		$this->assertSame( array( 'edit_posts' => true ), $user->allcaps, 'Unserialized allcaps should match the serialized data.' );
		$this->assertSame( 7, $user->get_site_id(), 'Unserialized site ID should match the serialized data.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_serialize_subclass_keeps_private_properties() {
		$user = new Tests_User_WpUserLazyCapabilities_Subclass( self::$editor_id );
		$user->set_secret( 'value' );

		$user = unserialize( serialize( $user ) );

		$this->assertSame( 'value', $user->get_secret(), 'Private property of the subclass should be restored.' );
		$this->assertSame( array( 'editor' ), $user->roles, 'Unserialized roles should match.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_clone_loads_capability_data_independently() {
		$user  = $this->get_fresh_editor();
		$clone = clone $user;

		$clone->roles = array( 'author' );

		$this->assertSame( array( 'editor' ), $user->roles, 'Original roles should not be affected by the clone.' );
		$this->assertSame( array( 'author' ), $clone->roles, 'Cloned roles should be modified.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_capability_mutators_load_capability_data() {
		$user = $this->get_fresh_editor();
		$user->add_cap( 'foo' );
		$this->assertTrue( $user->has_cap( 'foo' ), 'User should have the added capability.' );
		$this->assertSame( array( 'editor' ), $user->roles, 'User should still have the editor role.' );

		$user = $this->get_fresh_editor();
		$user->remove_cap( 'foo' );
		$this->assertFalse( $user->has_cap( 'foo' ), 'User should not have the removed capability.' );

		$user = $this->get_fresh_editor();
		$user->add_role( 'author' );
		$this->assertSameSets( array( 'editor', 'author' ), $user->roles, 'User should have both roles.' );

		$user = $this->get_fresh_editor();
		$user->remove_role( 'author' );
		$this->assertSame( array( 'editor' ), array_values( $user->roles ), 'User should only have the editor role.' );

		$user = $this->get_fresh_editor();
		$user->set_role( 'editor' );
		$this->assertSame( array( 'editor' ), array_values( $user->roles ), 'Setting the same role should keep the role.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_for_site_resets_loaded_capability_data() {
		$user = $this->get_fresh_editor();
		$user->roles;

		update_user_meta( self::$editor_id, $user->cap_key, array( 'author' => true ) );
		$user->for_site();

		$this->assertArrayNotHasKey( 'roles', get_object_vars( $user ), 'Roles should be unloaded after for_site().' );
		$this->assertSame( array( 'author' ), $user->roles, 'Roles should be reloaded after for_site().' );

		update_user_meta( self::$editor_id, $user->cap_key, array( 'editor' => true ) );
	}

	/**
	 * @ticket 58001
	 */
	public function test_for_site_loads_eagerly_by_default() {
		$user = new WP_User( self::$editor_id );

		$user->for_site();

		$this->assertSame( array( 'editor' ), get_object_vars( $user )['roles'], 'Roles should be reloaded immediately after for_site().' );
	}

	/**
	 * @ticket 58001
	 *
	 * @group ms-required
	 */
	public function test_for_site_loads_capability_data_for_other_site() {
		$site_id = self::factory()->blog->create();
		add_user_to_blog( $site_id, self::$editor_id, 'author' );

		$user = $this->get_fresh_editor();
		$this->assertSame( array( 'editor' ), $user->roles, 'User should have the editor role on the main site.' );

		$user->for_site( $site_id );
		$this->assertSame( array( 'author' ), $user->roles, 'User should have the author role on the other site.' );

		$user->for_site( $site_id );
		$user->for_site();
		$this->assertSame( array( 'editor' ), $user->roles, 'User should have the editor role after switching back.' );

		wp_delete_site( $site_id );
	}

	/**
	 * @ticket 58001
	 */
	public function test_non_existent_user_has_empty_capability_data() {
		$user = new WP_User( 0 );

		$this->assertSame( array(), $user->caps, 'Non-existent user should have no caps.' );
		$this->assertSame( array(), $user->roles, 'Non-existent user should have no roles.' );
		$this->assertSame( array(), $user->allcaps, 'Non-existent user should have no allcaps.' );
		$this->assertSame( '[]', wp_json_encode( $user->roles ), 'Non-existent user roles should encode to an empty array.' );
	}

	/**
	 * Asserts that the capability data of a user has not been loaded yet.
	 *
	 * @param WP_User $user    User object.
	 * @param string  $message Optional. Assertion message.
	 */
	private function assertCapabilityDataNotLoaded( $user, $message = '' ) {
		$vars = get_object_vars( $user );

		$this->assertArrayNotHasKey( 'caps', $vars, $message );
		$this->assertArrayNotHasKey( 'roles', $vars, $message );
		$this->assertArrayNotHasKey( 'allcaps', $vars, $message );
	}

	/**
	 * @ticket 58001
	 */
	public function test_init_loads_eagerly_by_default() {
		$user = new WP_User();
		$user->init( WP_User::get_data_by( 'id', self::$editor_id ) );

		$this->assertSame( array( 'editor' ), get_object_vars( $user )['roles'], 'Roles should be loaded by init() by default.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_constructing_from_short_init_user_loads_eagerly() {
		$user = new WP_User( $this->get_fresh_editor() );

		$this->assertSame( array( 'editor' ), get_object_vars( $user )['roles'], 'A copy of a short-init user should load the roles immediately.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_json_encode_eager_user_keys() {
		$decoded = json_decode( wp_json_encode( new WP_User( self::$editor_id ) ), true );

		$this->assertSame(
			array( 'data', 'ID', 'caps', 'cap_key', 'roles', 'allcaps', 'filter' ),
			array_keys( $decoded ),
			'JSON encoding should contain the public properties in declaration order.'
		);
		$this->assertSame( array( 'editor' ), $decoded['roles'], 'JSON encoded roles should match.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_serialize_eager_user() {
		$user         = new WP_User( self::$editor_id );
		$unserialized = unserialize( serialize( $user ) );

		$this->assertEquals( get_object_vars( $user ), get_object_vars( $unserialized ), 'Unserialized public properties should match.' );
		$this->assertSame( $user->get_site_id(), $unserialized->get_site_id(), 'Unserialized site ID should match.' );
		$this->assertTrue( $unserialized->has_cap( 'edit_others_posts' ), 'Unserialized user should have the edit_others_posts capability.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_magic_get_non_capability_keys() {
		$user = $this->get_fresh_editor();

		$this->assertSame( 'Edith', $user->first_name, 'User meta should be readable.' );
		$this->assertSame( WP_User::get_data_by( 'id', self::$editor_id )->user_login, $user->get( 'user_login' ), 'User data should be readable via get().' );
		$this->assertCapabilityDataNotLoaded( $user, 'Reading other keys should not load the capability data.' );
	}

	/**
	 * @ticket 58001
	 *
	 * @expectedDeprecated WP_User->id
	 */
	public function test_magic_get_deprecated_id() {
		$user = $this->get_fresh_editor();

		$this->assertSame( self::$editor_id, $user->id, 'The deprecated id property should return the user ID.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_magic_isset_set_unset_non_capability_keys() {
		$user = $this->get_fresh_editor();

		$this->assertTrue( isset( $user->user_login ), 'User data should be set.' );

		$user->foo = 'bar';
		$this->assertSame( 'bar', $user->data->foo, 'Setting a custom key should store it in the user data.' );

		unset( $user->foo );
		$this->assertObjectNotHasProperty( 'foo', $user->data, 'Unsetting a custom key should remove it from the user data.' );

		$this->assertCapabilityDataNotLoaded( $user, 'Other magic operations should not load the capability data.' );
	}

	/**
	 * @ticket 58001
	 */
	public function test_remove_all_caps_before_access() {
		$user_id = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$user    = get_authordata( $user_id );

		$user->remove_all_caps();

		$this->assertSame( array(), $user->caps, 'User caps should be empty.' );
		$this->assertSame( array(), $user->roles, 'User roles should be empty.' );
		$this->assertFalse( $user->has_cap( 'edit_posts' ), 'User should not have the edit_posts capability.' );
		$this->assertSame( '', get_user_meta( $user_id, $user->cap_key, true ), 'Capabilities meta should be deleted.' );
	}

	/**
	 * Super admins are granted all capabilities without checking the capability data,
	 * so has_cap() does not load it.
	 *
	 * @ticket 58001
	 *
	 * @group ms-required
	 */
	public function test_has_cap_for_super_admin_does_not_load_capability_data() {
		grant_super_admin( self::$editor_id );

		$user    = $this->get_fresh_editor();
		$has_cap = $user->has_cap( 'manage_network' );
		$vars    = get_object_vars( $user );
		$roles   = $user->roles;

		revoke_super_admin( self::$editor_id );

		$this->assertTrue( $has_cap, 'Super admin should have the manage_network capability.' );
		$this->assertArrayNotHasKey( 'roles', $vars, 'has_cap() should not load the roles for a super admin.' );
		$this->assertSame( array( 'editor' ), $roles, 'Roles should still be loaded on access.' );
	}
}

/**
 * WP_User subclass with a private property, used to test serialization.
 */
class Tests_User_WpUserLazyCapabilities_Subclass extends WP_User {

	/**
	 * Private property.
	 *
	 * @var string
	 */
	private $secret = '';

	/**
	 * Sets the private property.
	 *
	 * @param string $secret Value.
	 */
	public function set_secret( $secret ) {
		$this->secret = $secret;
	}

	/**
	 * Gets the private property.
	 *
	 * @return string
	 */
	public function get_secret() {
		return $this->secret;
	}
}
