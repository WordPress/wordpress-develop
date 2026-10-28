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
}
