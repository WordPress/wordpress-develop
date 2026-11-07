<?php
/**
 * Tests for the server-side rendering of the Categories List block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderCategories extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * Category with posts.
	 *
	 * @var int
	 */
	private $news_id;

	/**
	 * Child of the category with posts.
	 *
	 * @var int
	 */
	private $child_id;

	/**
	 * Category without posts.
	 *
	 * @var int
	 */
	private $empty_id;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/categories',
			'attrs'     => array(),
		);

		$this->news_id  = self::factory()->category->create( array( 'name' => 'News' ) );
		$this->child_id = self::factory()->category->create(
			array(
				'name'   => 'Local',
				'parent' => $this->news_id,
			)
		);
		$this->empty_id = self::factory()->category->create( array( 'name' => 'Empty' ) );

		self::factory()->post->create_many( 2, array( 'post_category' => array( $this->news_id ) ) );
		self::factory()->post->create( array( 'post_category' => array( $this->child_id ) ) );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The attributes of the block that differ from the defaults.
	 * @param array $context    Optional. The context of the block. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = array() ) {
		$attributes = array_merge( array( 'taxonomy' => 'category' ), $attributes );

		$block = new WP_Block(
			array(
				'blockName' => 'core/categories',
				'attrs'     => $attributes,
			),
			$context
		);

		return render_block_core_categories( $attributes, '', $block );
	}

	/**
	 * Returns the attributes of the first element matching the query.
	 *
	 * @param string       $markup The markup.
	 * @param string|array $query  The tag name or the query of the tag processor.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $markup, $query ) {
		$processor = new WP_HTML_Tag_Processor( $markup );

		if ( ! $processor->next_tag( $query ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Returns the text of the links of the markup.
	 *
	 * @param string $markup The markup.
	 * @return string[] The texts.
	 */
	private function get_link_texts( $markup ) {
		preg_match_all( '/<a [^>]*>(.*?)<\/a>/', $markup, $matches );

		return $matches[1];
	}

	/**
	 * Tests that nothing is rendered for a taxonomy that does not exist.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_render_nothing_for_a_taxonomy_that_does_not_exist() {
		$this->assertSame( '', $this->render( array( 'taxonomy' => 'missing-taxonomy' ) ) );
	}

	/**
	 * Tests the list of the categories.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_list_the_categories() {
		$markup = $this->render();
		$list   = $this->get_element( $markup, 'ul' );

		$this->assertStringStartsWith( '<ul ', $markup );
		$this->assertStringEndsWith( '</ul>', $markup );
		$this->assertContains( 'wp-block-categories-list', explode( ' ', $list['class'] ) );
		$this->assertContains( 'wp-block-categories-taxonomy-category', explode( ' ', $list['class'] ) );
		$this->assertSame( array( 'Local', 'News' ), $this->get_link_texts( $markup ), 'Empty categories are left out, and the others are ordered by name.' );
		$this->assertStringContainsString( 'cat-item-' . $this->news_id, $markup );
		$this->assertStringNotContainsString( 'children', $markup, 'The list should be flat.' );
	}

	/**
	 * Tests the post counts.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_show_the_post_counts() {
		$markup = $this->render( array( 'showPostCounts' => true ) );

		$this->assertStringContainsString( 'News</a> (2)', $markup );
		$this->assertStringContainsString( 'Local</a> (1)', $markup );
		$this->assertStringNotContainsString( ' (', $this->render() );
		$this->assertStringNotContainsString( ' (', $this->render( array( 'showPostCounts' => false ) ) );
	}

	/**
	 * Tests the hierarchy.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_show_the_hierarchy() {
		$markup = $this->render( array( 'showHierarchy' => true ) );

		$this->assertSame( array( 'News', 'Local' ), $this->get_link_texts( $markup ) );
		$this->assertMatchesRegularExpression( '/News<\/a>.*<ul class=\'children\'>.*Local<\/a>/s', $markup );
	}

	/**
	 * Tests the empty categories.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_show_the_empty_categories() {
		$this->assertSame( array( 'Empty', 'Local', 'News' ), array_slice( $this->get_link_texts( $this->render( array( 'showEmpty' => true ) ) ), 0, 3 ) );
		$this->assertNotContains( 'Empty', $this->get_link_texts( $this->render( array( 'showEmpty' => false ) ) ) );
	}

	/**
	 * Tests the categories of the top level.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_show_only_the_top_level() {
		$this->assertSame( array( 'News' ), $this->get_link_texts( $this->render( array( 'showOnlyTopLevel' => true ) ) ) );
		$this->assertSame( array( 'Local', 'News' ), $this->get_link_texts( $this->render( array( 'showOnlyTopLevel' => false ) ) ) );
	}

	/**
	 * Tests the message when there are no categories.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_show_the_message_when_there_are_no_categories() {
		$markup = $this->render( array( 'taxonomy' => 'post_tag' ) );

		$this->assertStringContainsString( 'No tags', $markup );
		$this->assertContains( 'wp-block-categories-taxonomy-post_tag', explode( ' ', $this->get_element( $markup, 'ul' )['class'] ) );
	}

	/**
	 * Tests the links of the list with the enhanced pagination.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_navigate_the_links_with_the_enhanced_pagination() {
		$markup = $this->render( array(), array( 'enhancedPagination' => true ) );

		$this->assertSame( 2, substr_count( $markup, 'data-wp-on--click="core/query::actions.navigate"' ) );
		$this->assertStringNotContainsString( 'data-wp-on--click', $this->render( array(), array( 'enhancedPagination' => false ) ) );
		$this->assertStringNotContainsString( 'data-wp-on--click', $this->render() );
	}

	/**
	 * Tests the dropdown.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_render_the_dropdown() {
		$markup = $this->render( array( 'displayAsDropdown' => true ) );

		$wrapper = $this->get_element( $markup, 'div' );
		$label   = $this->get_element( $markup, 'label' );
		$select  = $this->get_element( $markup, 'select' );

		$this->assertContains( 'wp-block-categories-dropdown', explode( ' ', $wrapper['class'] ) );
		$this->assertContains( 'wp-block-categories-taxonomy-category', explode( ' ', $wrapper['class'] ) );
		$this->assertMatchesRegularExpression( '/^wp-block-categories-\d+$/', $select['id'] );
		$this->assertSame( $select['id'], $label['for'] );
		$this->assertSame( 'category_name', $select['name'] );
		$this->assertStringContainsString( "<option value='-1'>Select Category</option>", $markup );
		$this->assertStringContainsString( 'value="news"', $markup );
		$this->assertStringContainsString( 'value="local"', $markup );
		$this->assertStringNotContainsString( 'value="empty"', $markup );
		$this->assertStringNotContainsString( '<ul', $markup );
	}

	/**
	 * Tests the label of the dropdown.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_render_the_label_of_the_dropdown() {
		$hidden = $this->render( array( 'displayAsDropdown' => true ) );
		$shown  = $this->render(
			array(
				'displayAsDropdown' => true,
				'showLabel'         => true,
				'label'             => '<b>Pick</b> one<script>alert(1)</script>',
			)
		);

		$this->assertSame( 'wp-block-categories__label screen-reader-text', $this->get_element( $hidden, 'label' )['class'] );
		$this->assertStringContainsString( '>Categories</label>', $hidden, 'The default label is the label of the taxonomy.' );
		$this->assertSame( 'wp-block-categories__label', $this->get_element( $shown, 'label' )['class'] );
		$this->assertStringContainsString( '><b>Pick</b> one</label>', $shown );
		$this->assertStringNotContainsString( '<script>alert', $shown );
	}

	/**
	 * Tests the selected category of the dropdown.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_select_the_current_category_in_the_dropdown() {
		set_query_var( 'category_name', 'local' );

		$markup = $this->render( array( 'displayAsDropdown' => true ) );

		$this->assertMatchesRegularExpression( '/<option [^>]*value="local" selected="selected"|<option [^>]*selected="selected"[^>]*value="local"/', $markup );
		$this->assertDoesNotMatchRegularExpression( '/value="news"[^>]*selected/', $markup );
	}

	/**
	 * Tests the post counts, the hierarchy and the empty categories in the dropdown.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_apply_the_options_to_the_dropdown() {
		$markup = $this->render(
			array(
				'displayAsDropdown' => true,
				'showPostCounts'    => true,
				'showHierarchy'     => true,
				'showEmpty'         => true,
			)
		);

		$this->assertStringContainsString( 'News&nbsp;&nbsp;(3)', $markup );
		$this->assertStringContainsString( '&nbsp;&nbsp;&nbsp;Local&nbsp;&nbsp;(1)', $markup );
		$this->assertStringContainsString( 'value="empty"', $markup );

		$top = $this->render(
			array(
				'displayAsDropdown' => true,
				'showOnlyTopLevel'  => true,
			)
		);

		$this->assertStringContainsString( 'value="news"', $top );
		$this->assertStringNotContainsString( 'value="local"', $top );
	}

	/**
	 * Tests the dropdown of another taxonomy.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_render_the_dropdown_of_another_taxonomy() {
		$tag_id = self::factory()->tag->create( array( 'name' => 'A tag' ) );
		self::factory()->post->create( array( 'tags_input' => array( $tag_id ) ) );

		$markup = $this->render(
			array(
				'taxonomy'          => 'post_tag',
				'displayAsDropdown' => true,
			)
		);

		$this->assertSame( 'tag', $this->get_element( $markup, 'select' )['name'] );
		$this->assertStringContainsString( "<option value='-1'>Select Tag</option>", $markup );
		$this->assertStringContainsString( '>Tags</label>', $markup );
		$this->assertStringContainsString( 'value="a-tag"', $markup );
	}

	/**
	 * Tests that each dropdown gets its own ID.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_give_each_dropdown_its_own_id() {
		$first  = $this->get_element( $this->render( array( 'displayAsDropdown' => true ) ), 'select' );
		$second = $this->get_element( $this->render( array( 'displayAsDropdown' => true ) ), 'select' );

		$this->assertNotSame( $first['id'], $second['id'] );
	}

	/**
	 * Tests the script after the dropdown.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_add_the_script_after_the_dropdown() {
		$markup = $this->render( array( 'displayAsDropdown' => true ) );
		$id     = $this->get_element( $markup, 'select' )['id'];

		$this->assertMatchesRegularExpression( '/<\/select><script/', $markup );
		$this->assertSame( 1, substr_count( $markup, '<script' ) );
		$this->assertStringContainsString( '} )( ["' . $id . '","' . home_url() . '"] );', $markup );
		$this->assertMatchesRegularExpression( '/<\/script>\s*<\/div>$/', $markup );
		$this->assertStringNotContainsString( '<script', $this->render() );
	}

	/**
	 * Tests that the script is not added in the admin.
	 *
	 * @ticket 66594
	 *
	 * @covers ::render_block_core_categories
	 */
	public function test_should_not_add_the_script_in_the_admin() {
		set_current_screen( 'dashboard' );

		$markup = $this->render( array( 'displayAsDropdown' => true ) );

		set_current_screen( 'front' );

		$this->assertStringContainsString( '<select ', $markup );
		$this->assertStringNotContainsString( '<script', $markup );
	}

	/**
	 * Tests the script of the dropdown.
	 *
	 * @ticket 66594
	 *
	 * @covers ::build_dropdown_script_block_core_categories
	 */
	public function test_build_dropdown_script() {
		$script = build_dropdown_script_block_core_categories( 'the-id' );

		$this->assertStringStartsWith( '<script>', $script );
		$this->assertStringContainsString( '} )( ["the-id","' . home_url() . '"] );', $script );
		$this->assertStringContainsString( '//# sourceURL=build_dropdown_script_block_core_categories', $script );
		$this->assertStringContainsString( "dropdown.value !== '-1'", $script );
	}

	/**
	 * Tests that the values in the script are escaped.
	 *
	 * @ticket 66594
	 *
	 * @covers ::build_dropdown_script_block_core_categories
	 */
	public function test_build_dropdown_script_escapes_the_values() {
		$script = build_dropdown_script_block_core_categories( '</script><b>"x"' );

		$this->assertStringNotContainsString( '</script><b>', $script );
		$this->assertStringContainsString( '\u003C/script\u003E\u003Cb\u003E\"x\"', $script );
		$this->assertStringContainsString( '"' . home_url() . '"', $script, 'The slashes of the home URL should not be escaped.' );
	}
}
