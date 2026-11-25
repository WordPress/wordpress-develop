<?php
/**
 * Tests for get_the_category_rss().
 *
 * @group feed
 *
 * @covers ::get_the_category_rss
 */
class Tests_Feed_GetTheCategoryRss extends WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Category term.
	 *
	 * @var WP_Term
	 */
	private static $category;

	/**
	 * Create shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$category = $factory->category->create_and_get(
			array(
				'name' => 'News',
				'slug' => 'news',
			)
		);

		self::$post_id = $factory->post->create();
		wp_set_post_categories( self::$post_id, array( self::$category->term_id ) );
	}

	/**
	 * Set the global post for category lookups.
	 */
	public function set_up() {
		parent::set_up();
		$GLOBALS['post'] = get_post( self::$post_id );
		setup_postdata( $GLOBALS['post'] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_rdf_subject_markup() {
		$expected = "\t\t<dc:subject><![CDATA[News]]></dc:subject>\n";

		$this->assertSame( $expected, get_the_category_rss( 'rdf' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_includes_tags_and_deduplicates_shared_names() {
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, array(), 'category' );

		$tag_only = self::factory()->tag->create_and_get(
			array(
				'name' => 'Featured',
				'slug' => 'featured',
			)
		);
		$tag_dup  = self::factory()->tag->create_and_get(
			array(
				'name' => 'News',
				'slug' => 'news-tag',
			)
		);

		wp_set_post_tags( $post_id, array( $tag_only->term_id, $tag_dup->term_id ) );
		wp_set_post_categories( $post_id, array( self::$category->term_id ) );

		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$expected  = "\t\t<category><![CDATA[News]]></category>\n";
		$expected .= "\t\t<category><![CDATA[Featured]]></category>\n";

		$this->assertSame( $expected, get_the_category_rss( 'rss2' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_atom_escapes_special_characters_in_term_attribute() {
		$post_id  = self::factory()->post->create();
		$category = self::factory()->category->create_and_get(
			array(
				'name' => 'News "A" & B',
				'slug' => 'news-a-b',
			)
		);

		wp_set_object_terms( $post_id, array(), 'category' );
		wp_set_post_categories( $post_id, array( $category->term_id ) );

		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		add_filter(
			'pre_option_home',
			static function () {
				return 'https://example.org';
			}
		);

		$expected = '<category scheme="https://example.org" term="News &quot;A&quot; &amp; B" />';

		$this->assertSame( $expected, get_the_category_rss( 'atom' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_list_and_type() {
		$filter = new MockAction();
		add_filter( 'the_category_rss', array( $filter, 'filter_append' ), 10, 2 );

		$result = get_the_category_rss( 'rss2' );

		$this->assertSame( "\t\t<category><![CDATA[News]]></category>\n_append", $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame(
			array( "\t\t<category><![CDATA[News]]></category>\n", 'rss2' ),
			$filter->get_args()[0]
		);
	}
}
