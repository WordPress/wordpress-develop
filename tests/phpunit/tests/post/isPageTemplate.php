<?php

/**
 * Tests for is_page_template().
 *
 * @group post
 * @group template
 *
 * @covers ::is_page_template
 */
class Tests_Post_IsPageTemplate extends WP_UnitTestCase {

	/**
	 * Page ID with custom template.
	 *
	 * @var int
	 */
	protected static $page_with_template;

	/**
	 * Page ID with default template.
	 *
	 * @var int
	 */
	protected static $page_with_default_template;

	/**
	 * Sets up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$page_with_template = $factory->post->create(
			array(
				'post_type' => 'page',
			)
		);
		update_post_meta( self::$page_with_template, '_wp_page_template', 'templates/custom.php' );

		self::$page_with_default_template = $factory->post->create(
			array(
				'post_type' => 'page',
			)
		);
		update_post_meta( self::$page_with_default_template, '_wp_page_template', 'default' );
	}

	/**
	 * Tests that is_page_template() returns false when not on a singular request.
	 *
	 * @ticket 65819
	 */
	public function test_is_page_template_returns_false_when_not_singular() {
		$this->go_to( home_url( '/' ) );

		$this->assertFalse( is_page_template() );
		$this->assertFalse( is_page_template( 'templates/custom.php' ) );
		$this->assertFalse( is_page_template( 'default' ) );
	}

	/**
	 * Tests that is_page_template() without arguments checks if any custom template is assigned.
	 *
	 * @ticket 65819
	 */
	public function test_is_page_template_without_arguments() {
		$this->go_to( get_permalink( self::$page_with_template ) );
		$this->assertTrue( is_page_template() );

		$this->go_to( get_permalink( self::$page_with_default_template ) );
		$this->assertFalse( is_page_template() );
	}

	/**
	 * Tests that is_page_template() matches a specific template filename string.
	 *
	 * @ticket 65819
	 */
	public function test_is_page_template_with_matching_string() {
		$this->go_to( get_permalink( self::$page_with_template ) );

		$this->assertTrue( is_page_template( 'templates/custom.php' ) );
		$this->assertFalse( is_page_template( 'templates/other.php' ) );
	}

	/**
	 * Tests that is_page_template() matches 'default' when no custom template is set.
	 *
	 * @ticket 65819
	 */
	public function test_is_page_template_with_default() {
		$this->go_to( get_permalink( self::$page_with_default_template ) );

		$this->assertTrue( is_page_template( 'default' ) );
		$this->assertFalse( is_page_template( 'templates/custom.php' ) );

		$this->go_to( get_permalink( self::$page_with_template ) );
		$this->assertFalse( is_page_template( 'default' ) );
	}

	/**
	 * Tests that is_page_template() matches against an array of template filenames.
	 *
	 * @ticket 65819
	 */
	public function test_is_page_template_with_array() {
		$this->go_to( get_permalink( self::$page_with_template ) );

		$this->assertTrue( is_page_template( array( 'templates/other.php', 'templates/custom.php' ) ) );
		$this->assertFalse( is_page_template( array( 'templates/other.php', 'templates/another.php' ) ) );

		$this->go_to( get_permalink( self::$page_with_default_template ) );
		$this->assertTrue( is_page_template( array( 'templates/custom.php', 'default' ) ) );
		$this->assertFalse( is_page_template( array( 'templates/custom.php', 'templates/other.php' ) ) );
	}
}
