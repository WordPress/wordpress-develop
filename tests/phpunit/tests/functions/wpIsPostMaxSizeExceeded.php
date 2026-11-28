<?php

/**
 * Tests for the wp_is_post_max_size_exceeded() function.
 *
 * @group functions
 *
 * @covers ::wp_is_post_max_size_exceeded
 */
class Tests_Functions_wpIsPostMaxSizeExceeded extends WP_UnitTestCase {

	/**
	 * The post_max_size limit in bytes.
	 *
	 * @var int
	 */
	private $post_max_size;

	private $original_server;
	private $original_post;
	private $original_files;

	public function set_up() {
		parent::set_up();

		$this->post_max_size = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
		if ( $this->post_max_size <= 0 ) {
			$this->markTestSkipped( 'post_max_size is unlimited in this environment.' );
		}

		$this->original_server = $_SERVER;
		$this->original_post   = $_POST;
		$this->original_files  = $_FILES;

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array();
		$_FILES                    = array();
	}

	public function tear_down() {
		if ( null !== $this->original_server ) {
			$_SERVER = $this->original_server;
			$_POST   = $this->original_post;
			$_FILES  = $this->original_files;
		}

		parent::tear_down();
	}

	/**
	 * @ticket 18474
	 */
	public function test_returns_true_when_post_body_was_discarded() {
		$_SERVER['CONTENT_LENGTH'] = (string) ( $this->post_max_size + 1 );

		$this->assertTrue( wp_is_post_max_size_exceeded() );
	}

	/**
	 * @ticket 18474
	 */
	public function test_returns_false_when_content_length_is_within_limit() {
		$_SERVER['CONTENT_LENGTH'] = (string) $this->post_max_size;

		$this->assertFalse( wp_is_post_max_size_exceeded() );
	}

	/**
	 * @ticket 18474
	 */
	public function test_returns_false_when_post_data_is_present() {
		$_SERVER['CONTENT_LENGTH'] = (string) ( $this->post_max_size + 1 );
		$_POST                     = array( '_wpnonce' => 'abc' );

		$this->assertFalse( wp_is_post_max_size_exceeded() );
	}

	/**
	 * @ticket 18474
	 */
	public function test_returns_false_for_get_requests() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['CONTENT_LENGTH'] = (string) ( $this->post_max_size + 1 );

		$this->assertFalse( wp_is_post_max_size_exceeded() );
	}

	/**
	 * @ticket 18474
	 */
	public function test_returns_false_without_content_length() {
		unset( $_SERVER['CONTENT_LENGTH'] );

		$this->assertFalse( wp_is_post_max_size_exceeded() );
	}
}
