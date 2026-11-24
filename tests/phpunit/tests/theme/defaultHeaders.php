<?php
/**
 * Test register_default_headers() and unregister_default_headers().
 *
 * @group theme
 */
class Tests_Theme_DefaultHeaders extends WP_UnitTestCase {

	/**
	 * Backup of $_wp_default_headers.
	 *
	 * @var array|null
	 */
	protected $old_wp_default_headers;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		global $_wp_default_headers;
		$this->old_wp_default_headers = $_wp_default_headers;
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		global $_wp_default_headers;
		$_wp_default_headers = $this->old_wp_default_headers;
		parent::tear_down();
	}

	/**
	 * Tests that register_default_headers() adds headers to the global.
	 *
	 * @ticket 21876
	 *
	 * @covers ::register_default_headers
	 */
	public function test_register_default_headers_adds_to_global() {
		global $_wp_default_headers;

		$test_headers = array(
			'test_header_one' => array(
				'url'           => '%s/images/headers/test1.jpg',
				'thumbnail_url' => '%s/images/headers/test1-thumb.jpg',
				'description'   => 'Test Header One',
			),
		);

		register_default_headers( $test_headers );

		$this->assertIsArray( $_wp_default_headers, 'Global $_wp_default_headers should be an array.' );
		$this->assertArrayHasKey( 'test_header_one', $_wp_default_headers, 'Registered header key should be present in global array.' );
		$this->assertSame( $test_headers['test_header_one'], $_wp_default_headers['test_header_one'], 'Registered header data should match.' );
	}

	/**
	 * Tests that register_default_headers() merges additional headers with existing ones.
	 *
	 * @ticket 21876
	 *
	 * @covers ::register_default_headers
	 */
	public function test_register_default_headers_merges_with_existing() {
		global $_wp_default_headers;

		$first_batch = array(
			'first_batch_header' => array(
				'url'           => '%s/images/headers/first.jpg',
				'thumbnail_url' => '%s/images/headers/first-thumb.jpg',
				'description'   => 'First Batch Header',
			),
		);

		$second_batch = array(
			'second_batch_header' => array(
				'url'           => '%s/images/headers/second.jpg',
				'thumbnail_url' => '%s/images/headers/second-thumb.jpg',
				'description'   => 'Second Batch Header',
			),
		);

		register_default_headers( $first_batch );
		register_default_headers( $second_batch );

		$this->assertArrayHasKey( 'first_batch_header', $_wp_default_headers, 'First batch header should remain present after merge.' );
		$this->assertArrayHasKey( 'second_batch_header', $_wp_default_headers, 'Second batch header should be present after merge.' );
	}

	/**
	 * Tests that unregister_default_headers() removes a single header and returns true on success.
	 *
	 * @ticket 21876
	 *
	 * @covers ::unregister_default_headers
	 */
	public function test_unregister_default_headers_single_success() {
		global $_wp_default_headers;

		$header_id = 'test_header_to_remove';
		register_default_headers(
			array(
				$header_id => array(
					'url'           => '%s/images/headers/remove.jpg',
					'thumbnail_url' => '%s/images/headers/remove-thumb.jpg',
					'description'   => 'Header To Remove',
				),
			)
		);

		$this->assertArrayHasKey( $header_id, $_wp_default_headers, 'Header should be present before unregistering.' );

		$result = unregister_default_headers( $header_id );

		$this->assertTrue( $result, 'unregister_default_headers() should return true when single header is removed.' );
		$this->assertArrayNotHasKey( $header_id, $_wp_default_headers, 'Header should no longer exist in global array.' );
	}

	/**
	 * Tests that unregister_default_headers() returns false when removing a non-existent header.
	 *
	 * @ticket 21876
	 *
	 * @covers ::unregister_default_headers
	 */
	public function test_unregister_default_headers_single_failure() {
		$result = unregister_default_headers( 'non_existent_header_id_12345' );

		$this->assertFalse( $result, 'unregister_default_headers() should return false when header does not exist.' );
	}

	/**
	 * Tests that unregister_default_headers() removes multiple headers when passed an array and returns null.
	 *
	 * @ticket 21876
	 *
	 * @covers ::unregister_default_headers
	 */
	public function test_unregister_default_headers_array() {
		global $_wp_default_headers;

		$headers = array(
			'batch_remove_one' => array(
				'url'           => '%s/images/headers/one.jpg',
				'thumbnail_url' => '%s/images/headers/one-thumb.jpg',
				'description'   => 'Batch One',
			),
			'batch_remove_two' => array(
				'url'           => '%s/images/headers/two.jpg',
				'thumbnail_url' => '%s/images/headers/two-thumb.jpg',
				'description'   => 'Batch Two',
			),
		);

		register_default_headers( $headers );

		$this->assertArrayHasKey( 'batch_remove_one', $_wp_default_headers );
		$this->assertArrayHasKey( 'batch_remove_two', $_wp_default_headers );

		$result = unregister_default_headers( array( 'batch_remove_one', 'batch_remove_two' ) );

		$this->assertNull( $result, 'unregister_default_headers() should return null when given an array of headers.' );
		$this->assertArrayNotHasKey( 'batch_remove_one', $_wp_default_headers, 'First header should be removed.' );
		$this->assertArrayNotHasKey( 'batch_remove_two', $_wp_default_headers, 'Second header should be removed.' );
	}
}
