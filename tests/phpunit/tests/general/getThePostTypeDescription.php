<?php

/**
 * Tests for the `get_the_post_type_description()` function.
 *
 * @group general
 * @group template
 * @group post
 *
 * @covers ::get_the_post_type_description
 */
class Tests_General_GetThePostTypeDescription extends WP_UnitTestCase {

	const POST_TYPE_WITH_DESC    = 'cpt_with_desc';
	const POST_TYPE_WITHOUT_DESC = 'cpt_without_desc';

	public function set_up() {
		parent::set_up();

		register_post_type(
			self::POST_TYPE_WITH_DESC,
			array(
				'public'      => true,
				'description' => 'Test CPT description.',
			)
		);

		register_post_type(
			self::POST_TYPE_WITHOUT_DESC,
			array(
				'public' => true,
			)
		);
	}

	/**
	 * Tests that get_the_post_type_description() returns an empty string when no post type is queried.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_post_type_description_without_queried_post_type() {
		global $wp_query;

		$wp_query->query_vars = array();

		$this->assertSame( '', get_the_post_type_description() );
	}

	/**
	 * Tests that get_the_post_type_description() returns the description of the queried post type.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_post_type_description_with_queried_post_type() {
		global $wp_query;

		$wp_query->set( 'post_type', self::POST_TYPE_WITH_DESC );

		$this->assertSame( "<p>Test CPT description.</p>\n", get_the_post_type_description() );
	}

	/**
	 * Tests that get_the_post_type_description() handles an array of post types.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_post_type_description_with_array_post_type() {
		global $wp_query;

		$wp_query->set( 'post_type', array( self::POST_TYPE_WITH_DESC, 'post' ) );

		$this->assertSame( "<p>Test CPT description.</p>\n", get_the_post_type_description() );
	}

	/**
	 * Tests that get_the_post_type_description() returns an empty string when the post type has no description.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_post_type_description_without_description() {
		global $wp_query;

		$wp_query->set( 'post_type', self::POST_TYPE_WITHOUT_DESC );

		$this->assertSame( '', get_the_post_type_description() );
	}

	/**
	 * Tests that the 'get_the_post_type_description' filter is applied.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_post_type_description_filter() {
		global $wp_query;

		$wp_query->set( 'post_type', self::POST_TYPE_WITH_DESC );

		$filter_received_obj = null;

		add_filter(
			'get_the_post_type_description',
			static function ( $description, $post_type_obj ) use ( &$filter_received_obj ) {
				$filter_received_obj = $post_type_obj;
				return 'Filtered: ' . $description;
			},
			20,
			2
		);

		$result = get_the_post_type_description();

		$this->assertSame( "Filtered: <p>Test CPT description.</p>\n", $result );
		$this->assertInstanceOf( 'WP_Post_Type', $filter_received_obj );
		$this->assertSame( self::POST_TYPE_WITH_DESC, $filter_received_obj->name );
	}
}
