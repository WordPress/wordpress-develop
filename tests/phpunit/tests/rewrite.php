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

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies();

		$this->home_url = get_option( 'home' );
	}

	public function tear_down() {
		global $wp_rewrite;
		$wp_rewrite->init();

		update_option( 'home', $this->home_url );
		unset( $this->blog_id_35531 );
		parent::tear_down();
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

		_unregister_post_type( $post_type );
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
	public function data_url_to_postid_host_matching() {
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
		$this->assertNotEquals(
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

		_unregister_post_type( 'foo' );

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
		$this->set_permalink_structure( '/%postname%/' );

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

	/**
	 * @ticket 43746
	 */
	public function test_cpt_with_no_archive_and_explicit_feeds_true_generates_single_post_feed_rules() {
		global $wp_rewrite;

		register_post_type(
			'cpt_43746_rules',
			array(
				'public'      => true,
				'has_archive' => false,
				'rewrite'     => array(
					'slug'  => 'cpt-43746-rules',
					'feeds' => true,
				),
			)
		);

		$wp_rewrite->flush_rules();
		$rules = $wp_rewrite->rewrite_rules();

		$feed_rule_found = false;
		foreach ( array_keys( $rules ) as $pattern ) {
			// Match direct single-post feed patterns.
			if ( str_contains( $pattern, 'cpt-43746-rules/([^/]+)/' ) && str_contains( $pattern, 'feed' ) ) {
				$feed_rule_found = true;
				break;
			}
		}

		_unregister_post_type( 'cpt_43746_rules' );
		$wp_rewrite->flush_rules();

		$this->assertTrue( $feed_rule_found, 'Feed rewrite rules should be generated for a CPT with has_archive=false and feeds=true' );
	}

	/**
	 * @ticket 43746
	 */
	public function test_cpt_with_no_archive_and_feeds_false_does_not_generate_single_post_feed_rules() {
		global $wp_rewrite;

		register_post_type(
			'cpt_43746_norules',
			array(
				'public'      => true,
				'has_archive' => false,
				'rewrite'     => array(
					'slug'  => 'cpt-43746-norules',
					'feeds' => false,
				),
			)
		);

		$wp_rewrite->flush_rules();
		$rules = $wp_rewrite->rewrite_rules();

		$feed_rule_found = false;
		foreach ( array_keys( $rules ) as $pattern ) {
			// Only look for direct single-post feed patterns.
			if ( str_contains( $pattern, 'cpt-43746-norules/([^/]+)/' ) && str_contains( $pattern, 'feed' ) ) {
				$feed_rule_found = true;
				break;
			}
		}

		_unregister_post_type( 'cpt_43746_norules' );
		$wp_rewrite->flush_rules();

		$this->assertFalse( $feed_rule_found, 'No feed rewrite rules should be generated for a CPT with has_archive=false and feeds=false' );
	}

	/**
	 * @ticket 43746
	 */
	public function test_cpt_with_no_archive_and_explicit_feeds_true_does_not_generate_archive_feed_rules() {
		global $wp_rewrite;

		register_post_type(
			'cpt_43746_noarch',
			array(
				'public'      => true,
				'has_archive' => false,
				'rewrite'     => array(
					'slug'  => 'cpt-43746-noarch',
					'feeds' => true,
				),
			)
		);

		$wp_rewrite->flush_rules();
		$rules = $wp_rewrite->rewrite_rules();

		$archive_feed_rule_found = false;
		foreach ( $rules as $pattern => $query ) {
			// Archive feed rules point directly to post_type=cpt_43746_noarch&feed= (no post slug segment).
			if ( str_contains( $pattern, 'cpt-43746-noarch' )
				&& str_contains( $pattern, 'feed' )
				&& str_contains( $query, 'post_type=cpt_43746_noarch&feed=' )
				&& ! str_contains( $query, 'cpt_43746_noarch=' )
			) {
				$archive_feed_rule_found = true;
				break;
			}
		}

		_unregister_post_type( 'cpt_43746_noarch' );
		$wp_rewrite->flush_rules();

		$this->assertFalse( $archive_feed_rule_found, 'Archive feed rewrite rules should NOT be generated when has_archive is false, even if feeds is true' );
	}
}
