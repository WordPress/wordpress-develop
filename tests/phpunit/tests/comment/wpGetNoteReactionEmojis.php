<?php

/**
 * Tests for the filterable list of note reaction emoji.
 *
 * @group comment
 * @group notes
 *
 * @covers ::wp_get_note_reaction_emojis
 * @covers ::wp_get_note_reaction_keys
 * @covers ::wp_normalize_note_reaction_key
 */
class Tests_Comment_WpGetNoteReactionEmojis extends WP_UnitTestCase {

	/**
	 * @ticket 66276
	 */
	public function test_defaults_to_the_five_quick_reactions() {
		$this->assertSame(
			array( '2764', '1f389', '1f604', '1f440', '1f680' ),
			wp_get_note_reaction_keys()
		);
	}

	/**
	 * @ticket 66276
	 */
	public function test_filter_can_add_and_remove_emoji() {
		add_filter(
			'wp_note_reaction_emojis',
			static function ( $emojis ) {
				$emojis[] = array(
					'hexKey' => '1f984',
					'label'  => 'unicorn',
				);
				return array_slice( $emojis, 1 );
			}
		);

		$this->assertSame(
			array( '1f389', '1f604', '1f440', '1f680', '1f984' ),
			wp_get_note_reaction_keys()
		);
	}

	/**
	 * @ticket 66276
	 */
	public function test_filter_can_remove_every_emoji() {
		add_filter( 'wp_note_reaction_emojis', '__return_empty_array' );

		$this->assertSame( array(), wp_get_note_reaction_emojis() );
	}

	/**
	 * @ticket 66276
	 */
	public function test_falls_back_to_the_defaults_when_the_filter_returns_no_list() {
		add_filter( 'wp_note_reaction_emojis', '__return_null' );

		$this->assertCount( 5, wp_get_note_reaction_emojis() );
	}

	/**
	 * @ticket 66276
	 */
	public function test_drops_malformed_and_duplicate_entries() {
		add_filter(
			'wp_note_reaction_emojis',
			static function () {
				return array(
					// Normalized: lowercase, padded, U+FE0F dropped.
					array(
						'hexKey' => '2764-FE0F',
						'label'  => 'heart',
					),
					// The same key again.
					array(
						'hexKey' => '2764',
						'label'  => 'red heart',
					),
					array(
						'hexKey' => '1F468-200D-1F4BB',
						'label'  => '<b>technologist</b>',
					),
					array(
						'hexKey' => 'not-hex',
						'label'  => 'broken',
					),
					array(
						'hexKey' => 'd83d',
						'label'  => 'surrogate',
					),
					array(
						'hexKey' => '110000',
						'label'  => 'past U+10FFFF',
					),
					array(
						'hexKey' => '1f984',
						'label'  => '',
					),
					array( 'hexKey' => '1f680' ),
					'1f389',
				);
			}
		);

		$this->assertSame(
			array(
				array(
					'hexKey' => '2764',
					'label'  => 'heart',
				),
				array(
					'hexKey' => '1f468-200d-1f4bb',
					'label'  => 'technologist',
				),
			),
			wp_get_note_reaction_emojis()
		);
	}

	/**
	 * @ticket 66276
	 *
	 * @dataProvider data_normalize_note_reaction_key
	 *
	 * @param mixed  $hex_key  The key to normalize.
	 * @param string $expected The normalized key.
	 */
	public function test_normalize_note_reaction_key( $hex_key, $expected ) {
		$this->assertSame( $expected, wp_normalize_note_reaction_key( $hex_key ) );
	}

	public function data_normalize_note_reaction_key() {
		return array(
			'already normalized' => array( '1f984', '1f984' ),
			'uppercase'          => array( '1F984', '1f984' ),
			'short code point'   => array( '23', '0023' ),
			'variation selector' => array( '2764-fe0f', '2764' ),
			'ZWJ sequence'       => array( '1f468-200d-1f4bb', '1f468-200d-1f4bb' ),
			'surrogate'          => array( 'd83d', '' ),
			'past U+10FFFF'      => array( '110000', '' ),
			'not hex'            => array( 'heart', '' ),
			'trailing separator' => array( '2764-', '' ),
			'not a string'       => array( 0x2764, '' ),
			'empty'              => array( '', '' ),
		);
	}
}
