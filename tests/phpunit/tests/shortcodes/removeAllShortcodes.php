<?php

/**
 * Tests for the remove_all_shortcodes() function.
 *
 * @group shortcode
 *
 * @covers ::remove_all_shortcodes
 */
class Tests_Shortcodes_RemoveAllShortcodes extends WP_UnitTestCase {

	/**
	 * Registered shortcodes, to restore after each test.
	 *
	 * @var array
	 */
	private $original_shortcode_tags;

	public function set_up() {
		parent::set_up();

		global $shortcode_tags;
		$this->original_shortcode_tags = $shortcode_tags;
	}

	public function tear_down() {
		global $shortcode_tags;
		$shortcode_tags = $this->original_shortcode_tags;

		parent::tear_down();
	}

	/**
	 * Tests that all the registered shortcodes are removed.
	 *
	 * @ticket 66500
	 */
	public function test_should_remove_all_the_registered_shortcodes() {
		global $shortcode_tags;

		add_shortcode( 'remove-all-one', '__return_empty_string' );
		add_shortcode( 'remove-all-two', '__return_empty_string' );

		$this->assertTrue( shortcode_exists( 'remove-all-one' ), 'The first shortcode should be registered.' );
		$this->assertTrue( shortcode_exists( 'remove-all-two' ), 'The second shortcode should be registered.' );

		remove_all_shortcodes();

		$this->assertFalse( shortcode_exists( 'remove-all-one' ), 'The first shortcode should be removed.' );
		$this->assertFalse( shortcode_exists( 'remove-all-two' ), 'The second shortcode should be removed.' );
		$this->assertSame( array(), $shortcode_tags, 'The list of shortcodes should be empty.' );
	}

	/**
	 * Tests that the shortcodes of WordPress are removed too.
	 *
	 * @ticket 66500
	 */
	public function test_should_remove_the_shortcodes_of_core() {
		$this->assertTrue( shortcode_exists( 'gallery' ), 'The gallery shortcode should be registered by default.' );

		remove_all_shortcodes();

		$this->assertFalse( shortcode_exists( 'gallery' ) );
	}

	/**
	 * Tests that the content is no longer processed after the shortcodes are removed.
	 *
	 * @ticket 66500
	 */
	public function test_should_not_process_the_content_after_removing_the_shortcodes() {
		add_shortcode( 'remove-all-hello', static fn() => 'Hello' );

		$this->assertSame( 'Hello', do_shortcode( '[remove-all-hello]' ), 'The shortcode should be processed before it is removed.' );

		remove_all_shortcodes();

		$this->assertSame( '[remove-all-hello]', do_shortcode( '[remove-all-hello]' ), 'The shortcode should be left as it is after it is removed.' );
	}

	/**
	 * Tests that shortcodes can be registered again after removing all of them.
	 *
	 * @ticket 66500
	 */
	public function test_should_allow_to_register_shortcodes_again() {
		remove_all_shortcodes();

		add_shortcode( 'remove-all-again', static fn() => 'Again' );

		$this->assertTrue( shortcode_exists( 'remove-all-again' ) );
		$this->assertSame( 'Again', do_shortcode( '[remove-all-again]' ) );
	}

	/**
	 * Tests that removing the shortcodes does not fail when there are none.
	 *
	 * @ticket 66500
	 */
	public function test_should_not_fail_when_there_are_no_shortcodes() {
		global $shortcode_tags;

		remove_all_shortcodes();
		remove_all_shortcodes();

		$this->assertSame( array(), $shortcode_tags );
	}
}
