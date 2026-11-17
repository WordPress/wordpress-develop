<?php

/**
 * @group l10n
 * @group i18n
 *
 * @covers ::wp_dropdown_languages
 */
class Tests_L10n_wpDropdownLanguages extends WP_UnitTestCase {

	/**
	 * @ticket 35294
	 */
	public function test_wp_dropdown_languages() {
		$args   = array(
			'id'           => 'foo',
			'name'         => 'bar',
			'languages'    => array( 'de_DE' ),
			'translations' => $this->wp_dropdown_languages_filter(),
			'selected'     => 'de_DE',
			'echo'         => false,
		);
		$actual = wp_dropdown_languages( $args );

		$this->assertStringContainsString( 'id="foo"', $actual );
		$this->assertStringContainsString( 'name="bar"', $actual );
		$this->assertStringContainsString( '<option value="" lang="en" data-installed="1">English (United States)</option>', $actual );
		$this->assertStringContainsString( '<option value="de_DE" lang="de" selected=\'selected\' data-installed="1">Deutsch</option>', $actual );
		$this->assertStringContainsString( '<option value="it_IT" lang="it">Italiano</option>', $actual );
		$this->assertStringContainsString( '<option value="ja_JP" lang="ja">日本語</option>', $actual );
	}

	/**
	 * @ticket 38632
	 */
	public function test_wp_dropdown_languages_site_default() {
		$args   = array(
			'id'                       => 'foo',
			'name'                     => 'bar',
			'languages'                => array( 'de_DE' ),
			'translations'             => $this->wp_dropdown_languages_filter(),
			'selected'                 => 'de_DE',
			'echo'                     => false,
			'show_option_site_default' => true,
		);
		$actual = wp_dropdown_languages( $args );

		$this->assertStringContainsString( 'id="foo"', $actual );
		$this->assertStringContainsString( 'name="bar"', $actual );
		$this->assertStringContainsString( '<option value="site-default" data-installed="1">Site Default</option>', $actual );
		$this->assertStringContainsString( '<option value="" lang="en" data-installed="1">English (United States)</option>', $actual );
		$this->assertStringContainsString( '<option value="de_DE" lang="de" selected=\'selected\' data-installed="1">Deutsch</option>', $actual );
		$this->assertStringContainsString( '<option value="it_IT" lang="it">Italiano</option>', $actual );
		$this->assertStringContainsString( '<option value="ja_JP" lang="ja">日本語</option>', $actual );
	}

	/**
	 * @ticket 44494
	 */
	public function test_wp_dropdown_languages_exclude_en_us() {
		$args   = array(
			'id'                => 'foo',
			'name'              => 'bar',
			'languages'         => array( 'de_DE' ),
			'translations'      => $this->wp_dropdown_languages_filter(),
			'selected'          => 'de_DE',
			'echo'              => false,
			'show_option_en_us' => false,
		);
		$actual = wp_dropdown_languages( $args );

		$this->assertStringNotContainsString( '<option value="" lang="en" data-installed="1">English (United States)</option>', $actual );
	}

	/**
	 * @ticket 38632
	 */
	public function test_wp_dropdown_languages_en_US_selected() {
		$args   = array(
			'id'           => 'foo',
			'name'         => 'bar',
			'languages'    => array( 'de_DE' ),
			'translations' => $this->wp_dropdown_languages_filter(),
			'selected'     => 'en_US',
			'echo'         => false,
		);
		$actual = wp_dropdown_languages( $args );

		$this->assertStringContainsString( 'id="foo"', $actual );
		$this->assertStringContainsString( 'name="bar"', $actual );
		$this->assertStringContainsString( '<option value="" lang="en" data-installed="1" selected=\'selected\'>English (United States)</option>', $actual );
		$this->assertStringContainsString( '<option value="de_DE" lang="de" data-installed="1">Deutsch</option>', $actual );
		$this->assertStringContainsString( '<option value="it_IT" lang="it">Italiano</option>', $actual );
		$this->assertStringContainsString( '<option value="ja_JP" lang="ja">日本語</option>', $actual );
	}

	/**
	 * Add site default language to ja_JP in dropdown
	 */
	public function test_wp_dropdown_languages_site_default_ja_JP() {
		$args   = array(
			'id'                       => 'foo',
			'name'                     => 'bar',
			'languages'                => array( 'ja_JP' ),
			'translations'             => $this->wp_dropdown_languages_filter(),
			'selected'                 => 'ja_JP',
			'echo'                     => false,
			'show_option_site_default' => true,
		);
		$actual = wp_dropdown_languages( $args );

		$this->assertStringContainsString( 'id="foo"', $actual );
		$this->assertStringContainsString( 'name="bar"', $actual );
		$this->assertStringContainsString( '<option value="site-default" data-installed="1">Site Default</option>', $actual );
		$this->assertStringContainsString( '<option value="" lang="en" data-installed="1">English (United States)</option>', $actual );
		$this->assertStringContainsString( '<option value="de_DE" lang="de">Deutsch</option>', $actual );
		$this->assertStringContainsString( '<option value="it_IT" lang="it">Italiano</option>', $actual );
		$this->assertStringContainsString( '<option value="ja_JP" lang="ja" selected=\'selected\' data-installed="1">日本語</option>', $actual );
	}

	/**
	 * Select dropdown language from de_DE to ja_JP
	 */
	public function test_wp_dropdown_languages_ja_JP_selected() {
		$args   = array(
			'id'           => 'foo',
			'name'         => 'bar',
			'languages'    => array( 'de_DE' ),
			'translations' => $this->wp_dropdown_languages_filter(),
			'selected'     => 'ja_JP',
			'echo'         => false,
		);
		$actual = wp_dropdown_languages( $args );

		$this->assertStringContainsString( 'id="foo"', $actual );
		$this->assertStringContainsString( 'name="bar"', $actual );
		$this->assertStringContainsString( '<option value="" lang="en" data-installed="1">English (United States)</option>', $actual );
		$this->assertStringContainsString( '<option value="de_DE" lang="de" data-installed="1">Deutsch</option>', $actual );
		$this->assertStringContainsString( '<option value="it_IT" lang="it">Italiano</option>', $actual );
		$this->assertStringContainsString( '<option value="ja_JP" lang="ja" selected=\'selected\'>日本語</option>', $actual );
	}

	/**
	 * We don't want to call the API when testing.
	 *
	 * @return array
	 */
	private function wp_dropdown_languages_filter() {
		return array(
			'de_DE' => array(
				'language'    => 'de_DE',
				'native_name' => 'Deutsch',
				'iso'         => array( 'de' ),
			),
			'it_IT' => array(
				'language'    => 'it_IT',
				'native_name' => 'Italiano',
				'iso'         => array( 'it', 'ita' ),
			),
			'ja_JP' => array(
				'language'    => 'ja_JP',
				'native_name' => '日本語',
				'iso'         => array( 'ja' ),
			),
		);
	}
}
