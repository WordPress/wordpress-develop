<?php
/**
 * Unit tests covering WP_Token_Map functionality.
 *
 * @package WordPress
 *
 * @since 6.6.0
 * @group html-api-token-map
 *
 * @coversDefaultClass WP_Token_Map
 */
class Tests_WpTokenMap extends WP_UnitTestCase {
	/**
	 * Number of unique HTML5 named character references, including
	 * variations of a given name that don't require the trailing semicolon.
	 *
	 * The set of names is fixed by the specification,
	 * and can be found at the following link.
	 *
	 * @link https://html.spec.whatwg.org/entities.json
	 */
	const KNOWN_COUNT_OF_ALL_HTML5_NAMED_CHARACTER_REFERENCES = 2231;

	/**
	 * Small test array matching names to Emoji.
	 *
	 * @var array.
	 */
	const ANIMAL_EMOJI = array(
		'cat'     => '🐈',
		'dog'     => '🐶',
		'fish'    => '🐟',
		'mammoth' => '🦣',
		'seal'    => '🦭',
	);

	/**
	 * Returns an associative array whose keys are tokens to replace and
	 * whose values are the replacement strings for those tokens.
	 *
	 * This function is here to help avoid bloating this specific test file.
	 * For example, the HTML5 dataset is very large and best served as a
	 * separate file.
	 *
	 * The HTML5 named character reference list is pulled directly from the
	 * WHATWG spec and stored in the tests directory so it doesn't need to
	 * be downloaded on every test run. By specification, it cannot change
	 * and will not be updated.
	 *
	 * @param string $dataset_name Which dataset to return.
	 * @return array The dataset as an associative array.
	 */
	private static function get_test_input_array( $dataset_name ) {
		static $html5_character_references = null;

		switch ( $dataset_name ) {
			case 'ANIMALS':
				return self::ANIMAL_EMOJI;

			case 'HTML5':
				if ( ! isset( $html5_character_references ) ) {
					$dataset = wp_json_file_decode(
						__DIR__ . '/../../data/html5-entities/entities.json',
						array( 'associative' => true )
					);

					$html5_character_references = array();
					foreach ( $dataset as $name => $value ) {
						$html5_character_references[ $name ] = $value['characters'];
					}
				}

				return $html5_character_references;
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_input_arrays() {
		$dataset_names = array(
			'ANIMALS',
			'HTML5',
		);

		foreach ( $dataset_names as $dataset_name ) {
			yield $dataset_name => array( self::get_test_input_array( $dataset_name ) );
		}
	}

	/**
	 * Ensure the basic creation of a Token Map from an associative array.
	 *
	 * @ticket 60698
	 *
	 * @dataProvider data_input_arrays
	 *
	 * @param array $dataset Dataset to test.
	 */
	public function test_creates_map_from_array_containing_proper_values( $dataset ) {
		$map = WP_Token_Map::from_array( $dataset );

		foreach ( $dataset as $token => $replacement ) {
			$this->assertTrue(
				$map->contains( $token ),
				"Map should have contained '{$token}' but didn't."
			);

			$skip_bytes = 0;
			$response   = $map->read_token( $token, 0, $skip_bytes );
			$this->assertSame(
				$replacement,
				$response,
				"Returned the wrong replacement value for '{$token}'."
			);

			$token_length = strlen( $token );
			$this->assertSame(
				$token_length,
				$skip_bytes,
				'Reported the wrong byte-length of the found token.'
			);
		}
	}

	/**
	 * Ensure that keys that are too long prevent the creation of a Token Map.
	 *
	 * If tokens or replacements are stored whose length is more than can be
	 * represented by a single byte, then the encoding scheme in the Token Map
	 * will fail and lead to corruption.
	 *
	 * @ticket 60698
	 *
	 * @expectedIncorrectUsage WP_Token_Map::from_array
	 */
	public function test_rejects_words_which_are_too_long() {
		$normal_length = str_pad( '', 255, '.' );
		$too_long_word = "{$normal_length}.";

		$this->assertInstanceOf(
			WP_Token_Map::class,
			WP_Token_Map::from_array( array( $normal_length => 'just fine' ) ),
			'Should have built Token Map containing long, but acceptable token length.'
		);

		$this->assertNull(
			WP_Token_Map::from_array( array( $too_long_word => 'not good' ) ),
			'Should have refused to build Token Map with key exceeding design limit.'
		);

		$this->assertInstanceOf(
			WP_Token_Map::class,
			WP_Token_Map::from_array( array( 'key' => $normal_length ) ),
			'Should have build Token Map containing long, but acceptable replacement.'
		);

		$this->assertNull(
			WP_Token_Map::from_array( array( 'key' => $too_long_word ) ),
			'Should have refused to build Token Map with replacement exceeding design limit.'
		);
	}

	/**
	 * Ensure isomorphic creation and export of a Token Map and associative arrays.
	 *
	 * @ticket 60698
	 *
	 * @dataProvider data_input_arrays
	 *
	 * @param array $dataset Dataset to test.
	 */
	public function test_round_trips_through_associative_array( $dataset ) {
		$map = WP_Token_Map::from_array( $dataset );
		$this->assertEqualsCanonicalizing(
			$dataset,
			$map->to_array(),
			'Should have produced an identical array on output as was given on input.'
		);
	}

	/**
	 * Ensure the basic creation of a Token Map from a precomputed source table.
	 *
	 * @ticket 60698
	 *
	 * @dataProvider data_input_arrays
	 *
	 * @param array $dataset Dataset to test.
	 */
	public function test_round_trips_through_precomputed_source_table( $dataset ) {
		$seed         = WP_Token_Map::from_array( $dataset );
		$source_table = $seed->precomputed_php_source_table();
		$map          = eval( "return {$source_table};" ); // phpcs:ignore.

		foreach ( $dataset as $token => $replacement ) {
			$this->assertTrue(
				$map->contains( $token ),
				"Map should have contained '{$token}' but didn't."
			);

			$skip_bytes = 0;
			$response   = $map->read_token( $token, 0, $skip_bytes );
			$this->assertSame(
				$replacement,
				$response,
				'Returned the wrong replacement value'
			);

			$token_length = strlen( $token );
			$this->assertSame(
				$token_length,
				$skip_bytes,
				'Reported the wrong byte-length of the found token.'
			);
		}
	}

	/**
	 * Ensures that when two or more keys share a prefix that the longest
	 * is matched first, to prevent tokens masking each other.
	 *
	 * @ticket 60698
	 */
	public function test_finds_longest_match_first() {
		$map = WP_Token_Map::from_array(
			array(
				'cat'                  => '1',
				'caterpillar'          => '2',
				'caterpillar machines' => '3',
			)
		);

		$skip_bytes = 0;
		$text       = 'cats like to meow';
		$this->assertSame(
			'1',
			$map->read_token( $text, 0, $skip_bytes ),
			"Should have matched 'cat' but matched '" . substr( $text, 0, $skip_bytes ) . "' instead."
		);

		$skip_bytes = 0;
		$text       = 'caterpillars turn into butterflies';
		$this->assertSame(
			'2',
			$map->read_token( $text, 0, $skip_bytes ),
			"Should have matched 'caterpillar' but matched '" . substr( $text, 0, $skip_bytes ) . "' instead."
		);

		$skip_bytes = 0;
		$text       = 'caterpillar machines are heavy duty equipment';
		$this->assertSame(
			'3',
			$map->read_token( $text, 0, $skip_bytes ),
			"Should have matched 'caterpillar machines' but matched '" . substr( $text, 0, $skip_bytes ) . "' instead."
		);
	}

	/**
	 * Ensures that tokens shorter than the group key length are found.
	 *
	 * @ticket 60698
	 *
	 * @dataProvider data_short_substring_matches_of_each_other
	 *
	 * @param WP_Token_Map $map Token map containing appropriate mapping for test.
	 * @param string       $search_document Document containing expected token at start of string.
	 * @param string       $expected_token  Which token should be found at start of search document.
	 */
	public function test_finds_short_matches_shorter_than_group_key_length( $map, $search_document, $expected_token ) {
		$skip_bytes = 0;
		$text       = 'antarctica is a continent';
		$this->assertSame(
			'article',
			$map->read_token( $text, 0, $skip_bytes ),
			"Should have matched 'a' but matched '" . substr( $text, 0, $skip_bytes ) . "' instead."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_short_substring_matches_of_each_other() {
		$map = WP_Token_Map::from_array(
			array(
				'a'       => 'article',
				'aa'      => 'defensive weapon',
				'ar'      => 'country code',
				'arizona' => 'state name',
			)
		);

		return array(
			'single character'    => array( $map, 'antarctica is a continent', 'a' ),
			'duplicate character' => array( $map, 'aaaaahhhh, he exclaimed', 'aa' ),
			'different character' => array( $map, 'argentina is a country', 'ar' ),
			'full word'           => array( $map, 'arizona was full of copper', 'arizona' ),
		);
	}

	/**
	 * Ensures that Token Map searches at appropriate starting offset.
	 *
	 * @ticket 60698
	 *
	 * @dataProvider data_html5_test_dataset
	 *
	 * @param string $token       Token to find.
	 * @param string $replacement Replacement string for token.
	 */
	public function test_reads_token_at_given_offset( $token, $replacement ) {
		$document = "& another {$token} & then some";
		$map      = self::get_html5_token_map();

		$skip_bytes = 0;
		$this->assertNull(
			$map->read_token( $document, 0, $skip_bytes ),
			"Shouldn't have found token at start of document."
		);

		$response = $map->read_token( $document, 10, $skip_bytes );

		$this->assertSame(
			strlen( $token ),
			$skip_bytes,
			"Found the wrong length for token '{$token}'."
		);

		$this->assertSame(
			$response,
			$replacement,
			'Found the wrong replacement value for the token.'
		);
	}

	/**
	 * Ensures that all given tokens exist inside a constructed Token Map.
	 *
	 * @ticket 60698
	 *
	 * @dataProvider data_html5_test_dataset
	 *
	 * @param string $token       Token to find.
	 * @param string $replacement Not used in this test.
	 */
	public function test_detects_all_tokens( $token, $replacement ) {
		$map = self::get_html5_token_map();

		$this->assertTrue(
			$map->contains( $token ),
			"Should have found '{$token}' inside the Token Map, but didn't."
		);

		$double_escaped_token = str_replace( '&', '&amp;', $token );
		$this->assertFalse(
			$map->contains( $double_escaped_token ),
			"Should not have found '{$double_escaped_token}' in Token Map, but did."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_html5_test_dataset() {
		$html5 = self::get_test_input_array( 'HTML5' );

		$this->assertSame(
			self::KNOWN_COUNT_OF_ALL_HTML5_NAMED_CHARACTER_REFERENCES,
			count( $html5 ),
			'Found the wrong number of HTML5 named character references: confirm the entities.json file."'
		);

		foreach ( $html5 as $token => $replacement ) {
			yield $token => array( $token, $replacement );
		}
	}

	/**
	 * Returns a static copy of the Token Map for HTML5.
	 * This is a test performance optimization.
	 *
	 * @return WP_Token_Map
	 */
	private static function get_html5_token_map() {
		static $html5_token_map = null;

		if ( ! isset( $html5_token_map ) ) {
			$html5_token_map = WP_Token_Map::from_array( self::get_test_input_array( 'HTML5' ) );
		}

		return $html5_token_map;
	}
}
