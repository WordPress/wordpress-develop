<?php

/**
 * Tests for the random content rewrite rules.
 *
 * @group rewrite
 * @ticket 64498
 *
 * @covers WP_Rewrite::random_rewrite_rules
 * @covers WP_Rewrite::rewrite_rules
 */
class Tests_Rewrite_RandomRewriteRules extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// Prevent random content redirects from exiting, only the rewrite rules are under test.
		add_filter( 'wp_redirect', '__return_false' );

		$this->set_permalink_structure( '/%postname%/' );
		// Register the taxonomy rewrite rules for the permalink structure.
		create_initial_taxonomies();
	}

	public function tear_down(): void {
		/*
		 * The parent class re-initializes the rewrite base from this option before the
		 * database transaction is rolled back, so it needs to be removed first.
		 */
		delete_option( 'random_base' );

		parent::tear_down();
	}

	/**
	 * Gets the random content rewrite rules after flushing the rules.
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 *
	 * @return string[] Random content rewrite rules, keyed by their regex pattern.
	 */
	private function get_random_rewrite_rules() {
		global $wp_rewrite;

		$wp_rewrite->flush_rules();

		return $wp_rewrite->random_rewrite_rules();
	}

	/**
	 * Gets the rewrite rules stored in the database.
	 *
	 * @return array<mixed> Stored rewrite rules, keyed by their regex pattern.
	 */
	private function get_stored_rewrite_rules(): array {
		$rules = get_option( 'rewrite_rules' );
		$this->assertIsArray( $rules, 'The rewrite rules are not stored in the database.' );

		return $rules;
	}

	/**
	 * @dataProvider data_core_random_rewrite_rules
	 *
	 * @param string $regex Expected rewrite rule regex.
	 * @param string $query Expected rewrite rule query.
	 */
	public function test_core_random_rewrite_rules_are_registered( $regex, $query ): void {
		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( $regex, $rules, 'The random content rewrite rule is not registered.' );
		$this->assertSame( $query, $rules[ $regex ], 'The random content rewrite rule query is not correct.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_core_random_rewrite_rules(): array {
		return array(
			'any post'    => array( 'random/?$', 'index.php?random=1' ),
			'category'    => array( 'random/category/(.+?)/?$', 'index.php?category_name=$matches[1]&random=1' ),
			'tag'         => array( 'random/tag/([^/]+)/?$', 'index.php?tag=$matches[1]&random=1' ),
			'post format' => array( 'random/type/([^/]+)/?$', 'index.php?post_format=$matches[1]&random=1' ),
			'author'      => array( 'random/author/([^/]+)/?$', 'index.php?author_name=$matches[1]&random=1' ),
		);
	}

	/**
	 * Every generated rule must include the random query variable.
	 */
	public function test_all_random_rewrite_rules_set_the_random_query_var(): void {
		register_taxonomy( 'wptests_genre', 'post', array( 'public' => true ) );
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => true,
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertNotEmpty( $rules, 'No random content rewrite rules were generated.' );
		foreach ( $rules as $regex => $query ) {
			parse_str( (string) wp_parse_url( $query, PHP_URL_QUERY ), $query_vars );

			$this->assertStringStartsWith( 'random/', $regex, "The rule {$regex} is not prefixed with the random base." );
			$this->assertSame( '1', $query_vars['random'] ?? null, "The rule {$regex} does not set the random query variable." );
		}
	}

	public function test_random_rewrite_rules_respect_the_category_base(): void {
		update_option( 'category_base', 'topics' );
		create_initial_taxonomies();

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/topics/(.+?)/?$', $rules, 'The custom category base is not used.' );
		$this->assertArrayNotHasKey( 'random/category/(.+?)/?$', $rules, 'The default category base should not be used.' );
	}

	public function test_random_rewrite_rules_respect_the_tag_base(): void {
		update_option( 'tag_base', 'labels' );
		create_initial_taxonomies();

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/labels/([^/]+)/?$', $rules, 'The custom tag base is not used.' );
	}

	public function test_random_rewrite_rules_include_public_custom_taxonomies(): void {
		register_taxonomy(
			'wptests_genre',
			'post',
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'genre' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/genre/([^/]+)/?$', $rules, 'The public custom taxonomy rule is missing.' );
		$this->assertSame( 'index.php?wptests_genre=$matches[1]&random=1', $rules['random/genre/([^/]+)/?$'], 'The public custom taxonomy rule has an unexpected query.' );
	}

	public function test_random_rewrite_rules_include_hierarchical_custom_taxonomies(): void {
		register_taxonomy(
			'wptests_genre',
			'post',
			array(
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array(
					'slug'         => 'genre',
					'hierarchical' => true,
				),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/genre/(.+?)/?$', $rules, 'The hierarchical custom taxonomy rule is missing.' );
		$this->assertSame( 'index.php?wptests_genre=$matches[1]&random=1', $rules['random/genre/(.+?)/?$'], 'The hierarchical custom taxonomy rule has an unexpected query.' );
	}

	public function test_random_rewrite_rules_exclude_non_publicly_queryable_taxonomies(): void {
		register_taxonomy(
			'wptests_hidden',
			'post',
			array(
				'public'             => true,
				'publicly_queryable' => false,
				'rewrite'            => array( 'slug' => 'hidden' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/hidden/([^/]+)/?$', $rules, 'Non-publicly queryable taxonomies should not have a rule.' );
	}

	public function test_random_rewrite_rules_exclude_taxonomies_without_rewrites(): void {
		register_taxonomy(
			'wptests_norewrite',
			'post',
			array(
				'public'  => true,
				'rewrite' => false,
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/wptests_norewrite/([^/]+)/?$', $rules, 'Taxonomies without rewrites should not have a rule at their slug.' );
		$this->assertStringNotContainsString( 'wptests_norewrite', implode( ' ', $rules ), 'Taxonomies without rewrites should not appear in any rule.' );
	}

	public function test_random_rewrite_rules_include_post_type_archives(): void {
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => true,
				'rewrite'     => array( 'slug' => 'books' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/books/?$', $rules, 'The post type archive rule is missing.' );
		$this->assertSame( 'index.php?post_type=wptests_book&random=1', $rules['random/books/?$'], 'The post type archive rule has an unexpected query.' );
	}

	public function test_random_rewrite_rules_use_custom_post_type_archive_slug(): void {
		register_post_type(
			'wptests_film',
			array(
				'public'      => true,
				'has_archive' => 'cinema',
				'rewrite'     => array( 'slug' => 'film' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/cinema/?$', $rules, 'The custom archive slug is not used.' );
		$this->assertArrayNotHasKey( 'random/film/?$', $rules, 'The post type rewrite slug should not be used when the archive slug differs.' );
		$this->assertSame( 'index.php?post_type=wptests_film&random=1', $rules['random/cinema/?$'], 'The custom post type archive slug rule has an unexpected query.' );
	}

	public function test_random_rewrite_rules_exclude_post_types_without_archives(): void {
		register_post_type(
			'wptests_note',
			array(
				'public'      => true,
				'has_archive' => false,
				'rewrite'     => array( 'slug' => 'notes' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/notes/?$', $rules, 'Post types without archives should not have a rule.' );
		$this->assertArrayNotHasKey( 'random/page/?$', $rules, 'Pages do not have an archive.' );
	}

	public function test_random_rewrite_rules_exclude_non_publicly_queryable_post_types(): void {
		register_post_type(
			'wptests_private',
			array(
				'public'             => true,
				'publicly_queryable' => false,
				'has_archive'        => true,
				'rewrite'            => array( 'slug' => 'private-things' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/private-things/?$', $rules, 'Non-publicly queryable post types should not have a rule.' );
	}

	public function test_random_rewrite_rules_exclude_post_types_that_are_not_randomable(): void {
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => true,
				'randomable'  => false,
				'rewrite'     => array( 'slug' => 'books' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/books/?$', $rules, 'Post types that are not randomable should not have a rule.' );
	}

	public function test_random_rewrite_rules_include_post_types_randomable_by_default(): void {
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => true,
				'rewrite'     => array( 'slug' => 'books' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/books/?$', $rules, 'Publicly queryable post types are randomable by default.' );
	}

	public function test_random_rewrite_rules_exclude_taxonomies_used_only_by_non_randomable_post_types(): void {
		register_taxonomy(
			'wptests_genre',
			'page',
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'genre' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/genre/([^/]+)/?$', $rules, 'Taxonomies used only by non-randomable post types should not have a rule.' );
	}

	public function test_random_rewrite_rules_include_taxonomies_shared_with_randomable_post_types(): void {
		register_taxonomy(
			'wptests_genre',
			array( 'page', 'post' ),
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'genre' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/genre/([^/]+)/?$', $rules, 'Taxonomies shared with randomable post types should have a rule.' );
	}

	public function test_random_rewrite_rules_exclude_core_taxonomies_when_posts_are_not_randomable(): void {
		$filter = static function ( array $args, string $post_type ): array {
			if ( 'post' === $post_type ) {
				$args['randomable'] = false;
			}
			return $args;
		};
		add_filter( 'register_post_type_args', $filter, 10, 2 );
		create_initial_post_types();

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/category/(.+?)/?$', $rules, 'The category rule should be omitted when posts are not randomable.' );
		$this->assertArrayNotHasKey( 'random/tag/([^/]+)/?$', $rules, 'The tag rule should be omitted when posts are not randomable.' );
	}

	public function test_random_rewrite_rules_exclude_post_type_archives_without_rewrites(): void {
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => true,
				'rewrite'     => false,
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertStringNotContainsString( 'post_type=wptests_book', implode( ' ', $rules ), 'Post type archives without rewrites should not appear in any rule.' );
	}

	/**
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_random_root_rule_is_omitted_for_a_page_at_a_custom_random_base(): void {
		global $wp_rewrite;

		$wp_rewrite->random_base = 'surprise-me';
		self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'surprise-me',
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'surprise-me/?$', $rules, 'The root rule should be omitted for a page at a custom random base.' );
	}

	/**
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_random_rewrite_rules_respect_the_random_base(): void {
		global $wp_rewrite;

		$wp_rewrite->random_base = 'surprise-me';

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'surprise-me/?$', $rules, 'The root rule should use the custom random base property.' );
		$this->assertArrayHasKey( 'surprise-me/category/(.+?)/?$', $rules, 'The custom random base is not used for archives.' );
		$this->assertArrayNotHasKey( 'random/?$', $rules, 'The root rule should not use the default base when the property is customized.' );
	}

	/**
	 * @covers WP_Rewrite::init
	 * @covers WP_Rewrite::get_random_base
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_random_base_defaults_to_random(): void {
		global $wp_rewrite;

		$this->assertFalse( get_option( 'random_base' ), 'The random_base option should not be populated on install.' );
		$this->assertSame( '', $wp_rewrite->random_base, 'The custom random base property should be empty by default.' );
		$this->assertSame( 'random', $wp_rewrite->get_random_base(), 'The random base should default to random.' );
	}

	/**
	 * @covers WP_Rewrite::get_random_base
	 *
	 * @dataProvider data_random_base_default_is_translated
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 *
	 * @param string $translation The translation of the default random base.
	 * @param string $expected    The expected random base.
	 */
	public function test_random_base_default_is_translated( string $translation, string $expected ): void {
		global $wp_rewrite;

		add_filter(
			'gettext_with_context',
			static function ( string $translated, string $text, string $context ) use ( $translation ): string {
				return 'random' === $text && 'random content permalink base' === $context ? $translation : $translated;
			},
			10,
			3
		);

		$this->assertSame( $expected, $wp_rewrite->get_random_base(), "The random base should be '{$expected}' when the default is translated as '{$translation}'." );

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( "{$expected}/?$", $rules, "The root rule should use the translated random base '{$expected}'." );
	}

	/**
	 * Data provider for test_random_base_default_is_translated().
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_random_base_default_is_translated(): array {
		return array(
			'translated'        => array( 'zufall', 'zufall' ),
			'empty translation' => array( '', 'random' ),
		);
	}

	/**
	 * @covers WP_Rewrite::get_random_base
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_custom_random_base_is_not_translated(): void {
		global $wp_rewrite;

		add_filter(
			'gettext_with_context',
			static function ( string $translated, string $text, string $context ): string {
				return 'random' === $text && 'random content permalink base' === $context ? 'zufall' : $translated;
			},
			10,
			3
		);

		$wp_rewrite->set_random_base( 'surprise-me' );

		$this->assertSame( 'surprise-me', $wp_rewrite->get_random_base(), 'A custom random base should be used instead of the translated default.' );
	}

	/**
	 * @covers WP_Rewrite::set_random_base
	 * @covers WP_Rewrite::get_random_base
	 * @covers WP_Rewrite::init
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_set_random_base_updates_the_random_base(): void {
		global $wp_rewrite;

		$wp_rewrite->set_random_base( '/surprise-me' );

		$this->assertSame( 'surprise-me', get_option( 'random_base' ), 'The option should be saved without slashes.' );
		$this->assertSame( 'surprise-me', $wp_rewrite->random_base, 'The rewrite base should be set from the option.' );
		$this->assertSame( 'surprise-me', $wp_rewrite->get_random_base(), 'The random base in use should be the custom base.' );

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'surprise-me/?$', $rules, 'The root rule should use the random base set by set_random_base().' );
		$this->assertArrayNotHasKey( 'random/?$', $rules, 'The root rule should not use the default base after set_random_base().' );
	}

	/**
	 * @covers WP_Rewrite::set_random_base
	 * @covers WP_Rewrite::get_random_base
	 * @covers WP_Rewrite::init
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_empty_random_base_falls_back_to_the_default(): void {
		global $wp_rewrite;

		$wp_rewrite->set_random_base( '/surprise-me' );
		$wp_rewrite->set_random_base( '' );

		$this->assertSame( '', $wp_rewrite->random_base, 'An empty option should clear the custom random base property.' );
		$this->assertSame( 'random', $wp_rewrite->get_random_base(), 'An empty random base should fall back to the default.' );
	}

	/**
	 * @covers WP_Rewrite::init
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_random_base_option_strips_a_pathinfo_prefix_and_slashes(): void {
		global $wp_rewrite;

		update_option( 'random_base', '/index.php/surprise-me/' );
		$wp_rewrite->init();

		$this->assertSame( 'surprise-me', $wp_rewrite->random_base, 'The random base option should be stripped of the PATHINFO prefix and slashes.' );
	}

	public function test_random_rewrite_rules_ignore_the_permalink_front(): void {
		$this->set_permalink_structure( '/blog/%postname%/' );

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/?$', $rules, 'The root rule should ignore the permalink front.' );
		$this->assertArrayHasKey( 'random/category/(.+?)/?$', $rules, 'The category rule should ignore the permalink front.' );
	}

	public function test_random_rewrite_rules_support_pathinfo_permalinks(): void {
		$this->set_permalink_structure( '/index.php/%postname%/' );

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'index.php/random/?$', $rules, 'The root rule should support PATHINFO permalinks.' );
		$this->assertArrayHasKey( 'index.php/random/category/(.+?)/?$', $rules, 'The category rule should support PATHINFO permalinks.' );
	}

	public function test_no_random_rewrite_rules_when_disabled(): void {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$this->assertSame( array(), $this->get_random_rewrite_rules(), 'Random rules should not be generated when disabled.' );
		$this->assertArrayNotHasKey( 'random/?$', $this->get_stored_rewrite_rules(), 'Random rules should not be stored when disabled.' );
	}

	public function test_random_rewrite_rules_are_stored_in_the_rewrite_rules_option(): void {
		$this->get_random_rewrite_rules();

		$rules = $this->get_stored_rewrite_rules();

		$this->assertArrayHasKey( 'random/?$', $rules, 'The root rule should be stored in the rewrite_rules option.' );
		$this->assertSame( 'index.php?random=1', $rules['random/?$'], 'The stored root rule has an unexpected query.' );
	}

	/**
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_random_rewrite_rules_filter(): void {
		global $wp_rewrite;

		$filter = new MockAction();
		add_filter( 'random_rewrite_rules', array( $filter, 'filter' ) );
		add_filter(
			'random_rewrite_rules',
			static function ( array $rules ): array {
				$rules['lucky-dip/?$'] = 'index.php?random=1';
				return $rules;
			}
		);

		$wp_rewrite->flush_rules();
		$rules    = $this->get_stored_rewrite_rules();
		$received = $filter->get_args()[0][0] ?? null;

		$this->assertSame( 1, $filter->get_call_count(), 'The filter should run once per rules generation.' );
		$this->assertIsArray( $received, 'The filter should receive an array of rules.' );
		$this->assertArrayHasKey( 'random/?$', $received, 'The filter should receive the random rules.' );
		$this->assertArrayHasKey( 'lucky-dip/?$', $rules, 'Rules added via the filter should be stored.' );
	}

	/**
	 * Random rules must be matched before any other rule matching the same URL,
	 * such as the greedy page and post rules.
	 *
	 * @dataProvider data_permalink_structures
	 *
	 * @param string $structure Permalink structure.
	 */
	public function test_random_rewrite_rules_precede_other_matching_rules( $structure ): void {
		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		flush_rewrite_rules();

		$rules = $this->get_stored_rewrite_rules();
		$keys  = array_keys( $rules );

		foreach ( array(
			'random/'               => 'random/?$',
			'random/category/news/' => 'random/category/(.+?)/?$',
		) as $path => $random_rule ) {
			$random_index = array_search( $random_rule, $keys, true );
			$this->assertIsInt( $random_index, "The {$random_rule} rule is missing." );

			$competing_rules = 0;
			foreach ( $keys as $index => $regex ) {
				if ( $regex === $random_rule || ! preg_match( "#^{$regex}#", $path ) ) {
					continue;
				}

				++$competing_rules;
				$this->assertGreaterThan( $random_index, $index, "The {$regex} rule would match {$path} before the random rule." );
			}

			$this->assertGreaterThan( 0, $competing_rules, "No competing rules match {$path}, the test is not meaningful." );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_permalink_structures(): array {
		return array(
			'post name'          => array( '/%postname%/' ),
			'verbose page rules' => array( '/%category%/%postname%/' ),
			'date and name'      => array( '/%year%/%monthnum%/%postname%/' ),
		);
	}

	/**
	 * @dataProvider data_random_urls
	 *
	 * @global WP $wp Current WordPress environment instance.
	 *
	 * @param string                $path           Request path.
	 * @param array<string, string> $expected_query Expected query variables.
	 */
	public function test_random_urls_resolve_to_random_query_vars( $path, $expected_query ): void {
		global $wp;

		register_taxonomy( 'wptests_genre', 'post', array( 'rewrite' => array( 'slug' => 'genre' ) ) );
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => 'books',
			)
		);
		flush_rewrite_rules();

		$this->go_to( home_url( $path ) );

		foreach ( $expected_query as $var => $value ) {
			$this->assertArrayHasKey( $var, $wp->query_vars, "The {$var} query variable is not set." );
			$this->assertSame( $value, $wp->query_vars[ $var ], "The {$var} query variable is incorrect." );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: array<string, string>}>
	 */
	public function data_random_urls(): array {
		return array(
			'random'                    => array( '/random/', array( 'random' => '1' ) ),
			'random without slash'      => array( '/random', array( 'random' => '1' ) ),
			'random category'           => array(
				'/random/category/news/',
				array(
					'random'        => '1',
					'category_name' => 'news',
				),
			),
			'random child category'     => array(
				'/random/category/news/local/',
				array(
					'random'        => '1',
					'category_name' => 'news/local',
				),
			),
			'random tag'                => array(
				'/random/tag/featured/',
				array(
					'random' => '1',
					'tag'    => 'featured',
				),
			),
			'random author'             => array(
				'/random/author/admin/',
				array(
					'random'      => '1',
					'author_name' => 'admin',
				),
			),
			'random custom taxonomy'    => array(
				'/random/genre/sci-fi/',
				array(
					'random'        => '1',
					'wptests_genre' => 'sci-fi',
				),
			),
			'random post type archive'  => array(
				'/random/books/',
				array(
					'random'    => '1',
					'post_type' => 'wptests_book',
				),
			),
			'query string random'       => array( '/?random', array( 'random' => '' ) ),
			'query string random value' => array( '/?random=1', array( 'random' => '1' ) ),
		);
	}

	/**
	 * A published page at the random base takes precedence over the random rule.
	 */
	public function test_random_root_rule_is_omitted_when_a_page_named_random_exists(): void {
		self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_name'     => 'random',
				'post_password' => 'secret',
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/?$', $rules, 'The page should take precedence over the random rule.' );
		$this->assertArrayHasKey( 'random/category/(.+?)/?$', $rules, 'Random archive rules should be unaffected.' );
	}

	/**
	 * @dataProvider data_pages_that_do_not_conflict
	 *
	 * @param array<string, string|bool> $args Page arguments. A `post_parent` is replaced with a parent page ID.
	 */
	public function test_random_root_rule_is_kept_for_pages_that_do_not_conflict( $args ): void {
		if ( isset( $args['post_parent'] ) ) {
			$args['post_parent'] = self::factory()->post->create( array( 'post_type' => 'page' ) );
		}

		self::factory()->post->create( array_merge( array( 'post_type' => 'page' ), $args ) );

		$this->assertArrayHasKey( 'random/?$', $this->get_random_rewrite_rules(), 'The root rule should be kept for pages that do not conflict.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: array<string, string|bool>}>
	 */
	public function data_pages_that_do_not_conflict(): array {
		return array(
			'slug starting with random' => array( array( 'post_name' => 'random-is-just-patterns-we-cant-decipher' ) ),
			'draft page named random'   => array(
				array(
					'post_name'   => 'random',
					'post_status' => 'draft',
				),
			),
			'private page named random' => array(
				array(
					'post_name'   => 'random',
					'post_status' => 'private',
				),
			),
			'child page named random'   => array(
				array(
					'post_name'   => 'random',
					'post_parent' => true,
				),
			),
		);
	}

	/**
	 * Disabling random redirects returns the URL to the page named "random".
	 */
	public function test_page_named_random_is_reachable_when_disabled(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_name'  => 'random',
				'post_title' => 'Random',
			)
		);

		add_filter( 'wp_enable_random_content_redirect', '__return_false' );
		flush_rewrite_rules();

		$this->go_to( home_url( '/random/' ) );

		$this->assertQueryTrue( 'is_page', 'is_singular' );
		$this->assertSame( $page_id, get_queried_object_id(), 'The page named random should be queried when random content is disabled.' );
	}

	/**
	 * @covers ::wp_flush_rewrite_rules_on_site_language_change
	 */
	public function test_rewrite_rules_are_cleared_when_the_site_language_is_added(): void {
		delete_option( 'WPLANG' );
		flush_rewrite_rules( false );

		$this->assertNotEmpty( get_option( 'rewrite_rules' ), 'The rewrite rules should be stored before the site language is added.' );

		update_option( 'WPLANG', 'de_DE' );

		$this->assertSame( '', get_option( 'rewrite_rules' ), 'The rewrite rules should be cleared when the site language is added.' );
	}

	/**
	 * @covers ::wp_flush_rewrite_rules_on_site_language_change
	 */
	public function test_rewrite_rules_are_cleared_when_the_site_language_is_changed(): void {
		update_option( 'WPLANG', 'es_ES' );
		flush_rewrite_rules( false );

		$this->assertNotEmpty( get_option( 'rewrite_rules' ), 'The rewrite rules should be stored before the site language is changed.' );

		update_option( 'WPLANG', 'de_DE' );

		$this->assertSame( '', get_option( 'rewrite_rules' ), 'The rewrite rules should be cleared when the site language is changed.' );
	}

	/**
	 * @covers ::wp_flush_rewrite_rules_on_site_language_change
	 */
	public function test_rewrite_rules_are_kept_when_the_site_language_is_unchanged(): void {
		update_option( 'WPLANG', 'de_DE' );
		flush_rewrite_rules( false );

		update_option( 'WPLANG', 'de_DE' );

		$this->assertNotEmpty( get_option( 'rewrite_rules' ), 'The rewrite rules should be kept when the site language is saved unchanged.' );
	}

	/**
	 * @covers ::wp_flush_rewrite_rules_on_site_language_change
	 * @covers WP_Rewrite::get_random_base
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function test_rewrite_rules_are_regenerated_with_the_translated_random_base_after_the_site_language_changes(): void {
		global $wp_rewrite;

		flush_rewrite_rules( false );
		update_option( 'WPLANG', 'de_DE' );

		add_filter(
			'gettext_with_context',
			static function ( string $translated, string $text, string $context ): string {
				return 'random' === $text && 'random content permalink base' === $context ? 'zufall' : $translated;
			},
			10,
			3
		);

		$rules = $wp_rewrite->wp_rewrite_rules();

		$this->assertIsArray( $rules, 'The rewrite rules should be regenerated on the next request.' );
		$this->assertArrayHasKey( 'zufall/?$', $rules, 'The regenerated rewrite rules should use the translated random base.' );
		$this->assertArrayNotHasKey( 'random/?$', $rules, 'The regenerated rewrite rules should not use the previous random base.' );
		$this->assertIsArray( get_option( 'rewrite_rules' ), 'The regenerated rewrite rules should be stored.' );
	}

	/**
	 * @covers WP_Rewrite::post_permalinks_share_random_base
	 *
	 * @dataProvider data_post_permalinks_share_random_base
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 *
	 * @param string $structure Permalink structure.
	 * @param bool   $expected  Whether post permalinks are expected to share the random base.
	 */
	public function test_post_permalinks_share_random_base( string $structure, bool $expected ): void {
		global $wp_rewrite;

		$this->set_permalink_structure( $structure );

		$this->assertSame( $expected, $wp_rewrite->post_permalinks_share_random_base(), "Post permalinks sharing the random base is unexpected for the '{$structure}' structure." );
	}

	/**
	 * Data provider for test_post_permalinks_share_random_base().
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function data_post_permalinks_share_random_base(): array {
		return array(
			'post name'                => array( '/%postname%/', true ),
			'post name without slash'  => array( '/%postname%', true ),
			'pathinfo post name'       => array( '/index.php/%postname%/', true ),
			'plain'                    => array( '', false ),
			'blog prefix'              => array( '/blog/%postname%/', false ),
			'date and post name'       => array( '/%year%/%monthnum%/%postname%/', false ),
			'category and post name'   => array( '/%category%/%postname%/', false ),
			'post name with extension' => array( '/%postname%.html', false ),
			'post ID'                  => array( '/%post_id%/', false ),
		);
	}

	/**
	 * @covers WP_Rewrite::post_permalinks_share_random_base
	 *
	 * @dataProvider data_post_name_at_the_root_structures
	 *
	 * @param string $structure Permalink structure.
	 */
	public function test_random_root_rule_is_omitted_when_a_post_named_random_exists( string $structure ): void {
		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();

		self::factory()->post->create( array( 'post_name' => 'random' ) );

		$rules = $this->get_random_rewrite_rules();
		$root  = 'pathinfo' === $this->dataName() ? 'index.php/' : '';

		$this->assertArrayNotHasKey( "{$root}random/?$", $rules, "The post should take precedence over the random rule for the '{$structure}' structure." );
		$this->assertArrayHasKey( "{$root}random/category/(.+?)/?$", $rules, "Random archive rules should be unaffected by a post named random for the '{$structure}' structure." );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_post_name_at_the_root_structures(): array {
		return array(
			'post name' => array( '/%postname%/' ),
			'pathinfo'  => array( '/index.php/%postname%/' ),
		);
	}

	/**
	 * @dataProvider data_posts_that_do_not_conflict
	 *
	 * @param string                $structure Permalink structure.
	 * @param array<string, string> $args      Post arguments.
	 */
	public function test_random_root_rule_is_kept_for_posts_that_do_not_conflict( string $structure, array $args ): void {
		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();

		self::factory()->post->create( array_merge( array( 'post_name' => 'random' ), $args ) );

		$this->assertArrayHasKey( 'random/?$', $this->get_random_rewrite_rules(), 'The root rule should be kept for posts that do not conflict.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: array<string, string>}>
	 */
	public function data_posts_that_do_not_conflict(): array {
		return array(
			'blog prefix'              => array( '/blog/%postname%/', array() ),
			'date and post name'       => array( '/%year%/%monthnum%/%postname%/', array() ),
			'post name with extension' => array( '/%postname%.html', array() ),
			'draft post'               => array( '/%postname%/', array( 'post_status' => 'draft' ) ),
			'private post'             => array( '/%postname%/', array( 'post_status' => 'private' ) ),
			'other post type'          => array(
				'/%postname%/',
				array(
					'post_type' => 'page',
					'post_name' => 'not-random',
				),
			),
		);
	}
}
