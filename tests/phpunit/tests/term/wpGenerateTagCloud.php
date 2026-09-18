<?php
/**
 * @group taxonomy
 *
 * @covers ::wp_generate_tag_cloud
 * @covers ::_wp_object_name_sort_cb
 * @covers ::_wp_object_name_sort_key
 */
class Tests_WP_Generate_Tag_Cloud extends WP_UnitTestCase {
	protected $terms = array();

	/**
	 * Testing when passed $tags array is empty
	 *
	 * @dataProvider data_empty_tags
	 *
	 * @param $expected Expected output from `wp_generate_tag_cloud()`.
	 * @param $args     Options for `wp_generate_tag_cloud()`.
	 */
	public function test_empty_tags_passed( $expected, $args ) {
		$empty_tags = array();
		$this->assertSame( $expected, wp_generate_tag_cloud( $empty_tags, $args ) );
	}

	/**
	 * Testing when no tags are found
	 *
	 * @dataProvider data_empty_tags
	 *
	 * @param $expected Expected output from `wp_generate_tag_cloud()`.
	 * @param $args     Options for `wp_generate_tag_cloud()`.
	 */
	public function test_empty_tags_list_returned( $expected, $args ) {
		$term_ids    = self::factory()->term->create_many( 4, array( 'taxonomy' => 'post_tag' ) );
		$this->terms = array();
		foreach ( $term_ids as $term_id ) {
			$this->terms[] = get_term( $term_id, 'post_tag' );
		}
		$tags = $this->retrieve_terms( array( 'number' => 4 ) );
		$this->assertSame( $expected, wp_generate_tag_cloud( $tags, $args ) );
	}

	/**
	 * Provider for test when tags are empty.
	 *
	 * @return array
	 */
	public function data_empty_tags() {
		return array(
			// When 'format' => 'array', we should be getting an empty array back.
			array(
				array(),
				array( 'format' => 'array' ),
			),
			// List format returns an empty string.
			array(
				'',
				array( 'format' => 'list' ),
			),
			// $args can be an array or ''. Either should return an empty string.
			array(
				'',
				array(),
			),
			array(
				'',
				'',
			),
		);
	}

	public function test_hide_empty_false() {
		$term_id = self::factory()->tag->create();
		$term    = get_term( $term_id, 'post_tag' );

		$tags = $this->retrieve_terms(
			array(
				'number'     => 1,
				'hide_empty' => false,
			)
		);

		$found = wp_generate_tag_cloud(
			$tags,
			array(
				'hide_empty' => false,
			)
		);

		$this->assertStringContainsString( '>' . $tags[0]->name . '<', $found );
	}

	public function test_hide_empty_false_format_array() {
		$term_id = self::factory()->tag->create();
		$term    = get_term( $term_id, 'post_tag' );

		$tags = $this->retrieve_terms(
			array(
				'number'     => 1,
				'hide_empty' => false,
				'format'     => 'array',
			)
		);

		$found = wp_generate_tag_cloud(
			$tags,
			array(
				'hide_empty' => false,
				'format'     => 'array',
			)
		);

		$this->assertIsArray( $found );
		$this->assertStringContainsString( '>' . $tags[0]->name . '<', $found[0] );
	}

	public function test_hide_empty_false_format_list() {
		$term_id = self::factory()->tag->create();
		$term    = get_term( $term_id, 'post_tag' );

		$tags = $this->retrieve_terms(
			array(
				'number'     => 1,
				'hide_empty' => false,
			)
		);

		$found = wp_generate_tag_cloud(
			$tags,
			array(
				'hide_empty' => false,
				'format'     => 'list',
			)
		);

		$this->assertMatchesRegularExpression( "|^<ul class='wp-tag-cloud' role='list'>|", $found );
		$this->assertMatchesRegularExpression( "|</ul>\n|", $found );
		$this->assertStringContainsString( '>' . $tags[0]->name . '<', $found );
	}

	public function test_hide_empty_false_multi() {
		$term_ids = self::factory()->tag->create_many( 4 );
		$terms    = array();
		foreach ( $term_ids as $term_id ) {
			$terms[] = get_term( $term_id, 'post_tag' );
		}

		$tags = $this->retrieve_terms(
			array(
				'number'     => 4,
				'order'      => 'id',
				'hide_empty' => false,
			)
		);

		$found = wp_generate_tag_cloud(
			$tags,
			array(
				'hide_empty' => false,
			)
		);

		$this->assertNotEmpty( $tags );

		foreach ( $tags as $tag ) {
			$this->assertStringContainsString( '>' . $tag->name . '<', $found );
		}
	}

