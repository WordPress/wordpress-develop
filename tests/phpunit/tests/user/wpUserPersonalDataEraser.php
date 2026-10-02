<?php
/**
 * Tests for personal data erasure for users.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group user
 * @group privacy
 *
 * @covers ::wp_user_personal_data_eraser
 * @covers ::wp_register_user_personal_data_eraser
 */
class Tests_User_WpUserPersonalDataEraser extends WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test user email.
	 *
	 * @var string
	 */
	protected static $user_email;

	/**
	 * Create test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id    = $factory->user->create(
			array(
				'user_email'  => 'personal-data@example.com',
				'description' => 'User biography.',
			)
		);
		self::$user_email = 'personal-data@example.com';
	}

	/**
	 * Test that the user's description is erased.
	 *
	 * @ticket 64282
	 */
	public function test_description_is_erased() {
		$this->assertSame( 'User biography.', get_user_meta( self::$user_id, 'description', true ) );

		$response = wp_user_personal_data_eraser( self::$user_email );

		$this->assertTrue( $response['items_removed'] );
		$this->assertFalse( $response['items_retained'] );
		$this->assertSame( array(), $response['messages'] );
		$this->assertTrue( $response['done'] );
		$this->assertFalse( metadata_exists( 'user', self::$user_id, 'description' ) );
	}

	/**
	 * Test that an empty description is not reported as removed.
	 */
	public function test_empty_description_is_not_removed() {
		update_user_meta( self::$user_id, 'description', '' );

		$response = wp_user_personal_data_eraser( self::$user_email );

		$this->assertFalse( $response['items_removed'] );
		$this->assertFalse( $response['items_retained'] );
		$this->assertSame( array(), $response['messages'] );
		$this->assertTrue( $response['done'] );
		$this->assertTrue( metadata_exists( 'user', self::$user_id, 'description' ) );
	}

	/**
	 * Test that an unknown email does not remove any data.
	 */
	public function test_unknown_email_does_nothing() {
		$response = wp_user_personal_data_eraser( 'unknown@example.com' );

		$this->assertFalse( $response['items_removed'] );
		$this->assertFalse( $response['items_retained'] );
		$this->assertSame( array(), $response['messages'] );
		$this->assertTrue( $response['done'] );
	}

	/**
	 * Test that an empty email does nothing.
	 */
	public function test_empty_email_does_nothing() {
		$response = wp_user_personal_data_eraser( '' );

		$this->assertFalse( $response['items_removed'] );
		$this->assertFalse( $response['items_retained'] );
		$this->assertSame( array(), $response['messages'] );
		$this->assertTrue( $response['done'] );
	}

	/**
	 * Test that only the requested user's data is erased.
	 */
	public function test_only_requested_user_data_is_erased() {
		$other_user_id = self::factory()->user->create(
			array(
				'user_email'  => 'other-user@example.com',
				'description' => "Other user's biography.",
			)
		);

		wp_user_personal_data_eraser( self::$user_email );

		$this->assertFalse( metadata_exists( 'user', self::$user_id, 'description' ) );
		$this->assertSame(
			"Other user's biography.",
			get_user_meta( $other_user_id, 'description', true )
		);
	}

	/**
	 * Test that additional user meta keys can be erased through the filter.
	 */
	public function test_additional_meta_keys_can_be_erased() {
		update_user_meta( self::$user_id, 'custom_personal_data', 'Sensitive data' );

		add_filter( 'wp_privacy_user_personal_data_eraser_meta_keys', array( $this, 'add_custom_meta_key' ) );

		$response = wp_user_personal_data_eraser( self::$user_email );

		remove_filter( 'wp_privacy_user_personal_data_eraser_meta_keys', array( $this, 'add_custom_meta_key' ) );

		$this->assertTrue( $response['items_removed'] );
		$this->assertFalse( metadata_exists( 'user', self::$user_id, 'custom_personal_data' ) );
	}

	/**
	 * Add a custom user meta key to the list of keys to erase.
	 *
	 * @param string[] $meta_keys Meta keys.
	 * @return string[] Meta keys.
	 */
	public function add_custom_meta_key( $meta_keys ) {
		$meta_keys[] = 'custom_personal_data';

		return $meta_keys;
	}

	/**
	 * Test that the filter receives the correct user.
	 */
	public function test_meta_keys_filter_receives_user() {
		$filter = new MockAction();

		add_filter( 'wp_privacy_user_personal_data_eraser_meta_keys', array( $filter, 'filter' ), 10, 2 );

		wp_user_personal_data_eraser( self::$user_email );

		remove_filter( 'wp_privacy_user_personal_data_eraser_meta_keys', array( $filter, 'filter' ) );

		$args = $filter->get_args();

		$this->assertCount( 1, $args );
		$this->assertSame( array( 'description' ), $args[0][0] );
		$this->assertInstanceOf( WP_User::class, $args[0][1] );
		$this->assertSame( self::$user_id, $args[0][1]->ID );
	}

	/**
	 * Test that invalid meta keys are ignored.
	 */
	public function test_invalid_meta_keys_are_ignored() {
		update_user_meta( self::$user_id, 'valid_meta_key', 'Valid data' );

		add_filter( 'wp_privacy_user_personal_data_eraser_meta_keys', array( $this, 'add_invalid_meta_keys' ) );

		$response = wp_user_personal_data_eraser( self::$user_email );

		remove_filter( 'wp_privacy_user_personal_data_eraser_meta_keys', array( $this, 'add_invalid_meta_keys' ) );

		$this->assertTrue( $response['items_removed'] );
		$this->assertSame( 'Valid data', get_user_meta( self::$user_id, 'valid_meta_key', true ) );
	}

	/**
	 * Add invalid meta keys to the eraser list.
	 *
	 * @param string[] $meta_keys Meta keys.
	 * @return array Meta keys.
	 */
	public function add_invalid_meta_keys( $meta_keys ) {
		$meta_keys[] = '';
		$meta_keys[] = array( 'invalid' );
		$meta_keys[] = 123;

		return $meta_keys;
	}

	/**
	 * Test that a meta key containing spaces is used unchanged.
	 *
	 * This ensures meta keys are not passed through sanitize_key().
	 */
	public function test_meta_key_is_used_unchanged() {
		$meta_key = 'Additional Personal Data';

		update_user_meta( self::$user_id, $meta_key, 'Sensitive data' );

		add_filter( 'wp_privacy_user_personal_data_eraser_meta_keys', array( $this, 'add_meta_key_with_spaces' ) );

		$response = wp_user_personal_data_eraser( self::$user_email );

		remove_filter( 'wp_privacy_user_personal_data_eraser_meta_keys', array( $this, 'add_meta_key_with_spaces' ) );

		$this->assertTrue( $response['items_removed'] );
		$this->assertFalse( metadata_exists( 'user', self::$user_id, $meta_key ) );
	}

	/**
	 * Add a meta key containing spaces.
	 *
	 * @param string[] $meta_keys Meta keys.
	 * @return string[] Meta keys.
	 */
	public function add_meta_key_with_spaces( $meta_keys ) {
		$meta_keys[] = 'Additional Personal Data';

		return $meta_keys;
	}

	/**
	 * Test the user personal data eraser registration.
	 */
	public function test_user_personal_data_eraser_is_registered() {
		$erasers = wp_register_user_personal_data_eraser( array() );

		$this->assertArrayHasKey( 'wordpress-user', $erasers );
		$this->assertSame( 'WordPress User', $erasers['wordpress-user']['eraser_friendly_name'] );
		$this->assertSame( 'wp_user_personal_data_eraser', $erasers['wordpress-user']['callback'] );
	}

	/**
	 * Test that existing erasers are preserved during registration.
	 */
	public function test_existing_erasers_are_preserved() {
		$erasers = array(
			'custom-eraser' => array(
				'eraser_friendly_name' => 'Custom Eraser',
				'callback'             => 'custom_eraser',
			),
		);

		$erasers = wp_register_user_personal_data_eraser( $erasers );

		$this->assertArrayHasKey( 'custom-eraser', $erasers );
		$this->assertSame( 'custom_eraser', $erasers['custom-eraser']['callback'] );
		$this->assertArrayHasKey( 'wordpress-user', $erasers );
	}

	/**
	 * Tests that all values for a meta key are erased.
	 */
	public function test_erases_all_values_for_a_meta_key() {
		$user_id = self::factory()->user->create(
			array(
				'user_email' => 'multiple-meta@example.com',
			)
		);

		add_user_meta( $user_id, 'description', 'First biography value' );
		add_user_meta( $user_id, 'description', 'Second biography value' );

		$response = wp_user_personal_data_eraser( 'multiple-meta@example.com' );

		$this->assertTrue( $response['items_removed'] );
		$this->assertSame( array(), get_user_meta( $user_id, 'description' ) );
	}
}
