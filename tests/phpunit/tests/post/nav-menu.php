<?php
/**
 * @group post
 * @group menu
 */
class Tests_Post_Nav_Menu extends WP_UnitTestCase {
	/**
	 * @var int
	 */
	public $menu_id;

	public function set_up() {
		parent::set_up();

		$this->menu_id = wp_create_nav_menu( 'foo' );
	}

	/**
	 * @ticket 11095
	 * @ticket 33974
	 */
	public function test_wp_page_menu_wp_nav_menu_fallback() {
		$pages = self::factory()->post->create_many( 3, array( 'post_type' => 'page' ) );

		// No menus + wp_nav_menu() falls back to wp_page_menu().
		$menu = wp_nav_menu( array( 'echo' => false ) );

		// After falling back, the 'before' argument should be set and output as '<ul>'.
		$this->assertMatchesRegularExpression( '/<div class="menu"><ul>/', $menu );

		// After falling back, the 'after' argument should be set and output as '</ul>'.
		$this->assertMatchesRegularExpression( '/<\/ul><\/div>/', $menu );

		// After falling back, the markup should include whitespace around <li>'s.
		$this->assertMatchesRegularExpression( '/\s<li.*>|<\/li>\s/U', $menu );
		$this->assertDoesNotMatchRegularExpression( '/><li.*>|<\/li></U', $menu );

		// No menus + wp_nav_menu() falls back to wp_page_menu(), this time without a container.
		$menu = wp_nav_menu(
			array(
				'echo'      => false,
				'container' => false,
			)
		);

		// After falling back, the empty 'container' argument should still return a container element.
		$this->assertMatchesRegularExpression( '/<div class="menu">/', $menu );

		// No menus + wp_nav_menu() falls back to wp_page_menu(), this time without white-space.
		$menu = wp_nav_menu(
			array(
				'echo'         => false,
				'item_spacing' => 'discard',
			)
		);

		// After falling back, the markup should not include whitespace around <li>'s.
		$this->assertDoesNotMatchRegularExpression( '/\s<li.*>|<\/li>\s/U', $menu );
		$this->assertMatchesRegularExpression( '/><li.*>|<\/li></U', $menu );

	}