	public function test_hide_empty_false_multi_format_list() {
		$term_ids = self::factory()->tag->create_many( 4 );
		$terms    = array();
		foreach ( $term_ids as $term_id ) {
			$terms[] = get_term( $term_id, 'post_tag' );
		}

		$tags = $this->retrieve_terms(
			array(
				'number'     => 4,
				'orderby'    => 'id',
				'hide_empty' => false,
			)
		);

		$found = wp_generate_tag_cloud(
			$tags,
			array(
				'hide_empty' => false,
				'format'     => 'list',
			)
		);

		$this->assertMatchesRegularExpression( "|^<ul class='wp-tag-cloud' role='list'>|", $found );
		$this->assertMatchesRegularExpression( "|</ul>\n|", $found );

		$this->assertNotEmpty( $tags );

		foreach ( $tags as $tag ) {
			$this->assertStringContainsString( '>' . $tag->name . '<', $found );
		}
	}

	public function test_topic_count_text() {
		register_taxonomy( 'wptests_tax', 'post' );
		$term_ids    = self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_tax' ) );
		$this->terms = array();
		foreach ( $term_ids as $term_id ) {
			$this->terms[] = get_term( $term_id, 'post_tag' );
		}
		$posts = self::factory()->post->create_many( 2 );

		wp_set_post_terms( $posts[0], $term_ids, 'wptests_tax' );
		wp_set_post_terms( $posts[1], array( $term_ids[1] ), 'wptests_tax' );

		$term_objects = $this->retrieve_terms(
			array(
				'include' => $term_ids,
			),
			'wptests_tax'
		);

		$actual = wp_generate_tag_cloud(
			$term_objects,
			array(
				'format'           => 'array',
				'topic_count_text' => array(
					'singular' => 'Term has %s post',
					'plural'   => 'Term has %s posts',
					'domain'   => 'foo',
					'context'  => 'bar',
				),
			)
		);

		$this->assertStringContainsString( 'aria-label="' . $term_objects[0]->name . ' (Term has 1 post)"', $actual[0] );
		$this->assertStringContainsString( 'aria-label="' . $term_objects[1]->name . ' (Term has 2 posts)"', $actual[1] );
	}

	public function test_topic_count_text_callback() {
		register_taxonomy( 'wptests_tax', 'post' );
		$term_ids    = self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_tax' ) );
		$this->terms = array();
		foreach ( $term_ids as $term_id ) {
			$this->terms[] = get_term( $term_id, 'post_tag' );
		}
		$posts = self::factory()->post->create_many( 2 );

		wp_set_post_terms( $posts[0], $term_ids, 'wptests_tax' );
		wp_set_post_terms( $posts[1], array( $term_ids[1] ), 'wptests_tax' );

		$term_objects = $this->retrieve_terms(
			array(
				'include' => $term_ids,
			),
			'wptests_tax'
		);

		$actual = wp_generate_tag_cloud(
			$term_objects,
			array(
				'format'                    => 'array',
				'topic_count_text_callback' => array( $this, 'topic_count_text_callback' ),
			)
		);

		$this->assertStringContainsString( 'aria-label="' . $term_objects[0]->name . ' (1 foo)"', $actual[0] );
		$this->assertStringContainsString( 'aria-label="' . $term_objects[1]->name . ' (2 foo)"', $actual[1] );
	}

	/**
	 * @ticket 5172
	 */
	public function test_should_include_tag_link_position_class() {
		if ( PHP_VERSION_ID >= 80100 ) {
			/*
			 * For the time being, ignoring PHP 8.1 "null to non-nullable" deprecations coming in
			 * via hooked in filter functions until a more structural solution to the
			 * "missing input validation" conundrum has been architected and implemented.
			 */
			$this->expectDeprecation();
			$this->expectDeprecationMessageMatches( '`Passing null to parameter \#[0-9]+ \(\$[^\)]+\) of type [^ ]+ is deprecated`' );
		}

		register_taxonomy( 'wptests_tax', 'post' );
		$term_ids = self::factory()->term->create_many( 3, array( 'taxonomy' => 'wptests_tax' ) );

		$p = self::factory()->post->create();
		wp_set_post_terms( $p, $term_ids, 'wptests_tax' );

		$term_objects = get_terms(
			'wptests_tax',
			array(
				'include' => $term_ids,
			)
		);

		$cloud = wp_generate_tag_cloud( $term_objects );
		preg_match_all( '|tag\-link\-position-([0-9]+)|', $cloud, $matches );

		$this->assertSame( array( 1, 2, 3 ), array_map( 'intval', $matches[1] ) );
	}

