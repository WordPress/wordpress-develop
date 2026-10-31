<?php

/**
 * Tests for the `get_edit_bookmark_link()` and `edit_bookmark_link()` functions.
 *
 * @group link
 * @group bookmark
 */
class Tests_Link_EditBookmarkLink extends WP_UnitTestCase {

	/**
	 * Bookmark ID.
	 *
	 * @var int
	 */
	private static $bookmark_id;

	/**
	 * Second bookmark ID, used as the global bookmark.
	 *
	 * @var int
	 */
	private static $global_bookmark_id;

	/**
	 * User IDs keyed by role.
	 *
	 * @var array<string, int>
	 */
	private static $user_ids = array();

	/**
	 * Whether the global bookmark was set before the test.
	 *
	 * @var bool
	 */
	private $had_global_link;

	/**
	 * Global bookmark before the test.
	 *
	 * @var mixed
	 */
	private $original_global_link;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$bookmark_id        = $factory->bookmark->create();
		self::$global_bookmark_id = $factory->bookmark->create();

		foreach ( array( 'administrator', 'editor', 'author', 'subscriber' ) as $role ) {
			self::$user_ids[ $role ] = $factory->user->create( array( 'role' => $role ) );
		}
	}

	/**
	 * Deletes the bookmarks, which are not removed between test classes.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_link( self::$bookmark_id );
		wp_delete_link( self::$global_bookmark_id );
	}

	public function set_up() {
		parent::set_up();

		$this->had_global_link = array_key_exists( 'link', $GLOBALS );
		if ( $this->had_global_link ) {
			$this->original_global_link = $GLOBALS['link'];
		}

		update_option( 'link_manager_enabled', 1 );
		wp_set_current_user( self::$user_ids['administrator'] );
	}

	public function tear_down() {
		if ( $this->had_global_link ) {
			$GLOBALS['link'] = $this->original_global_link;
		} else {
			unset( $GLOBALS['link'] );
		}

		parent::tear_down();
	}

	/**
	 * Tests that get_edit_bookmark_link() returns the bookmark edit screen URL.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_edit_bookmark_link
	 */
	public function test_get_edit_bookmark_link_returns_edit_url() {
		$this->assertSame(
			admin_url( 'link.php?action=edit&amp;link_id=' . self::$bookmark_id ),
			get_edit_bookmark_link( self::$bookmark_id )
		);
	}

	/**
	 * Tests that get_edit_bookmark_link() accepts a bookmark object.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_edit_bookmark_link
	 */
	public function test_get_edit_bookmark_link_accepts_bookmark_object() {
		$this->assertSame(
			admin_url( 'link.php?action=edit&amp;link_id=' . self::$bookmark_id ),
			get_edit_bookmark_link( get_bookmark( self::$bookmark_id ) )
		);
	}

	/**
	 * Tests that get_edit_bookmark_link() defaults to the global bookmark, and that
	 * an explicit bookmark takes precedence.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_edit_bookmark_link
	 */
	public function test_get_edit_bookmark_link_uses_global_bookmark_by_default() {
		$GLOBALS['link'] = get_bookmark( self::$global_bookmark_id );

		$this->assertSame(
			admin_url( 'link.php?action=edit&amp;link_id=' . self::$global_bookmark_id ),
			get_edit_bookmark_link(),
			'The global bookmark should be used by default.'
		);
		$this->assertSame(
			admin_url( 'link.php?action=edit&amp;link_id=' . self::$bookmark_id ),
			get_edit_bookmark_link( self::$bookmark_id ),
			'An explicit bookmark should be used instead of the global bookmark.'
		);
	}

	/**
	 * Tests that get_edit_bookmark_link() requires the manage_links capability.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_edit_bookmark_link
	 *
	 * @dataProvider data_users_without_manage_links
	 *
	 * @param string $role User role, or an empty string for a logged-out user.
	 */
	public function test_get_edit_bookmark_link_returns_null_without_capability( $role ) {
		wp_set_current_user( $role ? self::$user_ids[ $role ] : 0 );

		$this->assertNull( get_edit_bookmark_link( self::$bookmark_id ) );
	}

	/**
	 * Tests that editors, who have the manage_links capability, get the link.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_edit_bookmark_link
	 */
	public function test_get_edit_bookmark_link_for_editor() {
		wp_set_current_user( self::$user_ids['editor'] );

		$this->assertSame(
			admin_url( 'link.php?action=edit&amp;link_id=' . self::$bookmark_id ),
			get_edit_bookmark_link( self::$bookmark_id )
		);
	}

	/**
	 * Tests that get_edit_bookmark_link() returns null when the Link Manager is disabled.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_edit_bookmark_link
	 */
	public function test_get_edit_bookmark_link_returns_null_when_link_manager_disabled() {
		update_option( 'link_manager_enabled', 0 );

		$this->assertNull( get_edit_bookmark_link( self::$bookmark_id ) );
	}

	/**
	 * Tests the arguments passed to the 'get_edit_bookmark_link' filter and that its return value is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_edit_bookmark_link
	 */
	public function test_get_edit_bookmark_link_filter() {
		$filter = new MockAction();
		add_filter( 'get_edit_bookmark_link', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'get_edit_bookmark_link',
			static function ( $location, $link_id ) {
				return $location . '&amp;from=' . $link_id;
			},
			20,
			2
		);

		$location = get_edit_bookmark_link( self::$bookmark_id );

		$this->assertSame(
			array( admin_url( 'link.php?action=edit&amp;link_id=' . self::$bookmark_id ), self::$bookmark_id ),
			$filter->get_args()[0],
			'The filter should receive the edit URL and the bookmark ID.'
		);
		$this->assertSame(
			admin_url( 'link.php?action=edit&amp;link_id=' . self::$bookmark_id . '&amp;from=' . self::$bookmark_id ),
			$location,
			'The filtered URL should be returned.'
		);
	}

	/**
	 * Tests that edit_bookmark_link() displays the escaped edit link with the default text.
	 *
	 * @ticket 65819
	 *
	 * @covers ::edit_bookmark_link
	 */
	public function test_edit_bookmark_link_default_output() {
		$this->expectOutputString(
			'<a href="' . admin_url( 'link.php?action=edit&#038;link_id=' . self::$bookmark_id ) . '">Edit This</a>'
		);

		edit_bookmark_link( '', '', '', self::$bookmark_id );
	}

	/**
	 * Tests that edit_bookmark_link() displays custom text between the before and after markup.
	 *
	 * @ticket 65819
	 *
	 * @covers ::edit_bookmark_link
	 */
	public function test_edit_bookmark_link_custom_text_and_wrapper() {
		$this->expectOutputString(
			'<p><a href="' . admin_url( 'link.php?action=edit&#038;link_id=' . self::$bookmark_id ) . '">Edit bookmark</a></p>'
		);

		edit_bookmark_link( 'Edit bookmark', '<p>', '</p>', self::$bookmark_id );
	}

	/**
	 * Tests that edit_bookmark_link() defaults to the global bookmark.
	 *
	 * @ticket 65819
	 *
	 * @covers ::edit_bookmark_link
	 */
	public function test_edit_bookmark_link_uses_global_bookmark_by_default() {
		$GLOBALS['link'] = get_bookmark( self::$global_bookmark_id );

		$this->expectOutputString(
			'<a href="' . admin_url( 'link.php?action=edit&#038;link_id=' . self::$global_bookmark_id ) . '">Edit This</a>'
		);

		edit_bookmark_link();
	}

	/**
	 * Tests that edit_bookmark_link() displays nothing without the manage_links capability.
	 *
	 * @ticket 65819
	 *
	 * @covers ::edit_bookmark_link
	 *
	 * @dataProvider data_users_without_manage_links
	 *
	 * @param string $role User role, or an empty string for a logged-out user.
	 */
	public function test_edit_bookmark_link_displays_nothing_without_capability( $role ) {
		wp_set_current_user( $role ? self::$user_ids[ $role ] : 0 );

		$this->expectOutputString( '' );

		edit_bookmark_link( 'Edit bookmark', '<p>', '</p>', self::$bookmark_id );
	}

	/**
	 * Data provider for the capability tests.
	 *
	 * @return array[]
	 */
	public static function data_users_without_manage_links() {
		return array(
			'author'     => array( 'author' ),
			'subscriber' => array( 'subscriber' ),
			'logged out' => array( '' ),
		);
	}

	/**
	 * Tests that edit_bookmark_link() escapes the URL from the 'get_edit_bookmark_link' filter.
	 *
	 * @ticket 65819
	 *
	 * @covers ::edit_bookmark_link
	 */
	public function test_edit_bookmark_link_escapes_filtered_url() {
		add_filter(
			'get_edit_bookmark_link',
			static function () {
				return 'https://example.org/edit-bookmark/?a=1&b=2';
			}
		);

		$this->expectOutputString( '<a href="https://example.org/edit-bookmark/?a=1&#038;b=2">Edit This</a>' );

		edit_bookmark_link( '', '', '', self::$bookmark_id );
	}

	/**
	 * Tests the arguments passed to the 'edit_bookmark_link' filter and that its return value is displayed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::edit_bookmark_link
	 */
	public function test_edit_bookmark_link_filter() {
		$filter = new MockAction();
		add_filter( 'edit_bookmark_link', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'edit_bookmark_link',
			static function ( $link, $link_id ) {
				return '<span data-id="' . $link_id . '">' . $link . '</span>';
			},
			20,
			2
		);

		$anchor = '<a href="' . admin_url( 'link.php?action=edit&#038;link_id=' . self::$bookmark_id ) . '">Edit bookmark</a>';

		$this->expectOutputString( '<p><span data-id="' . self::$bookmark_id . '">' . $anchor . '</span></p>' );

		edit_bookmark_link( 'Edit bookmark', '<p>', '</p>', self::$bookmark_id );

		$this->assertSame(
			array( $anchor, self::$bookmark_id ),
			$filter->get_args()[0],
			'The filter should receive the anchor and the bookmark ID.'
		);
	}
}
