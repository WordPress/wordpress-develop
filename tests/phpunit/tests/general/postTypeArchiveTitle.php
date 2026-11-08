<?php

/**
 * Tests for the `post_type_archive_title()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::post_type_archive_title
 */
class Tests_General_PostTypeArchiveTitle extends WP_UnitTestCase {

	const POST_TYPE = 'wptests_pt';

	public function set_up() {
		parent::set_up();

		register_post_type(
			self::POST_TYPE,
			array(
				'public'      => true,
				'has_archive' => true,
				'labels'      => array(
					'name' => 'Custom Books',
				),
			)
		);
	}

	/**
	 * Tests that post_type_archive_title() returns null when not on a post type archive.
	 *
	 * @ticket 65819
	 */
	public function test_post_type_archive_title_returns_null_outside_archive() {
		$this->go_to( '/' );

		$this->assertNull( post_type_archive_title( '', false ) );
	}

	/**
	 * Tests that post_type_archive_title() displays the title on a post type archive.
	 *
	 * @ticket 65819
	 */
	public function test_post_type_archive_title_should_display_on_archive() {
		$this->go_to( '/?post_type=' . self::POST_TYPE );

		$this->expectOutputString( 'Custom Books' );
		post_type_archive_title();
	}

	/**
	 * Tests that post_type_archive_title() returns the title when $display is false.
	 *
	 * @ticket 65819
	 */
	public function test_post_type_archive_title_returns_when_display_is_false() {
		$this->go_to( '/?post_type=' . self::POST_TYPE );

		$this->assertSame( 'Custom Books', post_type_archive_title( '', false ) );
	}

	/**
	 * Tests that post_type_archive_title() prepends the prefix to the title.
	 *
	 * @ticket 65819
	 */
	public function test_post_type_archive_title_prepends_prefix() {
		$this->go_to( '/?post_type=' . self::POST_TYPE );

		$this->expectOutputString( 'Archive: Custom Books' );
		post_type_archive_title( 'Archive: ' );
	}

	/**
	 * Tests that post_type_archive_title() respects the post_type_archive_title filter.
	 *
	 * @ticket 65819
	 */
	public function test_post_type_archive_title_filter() {
		$this->go_to( '/?post_type=' . self::POST_TYPE );

		add_filter(
			'post_type_archive_title',
			static function ( $title, $post_type ) {
				return 'Filtered: ' . $title . ' (' . $post_type . ')';
			},
			10,
			2
		);

		$this->assertSame( 'Filtered: Custom Books (wptests_pt)', post_type_archive_title( '', false ) );
	}
}
