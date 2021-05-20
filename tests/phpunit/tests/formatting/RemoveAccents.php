<?php

/**
 * @group formatting
 */
class Tests_Formatting_RemoveAccents extends WP_UnitTestCase {
	public function test_remove_accents_simple() {
		$this->assertSame( 'abcdefghijkl', remove_accents( 'abcdefghijkl' ) );
	}

	/**
	 * @ticket 9591
	 */
	public function test_remove_accents_latin1_supplement() {
		$input  = 'ªºÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ';
		$output = 'aoAAAAAAAECEEEEIIIIDNOOOOOOUUUUYTHsaaaaaaaeceeeeiiiidnoooooouuuuythy';

		$this->assertSame( $output, remove_accents( $input ), 'remove_accents replaces Latin-1 Supplement' );
	}

	public function test_remove_accents_latin_extended_a() {
		$input  = 'ĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĸĹĺĻļĽľĿŀŁłŃńŅņŇňŉŊŋŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſ';
		$output = 'AaAaAaCcCcCcCcDdDdEeEeEeEeEeGgGgGgGgHhHhIiIiIiIiIiIJijJjKkkLlLlLlLlLlNnNnNnnNnOoOoOoOEoeRrRrRrSsSsSsSsTtTtTtUuUuUuUuUuUuWwYyYZzZzZzs';

		$this->assertSame( $output, remove_accents( $input ), 'remove_accents replaces Latin Extended A' );
	}

	public function test_remove_accents_latin_extended_b() {
		$this->assertSame( 'SsTt', remove_accents( 'ȘșȚț' ), 'remove_accents replaces Latin Extended B' );
	}

	public function test_remove_accents_euro_pound_signs() {
		$this->assertSame( 'E', remove_accents( '€' ), 'remove_accents replaces euro sign' );
		$this->assertSame( '', remove_accents( '£' ), 'remove_accents replaces pound sign' );
	}

	public function test_remove_accents_iso8859() {
		// File is Latin1-encoded.
		$file   = DIR_TESTDATA . '/formatting/remove_accents.01.input.txt';
		$input  = file_get_contents( $file );
		$input  = trim( $input );
		$output = 'EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyyOEoeAEDHTHssaedhth';

		$this->assertSame( $output, remove_accents( $input ), 'remove_accents from ISO-8859-1 text' );
	}

	/**
	 * @ticket 17738
	 */
	public function test_remove_accents_vowels_diacritic() {
		// Vowels with diacritic.
		// Unmarked.
		$this->assertSame( 'OoUu', remove_accents( 'ƠơƯư' ) );
		// Grave accent.
		$this->assertSame( 'AaAaEeOoOoUuYy', remove_accents( 'ẦầẰằỀềỒồỜờỪừỲỳ' ) );
		// Hook.
		$this->assertSame( 'AaAaAaEeEeIiOoOoOoUuUuYy', remove_accents( 'ẢảẨẩẲẳẺẻỂểỈỉỎỏỔổỞởỦủỬửỶỷ' ) );
		// Tilde.
		$this->assertSame( 'AaAaEeEeOoOoUuYy', remove_accents( 'ẪẫẴẵẼẽỄễỖỗỠỡỮữỸỹ' ) );
		// Acute accent.
		$this->assertSame( 'AaAaEeOoOoUu', remove_accents( 'ẤấẮắẾếỐốỚớỨứ' ) );
		// Dot below.
		$this->assertSame( 'AaAaAaEeEeIiOoOoOoUuUuYy', remove_accents( 'ẠạẬậẶặẸẹỆệỊịỌọỘộỢợỤụỰựỴỵ' ) );
	}

	/**
	 * @ticket 20772
	 */
	public function test_remove_accents_hanyu_pinyin() {
		// Vowels with diacritic (Chinese, Hanyu Pinyin).
		// Macron.
		$this->assertSame( 'aeiouuAEIOUU', remove_accents( 'āēīōūǖĀĒĪŌŪǕ' ) );
		// Acute accent.
		$this->assertSame( 'aeiouuAEIOUU', remove_accents( 'áéíóúǘÁÉÍÓÚǗ' ) );
		// Caron.
		$this->assertSame( 'aeiouuAEIOUU', remove_accents( 'ǎěǐǒǔǚǍĚǏǑǓǙ' ) );
		// Grave accent.
		$this->assertSame( 'aeiouuAEIOUU', remove_accents( 'àèìòùǜÀÈÌÒÙǛ' ) );
		// Unmarked.
		$this->assertSame( 'aaeiouuAEIOUU', remove_accents( 'aɑeiouüAEIOUÜ' ) );
	}

	function _remove_accents_germanic_umlauts_cb() {
		return 'de_DE';
	}

	/**
	 * @ticket 3782
	 */
	public function test_remove_accents_germanic_umlauts() {
		add_filter( 'locale', array( $this, '_remove_accents_germanic_umlauts_cb' ) );

		$this->assertSame( 'AeOeUeaeoeuess', remove_accents( 'ÄÖÜäöüß' ) );

		remove_filter( 'locale', array( $this, '_remove_accents_germanic_umlauts_cb' ) );
	}

	public function _set_locale_to_danish() {
		return 'da_DK';
	}

	/**
	 * @ticket 23907
	 */
	public function test_remove_danish_accents() {
		add_filter( 'locale', array( $this, '_set_locale_to_danish' ) );

		$this->assertSame( 'AeOeAaaeoeaa', remove_accents( 'ÆØÅæøå' ) );

		remove_filter( 'locale', array( $this, '_set_locale_to_danish' ) );
	}

	public function _set_locale_to_catalan() {
		return 'ca';
	}

	/**
	 * @ticket 37086
	 */
	public function test_remove_catalan_middot() {
		add_filter( 'locale', array( $this, '_set_locale_to_catalan' ) );

		$this->assertSame( 'allallalla', remove_accents( 'al·lallaŀla' ) );

		remove_filter( 'locale', array( $this, '_set_locale_to_catalan' ) );

		$this->assertSame( 'al·lallalla', remove_accents( 'al·lallaŀla' ) );
	}

	public function _set_locale_to_serbian() {
		return 'sr_RS';
	}

	/**
	 * @ticket 38078
	 */
	public function test_transcribe_serbian_crossed_d() {
		add_filter( 'locale', array( $this, '_set_locale_to_serbian' ) );

		$this->assertSame( 'DJdj', remove_accents( 'Đđ' ) );

		remove_filter( 'locale', array( $this, '_set_locale_to_serbian' ) );

		$this->assertSame( 'Dd', remove_accents( 'Đđ' ) );
	}
}
