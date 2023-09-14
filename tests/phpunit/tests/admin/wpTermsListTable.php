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
	 * Call a private method as if it was public.
	 *
	 * @param object|string $object      Object instance or class string to call the method of.
	 * @param string        $method_name Name of the method to call.
	 * @param array         $args        Optional. Array of arguments to pass to the method.
	 * @return mixed Return value of the method call.
	 * @throws ReflectionException If the object could not be reflected upon.
	 */
	private function call_private_method( $object, $method_name, $args = array() ) {
		$method = ( new ReflectionClass( $object ) )->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $object, $args );
	}

	/**
	 * This test proves the existence and reproducibility of the deprecation warnings
	 * caused by passing null as an argument to `add_query_arg()`
	 *
	 * @ticket 59336
	 *
	 * @covers WP_Terms_List_Table::handle_row_actions()
	 */
	public function test_handle_row_actions_should_generate_deprecation_notice() {
		if ( ! PHP_VERSION_ID >= 80100 ) {
			$this->markTestSkipped( 'This test requires PHP 8.1 or higher.' );
		}

		wp_set_current_user( self::$admin_id );

		$edit_link = add_query_arg(
			'wp_http_referer',
			admin_url( 'index.php' ),
			get_edit_term_link( self::$term_object, self::CATEGORY_TAXONOMY, 'post' )
		);

		$this->assertStringContainsString( admin_url( 'term.php' ), $edit_link );

		wp_set_current_user( self::$author_id );

		set_error_handler(
			static function ( $errno, $errstr ) {
				throw new ErrorException( $errstr, $errno );
			},
			E_ALL
		);

		$this->expectException( ErrorException::class );
		$this->expectExceptionMessageMatches( '/^strstr\(\): Passing null to parameter #1 \(\$haystack\) of type string is deprecated/' );

		$edit_link = add_query_arg(
			'wp_http_referer',
			admin_url( 'index.php' ),
			get_edit_term_link( self::$term_object, self::CATEGORY_TAXONOMY, 'post' )
		);

		$this->assertStringNotContainsString( admin_url( 'term.php' ), $edit_link );

		restore_error_handler();
	}

	/**
	 * @covers WP_Terms_List_Table::handle_row_actions()
	 */
	public function test_handle_row_actions() {
		wp_set_current_user( self::$author_id );

		$actions = $this->call_private_method( $this->terms_list_table, 'handle_row_actions', array( self::$term_object, 'title', 'title' ) );

		$this->assertStringContainsString( '<div class="row-actions">', $actions );
		$this->assertStringContainsString( 'View', $actions );
		$this->assertStringNotContainsString( 'Edit', $actions );
		$this->assertStringNotContainsString( 'Delete', $actions );

		wp_set_current_user( self::$admin_id );

		$actions = $this->call_private_method( $this->terms_list_table, 'handle_row_actions', array( self::$term_object, 'title', 'title' ) );

		$this->assertStringContainsString( '<div class="row-actions">', $actions );
		$this->assertStringContainsString( 'View', $actions );
		$this->assertStringContainsString( 'Edit', $actions );
		$this->assertStringContainsString( 'Delete', $actions );
		$this->assertStringContainsString( admin_url( 'term.php' ), $actions );
	}
}
