<?php

/**
 * Tests for _wp_kses_allow_pdf_objects().
 *
 * @group kses
 *
 * @covers ::_wp_kses_allow_pdf_objects
 */
class Tests_Kses_wpKsesAllowPdfObjects extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_rejects_urls_with_query_string() {
		$uploads = wp_upload_dir( null, false );

		$this->assertFalse( _wp_kses_allow_pdf_objects( $uploads['url'] . '/file.pdf?download=.pdf' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_rejects_urls_with_fragment() {
		$uploads = wp_upload_dir( null, false );

		$this->assertFalse( _wp_kses_allow_pdf_objects( $uploads['url'] . '/file.pdf#page=.pdf' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_rejects_non_pdf_extension() {
		$uploads = wp_upload_dir( null, false );

		$this->assertFalse( _wp_kses_allow_pdf_objects( $uploads['url'] . '/file.txt' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_rejects_pdf_from_other_host() {
		$this->assertFalse( _wp_kses_allow_pdf_objects( 'https://evil.example/file.pdf' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_allows_pdf_from_uploads_host() {
		$uploads = wp_upload_dir( null, false );
		$url     = trailingslashit( $uploads['url'] ) . 'document.pdf';

		$this->assertTrue( _wp_kses_allow_pdf_objects( $url ) );
	}
}
