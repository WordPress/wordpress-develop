<?php

/**
 * @group query
 */
class Tests_Query_PostsPerPage extends WP_UnitTestCase {
	public $q;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// More than posts_per_page default of 10
		// This number is verified in the 'test_posts_per_page_all' method.
		self::factory()->post->create_many( 11 );
	}

	public function set_up() {
		parent::set_up();
		unset( $this->q );
		$this->q = new WP_Query();
	}

	public function _get_post_count( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'fields' => 'ids',
			)
		);

		return count( $this->q->query( $args ) );
	}

	public function test_posts_per_page_integer_positive() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => 2,
			)
		);

		$this->assertSame( 2, $count );
	}

	/**
	 * @ticket 24142
	 */
	public function test_posts_per_page_integer_zero() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => 0,
			)
		);

		$this->assertSame( 0, $count );
	}

	public function test_posts_per_page_string_numeric() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => '2',
			)
		);

		$this->assertSame( 2, $count );
	}

	public function test_posts_per_page_string_non_numeric() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => 'foo',
			)
		);

		$this->assertSame( 10, $count );
	}

	public function test_posts_per_page_boolean_true() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => true,
			)
		);

		$this->assertSame( 1, $count );
	}

	/**
	 * @ticket 24142
	 */
	public function test_posts_per_page_boolean_false() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => false,
			)
		);

		$this->assertSame( 0, $count );
	}

	public function test_posts_per_page_null() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => null,
			)
		);

		$this->assertSame( 10, $count );
	}

	public function test_posts_per_page_empty_string() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => '',
			)
		);

		$this->assertSame( 10, $count );
	}

	public function test_posts_per_page_array() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => array(),
			)
		);

		$this->assertSame( 10, $count );
	}

	public function test_posts_per_page_negative() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => -2,
			)
		);

		$this->assertSame( 2, $count );
	}

	public function test_posts_per_page_all() {
		$count = $this->_get_post_count(
			array(
				'posts_per_page' => -1,
			)
		);

		$this->assertSame( 11, $count );
	}
}
