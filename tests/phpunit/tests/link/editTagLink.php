<?php

/**
 * @group link
 *
 * @covers ::edit_tag_link
 */
class Tests_Link_EditTagLink extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Tag ID.
	 *
	 * @var int
	 */
	protected static $tag_id;

	/**
	 * Tag object.
	 *
	 * @var WP_Term
	 */
	protected static $tag_object;

	/**
	 * Set up before class.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		self::$tag_id     = $factory->tag->create( array( 'name' => 'Alpha Tag' ) );
		self::$tag_object = get_term( self::$tag_id, 'post_tag' );
	}

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Tests that edit_tag_link() outputs the default anchor text and URL when link text is empty.
	 *
	 * @ticket 65819
	 */
	public function test_edit_tag_link_default_link_text() {
		$expected_url = get_edit_term_link( self::$tag_id, 'post_tag' );

		ob_start();
		edit_tag_link( '', '', '', self::$tag_object );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Edit This', $output );
		$this->assertStringContainsString( 'href="' . $expected_url . '"', $output );
	}

	/**
	 * Tests that edit_tag_link() respects custom anchor text.
	 *
	 * @ticket 65819
	 */
	public function test_edit_tag_link_custom_link_text() {
		ob_start();
		edit_tag_link( 'Modify Tag', '', '', self::$tag_object );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Modify Tag', $output );
		$this->assertStringNotContainsString( 'Edit This', $output );
	}

	/**
	 * Tests that edit_tag_link() prepends before and appends after strings.
	 *
	 * @ticket 65819
	 */
	public function test_edit_tag_link_before_and_after() {
		$before = '<span class="wrapper-before">';
		$after  = '</span><!-- wrapper-after -->';

		ob_start();
		edit_tag_link( 'Edit', $before, $after, self::$tag_object );
		$output = ob_get_clean();

		$this->assertStringStartsWith( $before, $output );
		$this->assertStringEndsWith( $after, $output );
	}

	/**
	 * Tests that edit_tag_link() accepts a term ID.
	 *
	 * @ticket 65819
	 */
	public function test_edit_tag_link_with_term_id() {
		$expected_url = get_edit_term_link( self::$tag_id, 'post_tag' );

		ob_start();
		edit_tag_link( 'Edit', '', '', self::$tag_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'href="' . $expected_url . '"', $output );
	}

	/**
	 * Tests that edit_tag_link() uses the queried object when tag argument is null.
	 *
	 * @ticket 65819
	 */
	public function test_edit_tag_link_defaults_to_queried_object() {
		global $wp_query;

		$expected_url = get_edit_term_link( self::$tag_id, 'post_tag' );

		$original_queried_object    = $wp_query->queried_object;
		$original_queried_object_id = $wp_query->queried_object_id;

		$wp_query->queried_object    = self::$tag_object;
		$wp_query->queried_object_id = self::$tag_id;

		ob_start();
		edit_tag_link( 'Edit Queried Tag' );
		$output = ob_get_clean();

		$wp_query->queried_object    = $original_queried_object;
		$wp_query->queried_object_id = $original_queried_object_id;

		$this->assertStringContainsString( 'Edit Queried Tag', $output );
		$this->assertStringContainsString( 'href="' . $expected_url . '"', $output );
	}

	/**
	 * Tests that edit_tag_link() outputs only before and after (no link) for unauthorized users.
	 *
	 * @ticket 65819
	 */
	public function test_edit_tag_link_unauthorized_user() {
		wp_set_current_user( self::$subscriber_id );

		ob_start();
		edit_tag_link( 'Edit', '<p>', '</p>', self::$tag_object );
		$output = ob_get_clean();

		$this->assertSame( '<p></p>', $output );
	}

	/**
	 * Tests that edit_tag_link() outputs empty string for unauthorized users when before and after are empty.
	 *
	 * @ticket 65819
	 */
	public function test_edit_tag_link_unauthorized_user_empty_before_after() {
		wp_set_current_user( self::$subscriber_id );

		ob_start();
		edit_tag_link( 'Edit', '', '', self::$tag_object );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Tests that edit_tag_link() outputs empty string when queried object is empty and no tag is passed.
	 *
	 * @ticket 65819
	 */
	public function test_edit_tag_link_empty_queried_object() {
		global $wp_query;

		$original_queried_object    = $wp_query->queried_object;
		$original_queried_object_id = $wp_query->queried_object_id;

		$wp_query->queried_object    = null;
		$wp_query->queried_object_id = 0;

		ob_start();
		edit_tag_link();
		$output = ob_get_clean();

		$wp_query->queried_object    = $original_queried_object;
		$wp_query->queried_object_id = $original_queried_object_id;

		$this->assertSame( '', $output );
	}

	/**
	 * Tests that edit_tag_link() outputs empty string for an invalid tag ID.
	 *
	 * @ticket 65819
	 */
	public function test_edit_tag_link_invalid_tag() {
		ob_start();
		edit_tag_link( 'Edit', '', '', 999999 );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Tests the 'edit_tag_link' filter.
	 *
	 * @ticket 65819
	 */
	public function test_edit_tag_link_filter() {
		add_filter(
			'edit_tag_link',
			static function ( $link ) {
				return '<span class="filtered-link">' . $link . '</span>';
			}
		);

		ob_start();
		edit_tag_link( 'Edit', '', '', self::$tag_object );
		$output = ob_get_clean();

		$this->assertStringStartsWith( '<span class="filtered-link"><a', $output );
		$this->assertStringEndsWith( '</a></span>', $output );
	}
}
