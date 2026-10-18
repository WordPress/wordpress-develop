<?php

/**
 * Tests for wp_is_file_mod_allowed().
 *
 * @group load
 *
 * @covers ::wp_is_file_mod_allowed
 */
class Tests_Load_WpIsFileModAllowed extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_when_disallow_file_mods_is_undefined() {
		$this->assertFalse( defined( 'DISALLOW_FILE_MODS' ) );
		$this->assertTrue( wp_is_file_mod_allowed( 'install_plugins' ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_false_when_disallow_file_mods_is_true() {
		define( 'DISALLOW_FILE_MODS', true );

		$this->assertFalse( wp_is_file_mod_allowed( 'install_plugins' ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_true_when_disallow_file_mods_is_false() {
		define( 'DISALLOW_FILE_MODS', false );

		$this->assertTrue( wp_is_file_mod_allowed( 'install_plugins' ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_contexts
	 *
	 * @param string $context Context passed to the function.
	 */
	public function test_passes_context_to_filter( $context ) {
		$received_context = null;
		$filter           = function ( $file_mod_allowed, $filter_context ) use ( &$received_context ) {
			$received_context = $filter_context;
			return $file_mod_allowed;
		};

		add_filter( 'file_mod_allowed', $filter, 10, 2 );
		wp_is_file_mod_allowed( $context );
		remove_filter( 'file_mod_allowed', $filter, 10 );

		$this->assertSame( $context, $received_context );
	}

	/**
	 * Data provider for test_passes_context_to_filter().
	 *
	 * @return array[]
	 */
	public function data_contexts() {
		return array(
			'install_plugins'        => array( 'install_plugins' ),
			'automatic_updater'      => array( 'automatic_updater' ),
			'capability_edit_themes' => array( 'capability_edit_themes' ),
			'download_language_pack' => array( 'download_language_pack' ),
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_disallow_when_file_mods_are_allowed() {
		add_filter( 'file_mod_allowed', '__return_false' );
		$this->assertFalse( wp_is_file_mod_allowed( 'install_plugins' ) );
		remove_filter( 'file_mod_allowed', '__return_false' );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_filter_can_allow_when_disallow_file_mods_is_true() {
		define( 'DISALLOW_FILE_MODS', true );

		add_filter( 'file_mod_allowed', '__return_true' );
		$this->assertTrue( wp_is_file_mod_allowed( 'install_plugins' ) );
		remove_filter( 'file_mod_allowed', '__return_true' );
	}
}
