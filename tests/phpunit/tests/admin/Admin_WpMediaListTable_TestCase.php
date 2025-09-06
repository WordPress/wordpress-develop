<?php

abstract class Admin_WpMediaListTable_TestCase extends WP_UnitTestCase {

	/**
	 * A list table for testing.
	 *
	 * @var WP_Media_List_Table
	 */
	protected static $list_table;

	/**
	 * A reflection of the `$is_trash` property.
	 *
	 * @var ReflectionProperty
	 */
	protected static $is_trash;

	/**
	 * The original value of the `$is_trash` property.
	 *
	 * @var bool|null
	 */
	protected static $is_trash_original;

	/**
	 * A reflection of the `$detached` property.
	 *
	 * @var ReflectionProperty
	 */
	protected static $detached;

	/**
	 * The original value of the `$detached` property.
	 *
	 * @var bool|null
	 */
	protected static $detached_original;

	/**
	 * The ID of an 'administrator' user for testing.
	 *
	 * @var int
	 */
	protected static $admin;

	/**
	 * The ID of a 'subscriber' user for testing.
	 *
	 * @var int
	 */
	protected static $subscriber;

	/**
	 * A post for testing.
	 *
	 * @var WP_Post
	 */
	protected static $post;

	/**
	 * An attachment for testing.
	 *
	 * @var WP_Post
	 */
	protected static $attachment;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-media-list-table.php';

		self::$list_table = new WP_Media_List_Table();
		self::$is_trash   = new ReflectionProperty( self::$list_table, 'is_trash' );
		self::$detached   = new ReflectionProperty( self::$list_table, 'detached' );

		self::$is_trash->setAccessible( true );
		self::$is_trash_original = self::$is_trash->getValue( self::$list_table );
		self::$is_trash->setAccessible( false );

		self::$detached->setAccessible( true );
		self::$detached_original = self::$detached->getValue( self::$list_table );
		self::$detached->setAccessible( false );

		// Create users.
		self::$admin      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Create posts.
		self::$post       = self::factory()->post->create_and_get();
		self::$attachment = self::factory()->attachment->create_and_get(
			array(
				'post_name'      => 'attachment-name',
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	/**
	 * Restores reflections to their original values.
	 */
	public function tear_down() {
		self::set_is_trash( self::$is_trash_original );
		self::set_detached( self::$detached_original );

		parent::tear_down();
	}

	/**
	 * Sets the `$is_trash` property.
	 *
	 * Helper method.
	 *
	 * @param bool $is_trash Whether the attachment filter is currently 'trash'.
	 */
	protected static function set_is_trash( $is_trash ) {
		self::$is_trash->setAccessible( true );
		self::$is_trash->setValue( self::$list_table, $is_trash );
		self::$is_trash->setAccessible( false );
	}

	/**
	 * Sets the `$detached` property.
	 *
	 * Helper method.
	 *
	 * @param bool $detached Whether the attachment filter is currently 'detached'.
	 */
	protected static function set_detached( $detached ) {
		self::$detached->setAccessible( true );
		self::$detached->setValue( self::$list_table, $detached );
		self::$detached->setAccessible( false );
	}
}
