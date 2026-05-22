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
	 * Builds a terms list table bound to a specific taxonomy screen so that
	 * `$this->screen->taxonomy` resolves correctly inside the table methods.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return WP_Terms_List_Table
	 */
	private function get_terms_list_table_for_taxonomy( $taxonomy ) {
		return new WP_Terms_List_Table( array( 'screen' => 'edit-' . $taxonomy ) );
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
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
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

	/**
	 * @covers WP_Terms_List_Table::handle_row_actions()
	 *
	 * @ticket 26268
	 */
	public function test_change_default_action_shown_for_default_category_as_admin() {
		wp_set_current_user( self::$admin_id );

		$default_category = self::factory()->term->create_and_get( array( 'taxonomy' => self::CATEGORY_TAXONOMY ) );
		$previous_default = get_option( 'default_category' );
		update_option( 'default_category', $default_category->term_id );

		$list_table = $this->get_terms_list_table_for_taxonomy( self::CATEGORY_TAXONOMY );
		$actions    = $this->call_inaccessible_method( $list_table, 'handle_row_actions', array( $default_category, 'title', 'title' ) );

		update_option( 'default_category', $previous_default );

		$this->assertStringContainsString( 'change-default', $actions, 'Change Default action should be displayed for the default category.' );
		$this->assertStringContainsString( esc_url( admin_url( 'options-writing.php#default_category' ) ), $actions, 'Change Default link should target the Writing Settings field.' );
	}

	/**
	 * @covers WP_Terms_List_Table::handle_row_actions()
	 *
	 * @ticket 26268
	 */
	public function test_change_default_action_hidden_for_default_category_as_author() {
		wp_set_current_user( self::$author_id );

		$default_category = self::factory()->term->create_and_get( array( 'taxonomy' => self::CATEGORY_TAXONOMY ) );
		$previous_default = get_option( 'default_category' );
		update_option( 'default_category', $default_category->term_id );

		$list_table = $this->get_terms_list_table_for_taxonomy( self::CATEGORY_TAXONOMY );
		$actions    = $this->call_inaccessible_method( $list_table, 'handle_row_actions', array( $default_category, 'title', 'title' ) );

		update_option( 'default_category', $previous_default );

		$this->assertStringNotContainsString( 'change-default', $actions, 'Change Default action should be hidden for users without manage_options.' );
	}

	/**
	 * @covers WP_Terms_List_Table::handle_row_actions()
	 *
	 * @ticket 26268
	 */
	public function test_change_default_action_hidden_for_non_default_category() {
		wp_set_current_user( self::$admin_id );

		$default_category     = self::factory()->term->create_and_get( array( 'taxonomy' => self::CATEGORY_TAXONOMY ) );
		$non_default_category = self::factory()->term->create_and_get( array( 'taxonomy' => self::CATEGORY_TAXONOMY ) );
		$previous_default     = get_option( 'default_category' );
		update_option( 'default_category', $default_category->term_id );

		$list_table = $this->get_terms_list_table_for_taxonomy( self::CATEGORY_TAXONOMY );
		$actions    = $this->call_inaccessible_method( $list_table, 'handle_row_actions', array( $non_default_category, 'title', 'title' ) );

		update_option( 'default_category', $previous_default );

		$this->assertStringNotContainsString( 'change-default', $actions, 'Change Default action should only appear on the default category row.' );
	}

	/**
	 * @covers WP_Terms_List_Table::handle_row_actions()
	 *
	 * @ticket 26268
	 */
	public function test_change_default_action_hidden_for_non_category_taxonomy() {
		wp_set_current_user( self::$admin_id );

		$tag = self::factory()->term->create_and_get( array( 'taxonomy' => 'post_tag' ) );

		$list_table = $this->get_terms_list_table_for_taxonomy( 'post_tag' );
		$actions    = $this->call_inaccessible_method( $list_table, 'handle_row_actions', array( $tag, 'title', 'title' ) );

		$this->assertStringNotContainsString( 'change-default', $actions, 'Change Default action should not appear on non-category taxonomies.' );
	}

	/**
	 * @covers WP_Terms_List_Table::column_name()
	 *
	 * @ticket 26268
	 */
	public function test_column_name_shows_default_label_for_default_category() {
		wp_set_current_user( self::$admin_id );

		$default_category = self::factory()->term->create_and_get( array( 'taxonomy' => self::CATEGORY_TAXONOMY ) );
		$previous_default = get_option( 'default_category' );
		update_option( 'default_category', $default_category->term_id );

		$list_table = $this->get_terms_list_table_for_taxonomy( self::CATEGORY_TAXONOMY );
		$output     = $list_table->column_name( $default_category );

		update_option( 'default_category', $previous_default );

		$this->assertStringContainsString( 'taxonomy-default-label', $output, 'Default label markup should be rendered for the default category.' );
	}

	/**
	 * @covers WP_Terms_List_Table::column_name()
	 *
	 * @ticket 26268
	 */
	public function test_column_name_shows_default_label_for_default_term_custom_taxonomy() {
		wp_set_current_user( self::$admin_id );

		register_taxonomy( 'wptest_default_label_tax', 'post' );
		$default_term = self::factory()->term->create_and_get( array( 'taxonomy' => 'wptest_default_label_tax' ) );
		update_option( 'default_term_wptest_default_label_tax', $default_term->term_id );

		$list_table = $this->get_terms_list_table_for_taxonomy( 'wptest_default_label_tax' );
		$output     = $list_table->column_name( $default_term );

		delete_option( 'default_term_wptest_default_label_tax' );
		unregister_taxonomy( 'wptest_default_label_tax' );

		$this->assertStringContainsString( 'taxonomy-default-label', $output, 'Default label should appear for terms identified by default_term_{taxonomy}.' );
	}

	/**
	 * @covers WP_Terms_List_Table::column_name()
	 *
	 * @ticket 26268
	 */
	public function test_column_name_omits_default_label_for_non_default_term() {
		wp_set_current_user( self::$admin_id );

		$non_default_category = self::factory()->term->create_and_get( array( 'taxonomy' => self::CATEGORY_TAXONOMY ) );

		$list_table = $this->get_terms_list_table_for_taxonomy( self::CATEGORY_TAXONOMY );
		$output     = $list_table->column_name( $non_default_category );

		$this->assertStringNotContainsString( 'taxonomy-default-label', $output, 'Default label should not appear on non-default terms.' );
	}
}
