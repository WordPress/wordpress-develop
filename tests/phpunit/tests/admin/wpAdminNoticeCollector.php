<?php

/**
 * Tests for the WP_Admin_Notice_Collector class.
 *
 * @group admin
 * @group admin-notices
 *
 * @coversDefaultClass WP_Admin_Notice_Collector
 */
class Tests_Admin_wpAdminNoticeCollector extends WP_UnitTestCase {

	/**
	 * Original value of the collector's notices property.
	 *
	 * @var array
	 */
	private $orig_notices;

	/**
	 * Original value of the collector's hooked property.
	 *
	 * @var bool
	 */
	private $orig_hooked;

	public function set_up() {
		parent::set_up();

		$this->orig_notices = $this->get_static_property( 'notices' )->getValue();
		$this->orig_hooked  = $this->get_static_property( 'hooked' )->getValue();

		$this->get_static_property( 'notices' )->setValue( null, array() );
		$this->get_static_property( 'hooked' )->setValue( null, false );
	}

	public function tear_down() {
		$this->get_static_property( 'notices' )->setValue( null, $this->orig_notices );
		$this->get_static_property( 'hooked' )->setValue( null, $this->orig_hooked );

		parent::tear_down();
	}

	/**
	 * Gets an accessible static property of the collector.
	 *
	 * @param string $property Property name.
	 * @return ReflectionProperty Accessible property.
	 */
	private function get_static_property( $property ) {
		$reflection = new ReflectionProperty( 'WP_Admin_Notice_Collector', $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection;
	}

	/**
	 * Tests that no notices are collected by default.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_notices
	 * @covers ::get_notice_count
	 */
	public function test_no_notices_are_collected_by_default() {
		$this->assertSame( array(), WP_Admin_Notice_Collector::get_notices() );
		$this->assertSame( 0, WP_Admin_Notice_Collector::get_notice_count() );
	}

	/**
	 * Tests that capture_notice() stores the notice under its ID and returns the markup unchanged.
	 *
	 * @ticket 65819
	 *
	 * @covers ::capture_notice
	 * @covers ::get_notices
	 * @covers ::get_notice_count
	 */
	public function test_capture_notice_stores_notice_and_returns_markup() {
		$markup = '<div class="notice notice-error" id="wp-admin-notice-1"><p>Something <strong>failed</strong>.</p></div>';
		$args   = array(
			'type'      => 'error',
			'notice_id' => 'wp-admin-notice-1',
		);

		$this->assertSame( $markup, WP_Admin_Notice_Collector::capture_notice( $markup, 'Something <strong>failed</strong>.', $args ), 'The markup should be returned unchanged.' );
		$this->assertSame(
			array(
				'wp-admin-notice-1' => array(
					'markup'  => $markup,
					'message' => 'Something <strong>failed</strong>.',
					'args'    => $args,
				),
			),
			WP_Admin_Notice_Collector::get_notices(),
			'The notice should be stored under its ID.'
		);
		$this->assertSame( 1, WP_Admin_Notice_Collector::get_notice_count(), 'One notice should be counted.' );
	}

	/**
	 * Tests that capture_notice() ignores notices without a visible message.
	 *
	 * @ticket 65819
	 *
	 * @covers ::capture_notice
	 *
	 * @dataProvider data_messages_without_text
	 *
	 * @param string $message Notice message.
	 */
	public function test_capture_notice_ignores_notice_without_text( $message ) {
		$markup = '<div class="notice" id="wp-admin-notice-1"><p>' . $message . '</p></div>';

		$this->assertSame( $markup, WP_Admin_Notice_Collector::capture_notice( $markup, $message, array( 'notice_id' => 'wp-admin-notice-1' ) ), 'The markup should be returned unchanged.' );
		$this->assertSame( array(), WP_Admin_Notice_Collector::get_notices(), 'The notice should not be stored.' );
		$this->assertSame( 0, WP_Admin_Notice_Collector::get_notice_count(), 'The notice should not be counted.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_messages_without_text() {
		return array(
			'empty string'        => array( '' ),
			'whitespace'          => array( " \t\n" ),
			'empty elements'      => array( '<p></p><span> </span>' ),
			'script without text' => array( '<script>document.title = "x";</script>' ),
		);
	}

	/**
	 * Tests that capture_notice() counts notices whose message is falsy but visible.
	 *
	 * @ticket 65819
	 *
	 * @covers ::capture_notice
	 */
	public function test_capture_notice_stores_notice_with_zero_message() {
		WP_Admin_Notice_Collector::capture_notice( '<div class="notice" id="wp-admin-notice-1"><p>0</p></div>', '0', array( 'notice_id' => 'wp-admin-notice-1' ) );

		$this->assertSame( 1, WP_Admin_Notice_Collector::get_notice_count() );
	}

	/**
	 * Tests that each notice ID is only counted once.
	 *
	 * @ticket 65819
	 *
	 * @covers ::capture_notice
	 * @covers ::get_notice_count
	 */
	public function test_capture_notice_counts_each_notice_id_once() {
		WP_Admin_Notice_Collector::capture_notice( '<div>First</div>', 'First', array( 'notice_id' => 'wp-admin-notice-1' ) );
		WP_Admin_Notice_Collector::capture_notice( '<div>Second</div>', 'Second', array( 'notice_id' => 'wp-admin-notice-2' ) );
		WP_Admin_Notice_Collector::capture_notice( '<div>First again</div>', 'First again', array( 'notice_id' => 'wp-admin-notice-1' ) );

		$notices = WP_Admin_Notice_Collector::get_notices();

		$this->assertSame( 2, WP_Admin_Notice_Collector::get_notice_count(), 'Two notices should be counted.' );
		$this->assertSame( array( 'wp-admin-notice-1', 'wp-admin-notice-2' ), array_keys( $notices ), 'The notices should be keyed by their IDs.' );
		$this->assertSame( 'First again', $notices['wp-admin-notice-1']['message'], 'The later notice should replace the earlier one with the same ID.' );
	}

	/**
	 * Tests that reset() removes all collected notices.
	 *
	 * @ticket 65819
	 *
	 * @covers ::reset
	 */
	public function test_reset_removes_collected_notices() {
		WP_Admin_Notice_Collector::capture_notice( '<div>First</div>', 'First', array( 'notice_id' => 'wp-admin-notice-1' ) );
		WP_Admin_Notice_Collector::capture_notice( '<div>Second</div>', 'Second', array( 'notice_id' => 'wp-admin-notice-2' ) );

		WP_Admin_Notice_Collector::reset();

		$this->assertSame( array(), WP_Admin_Notice_Collector::get_notices() );
		$this->assertSame( 0, WP_Admin_Notice_Collector::get_notice_count() );
	}

	/**
	 * Tests that init() collects the notices generated by wp_get_admin_notice().
	 *
	 * @ticket 65819
	 *
	 * @covers ::init
	 * @covers ::capture_notice
	 */
	public function test_init_collects_generated_admin_notices() {
		WP_Admin_Notice_Collector::init();

		$this->assertSame( 10, has_filter( 'wp_admin_notice_markup', array( 'WP_Admin_Notice_Collector', 'capture_notice' ) ), 'The capture callback should be hooked.' );
		$this->assertFalse( has_filter( 'admin_title', array( 'WP_Admin_Notice_Collector', 'add_title_placeholder' ) ), 'The title placeholder should not be added outside the admin.' );

		$first_markup  = wp_get_admin_notice( 'Settings saved.', array( 'type' => 'success' ) );
		$second_markup = wp_get_admin_notice( 'Something failed.', array( 'type' => 'error' ) );
		wp_get_admin_notice( '' );

		$notices = array_values( WP_Admin_Notice_Collector::get_notices() );

		$this->assertSame( 2, WP_Admin_Notice_Collector::get_notice_count(), 'The two notices with a message should be counted.' );
		$this->assertSame( array( 'Settings saved.', 'Something failed.' ), wp_list_pluck( $notices, 'message' ), 'The messages should be stored in order.' );
		$this->assertSame( array( $first_markup, $second_markup ), wp_list_pluck( $notices, 'markup' ), 'The generated markup should be stored.' );
		$this->assertSame( 'success', $notices[0]['args']['type'], 'The notice arguments should be stored.' );
	}

	/**
	 * Tests that init() only hooks the capture callback once.
	 *
	 * @ticket 65819
	 *
	 * @covers ::init
	 */
	public function test_init_only_hooks_once() {
		WP_Admin_Notice_Collector::init();

		remove_filter( 'wp_admin_notice_markup', array( 'WP_Admin_Notice_Collector', 'capture_notice' ) );

		WP_Admin_Notice_Collector::init();

		$this->assertFalse( has_filter( 'wp_admin_notice_markup', array( 'WP_Admin_Notice_Collector', 'capture_notice' ) ) );
	}

	/**
	 * Tests that the placeholder is added in front of the admin title.
	 *
	 * @ticket 65819
	 *
	 * @covers ::add_title_placeholder
	 */
	public function test_add_title_placeholder_prepends_placeholder() {
		$this->assertSame(
			'%%wp_admin_notice_count%%Dashboard &lsaquo; Test Blog &#8212; WordPress',
			WP_Admin_Notice_Collector::add_title_placeholder( 'Dashboard &lsaquo; Test Blog &#8212; WordPress', 'Dashboard' )
		);
	}

	/**
	 * Tests that the placeholder is replaced with the number of collected notices.
	 *
	 * @ticket 65819
	 *
	 * @covers ::inject_notice_count
	 *
	 * @dataProvider data_inject_notice_count
	 *
	 * @param int    $count    Number of collected notices.
	 * @param string $buffer   Page output.
	 * @param string $expected Expected page output.
	 */
	public function test_inject_notice_count( $count, $buffer, $expected ) {
		for ( $i = 1; $i <= $count; $i++ ) {
			WP_Admin_Notice_Collector::capture_notice( "<div>Notice {$i}</div>", "Notice {$i}", array( 'notice_id' => "wp-admin-notice-{$i}" ) );
		}

		$this->assertSame( $expected, WP_Admin_Notice_Collector::inject_notice_count( $buffer ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_inject_notice_count() {
		$title = '<title>%%wp_admin_notice_count%%Dashboard</title>';

		return array(
			'no notices'                   => array( 0, $title, '<title>Dashboard</title>' ),
			'one notice'                   => array( 1, $title, '<title>(1 notice) Dashboard</title>' ),
			'two notices'                  => array( 2, $title, '<title>(2 notices) Dashboard</title>' ),
			'eleven notices'               => array( 11, $title, '<title>(11 notices) Dashboard</title>' ),
			'output without a placeholder' => array( 2, '<title>Dashboard</title>', '<title>Dashboard</title>' ),
			'empty output'                 => array( 2, '', '' ),
			'empty output, no notices'     => array( 0, '', '' ),
		);
	}
}
