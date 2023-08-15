<?php

/**
 * @group  link
 * @covers ::edit_term_link
 */
class Tests_Link_EditTermLink extends WP_UnitTestCase {

	private static $terms;
	private static $user_ids;

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

	/**
	 * @dataProvider data_edit_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested.
	 * @param bool   $use_id   Whether to pass term ID or term object to `edit_term_link()`.
	 * @param string $expected Expected part of admin URL for the edit link.
	 */
	public function test_edit_term_link_should_return_the_link_for_permitted_user( $taxonomy, $use_id, $expected ) {
		$term = $this->get_term( $taxonomy, $use_id );

		// Term IDs are not known by the data provider so need to be replaced.
		$expected = str_replace( '%ID%', $use_id ? $term : $term->term_id, $expected );
		$expected = '"' . admin_url( $expected ) . '"';

		$this->assertStringContainsString( $expected, edit_term_link( '', '', '', $term, false ) );
	}

	/**
	 * @dataProvider data_edit_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested.
	 * @param bool   $use_id   Whether to pass term ID or term object to `edit_term_link()`.
	 */
	public function test_edit_term_link_should_return_null_for_denied_user( $taxonomy, $use_id ) {
		wp_set_current_user( self::$user_ids['subscriber'] );
		$term = $this->get_term( $taxonomy, $use_id );

		$this->assertNull( edit_term_link( '', '', '', $term, false ) );
	}

	/**
	 * @dataProvider data_edit_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested.
	 * @param bool   $use_id   Whether to pass term ID or term object to `edit_term_link()`.
	 */
	public function test_edit_term_link_filter_should_receive_term_id( $taxonomy, $use_id ) {
		$term = $this->get_term( $taxonomy, $use_id );

		add_filter(
			'edit_term_link',
			function( $location, $term ) {
				$this->assertIsInt( $term );
			},
			10,
			2
		);

		edit_term_link( '', '', '', $term, false );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_edit_term_link() {
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
