<?php
/**
 * Tests for WP_AI_Client_Capabilities.
 *
 * @group ai-client
 * @covers WP_AI_Client_Capabilities
 */
class Tests_AI_Client_Capabilities extends WP_UnitTestCase {

	/**
	 * Test admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_user_id;

	/**
	 * Test editor user ID.
	 *
	 * @var int
	 */
	protected static $editor_user_id;

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$admin_user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		self::$editor_user_id = self::factory()->user->create(
			array(
				'role' => 'editor',
			)
		);
	}

	/**
	 * Test that PROMPT_AI constant is defined.
	 *
	 * @ticket TBD
	 */
	public function test_prompt_ai_constant() {
		$this->assertSame( 'prompt_ai', WP_AI_Client_Capabilities::PROMPT_AI );
	}

	/**
	 * Test that LIST_AI_PROVIDERS constant is defined.
	 *
	 * @ticket TBD
	 */
	public function test_list_ai_providers_constant() {
		$this->assertSame( 'list_ai_providers', WP_AI_Client_Capabilities::LIST_AI_PROVIDERS );
	}

	/**
	 * Test that LIST_AI_MODELS constant is defined.
	 *
	 * @ticket TBD
	 */
	public function test_list_ai_models_constant() {
		$this->assertSame( 'list_ai_models', WP_AI_Client_Capabilities::LIST_AI_MODELS );
	}

	/**
	 * Test that admin has prompt_ai capability.
	 *
	 * @ticket TBD
	 */
	public function test_admin_has_prompt_ai() {
		wp_set_current_user( self::$admin_user_id );
		$this->assertTrue( current_user_can( WP_AI_Client_Capabilities::PROMPT_AI ) );
	}

	/**
	 * Test that admin has list_ai_providers capability.
	 *
	 * @ticket TBD
	 */
	public function test_admin_has_list_ai_providers() {
		wp_set_current_user( self::$admin_user_id );
		$this->assertTrue( current_user_can( WP_AI_Client_Capabilities::LIST_AI_PROVIDERS ) );
	}

	/**
	 * Test that admin has list_ai_models capability.
	 *
	 * @ticket TBD
	 */
	public function test_admin_has_list_ai_models() {
		wp_set_current_user( self::$admin_user_id );
		$this->assertTrue( current_user_can( WP_AI_Client_Capabilities::LIST_AI_MODELS ) );
	}

	/**
	 * Test that editor does NOT have prompt_ai capability.
	 *
	 * @ticket TBD
	 */
	public function test_editor_does_not_have_prompt_ai() {
		wp_set_current_user( self::$editor_user_id );
		$this->assertFalse( current_user_can( WP_AI_Client_Capabilities::PROMPT_AI ) );
	}

	/**
	 * Test that editor does NOT have list_ai_providers capability.
	 *
	 * @ticket TBD
	 */
	public function test_editor_does_not_have_list_ai_providers() {
		wp_set_current_user( self::$editor_user_id );
		$this->assertFalse( current_user_can( WP_AI_Client_Capabilities::LIST_AI_PROVIDERS ) );
	}

	/**
	 * Test that editor does NOT have list_ai_models capability.
	 *
	 * @ticket TBD
	 */
	public function test_editor_does_not_have_list_ai_models() {
		wp_set_current_user( self::$editor_user_id );
		$this->assertFalse( current_user_can( WP_AI_Client_Capabilities::LIST_AI_MODELS ) );
	}

	/**
	 * Test grant_prompt_ai_to_administrators static method directly.
	 *
	 * @ticket TBD
	 */
	public function test_grant_prompt_ai_with_manage_options() {
		$allcaps = array( 'manage_options' => true );
		$result  = WP_AI_Client_Capabilities::grant_prompt_ai_to_administrators( $allcaps );
		$this->assertTrue( $result['prompt_ai'] );
	}

	/**
	 * Test grant_prompt_ai_to_administrators without manage_options.
	 *
	 * @ticket TBD
	 */
	public function test_grant_prompt_ai_without_manage_options() {
		$allcaps = array( 'edit_posts' => true );
		$result  = WP_AI_Client_Capabilities::grant_prompt_ai_to_administrators( $allcaps );
		$this->assertArrayNotHasKey( 'prompt_ai', $result );
	}

	/**
	 * Test grant_list_ai_providers_models_to_administrators static method directly.
	 *
	 * @ticket TBD
	 */
	public function test_grant_list_providers_models_with_manage_options() {
		$allcaps = array( 'manage_options' => true );
		$result  = WP_AI_Client_Capabilities::grant_list_ai_providers_models_to_administrators( $allcaps );
		$this->assertTrue( $result['list_ai_providers'] );
		$this->assertTrue( $result['list_ai_models'] );
	}

	/**
	 * Test grant_list_ai_providers_models_to_administrators without manage_options.
	 *
	 * @ticket TBD
	 */
	public function test_grant_list_providers_models_without_manage_options() {
		$allcaps = array( 'edit_posts' => true );
		$result  = WP_AI_Client_Capabilities::grant_list_ai_providers_models_to_administrators( $allcaps );
		$this->assertArrayNotHasKey( 'list_ai_providers', $result );
		$this->assertArrayNotHasKey( 'list_ai_models', $result );
	}

	/**
	 * Test that removing the filter removes the capability.
	 *
	 * @ticket TBD
	 */
	public function test_removing_filter_removes_capability() {
		wp_set_current_user( self::$admin_user_id );

		// Verify capability exists.
		$this->assertTrue( current_user_can( WP_AI_Client_Capabilities::PROMPT_AI ) );

		// Remove the filter.
		remove_filter( 'user_has_cap', array( 'WP_AI_Client_Capabilities', 'grant_prompt_ai_to_administrators' ) );

		// Clear cached capabilities.
		wp_get_current_user()->allcaps = array();
		wp_get_current_user()->caps    = array();
		wp_get_current_user()->get_role_caps();

		$this->assertFalse( current_user_can( WP_AI_Client_Capabilities::PROMPT_AI ) );

		// Re-add the filter for other tests.
		add_filter( 'user_has_cap', array( 'WP_AI_Client_Capabilities', 'grant_prompt_ai_to_administrators' ) );
	}
}