	/**
	 * Returns tag objects for the given names, shaped the way `wp_tag_cloud()`
	 * passes them to `wp_generate_tag_cloud()`.
	 *
	 * @param string[] $names Term names.
	 * @return object[] Tag objects.
	 */
	protected function get_tags_for_names( array $names ) {
		$tags = array();

		foreach ( array_values( $names ) as $index => $name ) {
			$tag        = new stdClass();
			$tag->id    = $index + 1;
			$tag->name  = $name;
			$tag->slug  = 'tag-' . ( $index + 1 );
			$tag->count = 1;
			$tag->link  = 'https://example.org/?tag=' . $tag->slug;
			$tags[]     = $tag;
		}

		return $tags;
	}

	/**
	 * Generates a tag cloud and returns the term names in their sorted order.
	 *
	 * @param string[] $names Term names.
	 * @return string[] Term names in the order produced by `wp_generate_tag_cloud()`.
	 */
	protected function get_sorted_names( array $names ) {
		$cloud = wp_generate_tag_cloud( $this->get_tags_for_names( $names ), array( 'format' => 'array' ) );

		return array_map( 'wp_strip_all_tags', $cloud );
	}

	/**
	 * Greek letters that only differ by case.
	 *
	 * Each inner array holds all case variants of a single letter, written as
	 * codepoints:
	 *
	 *     U+0391..U+0399  Greek capital letters
	 *     U+03B1..U+03B9  Greek small letters
	 *
	 * All variants of a letter belong to one group and must stay together.
	 *
	 * @return string[][]
	 */
	protected function get_greek_case_groups() {
		return array(
			array( "\u{0391}", "\u{03B1}" ), // Alpha.
			array( "\u{0392}", "\u{03B2}" ), // Beta.
			array( "\u{0393}", "\u{03B3}" ), // Gamma.
			array( "\u{0394}", "\u{03B4}" ), // Delta.
			array( "\u{0395}", "\u{03B5}" ), // Epsilon.
			array( "\u{0396}", "\u{03B6}" ), // Zeta.
			array( "\u{0397}", "\u{03B7}" ), // Eta.
			array( "\u{0398}", "\u{03B8}" ), // Theta.
			array( "\u{0399}", "\u{03B9}" ), // Iota.
		);
	}

	/**
	 * Greek letters that differ by case and by accents.
	 *
	 * Each inner array holds all variants of a single letter, written as
	 * codepoints:
	 *
	 *     U+0386, U+0388, U+0389, U+038A  capital letter with tonos
	 *     U+0391..U+0399                 capital letter
	 *     U+03B1..U+03B9                 small letter
	 *     U+03AC..U+03AF                 small letter with tonos
	 *     U+1F00..U+1F77                 small letter with breathing or grave
	 *     U+1FB0, U+1FB1                 small alpha with vrachy or macron
	 *
	 * Consonants have no polytonic variants, so their groups are smaller than
	 * the vowel groups. All variants of a letter belong to one group and must
	 * stay together once combining marks are stripped.
	 *
	 * @return string[][]
	 */
	protected function get_greek_accent_groups() {
		return array(
			// Alpha.
			array( "\u{0386}", "\u{0391}", "\u{03B1}", "\u{03AC}", "\u{1F00}", "\u{1F04}", "\u{1F70}", "\u{1F71}", "\u{1FB0}", "\u{1FB1}" ),
			// Beta.
			array( "\u{0392}", "\u{03B2}" ),
			// Gamma.
			array( "\u{0393}", "\u{03B3}" ),
			// Delta.
			array( "\u{0394}", "\u{03B4}" ),
			// Epsilon.
			array( "\u{0388}", "\u{0395}", "\u{03B5}", "\u{03AD}", "\u{1F10}", "\u{1F11}", "\u{1F72}", "\u{1F73}" ),
			// Zeta.
			array( "\u{0396}", "\u{03B6}" ),
			// Eta.
			array( "\u{0389}", "\u{0397}", "\u{03B7}", "\u{03AE}", "\u{1F20}", "\u{1F21}", "\u{1F74}", "\u{1F75}" ),
			// Theta.
			array( "\u{0398}", "\u{03B8}" ),
			// Iota.
			array( "\u{038A}", "\u{0399}", "\u{03B9}", "\u{03AF}", "\u{1F30}", "\u{1F31}", "\u{1F76}", "\u{1F77}" ),
		);
	}

	/**
	 * Asserts that every group of equivalent names stays contiguous and that
	 * the groups themselves keep the order in which they were supplied.
	 *
	 * @param string[][] $groups       Groups of equivalent term names.
	 * @param string[]   $sorted_names Term names in sorted order.
	 */
	protected function assertGroupsAreNotSplit( array $groups, array $sorted_names ) {
		$group_of = array();
		foreach ( $groups as $group_index => $names ) {
			foreach ( $names as $name ) {
				$group_of[ $name ] = $group_index;
			}
		}

		$found_groups = array();
		foreach ( $sorted_names as $name ) {
			$this->assertArrayHasKey( $name, $group_of, "Unexpected term in the sorted output: {$name}" );
			$found_groups[] = $group_of[ $name ];
		}

		$this->assertSame(
			array_keys( $groups ),
			array_values( array_unique( $found_groups ) ),
			'Groups of equivalent names were split or reordered.'
		);

		$sorted_groups = $found_groups;
		sort( $sorted_groups );
		$this->assertSame( $sorted_groups, $found_groups, 'Groups of equivalent names were interleaved.' );
	}

