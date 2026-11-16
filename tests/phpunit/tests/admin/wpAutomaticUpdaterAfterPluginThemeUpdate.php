<?php

/**
 * Tests for WP_Automatic_Updater::after_plugin_theme_update().
 *
 * @group admin
 * @group upgrade
 * @group plugins
 *
 * @covers WP_Automatic_Updater::after_plugin_theme_update
 */
class Tests_Admin_WpAutomaticUpdaterAfterPluginThemeUpdate extends WP_UnitTestCase {

	/**
	 * Loads the automatic updater class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php';
	}

	/**
	 * Tests that no email is sent without plugin or theme results.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_no_plugin_or_theme_results
	 *
	 * @param array $update_results The update results.
	 */
	public function test_should_not_send_email_without_plugin_or_theme_results( $update_results ) {
		$filter = new MockAction();
		add_filter( 'auto_plugin_update_send_email', array( $filter, 'filter' ) );
		add_filter( 'auto_theme_update_send_email', array( $filter, 'filter' ) );

		$updater = $this->get_updater();
		$updater->expects( $this->never() )->method( 'send_plugin_theme_email' );

		$this->call( $updater, $update_results );

		$this->assertSame( 0, $filter->get_call_count(), 'The email filters should not run without results.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_plugin_or_theme_results() {
		return array(
			'no results'                     => array( array() ),
			'empty plugin and theme results' => array(
				array(
					'plugin' => array(),
					'theme'  => array(),
				),
			),
			'core and translation results'   => array(
				array(
					'core'        => array( (object) array( 'result' => true ) ),
					'translation' => array( (object) array( 'result' => true ) ),
				),
			),
		);
	}

	/**
	 * Tests the email type and the updates passed to it.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_email_types
	 *
	 * @param array  $results    The plugin and theme results, as result values keyed by type.
	 * @param string $type       The expected email type.
	 * @param array  $successful The expected successful results, as indexes keyed by type.
	 * @param array  $failed     The expected failed results, as indexes keyed by type.
	 */
	public function test_should_send_email_for_results( $results, $type, $successful, $failed ) {
		$update_results = array();
		foreach ( $results as $result_type => $values ) {
			foreach ( $values as $value ) {
				$update_results[ $result_type ][] = (object) array(
					'result' => $value,
					'name'   => "{$result_type} " . count( $update_results[ $result_type ] ?? array() ),
				);
			}
		}

		$updater = $this->get_updater();
		$updater->expects( $this->once() )
			->method( 'send_plugin_theme_email' )
			->with( $type, $this->pick( $update_results, $successful ), $this->pick( $update_results, $failed ) );

		$this->call( $updater, $update_results );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_email_types() {
		return array(
			'plugin succeeded'               => array(
				array( 'plugin' => array( true ) ),
				'success',
				array( 'plugin' => array( 0 ) ),
				array(),
			),
			'theme succeeded'                => array(
				array( 'theme' => array( true ) ),
				'success',
				array( 'theme' => array( 0 ) ),
				array(),
			),
			'plugin failed with an error'    => array(
				array( 'plugin' => array( new WP_Error( 'update_failed', 'Failed.' ) ) ),
				'fail',
				array(),
				array( 'plugin' => array( 0 ) ),
			),
			'plugin result false'            => array(
				array( 'plugin' => array( false ) ),
				'fail',
				array(),
				array( 'plugin' => array( 0 ) ),
			),
			'plugin result null'             => array(
				array( 'plugin' => array( null ) ),
				'fail',
				array(),
				array( 'plugin' => array( 0 ) ),
			),
			'plugin result truthy, not true' => array(
				array( 'plugin' => array( 1 ) ),
				'fail',
				array(),
				array( 'plugin' => array( 0 ) ),
			),
			'theme result truthy, not true'  => array(
				array( 'theme' => array( 1 ) ),
				'fail',
				array(),
				array( 'theme' => array( 0 ) ),
			),
			'theme failed'                   => array(
				array( 'theme' => array( false ) ),
				'fail',
				array(),
				array( 'theme' => array( 0 ) ),
			),
			'one plugin of two failed'       => array(
				array( 'plugin' => array( true, false ) ),
				'mixed',
				array( 'plugin' => array( 0 ) ),
				array( 'plugin' => array( 1 ) ),
			),
			'plugin succeeded, theme failed' => array(
				array(
					'plugin' => array( true ),
					'theme'  => array( false ),
				),
				'mixed',
				array( 'plugin' => array( 0 ) ),
				array( 'theme' => array( 0 ) ),
			),
			'all plugins and themes updated' => array(
				array(
					'plugin' => array( true, true ),
					'theme'  => array( true ),
				),
				'success',
				array(
					'plugin' => array( 0, 1 ),
					'theme'  => array( 0 ),
				),
				array(),
			),
		);
	}

	/**
	 * Tests that the email filters can leave out plugin or theme results.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_disabled_notifications
	 *
	 * @param string $disabled The type whose email filter returns false.
	 * @param string $kept     The type that is still emailed.
	 */
	public function test_should_leave_out_results_when_email_is_disabled_for_type( $disabled, $kept ) {
		$update_results = array(
			'plugin' => array( (object) array( 'result' => true ) ),
			'theme'  => array( (object) array( 'result' => false ) ),
		);

		$filter = new MockAction();
		add_filter( "auto_{$disabled}_update_send_email", array( $filter, 'filter' ), 10, 2 );
		add_filter( "auto_{$disabled}_update_send_email", '__return_false', 11 );

		$expected = array( $kept => $update_results[ $kept ] );

		$updater = $this->get_updater();
		$updater->expects( $this->once() )
			->method( 'send_plugin_theme_email' )
			->with(
				'plugin' === $kept ? 'success' : 'fail',
				'plugin' === $kept ? $expected : array(),
				'plugin' === $kept ? array() : $expected
			);

		$this->call( $updater, $update_results );

		$this->assertSame( array( array( true, $update_results[ $disabled ] ) ), $filter->get_args(), 'The filter should receive true and the results of its type.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_disabled_notifications() {
		return array(
			'plugin emails disabled' => array( 'plugin', 'theme' ),
			'theme emails disabled'  => array( 'theme', 'plugin' ),
		);
	}

	/**
	 * Tests that no email is sent when both email filters return false.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_send_email_when_both_types_are_disabled() {
		add_filter( 'auto_plugin_update_send_email', '__return_false' );
		add_filter( 'auto_theme_update_send_email', '__return_false' );

		$updater = $this->get_updater();
		$updater->expects( $this->never() )->method( 'send_plugin_theme_email' );

		$this->call(
			$updater,
			array(
				'plugin' => array( (object) array( 'result' => true ) ),
				'theme'  => array( (object) array( 'result' => true ) ),
			)
		);
	}

	/**
	 * Returns an updater whose email sending is mocked.
	 *
	 * @return WP_Automatic_Updater&PHPUnit\Framework\MockObject\MockObject The updater.
	 */
	private function get_updater() {
		return $this->getMockBuilder( 'WP_Automatic_Updater' )
			// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
			->setMethods( array( 'send_plugin_theme_email' ) )
			->getMock();
	}

	/**
	 * Calls the protected after_plugin_theme_update() method.
	 *
	 * @param WP_Automatic_Updater $updater        The updater.
	 * @param array                $update_results The update results.
	 */
	private function call( $updater, $update_results ) {
		$method = new ReflectionMethod( 'WP_Automatic_Updater', 'after_plugin_theme_update' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$method->invoke( $updater, $update_results );
	}

	/**
	 * Picks results by index.
	 *
	 * @param array $update_results The update results, keyed by type.
	 * @param array $indexes        The indexes to pick, keyed by type.
	 * @return array The picked results, keyed by type.
	 */
	private function pick( $update_results, $indexes ) {
		$picked = array();
		foreach ( $indexes as $type => $type_indexes ) {
			foreach ( $type_indexes as $index ) {
				$picked[ $type ][] = $update_results[ $type ][ $index ];
			}
		}

		return $picked;
	}
}
