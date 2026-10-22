<?php
/**
 * @group customize
 * @group themes
 *
 * @covers ::_wp_customize_include
 */
class Tests_Functions_wpCustomizeInclude extends WP_UnitTestCase {
	protected static $admin_id;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * @ticket 54160
	 * @dataProvider data__wp_customize_include
	 *
	 * @param mixed  $uuid      The UUID to use.
	 * @param string $input_var The input var to use.
	 * @param bool The expected result.
	 */
	public function test__wp_customize_include( $uuid, $input_var, $expected ) {
		global $wp_customize;

		$this->go_to( admin_url( "/customize.php/?$input_var=$uuid" ) );
		wp_set_current_user( self::$admin_id );

		$wp_customize = new WP_Customize_Manager(
			array(
				'changeset_uuid' => $uuid,
			)
		);
		$wp_customize->start_previewing_theme();

		$wp_admin_bar = $this->get_standard_admin_bar();
		$node         = $wp_admin_bar->get_node( 'customize' );
		$this->assertNotEmpty( $node );

		$parsed_url   = wp_parse_url( $node->href );
		$query_params = array();
		wp_parse_str( $parsed_url['query'], $query_params );
		$this->assertSame( $expected, ( $uuid === $query_params['changeset_uuid'] ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data__wp_customize_include() {
		return array(
			'a valid changeset_uuid'            => array(
				'uuid'      => wp_generate_uuid4(),
				'input_var' => 'changeset_uuid',
				'expected'  => true,
			),
			'an invalid changeset_uuid'         => array(
				'uuid'      => 12345678,
				'input_var' => 'changeset_uuid',
				'expected'  => false,
			),
			'a valid customize_changeset_uuid'  => array(
				'uuid'      => wp_generate_uuid4(),
				'input_var' => 'customize_changeset_uuid',
				'expected'  => true,
			),
			'an empty customize_changeset_uuid' => array(
				'uuid'      => '',
				'input_var' => 'customize_changeset_uuid',
				'expected'  => false,
			),
		);
	}

	/**
	 * Helper method.
	 *
	 * @return mixed The admin bar object. See ::_wp_admin_bar_init().
	 */
	protected function get_standard_admin_bar() {
		global $wp_admin_bar;
		_wp_admin_bar_init();
		do_action_ref_array( 'admin_bar_menu', array( &$wp_admin_bar ) );
		return $wp_admin_bar;
	}
}
