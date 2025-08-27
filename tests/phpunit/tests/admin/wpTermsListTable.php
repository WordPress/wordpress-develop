<?php

/**
 * @group admin
 *
 * @covers WP_Terms_List_Table
 */
class Tests_Admin_WpTermsListTable extends WP_UnitTestCase {

	/**
	 * List table.
	 *
	 * @var WP_Terms_List_Table $terms_list_table
	 */
	private $terms_list_table;

	private static $admin_id;
	private static $author_id;
	private static $term_object;

	const CATEGORY_TAXONOMY = 'category';

	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$admin_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::$author_id = self::factory()->user->create( array( 'role' => 'author' ) );

		self::$term_object = self::factory()->term->create_and_get( array( 'taxonomy' => self::CATEGORY_TAXONOMY ) );

		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-terms-list-table.php';
	}

	public function set_up() {
		parent::set_up();

		$this->terms_list_table = new WP_Terms_List_Table();
	}

	/**
	 * Call an inaccessible (private or protected) method.
	 *
	 * @param object|string $instance    Object instance or class string to call the method of.
	 * @param string        $method_name Name of the method to call.
	 * @param array         $args        Optional. Array of arguments to pass to the method.
	 * @return mixed Return value of the method call.
	 * @throws ReflectionException If the object could not be reflected upon.
	 */
	private function call_inaccessible_method( $instance, $method_name, $args = array() ) {
		$method = ( new ReflectionClass( $instance ) )->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $instance, $args );
	}

	/**
	 * @covers WP_Terms_List_Table::handle_row_actions()
	 *
	 * @ticket 59336
	 */
	public function test_handle_row_actions_as_author() {
		wp_set_current_user( self::$author_id );

		$actions = $this->call_inaccessible_method( $this->terms_list_table, 'handle_row_actions', array( self::$term_object, 'title', 'title' ) );

		$this->assertStringContainsString( '<div class="row-actions">', $actions, 'Row actions should be displayed.' );
		$this->assertStringContainsString( 'View', $actions, 'View action should be displayed to the author.' );
		$this->assertStringNotContainsString( 'Edit', $actions, 'Edit action should not be displayed to the author.' );
		$this->assertStringNotContainsString( 'Delete', $actions, 'Delete action should not be displayed to the author.' );
	}

	/**
	 * @covers WP_Terms_List_Table::handle_row_actions()
	 *
	 * @ticket 59336
	 */
	public function test_handle_row_actions_as_admin() {
		wp_set_current_user( self::$admin_id );

		$actions = $this->call_inaccessible_method( $this->terms_list_table, 'handle_row_actions', array( self::$term_object, 'title', 'title' ) );

		$this->assertStringContainsString( '<div class="row-actions">', $actions, 'Row actions should be displayed.' );
		$this->assertStringContainsString( 'View', $actions, 'View action should be displayed to the admin.' );
		$this->assertStringContainsString( 'Edit', $actions, 'Edit action should be displayed to the admin.' );
		$this->assertStringContainsString( 'Delete', $actions, 'Delete action should be displayed to the admin.' );
		$this->assertStringContainsString( admin_url( 'term.php' ), $actions, 'Edit term link should be displayed to the admin.' );
	}
}
