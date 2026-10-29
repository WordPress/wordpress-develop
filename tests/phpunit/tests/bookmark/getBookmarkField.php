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
	 *
	 * @dataProvider data_returns_sanitized_bookmark_fields
	 *
	 * @param string $field    Bookmark field name.
	 * @param string $expected Expected field value in display context.
	 */
	public function test_returns_sanitized_bookmark_fields( $field, $expected ) {
		$this->assertSame( $expected, get_bookmark_field( $field, self::$bookmark_id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_returns_sanitized_bookmark_fields() {
		return array(
			'link_name'        => array( 'link_name', 'Example Link' ),
			'link_url'         => array( 'link_url', 'https://example.com/' ),
			'link_description' => array( 'link_description', 'An example bookmark' ),
			'link_rel'         => array( 'link_rel', 'nofollow' ),
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_uses_sanitize_bookmark_field_for_context() {
		$bookmark = get_bookmark( self::$bookmark_id );

		$this->assertSame(
			sanitize_bookmark_field( 'link_name', $bookmark->link_name, self::$bookmark_id, 'raw' ),
			get_bookmark_field( 'link_name', self::$bookmark_id, 'raw' )
		);
		$this->assertSame(
			sanitize_bookmark_field( 'link_name', $bookmark->link_name, self::$bookmark_id, 'attribute' ),
			get_bookmark_field( 'link_name', self::$bookmark_id, 'attribute' )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_display_context_escapes_markup_in_link_name() {
		$bookmark_id = self::factory()->bookmark->create(
			array(
				'link_name' => 'Bold <b>Name</b> & Co',
				'link_url'  => 'https://example.org/',
			)
		);

		$expected = sanitize_bookmark_field(
			'link_name',
			get_bookmark( $bookmark_id )->link_name,
			$bookmark_id,
			'display'
		);

		$this->assertSame( $expected, get_bookmark_field( 'link_name', $bookmark_id, 'display' ) );
		$this->assertStringNotContainsString( '<b>', get_bookmark_field( 'link_name', $bookmark_id, 'display' ) );

		wp_delete_link( $bookmark_id );
	}
}
