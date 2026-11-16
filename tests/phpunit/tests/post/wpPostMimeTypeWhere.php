<?php

/**
 * Tests for wp_post_mime_type_where().
 *
 * @group post
 *
 * @covers ::wp_post_mime_type_where
 */
class Tests_Post_WpPostMimeTypeWhere extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_clause_for_mime_type_with_plus() {
		$this->assertSame(
			" AND (post_mime_type = 'image/svg+xml') ",
			wp_post_mime_type_where( 'image/svg+xml' )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_clause_for_mime_type_with_dot_and_dash() {
		$this->assertSame(
			" AND (post_mime_type = 'application/vnd.ms-excel') ",
			wp_post_mime_type_where( 'application/vnd.ms-excel' )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_removes_invalid_characters() {
		$this->assertSame(
			" AND (post_mime_type = 'image/jpegOR11') ",
			wp_post_mime_type_where( "image/jpeg' OR 1=1" )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_removes_invalid_characters_from_type() {
		$this->assertSame(
			" AND (post_mime_type = 'image/jpeg') ",
			wp_post_mime_type_where( "ima'ge/jpeg" )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_wildcard_cancels_preceding_restrictions() {
		$this->assertSame( '', wp_post_mime_type_where( array( 'image/jpeg', '%' ) ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_prefixes_column_with_table_alias() {
		$this->assertSame(
			" AND (p.post_mime_type = 'image/jpeg' OR p.post_mime_type LIKE 'video/%') ",
			wp_post_mime_type_where( 'image/jpeg,video', 'p' )
		);
	}

	/**
	 * Tests that empty items in a comma-separated string do not remove the restriction.
	 *
	 * @ticket 66454
	 *
	 * @dataProvider data_ignores_empty_items_in_comma_separated_string
	 *
	 * @param string $post_mime_types Comma-separated MIME types.
	 * @param string $expected        Expected SQL clause.
	 */
	public function test_ignores_empty_items_in_comma_separated_string( $post_mime_types, $expected ) {
		$this->assertSame( $expected, wp_post_mime_type_where( $post_mime_types ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_ignores_empty_items_in_comma_separated_string() {
		return array(
			'trailing comma'           => array( 'image/jpeg,', " AND (post_mime_type = 'image/jpeg') " ),
			'trailing comma and space' => array( 'image/jpeg, ', " AND (post_mime_type = 'image/jpeg') " ),
			'leading comma'            => array( ',image/jpeg', " AND (post_mime_type = 'image/jpeg') " ),
			'double comma'             => array( 'image/jpeg,,video', " AND (post_mime_type = 'image/jpeg' OR post_mime_type LIKE 'video/%') " ),
			'type with trailing comma' => array( 'image,', " AND (post_mime_type LIKE 'image/%') " ),
		);
	}

	/**
	 * Tests that an empty string and a wildcard in a comma-separated string still match every MIME type.
	 *
	 * @ticket 66454
	 *
	 * @dataProvider data_matches_every_mime_type
	 *
	 * @param string $post_mime_types MIME types.
	 */
	public function test_matches_every_mime_type( $post_mime_types ) {
		$this->assertSame( '', wp_post_mime_type_where( $post_mime_types ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_matches_every_mime_type() {
		return array(
			'empty string'           => array( '' ),
			'only commas and spaces' => array( ' , ' ),
			'wildcard after a type'  => array( 'image/jpeg,%' ),
		);
	}
}
