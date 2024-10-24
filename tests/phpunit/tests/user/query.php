<?php
/**
 * Test WP_User_Query, in wp-includes/class-wp-user-query.php.
 *
 * @group user
 */
class Tests_User_Query extends WP_UnitTestCase {
	protected static $author_ids;
	protected static $sub_ids;
	protected static $editor_ids;
	protected static $contrib_id;
	protected static $admin_ids;

	protected $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_ids = $factory->user->create_many(
			4,
			array(
				'role' => 'author',
			)
		);

		self::$sub_ids = $factory->user->create_many(
			2,
			array(
				'role' => 'subscriber',
			)
		);

		self::$editor_ids = $factory->user->create_many(
			3,
			array(
				'role' => 'editor',
			)
		);

		self::$contrib_id = $factory->user->create(
			array(
				'role' => 'contributor',
			)
		);

		self::$admin_ids = $factory->user->create_many(
			2,
			array(
				'role' => 'administrator',
			)
		);
	}

	public function test_get_and_set() {
		$users = new WP_User_Query();

		$this->assertNull( $users->get( 'fields' ) );
		if ( isset( $users->query_vars['fields'] ) ) {
			$this->assertSame( '', $users->query_vars['fields'] );
		}

		$users->set( 'fields', 'all' );

		$this->assertSame( 'all', $users->get( 'fields' ) );
		$this->assertSame( 'all', $users->query_vars['fields'] );

		$users->set( 'fields', '' );
		$this->assertSame( '', $users->get( 'fields' ) );
		$this->assertSame( '', $users->query_vars['fields'] );

		$this->assertNull( $users->get( 'does-not-exist' ) );
	}

	public function test_include_single() {
		$q   = new WP_User_Query(
			array(
				'fields'  => '',
				'include' => self::$author_ids[0],
			)
		);
		$ids = $q->get_results();

		$this->assertEqualSets( array( self::$author_ids[0] ), $ids );
	}

	public function test_include_comma_separated() {
		$q   = new WP_User_Query(
			array(
				'fields'  => '',
				'include' => self::$author_ids[0] . ', ' . self::$author_ids[2],
			)
		);
		$ids = $q->get_results();

		$this->assertEqualSets( array( self::$author_ids[0], self::$author_ids[2] ), $ids );
	}

	public function test_include_array() {
		$q   = new WP_User_Query(
			array(
				'fields'  => '',
				'include' => array( self::$author_ids[0], self::$author_ids[2] ),
			)
		);
		$ids = $q->get_results();

		$this->assertEqualSets( array( self::$author_ids[0], self::$author_ids[2] ), $ids );
	}

	public function test_include_array_bad_values() {
		$q   = new WP_User_Query(
			array(
				'fields'  => '',
				'include' => array( self::$author_ids[0], 'foo', self::$author_ids[2] ),
			)
		);
		$ids = $q->get_results();

		$this->assertEqualSets( array( self::$author_ids[0], self::$author_ids[2] ), $ids );
	}

	public function test_exclude() {
		$q = new WP_User_Query(
			array(
				'fields'  => '',
				'exclude' => self::$author_ids[1],
			)
		);

		$ids = $q->get_results();

		// Indirect test in order to ignore default user created during installation.
		$this->assertNotEmpty( $ids );
		$this->assertNotContains( self::$author_ids[1], $ids );
	}

	public function test_get_all() {
		$users = new WP_User_Query( array( 'blog_id' => get_current_blog_id() ) );
		$users = $users->get_results();

		// +1 for the default user created during installation.
		$this->assertCount( 13, $users );
		foreach ( $users as $user ) {
			$this->assertInstanceOf( 'WP_User', $user );
		}

		$users = new WP_User_Query(
			array(
				'blog_id' => get_current_blog_id(),
				'fields'  => 'all_with_meta',
			)
		);
		$users = $users->get_results();
		$this->assertCount( 13, $users );
		foreach ( $users as $user ) {
			$this->assertInstanceOf( 'WP_User', $user );
		}
	}

	/**
	 * @ticket 55594
	 */
	public function test_get_all_primed_users() {
		$this->reset_lazyload_queue();
		$filter = new MockAction();
		add_filter( 'update_user_metadata_cache', array( $filter, 'filter' ), 10, 2 );

		$query = new WP_User_Query(
			array(
				'include' => self::$author_ids,
				'fields'  => 'all',
			)
		);

		$users = $query->get_results();
		foreach ( $users as $user ) {
			$user->roles;
		}

		$args      = $filter->get_args();
		$last_args = end( $args );
		$this->assertIsArray( $last_args[1] );
		$this->assertSameSets( self::$author_ids, $last_args[1], 'Ensure that user meta is primed' );
	}

	/**
	 * @ticket 39297
	 */
	public function test_get_total_is_int() {
		$users       = new WP_User_Query( array( 'blog_id' => get_current_blog_id() ) );
		$total_users = $users->get_total();

		$this->assertSame( 13, $total_users );
	}

	/**
	 * @dataProvider data_orderby_should_convert_non_prefixed_keys
	 */
	public function test_orderby_should_convert_non_prefixed_keys( $short_key, $full_key ) {
		$q = new WP_User_Query(
			array(
				'orderby' => $short_key,
			)
		);

		$this->assertStringContainsString( "ORDER BY $full_key", $q->query_orderby );
	}

	public function data_orderby_should_convert_non_prefixed_keys() {
		return array(
			array( 'nicename', 'user_nicename' ),
			array( 'email', 'user_email' ),
			array( 'url', 'user_url' ),
			array( 'registered', 'user_registered' ),
			array( 'name', 'display_name' ),
		);
	}

	public function test_orderby_meta_value() {
		update_user_meta( self::$author_ids[0], 'last_name', 'Jones' );
		update_user_meta( self::$author_ids[1], 'last_name', 'Albert' );
		update_user_meta( self::$author_ids[2], 'last_name', 'Zorro' );

		$q = new WP_User_Query(
			array(
				'include'  => self::$author_ids,
				'meta_key' => 'last_name',
				'orderby'  => 'meta_value',
				'fields'   => 'ID',
			)
		);

		$expected = array( self::$author_ids[3], self::$author_ids[1], self::$author_ids[0], self::$author_ids[2] );

		$this->assertEquals( $expected, $q->get_results() );
	}

	/**
	 * @ticket 27887
	 */
	public function test_orderby_meta_value_num() {
		update_user_meta( self::$author_ids[0], 'user_age', '101' );
		update_user_meta( self::$author_ids[1], 'user_age', '20' );
		update_user_meta( self::$author_ids[2], 'user_age', '25' );

		$q = new WP_User_Query(
			array(
				'include'  => self::$author_ids,
				'meta_key' => 'user_age',
				'orderby'  => 'meta_value_num',
				'fields'   => 'ID',
			)
		);

		$expected = array( self::$author_ids[1], self::$author_ids[2], self::$author_ids[0] );

		$this->assertEquals( $expected, $q->get_results() );
	}

	/**
	 * @ticket 31265
	 */
	public function test_orderby_somekey_where_meta_key_is_somekey() {
		update_user_meta( self::$author_ids[0], 'foo', 'zzz' );
		update_user_meta( self::$author_ids[1], 'foo', 'aaa' );
		update_user_meta( self::$author_ids[2], 'foo', 'jjj' );

		$q = new WP_User_Query(
			array(
				'include'  => self::$author_ids,
				'meta_key' => 'foo',
				'orderby'  => 'foo',
				'fields'   => 'ID',
			)
		);

		$expected = array( self::$author_ids[1], self::$author_ids[2], self::$author_ids[0] );

		$this->assertEquals( $expected, $q->get_results() );
	}

	/**
	 * @ticket 31265
	 */
	public function test_orderby_clause_key() {
		add_user_meta( self::$author_ids[0], 'foo', 'aaa' );
		add_user_meta( self::$author_ids[1], 'foo', 'zzz' );
		add_user_meta( self::$author_ids[2], 'foo', 'jjj' );

		$q = new WP_User_Query(
			array(
				'fields'     => 'ID',
				'meta_query' => array(
					'foo_key' => array(
						'key'     => 'foo',
						'compare' => 'EXISTS',
					),
				),
				'orderby'    => 'foo_key',
				'order'      => 'DESC',
			)
		);

		$this->assertEquals( array( self::$author_ids[1], self::$author_ids[2], self::$author_ids[0] ), $q->results );
	}

	/**
	 * @ticket 31265
	 */
	public function test_orderby_clause_key_as_secondary_sort() {
		$u1 = self::factory()->user->create(
			array(
				'user_registered' => '2015-01-28 03:00:00',
			)
		);
		$u2 = self::factory()->user->create(
			array(
				'user_registered' => '2015-01-28 05:00:00',
			)
		);
		$u3 = self::factory()->user->create(
			array(
				'user_registered' => '2015-01-28 03:00:00',
			)
		);

		add_user_meta( $u1, 'foo', 'jjj' );
		add_user_meta( $u2, 'foo', 'zzz' );
		add_user_meta( $u3, 'foo', 'aaa' );

		$q = new WP_User_Query(
			array(
				'fields'     => 'ID',
				'meta_query' => array(
					'foo_key' => array(
						'key'     => 'foo',
						'compare' => 'EXISTS',
					),
				),
				'orderby'    => array(
					'comment_date' => 'asc',
					'foo_key'      => 'asc',
				),
			)
		);

		$this->assertEquals( array( $u3, $u1, $u2 ), $q->results );
	}

	/**
	 * @ticket 31265
	 */
	public function test_orderby_more_than_one_clause_key() {
		add_user_meta( self::$author_ids[0], 'foo', 'jjj' );
		add_user_meta( self::$author_ids[1], 'foo', 'zzz' );
		add_user_meta( self::$author_ids[2], 'foo', 'jjj' );
		add_user_meta( self::$author_ids[0], 'bar', 'aaa' );
		add_user_meta( self::$author_ids[1], 'bar', 'ccc' );
		add_user_meta( self::$author_ids[2], 'bar', 'bbb' );

		$q = new WP_User_Query(
			array(
				'fields'     => 'ID',
				'meta_query' => array(
					'foo_key' => array(
						'key'     => 'foo',
						'compare' => 'EXISTS',
					),
					'bar_key' => array(
						'key'     => 'bar',
						'compare' => 'EXISTS',
					),
				),
				'orderby'    => array(
					'foo_key' => 'asc',
					'bar_key' => 'desc',
				),
			)
		);

		$this->assertEquals( array( self::$author_ids[2], self::$author_ids[0], self::$author_ids[1] ), $q->results );
	}

	/**
	 * @ticket 30064
	 */
	public function test_orderby_include_with_empty_include() {
		$q = new WP_User_Query(
			array(
				'orderby' => 'include',
			)
		);

		$this->assertStringContainsString( 'ORDER BY user_login', $q->query_orderby );
	}

	/**
	 * @ticket 30064
	 */
	public function test_orderby_include() {
		global $wpdb;

		$q = new WP_User_Query(
			array(
				'orderby' => 'include',
				'include' => array( self::$author_ids[1], self::$author_ids[0], self::$author_ids[3] ),
				'fields'  => '',
			)
		);

		$expected_orderby = 'ORDER BY FIELD( ' . $wpdb->users . '.ID, ' . self::$author_ids[1] . ',' . self::$author_ids[0] . ',' . self::$author_ids[3] . ' )';
		$this->assertStringContainsString( $expected_orderby, $q->query_orderby );

		// assertEquals() respects order but ignores type (get_results() returns numeric strings).
		$this->assertEquals( array( self::$author_ids[1], self::$author_ids[0], self::$author_ids[3] ), $q->get_results() );
	}

	/**
	 * @ticket 30064
	 */
	public function test_orderby_include_duplicate_values() {
		global $wpdb;

		$q = new WP_User_Query(
			array(
				'orderby' => 'include',
				'include' => array( self::$author_ids[1], self::$author_ids[0], self::$author_ids[1], self::$author_ids[3] ),
				'fields'  => '',
			)
		);

		$expected_orderby = 'ORDER BY FIELD( ' . $wpdb->users . '.ID, ' . self::$author_ids[1] . ',' . self::$author_ids[0] . ',' . self::$author_ids[3] . ' )';
		$this->assertStringContainsString( $expected_orderby, $q->query_orderby );

		// assertEquals() respects order but ignores type (get_results() returns numeric strings).
		$this->assertEquals( array( self::$author_ids[1], self::$author_ids[0], self::$author_ids[3] ), $q->get_results() );
	}

	/**
	 * @ticket 31265
	 */
	public function test_orderby_space_separated() {
		$q = new WP_User_Query(
			array(
				'orderby' => 'login nicename',
				'order'   => 'ASC',
			)
		);

		$this->assertStringContainsString( 'ORDER BY user_login ASC, user_nicename ASC', $q->query_orderby );
	}

	/**
	 * @ticket 31265
	 */
	public function test_orderby_flat_array() {
		$q = new WP_User_Query(
			array(
				'orderby' => array( 'login', 'nicename' ),
			)
		);

		$this->assertStringContainsString( 'ORDER BY user_login ASC, user_nicename ASC', $q->query_orderby );
	}

	/**
	 * @ticket 31265
	 */
	public function test_orderby_array_contains_invalid_item() {
		$q = new WP_User_Query(
			array(
				'orderby' => array( 'login', 'foo', 'nicename' ),
			)
		);

		$this->assertStringContainsString( 'ORDER BY user_login ASC, user_nicename ASC', $q->query_orderby );
	}

	/**
	 * @ticket 31265
	 */
	public function test_orderby_array_contains_all_invalid_items() {
		$q = new WP_User_Query(
			array(
				'orderby' => array( 'foo', 'bar', 'baz' ),
			)
		);

		$this->assertStringContainsString( 'ORDER BY user_login', $q->query_orderby );
	}

	/**
	 * @ticket 31265
	 */
	public function test_orderby_array() {
		$q = new WP_User_Query(
			array(
				'orderby' => array(
					'login'    => 'DESC',
					'nicename' => 'ASC',
					'email'    => 'DESC',
				),
			)
		);

		$this->assertStringContainsString( 'ORDER BY user_login DESC, user_nicename ASC, user_email DESC', $q->query_orderby );
	}

	/**
	 * @ticket 31265
	 */
	public function test_orderby_array_should_discard_invalid_columns() {
		$q = new WP_User_Query(
			array(
				'orderby' => array(
					'login' => 'DESC',
					'foo'   => 'ASC',
					'email' => 'ASC',
				),
			)
		);

		$this->assertStringContainsString( 'ORDER BY user_login DESC, user_email ASC', $q->query_orderby );
	}

	/**
	 * @ticket 28631
	 */
	public function test_number() {
		// +1 for the default user created by the test suite.
		$users = new WP_User_Query( array( 'blog_id' => get_current_blog_id() ) );
		$users = $users->get_results();
		$this->assertCount( 13, $users );

		$users = new WP_User_Query(
			array(
				'blog_id' => get_current_blog_id(),
				'number'  => 10,
			)
		);
		$users = $users->get_results();
		$this->assertCount( 10, $users );

		$users = new WP_User_Query(
			array(
				'blog_id' => get_current_blog_id(),
				'number'  => 2,
			)
		);
		$users = $users->get_results();
		$this->assertCount( 2, $users );

		$users = new WP_User_Query(
			array(
				'blog_id' => get_current_blog_id(),
				'number'  => -1,
			)
		);
		$users = $users->get_results();
		$this->assertCount( 13, $users );
	}

	/**
	 * @ticket 21119
	 */
	public function test_prepare_query() {
		$query = new WP_User_Query();
		$this->assertEmpty( $query->query_fields );
		$this->assertEmpty( $query->query_from );
		$this->assertEmpty( $query->query_limit );
		$this->assertEmpty( $query->query_orderby );
		$this->assertEmpty( $query->query_where );
		$this->assertEmpty( $query->query_vars );
		$_query_vars = $query->query_vars;

		$query->prepare_query();
		$this->assertNotEmpty( $query->query_fields );
		$this->assertNotEmpty( $query->query_from );
		$this->assertEmpty( $query->query_limit );
		$this->assertNotEmpty( $query->query_orderby );
		$this->assertNotEmpty( $query->query_where );
		$this->assertNotEmpty( $query->query_vars );
		$this->assertNotEquals( $_query_vars, $query->query_vars );

		// All values get reset.
		$query->prepare_query( array( 'number' => 8 ) );
		$this->assertNotEmpty( $query->query_limit );
		$this->assertSame( 'LIMIT 0, 8', $query->query_limit );

		// All values get reset.
		$query->prepare_query( array( 'fields' => 'all' ) );
		$this->assertEmpty( $query->query_limit );
		$this->assertNull( $query->query_limit );
		$_query_vars = $query->query_vars;

		$query->prepare_query();
		$this->assertSame( $_query_vars, $query->query_vars );

		$query->prepare_query( array( 'number' => -1 ) );
		$this->assertNotEquals( 'LIMIT -1', $query->query_limit );
		$this->assertEmpty( $query->query_limit );
	}

	public function test_meta_vars_should_be_converted_to_meta_query() {
		$q = new WP_User_Query(
			array(
				'meta_key'     => 'foo',
				'meta_value'   => '5',
				'meta_compare' => '>',
				'meta_type'    => 'SIGNED',
			)
		);

		// Multisite adds a 'blog_id' clause, so we have to find the 'foo' clause.
		$mq_clauses = $q->meta_query->get_clauses();
		foreach ( $mq_clauses as $mq_clause ) {
			if ( 'foo' === $mq_clause['key'] ) {
				$clause = $mq_clause;
			}
		}

		$this->assertSame( 'foo', $clause['key'] );
		$this->assertSame( '5', $clause['value'] );
		$this->assertSame( '>', $clause['compare'] );
		$this->assertSame( 'SIGNED', $clause['type'] );
	}

	/**
	 * @ticket 23849
	 */
	public function test_meta_query_with_role() {
		add_user_meta( self::$author_ids[0], 'foo', 'bar' );
		add_user_meta( self::$author_ids[1], 'foo', 'baz' );

		// Users with foo = bar or baz restricted to the author role.
		$query = new WP_User_Query(
			array(
				'fields'     => '',
				'role'       => 'author',
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'   => 'foo',
						'value' => 'bar',
					),
					array(
						'key'   => 'foo',
						'value' => 'baz',
					),
				),
			)
		);

		$this->assertEquals( array( self::$author_ids[0], self::$author_ids[1] ), $query->get_results() );
	}

	public function test_roles_and_caps_should_be_populated_for_default_value_of_blog_id() {
		$query = new WP_User_Query(
			array(
				'include' => self::$author_ids[0],
			)
		);

		$found = $query->get_results();

		$this->assertNotEmpty( $found );
		$user = reset( $found );
		$this->assertSame( array( 'author' ), $user->roles );
		$this->assertSame( array( 'author' => true ), $user->caps );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_roles_and_caps_should_be_populated_for_explicit_value_of_blog_id_on_nonms() {
		$query = new WP_User_Query(
			array(
				'include' => self::$author_ids[0],
				'blog_id' => get_current_blog_id(),
			)
		);

		$found = $query->get_results();

		$this->assertNotEmpty( $found );
		$user = reset( $found );
		$this->assertSame( array( 'author' ), $user->roles );
		$this->assertSame( array( 'author' => true ), $user->caps );
	}

	/**
	 * @group ms-required
	 */
	public function test_roles_and_caps_should_be_populated_for_explicit_value_of_current_blog_id_on_ms() {
		$query = new WP_User_Query(
			array(
				'include' => self::$author_ids[0],
				'blog_id' => get_current_blog_id(),
			)
		);

		$found = $query->get_results();

		$this->assertNotEmpty( $found );
		$user = reset( $found );
		$this->assertSame( array( 'author' ), $user->roles );
		$this->assertSame( array( 'author' => true ), $user->caps );
	}

	/**
	 * @group ms-required
	 */
	public function test_roles_and_caps_should_be_populated_for_explicit_value_of_different_blog_id_on_ms_when_fields_all_with_meta() {
		$b = self::factory()->blog->create();

		add_user_to_blog( $b, self::$author_ids[0], 'author' );

		$query = new WP_User_Query(
			array(
				'include' => self::$author_ids[0],
				'blog_id' => $b,
				'fields'  => 'all_with_meta',
			)
		);

		$found = $query->get_results();

		$this->assertNotEmpty( $found );
		$user = reset( $found );
		$this->assertSame( array( 'author' ), $user->roles );
		$this->assertSame( array( 'author' => true ), $user->caps );
	}

	/**
	 * @ticket 31878
	 * @group ms-required
	 */
	public function test_roles_and_caps_should_be_populated_for_explicit_value_of_different_blog_id_on_ms_when_fields_all() {
		$b = self::factory()->blog->create();
		add_user_to_blog( $b, self::$author_ids[0], 'author' );

		$query = new WP_User_Query(
			array(
				'fields'  => 'all',
				'include' => self::$author_ids[0],
				'blog_id' => $b,
			)
		);

		$found = $query->get_results();

		$this->assertNotEmpty( $found );
		$user = reset( $found );
		$this->assertSame( array( 'author' ), $user->roles );
		$this->assertSame( array( 'author' => true ), $user->caps );
	}

	/**
	 * @ticket 32019
	 * @group ms-required
	 * @expectedDeprecated WP_User_Query
	 */
	public function test_who_authors() {
		$b = self::factory()->blog->create();

		add_user_to_blog( $b, self::$author_ids[0], 'subscriber' );
		add_user_to_blog( $b, self::$author_ids[1], 'author' );
		add_user_to_blog( $b, self::$author_ids[2], 'editor' );

		$q = new WP_User_Query(
			array(
				'who'     => 'authors',
				'blog_id' => $b,
			)
		);

		$found = wp_list_pluck( $q->get_results(), 'ID' );

		$this->assertNotContains( self::$author_ids[0], $found );
		$this->assertContains( self::$author_ids[1], $found );
		$this->assertContains( self::$author_ids[2], $found );
	}

	/**
	 * @ticket 32019
	 * @group ms-required
	 * @expectedDeprecated WP_User_Query
	 */
	public function test_who_authors_should_work_alongside_meta_query() {
		$b = self::factory()->blog->create();

		add_user_to_blog( $b, self::$author_ids[0], 'subscriber' );
		add_user_to_blog( $b, self::$author_ids[1], 'author' );
		add_user_to_blog( $b, self::$author_ids[2], 'editor' );

		add_user_meta( self::$author_ids[1], 'foo', 'bar' );
		add_user_meta( self::$author_ids[2], 'foo', 'baz' );

		$q = new WP_User_Query(
			array(
				'who'        => 'authors',
				'blog_id'    => $b,
				'meta_query' => array(
					array(
						'key'   => 'foo',
						'value' => 'bar',
					),
				),
			)
		);

		$found = wp_list_pluck( $q->get_results(), 'ID' );

		$this->assertNotContains( self::$author_ids[0], $found );
		$this->assertContains( self::$author_ids[1], $found );
		$this->assertNotContains( self::$author_ids[2], $found );
	}

	/**
	 * @ticket 36724
	 * @group ms-required
	 * @expectedDeprecated WP_User_Query
	 */
	public function test_who_authors_should_work_alongside_meta_params() {
		$b = self::factory()->blog->create();

		add_user_to_blog( $b, self::$author_ids[0], 'subscriber' );
		add_user_to_blog( $b, self::$author_ids[1], 'author' );
		add_user_to_blog( $b, self::$author_ids[2], 'editor' );

		add_user_meta( self::$author_ids[1], 'foo', 'bar' );
		add_user_meta( self::$author_ids[2], 'foo', 'baz' );

		$q = new WP_User_Query(
			array(
				'who'        => 'authors',
				'blog_id'    => $b,
				'meta_key'   => 'foo',
				'meta_value' => 'bar',
			)
		);

		$found = wp_list_pluck( $q->get_results(), 'ID' );

		$this->assertNotContains( self::$author_ids[0], $found );
		$this->assertContains( self::$author_ids[1], $found );
		$this->assertNotContains( self::$author_ids[2], $found );
	}

	/**
	 * @ticket 32250
	 */
	public function test_has_published_posts_with_value_true_should_show_authors_of_posts_in_public_post_types() {
		register_post_type( 'wptests_pt_public', array( 'public' => true ) );
		register_post_type( 'wptests_pt_private', array( 'public' => false ) );

		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[0],
				'post_status' => 'publish',
				'post_type'   => 'wptests_pt_public',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[1],
				'post_status' => 'publish',
				'post_type'   => 'wptests_pt_private',
			)
		);

		$q = new WP_User_Query(
			array(
				'has_published_posts' => true,
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[0] );

		$this->assertSameSets( $expected, $found );
	}

	/**
	 * @ticket 32250
	 */
	public function test_has_published_posts_should_obey_post_types() {
		register_post_type( 'wptests_pt_public', array( 'public' => true ) );
		register_post_type( 'wptests_pt_private', array( 'public' => false ) );

		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[0],
				'post_status' => 'publish',
				'post_type'   => 'wptests_pt_public',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[1],
				'post_status' => 'publish',
				'post_type'   => 'wptests_pt_private',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[2],
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		$q = new WP_User_Query(
			array(
				'has_published_posts' => array( 'wptests_pt_private', 'post' ),
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[1], self::$author_ids[2] );

		$this->assertSameSets( $expected, $found );
	}

	/**
	 * @ticket 32250
	 */
	public function test_has_published_posts_should_ignore_non_published_posts() {
		register_post_type( 'wptests_pt_public', array( 'public' => true ) );
		register_post_type( 'wptests_pt_private', array( 'public' => false ) );

		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[0],
				'post_status' => 'draft',
				'post_type'   => 'wptests_pt_public',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[1],
				'post_status' => 'inherit',
				'post_type'   => 'wptests_pt_private',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[2],
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		$q = new WP_User_Query(
			array(
				'has_published_posts' => array( 'wptests_pt_public', 'wptests_pt_private', 'post' ),
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[2] );

		$this->assertSameSets( $expected, $found );
	}

	/**
	 * @ticket 32250
	 * @group ms-required
	 */
	public function test_has_published_posts_should_respect_blog_id() {
		$blogs = self::factory()->blog->create_many( 2 );

		add_user_to_blog( $blogs[0], self::$author_ids[0], 'author' );
		add_user_to_blog( $blogs[0], self::$author_ids[1], 'author' );
		add_user_to_blog( $blogs[1], self::$author_ids[0], 'author' );
		add_user_to_blog( $blogs[1], self::$author_ids[1], 'author' );

		switch_to_blog( $blogs[0] );
		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[0],
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		restore_current_blog();

		switch_to_blog( $blogs[1] );
		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[1],
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		restore_current_blog();

		$q = new WP_User_Query(
			array(
				'has_published_posts' => array( 'post' ),
				'blog_id'             => $blogs[1],
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[1] );

		$this->assertSameSets( $expected, $found );
	}

	/**
	 * @ticket 32592
	 */
	public function test_top_level_or_meta_query_should_eliminate_duplicate_matches() {
		add_user_meta( self::$author_ids[0], 'foo', 'bar' );
		add_user_meta( self::$author_ids[1], 'foo', 'bar' );
		add_user_meta( self::$author_ids[0], 'foo2', 'bar2' );

		$q = new WP_User_Query(
			array(
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'   => 'foo',
						'value' => 'bar',
					),
					array(
						'key'   => 'foo2',
						'value' => 'bar2',
					),
				),
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[0], self::$author_ids[1] );

		$this->assertSameSets( $expected, $found );
	}

	/**
	 * @ticket 32592
	 */
	public function test_nested_or_meta_query_should_eliminate_duplicate_matches() {
		add_user_meta( self::$author_ids[0], 'foo', 'bar' );
		add_user_meta( self::$author_ids[1], 'foo', 'bar' );
		add_user_meta( self::$author_ids[0], 'foo2', 'bar2' );
		add_user_meta( self::$author_ids[1], 'foo3', 'bar3' );

		$q = new WP_User_Query(
			array(
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'   => 'foo',
						'value' => 'bar',
					),
					array(
						'relation' => 'OR',
						array(
							'key'   => 'foo',
							'value' => 'bar',
						),
						array(
							'key'   => 'foo2',
							'value' => 'bar2',
						),
					),
				),
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[0], self::$author_ids[1] );

		$this->assertSameSets( $expected, $found );
	}

	/**
	 * @ticket 36624
	 */
	public function test_nicename_returns_user_with_nicename() {
		wp_update_user(
			array(
				'ID'            => self::$author_ids[0],
				'user_nicename' => 'peter',
			)
		);

		$q = new WP_User_Query(
			array(
				'nicename' => 'peter',
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[0] );

		$this->assertStringContainsString( "AND user_nicename = 'peter'", $q->query_where );
		$this->assertSameSets( $expected, $found );
	}

	/**
	 * @ticket 36624
	 */
	public function test_nicename__in_returns_users_with_included_nicenames() {
		wp_update_user(
			array(
				'ID'            => self::$author_ids[0],
				'user_nicename' => 'peter',
			)
		);

		wp_update_user(
			array(
				'ID'            => self::$author_ids[1],
				'user_nicename' => 'paul',
			)
		);

		wp_update_user(
			array(
				'ID'            => self::$author_ids[2],
				'user_nicename' => 'mary',
			)
		);

		$q = new WP_User_Query(
			array(
				'nicename__in' => array( 'peter', 'paul', 'mary' ),
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[0], self::$author_ids[1], self::$author_ids[2] );

		$this->assertStringContainsString( "AND user_nicename IN ( 'peter','paul','mary' )", $q->query_where );
		$this->assertSameSets( $expected, $found );
	}

	/**
	 * @ticket 36624
	 */
	public function test_nicename__not_in_returns_users_without_included_nicenames() {
		wp_update_user(
			array(
				'ID'            => self::$author_ids[0],
				'user_nicename' => 'peter',
			)
		);

		wp_update_user(
			array(
				'ID'            => self::$author_ids[1],
				'user_nicename' => 'paul',
			)
		);

		wp_update_user(
			array(
				'ID'            => self::$author_ids[2],
				'user_nicename' => 'mary',
			)
		);

		$q = new WP_User_Query(
			array(
				'nicename__not_in' => array( 'peter', 'paul', 'mary' ),
			)
		);

		$found_count    = count( $q->get_results() );
		$expected_count = 10; // 13 total users minus 3 from query.

		$this->assertStringContainsString( "AND user_nicename NOT IN ( 'peter','paul','mary' )", $q->query_where );
		$this->assertSame( $expected_count, $found_count );
	}

	/**
	 * @ticket 36624
	 */
	public function test_orderby_nicename__in() {
		wp_update_user(
			array(
				'ID'            => self::$author_ids[0],
				'user_nicename' => 'peter',
			)
		);

		wp_update_user(
			array(
				'ID'            => self::$author_ids[1],
				'user_nicename' => 'paul',
			)
		);

		wp_update_user(
			array(
				'ID'            => self::$author_ids[2],
				'user_nicename' => 'mary',
			)
		);

		$q = new WP_User_Query(
			array(
				'nicename__in' => array( 'mary', 'peter', 'paul' ),
				'orderby'      => 'nicename__in',
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[2], self::$author_ids[0], self::$author_ids[1] );

		$this->assertStringContainsString( "FIELD( user_nicename, 'mary','peter','paul' )", $q->query_orderby );
		$this->assertSame( $expected, $found );
	}

	/**
	 * @ticket 36624
	 */
	public function test_login_returns_user_with_login() {

		$user_login = get_userdata( self::$author_ids[0] )->user_login;

		$q = new WP_User_Query(
			array(
				'login' => $user_login,
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[0] );

		$this->assertStringContainsString( "AND user_login = '$user_login'", $q->query_where );
		$this->assertSameSets( $expected, $found );
	}

	/**
	 * @ticket 36624
	 */
	public function test_login__in_returns_users_with_included_logins() {
		$user_login1 = get_userdata( self::$author_ids[0] )->user_login;
		$user_login2 = get_userdata( self::$author_ids[1] )->user_login;
		$user_login3 = get_userdata( self::$author_ids[2] )->user_login;

		$q = new WP_User_Query(
			array(
				'login__in' => array( $user_login1, $user_login2, $user_login3 ),
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[0], self::$author_ids[1], self::$author_ids[2] );

		$this->assertStringContainsString( "AND user_login IN ( '$user_login1','$user_login2','$user_login3' )", $q->query_where );
		$this->assertSameSets( $expected, $found );
	}

	/**
	 * @ticket 36624
	 */
	public function test_login__not_in_returns_users_without_included_logins() {
		$user_login1 = get_userdata( self::$author_ids[0] )->user_login;
		$user_login2 = get_userdata( self::$author_ids[1] )->user_login;
		$user_login3 = get_userdata( self::$author_ids[2] )->user_login;

		$q = new WP_User_Query(
			array(
				'login__not_in' => array( $user_login1, $user_login2, $user_login3 ),
			)
		);

		$found_count    = count( $q->get_results() );
		$expected_count = 10; // 13 total users minus 3 from query.

		$this->assertStringContainsString( "AND user_login NOT IN ( '$user_login1','$user_login2','$user_login3' )", $q->query_where );
		$this->assertSame( $expected_count, $found_count );
	}

	/**
	 * @ticket 36624
	 */
	public function test_orderby_login__in() {
		$user_login1 = get_userdata( self::$author_ids[0] )->user_login;
		$user_login2 = get_userdata( self::$author_ids[1] )->user_login;
		$user_login3 = get_userdata( self::$author_ids[2] )->user_login;

		$q = new WP_User_Query(
			array(
				'login__in' => array( $user_login2, $user_login3, $user_login1 ),
				'orderby'   => 'login__in',
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[1], self::$author_ids[2], self::$author_ids[0] );

		$this->assertStringContainsString( "FIELD( user_login, '$user_login2','$user_login3','$user_login1' )", $q->query_orderby );
		$this->assertSame( $expected, $found );
	}

	/**
	 * @ticket 25145
	 */
	public function test_paged() {
		$q = new WP_User_Query(
			array(
				'number'  => 2,
				'paged'   => 2,
				'orderby' => 'ID',
				'order'   => 'DESC', // Avoid funkiness with user 1.
				'fields'  => 'ID',
			)
		);

		$this->assertEquals( array( self::$contrib_id, self::$editor_ids[2] ), $q->results );
	}

	/**
	 * @ticket 33449
	 */
	public function test_query_vars_should_be_filled_in_after_pre_get_users() {
		$query_vars = array( 'blog_id', 'role', 'meta_key', 'meta_value', 'meta_compare', 'include', 'exclude', 'search', 'search_columns', 'orderby', 'order', 'offset', 'number', 'paged', 'count_total', 'fields', 'who', 'has_published_posts' );

		add_action( 'pre_get_users', array( $this, 'filter_pre_get_users_args' ) );
		$q = new WP_User_Query( array_fill_keys( $query_vars, '1' ) );
		remove_action( 'pre_get_users', array( $this, 'filter_pre_get_users_args' ) );

		foreach ( $query_vars as $query_var ) {
			$this->assertArrayHasKey( $query_var, $q->query_vars, "$query_var does not exist." );
		}
	}

	public function filter_pre_get_users_args( $q ) {
		foreach ( $q->query_vars as $k => $v ) {
			unset( $q->query_vars[ $k ] );
		}
	}

	/**
	 * @ticket 22212
	 */
	public function test_get_single_role_by_user_query() {
		$wp_user_search = new WP_User_Query( array( 'role' => 'subscriber' ) );
		$users          = $wp_user_search->get_results();

		$this->assertCount( 2, $users );
	}

	/**
	 * @ticket 22212
	 */
	public function test_get_multiple_roles_by_user_query() {
		$wp_user_search = new WP_User_Query( array( 'role__in' => array( 'subscriber', 'editor' ) ) );
		$users          = $wp_user_search->get_results();
		$this->assertCount( 5, $users );
	}

	/**
	 * @ticket 22212
	 */
	public function test_get_single_role_by_string() {
		$users = get_users(
			array(
				'role' => 'subscriber',
			)
		);

		$this->assertCount( 2, $users );
	}

	/**
	 * @ticket 22212
	 */
	public function test_get_single_role_by_string_which_is_similar() {
		$another_editor = self::factory()->user->create(
			array(
				'user_email' => 'another_editor@another_editor.com',
				'user_login' => 'another_editor',
				'role'       => 'another-editor',
			)
		);

		$users = get_users(
			array(
				'role'   => 'editor',
				'fields' => 'ID',
			)
		);

		$this->assertEqualSets( self::$editor_ids, $users );
	}


	/**
	 * @ticket 22212
	 */
	public function test_get_single_role_by_array() {
		$users = get_users(
			array(
				'role' => array( 'subscriber' ),
			)
		);

		$this->assertCount( 2, $users );
	}

	/**
	 * @ticket 22212
	 */
	public function test_get_multiple_roles_should_only_match_users_who_have_each_role() {
		$users = new WP_User_Query( array( 'role' => array( 'subscriber', 'editor' ) ) );
		$users = $users->get_results();

		$this->assertEmpty( $users );

		foreach ( self::$sub_ids as $subscriber ) {
			$subscriber = get_user_by( 'ID', $subscriber );
			$subscriber->add_role( 'editor' );
		}

		$users = new WP_User_Query( array( 'role' => array( 'subscriber', 'editor' ) ) );
		$users = $users->get_results();

		$this->assertCount( 2, $users );

		foreach ( $users as $user ) {
			$this->assertInstanceOf( 'WP_User', $user );
		}
	}

	/**
	 * @ticket 22212
	 */
	public function test_get_multiple_roles_or() {
		$users = new WP_User_Query( array( 'role__in' => array( 'subscriber', 'editor', 'administrator' ) ) );
		$users = $users->get_results();

		// +1 for the default user created during installation.
		$this->assertCount( 8, $users );
		foreach ( $users as $user ) {
			$this->assertInstanceOf( 'WP_User', $user );
		}
	}

	/**
	 * @ticket 22212
	 */
	public function test_get_multiple_roles_by_comma_separated_list() {
		$users = get_users(
			array(
				'role' => 'subscriber, editor',
			)
		);

		$this->assertEmpty( $users );

		foreach ( self::$sub_ids as $subscriber ) {
			$subscriber = get_user_by( 'ID', $subscriber );
			$subscriber->add_role( 'editor' );
		}

		$users = get_users(
			array(
				'role' => 'subscriber, editor',
			)
		);

		$this->assertCount( 2, $users );
	}

	/**
	 * @ticket 22212
	 */
	public function test_get_multiple_roles_with_meta() {
		// Create administrator user + meta.
		update_user_meta( self::$admin_ids[0], 'mk1', 1 );
		update_user_meta( self::$admin_ids[0], 'mk2', 1 );

		// Create editor user + meta.
		update_user_meta( self::$editor_ids[0], 'mk1', 1 );
		update_user_meta( self::$editor_ids[0], 'mk2', 2 );

		// Create subscriber user + meta.
		update_user_meta( self::$sub_ids[0], 'mk1', 1 );
		update_user_meta( self::$sub_ids[0], 'mk2', 1 );

		// Create contributor user + meta.
		update_user_meta( self::$contrib_id, 'mk1', 1 );
		update_user_meta( self::$contrib_id, 'mk2', 2 );

		// Fetch users.
		$users = get_users(
			array(
				'role__in'   => array( 'administrator', 'editor', 'subscriber' ),
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => 'mk1',
						'value'   => '1',
						'compare' => '=',
						'type'    => 'numeric',
					),
					array(
						'key'     => 'mk2',
						'value'   => '2',
						'compare' => '=',
						'type'    => 'numeric',
					),
				),
			)
		);

		// Check results.
		$this->assertCount( 1, $users );
		$this->assertSame( self::$editor_ids[0], (int) $users[0]->ID );
	}

	/**
	 * @ticket 22212
	 */
	public function test_role_exclusion() {
		$users = get_users(
			array(
				'role__not_in' => 'subscriber',
			)
		);

		// +1 for the default user created during installation.
		$this->assertCount( 11, $users );

		$users = get_users(
			array(
				'role__not_in' => 'editor',
			)
		);

		// +1 for the default user created during installation.
		$this->assertCount( 10, $users );
	}

	/**
	 * @ticket 22212
	 */
	public function test_role__in_role__not_in_combined() {
		foreach ( self::$sub_ids as $subscriber ) {
			$subscriber = get_user_by( 'ID', $subscriber );
			$subscriber->add_role( 'editor' );
		}

		$users = get_users(
			array(
				'role__in' => 'editor',
			)
		);

		$this->assertCount( 5, $users );

		$users = get_users(
			array(
				'role__in'     => 'editor',
				'role__not_in' => 'subscriber',
			)
		);

		$this->assertCount( 3, $users );
	}

	/**
	 * @ticket 22212
	 */
	public function test_role__not_in_role_combined() {
		$subscriber = get_user_by( 'ID', self::$sub_ids[0] );
		$subscriber->add_role( 'editor' );

		$users = get_users(
			array(
				'role'         => 'subscriber',
				'role__not_in' => array( 'editor' ),
			)
		);

		$this->assertCount( 1, $users );
	}

	/**
	 * @ticket 22212
	 */
	public function test_role__not_in_user_without_role() {
		$user_without_rule = get_user_by( 'ID', self::$sub_ids[0] );

		$user_without_rule->remove_role( 'subscriber' );

		$users = get_users(
			array(
				'role__not_in' => 'subscriber',
			)
		);

		// +1 for the default user created during installation.
		$this->assertCount( 12, $users );

		$users = get_users(
			array(
				'role__not_in' => 'editor',
			)
		);

		// +1 for the default user created during installation.
		$this->assertCount( 10, $users );
	}

	/**
	 * @ticket 22212
	 * @group ms-required
	 */
	public function test_blog_id_should_restrict_by_blog_without_requiring_a_named_role() {
		$sites = self::factory()->blog->create_many( 2 );

		add_user_to_blog( $sites[0], self::$author_ids[0], 'author' );
		add_user_to_blog( $sites[1], self::$author_ids[1], 'author' );

		$found = get_users(
			array(
				'blog_id' => $sites[1],
				'fields'  => 'ID',
			)
		);

		$this->assertEqualSets( array( self::$author_ids[1] ), $found );
	}

	/**
	 * @ticket 22212
	 * @ticket 21119
	 * @group ms-required
	 */
	public function test_calling_prepare_query_a_second_time_should_not_add_another_cap_query_on_multisite() {
		$site_id = get_current_blog_id();
		add_user_to_blog( $site_id, self::$author_ids[0], 'author' );

		$q = new WP_User_Query(
			array(
				'include' => self::$author_ids[0],
			)
		);

		$r1 = $q->request;

		$q->prepare_query(
			array(
				'include' => self::$author_ids[0],
			)
		);

		$r2 = $q->request;

		$q->prepare_query(
			array(
				'include' => self::$author_ids[0],
			)
		);

		$r3 = $q->request;

		$this->assertSame( $r1, $r2 );
		$this->assertSame( $r1, $r3 );
	}

	/**
	 * @ticket 39643
	 */
	public function test_search_by_display_name_only() {

		$new_user1          = self::factory()->user->create(
			array(
				'user_login'   => 'name1',
				'display_name' => 'Sophia Andresen',
			)
		);
		self::$author_ids[] = $new_user1;

		$q = new WP_User_Query(
			array(
				'search'         => '*Sophia*',
				'fields'         => '',
				'search_columns' => array( 'display_name' ),
				'include'        => self::$author_ids,
			)
		);

		$ids = $q->get_results();

		// Must include user that has the same string in display_name.
		$this->assertSameSetsWithIndex( array( (string) $new_user1 ), $ids );
	}

	/**
	 * @ticket 39643
	 */
	public function test_search_by_display_name_only_ignore_others() {

		$new_user1          = self::factory()->user->create(
			array(
				'user_login'   => 'Sophia Andresen',
				'display_name' => 'name1',
			)
		);
		self::$author_ids[] = $new_user1;

		$q = new WP_User_Query(
			array(
				'search'         => '*Sophia*',
				'fields'         => '',
				'search_columns' => array( 'display_name' ),
				'include'        => self::$author_ids,
			)
		);

		$ids = $q->get_results();

		// Must not include user that has the same string in other fields.
		$this->assertSame( array(), $ids );
	}

	/**
	 * @ticket 44169
	 */
	public function test_users_pre_query_filter_should_bypass_database_query() {
		add_filter( 'users_pre_query', array( __CLASS__, 'filter_users_pre_query' ), 10, 2 );

		$num_queries = get_num_queries();
		$q           = new WP_User_Query(
			array(
				'fields' => 'ID',
			)
		);

		remove_filter( 'users_pre_query', array( __CLASS__, 'filter_users_pre_query' ), 10, 2 );

		// Make sure no queries were executed.
		$this->assertSame( $num_queries, get_num_queries() );

		// We manually inserted a non-existing user and overrode the results with it.
		$this->assertSame( array( 555 ), $q->results );

		// Make sure manually setting total_users doesn't get overwritten.
		$this->assertSame( 1, $q->total_users );
	}

	public static function filter_users_pre_query( $posts, $query ) {
		$query->total_users = 1;

		return array( 555 );
	}

	/**
	 * @ticket 16841
	 * @group ms-excluded
	 */
	public function test_get_single_capability_by_string() {
		$wp_user_search = new WP_User_Query( array( 'capability' => 'install_plugins' ) );
		$users          = $wp_user_search->get_results();

		$this->assertNotEmpty( $users );
		foreach ( $users as $user ) {
			// User has the capability, but on Multisite they would also need to be a super admin.
			// Hence using get_role_caps() instead of has_cap().
			$role_caps = $user->get_role_caps();
			$this->assertArrayHasKey( 'install_plugins', $role_caps );
			$this->assertTrue( $role_caps['install_plugins'] );
		}
	}

	/**
	 * @ticket 16841
	 * @group ms-required
	 */
	public function test_get_single_capability_by_string_multisite() {
		$wp_user_search = new WP_User_Query( array( 'capability' => array( 'install_plugins' ) ) );
		$users          = $wp_user_search->get_results();

		$this->assertNotEmpty( $users );
		foreach ( $users as $user ) {
			$role_caps = $user->get_role_caps();
			$this->assertArrayHasKey( 'install_plugins', $role_caps );
			$this->assertTrue( $role_caps['install_plugins'] );
			// While the user can have the capability, on Multisite they also need to be a super admin.
			if ( is_super_admin( $user->ID ) ) {
				$this->assertTrue( $user->has_cap( 'install_plugins' ) );
			} else {
				$this->assertFalse( $user->has_cap( 'install_plugins' ) );
			}
		}
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_single_capability_invalid() {
		$wp_user_search = new WP_User_Query( array( 'capability' => 'foo_bar' ) );
		$users          = $wp_user_search->get_results();

		$this->assertEmpty( $users );
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_single_capability_by_array() {
		$wp_user_search = new WP_User_Query( array( 'capability' => array( 'install_plugins' ) ) );
		$users          = $wp_user_search->get_results();

		$this->assertNotEmpty( $users );
		foreach ( $users as $user ) {
			// User has the capability, but on Multisite they would also need to be a super admin.
			// Hence using get_role_caps() instead of has_cap().
			$role_caps = $user->get_role_caps();
			$this->assertArrayHasKey( 'install_plugins', $role_caps );
			$this->assertTrue( $role_caps['install_plugins'] );
		}
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_single_capability_added_to_user() {
		foreach ( self::$sub_ids as $subscriber ) {
			$subscriber = get_user_by( 'ID', $subscriber );
			$subscriber->add_cap( 'custom_cap' );
		}

		$wp_user_search = new WP_User_Query( array( 'capability' => 'custom_cap' ) );
		$users          = $wp_user_search->get_results();

		$this->assertCount( 2, $users );
		$this->assertEqualSets( self::$sub_ids, wp_list_pluck( $users, 'ID' ) );

		foreach ( $users as $user ) {
			$this->assertTrue( $user->has_cap( 'custom_cap' ) );
		}
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_multiple_capabilities_should_only_match_users_who_have_each_capability_test() {
		wp_roles()->add_role( 'role_1', 'Role 1', array( 'role_1_cap' => true ) );
		wp_roles()->add_role( 'role_2', 'Role 2', array( 'role_2_cap' => true ) );

		$subscriber1 = get_user_by( 'ID', self::$sub_ids[0] );
		$subscriber1->add_role( 'role_1' );

		$subscriber2 = get_user_by( 'ID', self::$sub_ids[1] );
		$subscriber2->add_role( 'role_1' );
		$subscriber2->add_role( 'role_2' );

		$wp_user_search = new WP_User_Query( array( 'capability' => array( 'role_1_cap', 'role_2_cap' ) ) );
		$users          = $wp_user_search->get_results();

		$this->assertCount( 1, $users );
		$this->assertSame( $users[0]->ID, $subscriber2->ID );
		foreach ( $users as $user ) {
			$this->assertTrue( $user->has_cap( 'role_1_cap' ) );
			$this->assertTrue( $user->has_cap( 'role_2_cap' ) );
		}
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_multiple_capabilities_should_only_match_users_who_have_each_capability_added_to_user() {
		$admin1 = get_user_by( 'ID', self::$admin_ids[0] );
		$admin1->add_cap( 'custom_cap' );

		$wp_user_search = new WP_User_Query( array( 'capability' => array( 'manage_options', 'custom_cap' ) ) );
		$users          = $wp_user_search->get_results();

		$this->assertCount( 1, $users );
		$this->assertSame( $users[0]->ID, $admin1->ID );
		$this->assertTrue( $users[0]->has_cap( 'custom_cap' ) );
		$this->assertTrue( $users[0]->has_cap( 'manage_options' ) );
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_multiple_capabilities_or() {
		$wp_user_search = new WP_User_Query( array( 'capability__in' => array( 'publish_posts', 'edit_posts' ) ) );
		$users          = $wp_user_search->get_results();

		$this->assertNotEmpty( $users );
		foreach ( $users as $user ) {
			$this->assertTrue( $user->has_cap( 'publish_posts' ) || $user->has_cap( 'edit_posts' ) );
		}
	}

	/**
	 * @ticket 16841
	 */
	public function test_get_multiple_capabilities_or_added_to_user() {
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$user->add_cap( 'custom_cap' );

		$wp_user_search = new WP_User_Query( array( 'capability__in' => array( 'publish_posts', 'custom_cap' ) ) );
		$users          = $wp_user_search->get_results();

		$this->assertNotEmpty( $users );
		foreach ( $users as $user ) {
			$this->assertTrue( $user->has_cap( 'publish_posts' ) || $user->has_cap( 'custom_cap' ) );
		}
	}

	/**
	 * @ticket 16841
	 */
	public function test_capability_exclusion() {
		$wp_user_search = new WP_User_Query( array( 'capability__not_in' => array( 'publish_posts', 'edit_posts' ) ) );
		$users          = $wp_user_search->get_results();

		$this->assertNotEmpty( $users );
		foreach ( $users as $user ) {
			$this->assertFalse( $user->has_cap( 'publish_posts' ) );
			$this->assertFalse( $user->has_cap( 'edit_posts' ) );
		}
	}

	/**
	 * @ticket 16841
	 */
	public function test_capability_exclusion_added_to_user() {
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$user->add_cap( 'custom_cap' );

		$wp_user_search = new WP_User_Query( array( 'capability__not_in' => array( 'publish_posts', 'custom_cap' ) ) );
		$users          = $wp_user_search->get_results();

		$this->assertNotEmpty( $users );
		foreach ( $users as $user ) {
			$this->assertFalse( $user->has_cap( 'publish_posts' ) );
			$this->assertFalse( $user->has_cap( 'custom_cap' ) );
		}
	}

	/**
	 * @ticket 16841
	 */
	public function test_capability__in_capability__not_in_combined() {
		$wp_user_search = new WP_User_Query(
			array(
				'capability__in'     => array( 'read' ),
				'capability__not_in' => array( 'manage_options' ),
			)
		);
		$users          = $wp_user_search->get_results();

		$this->assertNotEmpty( $users );
		foreach ( $users as $user ) {
			$this->assertTrue( $user->has_cap( 'read' ) );
			$this->assertFalse( $user->has_cap( 'manage_options' ) );
		}
	}

	/**
	 * @ticket 16841
	 * @group ms-required
	 */
	public function test_get_single_capability_multisite_blog_id() {
		$blog_id = self::factory()->blog->create();

		add_user_to_blog( $blog_id, self::$author_ids[0], 'subscriber' );
		add_user_to_blog( $blog_id, self::$author_ids[1], 'author' );
		add_user_to_blog( $blog_id, self::$author_ids[2], 'editor' );

		$wp_user_search = new WP_User_Query(
			array(
				'capability' => 'publish_posts',
				'blog_id'    => $blog_id,
			)
		);
		$users          = $wp_user_search->get_results();

		$found = wp_list_pluck( $wp_user_search->get_results(), 'ID' );

		$this->assertNotEmpty( $users );
		foreach ( $users as $user ) {
			$this->assertTrue( $user->has_cap( 'publish_posts' ) );
		}

		$this->assertNotContains( self::$author_ids[0], $found );
		$this->assertContains( self::$author_ids[1], $found );
		$this->assertContains( self::$author_ids[2], $found );
	}

	/**
	 * @ticket 53177
	 * @dataProvider data_returning_field_subset_as_string
	 *
	 * @param string $field
	 * @param mixed  $expected
	 */
	public function test_returning_field_subset_as_string( $field, $expected ) {
		$q       = new WP_User_Query(
			array(
				'fields'  => $field,
				'include' => array( '1' ),
			)
		);
		$results = $q->get_results();

		$this->assertSameSets( $expected, $results );
	}

	/**
	 * Data provider
	 *
	 * @return array
	 */
	public function data_returning_field_subset_as_string() {
		$data = array(
			'id'            => array(
				'fields'   => 'id',
				'expected' => array( '1' ),
			),
			'ID'            => array(
				'fields'   => 'ID',
				'expected' => array( '1' ),
			),
			'user_login'    => array(
				'fields'   => 'user_login',
				'expected' => array( 'admin' ),
			),
			'user_nicename' => array(
				'fields'   => 'user_nicename',
				'expected' => array( 'admin' ),
			),
			'user_email'    => array(
				'fields'   => 'user_email',
				'expected' => array( WP_TESTS_EMAIL ),
			),
			'user_url'      => array(
				'fields'   => 'user_url',
				'expected' => array( wp_guess_url() ),
			),
			'user_status'   => array(
				'fields'   => 'user_status',
				'expected' => array( '0' ),
			),
			'display_name'  => array(
				'fields'   => 'display_name',
				'expected' => array( 'admin' ),
			),
			'invalid_field' => array(
				'fields'   => 'invalid_field',
				'expected' => array( '1' ),
			),
		);

		if ( is_multisite() ) {
			$data['spam']    = array(
				'fields'   => 'spam',
				'expected' => array( '0' ),
			);
			$data['deleted'] = array(
				'fields'   => 'deleted',
				'expected' => array( '0' ),
			);
		}

		return $data;
	}

	/**
	 * @ticket 53177
	 * @dataProvider data_returning_field_subset_as_array
	 *
	 * @param array $field
	 * @param mixed $expected
	 */
	public function test_returning_field_subset_as_array( $field, $expected ) {
		$q       = new WP_User_Query(
			array(
				'fields'  => $field,
				'include' => array( '1' ),
			)
		);
		$results = $q->get_results();

		if ( isset( $results[0] ) && is_object( $results[0] ) ) {
			$results = (array) $results[0];
		}

		$this->assertSameSetsWithIndex( $expected, $results );
	}

	/**
	 * Data provider
	 *
	 * @return array
	 */
	public function data_returning_field_subset_as_array() {
		$data = array(
			'id'                 => array(
				'fields'   => array( 'id' ),
				'expected' => array(
					'ID' => '1',
					'id' => '1',
				),
			),
			'ID'                 => array(
				'fields'   => array( 'ID' ),
				'expected' => array(
					'ID' => '1',
					'id' => '1',
				),
			),
			'user_login'         => array(
				'fields'   => array( 'user_login' ),
				'expected' => array( 'user_login' => 'admin' ),
			),
			'user_nicename'      => array(
				'fields'   => array( 'user_nicename' ),
				'expected' => array( 'user_nicename' => 'admin' ),
			),
			'user_email'         => array(
				'fields'   => array( 'user_email' ),
				'expected' => array( 'user_email' => WP_TESTS_EMAIL ),
			),
			'user_url'           => array(
				'fields'   => array( 'user_url' ),
				'expected' => array( 'user_url' => wp_guess_url() ),
			),
			'user_status'        => array(
				'fields'   => array( 'user_status' ),
				'expected' => array( 'user_status' => '0' ),
			),
			'display_name'       => array(
				'fields'   => array( 'display_name' ),
				'expected' => array( 'display_name' => 'admin' ),
			),
			'invalid_field'      => array(
				'fields'   => array( 'invalid_field' ),
				'expected' => array(
					'ID' => '1',
					'id' => '1',
				),
			),
			'valid array inc id' => array(
				'fields'   => array( 'display_name', 'user_email', 'id' ),
				'expected' => array(
					'display_name' => 'admin',
					'user_email'   => WP_TESTS_EMAIL,
					'ID'           => '1',
					'id'           => '1',
				),
			),
			'valid array inc ID' => array(
				'fields'   => array( 'display_name', 'user_email', 'ID' ),
				'expected' => array(
					'display_name' => 'admin',
					'user_email'   => WP_TESTS_EMAIL,
					'ID'           => '1',
					'id'           => '1',
				),
			),
			'partly valid array' => array(
				'fields'   => array( 'display_name', 'invalid_field' ),
				'expected' => array( 'display_name' => 'admin' ),
			),
		);

		if ( is_multisite() ) {
			$data['spam']    = array(
				'fields'   => array( 'spam' ),
				'expected' => array( 'spam' => '0' ),
			);
			$data['deleted'] = array(
				'fields'   => array( 'deleted' ),
				'expected' => array( 'deleted' => '0' ),
			);
		}

		return $data;
	}

	/**
	 * @ticket 53177
	 */
	public function test_returning_field_all() {
		$q         = new WP_User_Query(
			array(
				'fields'  => 'all',
				'include' => array( '1' ),
			)
		);
		$results   = $q->get_results();
		$user_data = (array) $results[0]->data;

		$expected_results = array(
			'ID'                  => '1',
			'user_login'          => 'admin',
			'user_nicename'       => 'admin',
			'user_url'            => wp_guess_url(),
			'user_email'          => WP_TESTS_EMAIL,
			'user_activation_key' => '',
			'user_status'         => '0',
			'display_name'        => 'admin',
		);

		if ( is_multisite() ) {
			$expected_results['spam']    = '0';
			$expected_results['deleted'] = '0';
		}

		// These change for each run.
		unset( $user_data['user_pass'], $user_data['user_registered'] );

		$this->assertSameSetsWithIndex( $expected_results, $user_data );
		$this->assertInstanceOf( 'WP_User', $results[0] );
	}

	/**
	 * @ticket 53177
	 *
	 * @covers WP_User_Query::prepare_query
	 */
	public function test_returning_field_user_registered() {
		$q       = new WP_User_Query(
			array(
				'fields'  => 'user_registered',
				'include' => array( self::$admin_ids[0] ),
			)
		);
		$results = $q->get_results();
		$this->assertNotFalse( DateTime::createFromFormat( 'Y-m-d H:i:s', $results[0] ) );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58897
	 *
	 * @covers WP_User_Query::__get()
	 *
	 * @param string $property_name Property name to get.
	 * @param mixed $expected       Expected value.
	 */
	public function test_should_get_compat_fields( $property_name, $expected ) {
		$user_query = new WP_User_Query();

		$this->assertSame( $expected, $user_query->$property_name );
	}

	/**
	 * @ticket 58897
	 *
	 * @covers WP_User_Query::__get()
	 */
	public function test_should_throw_deprecation_when_getting_dynamic_property() {
		$user_query = new WP_User_Query();

		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_User_Query::__get(): ' .
			'The property `undefined_property` is not declared. Getting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->assertNull( $user_query->undefined_property, 'Getting a dynamic property should return null from WP_User_Query::__get()' );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58897
	 *
	 * @covers WP_User_Query::__set()
	 *
	 * @param string $property_name Property name to set.
	 */
	public function test_should_set_compat_fields( $property_name ) {
		$user_query = new WP_User_Query();
		$value      = uniqid();

		$user_query->$property_name = $value;
		$this->assertSame( $value, $user_query->$property_name );
	}

	/**
	 * @ticket 58897
	 *
	 * @covers WP_User_Query::__set()
	 */
	public function test_should_throw_deprecation_when_setting_dynamic_property() {
		$user_query = new WP_User_Query();

		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_User_Query::__set(): ' .
			'The property `undefined_property` is not declared. Setting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$user_query->undefined_property = 'some value';
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58897
	 *
	 * @covers WP_User_Query::__isset()
	 *
	 * @param string $property_name Property name to check.
	 * @param mixed $expected       Expected value.
	 */
	public function test_should_isset_compat_fields( $property_name, $expected ) {
		$user_query = new WP_User_Query();

		$actual = isset( $user_query->$property_name );
		if ( is_null( $expected ) ) {
			$this->assertFalse( $actual );
		} else {
			$this->assertTrue( $actual );
		}
	}

	/**
	 * @ticket 58897
	 *
	 * @covers WP_User_Query::__isset()
	 */
	public function test_should_throw_deprecation_when_isset_of_dynamic_property() {
		$user_query = new WP_User_Query();

		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_User_Query::__isset(): ' .
			'The property `undefined_property` is not declared. Checking `isset()` on a dynamic property ' .
			'is deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->assertFalse( isset( $user_query->undefined_property ), 'Checking a dynamic property should return false from WP_User_Query::__isset()' );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58897
	 *
	 * @covers WP_User_Query::__unset()
	 *
	 * @param string $property_name Property name to unset.
	 */
	public function test_should_unset_compat_fields( $property_name ) {
		$user_query = new WP_User_Query();

		unset( $user_query->$property_name );
		$this->assertFalse( isset( $user_query->$property_name ) );
	}

	/**
	 * @ticket 58897
	 *
	 * @covers WP_User_Query::__unset()
	 */
	public function test_should_throw_deprecation_when_unset_of_dynamic_property() {
		$user_query = new WP_User_Query();

		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_User_Query::__unset(): ' .
			'A property `undefined_property` is not declared. Unsetting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		unset( $user_query->undefined_property );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_compat_fields() {
		return array(
			'results'     => array(
				'property_name' => 'results',
				'expected'      => null,
			),
			'total_users' => array(
				'property_name' => 'total_users',
				'expected'      => 0,
			),
		);
	}

	/**
	 * @ticket 56841
	 */
	public function test_query_does_not_have_leading_whitespace() {
		$q = new WP_User_Query(
			array(
				'number' => 2,
			)
		);

		$this->assertSame( ltrim( $q->request ), $q->request, 'The query has leading whitespace' );
	}
}
