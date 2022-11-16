<?php

// Test the output of Comment Querying functions.

/**
 * @group comment
 */
class Tests_Comment_Query extends WP_UnitTestCase {
	protected static $post_id;
	protected $comment_id;

	/**
	 * Temporary storage for comment exclusions to allow a filter to access these.
	 *
	 * Used in the following tests:
	 * - `test_comment_clauses_prepend_callback_should_be_respected_when_filling_descendants()`
	 * - `test_comment_clauses_append_callback_should_be_respected_when_filling_descendants()`
	 *
	 * @var array
	 */
	private $to_exclude;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	public function tear_down() {
		unset( $this->to_exclude );
		parent::tear_down();
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_query() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3, $c4, $c5 ), $found );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_query_post_id_0() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'post_id' => 0,
				'fields'  => 'ids',
			)
		);

		$this->assertSameSets( array( $c1 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_query_type_empty_string() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => '',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3, $c4, $c5 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_query_type_comment() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => 'comment',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1 ), $found );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_query_type_pingback() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => 'pingback',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c2, $c3 ), $found );

	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_query_type_trackback() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => 'trackback',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c2, $c3 ), $found );

	}

	/**
	 * 'pings' is an alias for 'trackback' + 'pingback'.
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_query_type_pings() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => 'pings',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c2, $c3 ), $found );
	}

	/**
	 * Comments and custom
	 *
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_array_comments_and_custom() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);
		$c6 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => array( 'comments', 'mario' ),
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c4, $c6 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_not__in_array_custom() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);
		$c6 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type__not_in' => array( 'luigi' ),
				'fields'       => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3, $c4, $c6 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type__in_array_and_not_type_array_custom() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);
		$c6 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type__in'     => array( 'comments' ),
				'type__not_in' => array( 'luigi' ),
				'fields'       => 'ids',
			)
		);

		$this->assertSameSets( array( $c1 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_array_and_type__not_in_array_custom() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);
		$c6 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'         => array( 'pings' ),
				'type__not_in' => array( 'mario' ),
				'fields'       => 'ids',
			)
		);

		$this->assertSameSets( array( $c2, $c3 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type__not_in_custom() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);
		$c6 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type__not_in' => 'luigi',
				'fields'       => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3, $c4, $c6 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_array_comments_and_pings() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'mario',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'luigi',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => array( 'comments', 'pings' ),
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_array_comment_pings() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => array( 'comment', 'pings' ),
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_array_pingback() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => array( 'pingback' ),
				'fields' => 'ids',
			)
		);

		$this->assertSame( array( $c2 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_array_custom_pingpack() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => array( 'peach', 'pingback' ),
				'fields' => 'ids',
			)
		);

		$this->assertSame( array( $c2 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_array_pings() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => array( 'pings' ),
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c2, $c3 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_status_approved_array_comment_pings() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
				'comment_type'     => 'pingback',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'status' => 'approve',
				'type'   => array( 'pings' ),
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c3, $c2 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_array_trackback() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => array( 'trackback' ),
				'fields' => 'ids',
			)
		);

		$this->assertSame( array( $c2 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_array_custom_trackback() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'type'   => array( 'toad', 'trackback' ),
				'fields' => 'ids',
			)
		);

		$this->assertSame( array( $c2 ), $found );
	}

	/**
	 * @ticket 12668
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_type_array_pings_approved() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_type'     => 'trackback',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
				'comment_type'     => 'trackback',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'status' => 'approve',
				'type'   => array( 'pings' ),
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c3, $c2 ), $found );
	}

	/**
	 * @ticket 29612
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_status_empty_string() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'spam',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'status' => '',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2 ), $found );
	}

	/**
	 * @ticket 21101
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_status_hold() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'status' => 'hold',
				'fields' => 'ids',
			)
		);

		$this->assertSame( array( $c2 ), $found );
	}

	/**
	 * @ticket 21101
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_status_approve() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'status' => 'approve',
				'fields' => 'ids',
			)
		);

		$this->assertSame( array( $c1 ), $found );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_status_custom() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'foo',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'foo1',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'status' => 'foo',
				'fields' => 'ids',
			)
		);

		$this->assertSame( array( $c2 ), $found );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_status_all() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'foo',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'status' => 'all',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c3 ), $found );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_status_default_to_all() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'foo',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c3 ), $found );
	}

	/**
	 * @ticket 29612
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_status_comma_any() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'foo',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'status' => 'any',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3 ), $found );
	}

	/**
	 * @ticket 29612
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_status_comma_separated() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'foo',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'status' => 'approve,foo,bar',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2 ), $found );
	}

	/**
	 * @ticket 29612
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_status_array() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'foo',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'status' => array( 'approve', 'foo', 'bar' ),
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2 ), $found );
	}

	/**
	 * @ticket 35478
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_multiple_post_fields_should_all_be_respected() {
		$posts = array();

		$posts[] = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_author' => 3,
			)
		);

		$posts[] = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => 4,
			)
		);

		$posts[] = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => 3,
			)
		);

		$comments = array();
		foreach ( $posts as $post ) {
			$comments[] = self::factory()->comment->create(
				array(
					'comment_post_ID' => $post,
				)
			);
		}

		$q = new WP_Comment_Query(
			array(
				'post_status' => 'draft',
				'post_author' => 3,
				'fields'      => 'ids',
			)
		);

		$this->assertSame( array( $comments[2] ), $q->comments );
	}

	/**
	 * @covers ::get_comments
	 */
	public function test_get_comments_for_post() {
		$limit = 5;

		$post_id = self::factory()->post->create();
		self::factory()->comment->create_post_comments( $post_id, $limit );

		$comments = get_comments( array( 'post_id' => $post_id ) );
		$this->assertCount( $limit, $comments );
		foreach ( $comments as $comment ) {
			$this->assertEquals( $post_id, $comment->comment_post_ID );
		}

		$post_id2 = self::factory()->post->create();
		self::factory()->comment->create_post_comments( $post_id2, $limit );

		$comments = get_comments( array( 'post_id' => $post_id2 ) );
		$this->assertCount( $limit, $comments );
		foreach ( $comments as $comment ) {
			$this->assertEquals( $post_id2, $comment->comment_post_ID );
		}

		$post_id3 = self::factory()->post->create();
		self::factory()->comment->create_post_comments( $post_id3, $limit, array( 'comment_approved' => '0' ) );

		$comments = get_comments( array( 'post_id' => $post_id3 ) );
		$this->assertCount( $limit, $comments );
		foreach ( $comments as $comment ) {
			$this->assertEquals( $post_id3, $comment->comment_post_ID );
		}

		$comments = get_comments(
			array(
				'post_id' => $post_id3,
				'status'  => 'hold',
			)
		);
		$this->assertCount( $limit, $comments );
		foreach ( $comments as $comment ) {
			$this->assertEquals( $post_id3, $comment->comment_post_ID );
		}

		$comments = get_comments(
			array(
				'post_id' => $post_id3,
				'status'  => 'approve',
			)
		);
		$this->assertCount( 0, $comments );

		self::factory()->comment->create_post_comments( $post_id3, $limit, array( 'comment_approved' => '1' ) );
		$comments = get_comments( array( 'post_id' => $post_id3 ) );
		$this->assertCount( $limit * 2, $comments );
		foreach ( $comments as $comment ) {
			$this->assertEquals( $post_id3, $comment->comment_post_ID );
		}
	}

	/**
	 * @ticket 21003
	 *
	 * @covers ::get_comments
	 */
	public function test_orderby_meta() {
		$comment_id  = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$comment_id2 = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		$comment_id3 = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		add_comment_meta( $comment_id, 'key', 'value1', true );
		add_comment_meta( $comment_id, 'key1', 'value1', true );
		add_comment_meta( $comment_id, 'key3', 'value3', true );
		add_comment_meta( $comment_id2, 'key', 'value2', true );
		add_comment_meta( $comment_id2, 'key2', 'value2', true );
		add_comment_meta( $comment_id3, 'key3', 'value3', true );

		$comments = get_comments(
			array(
				'meta_key' => 'key',
				'orderby'  => array( 'key' ),
			)
		);
		$this->assertCount( 2, $comments );
		$this->assertEquals( $comment_id2, $comments[0]->comment_ID );
		$this->assertEquals( $comment_id, $comments[1]->comment_ID );

		$comments = get_comments(
			array(
				'meta_key' => 'key',
				'orderby'  => array( 'meta_value' ),
			)
		);
		$this->assertCount( 2, $comments );
		$this->assertEquals( $comment_id2, $comments[0]->comment_ID );
		$this->assertEquals( $comment_id, $comments[1]->comment_ID );

		$comments = get_comments(
			array(
				'meta_key' => 'key',
				'orderby'  => array( 'key' ),
				'order'    => 'ASC',
			)
		);
		$this->assertCount( 2, $comments );
		$this->assertEquals( $comment_id, $comments[0]->comment_ID );
		$this->assertEquals( $comment_id2, $comments[1]->comment_ID );

		$comments = get_comments(
			array(
				'meta_key' => 'key',
				'orderby'  => array( 'meta_value' ),
				'order'    => 'ASC',
			)
		);
		$this->assertCount( 2, $comments );
		$this->assertEquals( $comment_id, $comments[0]->comment_ID );
		$this->assertEquals( $comment_id2, $comments[1]->comment_ID );

		$comments = get_comments(
			array(
				'meta_value' => 'value3',
				'orderby'    => array( 'key' ),
			)
		);
		$this->assertEquals( array( $comment_id3, $comment_id ), wp_list_pluck( $comments, 'comment_ID' ) );

		$comments = get_comments(
			array(
				'meta_value' => 'value3',
				'orderby'    => array( 'meta_value' ),
			)
		);
		$this->assertEquals( array( $comment_id3, $comment_id ), wp_list_pluck( $comments, 'comment_ID' ) );

		// 'value1' is present on two different keys for $comment_id,
		// yet we should get only one instance of that comment in the results.
		$comments = get_comments(
			array(
				'meta_value' => 'value1',
				'orderby'    => array( 'key' ),
			)
		);
		$this->assertCount( 1, $comments );

		$comments = get_comments(
			array(
				'meta_value' => 'value1',
				'orderby'    => array( 'meta_value' ),
			)
		);
		$this->assertCount( 1, $comments );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_clause_key() {
		$comments = self::factory()->comment->create_many( 3 );
		add_comment_meta( $comments[0], 'foo', 'aaa' );
		add_comment_meta( $comments[1], 'foo', 'zzz' );
		add_comment_meta( $comments[2], 'foo', 'jjj' );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'     => 'ids',
				'meta_query' => array(
					'foo_key' => array(
						'key'     => 'foo',
						'compare' => 'EXISTS',
					),
				),
				'orderby'    => 'foo_key',
				'order'      => 'DESC',
			)
		);

		$this->assertSame( array( $comments[1], $comments[2], $comments[0] ), $found );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_clause_key_as_secondary_sort() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_date' => '2015-01-28 03:00:00',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_date' => '2015-01-28 05:00:00',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_date' => '2015-01-28 03:00:00',
			)
		);

		add_comment_meta( $c1, 'foo', 'jjj' );
		add_comment_meta( $c2, 'foo', 'zzz' );
		add_comment_meta( $c3, 'foo', 'aaa' );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'     => 'ids',
				'meta_query' => array(
					'foo_key' => array(
						'key'     => 'foo',
						'compare' => 'EXISTS',
					),
				),
				'orderby'    => array(
					'comment_date' => 'asc',
					'foo_key'      => 'asc',
				),
			)
		);

		$this->assertSame( array( $c3, $c1, $c2 ), $found );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_more_than_one_clause_key() {
		$comments = self::factory()->comment->create_many( 3 );

		add_comment_meta( $comments[0], 'foo', 'jjj' );
		add_comment_meta( $comments[1], 'foo', 'zzz' );
		add_comment_meta( $comments[2], 'foo', 'jjj' );
		add_comment_meta( $comments[0], 'bar', 'aaa' );
		add_comment_meta( $comments[1], 'bar', 'ccc' );
		add_comment_meta( $comments[2], 'bar', 'bbb' );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'     => 'ids',
				'meta_query' => array(
					'foo_key' => array(
						'key'     => 'foo',
						'compare' => 'EXISTS',
					),
					'bar_key' => array(
						'key'     => 'bar',
						'compare' => 'EXISTS',
					),
				),
				'orderby'    => array(
					'foo_key' => 'asc',
					'bar_key' => 'desc',
				),
			)
		);

		$this->assertSame( array( $comments[2], $comments[0], $comments[1] ), $found );
	}

	/**
	 * @ticket 32081
	 *
	 * @covers WP_Comment_Query::__construct
	 * @covers WP_Comment_Query::get_comments
	 */
	public function test_meta_query_should_work_with_comment__in() {
		$comments = self::factory()->comment->create_many( 3 );

		add_comment_meta( $comments[0], 'foo', 'jjj' );
		add_comment_meta( $comments[1], 'foo', 'zzz' );
		add_comment_meta( $comments[2], 'foo', 'jjj' );

		$q = new WP_Comment_Query(
			array(
				'comment__in' => array( $comments[1], $comments[2] ),
				'meta_query'  => array(
					array(
						'key'   => 'foo',
						'value' => 'jjj',
					),
				),
				'fields'      => 'ids',
			)
		);

		$this->assertSame( array( $comments[2] ), $q->get_comments() );
	}

	/**
	 * @ticket 32081
	 *
	 * @covers WP_Comment_Query::__construct
	 * @covers WP_Comment_Query::get_comments
	 */
	public function test_meta_query_should_work_with_comment__not_in() {
		$comments = self::factory()->comment->create_many( 3 );

		add_comment_meta( $comments[0], 'foo', 'jjj' );
		add_comment_meta( $comments[1], 'foo', 'zzz' );
		add_comment_meta( $comments[2], 'foo', 'jjj' );

		$q = new WP_Comment_Query(
			array(
				'comment__not_in' => array( $comments[1], $comments[2] ),
				'meta_query'      => array(
					array(
						'key'   => 'foo',
						'value' => 'jjj',
					),
				),
				'fields'          => 'ids',
			)
		);

		$this->assertSame( array( $comments[0] ), $q->get_comments() );
	}

	/**
	 * @ticket 27064
	 *
	 * @covers ::get_comments
	 */
	public function test_get_comments_by_user() {
		$users = self::factory()->user->create_many( 2 );
		self::factory()->comment->create(
			array(
				'user_id'          => $users[0],
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		self::factory()->comment->create(
			array(
				'user_id'          => $users[0],
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		self::factory()->comment->create(
			array(
				'user_id'          => $users[1],
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$comments = get_comments(
			array(
				'user_id' => $users[0],
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
			)
		);

		$this->assertCount( 2, $comments );
		$this->assertEquals( $users[0], $comments[0]->user_id );
		$this->assertEquals( $users[0], $comments[1]->user_id );

		$comments = get_comments(
			array(
				'user_id' => $users,
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
			)
		);

		$this->assertCount( 3, $comments );
		$this->assertEquals( $users[0], $comments[0]->user_id );
		$this->assertEquals( $users[0], $comments[1]->user_id );
		$this->assertEquals( $users[1], $comments[2]->user_id );

	}

	/**
	 * @ticket 35377
	 *
	 * @covers ::get_comments
	 */
	public function test_get_comments_by_author_url() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_author'       => 'bar',
				'comment_author_email' => 'bar@example.com',
				'comment_author_url'   => 'http://foo.bar',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_author'       => 'bar',
				'comment_author_email' => 'bar@example.com',
				'comment_author_url'   => 'http://foo.bar',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_author'       => 'bar',
				'comment_author_email' => 'bar@example.com',
				'comment_author_url'   => 'http://foo.bar/baz',
			)
		);

		$comments = get_comments(
			array(
				'author_url' => 'http://foo.bar',
				'fields'     => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2 ), $comments );
	}

	/**
	 * @ticket 28434
	 *
	 * @covers ::get_comments
	 */
	public function test_fields_ids_query() {
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$comment_3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);

		// Ensure we are dealing with integers, and not objects.
		$this->assertIsInt( $comment_1 );
		$this->assertIsInt( $comment_2 );
		$this->assertIsInt( $comment_3 );

		$comment_ids = get_comments( array( 'fields' => 'ids' ) );
		$this->assertCount( 3, $comment_ids );
		$this->assertSameSets( array( $comment_1, $comment_2, $comment_3 ), $comment_ids );
	}

	/**
	 * @ticket 29189
	 *
	 * @covers ::get_comments
	 */
	public function test_fields_comment__in() {
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$comment_3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);

		$comment_ids = get_comments(
			array(
				'fields'      => 'ids',
				'comment__in' => array( $comment_1, $comment_3 ),
			)
		);

		$this->assertSameSets( array( $comment_1, $comment_3 ), $comment_ids );
	}

	/**
	 * @ticket 29189
	 *
	 * @covers ::get_comments
	 */
	public function test_fields_comment__not_in() {
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$comment_3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);

		$comment_ids = get_comments(
			array(
				'fields'          => 'ids',
				'comment__not_in' => array( $comment_2, $comment_3 ),
			)
		);

		$this->assertSameSets( array( $comment_1 ), $comment_ids );
	}

	/**
	 * @ticket 29189
	 *
	 * @covers ::get_comments
	 */
	public function test_fields_post__in() {
		$p1 = self::factory()->post->create();
		$p2 = self::factory()->post->create();
		$p3 = self::factory()->post->create();

		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p1,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p2,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p3,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);

		$comment_ids = get_comments(
			array(
				'fields'   => 'ids',
				'post__in' => array( $p1, $p2 ),
			)
		);

		$this->assertSameSets( array( $c1, $c2 ), $comment_ids );
	}

	/**
	 * @ticket 29189
	 *
	 * @covers ::get_comments
	 */
	public function test_fields_post__not_in() {
		$p1 = self::factory()->post->create();
		$p2 = self::factory()->post->create();
		$p3 = self::factory()->post->create();

		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p1,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p2,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p3,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);

		$comment_ids = get_comments(
			array(
				'fields'       => 'ids',
				'post__not_in' => array( $p1, $p2 ),
			)
		);

		$this->assertSameSets( array( $c3 ), $comment_ids );
	}

	/**
	 * @ticket 29885
	 *
	 * @covers ::get_comments
	 */
	public function test_fields_post_author__in() {
		$author_id1 = 105;
		$author_id2 = 106;

		$p1 = self::factory()->post->create( array( 'post_author' => $author_id1 ) );
		$p2 = self::factory()->post->create( array( 'post_author' => $author_id1 ) );
		$p3 = self::factory()->post->create( array( 'post_author' => $author_id2 ) );

		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p1,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p2,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p3,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);

		$comment_ids = get_comments(
			array(
				'fields'          => 'ids',
				'post_author__in' => array( $author_id1 ),
			)
		);

		$this->assertSameSets( array( $c1, $c2 ), $comment_ids );
	}

	/**
	 * @ticket 29885
	 *
	 * @covers ::get_comments
	 */
	public function test_fields_post_author__not_in() {
		$author_id1 = 111;
		$author_id2 = 112;

		$p1 = self::factory()->post->create( array( 'post_author' => $author_id1 ) );
		$p2 = self::factory()->post->create( array( 'post_author' => $author_id1 ) );
		$p3 = self::factory()->post->create( array( 'post_author' => $author_id2 ) );

		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p1,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p2,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p3,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);

		$comment_ids = get_comments(
			array(
				'fields'              => 'ids',
				'post_author__not_in' => array( $author_id1 ),
			)
		);

		$this->assertSameSets( array( $c3 ), $comment_ids );
	}

	/**
	 * @ticket 29885
	 *
	 * @covers ::get_comments
	 */
	public function test_fields_author__in() {
		$p1 = self::factory()->post->create();
		$p2 = self::factory()->post->create();
		$p3 = self::factory()->post->create();
		$p4 = self::factory()->post->create();

		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p1,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p1,
				'user_id'          => 2,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p2,
				'user_id'          => 3,
				'comment_approved' => '1',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p4,
				'user_id'          => 4,
				'comment_approved' => '1',
			)
		);

		$comment_ids = get_comments(
			array(
				'fields'     => 'ids',
				'author__in' => array( 1, 3 ),
			)
		);

		$this->assertSameSets( array( $c1, $c3 ), $comment_ids );
	}

	/**
	 * @ticket 29885
	 *
	 * @covers ::get_comments
	 */
	public function test_fields_author__not_in() {
		$p1 = self::factory()->post->create();
		$p2 = self::factory()->post->create();
		$p3 = self::factory()->post->create();
		$p4 = self::factory()->post->create();

		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p1,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p1,
				'user_id'          => 2,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p2,
				'user_id'          => 3,
				'comment_approved' => '1',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p4,
				'user_id'          => 4,
				'comment_approved' => '1',
			)
		);

		$comment_ids = get_comments(
			array(
				'fields'         => 'ids',
				'author__not_in' => array( 1, 2 ),
			)
		);

		$this->assertSameSets( array( $c3, $c4 ), $comment_ids );
	}

	/**
	 * @ticket 19623
	 *
	 * @covers ::get_comments
	 */
	public function test_get_comments_with_status_all() {
		$comment_1           = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$comment_2           = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$comment_3           = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '0',
			)
		);
		$comments_approved_1 = get_comments( array( 'status' => 'all' ) );

		$comment_ids = get_comments( array( 'fields' => 'ids' ) );
		$this->assertSameSets( array( $comment_1, $comment_2, $comment_3 ), $comment_ids );
	}

	/**
	 * @ticket 19623
	 *
	 * @covers ::get_comments
	 */
	public function test_get_comments_with_include_unapproved_user_id() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '0',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 6,
				'comment_approved' => '0',
			)
		);

		$found = get_comments(
			array(
				'fields'             => 'ids',
				'include_unapproved' => 1,
				'status'             => 'approve',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3 ), $found );
	}

	/**
	 * @ticket 19623
	 *
	 * @covers ::get_comments
	 */
	public function test_get_comments_with_include_unapproved_user_id_array() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '0',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 6,
				'comment_approved' => '0',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 8,
				'comment_approved' => '0',
			)
		);

		$found = get_comments(
			array(
				'fields'             => 'ids',
				'include_unapproved' => array( 1, 8 ),
				'status'             => 'approve',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3, $c5 ), $found );
	}

	/**
	 * @ticket 19623
	 *
	 * @covers ::get_comments
	 */
	public function test_get_comments_with_include_unapproved_user_id_comma_separated() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 1,
				'comment_approved' => '0',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 6,
				'comment_approved' => '0',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 8,
				'comment_approved' => '0',
			)
		);

		$found = get_comments(
			array(
				'fields'             => 'ids',
				'include_unapproved' => '1,8',
				'status'             => 'approve',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3, $c5 ), $found );
	}

	/**
	 * @ticket 19623
	 *
	 * @covers ::get_comments
	 */
	public function test_get_comments_with_include_unapproved_author_email() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 0,
				'comment_approved'     => '1',
				'comment_author'       => 'foo',
				'comment_author_email' => 'foo@example.com',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 0,
				'comment_approved'     => '0',
				'comment_author'       => 'foo',
				'comment_author_email' => 'foo@example.com',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 0,
				'comment_approved'     => '0',
				'comment_author'       => 'foo',
				'comment_author_email' => 'bar@example.com',
			)
		);

		$found = get_comments(
			array(
				'fields'             => 'ids',
				'include_unapproved' => 'foo@example.com',
				'status'             => 'approve',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3 ), $found );
	}

	/**
	 * @ticket 19623
	 *
	 * @covers ::get_comments
	 */
	public function test_get_comments_with_include_unapproved_mixed_array() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 0,
				'comment_approved'     => '1',
				'comment_author'       => 'foo',
				'comment_author_email' => 'foo@example.com',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 0,
				'comment_approved'     => '0',
				'comment_author'       => 'foo',
				'comment_author_email' => 'foo@example.com',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 0,
				'comment_approved'     => '0',
				'comment_author'       => 'foo',
				'comment_author_email' => 'bar@example.com',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 4,
				'comment_approved'     => '0',
				'comment_author'       => 'foo',
				'comment_author_email' => 'bar@example.com',
			)
		);

		$found = get_comments(
			array(
				'fields'             => 'ids',
				'include_unapproved' => array( 'foo@example.com', 4 ),
				'status'             => 'approve',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3, $c5 ), $found );
	}

	/**
	 * @ticket 19623
	 *
	 * @covers ::get_comments
	 */
	public function test_get_comments_with_include_unapproved_mixed_comma_separated() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => 7,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 0,
				'comment_approved'     => '1',
				'comment_author'       => 'foo',
				'comment_author_email' => 'foo@example.com',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 0,
				'comment_approved'     => '0',
				'comment_author'       => 'foo',
				'comment_author_email' => 'foo@example.com',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 0,
				'comment_approved'     => '0',
				'comment_author'       => 'foo',
				'comment_author_email' => 'bar@example.com',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 4,
				'comment_approved'     => '0',
				'comment_author'       => 'foo',
				'comment_author_email' => 'bar@example.com',
			)
		);

		$found = get_comments(
			array(
				'fields'             => 'ids',
				'include_unapproved' => 'foo@example.com, 4',
				'status'             => 'approve',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3, $c5 ), $found );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_search() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 4,
				'comment_approved'     => '0',
				'comment_author'       => 'foo',
				'comment_author_email' => 'bar@example.com',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 4,
				'comment_approved'     => '0',
				'comment_author'       => 'bar',
				'comment_author_email' => 'foo@example.com',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 4,
				'comment_approved'     => '0',
				'comment_author'       => 'bar',
				'comment_author_email' => 'bar@example.com',
				'comment_author_url'   => 'http://foo.bar',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 4,
				'comment_approved'     => '0',
				'comment_author'       => 'bar',
				'comment_author_email' => 'bar@example.com',
				'comment_author_url'   => 'http://example.com',
				'comment_author_IP'    => 'foo.bar',
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 4,
				'comment_approved'     => '0',
				'comment_author'       => 'bar',
				'comment_author_email' => 'bar@example.com',
				'comment_author_url'   => 'http://example.com',
				'comment_content'      => 'Nice foo comment',
			)
		);
		$c6 = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'user_id'              => 4,
				'comment_approved'     => '0',
				'comment_author'       => 'bar',
				'comment_author_email' => 'bar@example.com',
				'comment_author_url'   => 'http://example.com',
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'search' => 'foo',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $c1, $c2, $c3, $c4, $c5 ), $found );
	}

	/**
	 * @ticket 35513
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_search_false_should_be_ignored() {
		$q = new WP_Comment_Query();
		$q->query(
			array(
				'search' => false,
			)
		);
		$this->assertStringNotContainsString( 'comment_author LIKE', $q->request );
	}

	/**
	 * @ticket 35513
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_search_null_should_be_ignored() {
		$q = new WP_Comment_Query();
		$q->query(
			array(
				'search' => null,
			)
		);
		$this->assertStringNotContainsString( 'comment_author LIKE', $q->request );
	}

	/**
	 * @ticket 35513
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_search_empty_string_should_be_ignored() {
		$q = new WP_Comment_Query();
		$q->query(
			array(
				'search' => false,
			)
		);
		$this->assertStringNotContainsString( 'comment_author LIKE', $q->request );
	}

	/**
	 * @ticket 35513
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_search_int_0_should_not_be_ignored() {
		global $wpdb;
		$q = new WP_Comment_Query();
		$q->query(
			array(
				'search' => 0,
			)
		);
		$this->assertStringContainsString( "comment_author LIKE '%0%'", $wpdb->remove_placeholder_escape( $q->request ) );
	}

	/**
	 * @ticket 35513
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_search_string_0_should_not_be_ignored() {
		global $wpdb;
		$q = new WP_Comment_Query();
		$q->query(
			array(
				'search' => '0',
			)
		);
		$this->assertStringContainsString( "comment_author LIKE '%0%'", $wpdb->remove_placeholder_escape( $q->request ) );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_default() {
		global $wpdb;

		$q = new WP_Comment_Query();
		$q->query( array() );

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_date_gmt", $q->request );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_single() {
		global $wpdb;

		$q = new WP_Comment_Query();
		$q->query(
			array(
				'orderby' => 'comment_agent',
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent", $q->request );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_single_invalid() {
		global $wpdb;

		$q = new WP_Comment_Query();
		$q->query(
			array(
				'orderby' => 'foo',
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_date_gmt", $q->request );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_space_separated() {
		global $wpdb;

		$q = new WP_Comment_Query();
		$q->query(
			array(
				'orderby' => 'comment_agent comment_approved',
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent DESC, $wpdb->comments.comment_approved DESC", $q->request );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_comma_separated() {
		global $wpdb;

		$q = new WP_Comment_Query();
		$q->query(
			array(
				'orderby' => 'comment_agent, comment_approved',
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent DESC, $wpdb->comments.comment_approved DESC", $q->request );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_flat_array() {
		global $wpdb;

		$q = new WP_Comment_Query();
		$q->query(
			array(
				'orderby' => array( 'comment_agent', 'comment_approved' ),
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent DESC, $wpdb->comments.comment_approved DESC", $q->request );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_array_contains_invalid_item() {
		global $wpdb;

		$q = new WP_Comment_Query();
		$q->query(
			array(
				'orderby' => array( 'comment_agent', 'foo', 'comment_approved' ),
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent DESC, $wpdb->comments.comment_approved DESC", $q->request );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_array_contains_all_invalid_items() {
		global $wpdb;

		$q = new WP_Comment_Query();
		$q->query(
			array(
				'orderby' => array( 'foo', 'bar', 'baz' ),
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_date_gmt", $q->request );
	}

	/**
	 * @ticket 29902
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_none() {
		$q = new WP_Comment_Query();
		$q->query(
			array(
				'orderby' => 'none',
			)
		);

		$this->assertStringNotContainsString( 'ORDER BY', $q->request );
	}

	/**
	 * @ticket 29902
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_empty_array() {
		$q = new WP_Comment_Query();
		$q->query(
			array(
				'orderby' => array(),
			)
		);

		$this->assertStringNotContainsString( 'ORDER BY', $q->request );
	}

	/**
	 * @ticket 29902
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_false() {
		$q = new WP_Comment_Query();
		$q->query(
			array(
				'orderby' => false,
			)
		);

		$this->assertStringNotContainsString( 'ORDER BY', $q->request );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_array() {
		global $wpdb;

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'  => 'ids',
				'orderby' => array(
					'comment_agent'    => 'DESC',
					'comment_date_gmt' => 'ASC',
					'comment_ID'       => 'DESC',
				),
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent DESC, $wpdb->comments.comment_date_gmt ASC, $wpdb->comments.comment_ID DESC", $q->request );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_array_should_discard_invalid_columns() {
		global $wpdb;

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'  => 'ids',
				'orderby' => array(
					'comment_agent' => 'DESC',
					'foo'           => 'ASC',
					'comment_ID'    => 'DESC',
				),
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent DESC, $wpdb->comments.comment_ID DESC", $q->request );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_array_should_convert_invalid_order_to_DESC() {
		global $wpdb;

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'  => 'ids',
				'orderby' => array(
					'comment_agent'    => 'DESC',
					'comment_date_gmt' => 'foo',
					'comment_ID'       => 'DESC',
				),
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent DESC, $wpdb->comments.comment_date_gmt DESC, $wpdb->comments.comment_ID DESC", $q->request );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_array_should_sort_by_comment_ID_as_fallback_and_should_inherit_order_from_comment_date_gmt() {
		global $wpdb;

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'  => 'ids',
				'orderby' => array(
					'comment_agent'    => 'DESC',
					'comment_date_gmt' => 'ASC',
				),
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent DESC, $wpdb->comments.comment_date_gmt ASC, $wpdb->comments.comment_ID ASC", $q->request );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_array_should_sort_by_comment_ID_as_fallback_and_should_inherit_order_from_comment_date() {
		global $wpdb;

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'  => 'ids',
				'orderby' => array(
					'comment_agent' => 'DESC',
					'comment_date'  => 'ASC',
				),
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent DESC, $wpdb->comments.comment_date ASC, $wpdb->comments.comment_ID ASC", $q->request );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_array_should_sort_by_comment_ID_DESC_as_fallback_when_not_sorted_by_date() {
		global $wpdb;

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'  => 'ids',
				'orderby' => array(
					'comment_agent' => 'ASC',
				),
			)
		);

		$this->assertStringContainsString( "ORDER BY $wpdb->comments.comment_agent ASC, $wpdb->comments.comment_ID DESC", $q->request );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_date_modified_gmt_should_order_by_comment_ID_in_case_of_tie_ASC() {
		$now      = current_time( 'mysql', 1 );
		$comments = self::factory()->comment->create_many(
			5,
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_date_gmt' => $now,
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'orderby' => 'comment_date_gmt',
				'order'   => 'ASC',
			)
		);

		// $comments is ASC by default.
		$this->assertEquals( $comments, wp_list_pluck( $found, 'comment_ID' ) );
	}

	/**
	 * @ticket 30478
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_orderby_date_modified_gmt_should_order_by_comment_ID_in_case_of_tie_DESC() {
		$now      = current_time( 'mysql', 1 );
		$comments = self::factory()->comment->create_many(
			5,
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_date_gmt' => $now,
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			)
		);

		// $comments is ASC by default.
		rsort( $comments );

		$this->assertEquals( $comments, wp_list_pluck( $found, 'comment_ID' ) );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_meta_vars_should_be_converted_to_meta_query() {
		$q = new WP_Comment_Query();
		$q->query(
			array(
				'meta_key'     => 'foo',
				'meta_value'   => '5',
				'meta_compare' => '>',
				'meta_type'    => 'SIGNED',
			)
		);

		$this->assertSame( 'foo', $q->meta_query->queries[0]['key'] );
		$this->assertSame( '5', $q->meta_query->queries[0]['value'] );
		$this->assertSame( '>', $q->meta_query->queries[0]['compare'] );
		$this->assertSame( 'SIGNED', $q->meta_query->queries[0]['type'] );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_count() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => 7,
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => 7,
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'count' => true,
			)
		);

		$this->assertSame( 2, $found );
	}

	/**
	 * @ticket 23369
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_count_with_meta_query() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => 7,
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => 7,
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => 7,
			)
		);
		add_comment_meta( $c1, 'foo', 'bar' );
		add_comment_meta( $c3, 'foo', 'bar' );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'count'      => true,
				'meta_query' => array(
					array(
						'key'   => 'foo',
						'value' => 'bar',
					),
				),
			)
		);

		$this->assertSame( 2, $found );
	}

	/**
	 * @ticket 38268
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_paged() {
		$now = time();

		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 50 ),
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 40 ),
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 30 ),
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 20 ),
			)
		);

		$query = new WP_Comment_Query();
		$found = $query->query(
			array(
				'paged'   => 2,
				'number'  => 2,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
				'fields'  => 'ids',
			)
		);

		$expected = array( $c2, $c1 );
		$this->assertSame( $expected, $found );
	}

	/**
	 * @ticket 38268
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_offset_should_take_precedence_over_paged() {
		$now = time();

		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 50 ),
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 40 ),
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 30 ),
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 20 ),
			)
		);

		$query = new WP_Comment_Query();
		$found = $query->query(
			array(
				'paged'   => 2,
				'offset'  => 1,
				'number'  => 2,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
				'fields'  => 'ids',
			)
		);

		$expected = array( $c3, $c2 );

		$this->assertSame( $expected, $found );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_type_single_value() {
		register_post_type( 'post-type-1' );
		register_post_type( 'post-type-2' );

		$p1 = self::factory()->post->create( array( 'post_type' => 'post-type-1' ) );
		$p2 = self::factory()->post->create( array( 'post_type' => 'post-type-2' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'    => 'ids',
				'post_type' => 'post-type-2',
			)
		);

		$this->assertSameSets( $c2, $found );

		_unregister_post_type( 'post-type-1' );
		_unregister_post_type( 'post-type-2' );
	}

	/**
	 * @ticket 20006
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_type_singleton_array() {
		register_post_type( 'post-type-1' );
		register_post_type( 'post-type-2' );

		$p1 = self::factory()->post->create( array( 'post_type' => 'post-type-1' ) );
		$p2 = self::factory()->post->create( array( 'post_type' => 'post-type-2' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'    => 'ids',
				'post_type' => array( 'post-type-2' ),
			)
		);

		$this->assertSameSets( $c2, $found );

		_unregister_post_type( 'post-type-1' );
		_unregister_post_type( 'post-type-2' );
	}

	/**
	 * @ticket 20006
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_type_array() {
		register_post_type( 'post-type-1' );
		register_post_type( 'post-type-2' );
		register_post_type( 'post-type-3' );

		$p1 = self::factory()->post->create( array( 'post_type' => 'post-type-1' ) );
		$p2 = self::factory()->post->create( array( 'post_type' => 'post-type-2' ) );
		$p3 = self::factory()->post->create( array( 'post_type' => 'post-type-3' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );
		$c3 = self::factory()->comment->create_post_comments( $p3, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'    => 'ids',
				'post_type' => array( 'post-type-1', 'post-type-3' ),
			)
		);

		$this->assertSameSets( array_merge( $c1, $c3 ), $found );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_name_single_value() {
		$p1 = self::factory()->post->create( array( 'post_name' => 'foo' ) );
		$p2 = self::factory()->post->create( array( 'post_name' => 'bar' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'    => 'ids',
				'post_name' => 'bar',
			)
		);

		$this->assertSameSets( $c2, $found );
	}

	/**
	 * @ticket 20006
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_name_singleton_array() {
		$p1 = self::factory()->post->create( array( 'post_name' => 'foo' ) );
		$p2 = self::factory()->post->create( array( 'post_name' => 'bar' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'    => 'ids',
				'post_name' => array( 'bar' ),
			)
		);

		$this->assertSameSets( $c2, $found );
	}

	/**
	 * @ticket 20006
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_name_array() {
		$p1 = self::factory()->post->create( array( 'post_name' => 'foo' ) );
		$p2 = self::factory()->post->create( array( 'post_name' => 'bar' ) );
		$p3 = self::factory()->post->create( array( 'post_name' => 'baz' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );
		$c3 = self::factory()->comment->create_post_comments( $p3, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'    => 'ids',
				'post_name' => array( 'foo', 'baz' ),
			)
		);

		$this->assertSameSets( array_merge( $c1, $c3 ), $found );
	}

	/**
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_status_single_value() {
		$p1 = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$p2 = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'      => 'ids',
				'post_status' => 'draft',
			)
		);

		$this->assertSameSets( $c2, $found );
	}

	/**
	 * @ticket 20006
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_status_singleton_array() {
		$p1 = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$p2 = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'      => 'ids',
				'post_status' => array( 'draft' ),
			)
		);

		$this->assertSameSets( $c2, $found );
	}

	/**
	 * @ticket 20006
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_status_array() {
		$p1 = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$p2 = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$p3 = self::factory()->post->create( array( 'post_status' => 'future' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );
		$c3 = self::factory()->comment->create_post_comments( $p3, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'      => 'ids',
				'post_status' => array( 'publish', 'future' ),
			)
		);

		$this->assertSameSets( array_merge( $c1, $c3 ), $found );
	}

	/**
	 * @ticket 35512
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_type_any_should_override_other_post_types() {
		register_post_type( 'post-type-1', array( 'exclude_from_search' => false ) );
		register_post_type( 'post-type-2', array( 'exclude_from_search' => false ) );

		$p1 = self::factory()->post->create( array( 'post_type' => 'post-type-1' ) );
		$p2 = self::factory()->post->create( array( 'post_type' => 'post-type-2' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'    => 'ids',
				'post_type' => array( 'any', 'post-type-1' ),
			)
		);
		$this->assertSameSets( array_merge( $c1, $c2 ), $found );
	}

	/**
	 * @ticket 35512
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_type_any_as_part_of_an_array_of_post_types() {
		register_post_type( 'post-type-1', array( 'exclude_from_search' => false ) );
		register_post_type( 'post-type-2', array( 'exclude_from_search' => false ) );

		$p1 = self::factory()->post->create( array( 'post_type' => 'post-type-1' ) );
		$p2 = self::factory()->post->create( array( 'post_type' => 'post-type-2' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'    => 'ids',
				'post_type' => array( 'any' ),
			)
		);
		$this->assertSameSets( array_merge( $c1, $c2 ), $found );
	}

	/**
	 * @ticket 35512
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_status_any_should_override_other_post_statuses() {
		$p1 = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$p2 = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'      => 'ids',
				'post_status' => array( 'any', 'draft' ),
			)
		);
		$this->assertSameSets( array_merge( $c1, $c2 ), $found );
	}

	/**
	 * @ticket 35512
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_post_status_any_as_part_of_an_array_of_post_statuses() {
		$p1 = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$p2 = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$c1 = self::factory()->comment->create_post_comments( $p1, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p2, 1 );

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'fields'      => 'ids',
				'post_status' => array( 'any' ),
			)
		);
		$this->assertSameSets( array_merge( $c1, $c2 ), $found );
	}

	/**
	 * @ticket 24826
	 *
	 * @covers WP_Comment_Query::__construct
	 * @covers WP_Comment_Query::get_comments
	 */
	public function test_comment_query_object() {
		$comment_id = self::factory()->comment->create();

		$query1 = new WP_Comment_Query();
		$this->assertNull( $query1->query_vars );
		$this->assertEmpty( $query1->comments );
		$comments = $query1->query( array( 'status' => 'all' ) );
		$this->assertIsArray( $query1->query_vars );
		$this->assertNotEmpty( $query1->comments );
		$this->assertIsArray( $query1->comments );

		$query2 = new WP_Comment_Query( array( 'status' => 'all' ) );
		$this->assertNotEmpty( $query2->query_vars );
		$this->assertNotEmpty( $query2->comments );
		$this->assertEquals( $query2->comments, $query1->get_comments() );
	}

	/**
	 * @ticket 22400
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_comment_cache_key_should_ignore_custom_params() {
		global $wpdb;

		$p = self::factory()->post->create();
		$c = self::factory()->comment->create( array( 'comment_post_ID' => $p ) );

		$q1 = new WP_Comment_Query();
		$q1->query(
			array(
				'post_id' => $p,
				'fields'  => 'ids',
			)
		);

		$num_queries = $wpdb->num_queries;

		$q2 = new WP_Comment_Query();
		$q2->query(
			array(
				'post_id' => $p,
				'fields'  => 'ids',
				'foo'     => 'bar',
			)
		);

		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 35677
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_cache_should_be_sensitive_to_parent__in() {
		global $wpdb;

		$q1 = new WP_Comment_Query(
			array(
				'parent__in' => array( 1, 2, 3 ),
			)
		);

		$num_queries = $wpdb->num_queries;

		$q2 = new WP_Comment_Query(
			array(
				'parent__in' => array( 4, 5, 6 ),
			)
		);

		$this->assertNotEquals( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 35677
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_cache_should_be_sensitive_to_parent__not_in() {
		global $wpdb;

		$q1 = new WP_Comment_Query(
			array(
				'parent__not_in' => array( 1, 2, 3 ),
			)
		);

		$num_queries = $wpdb->num_queries;

		$q2 = new WP_Comment_Query(
			array(
				'parent__not_in' => array( 4, 5, 6 ),
			)
		);

		$this->assertNotEquals( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 32762
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_it_should_be_possible_to_modify_meta_query_using_pre_get_comments_action() {
		$comments = self::factory()->comment->create_many(
			2,
			array(
				'comment_post_ID' => self::$post_id,
			)
		);

		add_comment_meta( $comments[1], 'foo', 'bar' );

		add_action( 'pre_get_comments', array( $this, 'modify_meta_query' ) );

		$q = new WP_Comment_Query(
			array(
				'comment_post_ID' => self::$post_id,
				'fields'          => 'ids',
			)
		);

		remove_action( 'pre_get_comments', array( $this, 'modify_meta_query' ) );

		$this->assertSameSets( array( $comments[1] ), $q->comments );
	}

	public function modify_meta_query( $q ) {
		$q->meta_query = new WP_Meta_Query(
			array(
				array(
					'key'   => 'foo',
					'value' => 'bar',
				),
			)
		);
	}

	/**
	 * @ticket 32762
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_it_should_be_possible_to_modify_meta_params_using_pre_get_comments_action() {
		$comments = self::factory()->comment->create_many(
			2,
			array(
				'comment_post_ID' => self::$post_id,
			)
		);

		add_comment_meta( $comments[1], 'foo', 'bar' );

		add_action( 'pre_get_comments', array( $this, 'modify_meta_params' ) );

		$q = new WP_Comment_Query(
			array(
				'comment_post_ID' => self::$post_id,
				'fields'          => 'ids',
			)
		);

		remove_action( 'pre_get_comments', array( $this, 'modify_meta_params' ) );

		$this->assertSameSets( array( $comments[1] ), $q->comments );
	}

	public function modify_meta_params( $q ) {
		$q->query_vars['meta_key']   = 'foo';
		$q->query_vars['meta_value'] = 'bar';
	}

	/**
	 * @ticket 33882
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_parent__in() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);

		$ids = new WP_Comment_Query(
			array(
				'comment_post_ID' => self::$post_id,
				'fields'          => 'ids',
				'parent__in'      => array( $c1 ),
			)
		);

		$this->assertSameSets( array( $c2 ), $ids->comments );
	}

	/**
	 * @ticket 33882
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_parent__in_commas() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c2,
			)
		);

		$ids = new WP_Comment_Query(
			array(
				'comment_post_ID' => self::$post_id,
				'fields'          => 'ids',
				'parent__in'      => "$c1,$c2",
			)
		);

		$this->assertSameSets( array( $c3, $c4 ), $ids->comments );
	}

	/**
	 * @ticket 33882
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_parent__not_in() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);

		$ids = new WP_Comment_Query(
			array(
				'comment_post_ID' => self::$post_id,
				'fields'          => 'ids',
				'parent__not_in'  => array( $c1 ),
			)
		);

		$this->assertSameSets( array( $c1 ), $ids->comments );
	}

	/**
	 * @ticket 33882
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_parent__not_in_commas() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c2,
			)
		);

		$ids = new WP_Comment_Query(
			array(
				'comment_post_ID' => self::$post_id,
				'fields'          => 'ids',
				'parent__not_in'  => "$c1,$c2",
			)
		);

		$this->assertSameSets( array( $c1, $c2 ), $ids->comments );
	}

	/**
	 * @ticket 33883
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_orderby_comment__in() {
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$ids = new WP_Comment_Query(
			array(
				'fields'      => 'ids',
				'comment__in' => array( $c2, $c3 ),
				'orderby'     => 'comment__in',
			)
		);

		$this->assertSame( array( $c2, $c3 ), $ids->comments );

	}

	/**
	 * @ticket 8071
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_no_found_rows_should_default_to_true() {
		$comments = self::factory()->comment->create_many( 3, array( 'comment_post_ID' => self::$post_id ) );

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'number'  => 2,
			)
		);

		$this->assertSame( 0, $q->found_comments );
		$this->assertSame( 0, $q->max_num_pages );
	}

	/**
	 * @ticket 8071
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_should_respect_no_found_rows_true() {
		$comments = self::factory()->comment->create_many( 3, array( 'comment_post_ID' => self::$post_id ) );

		$q = new WP_Comment_Query(
			array(
				'post_id'       => self::$post_id,
				'number'        => 2,
				'no_found_rows' => true,
			)
		);

		$this->assertSame( 0, $q->found_comments );
		$this->assertSame( 0, $q->max_num_pages );
	}

	/**
	 * @ticket 8071
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_should_respect_no_found_rows_false() {
		$comments = self::factory()->comment->create_many( 3, array( 'comment_post_ID' => self::$post_id ) );

		$q = new WP_Comment_Query(
			array(
				'post_id'       => self::$post_id,
				'number'        => 2,
				'no_found_rows' => false,
			)
		);

		$this->assertSame( 3, $q->found_comments );
		$this->assertEquals( 2, $q->max_num_pages );
	}

	/**
	 * @ticket 37184
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_found_rows_should_be_fetched_from_the_cache() {
		$comments = self::factory()->comment->create_many( 3, array( 'comment_post_ID' => self::$post_id ) );

		// Prime cache.
		new WP_Comment_Query(
			array(
				'post_id'       => self::$post_id,
				'number'        => 2,
				'no_found_rows' => false,
			)
		);

		$q = new WP_Comment_Query(
			array(
				'post_id'       => self::$post_id,
				'number'        => 2,
				'no_found_rows' => false,
			)
		);

		$this->assertSame( 3, $q->found_comments );
		$this->assertEquals( 2, $q->max_num_pages );
	}

	/**
	 * @ticket 8071
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_hierarchical_should_skip_child_comments_in_offset() {
		$top_level_0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$child_of_0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $top_level_0,
			)
		);

		$top_level_comments = self::factory()->comment->create_many(
			3,
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$q = new WP_Comment_Query(
			array(
				'post_id'      => self::$post_id,
				'hierarchical' => 'flat',
				'number'       => 2,
				'offset'       => 1,
				'orderby'      => 'comment_ID',
				'order'        => 'ASC',
				'fields'       => 'ids',
			)
		);

		$this->assertSame( array( $top_level_comments[0], $top_level_comments[1] ), $q->comments );
	}

	/**
	 * @ticket 8071
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_hierarchical_should_not_include_child_comments_in_number() {
		$top_level_0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$child_of_0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $top_level_0,
			)
		);

		$top_level_comments = self::factory()->comment->create_many(
			3,
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$q = new WP_Comment_Query(
			array(
				'post_id'      => self::$post_id,
				'hierarchical' => 'flat',
				'number'       => 2,
				'orderby'      => 'comment_ID',
				'order'        => 'ASC',
			)
		);

		$this->assertEqualSets( array( $top_level_0, $child_of_0, $top_level_comments[0] ), wp_list_pluck( $q->comments, 'comment_ID' ) );
	}

	/**
	 * @ticket 8071
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_hierarchical_threaded() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);

		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c2,
			)
		);

		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);

		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$c6 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c5,
			)
		);

		$args = array(
			'hierarchical' => 'threaded',
			'orderby'      => 'comment_ID',
			'order'        => 'ASC',
		);

		$query_args = array_merge(
			$args,
			array(
				'post_id' => self::$post_id,
			)
		);

		$q = new WP_Comment_Query( $query_args );

		// Top-level comments.
		$this->assertEqualSets( array( $c1, $c5 ), array_values( wp_list_pluck( $q->comments, 'comment_ID' ) ) );

		// Direct descendants of $c1.
		$this->assertEqualSets( array( $c2, $c4 ), array_values( wp_list_pluck( $q->comments[ $c1 ]->get_children( $args ), 'comment_ID' ) ) );

		// Direct descendants of $c2.
		$this->assertEqualSets( array( $c3 ), array_values( wp_list_pluck( $q->comments[ $c1 ]->get_child( $c2 )->get_children( $args ), 'comment_ID' ) ) );

		// Direct descendants of $c5.
		$this->assertEqualSets( array( $c6 ), array_values( wp_list_pluck( $q->comments[ $c5 ]->get_children( $args ), 'comment_ID' ) ) );
	}

	/**
	 * @ticket 8071
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_hierarchical_threaded_approved() {
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);

		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '0',
				'comment_parent'   => $c2,
			)
		);

		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);

		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c5,
			)
		);

		$args = array(
			'hierarchical' => 'threaded',
			'status'       => 'approve',
			'orderby'      => 'comment_ID',
			'order'        => 'ASC',
		);

		$query_args = array_merge(
			$args,
			array(
				'post_id' => self::$post_id,
			)
		);

		$q = new WP_Comment_Query( $query_args );

		// Top-level comments.
		$this->assertEqualSets( array( $c1, $c5 ), array_values( wp_list_pluck( $q->comments, 'comment_ID' ) ) );

		// Direct descendants of $c1.
		$this->assertEqualSets( array( $c2, $c4 ), array_values( wp_list_pluck( $q->comments[ $c1 ]->get_children( $args ), 'comment_ID' ) ) );

		// Direct descendants of $c2.
		$this->assertEqualSets( array(), array_values( wp_list_pluck( $q->comments[ $c1 ]->get_child( $c2 )->get_children( $args ), 'comment_ID' ) ) );
	}

	/**
	 * @ticket 35192
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_comment_clauses_prepend_callback_should_be_respected_when_filling_descendants() {
		$top_level_0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$child1_of_0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $top_level_0,
			)
		);

		$child2_of_0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $top_level_0,
			)
		);

		$top_level_comments = self::factory()->comment->create_many(
			3,
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$this->to_exclude = array( $child2_of_0, $top_level_comments[1] );

		add_filter( 'comments_clauses', array( $this, 'prepend_exclusions' ) );
		$q = new WP_Comment_Query(
			array(
				'post_id'      => self::$post_id,
				'hierarchical' => 'flat',
			)
		);
		remove_filter( 'comments_clauses', array( $this, 'prepend_exclusions' ) );

		$this->assertEqualSets( array( $top_level_0, $child1_of_0, $top_level_comments[0], $top_level_comments[2] ), wp_list_pluck( $q->comments, 'comment_ID' ) );
	}

	public function prepend_exclusions( $clauses ) {
		global $wpdb;
		$clauses['where'] = $wpdb->prepare( 'comment_ID != %d AND comment_ID != %d AND ', $this->to_exclude[0], $this->to_exclude[1] ) . $clauses['where'];
		return $clauses;
	}

	/**
	 * @ticket 35192
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_comment_clauses_append_callback_should_be_respected_when_filling_descendants() {
		$top_level_0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$child1_of_0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $top_level_0,
			)
		);

		$child2_of_0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $top_level_0,
			)
		);

		$top_level_comments = self::factory()->comment->create_many(
			3,
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$this->to_exclude = array( $child2_of_0, $top_level_comments[1] );

		add_filter( 'comments_clauses', array( $this, 'append_exclusions' ) );
		$q = new WP_Comment_Query(
			array(
				'post_id'      => self::$post_id,
				'hierarchical' => 'flat',
			)
		);
		remove_filter( 'comments_clauses', array( $this, 'append_exclusions' ) );

		$this->assertEqualSets( array( $top_level_0, $child1_of_0, $top_level_comments[0], $top_level_comments[2] ), wp_list_pluck( $q->comments, 'comment_ID' ) );
	}

	public function append_exclusions( $clauses ) {
		global $wpdb;
		$clauses['where'] .= $wpdb->prepare( ' AND comment_ID != %d AND comment_ID != %d', $this->to_exclude[0], $this->to_exclude[1] );
		return $clauses;
	}

	/**
	 * @ticket 36487
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_cache_should_be_hit_when_querying_descendants() {
		global $wpdb;

		$p         = self::factory()->post->create();
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_approved' => '1',
			)
		);
		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_approved' => '1',
				'comment_parent'   => $comment_1,
			)
		);
		$comment_3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_approved' => '1',
				'comment_parent'   => $comment_1,
			)
		);
		$comment_4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_approved' => '1',
				'comment_parent'   => $comment_2,
			)
		);

		$q1     = new WP_Comment_Query(
			array(
				'post_id'      => $p,
				'hierarchical' => true,
			)
		);
		$q1_ids = wp_list_pluck( $q1->comments, 'comment_ID' );

		$num_queries = $wpdb->num_queries;
		$q2          = new WP_Comment_Query(
			array(
				'post_id'      => $p,
				'hierarchical' => true,
			)
		);
		$q2_ids      = wp_list_pluck( $q2->comments, 'comment_ID' );

		$this->assertSameSets( $q1_ids, $q2_ids );
		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 37696
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_hierarchy_should_be_filled_when_cache_is_incomplete() {
		global $wpdb;

		$p         = self::factory()->post->create();
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_approved' => '1',
			)
		);
		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_approved' => '1',
				'comment_parent'   => $comment_1,
			)
		);
		$comment_3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_approved' => '1',
				'comment_parent'   => $comment_1,
			)
		);
		$comment_4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_approved' => '1',
				'comment_parent'   => $comment_2,
			)
		);

		// Prime cache.
		$q1     = new WP_Comment_Query(
			array(
				'post_id'      => $p,
				'hierarchical' => true,
			)
		);
		$q1_ids = wp_list_pluck( $q1->comments, 'comment_ID' );
		$this->assertEqualSets( array( $comment_1, $comment_2, $comment_3, $comment_4 ), $q1_ids );

		// Delete one of the parent caches.
		$last_changed = wp_cache_get( 'last_changed', 'comment' );
		$key          = md5( serialize( wp_array_slice_assoc( $q1->query_vars, array_keys( $q1->query_var_defaults ) ) ) );
		$cache_key    = "get_comment_child_ids:$comment_2:$key:$last_changed";
		wp_cache_delete( $cache_key, 'comment' );

		$q2     = new WP_Comment_Query(
			array(
				'post_id'      => $p,
				'hierarchical' => true,
			)
		);
		$q2_ids = wp_list_pluck( $q2->comments, 'comment_ID' );
		$this->assertSameSets( $q1_ids, $q2_ids );
	}

	/**
	 * @ticket 37966
	 * @ticket 37696
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_fill_hierarchy_should_disregard_offset_and_number() {
		$c0 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c1,
			)
		);
		$c3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);
		$c4 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c3,
			)
		);
		$c5 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
				'comment_parent'   => $c3,
			)
		);

		$q     = new WP_Comment_Query();
		$found = $q->query(
			array(
				'orderby'       => 'comment_date_gmt',
				'order'         => 'ASC',
				'status'        => 'approve',
				'post_id'       => self::$post_id,
				'no_found_rows' => false,
				'hierarchical'  => 'threaded',
				'number'        => 2,
				'offset'        => 1,
			)
		);

		$found_1    = $found[ $c1 ];
		$children_1 = $found_1->get_children();
		$this->assertSameSets( array( $c2 ), array_keys( $children_1 ) );

		$found_3    = $found[ $c3 ];
		$children_3 = $found_3->get_children();
		$this->assertSameSets( array( $c4, $c5 ), array_keys( $children_3 ) );
	}

	/**
	 * @ticket 27571
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_update_comment_post_cache_should_be_disabled_by_default() {
		global $wpdb;

		$p = self::factory()->post->create();
		$c = self::factory()->comment->create( array( 'comment_post_ID' => $p ) );

		$q = new WP_Comment_Query(
			array(
				'post_ID' => $p,
			)
		);

		$num_queries = $wpdb->num_queries;
		$this->assertTrue( isset( $q->comments[0]->post_name ) );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );
	}

	/**
	 * @ticket 27571
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_should_respect_update_comment_post_cache_true() {
		global $wpdb;

		$p = self::factory()->post->create();
		$c = self::factory()->comment->create( array( 'comment_post_ID' => $p ) );

		$q = new WP_Comment_Query(
			array(
				'post_ID'                   => $p,
				'update_comment_post_cache' => true,
			)
		);

		$num_queries = $wpdb->num_queries;
		$this->assertTrue( isset( $q->comments[0]->post_name ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 34138
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_comment_objects_should_be_filled_from_cache() {
		global $wpdb;

		$comments = self::factory()->comment->create_many( 3, array( 'comment_post_ID' => self::$post_id ) );
		clean_comment_cache( $comments );

		$num_queries = $wpdb->num_queries;
		$q           = new WP_Comment_Query(
			array(
				'post_id'                   => self::$post_id,
				'no_found_rows'             => true,
				'update_comment_post_cache' => false,
				'update_comment_meta_cache' => false,
			)
		);

		// 2 queries should have been fired: one for IDs, one to prime comment caches.
		$num_queries += 2;

		$found = wp_list_pluck( $q->comments, 'comment_ID' );
		$this->assertEqualSets( $comments, $found );

		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 34138
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_comment_objects_should_be_fetched_from_database_when_suspend_cache_addition() {
		$suspend = wp_suspend_cache_addition();
		wp_suspend_cache_addition( true );

		$c = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
			)
		);

		wp_suspend_cache_addition( $suspend );

		$found = wp_list_pluck( $q->comments, 'comment_ID' );
		$this->assertEqualSets( array( $c ), $found );
	}

	/**
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_comment_query_should_be_cached() {
		global $wpdb;

		$c = wp_insert_comment(
			array(
				'comment_author'       => 'Foo',
				'comment_author_email' => 'foo@example.com',
				'comment_post_ID'      => self::$post_id,
			)
		);

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		$num_queries = $wpdb->num_queries;

		$q2 = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_created_comment_should_invalidate_query_cache() {
		global $wpdb;

		$c = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		$num_queries = $wpdb->num_queries;

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		$this->assertSame( $num_queries, $wpdb->num_queries );
		$this->assertSameSets( array( $c ), $q->comments );
	}

	/**
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_updated_comment_should_invalidate_query_cache() {
		global $wpdb;

		$c = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		wp_update_comment(
			array(
				'comment_ID'           => $c,
				'comment_author'       => 'Foo',
				'comment_author_email' => 'foo@example.com',
				'comment_post_ID'      => self::$post_id,
			)
		);

		$num_queries = $wpdb->num_queries;

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		$num_queries++;
		$this->assertSame( $num_queries, $wpdb->num_queries );
		$this->assertSameSets( array( $c ), $q->comments );
	}

	/**
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_deleted_comment_should_invalidate_query_cache() {
		global $wpdb;

		$c = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		wp_delete_comment( $c );

		$num_queries = $wpdb->num_queries;

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		$num_queries++;
		$this->assertSame( $num_queries, $wpdb->num_queries );
		$this->assertSameSets( array(), $q->comments );
	}

	/**
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_trashed_comment_should_invalidate_query_cache() {
		global $wpdb;

		$c = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		wp_trash_comment( $c );

		$num_queries = $wpdb->num_queries;

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		$num_queries++;
		$this->assertSame( $num_queries, $wpdb->num_queries );
		$this->assertSameSets( array(), $q->comments );
	}

	/**
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_untrashed_comment_should_invalidate_query_cache() {
		global $wpdb;

		$c = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		wp_trash_comment( $c );

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		wp_untrash_comment( $c );

		$num_queries = $wpdb->num_queries;

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		$num_queries++;
		$this->assertSame( $num_queries, $wpdb->num_queries );
		$this->assertSameSets( array( $c ), $q->comments );
	}

	/**
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_spammed_comment_should_invalidate_query_cache() {
		global $wpdb;

		$c = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		wp_spam_comment( $c );

		$num_queries = $wpdb->num_queries;

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		$num_queries++;
		$this->assertSame( $num_queries, $wpdb->num_queries );
		$this->assertSameSets( array(), $q->comments );
	}

	/**
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_unspammed_comment_should_invalidate_query_cache() {
		global $wpdb;

		$c = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		wp_spam_comment( $c );

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		wp_unspam_comment( $c );

		$num_queries = $wpdb->num_queries;

		$q = new WP_Comment_Query(
			array(
				'post_id' => self::$post_id,
				'fields'  => 'ids',
			)
		);

		$num_queries++;
		$this->assertSame( $num_queries, $wpdb->num_queries );
		$this->assertSameSets( array( $c ), $q->comments );
	}

	/**
	 * @ticket 41348
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_count_query_should_miss_noncount_cache() {
		global $wpdb;

		$q = new WP_Comment_Query();

		$query_1 = $q->query(
			array(
				'fields' => 'ids',
				'number' => 3,
				'order'  => 'ASC',
			)
		);

		$number_of_queries = $wpdb->num_queries;

		$query_2 = $q->query(
			array(
				'fields' => 'ids',
				'number' => 3,
				'order'  => 'ASC',
				'count'  => true,
			)
		);
		$this->assertSame( $number_of_queries + 1, $wpdb->num_queries );
	}

	/**
	 * @ticket 41348
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_count_query_should_hit_count_cache() {
		global $wpdb;

		$q = new WP_Comment_Query();

		$query_1           = $q->query(
			array(
				'fields' => 'ids',
				'number' => 3,
				'order'  => 'ASC',
				'count'  => true,
			)
		);
		$number_of_queries = $wpdb->num_queries;

		$query_2 = $q->query(
			array(
				'fields' => 'ids',
				'number' => 3,
				'order'  => 'ASC',
				'count'  => true,
			)
		);
		$this->assertSame( $number_of_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 41348
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_different_values_of_fields_should_share_cached_values() {
		global $wpdb;

		$q = new WP_Comment_Query();

		$query_1           = $q->query(
			array(
				'fields' => 'all',
				'number' => 3,
				'order'  => 'ASC',
			)
		);
		$number_of_queries = $wpdb->num_queries;

		$query_2 = $q->query(
			array(
				'fields' => 'ids',
				'number' => 3,
				'order'  => 'ASC',
			)
		);

		$this->assertSame( $number_of_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 40669
	 *
	 * @covers ::get_comments
	 */
	public function test_add_comment_meta_should_invalidate_query_cache() {
		global $wpdb;

		$p  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$c1 = self::factory()->comment->create_post_comments( $p, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p, 1 );

		foreach ( $c1 as $cid ) {
			add_comment_meta( $cid, 'sauce', 'fire' );
		}

		$cached = get_comments(
			array(
				'post_id'    => $p,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => 'sauce',
						'value' => 'fire',
					),
				),
			)
		);

		$this->assertSameSets( $c1, $cached );

		foreach ( $c2 as $cid ) {
			add_comment_meta( $cid, 'sauce', 'fire' );
		}

		$found = get_comments(
			array(
				'post_id'    => $p,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => 'sauce',
						'value' => 'fire',
					),
				),
			)
		);

		$this->assertSameSets( array_merge( $c1, $c2 ), $found );
	}

	/**
	 * @ticket 40669
	 *
	 * @covers ::get_comments
	 */
	public function test_update_comment_meta_should_invalidate_query_cache() {
		global $wpdb;

		$p  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$c1 = self::factory()->comment->create_post_comments( $p, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p, 1 );

		foreach ( array_merge( $c1, $c2 ) as $cid ) {
			add_comment_meta( $cid, 'sauce', 'fire' );
		}

		$cached = get_comments(
			array(
				'post_id'    => $p,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => 'sauce',
						'value' => 'fire',
					),
				),
			)
		);

		$this->assertSameSets( array_merge( $c1, $c2 ), $cached );

		foreach ( $c2 as $cid ) {
			update_comment_meta( $cid, 'sauce', 'foo' );
		}

		$found = get_comments(
			array(
				'post_id'    => $p,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => 'sauce',
						'value' => 'fire',
					),
				),
			)
		);

		$this->assertSameSets( $c1, $found );
	}

	/**
	 * @ticket 40669
	 *
	 * @covers ::get_comments
	 */
	public function test_delete_comment_meta_should_invalidate_query_cache() {
		global $wpdb;

		$p  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$c1 = self::factory()->comment->create_post_comments( $p, 1 );
		$c2 = self::factory()->comment->create_post_comments( $p, 1 );

		foreach ( array_merge( $c1, $c2 ) as $cid ) {
			add_comment_meta( $cid, 'sauce', 'fire' );
		}

		$cached = get_comments(
			array(
				'post_id'    => $p,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => 'sauce',
						'value' => 'fire',
					),
				),
			)
		);

		$this->assertSameSets( array_merge( $c1, $c2 ), $cached );

		foreach ( $c2 as $cid ) {
			delete_comment_meta( $cid, 'sauce' );
		}

		$found = get_comments(
			array(
				'post_id'    => $p,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => 'sauce',
						'value' => 'fire',
					),
				),
			)
		);

		$this->assertSameSets( $c1, $found );
	}

	/**
	 * @ticket 45800
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_comments_pre_query_filter_should_bypass_database_query() {
		global $wpdb;

		add_filter( 'comments_pre_query', array( __CLASS__, 'filter_comments_pre_query' ), 10, 2 );

		$num_queries = $wpdb->num_queries;

		$q       = new WP_Comment_Query();
		$results = $q->query( array() );

		remove_filter( 'comments_pre_query', array( __CLASS__, 'filter_comments_pre_query' ), 10, 2 );

		// Make sure no queries were executed.
		$this->assertSame( $num_queries, $wpdb->num_queries );

		// We manually inserted a non-existing site and overrode the results with it.
		$this->assertSame( array( 555 ), $results );

		// Make sure manually setting found_comments doesn't get overwritten.
		$this->assertSame( 1, $q->found_comments );
	}

	public static function filter_comments_pre_query( $comments, $query ) {
		$query->found_comments = 1;

		return array( 555 );
	}

	/**
	 * @ticket 50521
	 *
	 * @covers WP_Comment_Query::query
	 */
	public function test_comments_pre_query_filter_should_set_comments_property() {
		add_filter( 'comments_pre_query', array( __CLASS__, 'filter_comments_pre_query_and_set_comments' ), 10, 2 );

		$q       = new WP_Comment_Query();
		$results = $q->query( array() );

		remove_filter( 'comments_pre_query', array( __CLASS__, 'filter_comments_pre_query_and_set_comments' ), 10 );

		// Make sure the comments property is the same as the results.
		$this->assertSame( $results, $q->comments );

		// Make sure the comment type is `foobar`.
		$this->assertSame( 'foobar', $q->comments[0]->comment_type );
	}

	public static function filter_comments_pre_query_and_set_comments( $comments, $query ) {
		$c = self::factory()->comment->create(
			array(
				'comment_type'     => 'foobar',
				'comment_approved' => '1',
			)
		);

		return array( get_comment( $c ) );
	}

	/**
	 * @ticket 55460
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_comment_cache_key_should_ignore_unset_params() {
		$p = self::factory()->post->create();
		$c = self::factory()->comment->create( array( 'comment_post_ID' => $p ) );

		$_args = array(
			'post_id'                   => $p,
			'fields'                    => 'ids',
			'update_comment_meta_cache' => true,
			'update_comment_post_cache' => false,
		);

		$q1 = new WP_Comment_Query();
		$q1->query( $_args );

		$num_queries_all_args = get_num_queries();

		// Ignore 'fields', 'update_comment_meta_cache', 'update_comment_post_cache'.
		unset( $_args['fields'], $_args['update_comment_meta_cache'], $_args['update_comment_post_cache'] );
		$q2 = new WP_Comment_Query();
		$q2->query( $_args );

		$this->assertSame( $num_queries_all_args, get_num_queries() );
	}

	/**
	 * @ticket 55218
	 *
	 * @covers WP_Comment_Query::__construct
	 */
	public function test_unapproved_comment_with_meta_query_does_not_trigger_ambiguous_identifier_error() {
		$p       = self::$post_id;
		$c       = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $p,
				'comment_content'      => '1',
				'comment_approved'     => '0',
				'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', time() ),
				'comment_author_email' => 'foo@bar.mail',
				'comment_meta'         => array( 'foo' => 'bar' ),
			)
		);
		$comment = get_comment( $c );

		/*
		 * This is used to get a bunch of globals set up prior to making the
		 * database query. This helps with prepping for the moderation hash.
		 */
		$this->go_to(
			add_query_arg(
				array(
					'unapproved'      => $comment->comment_ID,
					'moderation-hash' => wp_hash( $comment->comment_date_gmt ),
				),
				get_comment_link( $comment )
			)
		);

		/*
		 * The result of the query is not needed so it's not assigned to variable.
		 *
		 * Returning the ID only limits the database query to only the one that was
		 * causing the error reported in ticket 55218.
		 */
		new WP_Comment_Query(
			array(
				'include_unapproved' => array( 'foo@bar.mail' ),
				'meta_query'         => array( array( 'key' => 'foo' ) ),
				'post_id'            => $p,
				'fields'             => 'ids',
			)
		);

		global $wpdb;
		$this->assertNotSame( "Column 'comment_ID' in where clause is ambiguous", $wpdb->last_error );
		$this->assertStringNotContainsString( ' comment_ID ', $wpdb->last_query );
	}
}
