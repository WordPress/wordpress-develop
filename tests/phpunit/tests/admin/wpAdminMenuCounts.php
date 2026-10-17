<?php

/**
 * Tests the counter markup built by wp-admin/menu.php.
 *
 * menu.php declares functions and builds globals at include time, so each test
 * runs in its own process.
 *
 * @group admin
 * @group menu
 * @group ms-excluded
 */
class Tests_Admin_WpAdminMenuCounts extends WP_UnitTestCase {

	/**
	 * Includes menu.php with the given update count, and returns the menu globals.
	 *
	 * @param int $count Count used for every update type and for Site Health critical issues.
	 * @return array{0: array, 1: array} The $menu and $submenu globals.
	 */
	private function build_menu( $count ) {
		global $menu, $submenu, $pagenow;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter(
			'wp_get_update_data',
			static function () use ( $count ) {
				return array(
					'counts' => array(
						'plugins'      => $count,
						'themes'       => $count,
						'wordpress'    => 0,
						'translations' => 0,
						'total'        => 2 * $count,
					),
					'title'  => '',
				);
			}
		);

		set_transient(
			'health-check-site-status-result',
			wp_json_encode(
				array(
					'good'        => 0,
					'recommended' => 0,
					'critical'    => $count,
				)
			)
		);

		if ( $count > 0 ) {
			self::factory()->comment->create_many(
				$count,
				array(
					'comment_post_ID'  => self::factory()->post->create(),
					'comment_approved' => '0',
				)
			);
		}

		$pagenow = 'index.php';
		$menu    = array();
		$submenu = array();

		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require ABSPATH . 'wp-admin/menu.php';

		return array( $menu, $submenu );
	}

	/**
	 * Returns the menu item whose slug matches.
	 *
	 * @param array  $items Menu or submenu items.
	 * @param string $slug  Menu slug.
	 * @return array The item, or an empty array.
	 */
	private function find_item( $items, $slug ) {
		foreach ( $items as $item ) {
			if ( $slug === $item[2] ) {
				return $item;
			}
		}

		return array();
	}

	/**
	 * Returns the five counter items keyed by description id.
	 *
	 * @param int $count Count to build the menu with.
	 * @return array[]
	 */
	private function counter_items( $count ) {
		list( $menu, $submenu ) = $this->build_menu( $count );

		return array(
			'wp-menu-updates-count-description'     => $this->find_item( $submenu['index.php'], 'update-core.php' ),
			'wp-menu-comments-count-description'    => $this->find_item( $menu, 'edit-comments.php' ),
			'wp-menu-themes-count-description'      => $this->find_item( $submenu['themes.php'], 'themes.php' ),
			'wp-menu-plugins-count-description'     => $this->find_item( $menu, 'plugins.php' ),
			'wp-menu-site-health-count-description' => $this->find_item( $submenu['tools.php'], 'site-health.php' ),
		);
	}

	/**
	 * At zero, each counter keeps its description element but with no text.
	 *
	 * @ticket 65793
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_zero_counts_have_empty_descriptions() {
		foreach ( $this->counter_items( 0 ) as $id => $item ) {
			$this->assertSame( $id, $item['count_description']['id'], "$id should still be referenced." );
			$this->assertMatchesRegularExpression(
				'#^<span id="' . $id . '"[^>]* hidden></span>$#',
				$item['count_description']['html'],
				"$id should have no text at zero."
			);
		}
	}

	/**
	 * With a count, each description has text and each bubble sits in an
	 * aria-hidden wrapper that starts with a space.
	 *
	 * @ticket 65793
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_nonzero_counts_have_text_and_wrapped_bubbles() {
		foreach ( $this->counter_items( 2 ) as $id => $item ) {
			$this->assertMatchesRegularExpression(
				'#^<span id="' . $id . '"[^>]* hidden>[^<]+</span>$#',
				$item['count_description']['html'],
				"$id should have text."
			);
			$this->assertMatchesRegularExpression(
				'#^[^<]+<span aria-hidden="true"> <span class="[^"]*count-\d+">#',
				$item[0],
				"$id bubble should be wrapped."
			);
		}
	}
}
