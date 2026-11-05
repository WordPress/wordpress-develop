<?php

/**
 * Tests for the `wp_admin_bar_render()` function.
 *
 * The function remembers that it already rendered the Toolbar in a static variable,
 * so each test that renders it runs in a separate process.
 *
 * @group general
 * @group admin-bar
 * @group toolbar
 *
 * @covers ::wp_admin_bar_render
 */
class Tests_General_WpAdminBarRender extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		// is_admin_bar_showing() caches its result, so an earlier test can leave it false.
		unset( $GLOBALS['show_admin_bar'] );
	}

	/**
	 * Records the order in which the actions around the rendering fire.
	 *
	 * @param array $calls Reference to the list that records the actions.
	 */
	private function record_actions( array &$calls ) {
		foreach ( array( 'admin_bar_menu', 'wp_before_admin_bar_render', 'wp_after_admin_bar_render' ) as $hook ) {
			add_action(
				$hook,
				static function () use ( &$calls, $hook ) {
					$calls[] = $hook;
				},
				1000
			);
		}
	}

	/**
	 * Tests that nothing is rendered, and no action fires, when the Toolbar is hidden.
	 *
	 * @ticket 66491
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_not_render_when_toolbar_is_hidden() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		_wp_admin_bar_init();

		$calls = array();
		$this->record_actions( $calls );

		show_admin_bar( false );

		ob_start();
		wp_admin_bar_render();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertSame( array(), $calls );
	}

	/**
	 * Tests that nothing is rendered, and no action fires, when there is no Toolbar object.
	 *
	 * @ticket 66491
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_not_render_without_toolbar_object() {
		global $wp_admin_bar;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$wp_admin_bar = null;
		$calls        = array();
		$this->record_actions( $calls );

		show_admin_bar( true );

		ob_start();
		wp_admin_bar_render();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertSame( array(), $calls );
	}

	/**
	 * Tests that the Toolbar is rendered between the actions that surround it, and only once.
	 *
	 * @ticket 66491
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_render_toolbar_once_between_actions() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		_wp_admin_bar_init();

		$calls = array();
		$this->record_actions( $calls );

		// Mark where the actions around the rendering output their content.
		add_action(
			'wp_before_admin_bar_render',
			static function () {
				echo '<!--before-admin-bar-->';
			},
			1000
		);
		add_action(
			'wp_after_admin_bar_render',
			static function () {
				echo '<!--after-admin-bar-->';
			},
			1000
		);

		show_admin_bar( true );

		ob_start();
		wp_admin_bar_render();
		$output = ob_get_clean();

		$before_at  = strpos( $output, '<!--before-admin-bar-->' );
		$toolbar_at = strpos( $output, 'id="wpadminbar"' );
		$after_at   = strpos( $output, '<!--after-admin-bar-->' );

		$this->assertNotFalse( $before_at, 'The output of wp_before_admin_bar_render should be present.' );
		$this->assertNotFalse( $toolbar_at, 'The Toolbar should be rendered.' );
		$this->assertNotFalse( $after_at, 'The output of wp_after_admin_bar_render should be present.' );
		$this->assertLessThan( $toolbar_at, $before_at, 'The Toolbar should be rendered after wp_before_admin_bar_render.' );
		$this->assertLessThan( $after_at, $toolbar_at, 'The Toolbar should be rendered before wp_after_admin_bar_render.' );
		$this->assertSame(
			array( 'admin_bar_menu', 'wp_before_admin_bar_render', 'wp_after_admin_bar_render' ),
			$calls,
			'The actions should fire in order around the rendering.'
		);

		// A second call does not render the Toolbar again.
		ob_start();
		wp_admin_bar_render();
		$second_output = ob_get_clean();

		$this->assertSame( '', $second_output );
		$this->assertCount( 3, $calls, 'The actions should not fire again.' );
	}
}
