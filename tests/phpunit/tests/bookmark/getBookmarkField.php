<?php
/**
 * Tests for get_bookmark_field().
 *
 * @group bookmark
 *
 * @covers ::get_bookmark_field
 */
class Tests_Bookmark_GetBookmarkField extends WP_UnitTestCase {

	/**
	 * Bookmark ID.
	 *
	 * @var int
	 */
	private static $bookmark_id;

	/**
	 * Create shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$bookmark_id = $factory->bookmark->create(
			array(
				'link_name'        => 'Example Link',
				'link_url'         => 'https://example.com/',
				'link_description' => 'An example bookmark',
				'link_rel'         => 'nofollow',
			)
		);
	}

	/**
	 * Delete shared fixtures.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_link( self::$bookmark_id );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_invalid_bookmark() {
		$this->assertSame( '', get_bookmark_field( 'link_name', 0 ) );
		$this->assertSame( '', get_bookmark_field( 'link_name', PHP_INT_MAX ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_unknown_field() {
		$this->assertSame( '', get_bookmark_field( 'not_a_real_field', self::$bookmark_id ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_sanitized_bookmark_field() {
		$this->assertSame( 'Example Link', get_bookmark_field( 'link_name', self::$bookmark_id ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_forwards_context_to_sanitize_bookmark_field() {
		add_filter(
			'link_name',
			static function ( $value ) {
				return $value . '|link_name';
			}
		);
		add_filter(
			'edit_link_name',
			static function ( $value ) {
				return $value . '|edit_link_name';
			}
		);

		$this->assertSame( 'Example Link', get_bookmark_field( 'link_name', self::$bookmark_id, 'raw' ) );
		$this->assertSame( 'Example Link|link_name', get_bookmark_field( 'link_name', self::$bookmark_id, 'display' ) );
		$this->assertSame( 'Example Link|link_name', get_bookmark_field( 'link_name', self::$bookmark_id, 'attribute' ) );
		$this->assertSame( 'Example Link|edit_link_name', get_bookmark_field( 'link_name', self::$bookmark_id, 'edit' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_attribute_context_escapes_markup_in_link_name() {
		$bookmark      = get_bookmark( self::$bookmark_id );
		$original_name = $bookmark->link_name;

		$bookmark->link_name = 'Bold <b>Name</b> & Co';
		wp_cache_set( self::$bookmark_id, $bookmark, 'bookmark' );

		remove_all_filters( 'link_name' );

		$this->assertSame(
			'Bold &lt;b&gt;Name&lt;/b&gt; &amp; Co',
			get_bookmark_field( 'link_name', self::$bookmark_id, 'attribute' )
		);

		$bookmark->link_name = $original_name;
		wp_cache_set( self::$bookmark_id, $bookmark, 'bookmark' );
	}
}
