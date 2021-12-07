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

		$taxonomies = array( 'category', 'post_tag', 'custom_taxonomy' );
		foreach ( $taxonomies as $taxonomy ) {
			self::$terms[ $taxonomy ] = $factory->term->create_and_get( array( 'taxonomy' => $taxonomy ) );
		}

		self::$user_ids['admin']      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$user_ids['subscriber'] = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function set_up() {
		parent::set_up();
		self::register_custom_taxonomy();
	}

	/**
	 * @dataProvider data_edit_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested (used for index of term keys).
	 * @param bool   $use_id   When true, pass term ID. Else, pass term object.
	 * @param string $expected Expected URL within admin of edit link.
	 */
	public function test_edit_term_link_for_permitted_user( $taxonomy, $use_id, $expected ) {
		wp_set_current_user( self::$user_ids['admin'] );
		$term = $this->get_term( $taxonomy, $use_id );

		// Term IDs are not known by the data provider so need to be replaced.
		$expected = str_replace( '%ID%', $use_id ? $term : $term->term_id, $expected );
		$expected = '"' . admin_url( $expected ) . '"';

		$this->assertStringContainsString( $expected, edit_term_link( '', '', '', $term, false ) );
		$this->assertStringContainsString( $expected, edit_term_link( '', '', '', get_term( $term, $taxonomy ), false ) );
	}

	/**
	 * @dataProvider data_edit_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested (used for index of term keys).
	 * @param bool   $use_id   When true, pass term ID. Else, pass term object.
	 */
	public function test_edit_term_link_for_denied_user( $taxonomy, $use_id ) {
		wp_set_current_user( self::$user_ids['subscriber'] );
		$term = $this->get_term( $taxonomy, $use_id );

		$this->assertNull( edit_term_link( '', '', '', $term, false ) );
		$this->assertNull( edit_term_link( '', '', '', get_term( $term, $taxonomy ), false ) );
	}

	/**
	 * @dataProvider data_edit_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested (used for index of term keys).
	 * @param bool   $use_id   When true, pass term ID. Else, pass term object.
	 */
	public function test_edit_term_link_filter_is_int_by_term_id( $taxonomy, $use_id ) {
		wp_set_current_user( self::$user_ids['admin'] );
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
	 * @dataProvider data_edit_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested (used for index of term keys).
	 * @param bool   $use_id   When true, pass term ID. Else, pass term object.
	 */
	public function test_edit_term_link_filter_is_int_by_term_object( $taxonomy, $use_id ) {
		wp_set_current_user( self::$user_ids['admin'] );
		$term = $this->get_term( $taxonomy, $use_id );

		add_filter(
			'edit_term_link',
			function( $location, $term ) {
				$this->assertIsInt( $term );
			},
			10,
			2
		);

		edit_term_link( '', '', '', get_term( $term, $taxonomy ), false );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_edit_term_link() {
		return array(
			'category passing term_id'          => array(
				'taxonomy' => 'category',
				'use_id'   => false,
				'expected' => 'term.php?taxonomy=category&tag_ID=%ID%&post_type=post',
			),
			'category passing term object'      => array(
				'taxonomy' => 'category',
				'use_id'   => true,
				'expected' => 'term.php?taxonomy=category&tag_ID=%ID%&post_type=post',
			),
			'post_tag passing term_id'          => array(
				'taxonomy' => 'post_tag',
				'use_id'   => false,
				'expected' => 'term.php?taxonomy=post_tag&tag_ID=%ID%&post_type=post',
			),
			'post_tag passing term object'      => array(
				'taxonomy' => 'post_tag',
				'use_id'   => true,
				'expected' => 'term.php?taxonomy=post_tag&tag_ID=%ID%&post_type=post',
			),
			'a custom taxonomy passing term_id' => array(
				'taxonomy' => 'custom_taxonomy',
				'use_id'   => false,
				'expected' => 'term.php?taxonomy=custom_taxonomy&tag_ID=%ID%&post_type=post',
			),
			'a custom taxonomy passing term_id' => array(
				'taxonomy' => 'custom_taxonomy',
				'use_id'   => true,
				'expected' => 'term.php?taxonomy=custom_taxonomy&tag_ID=%ID%&post_type=post',
			),
		);
	}

	/**
	 * Helper to register a custom taxonomy for use in tests.
	 *
	 * @since 5.9.0
	 */
	private static function register_custom_taxonomy() {
		register_taxonomy( 'custom_taxonomy', 'post' );
	}

	/**
	 * Helper to get the term for the given taxonomy.
	 *
	 * @since 5.9.0
	 *
	 * @param string $taxonomy Taxonomy being tested (used for index of term keys).
	 * @param bool   $use_id   When true, pass term ID. Else, pass term object.
	 * @return WP_Term|int If $use_id is true, term ID is returned; else instance of WP_Term.
	 */
	private function get_term( $taxonomy, $use_id ) {
		$term = self::$terms[ $taxonomy ];
		if ( $use_id ) {
			$term = $term->term_id;
		}

		return $term;
	}
}
