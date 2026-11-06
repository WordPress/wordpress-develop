<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Tests post status labels in the Find Posts modal.
 *
 * @group ajax
 * @group media
 *
 * @covers ::wp_ajax_find_posts
 */
class Tests_Ajax_wpAjaxFindPosts extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );
		$_POST['_ajax_nonce'] = wp_create_nonce( 'find-posts' );
		$_POST['ps']          = 'find_posts_status_test';
	}

	/**
	 * Tests that the existing labels for built-in post statuses are preserved.
	 *
	 * @ticket 65817
	 * @dataProvider data_builtin_post_statuses
	 *
	 * @param string $status         Post status.
	 * @param string $expected_label Expected status label.
	 */
	public function test_builtin_post_status_labels( $status, $expected_label ) {
		self::factory()->post->create(
			array(
				'post_title'  => 'find_posts_status_test',
				'post_status' => $status,
				'post_date'   => 'future' === $status ? gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) : '2020-01-01 00:00:00',
			)
		);

		$this->assertSame( array( $expected_label ), $this->get_status_labels() );
	}

	/**
	 * Provides built-in post statuses and their labels.
	 *
	 * @return array[]
	 */
	public static function data_builtin_post_statuses() {
		return array(
			'published' => array( 'publish', 'Published' ),
			'private'   => array( 'private', 'Published' ),
			'scheduled' => array( 'future', 'Scheduled' ),
			'pending'   => array( 'pending', 'Pending Review' ),
			'draft'     => array( 'draft', 'Draft' ),
		);
	}

	/**
	 * Tests that a custom status uses its registered label, escaped for HTML.
	 *
	 * @ticket 65817
	 * @dataProvider data_custom_post_status_labels
	 *
	 * @param string $label          Registered status label.
	 * @param string $expected_label Expected HTML-escaped label.
	 */
	public function test_custom_post_status_label( $label, $expected_label ) {
		register_post_status(
			'awaiting-review',
			array(
				'label'  => $label,
				'public' => true,
			)
		);

		self::factory()->post->create(
			array(
				'post_title'  => 'find_posts_status_test',
				'post_status' => 'awaiting-review',
			)
		);

		$this->assertSame( array( $expected_label ), $this->get_status_labels() );
	}

	/**
	 * Provides custom status labels and their escaped forms.
	 *
	 * @return array[]
	 */
	public static function data_custom_post_status_labels() {
		return array(
			'plain label' => array( 'Awaiting Review', 'Awaiting Review' ),
			'HTML label'  => array( '<b>Review</b> & approve', '&lt;b&gt;Review&lt;/b&gt; &amp; approve' ),
		);
	}

	/**
	 * Tests that custom statuses do not inherit a previous post's status label.
	 *
	 * @ticket 65817
	 */
	public function test_each_post_uses_its_own_status_label() {
		register_post_status(
			'awaiting-review',
			array(
				'label'  => 'Awaiting Review',
				'public' => true,
			)
		);
		register_post_status(
			'approved',
			array(
				'label'  => 'Approved',
				'public' => true,
			)
		);

		$statuses = array( 'publish', 'awaiting-review', 'approved', 'draft' );
		foreach ( $statuses as $index => $status ) {
			self::factory()->post->create(
				array(
					'post_title'  => 'find_posts_status_test',
					'post_status' => $status,
					'post_date'   => sprintf( '2020-01-%02d 00:00:00', 4 - $index ),
				)
			);
		}

		$this->assertSame(
			array( 'Published', 'Awaiting Review', 'Approved', 'Draft' ),
			$this->get_status_labels()
		);
	}

	/**
	 * Runs the AJAX handler and extracts the status column from each result row.
	 *
	 * @return string[] Status labels, in result order.
	 */
	private function get_status_labels() {
		try {
			$this->_handleAjax( 'find_posts' );
		} catch ( WPAjaxDieContinueException $e ) {
			// The handler stops execution after sending its JSON response.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );

		preg_match_all( '/<td class="no-break">([^<]*) <\/td><\/tr>/', $response['data'], $matches );

		return $matches[1];
	}
}
