<?php

/**
 * Tests for wp_convert_widget_settings().
 *
 * @group widgets
 *
 * @covers ::wp_convert_widget_settings
 */
class Tests_Widgets_wpConvertWidgetSettings extends WP_UnitTestCase {

	/**
	 * Whether the `$_wp_sidebars_widgets` global was set before the test.
	 *
	 * @var bool
	 */
	private $had_sidebars_widgets;

	/**
	 * Original value of the `$_wp_sidebars_widgets` global.
	 *
	 * @var mixed
	 */
	private $orig_sidebars_widgets;

	/**
	 * Sidebars used by the tests.
	 *
	 * @var array
	 */
	const SIDEBARS = array(
		'wp_inactive_widgets' => array(),
		'sidebar-1'           => array( 'archives-3', 'search', 'search' ),
		'sidebar-2'           => array( 'search', 'meta-2' ),
		'array_version'       => 3,
	);

	public function set_up() {
		parent::set_up();

		$this->had_sidebars_widgets  = array_key_exists( '_wp_sidebars_widgets', $GLOBALS );
		$this->orig_sidebars_widgets = $this->had_sidebars_widgets ? $GLOBALS['_wp_sidebars_widgets'] : null;

		unset( $GLOBALS['_wp_sidebars_widgets'] );

		update_option( 'sidebars_widgets', self::SIDEBARS );
		delete_option( 'widget_search' );
	}

	public function tear_down() {
		if ( $this->had_sidebars_widgets ) {
			$GLOBALS['_wp_sidebars_widgets'] = $this->orig_sidebars_widgets;
		} else {
			unset( $GLOBALS['_wp_sidebars_widgets'] );
		}

		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * Tests that settings already in the multi-widget format are only marked as such.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_multi_widget_settings
	 *
	 * @param array $settings Widget settings.
	 */
	public function test_keeps_multi_widget_settings( $settings ) {
		$this->assertSame( $settings + array( '_multiwidget' => 1 ), wp_convert_widget_settings( 'search', 'widget_search', $settings ) );
		$this->assertSame( self::SIDEBARS, get_option( 'sidebars_widgets' ), 'The sidebars should not change.' );
		$this->assertArrayNotHasKey( '_wp_sidebars_widgets', $GLOBALS, 'The sidebars should not be loaded into memory.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_multi_widget_settings() {
		return array(
			'numbered instances'            => array(
				array(
					2 => array( 'title' => 'First' ),
					3 => array( 'title' => 'Second' ),
				),
			),
			'numbered instances and number' => array(
				array(
					'number' => 2,
					2        => array( 'title' => 'First' ),
				),
			),
			'numeric string keys'           => array(
				array(
					'05' => array( 'title' => 'Fifth' ),
				),
			),
			'only a number'                 => array( array( 'number' => 2 ) ),
		);
	}

	/**
	 * Tests that single-widget settings are converted on the front end without saving anything.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_single_widget_settings
	 *
	 * @param mixed $settings Widget settings.
	 */
	public function test_converts_single_widget_settings_on_front_end( $settings ) {
		$this->assertSame(
			array(
				2              => $settings,
				'_multiwidget' => 1,
			),
			wp_convert_widget_settings( 'search', 'widget_search', $settings ),
			'The settings should become the instance with number 2.'
		);

		$expected_sidebars                 = self::SIDEBARS;
		$expected_sidebars['sidebar-1'][1] = 'search-2';

		$this->assertSame( $expected_sidebars, $GLOBALS['_wp_sidebars_widgets'], 'Only the first widget should be renamed in memory.' );
		$this->assertSame( self::SIDEBARS, get_option( 'sidebars_widgets' ), 'The sidebars should not be saved.' );
		$this->assertFalse( get_option( 'widget_search' ), 'The settings should not be saved.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_single_widget_settings() {
		return array(
			'single widget settings' => array( array( 'title' => 'Search' ) ),
			'mixed keys'             => array(
				array(
					2       => array( 'title' => 'First' ),
					'title' => 'Search',
				),
			),
			'empty array'            => array( array() ),
			'null'                   => array( null ),
		);
	}

	/**
	 * Tests that the sidebars already in memory are updated on the front end.
	 *
	 * @ticket 65819
	 */
	public function test_updates_sidebars_in_memory_on_front_end() {
		$GLOBALS['_wp_sidebars_widgets'] = array(
			'sidebar-3' => array( 'search' ),
		);

		wp_convert_widget_settings( 'search', 'widget_search', array( 'title' => 'Search' ) );

		$this->assertSame( array( 'sidebar-3' => array( 'search-2' ) ), $GLOBALS['_wp_sidebars_widgets'] );
	}

	/**
	 * Tests that single-widget settings and sidebars are saved in the admin.
	 *
	 * @ticket 65819
	 */
	public function test_converts_and_saves_single_widget_settings_in_admin() {
		set_current_screen( 'widgets' );

		$expected = array(
			2              => array( 'title' => 'Search' ),
			'_multiwidget' => 1,
		);

		$this->assertSame( $expected, wp_convert_widget_settings( 'search', 'widget_search', array( 'title' => 'Search' ) ) );

		$expected_sidebars                 = self::SIDEBARS;
		$expected_sidebars['sidebar-1'][1] = 'search-2';

		$this->assertSame( $expected_sidebars, get_option( 'sidebars_widgets' ), 'The renamed widget should be saved.' );
		$this->assertSame( $expected, get_option( 'widget_search' ), 'The converted settings should be saved.' );
		$this->assertArrayNotHasKey( '_wp_sidebars_widgets', $GLOBALS, 'The sidebars in memory should not be used.' );
	}

	/**
	 * Tests that the sidebars are not saved in the admin when no widget was renamed.
	 *
	 * @ticket 65819
	 */
	public function test_does_not_save_sidebars_in_admin_without_changes() {
		set_current_screen( 'widgets' );

		$filter = new MockAction();
		add_filter( 'pre_update_option_sidebars_widgets', array( $filter, 'filter' ) );

		$result = wp_convert_widget_settings( 'calendar', 'widget_calendar', array( 'title' => 'Calendar' ) );

		$this->assertSame(
			array(
				2              => array( 'title' => 'Calendar' ),
				'_multiwidget' => 1,
			),
			$result,
			'The settings should be converted.'
		);
		$this->assertSame( 0, $filter->get_call_count(), 'The sidebars should not be saved.' );
		$this->assertSame( $result, get_option( 'widget_calendar' ), 'The converted settings should be saved.' );
	}

	/**
	 * Tests that multi-widget settings are saved in the admin.
	 *
	 * @ticket 65819
	 */
	public function test_saves_multi_widget_settings_in_admin() {
		set_current_screen( 'widgets' );

		$settings = array( 3 => array( 'title' => 'Third' ) );

		wp_convert_widget_settings( 'search', 'widget_search', $settings );

		$this->assertSame( $settings + array( '_multiwidget' => 1 ), get_option( 'widget_search' ), 'The settings should be saved.' );
		$this->assertSame( self::SIDEBARS, get_option( 'sidebars_widgets' ), 'The sidebars should not change.' );
	}
}
