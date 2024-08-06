<?php
/**
 * @group query
 */
class Tests_Query_CommentCount extends WP_UnitTestCase {
	public static $post_ids = array();
	public $q;
	public static $post_type = 'page'; // Can be anything.

	public function set_up() {
		parent::set_up();
		unset( $this->q );
		$this->q = new WP_Query();
	}

	public function tear_down() {
		unset( $this->q );
		parent::tear_down();
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$post_id             = $factory->post->create(
			array(
				'post_content' => 1 . rand_str() . ' about',
				'post_type'    => self::$post_type,
			)
		);
		self::$post_ids[1][] = $post_id;
		$factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		$post_id             = $factory->post->create(
			array(
				'post_content' => 1 . rand_str() . ' about',
				'post_type'    => self::$post_type,
			)
		);
		self::$post_ids[4][] = $post_id;
		for ( $i = 0; $i < 4; $i++ ) {
			$factory->comment->create( array( 'comment_post_ID' => $post_id ) );
		}

		$post_id             = $factory->post->create(
			array(
				'post_content' => 1 . rand_str() . ' about',
				'post_type'    => self::$post_type,
			)
		);
		self::$post_ids[5][] = $post_id;
		for ( $i = 0; $i < 5; $i++ ) {
			$factory->comment->create( array( 'comment_post_ID' => $post_id ) );
		}

		$post_id             = $factory->post->create(
			array(
				'post_content' => 1 . rand_str() . ' about',
				'post_type'    => self::$post_type,
			)
		);
		self::$post_ids[5][] = $post_id;
		for ( $i = 0; $i < 5; $i++ ) {
			$factory->comment->create( array( 'comment_post_ID' => $post_id ) );
		}
	}

	private function helper_get_found_post_ids() {
		return wp_list_pluck( $this->q->posts, 'ID' );
	}

	public function test_operator_equals() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 4,
				'compare' => '=',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = self::$post_ids[4];

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_operator_greater_than() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 4,
				'compare' => '>',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = self::$post_ids[5];

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_operator_greater_than_no_results() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 6,
				'compare' => '>',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();

		$this->assertSameSets( $found_post_ids, $expected );
	}
	public function test_operator_less_than() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 6,
				'compare' => '<',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();
		foreach ( self::$post_ids[1] as $expected_id ) {
			$expected[] = $expected_id;
		}
		foreach ( self::$post_ids[4] as $expected_id ) {
			$expected[] = $expected_id;
		}
		foreach ( self::$post_ids[5] as $expected_id ) {
			$expected[] = $expected_id;
		}

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_operator_less_than_no_results() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 1,
				'compare' => '<',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();

		$this->assertSameSets( $found_post_ids, $expected );
	}


	public function test_operator_not_equal() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 15,
				'compare' => '!=',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();
		foreach ( self::$post_ids[1] as $expected_id ) {
			$expected[] = $expected_id;
		}
		foreach ( self::$post_ids[4] as $expected_id ) {
			$expected[] = $expected_id;
		}
		foreach ( self::$post_ids[5] as $expected_id ) {
			$expected[] = $expected_id;
		}

		$this->assertSameSets( $found_post_ids, $expected );

	}
	public function test_operator_equal_or_greater_than() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 4,
				'compare' => '>=',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();
		foreach ( self::$post_ids[4] as $expected_id ) {
			$expected[] = $expected_id;
		}
		foreach ( self::$post_ids[5] as $expected_id ) {
			$expected[] = $expected_id;
		}

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_operator_equal_or_greater_than_no_results() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 7,
				'compare' => '>=',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_operator_equal_or_less_than() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 4,
				'compare' => '<=',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();
		foreach ( self::$post_ids[1] as $expected_id ) {
			$expected[] = $expected_id;
		}
		foreach ( self::$post_ids[4] as $expected_id ) {
			$expected[] = $expected_id;
		}

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_operator_equal_or_less_than_no_results() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 0,
				'compare' => '<=',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_invalid_operator_should_fall_back_on_equals() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 5,
				'compare' => '@',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();
		foreach ( self::$post_ids[5] as $expected_id ) {
			$expected[] = $expected_id;
		}

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_wrong_count_no_results() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value'   => 'abc',
				'compare' => '=',
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_no_operator_no_results() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => array(
				'value' => 5,
			),
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = self::$post_ids[5];

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_empty_non_numeric_string_should_be_ignored() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => '',
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = array();
		foreach ( self::$post_ids[1] as $expected_id ) {
			$expected[] = $expected_id;
		}
		foreach ( self::$post_ids[4] as $expected_id ) {
			$expected[] = $expected_id;
		}
		foreach ( self::$post_ids[5] as $expected_id ) {
			$expected[] = $expected_id;
		}

		$this->assertSameSets( $found_post_ids, $expected );
	}

	public function test_simple_count() {
		$args = array(
			'post_type'      => self::$post_type,
			'posts_per_page' => -1,
			'comment_count'  => 5,
		);
		$this->q->query( $args );

		$found_post_ids = $this->helper_get_found_post_ids();

		$expected = self::$post_ids[5];

		$this->assertSameSets( $found_post_ids, $expected );
	}
}

