<?php
/**
 * Tests for the `new_user_email_admin_notice()` function.
 *
 * @group user
 *
 * @covers ::new_user_email_admin_notice
 */
class Tests_User_NewUserEmailAdminNotice extends WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Original value of the `$pagenow` global.
	 *
	 * @var string|null
	 */
	private $original_pagenow;

	/**
	 * Creates a test user.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'before@example.com',
			)
		);
	}

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_pagenow = $GLOBALS['pagenow'] ?? null;
		wp_set_current_user( self::$user_id );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		$GLOBALS['pagenow'] = $this->original_pagenow;
		unset( $_GET['updated'] );

		parent::tear_down();
	}

	/**
	 * Stores a pending email change for the test user.
	 */
	private function set_pending_email_change() {
		update_user_meta(
			self::$user_id,
			'_new_email',
			array(
				'hash'     => md5( 'after@example.com' ),
				'newemail' => 'after@example.com',
			)
		);
	}

	/**
	 * Tests that the notice is displayed when an email change is pending.
	 *
	 * @ticket 66173
	 */
	public function test_should_display_notice_when_email_change_is_pending() {
		$GLOBALS['pagenow'] = 'profile.php';
		$this->set_pending_email_change();

		$output = get_echo( 'new_user_email_admin_notice' );

		$this->assertStringContainsString( 'notice-info', $output, 'The notice should be of type "info".' );
		$this->assertStringContainsString( '<code>after@example.com</code>', $output, 'The notice should contain the new email address.' );
	}

	/**
	 * Tests that the notice is displayed regardless of the "updated" query arg.
	 *
	 * @ticket 66173
	 */
	public function test_should_display_notice_regardless_of_updated_query_arg() {
		$GLOBALS['pagenow'] = 'profile.php';
		$this->set_pending_email_change();

		unset( $_GET['updated'] );
		$this->assertNotEmpty( get_echo( 'new_user_email_admin_notice' ), 'The notice should display when the "updated" query arg is absent.' );

		$_GET['updated'] = 'true';
		$this->assertNotEmpty( get_echo( 'new_user_email_admin_notice' ), 'The notice should display when the "updated" query arg is present.' );
	}

	/**
	 * Tests that the notice includes a nonced link to cancel the email change request.
	 *
	 * @ticket 66173
	 */
	public function test_should_include_cancel_request_link() {
		$GLOBALS['pagenow'] = 'profile.php';
		$this->set_pending_email_change();

		$output = get_echo( 'new_user_email_admin_notice' );

		$this->assertStringContainsString( 'dismiss=' . self::$user_id . '_new_email', $output, 'The notice should link to the dismiss action.' );
		$this->assertStringContainsString( '_wpnonce=', $output, 'The cancel link should be nonced.' );
		$this->assertStringContainsString( 'Cancel request', $output, 'The cancel link text should be "Cancel request".' );
	}

	/**
	 * Tests that the notice is not displayed when no email change is pending.
	 *
	 * @ticket 66173
	 */
	public function test_should_not_display_notice_without_pending_email_change() {
		$GLOBALS['pagenow'] = 'profile.php';

		$this->assertSame( '', get_echo( 'new_user_email_admin_notice' ) );
	}

	/**
	 * Tests that the notice is not displayed outside the profile screen.
	 *
	 * @ticket 66173
	 */
	public function test_should_not_display_notice_outside_profile_screen() {
		$GLOBALS['pagenow'] = 'index.php';
		$this->set_pending_email_change();

		$this->assertSame( '', get_echo( 'new_user_email_admin_notice' ) );
	}

	/**
	 * Tests that the notice is not hooked to multisite admin notice actions.
	 *
	 * Ensures the notice is not output a second time through the multisite
	 * admin notice actions, since wp-admin/user-edit.php renders it directly.
	 *
	 * @ticket 66173
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_multisite_admin_notice_actions
	 *
	 * @param string $hook_name Admin notice action name.
	 */
	public function test_should_not_be_hooked_to_multisite_admin_notice_actions( $hook_name ) {
		require_once ABSPATH . 'wp-admin/includes/ms-admin-filters.php';

		$this->assertFalse( has_action( $hook_name, 'new_user_email_admin_notice' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_multisite_admin_notice_actions() {
		return array(
			'user_admin_notices'    => array( 'user_admin_notices' ),
			'network_admin_notices' => array( 'network_admin_notices' ),
		);
	}
}
