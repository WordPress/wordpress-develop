<?php

/**
 * Tests for add_allowed_options(), remove_allowed_options() and option_update_filter().
 *
 * @group admin
 * @group plugins
 * @group option
 */
class Tests_Admin_Includes_Plugin_AllowedOptions extends WP_UnitTestCase {

	/**
	 * Names of the globals used by the functions under test.
	 *
	 * @var string[]
	 */
	const GLOBALS_USED = array( 'allowed_options', 'new_allowed_options' );

	/**
	 * Original values of the globals that were set before the test, keyed by name.
	 *
	 * @var array
	 */
	private $orig_globals = array();

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->orig_globals = array();

		foreach ( self::GLOBALS_USED as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$this->orig_globals[ $name ] = $GLOBALS[ $name ];
			}

			unset( $GLOBALS[ $name ] );
		}
	}

	public function tear_down() {
		foreach ( self::GLOBALS_USED as $name ) {
			if ( array_key_exists( $name, $this->orig_globals ) ) {
				$GLOBALS[ $name ] = $this->orig_globals[ $name ];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests the options added by add_allowed_options().
	 *
	 * @ticket 65819
	 *
	 * @covers ::add_allowed_options
	 *
	 * @dataProvider data_add_allowed_options
	 *
	 * @param array $new_options Options to add, keyed by option page.
	 * @param array $options     Existing allowed options.
	 * @param array $expected    Expected allowed options.
	 */
	public function test_add_allowed_options( $new_options, $options, $expected ) {
		$this->assertSame( $expected, add_allowed_options( $new_options, $options ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_add_allowed_options() {
		return array(
			'new option on an existing page'     => array(
				array( 'general' => array( 'my_option' ) ),
				array( 'general' => array( 'blogname' ) ),
				array( 'general' => array( 'blogname', 'my_option' ) ),
			),
			'several options on a new page'      => array(
				array( 'my_page' => array( 'my_option', 'my_other_option' ) ),
				array( 'general' => array( 'blogname' ) ),
				array(
					'general' => array( 'blogname' ),
					'my_page' => array( 'my_option', 'my_other_option' ),
				),
			),
			'several pages'                      => array(
				array(
					'general' => array( 'my_option' ),
					'my_page' => array( 'my_other_option' ),
				),
				array( 'general' => array( 'blogname' ) ),
				array(
					'general' => array( 'blogname', 'my_option' ),
					'my_page' => array( 'my_other_option' ),
				),
			),
			'option that is already allowed'     => array(
				array( 'general' => array( 'blogname' ) ),
				array( 'general' => array( 'blogname', 'blogdescription' ) ),
				array( 'general' => array( 'blogname', 'blogdescription' ) ),
			),
			'duplicate new options'              => array(
				array( 'my_page' => array( 'my_option', 'my_option' ) ),
				array(),
				array( 'my_page' => array( 'my_option' ) ),
			),
			'falsy option names'                 => array(
				array( 'my_page' => array( '0', '', 0 ) ),
				array( 'my_page' => array( '0' ) ),
				array( 'my_page' => array( '0', '', 0 ) ),
			),
			'existing page that is not an array' => array(
				array( 'my_page' => array( 'my_option' ) ),
				array( 'my_page' => 'not-an-array' ),
				array( 'my_page' => array( 'my_option' ) ),
			),
			'page without options'               => array(
				array( 'my_page' => array() ),
				array( 'general' => array( 'blogname' ) ),
				array( 'general' => array( 'blogname' ) ),
			),
			'no new options'                     => array(
				array(),
				array( 'general' => array( 'blogname' ) ),
				array( 'general' => array( 'blogname' ) ),
			),
			'no existing options'                => array(
				array( 'my_page' => array( 'my_option' ) ),
				array(),
				array( 'my_page' => array( 'my_option' ) ),
			),
		);
	}

	/**
	 * Tests that add_allowed_options() updates the $allowed_options global when no options are passed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::add_allowed_options
	 */
	public function test_add_allowed_options_updates_global_by_default() {
		$GLOBALS['allowed_options'] = array( 'general' => array( 'blogname' ) );

		$expected = array(
			'general' => array( 'blogname', 'my_option' ),
			'my_page' => array( 'my_other_option' ),
		);

		$this->assertSame(
			$expected,
			add_allowed_options(
				array(
					'general' => array( 'my_option' ),
					'my_page' => array( 'my_other_option' ),
				)
			),
			'The updated global should be returned.'
		);
		$this->assertSame( $expected, $GLOBALS['allowed_options'], 'The $allowed_options global should be updated.' );
	}

	/**
	 * Tests that add_allowed_options() does not change the $allowed_options global when options are passed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::add_allowed_options
	 */
	public function test_add_allowed_options_does_not_change_global_when_options_are_passed() {
		$GLOBALS['allowed_options'] = array( 'general' => array( 'blogname' ) );

		add_allowed_options( array( 'general' => array( 'my_option' ) ), array( 'general' => array( 'blogdescription' ) ) );

		$this->assertSame( array( 'general' => array( 'blogname' ) ), $GLOBALS['allowed_options'] );
	}

	/**
	 * Tests the options removed by remove_allowed_options().
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_allowed_options
	 *
	 * @dataProvider data_remove_allowed_options
	 *
	 * @param array $del_options Options to remove, keyed by option page.
	 * @param array $options     Existing allowed options.
	 * @param array $expected    Expected allowed options.
	 */
	public function test_remove_allowed_options( $del_options, $options, $expected ) {
		$this->assertSame( $expected, remove_allowed_options( $del_options, $options ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_remove_allowed_options() {
		return array(
			'option in the middle keeps the other keys' => array(
				array( 'general' => array( 'blogdescription' ) ),
				array( 'general' => array( 'blogname', 'blogdescription', 'admin_email' ) ),
				array(
					'general' => array(
						0 => 'blogname',
						2 => 'admin_email',
					),
				),
			),
			'several options on several pages'          => array(
				array(
					'general' => array( 'blogname', 'admin_email' ),
					'my_page' => array( 'my_option' ),
				),
				array(
					'general' => array( 'blogname', 'blogdescription', 'admin_email' ),
					'my_page' => array( 'my_option' ),
				),
				array(
					'general' => array( 1 => 'blogdescription' ),
					'my_page' => array(),
				),
			),
			'option that is not allowed'                => array(
				array( 'general' => array( 'my_option' ) ),
				array( 'general' => array( 'blogname' ) ),
				array( 'general' => array( 'blogname' ) ),
			),
			'option on another page'                    => array(
				array( 'my_page' => array( 'blogname' ) ),
				array(
					'general' => array( 'blogname' ),
					'my_page' => array( 'my_option' ),
				),
				array(
					'general' => array( 'blogname' ),
					'my_page' => array( 'my_option' ),
				),
			),
			'page that does not exist'                  => array(
				array( 'my_page' => array( 'my_option' ) ),
				array( 'general' => array( 'blogname' ) ),
				array( 'general' => array( 'blogname' ) ),
			),
			'existing page that is not an array'        => array(
				array( 'my_page' => array( 'my_option' ) ),
				array( 'my_page' => 'my_option' ),
				array( 'my_page' => 'my_option' ),
			),
			'loosely equal option name'                 => array(
				array( 'my_page' => array( 0 ) ),
				array( 'my_page' => array( '0', 'my_option' ) ),
				array( 'my_page' => array( '0', 'my_option' ) ),
			),
			'duplicate allowed option'                  => array(
				array( 'my_page' => array( 'my_option' ) ),
				array( 'my_page' => array( 'my_option', 'my_option' ) ),
				array( 'my_page' => array( 1 => 'my_option' ) ),
			),
			'no options to remove'                      => array(
				array(),
				array( 'general' => array( 'blogname' ) ),
				array( 'general' => array( 'blogname' ) ),
			),
		);
	}

	/**
	 * Tests that remove_allowed_options() updates the $allowed_options global when no options are passed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_allowed_options
	 */
	public function test_remove_allowed_options_updates_global_by_default() {
		$GLOBALS['allowed_options'] = array( 'general' => array( 'blogname', 'blogdescription' ) );

		$expected = array( 'general' => array( 1 => 'blogdescription' ) );

		$this->assertSame( $expected, remove_allowed_options( array( 'general' => array( 'blogname' ) ) ), 'The updated global should be returned.' );
		$this->assertSame( $expected, $GLOBALS['allowed_options'], 'The $allowed_options global should be updated.' );
	}

	/**
	 * Tests that remove_allowed_options() does not change the $allowed_options global when options are passed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_allowed_options
	 */
	public function test_remove_allowed_options_does_not_change_global_when_options_are_passed() {
		$GLOBALS['allowed_options'] = array( 'general' => array( 'blogname' ) );

		remove_allowed_options( array( 'general' => array( 'blogname' ) ), array( 'general' => array( 'blogname' ) ) );

		$this->assertSame( array( 'general' => array( 'blogname' ) ), $GLOBALS['allowed_options'] );
	}

	/**
	 * Tests that option_update_filter() adds the options from the $new_allowed_options global.
	 *
	 * @ticket 65819
	 *
	 * @covers ::option_update_filter
	 */
	public function test_option_update_filter_adds_new_allowed_options() {
		$GLOBALS['allowed_options']     = array( 'privacy' => array( 'blog_public' ) );
		$GLOBALS['new_allowed_options'] = array(
			'general' => array( 'my_option' ),
			'my_page' => array( 'my_other_option' ),
		);

		$this->assertSame(
			array(
				'general' => array( 'blogname', 'my_option' ),
				'my_page' => array( 'my_other_option' ),
			),
			option_update_filter( array( 'general' => array( 'blogname' ) ) ),
			'The new options should be added to the passed options.'
		);
		$this->assertSame( array( 'privacy' => array( 'blog_public' ) ), $GLOBALS['allowed_options'], 'The $allowed_options global should not change.' );
	}

	/**
	 * Tests that option_update_filter() returns the options unchanged without new allowed options.
	 *
	 * @ticket 65819
	 *
	 * @covers ::option_update_filter
	 *
	 * @dataProvider data_invalid_new_allowed_options
	 *
	 * @param mixed $new_allowed_options Value of the $new_allowed_options global.
	 */
	public function test_option_update_filter_returns_options_unchanged_without_new_allowed_options( $new_allowed_options ) {
		$GLOBALS['new_allowed_options'] = $new_allowed_options;

		$options = array( 'general' => array( 'blogname' ) );

		$this->assertSame( $options, option_update_filter( $options ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_new_allowed_options() {
		return array(
			'null'         => array( null ),
			'false'        => array( false ),
			'empty string' => array( '' ),
			'string'       => array( 'my_option' ),
			'empty array'  => array( array() ),
		);
	}
}
