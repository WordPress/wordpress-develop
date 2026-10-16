<?php

/**
 * Tests for the WP_Metadata_Lazyloader class.
 *
 * @group meta
 *
 * @coversDefaultClass WP_Metadata_Lazyloader
 */
class Tests_Meta_wpMetadataLazyloader extends WP_UnitTestCase {

	/**
	 * IDs of terms that have metadata.
	 *
	 * @var int[]
	 */
	private static $term_ids;

	/**
	 * ID of a comment that has metadata.
	 *
	 * @var int
	 */
	private static $comment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$term_ids = $factory->term->create_many( 3 );

		foreach ( self::$term_ids as $term_id ) {
			add_term_meta( $term_id, 'color', 'blue' );
		}

		self::$comment_id = $factory->comment->create();

		add_comment_meta( self::$comment_id, 'rating', '5' );
	}

	public function set_up() {
		parent::set_up();

		// Creating the fixtures primed the metadata caches.
		foreach ( self::$term_ids as $term_id ) {
			wp_cache_delete( $term_id, 'term_meta' );
		}

		wp_cache_delete( self::$comment_id, 'comment_meta' );
	}

	/**
	 * Tests that queue_objects() and reset_queue() reject unknown object types.
	 *
	 * @ticket 65819
	 *
	 * @covers ::queue_objects
	 * @covers ::reset_queue
	 *
	 * @dataProvider data_invalid_object_types
	 *
	 * @param mixed $object_type Object type.
	 */
	public function test_invalid_object_type_returns_wp_error( $object_type ) {
		$lazyloader = new WP_Metadata_Lazyloader();

		$action = new MockAction();
		add_action( 'metadata_lazyloader_queued_objects', array( $action, 'action' ) );

		$queued = $lazyloader->queue_objects( $object_type, array( 1 ) );

		$this->assertWPError( $queued, 'Queueing objects should fail.' );
		$this->assertSame( 'invalid_object_type', $queued->get_error_code(), 'Queueing objects should report an invalid object type.' );
		$this->assertSame( 0, $action->get_call_count(), 'The queued objects action should not fire.' );

		$reset = $lazyloader->reset_queue( $object_type );

		$this->assertWPError( $reset, 'Resetting the queue should fail.' );
		$this->assertSame( 'invalid_object_type', $reset->get_error_code(), 'Resetting the queue should report an invalid object type.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_object_types() {
		return array(
			'post'           => array( 'post' ),
			'user'           => array( 'user' ),
			'unknown type'   => array( 'does_not_exist' ),
			'different case' => array( 'TERM' ),
			'empty string'   => array( '' ),
		);
	}

	/**
	 * Tests that queue_objects() hooks the lazy-load callback to the metadata filter of the object type.
	 *
	 * @ticket 65819
	 *
	 * @covers ::__construct
	 * @covers ::queue_objects
	 *
	 * @dataProvider data_object_type_filters
	 *
	 * @param string $object_type Object type.
	 * @param string $filter      Expected metadata filter.
	 */
	public function test_queue_objects_adds_metadata_filter( $object_type, $filter ) {
		$lazyloader = new WP_Metadata_Lazyloader();
		$callback   = array( $lazyloader, 'lazyload_meta_callback' );

		$this->assertFalse( has_filter( $filter, $callback ), 'The callback should not be hooked before objects are queued.' );
		$this->assertNull( $lazyloader->queue_objects( $object_type, array( 1, 2 ) ), 'Queueing objects should return nothing.' );
		$this->assertSame( 10, has_filter( $filter, $callback ), 'The callback should be hooked after objects are queued.' );

		$lazyloader->queue_objects( $object_type, array( 3 ) );

		$this->assertSame( 10, has_filter( $filter, $callback ), 'The callback should stay hooked when more objects are queued.' );
	}

	/**
	 * Tests that reset_queue() unhooks the lazy-load callback.
	 *
	 * @ticket 65819
	 *
	 * @covers ::reset_queue
	 *
	 * @dataProvider data_object_type_filters
	 *
	 * @param string $object_type Object type.
	 * @param string $filter      Expected metadata filter.
	 */
	public function test_reset_queue_removes_metadata_filter( $object_type, $filter ) {
		$lazyloader = new WP_Metadata_Lazyloader();
		$lazyloader->queue_objects( $object_type, array( 1, 2 ) );

		$this->assertNull( $lazyloader->reset_queue( $object_type ), 'Resetting the queue should return nothing.' );
		$this->assertFalse( has_filter( $filter, array( $lazyloader, 'lazyload_meta_callback' ) ), 'The callback should be unhooked.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_object_type_filters() {
		return array(
			'term'    => array( 'term', 'get_term_metadata' ),
			'comment' => array( 'comment', 'get_comment_metadata' ),
			'blog'    => array( 'blog', 'get_blog_metadata' ),
		);
	}

	/**
	 * Tests that the queued objects action fires with the object IDs, object type and lazy-loader.
	 *
	 * @ticket 65819
	 *
	 * @covers ::queue_objects
	 */
	public function test_queue_objects_fires_queued_objects_action() {
		$lazyloader = new WP_Metadata_Lazyloader();

		$action = new MockAction();
		add_action( 'metadata_lazyloader_queued_objects', array( $action, 'action' ), 10, 3 );

		$lazyloader->queue_objects( 'term', array( 4, 5 ) );
		$lazyloader->queue_objects( 'comment', array() );

		$this->assertSame(
			array(
				array( array( 4, 5 ), 'term', $lazyloader ),
				array( array(), 'comment', $lazyloader ),
			),
			$action->get_args()
		);
	}

	/**
	 * Tests that the callback primes the metadata cache of all queued objects and returns the value it was given.
	 *
	 * @ticket 65819
	 *
	 * @covers ::lazyload_meta_callback
	 * @covers ::queue_objects
	 *
	 * @dataProvider data_check_values
	 *
	 * @param mixed $check Value passed through the metadata filter.
	 */
	public function test_lazyload_meta_callback_primes_cache_of_queued_objects( $check ) {
		$lazyloader = new WP_Metadata_Lazyloader();
		$lazyloader->queue_objects( 'term', array( self::$term_ids[0], self::$term_ids[1] ) );

		$this->assertFalse( wp_cache_get( self::$term_ids[0], 'term_meta' ), 'Queueing objects should not prime the cache.' );

		$this->assertSame( $check, $lazyloader->lazyload_meta_callback( $check, 0, '', false, 'term' ), 'The value passed through the filter should be returned.' );

		$this->assertSame( array( 'color' => array( 'blue' ) ), wp_cache_get( self::$term_ids[0], 'term_meta' ), 'The first queued term should be primed.' );
		$this->assertSame( array( 'color' => array( 'blue' ) ), wp_cache_get( self::$term_ids[1], 'term_meta' ), 'The second queued term should be primed.' );
		$this->assertFalse( wp_cache_get( self::$term_ids[2], 'term_meta' ), 'A term that was not queued should not be primed.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_check_values() {
		return array(
			'null'         => array( null ),
			'false'        => array( false ),
			'empty string' => array( '' ),
			'empty array'  => array( array() ),
			'value'        => array( array( 'short-circuited' ) ),
		);
	}

	/**
	 * Tests that the callback also primes the requested object when it was not queued.
	 *
	 * @ticket 65819
	 *
	 * @covers ::lazyload_meta_callback
	 */
	public function test_lazyload_meta_callback_primes_cache_of_requested_object() {
		$lazyloader = new WP_Metadata_Lazyloader();
		$lazyloader->queue_objects( 'term', array( self::$term_ids[0] ) );

		$lazyloader->lazyload_meta_callback( null, self::$term_ids[2], 'color', true, 'term' );

		$this->assertSame( array( 'color' => array( 'blue' ) ), wp_cache_get( self::$term_ids[0], 'term_meta' ), 'The queued term should be primed.' );
		$this->assertSame( array( 'color' => array( 'blue' ) ), wp_cache_get( self::$term_ids[2], 'term_meta' ), 'The requested term should be primed.' );
		$this->assertFalse( wp_cache_get( self::$term_ids[1], 'term_meta' ), 'A term that was neither queued nor requested should not be primed.' );
	}

	/**
	 * Tests that the callback empties the queue and unhooks itself after priming the cache.
	 *
	 * @ticket 65819
	 *
	 * @covers ::lazyload_meta_callback
	 * @covers ::reset_queue
	 */
	public function test_lazyload_meta_callback_resets_queue() {
		$lazyloader = new WP_Metadata_Lazyloader();
		$lazyloader->queue_objects( 'term', array( self::$term_ids[0] ) );
		$lazyloader->lazyload_meta_callback( null, 0, '', false, 'term' );

		$this->assertFalse( has_filter( 'get_term_metadata', array( $lazyloader, 'lazyload_meta_callback' ) ), 'The callback should be unhooked.' );

		wp_cache_delete( self::$term_ids[0], 'term_meta' );

		$lazyloader->lazyload_meta_callback( null, 0, '', false, 'term' );

		$this->assertFalse( wp_cache_get( self::$term_ids[0], 'term_meta' ), 'The term should no longer be queued.' );
	}

	/**
	 * Tests that the callback does nothing when no objects of the requested type are queued.
	 *
	 * @ticket 65819
	 *
	 * @covers ::lazyload_meta_callback
	 *
	 * @dataProvider data_meta_types_without_queued_objects
	 *
	 * @param string $meta_type Metadata type requested through the filter.
	 */
	public function test_lazyload_meta_callback_does_nothing_without_queued_objects_of_type( $meta_type ) {
		$lazyloader = new WP_Metadata_Lazyloader();
		$lazyloader->queue_objects( 'term', array( self::$term_ids[0] ) );
		$lazyloader->queue_objects( 'comment', array() );

		$this->assertSame( 'check', $lazyloader->lazyload_meta_callback( 'check', self::$comment_id, 'rating', true, $meta_type ), 'The value passed through the filter should be returned.' );
		$this->assertFalse( wp_cache_get( self::$term_ids[0], 'term_meta' ), 'The queued term should not be primed.' );
		$this->assertFalse( wp_cache_get( self::$comment_id, 'comment_meta' ), 'The requested comment should not be primed.' );
		$this->assertSame( 10, has_filter( 'get_term_metadata', array( $lazyloader, 'lazyload_meta_callback' ) ), 'The term queue should be kept.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_meta_types_without_queued_objects() {
		return array(
			'type with an empty queue'   => array( 'comment' ),
			'type that was never queued' => array( 'blog' ),
			'unknown type'               => array( 'post' ),
		);
	}

	/**
	 * Tests that reset_queue() empties the queue of the given object type only.
	 *
	 * @ticket 65819
	 *
	 * @covers ::reset_queue
	 */
	public function test_reset_queue_only_empties_queue_of_object_type() {
		$lazyloader = new WP_Metadata_Lazyloader();
		$lazyloader->queue_objects( 'term', array( self::$term_ids[0] ) );
		$lazyloader->queue_objects( 'comment', array( self::$comment_id ) );

		$lazyloader->reset_queue( 'term' );

		$lazyloader->lazyload_meta_callback( null, 0, '', false, 'term' );
		$lazyloader->lazyload_meta_callback( null, 0, '', false, 'comment' );

		$this->assertFalse( wp_cache_get( self::$term_ids[0], 'term_meta' ), 'The term should no longer be queued.' );
		$this->assertSame( array( 'rating' => array( '5' ) ), wp_cache_get( self::$comment_id, 'comment_meta' ), 'The comment should still be queued.' );
	}

	/**
	 * Tests that queued objects are lazy-loaded when metadata of that type is requested.
	 *
	 * @ticket 65819
	 *
	 * @covers ::queue_objects
	 * @covers ::lazyload_meta_callback
	 */
	public function test_queued_objects_are_primed_when_metadata_is_requested() {
		$lazyloader = new WP_Metadata_Lazyloader();
		$lazyloader->queue_objects( 'term', array( self::$term_ids[0], self::$term_ids[1] ) );

		$this->assertSame( 'blue', get_term_meta( self::$term_ids[2], 'color', true ), 'The requested metadata should be returned.' );
		$this->assertSame( array( 'color' => array( 'blue' ) ), wp_cache_get( self::$term_ids[0], 'term_meta' ), 'The first queued term should be primed.' );
		$this->assertSame( array( 'color' => array( 'blue' ) ), wp_cache_get( self::$term_ids[1], 'term_meta' ), 'The second queued term should be primed.' );
	}

	/**
	 * Tests the deprecated lazyload_term_meta() method.
	 *
	 * @ticket 65819
	 *
	 * @covers ::lazyload_term_meta
	 *
	 * @expectedDeprecated WP_Metadata_Lazyloader::lazyload_term_meta
	 */
	public function test_lazyload_term_meta_is_deprecated_and_primes_term_meta() {
		$lazyloader = new WP_Metadata_Lazyloader();
		$lazyloader->queue_objects( 'term', array( self::$term_ids[0] ) );
		$lazyloader->queue_objects( 'comment', array( self::$comment_id ) );

		$this->assertSame( 'check', $lazyloader->lazyload_term_meta( 'check' ), 'The value passed through the filter should be returned.' );
		$this->assertSame( array( 'color' => array( 'blue' ) ), wp_cache_get( self::$term_ids[0], 'term_meta' ), 'The queued term should be primed.' );
		$this->assertFalse( wp_cache_get( self::$comment_id, 'comment_meta' ), 'The queued comment should not be primed.' );
	}

	/**
	 * Tests the deprecated lazyload_comment_meta() method.
	 *
	 * @ticket 65819
	 *
	 * @covers ::lazyload_comment_meta
	 *
	 * @expectedDeprecated WP_Metadata_Lazyloader::lazyload_comment_meta
	 */
	public function test_lazyload_comment_meta_is_deprecated_and_primes_comment_meta() {
		$lazyloader = new WP_Metadata_Lazyloader();
		$lazyloader->queue_objects( 'term', array( self::$term_ids[0] ) );
		$lazyloader->queue_objects( 'comment', array( self::$comment_id ) );

		$this->assertSame( 'check', $lazyloader->lazyload_comment_meta( 'check' ), 'The value passed through the filter should be returned.' );
		$this->assertSame( array( 'rating' => array( '5' ) ), wp_cache_get( self::$comment_id, 'comment_meta' ), 'The queued comment should be primed.' );
		$this->assertFalse( wp_cache_get( self::$term_ids[0], 'term_meta' ), 'The queued term should not be primed.' );
	}
}
