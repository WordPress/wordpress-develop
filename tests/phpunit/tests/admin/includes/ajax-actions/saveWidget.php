<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_save_widget() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 * @group widgets
 *
 * @covers ::wp_ajax_save_widget
 */
class Tests_Admin_Includes_AjaxActions_saveWidget extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Original widget registration globals.
	 *
	 * @var array
	 */
	private $widget_globals_backup = array();

	/**
	 * Text widget used by the test.
	 *
	 * A new instance is used for each test, rather than the shared widget factory's,
	 * because other tests empty the factory and update_callback() only runs once per instance.
	 *
	 * @var WP_Widget_Text
	 */
	private $text_widget;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'wp_registered_widgets', 'wp_registered_widget_controls', 'wp_registered_widget_updates' ) as $global ) {
			$this->widget_globals_backup[ $global ] = $GLOBALS[ $global ];
		}

		update_option(
			'widget_text',
			array(
				2              => array(
					'title' => 'Old title',
					'text'  => 'Old text',
				),
				3              => array(
					'title' => 'Other title',
					'text'  => 'Other text',
				),
				'_multiwidget' => 1,
			)
		);
		// wp_register_widget_control() keeps an existing update callback, so remove the shared one first.
		unset( $GLOBALS['wp_registered_widget_updates']['text'] );
		$this->text_widget = new WP_Widget_Text();
		$this->text_widget->_register();

		wp_set_sidebars_widgets( array( 'sidebar-1' => array( 'text-2', 'text-3' ) ) );

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Tear down after each test method.
	 */
	public function tear_down(): void {
		foreach ( $this->widget_globals_backup as $global => $value ) {
			$GLOBALS[ $global ] = $value;
		}

		parent::tear_down();
	}

	/**
	 * Returns the Text widget's saved instances.
	 *
	 * @return array
	 */
	private function get_text_instances(): array {
		$instances = get_option( 'widget_text' );
		unset( $instances['_multiwidget'] );

		return $instances;
	}

	/**
	 * Returns the error the handler prints for an invalid request.
	 *
	 * @return string
	 */
	private function get_error_message(): string {
		return '<p>' . __( 'An error has occurred. Please reload the page and try again.' ) . '</p>';
	}

	/**
	 * Returns a valid request that updates the text-2 widget.
	 *
	 * @return array
	 */
	private function get_update_request(): array {
		return array(
			'savewidgets' => wp_create_nonce( 'save-sidebar-widgets' ),
			'sidebar'     => 'sidebar-1',
			'widget-id'   => 'text-2',
			'id_base'     => 'text',
			'widget-text' => array(
				2 => array(
					'title' => 'New title',
					'text'  => 'New text',
				),
			),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_save_widget_updates_widget_and_returns_form(): void {
		$_POST = $this->get_update_request();

		$response = $this->get_ajax_response( 'save-widget' );

		$this->assertStringContainsString( 'name="widget-text[2][title]"', $response, 'The widget form should be returned.' );
		$this->assertStringContainsString( 'value="New title"', $response );
		$this->assertSame( 'New title', $this->get_text_instances()[2]['title'] );
		$this->assertSame( 'Other title', $this->get_text_instances()[3]['title'] );
	}

	/**
	 * @ticket 65252
	 */
	public function test_save_widget_deletes_widget(): void {
		$action = new MockAction();
		add_action( 'delete_widget', array( $action, 'action' ), 10, 3 );

		$_POST = array(
			'savewidgets'   => wp_create_nonce( 'save-sidebar-widgets' ),
			'sidebar'       => 'sidebar-1',
			'widget-id'     => 'text-2',
			'id_base'       => 'text',
			'delete_widget' => '1',
		);

		$this->assertSame( 'deleted:text-2', $this->get_ajax_response( 'save-widget' ) );
		$this->assertSame( array( 'text-3' ), array_values( wp_get_sidebars_widgets()['sidebar-1'] ) );
		$this->assertSame( array( 3 ), array_keys( $this->get_text_instances() ), 'The widget settings should be deleted.' );
		$this->assertSame( array( array( 'text-2', 'sidebar-1', 'text' ) ), $action->get_args() );
	}

	/**
	 * @ticket 65252
	 */
	public function test_save_widget_rejects_deleting_unregistered_widget(): void {
		$_POST = array(
			'savewidgets'   => wp_create_nonce( 'save-sidebar-widgets' ),
			'sidebar'       => 'sidebar-1',
			'widget-id'     => 'text-99',
			'id_base'       => 'text',
			'delete_widget' => '1',
		);

		$this->assertSame( $this->get_error_message(), $this->get_ajax_response( 'save-widget' ) );
		$this->assertSame( array( 'text-2', 'text-3' ), wp_get_sidebars_widgets()['sidebar-1'] );
		$this->assertSame( array( 2, 3 ), array_keys( $this->get_text_instances() ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_save_widget_adds_new_multi_widget(): void {
		$_POST = array(
			'savewidgets'  => wp_create_nonce( 'save-sidebar-widgets' ),
			'sidebar'      => 'sidebar-1',
			'widget-id'    => 'text-__i__',
			'id_base'      => 'text',
			'multi_number' => '4',
			'add_new'      => 'multi',
			'widget-text'  => array(
				'__i__' => array(
					'title' => 'Added title',
					'text'  => 'Added text',
				),
			),
		);

		$this->assertSame( '', $this->get_ajax_response( 'save-widget' ), 'Adding a widget should not return its form.' );
		$this->assertSame( array( 2, 3, 4 ), array_keys( $this->get_text_instances() ), 'The placeholder should be saved as the multi number.' );
		$this->assertSame( 'Added title', $this->get_text_instances()[4]['title'] );
	}

	/**
	 * @ticket 65252
	 */
	public function test_save_widget_rejects_new_widget_without_multi_number(): void {
		$_POST = array(
			'savewidgets' => wp_create_nonce( 'save-sidebar-widgets' ),
			'sidebar'     => 'sidebar-1',
			'widget-id'   => 'text-__i__',
			'id_base'     => 'text',
			'add_new'     => 'multi',
			'widget-text' => array(
				'__i__' => array( 'title' => 'Added title' ),
			),
		);

		$this->assertSame( $this->get_error_message(), $this->get_ajax_response( 'save-widget' ) );
		$this->assertSame( array( 2, 3 ), array_keys( $this->get_text_instances() ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_save_widget_rejects_invalid_nonce(): void {
		$_POST                = $this->get_update_request();
		$_POST['savewidgets'] = 'invalid-nonce';

		$this->assertSame( '-1', $this->get_ajax_response( 'save-widget' ) );
		$this->assertSame( 'Old title', $this->get_text_instances()[2]['title'] );
	}

	/**
	 * @ticket 65252
	 *
	 * @dataProvider data_roles_without_edit_theme_options
	 *
	 * @param string $role User role.
	 */
	public function test_save_widget_rejects_user_without_edit_theme_options( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

		$_POST = $this->get_update_request();

		$this->assertSame( '-1', $this->get_ajax_response( 'save-widget' ) );
		$this->assertSame( 'Old title', $this->get_text_instances()[2]['title'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }>
	 */
	public static function data_roles_without_edit_theme_options(): array {
		return array(
			'editor'     => array( 'editor' ),
			'subscriber' => array( 'subscriber' ),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_save_widget_rejects_missing_id_base(): void {
		$_POST = $this->get_update_request();
		unset( $_POST['id_base'] );

		$this->assertSame( '-1', $this->get_ajax_response( 'save-widget' ) );
		$this->assertSame( 'Old title', $this->get_text_instances()[2]['title'] );
	}
}
