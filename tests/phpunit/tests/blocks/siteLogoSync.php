<?php
/**
 * Tests for the functions that keep the site logo option and the custom logo theme modification in sync.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_SiteLogoSync extends WP_UnitTestCase {

	/**
	 * Original value of the global that ignores the changes of the logo.
	 *
	 * @var mixed
	 */
	private $original_ignore;

	public function set_up() {
		parent::set_up();

		$this->original_ignore                = $GLOBALS['_ignore_site_logo_changes'] ?? null;
		$GLOBALS['_ignore_site_logo_changes'] = false;

		delete_option( 'site_logo' );
		remove_theme_mod( 'custom_logo' );
	}

	public function tear_down() {
		if ( null === $this->original_ignore ) {
			unset( $GLOBALS['_ignore_site_logo_changes'] );
		} else {
			$GLOBALS['_ignore_site_logo_changes'] = $this->original_ignore;
		}

		remove_theme_support( 'custom-logo' );

		parent::tear_down();
	}

	/**
	 * Tests that the logo of the theme modification is used when there is no option.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_override_custom_logo_theme_mod
	 */
	public function test_should_keep_the_custom_logo_without_a_site_logo() {
		$this->assertSame( 12, _override_custom_logo_theme_mod( 12 ) );
		$this->assertFalse( _override_custom_logo_theme_mod( false ) );
	}

	/**
	 * Tests that the option replaces the logo of the theme modification.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_override_custom_logo_theme_mod
	 *
	 * @dataProvider data_site_logo_values
	 *
	 * @param mixed $value Value of the option.
	 */
	public function test_should_use_the_site_logo_instead_of_the_custom_logo( $value ) {
		update_option( 'site_logo', $value );

		$this->assertEquals( $value, _override_custom_logo_theme_mod( 12 ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_site_logo_values() {
		return array(
			'an ID'  => array( 34 ),
			'zero'   => array( 0 ),
			'a text' => array( 'abc' ),
		);
	}

	/**
	 * Tests that the function is hooked to the theme modification.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_override_custom_logo_theme_mod
	 */
	public function test_should_override_the_theme_modification_with_the_site_logo() {
		set_theme_mod( 'custom_logo', 5 );
		update_option( 'site_logo', 7 );

		$this->assertSame( 10, has_filter( 'theme_mod_custom_logo', '_override_custom_logo_theme_mod' ) );
		$this->assertEquals( 7, get_theme_mod( 'custom_logo' ) );
	}

	/**
	 * Tests that the option is saved when the logo is set.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_sync_custom_logo_to_site_logo
	 */
	public function test_should_save_the_site_logo_when_the_custom_logo_is_set() {
		$this->assertSame( 21, _sync_custom_logo_to_site_logo( 21 ), 'The value should be returned.' );
		$this->assertEquals( 21, get_option( 'site_logo' ) );
	}

	/**
	 * Tests that the option is deleted when the logo is empty.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_sync_custom_logo_to_site_logo
	 *
	 * @dataProvider data_empty_values
	 *
	 * @param mixed $value Empty value.
	 */
	public function test_should_delete_the_site_logo_when_the_custom_logo_is_empty( $value ) {
		update_option( 'site_logo', 21 );

		$this->assertSame( $value, _sync_custom_logo_to_site_logo( $value ), 'The value should be returned.' );
		$this->assertFalse( get_option( 'site_logo' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_values() {
		return array(
			'zero'           => array( 0 ),
			'an empty text'  => array( '' ),
			'null'           => array( null ),
			'false'          => array( false ),
			'an empty array' => array( array() ),
			'zero as text'   => array( '0' ),
		);
	}

	/**
	 * Tests that the option follows the theme modification.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_sync_custom_logo_to_site_logo
	 */
	public function test_should_save_the_site_logo_when_the_theme_modification_is_set() {
		$this->assertSame( 10, has_filter( 'pre_set_theme_mod_custom_logo', '_sync_custom_logo_to_site_logo' ) );

		set_theme_mod( 'custom_logo', 33 );
		$this->assertEquals( 33, get_option( 'site_logo' ), 'The option should be saved.' );

		set_theme_mod( 'custom_logo', 0 );
		$this->assertFalse( get_option( 'site_logo' ), 'The option should be deleted.' );
	}

	/**
	 * Tests that the option is deleted when the custom logo is removed from the theme modifications.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_site_logo_on_remove_custom_logo
	 *
	 * @dataProvider data_theme_mods_changes
	 *
	 * @param array $old_value Old theme modifications.
	 * @param array $value     New theme modifications.
	 * @param bool  $deleted   Whether the option is expected to be deleted.
	 */
	public function test_should_delete_the_site_logo_when_the_custom_logo_is_removed( $old_value, $value, $deleted ) {
		update_option( 'site_logo', 21 );

		_delete_site_logo_on_remove_custom_logo( $old_value, $value );

		if ( $deleted ) {
			$this->assertFalse( get_option( 'site_logo' ) );
		} else {
			$this->assertEquals( 21, get_option( 'site_logo' ) );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_theme_mods_changes() {
		return array(
			'the logo is removed'                   => array( array( 'custom_logo' => 5 ), array( 'other' => 1 ), true ),
			'the logo is removed with others'       => array(
				array(
					'custom_logo' => 5,
					'other'       => 1,
				),
				array( 'other' => 1 ),
				true,
			),
			'the logo is removed from all'          => array( array( 'custom_logo' => 5 ), array(), true ),
			'the logo is changed'                   => array( array( 'custom_logo' => 5 ), array( 'custom_logo' => 6 ), false ),
			'the logo is kept'                      => array( array( 'custom_logo' => 5 ), array( 'custom_logo' => 5 ), false ),
			'the logo is added'                     => array( array( 'other' => 1 ), array( 'custom_logo' => 6 ), false ),
			'there is no logo'                      => array( array( 'other' => 1 ), array( 'other' => 2 ), false ),
			'the logo is changed to an empty value' => array( array( 'custom_logo' => 5 ), array( 'custom_logo' => 0 ), false ),
			'the logo is set to null before'        => array( array( 'custom_logo' => null ), array(), false ),
		);
	}

	/**
	 * Tests that the option is not deleted when the changes of the logo are ignored.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_site_logo_on_remove_custom_logo
	 */
	public function test_should_not_delete_the_site_logo_when_the_changes_are_ignored() {
		update_option( 'site_logo', 21 );
		$GLOBALS['_ignore_site_logo_changes'] = true;

		_delete_site_logo_on_remove_custom_logo( array( 'custom_logo' => 5 ), array() );

		$this->assertEquals( 21, get_option( 'site_logo' ) );
	}

	/**
	 * Tests that the option is deleted when the theme modifications are removed and the theme supports the logo.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_site_logo_on_remove_theme_mods
	 */
	public function test_should_delete_the_site_logo_when_the_theme_modifications_are_removed() {
		add_theme_support( 'custom-logo' );
		update_option( 'site_logo', 21 );

		_delete_site_logo_on_remove_theme_mods();

		$this->assertFalse( get_option( 'site_logo' ) );
	}

	/**
	 * Tests that the option is kept when the theme does not support the logo.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_site_logo_on_remove_theme_mods
	 */
	public function test_should_not_delete_the_site_logo_when_the_theme_does_not_support_the_logo() {
		remove_theme_support( 'custom-logo' );
		update_option( 'site_logo', 21 );

		_delete_site_logo_on_remove_theme_mods();

		$this->assertEquals( 21, get_option( 'site_logo' ) );
	}

	/**
	 * Tests that the option is kept when the changes of the logo are ignored.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_site_logo_on_remove_theme_mods
	 */
	public function test_should_not_delete_the_site_logo_when_the_theme_modifications_are_removed_and_the_changes_are_ignored() {
		add_theme_support( 'custom-logo' );
		update_option( 'site_logo', 21 );
		$GLOBALS['_ignore_site_logo_changes'] = true;

		_delete_site_logo_on_remove_theme_mods();

		$this->assertEquals( 21, get_option( 'site_logo' ) );
	}

	/**
	 * Tests that the actions of the theme modifications are added for the active theme.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_site_logo_on_remove_custom_logo_on_setup_theme
	 */
	public function test_should_add_the_actions_of_the_theme_modifications_for_the_active_theme() {
		$theme = get_option( 'stylesheet' );

		remove_action( "update_option_theme_mods_$theme", '_delete_site_logo_on_remove_custom_logo' );
		remove_action( "delete_option_theme_mods_$theme", '_delete_site_logo_on_remove_theme_mods' );

		_delete_site_logo_on_remove_custom_logo_on_setup_theme();

		$this->assertSame( 10, has_action( "update_option_theme_mods_$theme", '_delete_site_logo_on_remove_custom_logo' ) );
		$this->assertSame( 10, has_action( "delete_option_theme_mods_$theme", '_delete_site_logo_on_remove_theme_mods' ) );
		$this->assertSame( 11, has_action( 'setup_theme', '_delete_site_logo_on_remove_custom_logo_on_setup_theme' ) );
	}

	/**
	 * Tests that the actions use the stylesheet of the active theme.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_site_logo_on_remove_custom_logo_on_setup_theme
	 */
	public function test_should_use_the_stylesheet_for_the_actions_of_the_theme_modifications() {
		update_option( 'stylesheet', 'wp-test-child-theme' );

		_delete_site_logo_on_remove_custom_logo_on_setup_theme();

		$this->assertSame( 10, has_action( 'update_option_theme_mods_wp-test-child-theme', '_delete_site_logo_on_remove_custom_logo' ) );
		$this->assertSame( 10, has_action( 'delete_option_theme_mods_wp-test-child-theme', '_delete_site_logo_on_remove_theme_mods' ) );
	}

	/**
	 * Tests that the custom logo is removed when the option is deleted.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_custom_logo_on_remove_site_logo
	 */
	public function test_should_remove_the_custom_logo_when_the_site_logo_is_deleted() {
		set_theme_mod( 'custom_logo', 44 );
		$this->assertEquals( 44, get_option( 'site_logo' ), 'The option should follow the logo.' );

		delete_option( 'site_logo' );

		$this->assertSame( 10, has_action( 'delete_option_site_logo', '_delete_custom_logo_on_remove_site_logo' ) );
		$this->assertFalse( get_theme_mod( 'custom_logo' ), 'The logo should be removed.' );
		$this->assertFalse( get_option( 'site_logo' ), 'The option should stay deleted.' );
	}

	/**
	 * Tests that the changes are not ignored after the custom logo is removed.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_custom_logo_on_remove_site_logo
	 */
	public function test_should_stop_ignoring_the_changes_after_the_custom_logo_is_removed() {
		$GLOBALS['_ignore_site_logo_changes'] = true;
		set_theme_mod( 'custom_logo', 44 );

		_delete_custom_logo_on_remove_site_logo();

		$this->assertFalse( $GLOBALS['_ignore_site_logo_changes'] );
	}

	/**
	 * Tests that the option is kept while the custom logo is removed.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_custom_logo_on_remove_site_logo
	 */
	public function test_should_not_delete_the_site_logo_again_while_the_custom_logo_is_removed() {
		set_theme_mod( 'custom_logo', 44 );
		set_theme_mod( 'other_mod', 'value' );
		$was_ignoring = null;

		add_action(
			'update_option_theme_mods_' . get_option( 'stylesheet' ),
			static function () use ( &$was_ignoring ) {
				$was_ignoring = $GLOBALS['_ignore_site_logo_changes'];
			},
			1
		);

		_delete_custom_logo_on_remove_site_logo();

		$this->assertTrue( $was_ignoring, 'The changes should be ignored while the logo is removed.' );
	}

	/**
	 * Tests that the option is deleted when the logo is removed from the theme modifications.
	 *
	 * @ticket 66532
	 *
	 * @covers ::_delete_site_logo_on_remove_custom_logo
	 * @covers ::_delete_site_logo_on_remove_theme_mods
	 */
	public function test_should_delete_the_site_logo_when_the_theme_modification_is_removed() {
		add_theme_support( 'custom-logo' );
		set_theme_mod( 'custom_logo', 44 );
		set_theme_mod( 'other_mod', 'value' );

		remove_theme_mod( 'custom_logo' );

		$this->assertFalse( get_option( 'site_logo' ), 'The option should be deleted when other modifications remain.' );

		set_theme_mod( 'custom_logo', 45 );
		remove_theme_mods();

		$this->assertFalse( get_option( 'site_logo' ), 'The option should be deleted when all the modifications are removed.' );
	}
}
