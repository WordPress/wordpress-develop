<?php

/**
 * @group post
 * @group media
 * @group upload
 *
 * @covers ::wp_count_attachments
 */
class Tests_Post_wpCountAttachments extends WP_UnitTestCase {

	/**
	 * Tests that the result is cached.
	 *
	 * @ticket 55227
	 */
	public function test_wp_count_attachments_should_cache_the_result() {
		$mime_type = 'image/jpeg';
		$cache_key = 'attachments:image_jpeg';

		self::factory()->post->create_many(
			3,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => $mime_type,
			)
		);
		$expected = wp_count_attachments( $mime_type );
		$actual   = wp_cache_get( $cache_key, 'counts' );

		// Keep assertEquals() because the objects are intentionally compared by value.
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * @ticket 66291
	 */
	public function test_pre_wp_count_attachments_short_circuits_query() {
		self::factory()->post->create_many(
			3,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$mime_type  = 'image/jpeg';
		$mock_count = 42;
		add_filter(
			'pre_wp_count_attachments',
			static function () use ( $mime_type, $mock_count ) {
				return (object) array( $mime_type => $mock_count );
			}
		);

		$start_num_queries = get_num_queries();
		$counts            = wp_count_attachments();

		$this->assertSame( 0, get_num_queries() - $start_num_queries, 'No database query should run when the filter returns a value.' );
		$this->assertObjectHasProperty( $mime_type, $counts );
		$this->assertSame( $mock_count, $counts->$mime_type, 'Count is expected to be filtered value.' );
		$this->assertObjectHasProperty( 'trash', $counts );
		$this->assertSame( 0, $counts->trash, 'Expected trash to be set to zero.' );
	}

	/**
	 * @ticket 66291
	 */
	public function test_pre_wp_count_attachments_takes_precedence_over_cached_counts() {
		self::factory()->post->create_many(
			3,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);

		wp_count_attachments();
		$this->assertNotFalse( wp_cache_get( 'attachments', 'counts' ), 'Expected the counts to be cached before the filter is added.' );

		$mime_type  = 'image/jpeg';
		$mock_count = 42;
		add_filter(
			'pre_wp_count_attachments',
			static function () use ( $mime_type, $mock_count ) {
				return (object) array( $mime_type => $mock_count );
			}
		);

		$this->assertSame( $mock_count, wp_count_attachments()->$mime_type, 'The filtered value should be returned instead of the cached counts.' );
	}

	/**
	 * @ticket 66291
	 */
	public function test_pre_wp_count_attachments_null_runs_default_query() {
		self::factory()->post->create_many(
			3,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);

		add_filter( 'pre_wp_count_attachments', '__return_null' );
		$start_num_queries = get_num_queries();

		$this->assertSame( '3', wp_count_attachments()->{'image/jpeg'}, 'Attachment count is expected to be set.' );
		$this->assertGreaterThan( 0, get_num_queries() - $start_num_queries, 'wp_count_attachments() is expected to trigger database queries.' );
	}

	/**
	 * @ticket 66291
	 *
	 * @dataProvider data_non_stdclass_values
	 *
	 * @param mixed $value Filtered value.
	 */
	public function test_pre_wp_count_attachments_non_stdclass_runs_default_query( $value ) {
		self::factory()->post->create_many(
			3,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);

		add_filter(
			'pre_wp_count_attachments',
			static function () use ( $value ) {
				return $value;
			}
		);
		$start_num_queries = get_num_queries();

		$this->assertSame( '3', wp_count_attachments()->{'image/jpeg'}, 'A filtered value that is not a stdClass object is expected to be ignored.' );
		$this->assertGreaterThan( 0, get_num_queries() - $start_num_queries, 'wp_count_attachments() is expected to trigger database queries.' );
	}

	/**
	 * Data provider for {@see self::test_pre_wp_count_attachments_non_stdclass_runs_default_query()}.
	 *
	 * @return array<non-falsy-string, array{ 0: mixed }>
	 */
	public function data_non_stdclass_values(): array {
		return array(
			'array'       => array( array( 'image/jpeg' => 42 ) ),
			'false'       => array( false ),
			'ArrayObject' => array( new ArrayObject( array( 'image/jpeg' => 42 ) ) ),
		);
	}

	/**
	 * @ticket 66291
	 */
	public function test_pre_wp_count_attachments_receives_mime_type() {
		$filter = new MockAction();
		add_filter( 'pre_wp_count_attachments', array( $filter, 'filter' ), 10, 2 );

		wp_count_attachments( array( 'image/jpeg', 'image/png' ) );

		$args = $filter->get_args();
		$this->assertSame( array( null, array( 'image/jpeg', 'image/png' ) ), $args[0] );
	}

	/**
	 * @ticket 66291
	 */
	public function test_pre_wp_count_attachments_keeps_filtered_trash_count() {
		$trash_count = 7;
		add_filter(
			'pre_wp_count_attachments',
			static function () use ( $trash_count ) {
				return (object) array( 'trash' => $trash_count );
			}
		);

		$this->assertSame( $trash_count, wp_count_attachments()->trash, 'Expected trash count to be the filtered value.' );
	}

	/**
	 * @ticket 66291
	 */
	public function test_pre_wp_count_attachments_value_is_passed_to_wp_count_attachments_filter() {
		add_filter(
			'pre_wp_count_attachments',
			static function () {
				return (object) array( 'image/jpeg' => 5 );
			}
		);
		$filter = new MockAction();
		add_filter( 'wp_count_attachments', array( $filter, 'filter' ), 10, 2 );

		wp_count_attachments( 'image' );

		$args = $filter->get_args();
		$this->assertSame( 1, $filter->get_call_count(), 'Expected the wp_count_attachments filter to run once.' );
		$this->assertEquals(
			array(
				(object) array(
					'image/jpeg' => 5,
					'trash'      => 0,
				),
				'image',
			),
			$args[0],
			'Expected the wp_count_attachments filter to receive the filtered counts and MIME type.'
		);
	}

	/**
	 * @ticket 66291
	 */
	public function test_pre_wp_count_attachments_does_not_modify_filtered_object() {
		$stored_counts = (object) array( 'image/jpeg' => 5 );
		add_filter(
			'pre_wp_count_attachments',
			static function () use ( $stored_counts ) {
				return $stored_counts;
			}
		);

		$counts = wp_count_attachments();

		$this->assertSame( 0, $counts->trash, 'Expected trash to be set to zero.' );
		$this->assertEquals( (object) array( 'image/jpeg' => 5 ), $stored_counts, 'The object returned by the filter should not be modified.' );
	}

	/**
	 * @ticket 66291
	 */
	public function test_pre_wp_count_attachments_result_is_not_cached() {
		self::factory()->post->create_many(
			3,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$filter = static function () {
			return (object) array( 'image/jpeg' => 99 );
		};
		add_filter( 'pre_wp_count_attachments', $filter );

		$this->assertSame( 99, wp_count_attachments( 'image/jpeg' )->{'image/jpeg'}, 'Expected the filtered count.' );
		$this->assertFalse( wp_cache_get( 'attachments:image_jpeg', 'counts' ), 'Expected the filtered counts not to be cached.' );

		remove_filter( 'pre_wp_count_attachments', $filter );
		$start_num_queries = get_num_queries();

		$this->assertSame( '3', wp_count_attachments( 'image/jpeg' )->{'image/jpeg'}, 'Expected the real count once the filter is removed.' );
		$this->assertGreaterThan( 0, get_num_queries() - $start_num_queries, 'Expected database queries since nothing should have been cached.' );
	}
}
