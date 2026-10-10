<?php

/**
 * @group admin
 * @group bookmark
 *
 * @covers ::wp_link_manager_disabled_message
 */
class Tests_Admin_Includes_Bookmark_WpLinkManagerDisabledMessage_Test extends WP_UnitTestCase {

	/**
	 * User ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * User ID of a subscriber.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * The value of $pagenow before the test ran.
	 *
	 * @var string
	 */
	private $original_pagenow;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function set_up() {
		parent::set_up();

		$this->original_pagenow = $GLOBALS['pagenow'];
	}

	public function tear_down() {
		$GLOBALS['pagenow'] = $this->original_pagenow;

		parent::tear_down();
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_do_nothing_outside_the_link_manager_screens() {
		$GLOBALS['pagenow'] = 'index.php';
		wp_set_current_user( self::$subscriber_id );

		$this->assertNull( wp_link_manager_disabled_message() );
	}

	/**
	 * @ticket 66019
	 *
	 * @dataProvider data_link_manager_screens
	 *
	 * @param string $pagenow The screen to test.
	 */
	public function test_should_die_for_users_who_cannot_manage_links( $pagenow ) {
		$GLOBALS['pagenow'] = $pagenow;
		wp_set_current_user( self::$subscriber_id );

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'Sorry, you are not allowed to edit the links for this site.' );

		wp_link_manager_disabled_message();
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{pagenow: string}>
	 */
	public function data_link_manager_screens() {
		return array(
			'the link manager' => array( 'pagenow' => 'link-manager.php' ),
			'adding a link'    => array( 'pagenow' => 'link-add.php' ),
			'editing a link'   => array( 'pagenow' => 'link.php' ),
		);
	}

	/**
	 * @ticket 66019
	 *
	 * @group ms-excluded
	 */
	public function test_should_offer_to_install_the_plugin_when_it_is_not_installed() {
		$GLOBALS['pagenow'] = 'link-manager.php';
		wp_set_current_user( self::$admin_id );
		wp_cache_set( 'plugins', array( '' => array() ), 'plugins' );

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'action=install-plugin' );

		wp_link_manager_disabled_message();
	}

	/**
	 * @ticket 66019
	 *
	 * @group ms-excluded
	 */
	public function test_should_offer_to_activate_the_plugin_when_it_is_inactive() {
		$GLOBALS['pagenow'] = 'link-manager.php';
		wp_set_current_user( self::$admin_id );
		wp_cache_set(
			'plugins',
			array( '' => array( 'link-manager/link-manager.php' => array( 'Name' => 'Link Manager' ) ) ),
			'plugins'
		);

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'action=activate' );

		wp_link_manager_disabled_message();
	}
}
