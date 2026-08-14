<?php

/**
 * Test wp_delete_inactive_widgets().
 *
 * @group widgets
 * @covers ::wp_delete_inactive_widgets
 */
class Tests_Widgets_WpDeleteInactiveWidgets extends WP_UnitTestCase {
	public function tear_down() {
		parent::tear_down();
	}

	/**
	 * Tests that wp_delete_inactive_widgets() does nothing when there are no inactive widgets.
	 */
	public function test_wp_delete_inactive_widgets_empty() {
		$sidebars_widgets = wp_get_sidebars_widgets();
		$sidebars_widgets['wp_inactive_widgets'] = array();
		wp_set_sidebars_widgets( $sidebars_widgets );

		wp_delete_inactive_widgets();

		$this->assertEmpty( wp_get_sidebars_widgets()['wp_inactive_widgets'] );
	}

	/**
	 * Tests that wp_delete_inactive_widgets() removes inactive widgets and their settings.
	 */
	public function test_wp_delete_inactive_widgets_removes_widgets() {
		require_once ABSPATH . 'wp-admin/includes/widgets.php';

		// Set up some inactive widgets.
		$widget_id_1 = 'search-2';
		$widget_id_2 = 'text-3';
		$widget_id_3 = 'no-option-4';

		update_option( 'widget_search', array( 2 => array( 'title' => 'Search' ), '_multiwidget' => 1 ) );
		update_option( 'widget_text', array( 3 => array( 'text' => 'Some text' ), '_multiwidget' => 1 ) );

		$sidebars_widgets = wp_get_sidebars_widgets();
		$sidebars_widgets['wp_inactive_widgets'] = array( $widget_id_1, $widget_id_2, $widget_id_3 );
		$sidebars_widgets['sidebar-1'] = array( 'search-3' );
		update_option( 'widget_search', array(
			2 => array( 'title' => 'Search' ),
			3 => array( 'title' => 'Active Search' ),
			'_multiwidget' => 1
		) );

		wp_set_sidebars_widgets( $sidebars_widgets );

		// Run the function.
		wp_delete_inactive_widgets();

		$updated_sidebars = wp_get_sidebars_widgets();
		$this->assertEmpty( $updated_sidebars['wp_inactive_widgets'], 'Inactive widgets sidebar should be empty.' );
		$this->assertContains( 'search-3', $updated_sidebars['sidebar-1'], 'Active widgets should remain.' );

		$search_option = get_option( 'widget_search' );
		$this->assertArrayNotHasKey( 2, $search_option, 'Inactive search widget setting should be removed.' );
		$this->assertArrayHasKey( 3, $search_option, 'Active search widget setting should remain.' );

		$text_option = get_option( 'widget_text' );
		$this->assertArrayNotHasKey( 3, $text_option, 'Inactive text widget setting should be removed.' );
	}
}
