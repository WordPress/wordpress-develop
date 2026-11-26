<?php

/**
 * A set of unit tests for functions in wp-includes/rewrite.php
 *
 * @group rewrite
 */
class Tests_Rewrite extends WP_UnitTestCase {
	private $home_url;

	/**
	 * Temporary storage for blog id for use with filters.
	 *
	 * Used in the `test_url_to_postid_of_http_site_when_current_site_uses_https()` method.
	 *
	 * @var int
	 */
	private $blog_id_35531;

	public function set_up() {
		parent::set_up();

		create_initial_taxonomies();

		$this->home_url = get_option( 'home' );
	}

	public function tear_down() {
		global $wp_rewrite;
		$wp_rewrite->init();

		update_option( 'home', $this->home_url );
		unset( $_SERVER['HTTPS'] );
		unset( $this->blog_id_35531 );
		parent::tear_down();
	}


	/**
	 * Gets permalink bases that must not be confused with rewrite tags.
	 *
	 * @return string[] Permalink bases.
	 */
	private function get_percent_encoded_bases() {
		return array(
			'Lucky%20Dip',
			'this%20is%20a%20test',
			'%E6%BC%A2%E8%AA%9E',
			'%e6%bc%a2%e8%aa%9e',
			'100%25',
			'100%',
			'Lucky%20Dip/More%20Posts',
			'topics',
		);
	}

	/**
	 * @ticket 45819
	 */
	public function test_generate_rewrite_rules_with_percent_encoded_bases() {
		global $wp_rewrite;

		$rewrite          = clone $wp_rewrite;
		$rewrite->matches = 'matches';
		$rewrite->add_rewrite_tag( '%test.tag%', '([^/]+)', 'test_tag=' );

		foreach ( $this->get_percent_encoded_bases() as $base ) {
			$rules = $rewrite->generate_rewrite_rules( $base . '/%category%', EP_NONE, false, false, false, false, false );
			$this->assertSame( 'index.php?category_name=$matches[1]', $rules[ $base . '/(.+?)/?$' ], $base );
		}

		$rules = $rewrite->generate_rewrite_rules( 'Lucky%20Dip/%test.tag%/more%20posts/%author%', EP_NONE, true, true, false, true, false );
		$this->assertSame( 'index.php?test_tag=$matches[1]&author_name=$matches[2]', $rules['Lucky%20Dip/([^/]+)/more%20posts/([^/]+)/?$'] );
		$this->assertSame( 'index.php?test_tag=$matches[1]&author_name=$matches[2]&paged=$matches[3]', $rules['Lucky%20Dip/([^/]+)/more%20posts/([^/]+)/page/?([0-9]{1,})/?$'] );
		$this->assertSame( 'index.php?test_tag=$matches[1]&author_name=$matches[2]&feed=$matches[3]', $rules['Lucky%20Dip/([^/]+)/more%20posts/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$'] );

		$rewrite->rewritecode = array();
		$this->assertSame( array(), $rewrite->generate_rewrite_rules( 'Lucky%20Dip', EP_NONE, false, false, false, false, false ) );
	}

