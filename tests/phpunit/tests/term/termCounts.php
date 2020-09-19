<?php

/**
 * @group taxonomy
 */
class Tests_Term_termCount extends WP_UnitTestCase {

	/**
	 * Term ID for testing attachment counts.
	 *
	 * @var int
	 */
	public static $attachment_term;

	/**
	 * Post IDs of shared posts.
	 *
	 * @var int[]
	 */
	public static $post_ids;

	/**
	 * Create shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test suite factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		$statuses = array( 'publish', 'auto-draft', 'draft', 'private' );
		foreach ( $statuses as $status ) {
			self::$post_ids[ $status ] = $factory->post->create( array( 'post_status' => $status ) );
		}

		register_taxonomy( 'wp_test_tax_counts', array( 'post', 'attachment' ) );
		self::$attachment_term = $factory->term->create( array( 'taxonomy' => 'wp_test_tax_counts' ) );
	}

	public function setUp() {
		parent::setUp();

		register_taxonomy( 'wp_test_tax_counts', array( 'post', 'attachment' ) );
	}

	/**
	 * Term counts increments correctly when post status becomes published.
	 *
	 * @covers wp_publish_post
	 * @covers wp_count_terms
	 * @dataProvider data_term_counts_incremented_on_publish
	 * @ticket 40351
	 * @ticket 51292
	 *
	 * @param string $original_post_status Post status prior to change to publish.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_counts_incremented_on_publish( $original_post_status, $change ) {
		$post_id    = self::$post_ids[ $original_post_status ];
		$term_count = get_term( get_option( 'default_category' ) )->count;

		wp_publish_post( $post_id );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( get_option( 'default_category' ) )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status prior to change to publish.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	function data_term_counts_incremented_on_publish() {
		return array(
			// 0. Published post
			array( 'publish', 0 ),
			// 1. Auto draft
			array( 'auto-draft', 1 ),
			// 2. Draft
			array( 'draft', 1 ),
			// 3. Private post
			array( 'private', 1 ),
		);
	}

	/**
	 * Term counts increments correctly when post status becomes published.
	 *
	 * @covers wp_publish_post
	 * @dataProvider data_term_counts_incremented_on_publish_with_attachments
	 * @ticket 40351
	 * @ticket 51292
	 *
	 * @param string $original_post_status Post status prior to change to publish.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_counts_incremented_on_publish_with_attachments( $original_post_status, $change ) {
		$post_id = self::$post_ids[ $original_post_status ];
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		wp_add_object_terms( $attachment_id, self::$attachment_term, 'wp_test_tax_counts' );
		$term_count = get_term( self::$attachment_term )->count;

		wp_publish_post( $post_id );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses_with_attachments.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status prior to change to publish.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	function data_term_counts_incremented_on_publish_with_attachments() {
		return array(
			// 0. Published post
			array( 'publish', 0 ),
			// 1. Auto draft
			array( 'auto-draft', 2 ),
			// 2. Draft
			array( 'draft', 2 ),
			// 3. Private post
			array( 'private', 2 ),
		);
	}

	/**
	 * Term counts increments correctly when post status becomes published.
	 *
	 * @covers wp_publish_post
	 * @dataProvider data_term_counts_incremented_on_publish_with_untermed_attachments
	 * @ticket 40351
	 * @ticket 51292
	 *
	 * @param string $original_post_status Post status prior to change to publish.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_counts_incremented_on_publish_with_untermed_attachments( $original_post_status, $change ) {
		$post_id = self::$post_ids[ $original_post_status ];
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		$term_count    = get_term( self::$attachment_term )->count;

		wp_publish_post( $post_id );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses_with_untermed_attachments.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status prior to change to publish.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	function data_term_counts_incremented_on_publish_with_untermed_attachments() {
		return array(
			// 0. Published post
			array( 'publish', 0 ),
			// 1. Auto draft
			array( 'auto-draft', 1 ),
			// 2. Draft
			array( 'draft', 1 ),
			// 3. Private post
			array( 'private', 1 ),
		);
	}
}
