<?php

/**
 * Tests for clean_attachment_cache().
 *
 * @group post
 *
 * @covers ::clean_attachment_cache
 */
class Tests_Post_CleanAttachmentCache extends WP_UnitTestCase {

	/**
	 * ID of the attachment in the tests.
	 *
	 * @var int
	 */
	protected static $attachment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$attachment_id = $factory->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		update_post_meta( self::$attachment_id, 'a_cache_test_field', 'a value' );
	}

	/**
	 * Fills the caches of the attachment.
	 */
	private function fill_the_caches() {
		get_post( self::$attachment_id );
		get_post_meta( self::$attachment_id );

		$this->assertNotFalse( wp_cache_get( self::$attachment_id, 'posts' ), 'The post cache should be filled.' );
		$this->assertNotFalse( wp_cache_get( self::$attachment_id, 'post_meta' ), 'The custom fields cache should be filled.' );
	}

	public function tear_down() {
		wp_suspend_cache_invalidation( false );

		parent::tear_down();
	}

	/**
	 * Tests that the cache of the attachment and of its custom fields is emptied.
	 *
	 * @ticket 66495
	 */
	public function test_should_empty_the_cache_of_the_attachment_and_its_custom_fields() {
		$this->fill_the_caches();

		clean_attachment_cache( self::$attachment_id );

		$this->assertFalse( wp_cache_get( self::$attachment_id, 'posts' ) );
		$this->assertFalse( wp_cache_get( self::$attachment_id, 'post_meta' ) );
	}

	/**
	 * Tests that an ID that is a string works.
	 *
	 * @ticket 66495
	 */
	public function test_should_accept_an_id_that_is_a_string() {
		$this->fill_the_caches();

		clean_attachment_cache( (string) self::$attachment_id );

		$this->assertFalse( wp_cache_get( self::$attachment_id, 'posts' ) );
	}

	/**
	 * Tests that the action fires with the ID as an integer.
	 *
	 * @ticket 66495
	 */
	public function test_should_fire_the_action_with_the_id_as_an_integer() {
		$received = array();
		add_action(
			'clean_attachment_cache',
			static function ( $id ) use ( &$received ) {
				$received[] = $id;
			}
		);

		clean_attachment_cache( (string) self::$attachment_id );

		$this->assertSame( array( self::$attachment_id ), $received );
	}

	/**
	 * Tests that the cache of the terms is emptied only when asked.
	 *
	 * @ticket 66495
	 */
	public function test_should_empty_the_cache_of_the_terms_only_when_asked() {
		$calls = array();
		add_action(
			'clean_object_term_cache',
			static function ( $object_ids, $object_type ) use ( &$calls ) {
				$calls[] = array( $object_ids, $object_type );
			},
			10,
			2
		);

		clean_attachment_cache( self::$attachment_id );

		$this->assertSame( array(), $calls, 'The terms should not be cleaned by default.' );

		clean_attachment_cache( self::$attachment_id, true );

		$this->assertSame( array( array( array( self::$attachment_id ), 'attachment' ) ), $calls );
	}

	/**
	 * Tests that nothing is done while the invalidation of the cache is suspended.
	 *
	 * @ticket 66495
	 */
	public function test_should_do_nothing_while_the_invalidation_of_the_cache_is_suspended() {
		$this->fill_the_caches();

		$action = new MockAction();
		add_action( 'clean_attachment_cache', array( $action, 'action' ) );

		wp_suspend_cache_invalidation( true );
		clean_attachment_cache( self::$attachment_id );

		$this->assertNotFalse( wp_cache_get( self::$attachment_id, 'posts' ), 'The post cache should be kept.' );
		$this->assertNotFalse( wp_cache_get( self::$attachment_id, 'post_meta' ), 'The custom fields cache should be kept.' );
		$this->assertSame( 0, $action->get_call_count(), 'The action should not fire.' );
	}
}
