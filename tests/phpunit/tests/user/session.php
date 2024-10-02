<?php
/**
 * Test WP_Session_Tokens and WP_User_Meta_Session_Tokens, in wp-includes/session.php
 *
 * @group user
 * @group session
 */
class Tests_User_Session extends WP_UnitTestCase {

	/**
	 * @var WP_User_Meta_Session_Tokens
	 */
	private $manager;

	public function set_up() {
		parent::set_up();
		remove_all_filters( 'session_token_manager' );
		$user_id       = self::factory()->user->create();
		$this->manager = WP_Session_Tokens::get_instance( $user_id );
		$this->assertInstanceOf( 'WP_Session_Tokens', $this->manager );
		$this->assertInstanceOf( 'WP_User_Meta_Session_Tokens', $this->manager );
	}

	public function test_verify_and_destroy_token() {
		$expiration = time() + DAY_IN_SECONDS;
		$token      = $this->manager->create( $expiration );
		$this->assertFalse( $this->manager->verify( 'foo' ) );
		$this->assertTrue( $this->manager->verify( $token ) );
		$this->manager->destroy( $token );
		$this->assertFalse( $this->manager->verify( $token ) );
	}

	public function test_destroy_other_tokens() {
		$expiration = time() + DAY_IN_SECONDS;
		$token_1    = $this->manager->create( $expiration );
		$token_2    = $this->manager->create( $expiration );
		$token_3    = $this->manager->create( $expiration );
		$this->assertTrue( $this->manager->verify( $token_1 ) );
		$this->assertTrue( $this->manager->verify( $token_2 ) );
		$this->assertTrue( $this->manager->verify( $token_3 ) );
		$this->manager->destroy_others( $token_2 );
		$this->assertFalse( $this->manager->verify( $token_1 ) );
		$this->assertTrue( $this->manager->verify( $token_2 ) );
		$this->assertFalse( $this->manager->verify( $token_3 ) );
	}

	public function test_destroy_all_tokens() {
		$expiration = time() + DAY_IN_SECONDS;
		$token_1    = $this->manager->create( $expiration );
		$token_2    = $this->manager->create( $expiration );
		$this->assertTrue( $this->manager->verify( $token_1 ) );
		$this->assertTrue( $this->manager->verify( $token_2 ) );
		$this->manager->destroy_all();
		$this->assertFalse( $this->manager->verify( $token_1 ) );
		$this->assertFalse( $this->manager->verify( $token_2 ) );
	}
}
