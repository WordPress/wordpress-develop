<?php

/**
 * Tests for admin notices accessibility helpers.
 *
 * @group admin
 *
 * @covers ::wp_count_admin_notices_from_html
 * @covers ::wp_prepend_admin_notices_count_to_admin_title
 * @covers ::wp_render_admin_notices
 */
class Tests_Admin_WpAdminNotices extends WP_UnitTestCase {

	/**
	 * @inheritDoc
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		require_once ABSPATH . 'wp-admin/includes/admin-notices.php';
	}

	/**
	 * @inheritDoc
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wp_captured_admin_notices'] );

		parent::tearDown();
	}

	/**
	 * @ticket 50486
	 *
	 * @dataProvider data_should_count_admin_notices_from_html
	 *
	 * @param string $html     Admin notices HTML markup.
	 * @param int    $expected Expected notice count.
	 */
	public function test_should_count_admin_notices_from_html( $html, $expected ) {
		$this->assertSame( $expected, wp_count_admin_notices_from_html( $html ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_count_admin_notices_from_html() {
		return array(
			'empty string'           => array(
				'html'     => '',
				'expected' => 0,
			),
			'single notice'          => array(
				'html'     => '<div class="notice notice-success"><p>Done!</p></div>',
				'expected' => 1,
			),
			'legacy updated notice'  => array(
				'html'     => '<div class="updated"><p>Settings saved.</p></div>',
				'expected' => 1,
			),
			'legacy error notice'    => array(
				'html'     => '<div class="error"><p>Something went wrong.</p></div>',
				'expected' => 1,
			),
			'inline notice excluded' => array(
				'html'     => '<div class="notice notice-error inline"><p>Inline notice.</p></div>',
				'expected' => 0,
			),
			'multiple notices'       => array(
				'html'     => '<div class="notice notice-success"><p>One</p></div><div class="notice notice-warning"><p>Two</p></div>',
				'expected' => 2,
			),
		);
	}

	/**
	 * @ticket 50486
	 */
	public function test_should_prepend_admin_notices_count_to_admin_title() {
		$title = 'Plugins &lsaquo; Test Site &#8212; WordPress';

		add_action(
			'admin_notices',
			static function () {
				wp_admin_notice( 'Plugin activated.', array( 'type' => 'success' ) );
			}
		);

		wp_capture_admin_notices();

		$this->assertSame(
			'(1 notice) ' . $title,
			wp_prepend_admin_notices_count_to_admin_title( $title )
		);
	}

	/**
	 * @ticket 50486
	 */
	public function test_should_render_admin_notices_in_landmark_container() {
		add_action(
			'admin_notices',
			static function () {
				wp_admin_notice( 'Settings saved.', array( 'type' => 'success' ) );
			}
		);

		wp_capture_admin_notices();

		ob_start();
		wp_render_admin_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<aside id="wp-admin-notices"', $output );
		$this->assertStringContainsString( 'class="wp-admin-notices"', $output );
		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( 'Settings saved.', $output );
	}
}