	/**
	 * @ticket 32464
	 */
	public function test_wp_nav_menu_empty_container() {
		$tag_id = self::factory()->tag->create();

		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'post_tag',
				'menu-item-object-id' => $tag_id,
				'menu-item-status'    => 'publish',
			)
		);

		$menu = wp_nav_menu(
			array(
				'echo'      => false,
				'container' => '',
				'menu'      => $this->menu_id,
			)
		);

		$this->assertStringStartsWith( '<ul', $menu );
	}

	public function test_wp_get_associated_nav_menu_items() {
		$tag_id    = self::factory()->tag->create();
		$cat_id    = self::factory()->category->create();
		$post_id   = self::factory()->post->create();
		$post_2_id = self::factory()->post->create();
		$page_id   = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$tag_insert = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'post_tag',
				'menu-item-object-id' => $tag_id,
				'menu-item-status'    => 'publish',
			)
		);

		$cat_insert = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'category',
				'menu-item-object-id' => $cat_id,
				'menu-item-status'    => 'publish',
			)
		);

		$post_insert = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
			)
		);

		// Item without menu-item-object arg.
		$post_2_insert = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object-id' => $post_2_id,
				'menu-item-status'    => 'publish',
			)
		);

		$page_insert = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-status'    => 'publish',
			)
		);

		$tag_items = wp_get_associated_nav_menu_items( $tag_id, 'taxonomy', 'post_tag' );
		$this->assertSameSets( array( $tag_insert ), $tag_items );
		$cat_items = wp_get_associated_nav_menu_items( $cat_id, 'taxonomy', 'category' );
		$this->assertSameSets( array( $cat_insert ), $cat_items );
		$post_items = wp_get_associated_nav_menu_items( $post_id );
		$this->assertSameSets( array( $post_insert ), $post_items );
		$post_2_items = wp_get_associated_nav_menu_items( $post_2_id );
		$this->assertSameSets( array( $post_2_insert ), $post_2_items );
		$page_items = wp_get_associated_nav_menu_items( $page_id );
		$this->assertSameSets( array( $page_insert ), $page_items );

		wp_delete_term( $tag_id, 'post_tag' );
		$tag_items = wp_get_associated_nav_menu_items( $tag_id, 'taxonomy', 'post_tag' );
		$this->assertSameSets( array(), $tag_items );

		wp_delete_term( $cat_id, 'category' );
		$cat_items = wp_get_associated_nav_menu_items( $cat_id, 'taxonomy', 'category' );
		$this->assertSameSets( array(), $cat_items );

		wp_delete_post( $post_id, true );
		$post_items = wp_get_associated_nav_menu_items( $post_id );
		$this->assertSameSets( array(), $post_items );

		wp_delete_post( $post_2_id, true );
		$post_2_items = wp_get_associated_nav_menu_items( $post_2_id );
		$this->assertSameSets( array(), $post_2_items );

		wp_delete_post( $page_id, true );
		$page_items = wp_get_associated_nav_menu_items( $page_id );
		$this->assertSameSets( array(), $page_items );
	}

	/**
	 * @ticket 27113
	 */
	public function test_orphan_nav_menu_item() {

		// Create an orphan nav menu item.
		$custom_item_id = wp_update_nav_menu_item(
			0,
			0,
			array(
				'menu-item-type'   => 'custom',
				'menu-item-title'  => 'Wordpress.org',
				'menu-item-url'    => 'http://wordpress.org',
				'menu-item-status' => 'publish',
			)
		);

		// Confirm it saved properly.
		$custom_item = wp_setup_nav_menu_item( get_post( $custom_item_id ) );
		$this->assertSame( 'Wordpress.org', $custom_item->title );

		// Update the orphan with an associated nav menu.
		wp_update_nav_menu_item(
			$this->menu_id,
			$custom_item_id,
			array(
				'menu-item-title' => 'WordPress.org',
			)
		);
		$menu_items  = wp_get_nav_menu_items( $this->menu_id );
		$custom_item = wp_filter_object_list( $menu_items, array( 'db_id' => $custom_item_id ) );
		$custom_item = array_pop( $custom_item );
		$this->assertSame( 'WordPress.org', $custom_item->title );

	}

	public function test_wp_get_nav_menu_items_with_taxonomy_term() {
		register_taxonomy( 'wptests_tax', 'post', array( 'hierarchical' => true ) );
		$t           = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		$child_terms = self::factory()->term->create_many(
			2,
			array(
				'taxonomy' => 'wptests_tax',
				'parent'   => $t,
			)
		);

		$term_menu_item = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'wptests_tax',
				'menu-item-object-id' => $t,
				'menu-item-status'    => 'publish',
			)
		);

		$term = get_term( $t, 'wptests_tax' );

		$menu_items = wp_get_nav_menu_items( $this->menu_id );
		$this->assertSame( $term->name, $menu_items[0]->title );
		$this->assertEquals( $t, $menu_items[0]->object_id );
	}

	/**
	 * @ticket 55620
	 * @covers ::update_menu_item_cache
	 */
	public function test_update_menu_item_cache_primes_posts() {
		$post_id = self::factory()->post->create();
		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
			)
		);

		$posts_query  = new WP_Query();
		$query_result = $posts_query->query( array( 'post_type' => 'nav_menu_item' ) );

		wp_cache_delete( $post_id, 'posts' );
		$action = new MockAction();
		add_filter( 'update_post_metadata_cache', array( $action, 'filter' ), 10, 2 );

		update_menu_item_cache( $query_result );

		$args = $action->get_args();
		$last = end( $args );
		$this->assertSameSets( array( $post_id ), $last[1], '_prime_post_caches() was not executed.' );
	}

	/**
	 * @ticket 55620
	 * @covers ::update_menu_item_cache
	 */
	public function test_update_menu_item_cache_primes_terms() {
		register_taxonomy( 'wptests_tax', 'post', array( 'hierarchical' => true ) );
		$term_id = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'wptests_tax',
				'menu-item-object-id' => $term_id,
				'menu-item-status'    => 'publish',
			)
		);

		$posts_query  = new WP_Query();
		$query_result = $posts_query->query( array( 'post_type' => 'nav_menu_item' ) );

		wp_cache_delete( $term_id, 'terms' );
		$action = new MockAction();
		add_filter( 'update_term_metadata_cache', array( $action, 'filter' ), 10, 2 );

		update_menu_item_cache( $query_result );

		$args = $action->get_args();
		$last = end( $args );
		$this->assertSameSets( array( $term_id ), $last[1], '_prime_term_caches() was not executed.' );
	}


	/**
	 * @ticket 55620
	 * @covers ::update_menu_item_cache
	 */
	public function test_wp_get_nav_menu_items_cache_primes_posts() {
		$post_ids     = self::factory()->post->create_many( 3 );
		$menu_nav_ids = array();
		foreach ( $post_ids as $post_id ) {
			$menu_nav_ids[] = wp_update_nav_menu_item(
				$this->menu_id,
				0,
				array(
					'menu-item-type'      => 'post_type',
					'menu-item-object'    => 'post',
					'menu-item-object-id' => $post_id,
					'menu-item-status'    => 'publish',
				)
			);
		}

		// Delete post and post meta caches.
		wp_cache_delete_multiple( $menu_nav_ids, 'posts' );
		wp_cache_delete_multiple( $menu_nav_ids, 'post_meta' );
		wp_cache_delete_multiple( $post_ids, 'posts' );
		wp_cache_delete_multiple( $post_ids, 'post_meta' );

		$action = new MockAction();
		add_filter( 'update_post_metadata_cache', array( $action, 'filter' ), 10, 2 );

		$start_num_queries = get_num_queries();
		wp_get_nav_menu_items( $this->menu_id );
		$queries_made = get_num_queries() - $start_num_queries;
		$this->assertSame( 6, $queries_made, 'Only does 6 database queries when running wp_get_nav_menu_items.' );

		$args = $action->get_args();
		$this->assertSameSets( $menu_nav_ids, $args[0][1], '_prime_post_caches() was not executed.' );
		$this->assertSameSets( $post_ids, $args[1][1], '_prime_post_caches() was not executed.' );
	}

	/**
	 * @ticket 55620
	 * @covers ::update_menu_item_cache
	 */
	public function test_wp_get_nav_menu_items_cache_primes_terms() {
		register_taxonomy( 'wptests_tax', 'post', array( 'hierarchical' => true ) );
		$term_ids     = self::factory()->term->create_many( 3, array( 'taxonomy' => 'wptests_tax' ) );
		$menu_nav_ids = array();
		foreach ( $term_ids as $term_id ) {
			$menu_nav_ids[] = wp_update_nav_menu_item(
				$this->menu_id,
				0,
				array(
					'menu-item-type'      => 'taxonomy',
					'menu-item-object'    => 'wptests_tax',
					'menu-item-object-id' => $term_id,
					'menu-item-status'    => 'publish',
				)
			);
		}
		// Delete post and post meta caches.
		wp_cache_delete_multiple( $menu_nav_ids, 'posts' );
		wp_cache_delete_multiple( $menu_nav_ids, 'post_meta' );
		// Delete term caches.
		wp_cache_delete_multiple( $term_ids, 'terms' );
		$action_terms = new MockAction();
		add_filter( 'update_term_metadata_cache', array( $action_terms, 'filter' ), 10, 2 );

		$action_posts = new MockAction();
		add_filter( 'update_post_metadata_cache', array( $action_posts, 'filter' ), 10, 2 );

		$start_num_queries = get_num_queries();
		wp_get_nav_menu_items( $this->menu_id );
		$queries_made = get_num_queries() - $start_num_queries;
		$this->assertSame( 6, $queries_made, 'Only does 6 database queries when running wp_get_nav_menu_items.' );

		$args = $action_terms->get_args();
		$last = end( $args );
		$this->assertSameSets( $term_ids, $last[1], '_prime_term_caches() was not executed.' );

		$args = $action_posts->get_args();
		$this->assertSameSets( $menu_nav_ids, $args[0][1], '_prime_post_caches() was not executed.' );
	}

	/**
	 * @ticket 13910
	 */
	public function test_wp_get_nav_menu_name() {
		// Register a nav menu location.
		register_nav_menu( 'primary', 'Primary Navigation' );

		// Create a menu with a title.
		$menu = wp_create_nav_menu( 'My Menu' );

		// Assign the menu to the `primary` location.
		$locations            = get_nav_menu_locations();
		$menu_obj             = wp_get_nav_menu_object( $menu );
		$locations['primary'] = $menu_obj->term_id;
		set_theme_mod( 'nav_menu_locations', $locations );

		$this->assertSame( 'My Menu', wp_get_nav_menu_name( 'primary' ) );
	}

	/**
	 * @ticket 29460
	 */
	public function test_orderby_name_by_default() {
		// We are going to create a random number of menus (min 2, max 10).
		$menus_no = rand( 2, 10 );

		for ( $i = 0; $i <= $menus_no; $i++ ) {
			wp_create_nav_menu( rand_str() );
		}

		// This is the expected array of menu names.
		$expected_nav_menus_names = wp_list_pluck(
			get_terms(
				'nav_menu',
				array(
					'hide_empty' => false,
					'orderby'    => 'name',
				)
			),
			'name'
		);

		// And this is what we got when calling wp_get_nav_menus().
		$nav_menus_names = wp_list_pluck( wp_get_nav_menus(), 'name' );

		$this->assertSame( $expected_nav_menus_names, $nav_menus_names );
	}

	/**
	 * @ticket 35324
	 */
	public function test_wp_setup_nav_menu_item_for_post_type_archive() {

		$post_type_slug        = 'fooo-bar-baz';
		$post_type_description = 'foo';
		register_post_type(
			$post_type_slug,
			array(
				'public'      => true,
				'has_archive' => true,
				'description' => $post_type_description,
				'label'       => $post_type_slug,
			)
		);

		$post_type_archive_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'        => 'post_type_archive',
				'menu-item-object'      => $post_type_slug,
				'menu-item-description' => $post_type_description,
				'menu-item-status'      => 'publish',
			)
		);
		$post_type_archive_item    = wp_setup_nav_menu_item( get_post( $post_type_archive_item_id ) );

		$this->assertSame( $post_type_slug, $post_type_archive_item->title );
		$this->assertSame( $post_type_description, $post_type_archive_item->description );
	}

	/**
	 * @ticket 35324
	 */
	public function test_wp_setup_nav_menu_item_for_post_type_archive_no_description() {

		$post_type_slug        = 'fooo-bar-baz';
		$post_type_description = '';
		register_post_type(
			$post_type_slug,
			array(
				'public'      => true,
				'has_archive' => true,
				'label'       => $post_type_slug,
			)
		);

		$post_type_archive_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'   => 'post_type_archive',
				'menu-item-object' => $post_type_slug,
				'menu-item-status' => 'publish',
			)
		);
		$post_type_archive_item    = wp_setup_nav_menu_item( get_post( $post_type_archive_item_id ) );

		$this->assertSame( $post_type_slug, $post_type_archive_item->title );
		$this->assertSame( $post_type_description, $post_type_archive_item->description ); // Fail!
	}

	/**
	 * @ticket 35324
	 */
	public function test_wp_setup_nav_menu_item_for_post_type_archive_custom_description() {

		$post_type_slug        = 'fooo-bar-baz';
		$post_type_description = 'foobaz';
		register_post_type(
			$post_type_slug,
			array(
				'public'      => true,
				'has_archive' => true,
				'description' => $post_type_description,
				'label'       => $post_type_slug,
			)
		);

		$menu_item_description = 'foo_description';

		$post_type_archive_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'        => 'post_type_archive',
				'menu-item-object'      => $post_type_slug,
				'menu-item-description' => $menu_item_description,
				'menu-item-status'      => 'publish',
			)
		);
		$post_type_archive_item    = wp_setup_nav_menu_item( get_post( $post_type_archive_item_id ) );

		$this->assertSame( $post_type_slug, $post_type_archive_item->title );
		$this->assertSame( $menu_item_description, $post_type_archive_item->description );
	}

	/**
	 * @ticket 35324
	 */
	public function test_wp_setup_nav_menu_item_for_unknown_post_type_archive_no_description() {

		$post_type_slug = 'fooo-bar-baz';

		$post_type_archive_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'   => 'post_type_archive',
				'menu-item-object' => $post_type_slug,
				'menu-item-status' => 'publish',
			)
		);
		$post_type_archive_item    = wp_setup_nav_menu_item( get_post( $post_type_archive_item_id ) );

		$this->assertEmpty( $post_type_archive_item->description );
	}

	/**
	 * @ticket 19038
	 */
	public function test_wp_setup_nav_menu_item_for_trashed_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'trash',
			)
		);

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
			)
		);

		$menu_item = wp_setup_nav_menu_item( get_post( $menu_item_id ) );

		$this->assertTrue( ! _is_valid_nav_menu_item( $menu_item ) );
	}

	/**
	 * @ticket 35206
	 */
	public function test_wp_nav_menu_whitespace_options() {
		$post_id1 = self::factory()->post->create();
		$post_id2 = self::factory()->post->create();
		$post_id3 = self::factory()->post->create();
		$post_id4 = self::factory()->post->create();

		$post_insert = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id1,
				'menu-item-status'    => 'publish',
			)
		);

		$post_insert2 = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id2,
				'menu-item-status'    => 'publish',
			)
		);

		$post_insert3 = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-parent-id' => $post_insert,
				'menu-item-object-id' => $post_id3,
				'menu-item-status'    => 'publish',
			)
		);

		$post_insert4 = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-parent-id' => $post_insert,
				'menu-item-object-id' => $post_id4,
				'menu-item-status'    => 'publish',
			)
		);

		// No whitespace suppression.
		$menu = wp_nav_menu(
			array(
				'echo' => false,
				'menu' => $this->menu_id,
			)
		);

		// The markup should include whitespace between <li>'s.
		$this->assertMatchesRegularExpression( '/\s<li.*>|<\/li>\s/U', $menu );
		$this->assertDoesNotMatchRegularExpression( '/<\/li><li.*>/U', $menu );

		// Whitespace suppressed.
		$menu = wp_nav_menu(
			array(
				'echo'         => false,
				'item_spacing' => 'discard',
				'menu'         => $this->menu_id,
			)
		);

		// The markup should not include whitespace around <li>'s.
		$this->assertDoesNotMatchRegularExpression( '/\s<li.*>|<\/li>\s/U', $menu );
		$this->assertMatchesRegularExpression( '/><li.*>|<\/li></U', $menu );
	}

	/*
	 * Confirm `wp_nav_menu()` and `Walker_Nav_Menu` passes an $args object to filters.
	 *
	 * `wp_nav_menu()` is unique in that it uses an $args object rather than an array.
	 * This has been the case for some time and should be maintained for reasons of
	 * backward compatibility.
	 *
	 * @ticket 24587
	 */
	public function test_wp_nav_menu_filters_are_passed_args_object() {
		$tag_id = self::factory()->tag->create();

		$tag_insert = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'post_tag',
				'menu-item-object-id' => $tag_id,
				'menu-item-status'    => 'publish',
			)
		);

		/*
		 * The tests take place in a range of filters to ensure the passed
		 * arguments are an object.
		 */
		// In function.
		add_filter( 'pre_wp_nav_menu', array( $this, 'confirm_second_param_args_object' ), 10, 2 );
		add_filter( 'wp_nav_menu_objects', array( $this, 'confirm_second_param_args_object' ), 10, 2 );
		add_filter( 'wp_nav_menu_items', array( $this, 'confirm_second_param_args_object' ), 10, 2 );

		// In walker.
		add_filter( 'nav_menu_item_args', array( $this, 'confirm_nav_menu_item_args_object' ) );

		add_filter( 'nav_menu_css_class', array( $this, 'confirm_third_param_args_object' ), 10, 3 );
		add_filter( 'nav_menu_item_id', array( $this, 'confirm_third_param_args_object' ), 10, 3 );
		add_filter( 'nav_menu_link_attributes', array( $this, 'confirm_third_param_args_object' ), 10, 3 );
		add_filter( 'nav_menu_item_title', array( $this, 'confirm_third_param_args_object' ), 10, 3 );

		add_filter( 'walker_nav_menu_start_el', array( $this, 'confirm_forth_param_args_object' ), 10, 4 );

		wp_nav_menu(
			array(
				'echo' => false,
				'menu' => $this->menu_id,
			)
		);
		wp_delete_term( $tag_id, 'post_tag' );

		/*
		 * Remove test filters.
		 */
		// In function.
		remove_filter( 'pre_wp_nav_menu', array( $this, 'confirm_second_param_args_object' ), 10, 2 );
		remove_filter( 'wp_nav_menu_objects', array( $this, 'confirm_second_param_args_object' ), 10, 2 );
		remove_filter( 'wp_nav_menu_items', array( $this, 'confirm_second_param_args_object' ), 10, 2 );

		// In walker.
		remove_filter( 'nav_menu_item_args', array( $this, 'confirm_nav_menu_item_args_object' ) );

		remove_filter( 'nav_menu_css_class', array( $this, 'confirm_third_param_args_object' ), 10, 3 );
		remove_filter( 'nav_menu_item_id', array( $this, 'confirm_third_param_args_object' ), 10, 3 );
		remove_filter( 'nav_menu_link_attributes', array( $this, 'confirm_third_param_args_object' ), 10, 3 );
		remove_filter( 'nav_menu_item_title', array( $this, 'confirm_third_param_args_object' ), 10, 3 );

		remove_filter( 'walker_nav_menu_start_el', array( $this, 'confirm_forth_param_args_object' ), 10, 4 );

	}

	/**
	 * Run tests required to confrim Walker_Nav_Menu receives an $args object.
	 */
	public function confirm_nav_menu_item_args_object( $args ) {
		$this->assertIsObject( $args );
		return $args;
	}

	public function confirm_second_param_args_object( $ignored_1, $args ) {
		$this->assertIsObject( $args );
		return $ignored_1;
	}

	public function confirm_third_param_args_object( $ignored_1, $ignored_2, $args ) {
		$this->assertIsObject( $args );
		return $ignored_1;
	}

	public function confirm_forth_param_args_object( $ignored_1, $ignored_2, $ignored_3, $args ) {
		$this->assertIsObject( $args );
		return $ignored_1;
	}

	/**
	 * @ticket 35272
	 */
	public function test_no_front_page_class_applied() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Home Page',
			)
		);

		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-status'    => 'publish',
			)
		);

		$menu_items = wp_get_nav_menu_items( $this->menu_id );
		_wp_menu_item_classes_by_context( $menu_items );

		$classes = $menu_items[0]->classes;

		$this->assertNotContains( 'menu-item-home', $classes );
	}


	/**
	 * @ticket 35272
	 */
	public function test_class_applied_to_front_page_item() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Home Page',
			)
		);
		update_option( 'page_on_front', $page_id );

		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-status'    => 'publish',
			)
		);

		$menu_items = wp_get_nav_menu_items( $this->menu_id );
		_wp_menu_item_classes_by_context( $menu_items );

		$classes = $menu_items[0]->classes;

		delete_option( 'page_on_front' );

		$this->assertContains( 'menu-item-home', $classes );
	}

	/**
	 * @ticket 35272
	 */
	public function test_class_not_applied_to_taxonomies_with_same_id_as_front_page_item() {
		global $wpdb;

		$new_id = 35272;

		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Home Page',
			)
		);
		$tag_id  = self::factory()->tag->create();

		$wpdb->update( $wpdb->posts, array( 'ID' => $new_id ), array( 'ID' => $page_id ) );
		$wpdb->update( $wpdb->terms, array( 'term_id' => $new_id ), array( 'term_id' => $tag_id ) );
		$wpdb->update( $wpdb->term_taxonomy, array( 'term_id' => $new_id ), array( 'term_id' => $tag_id ) );

		update_option( 'page_on_front', $new_id );

		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'post_tag',
				'menu-item-object-id' => $new_id,
				'menu-item-status'    => 'publish',
			)
		);

		$menu_items = wp_get_nav_menu_items( $this->menu_id );
		_wp_menu_item_classes_by_context( $menu_items );

		$classes = $menu_items[0]->classes;

		$this->assertNotContains( 'menu-item-home', $classes );
	}

	/**
	 * Test _wp_delete_customize_changeset_dependent_auto_drafts.
	 *
	 * @covers ::_wp_delete_customize_changeset_dependent_auto_drafts
	 */
	public function test_wp_delete_customize_changeset_dependent_auto_drafts() {
		$auto_draft_post_id = self::factory()->post->create(
			array(
				'post_status' => 'auto-draft',
			)
		);
		$draft_post_id      = self::factory()->post->create(
			array(
				'post_status' => 'draft',
			)
		);
		$private_post_id    = self::factory()->post->create(
			array(
				'post_status' => 'private',
			)
		);

		$nav_created_post_ids = array(
			$auto_draft_post_id,
			$draft_post_id,
			$private_post_id,
		);
		$data                 = array(
			'nav_menus_created_posts' => array(
				'value' => $nav_created_post_ids,
			),
		);
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'administrator',
				)
			)
		);
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		$wp_customize = new WP_Customize_Manager();
		do_action( 'customize_register', $wp_customize );
		$wp_customize->save_changeset_post(
			array(
				'data' => $data,
			)
		);
		$this->assertSame( 'auto-draft', get_post_status( $auto_draft_post_id ) );
		$this->assertSame( 'draft', get_post_status( $draft_post_id ) );
		$this->assertSame( 'private', get_post_status( $private_post_id ) );
		wp_delete_post( $wp_customize->changeset_post_id(), true );
		$this->assertFalse( get_post_status( $auto_draft_post_id ) );
		$this->assertSame( 'trash', get_post_status( $draft_post_id ) );
		$this->assertSame( 'private', get_post_status( $private_post_id ) );
	}

	/**
	 * @ticket 39800
	 */
	public function test_parent_ancestor_for_post_archive() {

		register_post_type(
			'books',
			array(
				'label'       => 'Books',
				'public'      => true,
				'has_archive' => true,
			)
		);

		$first_page_id  = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Top Level Page',
			)
		);
		$second_page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Second Level Page',
			)
		);

		$first_menu_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $first_page_id,
				'menu-item-status'    => 'publish',
			)
		);

		$second_menu_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $second_page_id,
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $first_menu_id,
			)
		);

		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type_archive',
				'menu-item-object'    => 'books',
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $second_menu_id,
			)
		);

		$this->go_to( get_post_type_archive_link( 'books' ) );

		$menu_items = wp_get_nav_menu_items( $this->menu_id );
		_wp_menu_item_classes_by_context( $menu_items );

		$top_page_menu_item       = $menu_items[0];
		$secondary_page_menu_item = $menu_items[1];
		$post_archive_menu_item   = $menu_items[2];

		$this->assertFalse( $top_page_menu_item->current_item_parent );
		$this->assertTrue( $top_page_menu_item->current_item_ancestor );
		$this->assertContains( 'current-menu-ancestor', $top_page_menu_item->classes );

		$this->assertTrue( $secondary_page_menu_item->current_item_parent );
		$this->assertTrue( $secondary_page_menu_item->current_item_ancestor );
		$this->assertContains( 'current-menu-parent', $secondary_page_menu_item->classes );
		$this->assertContains( 'current-menu-ancestor', $secondary_page_menu_item->classes );

		$this->assertFalse( $post_archive_menu_item->current_item_parent );
		$this->assertFalse( $post_archive_menu_item->current_item_ancestor );

		$this->assertNotContains( 'current-menu-parent', $post_archive_menu_item->classes );
		$this->assertNotContains( 'current-menu-ancestor', $post_archive_menu_item->classes );
	}

	/**
	 * @ticket 43401
	 * @dataProvider data_iri_current_menu_item
	 */
	public function test_iri_current_menu_item( $custom_link, $current = true ) {
		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
				'menu-item-url'    => $custom_link,
			)
		);

		$this->go_to( site_url( '/%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82/' ) );

		$menu_items = wp_get_nav_menu_items( $this->menu_id );
		_wp_menu_item_classes_by_context( $menu_items );

		$classes = $menu_items[0]->classes;

		if ( $current ) {
			$this->assertContains( 'current-menu-item', $classes );
		} else {
			$this->assertNotContains( 'current-menu-item', $classes );
		}
	}

	/**
	 * Provides IRI matching data for _wp_menu_item_classes_by_context() test.
	 */
	public function data_iri_current_menu_item() {
		return array(
			array( site_url( '/%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82/' ) ),
			array( site_url( '/%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82' ) ),
			array( '/%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82/' ),
			array( '/%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82' ),
			array( '/привет/' ),
			array( '/привет' ),
		);
	}

	/**
	 * @ticket 44005
	 * @group privacy
	 */
	public function test_no_privacy_policy_class_applied() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Privacy Policy Page',
			)
		);

		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-status'    => 'publish',
			)
		);

		$menu_items = wp_get_nav_menu_items( $this->menu_id );
		_wp_menu_item_classes_by_context( $menu_items );

		$classes = $menu_items[0]->classes;

		$this->assertNotContains( 'menu-item-privacy-policy', $classes );
	}

	/**
	 * @ticket 44005
	 * @group privacy
	 */
	public function test_class_applied_to_privacy_policy_page_item() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Privacy Policy Page',
			)
		);
		update_option( 'wp_page_for_privacy_policy', $page_id );

		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-status'    => 'publish',
			)
		);

		$menu_items = wp_get_nav_menu_items( $this->menu_id );
		_wp_menu_item_classes_by_context( $menu_items );

		$classes = $menu_items[0]->classes;

		delete_option( 'wp_page_for_privacy_policy' );

		$this->assertContains( 'menu-item-privacy-policy', $classes );
	}

	/**
	 * @ticket 47723
	 * @dataProvider data_trim_url_for_custom_item
	 */
	public function test_trim_url_for_custom_item( $custom_url, $correct_url ) {
		$custom_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'   => 'custom',
				'menu-item-title'  => 'WordPress.org',
				'menu-item-url'    => $custom_url,
				'menu-item-status' => 'publish',
			)
		);

		$custom_item = wp_setup_nav_menu_item( get_post( $custom_item_id ) );
		$this->assertSame( $correct_url, $custom_item->url );
	}

	/**
	 * Provides data for test_trim_url_for_custom_item().
	 */
	public function data_trim_url_for_custom_item() {
		return array(
			array( 'https://wordpress.org ', 'https://wordpress.org' ),
			array( ' https://wordpress.org', 'https://wordpress.org' ),
		);
	}

	/**
	 * Tests `wp_update_nav_menu_item()` with special characters in a category name.
	 *
	 * When inserting a category as a nav item, the `post_title` property should
	 * be empty, as the item should get the title from the category object itself.
	 *
	 * @ticket 48011
	 */
	public function test_wp_update_nav_menu_item_with_special_characters_in_category_name() {
		$category_name = 'Test Cat - \"Pre-Slashed\" Cat Name & >';

		$category = self::factory()->category->create_and_get(
			array(
				'name' => $category_name,
			)
		);

		$category_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'category',
				'menu-item-object-id' => $category->term_id,
				'menu-item-status'    => 'publish',
				/*
				 * Interestingly enough, if we use `$cat->name` for the menu item title,
				 * we won't be able to replicate the bug because it's in htmlentities form.
				 */
				'menu-item-title'     => $category_name,
			)
		);

		$category_item = get_post( $category_item_id );
		$this->assertEmpty( $category_item->post_title );
	}

	/**
	 * Test passed post_date/post_date_gmt.
	 *
	 * When inserting a nav menu item, it should be possible to set the post_date
	 * of it to ensure that this data is maintained during an import.
	 *
	 * @ticket 52189
	 */
	public function test_wp_update_nav_menu_item_with_post_date() {
		$post_date     = '2020-12-28 11:26:35';
		$post_date_gmt = '2020-12-29 10:11:45';
		$invalid_date  = '2020-12-41 14:15:27';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
			)
		);
		$post         = get_post( $menu_item_id );
		$this->assertEqualsWithDelta( strtotime( gmdate( 'Y-m-d H:i:s' ) ), strtotime( $post->post_date ), 2, 'The dates should be equal' );

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'          => 'post_type',
				'menu-item-object'        => 'post',
				'menu-item-object-id'     => $post_id,
				'menu-item-status'        => 'publish',
				'menu-item-post-date-gmt' => $post_date_gmt,
			)
		);
		$post         = get_post( $menu_item_id );
		$this->assertSame( get_date_from_gmt( $post_date_gmt ), $post->post_date );

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'          => 'post_type',
				'menu-item-object'        => 'post',
				'menu-item-object-id'     => $post_id,
				'menu-item-status'        => 'publish',
				'menu-item-post-date-gmt' => $invalid_date,
			)
		);
		$post         = get_post( $menu_item_id );
		$this->assertSame( '1970-01-01 00:00:00', $post->post_date );

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
				'menu-item-post-date' => $post_date,
			)
		);
		$post         = get_post( $menu_item_id );
		$this->assertSame( $post_date, $post->post_date );

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'          => 'post_type',
				'menu-item-object'        => 'post',
				'menu-item-object-id'     => $post_id,
				'menu-item-status'        => 'publish',
				'menu-item-post-date'     => $post_date,
				'menu-item-post-date-gmt' => $post_date_gmt,
			)
		);
		$post         = get_post( $menu_item_id );
		$this->assertSame( $post_date, $post->post_date );

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'          => 'post_type',
				'menu-item-object'        => 'post',
				'menu-item-object-id'     => $post_id,
				'menu-item-status'        => 'publish',
				'menu-item-post-date'     => $post_date,
				'menu-item-post-date-gmt' => $invalid_date,
			)
		);
		$post         = get_post( $menu_item_id );
		$this->assertSame( $post_date, $post->post_date );

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
				'menu-item-post-date' => $invalid_date,
			)
		);
		$post         = get_post( $menu_item_id );
		$this->assertEqualsWithDelta( strtotime( gmdate( 'Y-m-d H:i:s' ) ), strtotime( $post->post_date ), 2, 'The dates should be equal' );

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'          => 'post_type',
				'menu-item-object'        => 'post',
				'menu-item-object-id'     => $post_id,
				'menu-item-status'        => 'publish',
				'menu-item-post-date'     => $invalid_date,
				'menu-item-post-date-gmt' => $post_date_gmt,
			)
		);
		$post         = get_post( $menu_item_id );
		$this->assertEqualsWithDelta( strtotime( gmdate( 'Y-m-d H:i:s' ) ), strtotime( $post->post_date ), 2, 'The dates should be equal' );

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'          => 'post_type',
				'menu-item-object'        => 'post',
				'menu-item-object-id'     => $post_id,
				'menu-item-status'        => 'publish',
				'menu-item-post-date'     => $invalid_date,
				'menu-item-post-date-gmt' => $invalid_date,
			)
		);
		$post         = get_post( $menu_item_id );
		$this->assertEqualsWithDelta( strtotime( gmdate( 'Y-m-d H:i:s' ) ), strtotime( $post->post_date ), 2, 'The dates should be equal' );
	}
}
