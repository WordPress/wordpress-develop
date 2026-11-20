<?php

/**
 * Tests for the `wp_default_editor()` function.
 *
 * @group general
 * @group editor
 *
 * @covers ::wp_default_editor
 */
class Tests_General_WpDefaultEditor extends WP_UnitTestCase {

	/**
	 * Tests that wp_default_editor() returns 'tinymce' by default.
	 *
	 * @ticket 65819
	 */
	public function test_wp_default_editor_default() {
		$this->assertSame( 'tinymce', wp_default_editor() );
	}

	/**
	 * Tests that wp_default_editor() returns user editor setting when set.
	 *
	 * @ticket 65819
	 */
	public function test_wp_default_editor_respects_user_setting() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$orig_updated_user_settings = isset( $GLOBALS['_updated_user_settings'] ) ? $GLOBALS['_updated_user_settings'] : null;
		unset( $GLOBALS['_updated_user_settings'] );

		$_COOKIE[ 'wp-settings-' . $user_id ] = 'editor=html';

		try {
			$this->assertSame( 'html', wp_default_editor() );
		} finally {
			unset( $_COOKIE[ 'wp-settings-' . $user_id ] );

			if ( null !== $orig_updated_user_settings ) {
				$GLOBALS['_updated_user_settings'] = $orig_updated_user_settings;
			} else {
				unset( $GLOBALS['_updated_user_settings'] );
			}
		}
	}

	/**
	 * Tests that the wp_default_editor filter modifies the default editor.
	 *
	 * @ticket 65819
	 */
	public function test_wp_default_editor_filter() {
		add_filter(
			'wp_default_editor',
			static function () {
				return 'html';
			}
		);

		$this->assertSame( 'html', wp_default_editor() );
	}
}
