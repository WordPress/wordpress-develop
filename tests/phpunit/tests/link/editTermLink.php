<?php

/**
 * @group link
 * @covers ::edit_term_link
 */
class Tests_Link_EditTermLink extends WP_UnitTestCase {

	public static $term_ids;

	public static $user_ids;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::_register_taxonomy();

		$taxonomies = array( 'category', 'post_tag', 'custom_taxonomy' );
		foreach ( $taxonomies as $taxonomy ) {
			self::$term_ids[ $taxonomy ] = $factory->term->create( array( 'taxonomy' => $taxonomy ) );
		}

		self::$user_ids['admin']      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$user_ids['subscriber'] = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function setUp() {
		parent::setUp();
		self::_register_taxonomy();
	}

	/**
	 * Register a custom taxonomy for use in tests.
	 */
	public static function _register_taxonomy() {
		register_taxonomy( 'custom_taxonomy', 'post' );
	}

	/**
	 * @dataProvider data_edit_term_link
	 *
	 * @param string $taxonomy Taxonomy been tested (used for index of term keys).
	 * @param string $expected Expected URL within admin of edit link.
	 */
	public function test_edit_term_link_for_permitted_user( $taxonomy, $expected ) {
		wp_set_current_user( self::$user_ids['admin'] );
		$term_id = self::$term_ids[ $taxonomy ];

		// Term IDs are not known by the data provider so need to be replaced.
		$expected = str_replace( '%ID%', $term_id, $expected );

		$this->assertContains( '"' . admin_url( $expected ) . '"', edit_term_link( '', '', '', $term_id, false ) );
		$this->assertContains( '"' . admin_url( $expected ) . '"', edit_term_link( '', '', '', get_term( $term_id, $taxonomy ), false ) );
	}

	/**
	 * @dataProvider data_edit_term_link
	 *
	 * @param string $taxonomy Taxonomy been tested (used for index of term keys).
	 */
	public function test_edit_term_link_for_denied_user( $taxonomy ) {
		wp_set_current_user( self::$user_ids['subscriber'] );
		$term_id = self::$term_ids[ $taxonomy ];

		$this->assertNull( edit_term_link( '', '', '', $term_id, false ) );
		$this->assertNull( edit_term_link( '', '', '', get_term( $term_id, $taxonomy ), false ) );
	}

	/**
	 * @dataProvider data_edit_term_link
	 *
	 * @param string $taxonomy Taxonomy been tested (used for index of term keys).
	 */
	public function test_edit_term_link_filter_is_int_by_term_id( $taxonomy ) {
		wp_set_current_user( self::$user_ids['admin'] );
		$term_id = self::$term_ids[ $taxonomy ];

		add_filter(
			'edit_term_link',
			function( $location, $term ) {
				$this->assertIsInt( $term );
			},
			10,
			2
		);

		edit_term_link( '', '', '', $term_id, false );
	}

	/**
	 * @dataProvider data_edit_term_link
	 *
	 * @param string $taxonomy Taxonomy been tested (used for index of term keys).
	 */
	public function test_edit_term_link_filter_is_int_by_term_object( $taxonomy ) {
		wp_set_current_user( self::$user_ids['admin'] );
		$term_id = self::$term_ids[ $taxonomy ];

		add_filter(
			'edit_term_link',
			function( $location, $term ) {
				$this->assertIsInt( $term );
			},
			10,
			2
		);

		edit_term_link( '', '', '', get_term( $term_id, $taxonomy ), false );
	}

	public function data_edit_term_link() {
		return array(
			array( 'category', 'term.php?taxonomy=category&tag_ID=%ID%&post_type=post' ),
			array( 'post_tag', 'term.php?taxonomy=post_tag&tag_ID=%ID%&post_type=post' ),
			array( 'custom_taxonomy', 'term.php?taxonomy=custom_taxonomy&tag_ID=%ID%&post_type=post' ),
		);
	}
}
