<?php

/**
 * @group l10n
 * @group i18n
 * @ticket 60975
 *
 * @covers ::get_w3_wai_language_code
 */
class Tests_L10n_getW3WAILanguageCode extends WP_UnitTestCase {

	public function test_empty_locale_should_return_empty_string() {
		$this->assertSame( '', get_w3_wai_language_code( '' ) );
	}

	public function test_not_supported_locale_should_return_empty_string() {
		$this->assertSame( '', get_w3_wai_language_code( 'es_ES' ) );
		$this->assertSame( '', get_w3_wai_language_code( 'pt_BR' ) );
	}

	public function test_supported_locale_should_return_language_code() {
		$this->assertSame( 'de', get_w3_wai_language_code( 'de_DE' ) );
		$this->assertSame( 'fr', get_w3_wai_language_code( 'fr_FR' ) );
		$this->assertSame( 'id', get_w3_wai_language_code( 'id_ID' ) );
		$this->assertSame( 'ja', get_w3_wai_language_code( 'ja' ) );
		$this->assertSame( 'ko', get_w3_wai_language_code( 'ko_KR' ) );
	}
}
