<?php
/**
 * @group taxonomy
 * @group term
 */
class Tests_Term_Template extends WP_UnitTestCase {
	protected static $post_id;
	protected static $term_id;
	protected static $second_term_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id        = $factory->post->create();
		self::$term_id        = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Test Category',
			)
		);
		self::$second_term_id = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Another Category',
			)
		);
		wp_set_post_terms( self::$post_id, array( self::$term_id, self::$second_term_id ), 'category' );
	}

	/**
	 * Tests that get_the_term_list() correctly outputs terms using the default template.
	 *
	 * @ticket 30705
	 *
	 * @covers ::get_the_term_list
	 */
	public function test_get_the_term_list_default_template() {
		$list = get_the_term_list( self::$post_id, 'category', '', ', ', '' );

		$this->assertMatchesRegularExpression(
			'/<a href="[^"]*" rel="tag">Test Category<\/a>/',
			$list
		);
		$this->assertMatchesRegularExpression(
			'/<a href="[^"]*" rel="tag">Another Category<\/a>/',
			$list
		);
	}

	/**
	 * Tests that get_the_term_list() correctly applies a custom template when provided.
	 *
	 * @ticket 30705
	 *
	 * @covers ::get_the_term_list
	 */
	public function test_get_the_term_list_custom_template() {
		$custom_template = '<span class="term"><a href="%1$s">%2$s</a></span>';
		$list            = get_the_term_list( self::$post_id, 'category', '', ', ', '', $custom_template );

		$this->assertMatchesRegularExpression(
			'/<span class="term"><a href="[^"]*">Test Category<\/a><\/span>/',
			$list
		);
	}

	/**
	 * Tests that get_the_term_list() properly escapes term names with unsafe characters.
	 *
	 * @ticket 30705
	 *
	 * @covers ::get_the_term_list
	 */
	public function test_get_the_term_list_escaping() {
		$unsafe_term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Test & Category <script>alert("xss")</script>',
			)
		);
		wp_set_post_terms( self::$post_id, array( $unsafe_term_id ), 'category' );

		$list = get_the_term_list( self::$post_id, 'category', '', ', ', '' );

		$this->assertStringContainsString( 'Test &amp; Category', $list );
		$this->assertStringNotContainsString( '<script>', $list );
	}
}