	/**
	 * @ticket 45819
	 */
	public function test_percent_encoded_bases_preserve_archive_and_random_queries() {
		global $wp, $wp_rewrite, $wp_taxonomies;

		$original_rewrite    = clone $wp_rewrite;
		$original_taxonomies = $wp_taxonomies;
		$original_options    = array();
		foreach ( array( 'permalink_structure', 'category_base', 'tag_base', 'wp_random_base' ) as $option ) {
			$original_options[ $option ] = get_option( $option );
		}
		$stop_redirect = static function () {
			throw new WP_Tests_Exception( 'Random content redirect.' );
		};

		try {
			$this->set_permalink_structure( '/%postname%/' );
			$terms   = array();
			$post_id = self::factory()->post->create();
			foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
				$terms[ $taxonomy ] = self::factory()->term->create_and_get( array( 'taxonomy' => $taxonomy ) );
				wp_set_object_terms( $post_id, array( $terms[ $taxonomy ]->term_id ), $taxonomy );
			}

			foreach ( $this->get_percent_encoded_bases() as $base ) {
				update_option( 'category_base', $base );
				update_option( 'tag_base', 'tags/' . $base );
				$wp_rewrite->init();
				$wp_rewrite->extra_rules_top = $original_rewrite->extra_rules_top;
				create_initial_taxonomies();
				flush_rewrite_rules( false );

				foreach ( $terms as $term ) {
					$this->go_to( get_term_link( $term ) );
					$this->assertFalse( is_404(), $base );
					$this->assertSame( $term->term_id, get_queried_object_id(), $base );
				}
			}

			update_option( 'category_base', '' );
			update_option( 'tag_base', '' );
			$wp_rewrite->set_random_base( 'Lucky Dip' );
			$wp_rewrite->extra_rules_top = $original_rewrite->extra_rules_top;
			create_initial_taxonomies();
			flush_rewrite_rules( false );
			add_filter( 'wp_redirect', $stop_redirect );

			foreach ( array(
				'author/admin/'     => array( 'author_name', 'admin' ),
				'category/missing/' => array( 'category_name', 'missing' ),
				'tag/missing/'      => array( 'tag', 'missing' ),
			) as $path => $expected ) {
				try {
					$this->go_to( home_url( '/Lucky%20Dip/' . $path ) );
				} catch ( WP_Tests_Exception $e ) {
					$this->assertSame( 'Random content redirect.', $e->getMessage() );
				}
				$this->assertSame( '1', $wp->query_vars['random'], $path );
				$this->assertArrayHasKey( $expected[0], $wp->query_vars, $path );
				$this->assertSame( $expected[1], $wp->query_vars[ $expected[0] ], $path );
			}
		} finally {
			remove_filter( 'wp_redirect', $stop_redirect );
			foreach ( $original_options as $option => $value ) {
				update_option( $option, $value );
			}
			$wp_rewrite    = $original_rewrite;
			$wp_taxonomies = $original_taxonomies;
		}
	}

	/**
	 * @ticket 16840
	 */
	public function test_add_rule() {
		global $wp_rewrite;

		$pattern  = 'path/to/rewrite/([^/]+)/?$';
		$redirect = 'index.php?test_var1=$matches[1]&test_var2=1';

		$wp_rewrite->add_rule( $pattern, $redirect );

		$wp_rewrite->flush_rules();

		$rewrite_rules = $wp_rewrite->rewrite_rules();

		$this->assertSame( $redirect, $rewrite_rules[ $pattern ] );
	}

	/**
	 * @ticket 16840
	 */
	public function test_add_rule_redirect_array() {
		global $wp_rewrite;

		$pattern  = 'path/to/rewrite/([^/]+)/?$';
		$redirect = 'index.php?test_var1=$matches[1]&test_var2=1';

		$wp_rewrite->add_rule(
			$pattern,
			array(
				'test_var1' => '$matches[1]',
				'test_var2' => '1',
			)
		);

		$wp_rewrite->flush_rules();

		$rewrite_rules = $wp_rewrite->rewrite_rules();

		$this->assertSame( $redirect, $rewrite_rules[ $pattern ] );
	}

	/**
	 * @ticket 16840
	 */
	public function test_add_rule_top() {
		global $wp_rewrite;

		$pattern  = 'path/to/rewrite/([^/]+)/?$';
		$redirect = 'index.php?test_var1=$matches[1]&test_var2=1';

		$wp_rewrite->add_rule( $pattern, $redirect, 'top' );

		$wp_rewrite->flush_rules();

		$extra_rules_top = $wp_rewrite->extra_rules_top;

		$this->assertStringContainsString( $redirect, $extra_rules_top[ $pattern ] );
	}

	public function test_url_to_postid() {

		$id = self::factory()->post->create();
		$this->assertSame( $id, url_to_postid( get_permalink( $id ) ) );

		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertSame( $id, url_to_postid( get_permalink( $id ) ) );
	}

	public function test_url_to_postid_set_url_scheme_https_to_http() {
		$post_id   = self::factory()->post->create();
		$permalink = get_permalink( $post_id );
		$this->assertSame( $post_id, url_to_postid( set_url_scheme( $permalink, 'https' ) ) );

		$post_id   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$permalink = get_permalink( $post_id );
		$this->assertSame( $post_id, url_to_postid( set_url_scheme( $permalink, 'https' ) ) );
	}

	public function test_url_to_postid_set_url_scheme_http_to_https() {
		$_SERVER['HTTPS'] = 'on';

		$post_id        = self::factory()->post->create();
		$post_permalink = get_permalink( $post_id );
		$post_url_to_id = url_to_postid( set_url_scheme( $post_permalink, 'http' ) );

		$page_id        = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_permalink = get_permalink( $page_id );
		$page_url_to_id = url_to_postid( set_url_scheme( $page_permalink, 'http' ) );

		$this->assertSame( $post_id, $post_url_to_id );
		$this->assertSame( $page_id, $page_url_to_id );
	}

	/**
	 * @ticket 35531
	 * @group multisite
	 * @group ms-required
	 */
	public function test_url_to_postid_of_http_site_when_current_site_uses_https() {
		$_SERVER['HTTPS'] = 'on';

		$network_home        = home_url();
		$this->blog_id_35531 = self::factory()->blog->create();

		add_filter( 'home_url', array( $this, 'filter_http_home_url' ), 10, 4 );

		switch_to_blog( $this->blog_id_35531 );

		$post_id       = self::factory()->post->create();
		$permalink     = get_permalink( $post_id );
		$url_to_postid = url_to_postid( $permalink );

		restore_current_blog();

		// Cleanup.
		remove_filter( 'home_url', array( $this, 'filter_http_home_url' ), 10 );

		// Test the tests.
		$this->assertSame( 'http', parse_url( $permalink, PHP_URL_SCHEME ) );
		$this->assertSame( 'https', parse_url( $network_home, PHP_URL_SCHEME ) );

		// Test that the url_to_postid() call matched.
		$this->assertSame( $post_id, $url_to_postid );
	}

	/**
	 * Enforce an `http` scheme for our target site.
	 *
	 * @param string      $url         The complete home URL including scheme and path.
	 * @param string      $path        Path relative to the home URL. Blank string if no path is specified.
	 * @param string|null $orig_scheme Scheme to give the home URL context.
	 * @param int|null    $_blog_id    Site ID, or null for the current site.
	 * @return string                  The complete home URL including scheme and path.
	 */
	public function filter_http_home_url( $url, $path, $orig_scheme, $_blog_id ) {
		global $blog_id;

		if ( $this->blog_id_35531 === $blog_id ) {
			return set_url_scheme( $url, 'http' );
		}

		return $url;
	}

	public function test_url_to_postid_custom_post_type() {
		delete_option( 'rewrite_rules' );

		$post_type = 'url_to_postid';
		register_post_type( $post_type, array( 'public' => true ) );

		$id = self::factory()->post->create( array( 'post_type' => $post_type ) );
		$this->assertSame( $id, url_to_postid( get_permalink( $id ) ) );
	}

	public function test_url_to_postid_hierarchical() {

		$parent_id = self::factory()->post->create(
			array(
				'post_title' => 'Parent',
				'post_type'  => 'page',
			)
		);
		$child_id  = self::factory()->post->create(
			array(
				'post_title'  => 'Child',
				'post_type'   => 'page',
				'post_parent' => $parent_id,
			)
		);

		$this->assertSame( $parent_id, url_to_postid( get_permalink( $parent_id ) ) );
		$this->assertSame( $child_id, url_to_postid( get_permalink( $child_id ) ) );
	}

	public function test_url_to_postid_hierarchical_with_matching_leaves() {

		$parent_id       = self::factory()->post->create(
			array(
				'post_name' => 'parent',
				'post_type' => 'page',
			)
		);
		$child_id_1      = self::factory()->post->create(
			array(
				'post_name'   => 'child1',
				'post_type'   => 'page',
				'post_parent' => $parent_id,
			)
		);
		$child_id_2      = self::factory()->post->create(
			array(
				'post_name'   => 'child2',
				'post_type'   => 'page',
				'post_parent' => $parent_id,
			)
		);
		$grandchild_id_1 = self::factory()->post->create(
			array(
				'post_name'   => 'grandchild',
				'post_type'   => 'page',
				'post_parent' => $child_id_1,
			)
		);
		$grandchild_id_2 = self::factory()->post->create(
			array(
				'post_name'   => 'grandchild',
				'post_type'   => 'page',
				'post_parent' => $child_id_2,
			)
		);

		$this->assertSame( home_url( 'parent/child1/grandchild/' ), get_permalink( $grandchild_id_1 ) );
		$this->assertSame( home_url( 'parent/child2/grandchild/' ), get_permalink( $grandchild_id_2 ) );
		$this->assertSame( $grandchild_id_1, url_to_postid( get_permalink( $grandchild_id_1 ) ) );
		$this->assertSame( $grandchild_id_2, url_to_postid( get_permalink( $grandchild_id_2 ) ) );
	}

	/**
	 * @covers ::url_to_postid
	 */
	public function test_url_to_postid_url_has_only_path() {
		$this->assertSame( 0, url_to_postid( '/example/' ) );
	}

	/**
	 * Only a leading 'www.' is optional when comparing the URL's host to the site's.
	 *
	 * A 'www.' elsewhere in the host belongs to a different domain, which an attacker
	 * can register: stripping it everywhere makes 'exwww.ample.com' match 'example.com'.
	 *
	 * @ticket 65016
	 *
	 * @covers ::url_to_postid
	 *
	 * @dataProvider data_url_to_postid_host_matching
	 *
	 * @param string $host     Host of the URL to resolve.
	 * @param bool   $is_local Whether the host should be treated as this site.
	 */
	public function test_url_to_postid_matches_www_prefix_only( $host, $is_local ) {
		update_option( 'home', 'https://example.com' );
		update_option( 'siteurl', 'https://example.com' );

		$post_id = self::factory()->post->create();

		$expected = $is_local ? $post_id : 0;

		$this->assertSame( $expected, url_to_postid( "https://$host/?p=$post_id" ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_url_to_postid_host_matching() {
		return array(
			'the site host'              => array( 'example.com', true ),
			'the site host with www'     => array( 'www.example.com', true ),
			'www inside the domain'      => array( 'exwww.ample.com', false ),
			'www inside the TLD'         => array( 'example.cwww.om', false ),
			'an unrelated host'          => array( 'evil.com', false ),
			'the site host as subdomain' => array( 'example.com.evil.com', false ),
		);
	}

	/**
	 * @covers ::url_to_postid
	 */
	public function test_url_to_postid_home_has_only_path() {
		update_option( 'home', home_url( '/example/' ) );

		$id = self::factory()->post->create(
			array(
				'post_title' => 'Hi',
				'post_type'  => 'page',
				'post_name'  => 'examp',
			)
		);
		$this->assertSame( $id, url_to_postid( get_permalink( $id ) ) );
		$this->assertSame( $id, url_to_postid( site_url( '/example/examp' ) ) );
		$this->assertSame( $id, url_to_postid( '/example/examp/' ) );
		$this->assertSame( $id, url_to_postid( '/example/examp' ) );

		$this->assertSame( 0, url_to_postid( site_url( '/example/ex' ) ) );
		$this->assertSame( 0, url_to_postid( '/example/ex' ) );
		$this->assertSame( 0, url_to_postid( '/example/ex/' ) );
		$this->assertSame( 0, url_to_postid( '/example-page/example/' ) );
		$this->assertSame( 0, url_to_postid( '/example-page/ex/' ) );
	}

	/**
	 * @ticket 30438
	 */
	public function test_parse_request_home_path() {
		$home_url = home_url( '/path/' );
		update_option( 'home', $home_url );

		$this->go_to( $home_url );
		$this->assertSame( array(), $GLOBALS['wp']->query_vars );

		$this->go_to( $home_url . 'page' );
		$this->assertSame(
			array(
				'page'     => '',
				'pagename' => 'page',
			),
			$GLOBALS['wp']->query_vars
		);
	}

	/**
	 * @ticket 30438
	 */
	public function test_parse_request_home_path_with_regex_character() {
		$home_url       = home_url( '/ma.ch/' );
		$not_a_home_url = home_url( '/match/' );
		update_option( 'home', $home_url );

		$this->go_to( $home_url );
		$this->assertSame( array(), $GLOBALS['wp']->query_vars );

		$this->go_to( $home_url . 'page' );
		$this->assertSame(
			array(
				'page'     => '',
				'pagename' => 'page',
			),
			$GLOBALS['wp']->query_vars
		);

		$this->go_to( $not_a_home_url . 'page' );
		$this->assertNotSame(
			array(
				'page'     => '',
				'pagename' => 'page',
			),
			$GLOBALS['wp']->query_vars
		);
		$this->assertSame(
			array(
				'page'     => '',
				'pagename' => 'match/page',
			),
			$GLOBALS['wp']->query_vars
		);
	}

	/**
	 * @ticket 30018
	 */
	public function test_parse_request_home_path_non_public_type() {
		register_post_type( 'foo', array( 'public' => false ) );

		$url = add_query_arg( 'foo', '1', home_url() );

		$this->go_to( $url );

		$this->assertSame( array(), $GLOBALS['wp']->query_vars );
	}

	public function test_url_to_postid_dupe_path() {
		update_option( 'home', home_url( '/example/' ) );

		$id = self::factory()->post->create(
			array(
				'post_title' => 'Hi',
				'post_type'  => 'page',
				'post_name'  => 'example',
			)
		);

		$this->assertSame( $id, url_to_postid( get_permalink( $id ) ) );
		$this->assertSame( $id, url_to_postid( site_url( '/example/example/' ) ) );
		$this->assertSame( $id, url_to_postid( '/example/example/' ) );
		$this->assertSame( $id, url_to_postid( '/example/example' ) );
	}

	/**
	 * Reveals bug introduced in WP 3.0
	 */
	public function test_url_to_postid_home_url_collision() {
		update_option( 'home', home_url( '/example' ) );

		self::factory()->post->create(
			array(
				'post_title' => 'Collision',
				'post_type'  => 'page',
				'post_name'  => 'collision',
			)
		);

		// This url should NOT return a post ID.
		$badurl = site_url( '/example-collision' );
		$this->assertSame( 0, url_to_postid( $badurl ) );
	}

	/**
	 * Reveals bug introduced in WP 3.0
	 *
	 * @group ms-required
	 */
	public function test_url_to_postid_ms_home_url_collision() {
		$blog_id = self::factory()->blog->create( array( 'path' => '/example' ) );
		switch_to_blog( $blog_id );

		self::factory()->post->create(
			array(
				'post_title' => 'Collision ',
				'post_type'  => 'page',
			)
		);

		// This url should NOT return a post ID.
		$badurl = network_home_url( '/example-collision' );
		$this->assertSame( 0, url_to_postid( $badurl ) );

		restore_current_blog();
	}

	/**
	 * @ticket 21970
	 */
	public function test_url_to_postid_with_post_slug_that_clashes_with_a_trashed_page() {
		$this->set_permalink_structure( '/%postname%/' );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'trash',
			)
		);
		$post_id = self::factory()->post->create( array( 'post_title' => get_post( $page_id )->post_title ) );

		$this->assertSame( $post_id, url_to_postid( get_permalink( $post_id ) ) );
	}

	/**
	 * @ticket 34971
	 */
	public function test_url_to_postid_static_front_page() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( 0, url_to_postid( home_url() ) );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $post_id );

		$this->assertSame( $post_id, url_to_postid( set_url_scheme( home_url(), 'http' ) ) );
		$this->assertSame( $post_id, url_to_postid( set_url_scheme( home_url(), 'https' ) ) );
		$this->assertSame( $post_id, url_to_postid( str_replace( array( 'http://', 'https://' ), 'http://www.', home_url() ) ) );
		$this->assertSame( $post_id, url_to_postid( home_url() . '#random' ) );
		$this->assertSame( $post_id, url_to_postid( home_url() . '?random' ) );

		update_option( 'show_on_front', 'posts' );
	}

	/**
	 * @ticket 39373
	 */
	public function test_url_to_postid_should_bail_when_host_does_not_match() {
		$post_id   = self::factory()->post->create( array( 'post_name' => 'foo-bar-baz' ) );
		$permalink = get_permalink( $post_id );
		$url       = str_replace( home_url(), 'http://some-other-domain.com', get_permalink( $post_id ) );

		$this->assertSame( $post_id, url_to_postid( $permalink ) );
		$this->assertSame( 0, url_to_postid( $url ) );
	}

	/**
	 * @ticket 21970
	 */
	public function test_parse_request_with_post_slug_that_clashes_with_a_trashed_page() {
		$this->set_permalink_structure( '/%postname%/' );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'trash',
			)
		);
		$post_id = self::factory()->post->create( array( 'post_title' => get_post( $page_id )->post_title ) );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertTrue( is_single() );
		$this->assertFalse( is_404() );
	}

	/**
	 * @ticket 29107
	 */
	public function test_flush_rules_does_not_delete_option() {
		$this->set_permalink_structure( '' );

		$rewrite_rules = get_option( 'rewrite_rules' );
		$this->assertSame( '', $rewrite_rules );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$rewrite_rules = get_option( 'rewrite_rules' );
		$this->assertIsArray( $rewrite_rules );
		$this->assertNotEmpty( $rewrite_rules );
	}
}
