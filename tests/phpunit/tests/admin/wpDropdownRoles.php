<?php
/**
 * @group admin
 *
 * @covers ::wp_dropdown_roles
 */
class Tests_Admin_WpDropdownRoles extends WP_UnitTestCase {
	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	public static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/template.php';

		// get_editable_roles() depends on the current user's capabilities.
		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Extracts the role slug of every option marked as selected.
	 *
	 * @param string $output The output of wp_dropdown_roles().
	 * @return string[] Role slugs, in output order.
	 */
	private function get_selected_roles( $output ) {
		preg_match_all( "/<option selected='selected' value='([^']+)'>/", $output, $matches );

		return $matches[1];
	}

	/**
	 * @ticket 24972
	 */
	public function test_default_selected_should_not_select_any_option() {
		$output = get_echo( 'wp_dropdown_roles' );

		$this->assertSame( 0, substr_count( $output, "selected='selected'" ) );
		$this->assertSame( array(), $this->get_selected_roles( $output ) );
	}

	/**
	 * @ticket 24972
	 */
	public function test_empty_string_selected_should_not_select_any_option() {
		$output = get_echo( 'wp_dropdown_roles', array( '' ) );

		$this->assertSame( 0, substr_count( $output, "selected='selected'" ) );
		$this->assertSame( array(), $this->get_selected_roles( $output ) );
	}

	/**
	 * @ticket 24972
	 */
	public function test_string_selected_should_select_only_that_role() {
		$output = get_echo( 'wp_dropdown_roles', array( 'editor' ) );

		$this->assertSame( 1, substr_count( $output, "selected='selected'" ) );
		$this->assertSame( array( 'editor' ), $this->get_selected_roles( $output ) );
	}

	/**
	 * @ticket 24972
	 */
	public function test_string_selected_should_be_compared_strictly() {
		// A slug that does not exist must not match any option.
		$output = get_echo( 'wp_dropdown_roles', array( 'Editor' ) );

		$this->assertSame( 0, substr_count( $output, "selected='selected'" ) );
	}

	/**
	 * @ticket 24972
	 */
	public function test_string_selected_should_output_exact_markup_for_back_compat() {
		$output = get_echo( 'wp_dropdown_roles', array( 'editor' ) );

		$this->assertStringContainsString(
			"\n\t<option selected='selected' value='editor'>Editor</option>",
			$output
		);

		$unselected = get_echo( 'wp_dropdown_roles', array( 'subscriber' ) );

		$this->assertStringContainsString(
			"\n\t<option value='editor'>Editor</option>",
			$unselected
		);
	}

	/**
	 * @ticket 24972
	 */
	public function test_array_selected_should_select_every_matching_role() {
		$output = get_echo( 'wp_dropdown_roles', array( array( 'editor', 'subscriber' ) ) );

		$this->assertSame( 2, substr_count( $output, "selected='selected'" ) );
		$this->assertSame( array( 'subscriber', 'editor' ), $this->get_selected_roles( $output ) );
	}

	/**
	 * @ticket 24972
	 */
	public function test_array_selected_should_not_change_option_order() {
		$output = get_echo( 'wp_dropdown_roles', array( array( 'editor', 'subscriber' ) ) );

		// The order of the passed array must not influence the output order.
		$this->assertLessThan(
			strpos( $output, "value='editor'" ),
			strpos( $output, "value='subscriber'" )
		);
	}

	/**
	 * @ticket 24972
	 */
	public function test_array_selected_should_ignore_unknown_roles() {
		$output = get_echo( 'wp_dropdown_roles', array( array( 'editor', 'does_not_exist' ) ) );

		$this->assertSame( 1, substr_count( $output, "selected='selected'" ) );
		$this->assertSame( array( 'editor' ), $this->get_selected_roles( $output ) );
	}

	/**
	 * @ticket 24972
	 */
	public function test_array_selected_should_compare_values_strictly() {
		// Since role slugs are strings, an integer must not match a slug.
		$output = get_echo( 'wp_dropdown_roles', array( array( 'editor', 0, null, false ) ) );

		$this->assertSame( 1, substr_count( $output, "selected='selected'" ) );
		$this->assertSame( array( 'editor' ), $this->get_selected_roles( $output ) );
	}

	/**
	 * @ticket 24972
	 */
	public function test_array_selected_with_editable_roles_should_limit_output() {
		$editable_roles = get_editable_roles();
		$limited        = array(
			'editor' => $editable_roles['editor'],
			'author' => $editable_roles['author'],
		);

		$output = get_echo( 'wp_dropdown_roles', array( array( 'editor' ), $limited ) );

		$this->assertStringContainsString( "value='editor'", $output );
		$this->assertStringContainsString( "value='author'", $output );
		$this->assertStringNotContainsString( "value='subscriber'", $output );
		$this->assertSame( array( 'editor' ), $this->get_selected_roles( $output ) );
	}

	/**
	 * @ticket 24972
	 */
	public function test_array_selected_outside_editable_roles_should_not_be_selected() {
		$editable_roles = get_editable_roles();
		$limited        = array( 'author' => $editable_roles['author'] );

		$output = get_echo( 'wp_dropdown_roles', array( array( 'editor', 'author' ), $limited ) );

		$this->assertSame( array( 'author' ), $this->get_selected_roles( $output ) );
	}

	/**
	 * @ticket 24972
	 */
	public function test_empty_array_selected_should_not_select_any_option() {
		$output = get_echo( 'wp_dropdown_roles', array( array() ) );

		$this->assertSame( 0, substr_count( $output, "selected='selected'" ) );
		$this->assertSame( array(), $this->get_selected_roles( $output ) );
	}
}
