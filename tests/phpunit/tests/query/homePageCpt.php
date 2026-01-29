<?php
/**
* @group: query
* @covers ::parse_query
* @ticket:19958
*/
class Tests_Query_HomePageCpt extends WP_UnitTestCase {
	private $cpt_allowed = 'cpt_allowed';
	private $cpt_not_allowed = 'cpt_not_allowed';

	public function set_up() {
		parent::set_up();
		register_post_type(
			$this->cpt_allowed,
			array(
				'public'                 => true,
				'show_in_home_page_list' => true,
			)
		);
		register_post_type(
			$this->cpt_not_allowed,
			array(
				'public'                 => true,
				'show_in_home_page_list' => false,
			)
		);
	}

	public function tear_down() {
		unregister_post_type( $this->cpt_allowed );
		unregister_post_type( $this->cpt_not_allowed );
		parent::tear_down();
	}

	/**
	 * @ticket 00000
	 */
	public function test_home_page_defaults_to_posts() {
		$this->go_to( home_url( '/' ) );
		$this->assertTrue( is_home() );
		$this->assertSame( 'post', get_query_var( 'post_type' ) );
	}

	/**
	 * @ticket 00000
	 */
	public function test_home_page_with_allowed_cpt() {
		update_option( 'show_on_front', $this->cpt_allowed );

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( is_home() );
		$this->assertSame( $this->cpt_allowed, get_query_var( 'post_type' ) );
	}

	/**
	 * @ticket 00000
	 */
	public function test_home_page_with_not_allowed_cpt_should_not_set_post_type() {
		update_option( 'show_on_front', $this->cpt_not_allowed );

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( is_home() );
		// It should NOT be our CPT. WP default for is_home when not 'page' is usually empty post_type (which means 'post' later)
		$this->assertNotSame( $this->cpt_not_allowed, get_query_var( 'post_type' ) );
	}

	/**
	 * @ticket 00000
	 */
	public function test_post_types_allowed_on_home_page_filter() {
		update_option( 'show_on_front', $this->cpt_not_allowed );

		add_filter( 'post_types_allowed_on_home_page', array( $this, 'filter_allowed_cpts' ) );

		$this->go_to( home_url( '/' ) );

		$this->assertSame( $this->cpt_not_allowed, get_query_var( 'post_type' ) );

		remove_filter( 'post_types_allowed_on_home_page', array( $this, 'filter_allowed_cpts' ) );
	}

	public function filter_allowed_cpts( $allowed ) {
		$allowed[] = $this->cpt_not_allowed;
		return $allowed;
	}

	/**
	 * @ticket 00000
	 */
	public function test_home_page_with_posts_option_explicit() {
		update_option( 'show_on_front', 'posts' );

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( is_home() );
		$this->assertSame( 'post', get_query_var( 'post_type' ) );
	}
}
