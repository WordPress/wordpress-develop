<?php

/**
 * @group formatting
 * @group emoji
 *
 * @covers ::convert_smilies
 */
class Tests_Formatting_ConvertSmilies extends WP_UnitTestCase {

	/**
	 * Basic validation test to confirm that smilies are converted to image
	 * when use_smilies = 1 and not when use_smilies = 0.
	 *
	 * @dataProvider data_convert_standard_smilies
	 */
	public function test_convert_standard_smilies( $input, $converted ) {
		// Standard smilies, use_smilies: ON.
		update_option( 'use_smilies', 1 );

		smilies_init();

		$this->assertSame( $converted, convert_smilies( $input ) );

		// Standard smilies, use_smilies: OFF.
		update_option( 'use_smilies', 0 );

		$this->assertSame( $input, convert_smilies( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $input     Input content.
	 *         @type string $converted Converted output.
	 *     }
	 * }
	 */
	public function data_convert_standard_smilies() {
		$includes_path = includes_url( 'images/smilies/' );

		return array(
			array(
				'Lorem ipsum dolor sit amet mauris ;-) Praesent gravida sodales. :lol: Vivamus nec diam in faucibus eu, bibendum varius nec, imperdiet purus est, at augue at lacus malesuada elit dapibus a, :eek: mauris. Cras mauris viverra elit. Nam laoreet viverra. Pellentesque tortor. Nam libero ante, porta urna ut turpis. Nullam wisi magna, :mrgreen: tincidunt nec, sagittis non, fringilla enim. Nam consectetuer nec, ullamcorper pede eu dui odio consequat vel, vehicula tortor quis pede turpis cursus quis, egestas ipsum ultricies ut, eleifend velit. Mauris vestibulum iaculis. Sed in nunc. Vivamus elit porttitor egestas. Mauris purus :?:',
				"Lorem ipsum dolor sit amet mauris \xf0\x9f\x98\x89 Praesent gravida sodales. \xf0\x9f\x98\x86 Vivamus nec diam in faucibus eu, bibendum varius nec, imperdiet purus est, at augue at lacus malesuada elit dapibus a, \xf0\x9f\x98\xae mauris. Cras mauris viverra elit. Nam laoreet viverra. Pellentesque tortor. Nam libero ante, porta urna ut turpis. Nullam wisi magna, <img src=\"{$includes_path}mrgreen.png\" alt=\":mrgreen:\" class=\"wp-smiley\" style=\"height: 1em; max-height: 1em;\" /> tincidunt nec, sagittis non, fringilla enim. Nam consectetuer nec, ullamcorper pede eu dui odio consequat vel, vehicula tortor quis pede turpis cursus quis, egestas ipsum ultricies ut, eleifend velit. Mauris vestibulum iaculis. Sed in nunc. Vivamus elit porttitor egestas. Mauris purus \xe2\x9d\x93",
			),
			array(
				'<strong>Welcome to the jungle!</strong> We got fun n games! :) We got everything you want 8-) <em>Honey we know the names :)</em>',
				"<strong>Welcome to the jungle!</strong> We got fun n games! \xf0\x9f\x99\x82 We got everything you want \xf0\x9f\x98\x8e <em>Honey we know the names \xf0\x9f\x99\x82</em>",
			),
			array(
				"<strong;)>a little bit of this\na little bit:other: of that :D\n:D a little bit of good\nyeah with a little bit of bad8O",
				"<strong;)>a little bit of this\na little bit:other: of that \xf0\x9f\x98\x80\n\xf0\x9f\x98\x80 a little bit of good\nyeah with a little bit of bad8O",
			),
			array(
				'<strong style="here comes the sun :-D">and I say it\'s allright:D:D',
				'<strong style="here comes the sun :-D">and I say it\'s allright:D:D',
			),
			array(
				'<!-- Woo-hoo, I\'m a comment, baby! :x > -->',
				'<!-- Woo-hoo, I\'m a comment, baby! :x > -->',
			),
			array(
				':?:P:?::-x:mrgreen:::',
				':?:P:?::-x:mrgreen:::',
			),
		);
	}

	/**
	 * Tests that custom smilies are converted to images when use_smilies = 1.
	 *
	 * @dataProvider data_convert_custom_smilies
	 */
	public function test_convert_custom_smilies( $input, $converted ) {
		global $wpsmiliestrans;

		// Custom smilies, use_smilies: ON.
		update_option( 'use_smilies', 1 );

		if ( ! isset( $wpsmiliestrans ) ) {
			smilies_init();
		}

		$trans_orig = $wpsmiliestrans; // Save original translations array.

		$wpsmiliestrans = array(
			':PP'      => 'icon_tongue.gif',
			':arrow:'  => 'icon_arrow.gif',
			':monkey:' => 'icon_shock_the_monkey.gif',
			':nervou:' => 'icon_nervou.gif',
		);

		smilies_init();

		$this->assertSame( $converted, convert_smilies( $input ) );

		// Standard smilies, use_smilies: OFF.
		update_option( 'use_smilies', 0 );

		$this->assertSame( $input, convert_smilies( $input ) );

		$wpsmiliestrans = $trans_orig; // Reset original translations array.
	}

	/**
	 * Data provider.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $input     Input content.
	 *         @type string $converted Converted output.
	 *     }
	 * }
	 */
	public function data_convert_custom_smilies() {
		$includes_path = includes_url( 'images/smilies/' );

		return array(
			array(
				'Peter Brian Gabriel (born 13 February 1950) is a British singer, musician, and songwriter who rose to fame as the lead vocalist and flautist of the progressive rock group Genesis. :monkey:',
				'Peter Brian Gabriel (born 13 February 1950) is a British singer, musician, and songwriter who rose to fame as the lead vocalist and flautist of the progressive rock group Genesis. <img src="' . $includes_path . 'icon_shock_the_monkey.gif" alt=":monkey:" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
			),
			array(
				'Star Wars Jedi Knight :arrow: Jedi Academy is a first and third-person shooter action game set in the Star Wars universe. It was developed by Raven Software and published, distributed and marketed by LucasArts in North America and by Activision in the rest of the world. :nervou:',
				'Star Wars Jedi Knight <img src="' . $includes_path . 'icon_arrow.gif" alt=":arrow:" class="wp-smiley" style="height: 1em; max-height: 1em;" /> Jedi Academy is a first and third-person shooter action game set in the Star Wars universe. It was developed by Raven Software and published, distributed and marketed by LucasArts in North America and by Activision in the rest of the world. <img src="' . $includes_path . 'icon_nervou.gif" alt=":nervou:" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
			),
			array(
				':arrow: monkey: Lorem ipsum dolor sit amet enim. Etiam ullam :PP <br />corper. Suspendisse a pellentesque dui, non felis.<a> :arrow: :arrow</a>',
				'<img src="' . $includes_path . 'icon_arrow.gif" alt=":arrow:" class="wp-smiley" style="height: 1em; max-height: 1em;" /> monkey: Lorem ipsum dolor sit amet enim. Etiam ullam <img src="' . $includes_path . 'icon_tongue.gif" alt=":PP" class="wp-smiley" style="height: 1em; max-height: 1em;" /> <br />corper. Suspendisse a pellentesque dui, non felis.<a> <img src="' . $includes_path . 'icon_arrow.gif" alt=":arrow:" class="wp-smiley" style="height: 1em; max-height: 1em;" /> :arrow</a>',
			),
		);
	}

	/**
	 * Tests that conversion of smilies is ignored in pre-determined tags:
	 * pre, code, script, style.
	 *
	 * @ticket 16448
	 * @dataProvider data_ignore_smilies_in_tags
	 */
	public function test_ignore_smilies_in_tags( $element ) {
		$includes_path = includes_url( 'images/smilies/' );

		$input    = 'Do we ignore smilies ;-) in ' . $element . ' tags <' . $element . ' class="foo">My Content Here :?: </' . $element . '>';
		$expected = "Do we ignore smilies \xf0\x9f\x98\x89 in $element tags <$element class=\"foo\">My Content Here :?: </$element>";

		// Standard smilies, use_smilies: ON.
		update_option( 'use_smilies', 1 );
		smilies_init();

		$this->assertSame( $expected, convert_smilies( $input ) );

		// Standard smilies, use_smilies: OFF.
		update_option( 'use_smilies', 0 );
	}

	/**
	 * Data provider.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $element HTML tag name.
	 *     }
	 * }
	 */
	public function data_ignore_smilies_in_tags() {
		return array(
			array( 'pre' ),
			array( 'code' ),
			array( 'script' ),
			array( 'style' ),
			array( 'textarea' ),
		);
	}

	/**
	 * Tests that combinations of smilies separated by a single space
	 * are converted correctly.
	 *
	 * @ticket 20124
	 * @dataProvider data_smilies_combinations
	 */
	public function test_smilies_combinations( $input, $converted ) {
		// Custom smilies, use_smilies: ON.
		update_option( 'use_smilies', 1 );
		smilies_init();

		$this->assertSame( $converted, convert_smilies( $input ) );

		// Custom smilies, use_smilies: OFF.
		update_option( 'use_smilies', 0 );

		$this->assertSame( $input, convert_smilies( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $input     Input content.
	 *         @type string $converted Converted output.
	 *     }
	 * }
	 */
	public function data_smilies_combinations() {
		$includes_path = includes_url( 'images/smilies/' );

		return array(
			array(
				'8-O :-(',
				"\xf0\x9f\x98\xaf \xf0\x9f\x99\x81",
			),
			array(
				'8-) 8-O',
				"\xf0\x9f\x98\x8e \xf0\x9f\x98\xaf",
			),
			array(
				'8-) 8O',
				"\xf0\x9f\x98\x8e \xf0\x9f\x98\xaf",
			),
			array(
				'8-) :-(',
				"\xf0\x9f\x98\x8e \xf0\x9f\x99\x81",
			),
			array(
				'8-) :twisted:',
				"\xf0\x9f\x98\x8e \xf0\x9f\x98\x88",
			),
			array(
				'8O :twisted: :( :? :roll: :mrgreen:',
				"\xf0\x9f\x98\xaf \xf0\x9f\x98\x88 \xf0\x9f\x99\x81 \xf0\x9f\x98\x95 \xf0\x9f\x99\x84 <img src=\"{$includes_path}mrgreen.png\" alt=\":mrgreen:\" class=\"wp-smiley\" style=\"height: 1em; max-height: 1em;\" />",
			),
		);
	}

	/**
	 * Tests that smilies are converted for single smilie in
	 * the $wpsmiliestrans global array.
	 *
	 * @ticket 25303
	 * @dataProvider data_single_smilies_in_wpsmiliestrans
	 */
	public function test_single_smilies_in_wpsmiliestrans( $input, $converted ) {
		global $wpsmiliestrans;

		// Standard smilies, use_smilies: ON.
		update_option( 'use_smilies', 1 );

		if ( ! isset( $wpsmiliestrans ) ) {
			smilies_init();
		}

		$orig_trans = $wpsmiliestrans; // Save original tranlations array.

		$wpsmiliestrans = array(
			':)' => 'simple-smile.png',
		);

		smilies_init();

		$this->assertSame( $converted, convert_smilies( $input ) );

		// Standard smilies, use_smilies: OFF.
		update_option( 'use_smilies', 0 );

		$this->assertSame( $input, convert_smilies( $input ) );

		$wpsmiliestrans = $orig_trans; // Reset original translations array.
	}

	/**
	 * Data provider.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $input     Input content.
	 *         @type string $converted Converted output.
	 *     }
	 * }
	 */
	public function data_single_smilies_in_wpsmiliestrans() {
		$includes_path = includes_url( 'images/smilies/' );

		return array(
			array(
				'8-O :-(',
				'8-O :-(',
			),
			array(
				'8O :) additional text here :)',
				'8O <img src="' . $includes_path . 'simple-smile.png" alt=":)" class="wp-smiley" style="height: 1em; max-height: 1em;" /> additional text here <img src="' . $includes_path . 'simple-smile.png" alt=":)" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
			),
			array(
				':) :) :) :)',
				'<img src="' . $includes_path . 'simple-smile.png" alt=":)" class="wp-smiley" style="height: 1em; max-height: 1em;" /> <img src="' . $includes_path . 'simple-smile.png" alt=":)" class="wp-smiley" style="height: 1em; max-height: 1em;" /> <img src="' . $includes_path . 'simple-smile.png" alt=":)" class="wp-smiley" style="height: 1em; max-height: 1em;" /> <img src="' . $includes_path . 'simple-smile.png" alt=":)" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
			),
		);
	}

	/**
	 * Tests that $wp_smiliessearch pattern will match smilies
	 * between spaces, but never capture those spaces.
	 *
	 * Further tests that spaces aren't randomly deleted
	 * or added when replacing the text with an image.
	 *
	 * @ticket 22692
	 * @dataProvider data_spaces_around_smilies
	 */
	public function test_spaces_around_smilies( $input, $converted ) {
		// Standard smilies, use_smilies: ON.
		update_option( 'use_smilies', 1 );

		smilies_init();

		$this->assertSame( $converted, convert_smilies( $input ) );

		// Standard smilies, use_smilies: OFF.
		update_option( 'use_smilies', 0 );
	}

	/**
	 * Data provider.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $input     Input content.
	 *         @type string $converted Converted output.
	 *     }
	 * }
	 */
	public function data_spaces_around_smilies() {
		$nbsp = "\xC2\xA0";

		return array(
			array(
				'test :) smile',
				"test \xf0\x9f\x99\x82 smile",
			),
			array(
				'test &nbsp;:)&nbsp;smile',
				"test &nbsp;\xf0\x9f\x99\x82&nbsp;smile",
			),
			array(
				"test {$nbsp}:){$nbsp}smile",
				"test {$nbsp}\xf0\x9f\x99\x82{$nbsp}smile",
			),
		);
	}

	/**
	 * Test to ensure smilies can be removed with a filter
	 *
	 * @ticket 35905
	 */
	public function test_smilies_filter_removes_smilies() {
		add_filter( 'smilies', array( $this, '_filter_remove_smilies' ) );
		smilies_init();
		remove_filter( 'smilies', array( $this, '_filter_remove_smilies' ) );

		$txt = ':oops: I did it again';

		$this->assertSame( $txt, convert_smilies( $txt ) );
	}

	/**
	 * Test to ensure smilies can be added with a filter
	 *
	 * @ticket 35905
	 */
	public function test_smilies_filter_adds_smilies() {
		add_filter( 'smilies', array( $this, '_filter_add_smilies' ) );
		smilies_init();
		remove_filter( 'smilies', array( $this, '_filter_add_smilies' ) );

		$txt          = 'You played with my <3';
		$expected_txt = 'You played with my \xe2\x9d\xa4';

		$this->assertSame( $expected_txt, convert_smilies( $txt ) );
	}


	public function _filter_remove_smilies( $wpsmiliestrans ) {
		unset( $wpsmiliestrans[':oops:'] );
		return $wpsmiliestrans;
	}

	public function _filter_add_smilies( $wpsmiliestrans ) {
		$wpsmiliestrans['<3'] = '\xe2\x9d\xa4';
		return $wpsmiliestrans;
	}
}
