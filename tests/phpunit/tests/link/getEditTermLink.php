<?php

/**
 * @group link
 * @covers ::get_edit_term_link
 */
class Tests_Link_GetEditTermLink extends WP_UnitTestCase {

	public static $terms;
	public static $user_ids;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::register_custom_taxonomy();

		$taxonomies = array( 'category', 'post_tag', 'wptests_tax' );
		foreach ( $taxonomies as $taxonomy ) {
			self::$terms[ $taxonomy ] = $factory->term->create_and_get( array( 'taxonomy' => $taxonomy ) );
		}

		self::$user_ids['admin']      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$user_ids['subscriber'] = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$user_ids['admin'] );
		self::register_custom_taxonomy();
	}

	/**
	 * Helper to register a custom taxonomy for use in tests.
	 *
	 * @since 5.9.0
	 */
	private static function register_custom_taxonomy() {
		register_taxonomy( 'wptests_tax', 'post' );
	}

	/**
	 * Helper to get the term for the given taxonomy.
	 *
	 * @since 5.9.0
	 *
	 * @param string $taxonomy Taxonomy being tested (used for index of term keys).
	 * @param bool   $use_id   Whether to return term ID or term object.
	 * @return WP_Term|int Term ID if `$use_id` is true, WP_Term instance otherwise.
	 */
	private function get_term( $taxonomy, $use_id ) {
		$term = self::$terms[ $taxonomy ];
		if ( $use_id ) {
			$term = $term->term_id;
		}

		return $term;
	}

	public function test_get_edit_term_link_default() {
		$term1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'foo',
			)
		);

		$actual   = get_edit_term_link( $term1, 'wptests_tax' );
		$expected = 'http://' . WP_TESTS_DOMAIN . '/wp-admin/term.php?taxonomy=wptests_tax&tag_ID=' . $term1 . '&post_type=post';
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 32786
	 */
	public function test_get_edit_term_link_invalid_id() {
		$term1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'foo',
			)
		);

		$actual = get_edit_term_link( 12345, 'wptests_tax' );
		$this->assertNull( $actual );
	}

	/**
	 * @ticket 32786
	 */
	public function test_get_edit_term_link_empty_id() {
		$actual = get_edit_term_link( '', 'wptests_tax' );
		$this->assertNull( $actual );
	}

	/**
	 * @ticket 32786
	 */
	public function test_get_edit_term_link_bad_tax() {
		$actual = get_edit_term_link( '', 'bad_tax' );
		$this->assertNull( $actual );
	}

	/**
	 * @ticket 35922
	 */
	public function test_taxonomy_should_not_be_required() {
		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'foo',
			)
		);

		$actual = get_edit_term_link( $t );
		$this->assertNotNull( $actual );
	}

	/**
	 * @ticket 35922
	 */
	public function test_cap_check_should_use_correct_taxonomy_when_taxonomy_is_not_specified() {
		register_taxonomy(
			'wptests_tax_subscriber',
			'post',
			array(
				'capabilities' => array(
					'edit_terms' => 'read',
				),
			)
		);

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax_subscriber',
				'name'     => 'foo',
			)
		);

		wp_set_current_user( self::$user_ids['subscriber'] );

		$actual = get_edit_term_link( $t );
		$this->assertNotNull( $actual );
	}

	/**
	 * @dataProvider data_get_edit_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested.
	 * @param bool   $use_id   Whether to pass term ID or term object to `get_edit_term_link()`.
	 * @param string $expected Expected part of admin URL for the edit link.
	 */
	public function test_get_edit_term_link_should_return_the_link_for_permitted_user( $taxonomy, $use_id, $expected ) {
		$term = $this->get_term( $taxonomy, $use_id );

		// Term IDs are not known by the data provider so need to be replaced.
		$expected = str_replace( '%ID%', $use_id ? $term : $term->term_id, $expected );
		$expected = admin_url( $expected );

		$this->assertSame( $expected, get_edit_term_link( $term, $taxonomy ) );
	}

	/**
	 * @dataProvider data_get_edit_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested.
	 * @param bool   $use_id   Whether to pass term ID or term object to `get_edit_term_link()`.
	 */
	public function test_get_edit_term_link_should_return_null_for_denied_user( $taxonomy, $use_id ) {
		wp_set_current_user( self::$user_ids['subscriber'] );
		$term = $this->get_term( $taxonomy, $use_id );

		$this->assertNull( get_edit_term_link( $term, $taxonomy ) );
	}

	/**
	 * @dataProvider data_get_edit_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested.
	 * @param bool   $use_id   Whether to pass term ID or term object to `get_edit_term_link()`.
	 */
	public function test_get_edit_term_link_filter_should_receive_term_id( $taxonomy, $use_id ) {
		$term = $this->get_term( $taxonomy, $use_id );

		add_filter(
			'get_edit_term_link',
			function( $location, $term ) {
				$this->assertIsInt( $term );
			},
			10,
			2
		);

		get_edit_term_link( $term, $taxonomy );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_edit_term_link() {
		return array(
			'category passing term_id'              => array(
				'taxonomy' => 'category',
				'use_id'   => true,
				'expected' => 'term.php?taxonomy=category&tag_ID=%ID%&post_type=post',
			),
			'category passing term object'          => array(
				'taxonomy' => 'category',
				'use_id'   => false,
				'expected' => 'term.php?taxonomy=category&tag_ID=%ID%&post_type=post',
			),
			'post_tag passing term_id'              => array(
				'taxonomy' => 'post_tag',
				'use_id'   => true,
				'expected' => 'term.php?taxonomy=post_tag&tag_ID=%ID%&post_type=post',
			),
			'post_tag passing term object'          => array(
				'taxonomy' => 'post_tag',
				'use_id'   => false,
				'expected' => 'term.php?taxonomy=post_tag&tag_ID=%ID%&post_type=post',
			),
			'a custom taxonomy passing term_id'     => array(
				'taxonomy' => 'wptests_tax',
				'use_id'   => true,
				'expected' => 'term.php?taxonomy=wptests_tax&tag_ID=%ID%&post_type=post',
			),
			'a custom taxonomy passing term object' => array(
				'taxonomy' => 'wptests_tax',
				'use_id'   => false,
				'expected' => 'term.php?taxonomy=wptests_tax&tag_ID=%ID%&post_type=post',
			),
		);
	}
}
