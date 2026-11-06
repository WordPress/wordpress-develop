<?php

/**
 * Tests for menu_page_url().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::menu_page_url
 */
class Tests_Admin_Includes_Plugin_MenuPageUrl extends WP_UnitTestCase {

	/**
	 * The original value of the parent pages.
	 *
	 * @var array|null
	 */
	private $original_parent_pages;

	/**
	 * Starts without pages.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_parent_pages = $GLOBALS['_parent_pages'] ?? null;
		$GLOBALS['_parent_pages']    = array();
	}

	/**
	 * Restores the pages.
	 */
	public function tear_down() {
		if ( null === $this->original_parent_pages ) {
			unset( $GLOBALS['_parent_pages'] );
		} else {
			$GLOBALS['_parent_pages'] = $this->original_parent_pages;
		}

		parent::tear_down();
	}

	/**
	 * Tests that the URL is output by default.
	 *
	 * @ticket 66557
	 */
	public function test_should_output_the_url_by_default() {
		$GLOBALS['_parent_pages']['my-page'] = false;

		ob_start();
		$url = menu_page_url( 'my-page' );
		$out = ob_get_clean();

		$this->assertSame( admin_url( 'admin.php?page=my-page' ), $url, 'The URL should be returned.' );
		$this->assertSame( $url, $out, 'The URL should be output.' );
	}

	/**
	 * Tests that the URL is not output when it is not displayed.
	 *
	 * @ticket 66557
	 */
	public function test_should_not_output_the_url_when_it_is_not_displayed() {
		$GLOBALS['_parent_pages']['my-page'] = false;

		ob_start();
		$url = menu_page_url( 'my-page', false );
		$out = ob_get_clean();

		$this->assertSame( admin_url( 'admin.php?page=my-page' ), $url );
		$this->assertSame( '', $out );
	}

	/**
	 * Tests that nothing is output for a page that does not exist.
	 *
	 * @ticket 66557
	 */
	public function test_should_output_nothing_for_a_page_that_does_not_exist() {
		ob_start();
		$url = menu_page_url( 'missing-page' );
		$out = ob_get_clean();

		$this->assertSame( '', $url );
		$this->assertSame( '', $out );
	}

	/**
	 * Tests the URL of a page of a parent that is a file.
	 *
	 * @ticket 66557
	 */
	public function test_should_add_the_page_to_the_file_of_the_parent() {
		$GLOBALS['_parent_pages']['my-page'] = 'tools.php';

		$this->assertSame( admin_url( 'tools.php?page=my-page' ), menu_page_url( 'my-page', false ) );
	}

	/**
	 * Tests the URL of a page of a parent that is a page.
	 *
	 * @ticket 66557
	 */
	public function test_should_use_the_admin_page_for_a_parent_that_is_a_page() {
		$GLOBALS['_parent_pages']['my-parent'] = false;
		$GLOBALS['_parent_pages']['my-page']   = 'my-parent';

		$this->assertSame( admin_url( 'admin.php?page=my-page' ), menu_page_url( 'my-page', false ) );
	}

	/**
	 * Tests that the URL is escaped.
	 *
	 * @ticket 66557
	 */
	public function test_should_escape_the_url() {
		$GLOBALS['_parent_pages']['my-page'] = 'edit.php?post_type=page';

		$this->assertSame( admin_url( 'edit.php?post_type=page&#038;page=my-page' ), menu_page_url( 'my-page', false ) );
	}
}
