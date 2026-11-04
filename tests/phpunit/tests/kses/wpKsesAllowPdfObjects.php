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
	public function test_rejects_pdf_from_other_host() {
		$this->assertFalse( _wp_kses_allow_pdf_objects( 'https://evil.example/file.pdf' ) );
	}
}
