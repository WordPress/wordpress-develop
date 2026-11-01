<?php
/**
 * @group taxonomy
 */
class Tests_WP_Generate_Tag_Cloud extends WP_UnitTestCase {
	protected $terms = array();

	public function tear_down() {
		unregister_taxonomy( 'wptests_tax' );

		parent::tear_down();
	}

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
	public static function data_empty_tags() {
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
	 * Tests that the aria-label is only output when font sizes differ or counts are shown.
	 *
	 * @ticket 65817
	 *
	 * @covers ::wp_generate_tag_cloud
	 *
	 * @dataProvider data_aria_label_font_sizes
	 *
	 * @param int|float $smallest          Smallest font size.
	 * @param int|float $largest           Largest font size.
	 * @param int       $show_count        Whether to show tag counts.
	 * @param bool      $expect_aria_label Whether an aria-label is expected.
	 */
	public function test_aria_label_font_sizes( $smallest, $largest, int $show_count, bool $expect_aria_label ) {
		register_taxonomy( 'wptests_tax', 'post' );
		$term_ids = self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_tax' ) );
		$posts    = self::factory()->post->create_many( 2 );

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
				'format'     => 'array',
				'smallest'   => $smallest,
				'largest'    => $largest,
				'show_count' => $show_count,
			)
		);

		$this->assertCount( 2, $actual );

		$expected_aria_labels = array(
			$term_objects[0]->name . ' (1 item)',
			$term_objects[1]->name . ' (2 items)',
		);
		foreach ( $actual as $i => $link ) {
			$processor = new WP_HTML_Tag_Processor( $link );
			$this->assertTrue( $processor->next_tag( 'A' ), 'Expected the tag cloud item to be a link.' );
			$this->assertSame(
				$expect_aria_label ? $expected_aria_labels[ $i ] : null,
				$processor->get_attribute( 'aria-label' ),
				'Expected the aria-label attribute to be ' . ( $expect_aria_label ? 'present' : 'absent' ) . '.'
			);
		}
	}

	/**
	 * Data provider for {@see self::test_aria_label_font_sizes()}.
	 *
	 * @return array<non-falsy-string, array{ 0: int|float, 1: int|float, 2: int, 3: bool }>
	 */
	public static function data_aria_label_font_sizes(): array {
		return array(
			'equal int sizes'              => array( 12, 12, 0, false ),
			'equal float sizes'            => array( 1.2, 1.2, 0, false ),
			'equal integral float sizes'   => array( 12.0, 12.0, 0, false ),
			'different int sizes'          => array( 8, 22, 0, true ),
			'different float sizes'        => array( 0.8, 1.5, 0, true ),
			'equal float sizes with count' => array( 1.2, 1.2, 1, true ),
			'smallest larger than largest' => array( 22.5, 8.5, 0, true ),
		);
	}

	/**
	 * Helper method retrieve the created terms.
	 *
	 * @param array $get_terms_args Options passed to get_terms()
	 * @return WP_Term[]
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
