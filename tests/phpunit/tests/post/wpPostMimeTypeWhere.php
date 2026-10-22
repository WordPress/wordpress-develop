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
	 * Tests the SQL clause built for mime types.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_mime_types
	 *
	 * @param string|string[] $post_mime_types Mime types to query.
	 * @param string          $expected        Expected SQL clause.
	 */
	public function test_returns_clause_for_mime_types( $post_mime_types, $expected ) {
		$this->assertSame( $expected, wp_post_mime_type_where( $post_mime_types ) );
	}

	/**
	 * Data provider for mime types.
	 *
	 * @return array[]
	 */
	public static function data_mime_types() {
		return array(
			'type without subtype'      => array( 'image', " AND (post_mime_type LIKE 'image/%') " ),
			'type and subtype'          => array( 'image/jpeg', " AND (post_mime_type = 'image/jpeg') " ),
			'wildcard subtype'          => array( 'image/*', " AND (post_mime_type LIKE 'image/%') " ),
			'empty subtype'             => array( 'image/', " AND (post_mime_type LIKE 'image/%') " ),
			'repeated wildcards'        => array( 'image/**', " AND (post_mime_type LIKE 'image/%') " ),
			'subtype with plus'         => array( 'image/svg+xml', " AND (post_mime_type = 'image/svg+xml') " ),
			'subtype with dot and dash' => array( 'application/vnd.ms-excel', " AND (post_mime_type = 'application/vnd.ms-excel') " ),
			'whitespace'                => array( ' image / jpeg ', " AND (post_mime_type = 'image/jpeg') " ),
			'comma-separated string'    => array( 'image/jpeg, video', " AND (post_mime_type = 'image/jpeg' OR post_mime_type LIKE 'video/%') " ),
			'array'                     => array( array( 'image/jpeg', 'video' ), " AND (post_mime_type = 'image/jpeg' OR post_mime_type LIKE 'video/%') " ),
		);
	}

	/**
	 * Tests that characters which are not allowed in a mime type are removed.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_mime_types_with_invalid_characters
	 *
	 * @param string $post_mime_types Mime types to query.
	 * @param string $expected        Expected SQL clause.
	 */
	public function test_removes_invalid_characters( $post_mime_types, $expected ) {
		$this->assertSame( $expected, wp_post_mime_type_where( $post_mime_types ) );
	}

	/**
	 * Data provider for mime types with invalid characters.
	 *
	 * @return array[]
	 */
	public static function data_mime_types_with_invalid_characters() {
		return array(
			'quote and spaces in subtype' => array( "image/jpeg' OR 1=1", " AND (post_mime_type = 'image/jpegOR11') " ),
			'quote in type'               => array( "ima'ge/jpeg", " AND (post_mime_type = 'image/jpeg') " ),
			'plus in type'                => array( 'im+age/png', " AND (post_mime_type = 'image/png') " ),
		);
	}

	/**
	 * Tests that no clause is returned when the mime types do not restrict the query.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unrestricted_mime_types
	 *
	 * @param string|string[] $post_mime_types Mime types to query.
	 */
	public function test_returns_empty_string_when_not_restricted( $post_mime_types ) {
		$this->assertSame( '', wp_post_mime_type_where( $post_mime_types ) );
	}

	/**
	 * Data provider for mime types that do not restrict the query.
	 *
	 * @return array[]
	 */
	public static function data_unrestricted_mime_types() {
		return array(
			'empty string'            => array( '' ),
			'percent sign'            => array( '%' ),
			'percent signs and slash' => array( '%/%' ),
			'wildcard after a type'   => array( array( 'image/jpeg', '%' ) ),
			'empty array'             => array( array() ),
		);
	}

	/**
	 * Tests that the table alias prefixes the column name.
	 *
	 * @ticket 65819
	 */
	public function test_prefixes_column_with_table_alias() {
		$this->assertSame(
			" AND (p.post_mime_type = 'image/jpeg' OR p.post_mime_type LIKE 'video/%') ",
			wp_post_mime_type_where( 'image/jpeg,video', 'p' )
		);
	}
}