	/**
	 * Flattens a list of groups into a single list of names.
	 *
	 * @param string[][] $groups Groups of equivalent term names.
	 * @return string[]
	 */
	protected function flatten_groups( array $groups ) {
		$names = array();

		foreach ( $groups as $group ) {
			$names = array_merge( $names, $group );
		}

		return $names;
	}

	/**
	 * The tag cloud is sorted in PHP after SQL has already sorted the terms.
	 * The comparison must not reorder plain ASCII names.
	 *
	 * @ticket 35144
	 */
	public function test_ascii_names_keep_their_natural_order() {
		$names = array( 'banana', 'apple', 'Cherry', 'item10', 'item9', 'Item2', 'zebra', 'Zulu', 'alpha' );

		$this->assertSame(
			array( 'alpha', 'apple', 'banana', 'Cherry', 'Item2', 'item9', 'item10', 'zebra', 'Zulu' ),
			$this->get_sorted_names( $names )
		);
	}

	/**
	 * Latin terms must keep sorting before Greek ones.
	 *
	 * @ticket 35144
	 */
	public function test_latin_names_sort_before_greek_names() {
		$names = array( 'zebra', "\u{0386}\u{03BB}\u{03C6}\u{03B1}", 'Apple', "\u{03C9}\u{03BC}\u{03AD}\u{03B3}\u{03B1}", 'beta' );

		$this->assertSame(
			array( 'Apple', 'beta', 'zebra', "\u{0386}\u{03BB}\u{03C6}\u{03B1}", "\u{03C9}\u{03BC}\u{03AD}\u{03B3}\u{03B1}" ),
			$this->get_sorted_names( $names )
		);
	}

	/**
	 * Uppercase and lowercase Greek letters must not be split into separate
	 * groups by the byte-oriented comparison.
	 *
	 * @ticket 35144
	 */
	public function test_greek_case_variants_are_not_split_into_groups() {
		$groups = $this->get_greek_case_groups();

		$this->assertGroupsAreNotSplit( $groups, $this->get_sorted_names( $this->flatten_groups( $groups ) ) );
	}

	/**
	 * Accents must not split Greek letters into separate groups either.
	 *
	 * Folding accents requires the intl extension, which WordPress does not
	 * require, so this test is skipped when it is unavailable.
	 *
	 * @ticket 35144
	 */
	public function test_greek_accent_variants_are_not_split_into_groups() {
		if ( ! class_exists( 'Normalizer' ) ) {
			$this->markTestSkipped( 'This test requires the intl extension.' );
		}

		$groups = $this->get_greek_accent_groups();

		$this->assertGroupsAreNotSplit( $groups, $this->get_sorted_names( $this->flatten_groups( $groups ) ) );
	}

	/**
	 * The variants of a single Greek letter form one group, ordered
	 * deterministically by the original name whenever their sort keys match.
	 *
	 * @ticket 35144
	 */
	public function test_greek_alpha_variants_form_a_single_deterministic_group() {
		if ( ! class_exists( 'Normalizer' ) ) {
			$this->markTestSkipped( 'This test requires the intl extension.' );
		}

		$names = array( "\u{1FB1}", "\u{03AC}", "\u{0386}", "\u{1FB0}", "\u{1F70}", "\u{1F04}", "\u{1F00}", "\u{03B1}" );

		$this->assertSame(
			array( "\u{0386}", "\u{03AC}", "\u{03B1}", "\u{1F00}", "\u{1F04}", "\u{1F70}", "\u{1FB0}", "\u{1FB1}" ),
			$this->get_sorted_names( $names )
		);
	}

	/**
	 * Helper method retrieve the created terms.
	 *
	 * @param array $get_terms_args Options passed to get_terms()
	 * @return array
	 */
	protected function retrieve_terms( $get_terms_args, $taxonomy = 'post_tag' ) {
		$terms = get_terms( array( $taxonomy ), $get_terms_args );

		$tags = array();
		foreach ( $terms as $term ) {
			// Add the link.
			$term->link = get_term_link( $term );
			$tags[]     = $term;

		}

		return $tags;
	}

	public function topic_count_text_callback( $real_count, $tag, $args ) {
		return sprintf( '%s foo', $real_count );
	}
}
