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

	public function set_up() {
		parent::set_up();

		// Prevent random content redirects from exiting, only the rewrite rules are under test.
		add_filter( 'wp_redirect', '__return_false' );

		$this->set_permalink_structure( '/%postname%/' );
		// Register the taxonomy rewrite rules for the permalink structure.
		create_initial_taxonomies();
	}

	public function tear_down() {
		global $wp_rewrite;

		$wp_rewrite->random_base = 'random';

		_unregister_post_type( 'wptests_book' );
		_unregister_post_type( 'wptests_film' );
		_unregister_post_type( 'wptests_note' );
		_unregister_post_type( 'wptests_private' );
		_unregister_taxonomy( 'wptests_genre' );
		_unregister_taxonomy( 'wptests_hidden' );
		_unregister_taxonomy( 'wptests_norewrite' );

		// Restore the default category and tag bases.
		update_option( 'category_base', '' );
		update_option( 'tag_base', '' );
		create_initial_taxonomies();

		parent::tear_down();
	}

	/**
	 * Gets the random content rewrite rules after flushing the rules.
	 *
	 * @return string[] Random content rewrite rules, keyed by their regex pattern.
	 */
	private function get_random_rewrite_rules() {
		global $wp_rewrite;

		$wp_rewrite->flush_rules();

		return $wp_rewrite->random_rewrite_rules();
	}

	/**
	 * @dataProvider data_core_random_rewrite_rules
	 *
	 * @param string $regex Expected rewrite rule regex.
	 * @param string $query Expected rewrite rule query.
	 */
	public function test_core_random_rewrite_rules_are_registered( $regex, $query ) {
		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( $regex, $rules, 'The random content rewrite rule is not registered.' );
		$this->assertSame( $query, $rules[ $regex ], 'The random content rewrite rule query is not correct.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_core_random_rewrite_rules() {
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
	public function test_all_random_rewrite_rules_set_the_random_query_var() {
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

	public function test_random_rewrite_rules_respect_the_category_base() {
		update_option( 'category_base', 'topics' );
		create_initial_taxonomies();

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/topics/(.+?)/?$', $rules, 'The custom category base is not used.' );
		$this->assertArrayNotHasKey( 'random/category/(.+?)/?$', $rules, 'The default category base should not be used.' );
	}

	public function test_random_rewrite_rules_respect_the_tag_base() {
		update_option( 'tag_base', 'labels' );
		create_initial_taxonomies();

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/labels/([^/]+)/?$', $rules, 'The custom tag base is not used.' );
	}

	public function test_random_rewrite_rules_include_public_custom_taxonomies() {
		register_taxonomy(
			'wptests_genre',
			'post',
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'genre' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/genre/([^/]+)/?$', $rules );
		$this->assertSame( 'index.php?wptests_genre=$matches[1]&random=1', $rules['random/genre/([^/]+)/?$'] );
	}

	public function test_random_rewrite_rules_include_hierarchical_custom_taxonomies() {
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

		$this->assertArrayHasKey( 'random/genre/(.+?)/?$', $rules );
		$this->assertSame( 'index.php?wptests_genre=$matches[1]&random=1', $rules['random/genre/(.+?)/?$'] );
	}

	public function test_random_rewrite_rules_exclude_non_publicly_queryable_taxonomies() {
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

		$this->assertArrayNotHasKey( 'random/hidden/([^/]+)/?$', $rules );
	}

	public function test_random_rewrite_rules_exclude_taxonomies_without_rewrites() {
		register_taxonomy(
			'wptests_norewrite',
			'post',
			array(
				'public'  => true,
				'rewrite' => false,
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/wptests_norewrite/([^/]+)/?$', $rules );
		$this->assertStringNotContainsString( 'wptests_norewrite', implode( ' ', $rules ) );
	}

	public function test_random_rewrite_rules_include_post_type_archives() {
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => true,
				'rewrite'     => array( 'slug' => 'books' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/books/?$', $rules );
		$this->assertSame( 'index.php?post_type=wptests_book&random=1', $rules['random/books/?$'] );
	}

	public function test_random_rewrite_rules_use_custom_post_type_archive_slug() {
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
		$this->assertSame( 'index.php?post_type=wptests_film&random=1', $rules['random/cinema/?$'] );
	}

	public function test_random_rewrite_rules_exclude_post_types_without_archives() {
		register_post_type(
			'wptests_note',
			array(
				'public'      => true,
				'has_archive' => false,
				'rewrite'     => array( 'slug' => 'notes' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/notes/?$', $rules );
		$this->assertArrayNotHasKey( 'random/page/?$', $rules, 'Pages do not have an archive.' );
	}

	public function test_random_rewrite_rules_exclude_non_publicly_queryable_post_types() {
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

		$this->assertArrayNotHasKey( 'random/private-things/?$', $rules );
	}

	public function test_random_rewrite_rules_exclude_post_types_that_are_not_randomable() {
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

		$this->assertArrayNotHasKey( 'random/books/?$', $rules );
	}

	public function test_random_rewrite_rules_include_post_types_randomable_by_default() {
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

	public function test_random_rewrite_rules_exclude_taxonomies_used_only_by_non_randomable_post_types() {
		register_taxonomy(
			'wptests_genre',
			'page',
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'genre' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'random/genre/([^/]+)/?$', $rules );
	}

	public function test_random_rewrite_rules_include_taxonomies_shared_with_randomable_post_types() {
		register_taxonomy(
			'wptests_genre',
			array( 'page', 'post' ),
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'genre' ),
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/genre/([^/]+)/?$', $rules );
	}

	public function test_random_rewrite_rules_exclude_core_taxonomies_when_posts_are_not_randomable() {
		$filter = static function ( $args, $post_type ) {
			if ( 'post' === $post_type ) {
				$args['randomable'] = false;
			}
			return $args;
		};
		add_filter( 'register_post_type_args', $filter, 10, 2 );
		create_initial_post_types();

		$rules = $this->get_random_rewrite_rules();

		remove_filter( 'register_post_type_args', $filter, 10 );
		create_initial_post_types();

		$this->assertArrayNotHasKey( 'random/category/(.+?)/?$', $rules );
		$this->assertArrayNotHasKey( 'random/tag/([^/]+)/?$', $rules );
	}

	public function test_random_rewrite_rules_exclude_post_type_archives_without_rewrites() {
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => true,
				'rewrite'     => false,
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertStringNotContainsString( 'post_type=wptests_book', implode( ' ', $rules ) );
	}

	public function test_random_root_rule_is_omitted_for_a_page_at_a_custom_random_base() {
		global $wp_rewrite;

		$wp_rewrite->random_base = 'surprise-me';
		self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'surprise-me',
			)
		);

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayNotHasKey( 'surprise-me/?$', $rules );
	}

	public function test_random_rewrite_rules_respect_the_random_base() {
		global $wp_rewrite;

		$wp_rewrite->random_base = 'surprise-me';

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'surprise-me/?$', $rules, 'The custom random base is not used.' );
		$this->assertArrayHasKey( 'surprise-me/category/(.+?)/?$', $rules, 'The custom random base is not used for archives.' );
		$this->assertArrayNotHasKey( 'random/?$', $rules, 'The default random base should not be used.' );
	}

	public function test_random_rewrite_rules_ignore_the_permalink_front() {
		$this->set_permalink_structure( '/blog/%postname%/' );

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'random/?$', $rules );
		$this->assertArrayHasKey( 'random/category/(.+?)/?$', $rules );
	}

	public function test_random_rewrite_rules_support_pathinfo_permalinks() {
		$this->set_permalink_structure( '/index.php/%postname%/' );

		$rules = $this->get_random_rewrite_rules();

		$this->assertArrayHasKey( 'index.php/random/?$', $rules );
		$this->assertArrayHasKey( 'index.php/random/category/(.+?)/?$', $rules );
	}

	public function test_no_random_rewrite_rules_when_disabled() {
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );

		$this->assertSame( array(), $this->get_random_rewrite_rules(), 'Random rules should not be generated when disabled.' );
		$this->assertArrayNotHasKey( 'random/?$', get_option( 'rewrite_rules' ), 'Random rules should not be stored when disabled.' );
	}

	public function test_random_rewrite_rules_are_stored_in_the_rewrite_rules_option() {
		$this->get_random_rewrite_rules();

		$rules = get_option( 'rewrite_rules' );

		$this->assertArrayHasKey( 'random/?$', $rules );
		$this->assertSame( 'index.php?random=1', $rules['random/?$'] );
	}

	public function test_random_rewrite_rules_filter() {
		global $wp_rewrite;

		$filter = new MockAction();
		add_filter( 'random_rewrite_rules', array( $filter, 'filter' ) );
		add_filter(
			'random_rewrite_rules',
			static function ( $rules ) {
				$rules['lucky-dip/?$'] = 'index.php?random=1';
				return $rules;
			}
		);

		$wp_rewrite->flush_rules();
		$rules = get_option( 'rewrite_rules' );

		$this->assertSame( 1, $filter->get_call_count(), 'The filter should run once per rules generation.' );
		$this->assertArrayHasKey( 'random/?$', $filter->get_args()[0][0], 'The filter should receive the random rules.' );
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
	public function test_random_rewrite_rules_precede_other_matching_rules( $structure ) {
		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		flush_rewrite_rules();

		$rules = get_option( 'rewrite_rules' );
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
	public function data_permalink_structures() {
		return array(
			'post name'          => array( '/%postname%/' ),
			'verbose page rules' => array( '/%category%/%postname%/' ),
			'date and name'      => array( '/%year%/%monthnum%/%postname%/' ),
		);
	}

	/**
	 * @dataProvider data_random_urls
	 *
	 * @param string $path           Request path.
	 * @param array  $expected_query Expected query variables.
	 */
	public function test_random_urls_resolve_to_random_query_vars( $path, $expected_query ) {
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
			$this->assertArrayHasKey( $var, $GLOBALS['wp']->query_vars, "The {$var} query variable is not set." );
			$this->assertSame( $value, $GLOBALS['wp']->query_vars[ $var ], "The {$var} query variable is incorrect." );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array>
	 */
	public function data_random_urls() {
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
	public function test_random_root_rule_is_omitted_when_a_page_named_random_exists() {
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
	 * @param array $args Page arguments.
	 */
	public function test_random_root_rule_is_kept_for_pages_that_do_not_conflict( $args ) {
		if ( isset( $args['post_parent'] ) ) {
			$args['post_parent'] = self::factory()->post->create( array( 'post_type' => 'page' ) );
		}

		self::factory()->post->create( array_merge( array( 'post_type' => 'page' ), $args ) );

		$this->assertArrayHasKey( 'random/?$', $this->get_random_rewrite_rules() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array[]>
	 */
	public function data_pages_that_do_not_conflict() {
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
	public function test_page_named_random_is_reachable_when_disabled() {
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
		$this->assertSame( $page_id, get_queried_object_id() );
	}
}
