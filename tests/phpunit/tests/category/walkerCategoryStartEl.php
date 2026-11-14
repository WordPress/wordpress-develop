<?php

/**
 * Tests for Walker_Category::start_el().
 *
 * @group taxonomy
 * @group category
 * @group walker
 *
 * @covers Walker_Category::start_el
 */
class Tests_Category_WalkerCategoryStartEl extends WP_UnitTestCase {

	/**
	 * Walker used in the tests.
	 *
	 * @var Walker_Category
	 */
	protected $walker;

	public function set_up() {
		parent::set_up();

		$this->walker = new Walker_Category();
	}

	/**
	 * Returns the arguments of the walker, with the defaults of wp_list_categories() that the method reads.
	 *
	 * @param array $args Optional. Arguments to change.
	 * @return array The arguments.
	 */
	private function get_args( $args = array() ) {
		return array_merge(
			array(
				'use_desc_for_title' => 0,
				'style'              => 'list',
				'show_count'         => 0,
				'feed'               => '',
				'feed_image'         => '',
				'feed_type'          => '',
				'current_category'   => 0,
			),
			$args
		);
	}

	/**
	 * Creates a category.
	 *
	 * @param array $args Optional. Arguments of the category.
	 * @return WP_Term The category.
	 */
	private function create_category( $args = array() ) {
		return self::factory()->category->create_and_get( $args );
	}

	/**
	 * Returns the output of the method.
	 *
	 * @param WP_Term $category Category.
	 * @param array   $args     Optional. Arguments of the walker.
	 * @param int     $depth    Optional. Depth of the category.
	 * @return string The output.
	 */
	private function get_output( $category, $args = array(), $depth = 0 ) {
		$output = '';

		$this->walker->start_el( $output, $category, $depth, $this->get_args( $args ) );

		return $output;
	}

	/**
	 * Returns the links of some HTML.
	 *
	 * @param string $html HTML.
	 * @return array[] The links, each with the attributes and the content.
	 */
	private function get_links( $html ) {
		preg_match_all( '#<a\b([^>]*)>(.*?)</a>#s', $html, $matches, PREG_SET_ORDER );

		$links = array();
		foreach ( $matches as $match ) {
			$processor = new WP_HTML_Tag_Processor( '<a' . $match[1] . '>' );
			$processor->next_tag( 'a' );

			$links[] = array(
				'href'    => $processor->get_attribute( 'href' ),
				'title'   => $processor->get_attribute( 'title' ),
				'content' => $match[2],
			);
		}

		return $links;
	}

	/**
	 * Tests that the link goes to the category, with its name as the content.
	 *
	 * @ticket 66497
	 */
	public function test_should_link_to_the_category_with_its_name() {
		$category = $this->create_category( array( 'name' => 'Walker Category' ) );

		$links = $this->get_links( $this->get_output( $category ) );

		$this->assertCount( 1, $links );
		$this->assertSame( get_term_link( $category ), $links[0]['href'] );
		$this->assertSame( 'Walker Category', $links[0]['content'] );
		$this->assertNull( $links[0]['title'] );
	}

	/**
	 * Tests that the name is escaped, and that the filter gets the escaped name and the category.
	 *
	 * @ticket 66497
	 */
	public function test_should_pass_the_escaped_name_and_the_category_to_the_list_cats_filter() {
		$category       = $this->create_category();
		$category->name = 'Fish & <i>Chips</i>';

		$received = null;
		add_filter(
			'list_cats',
			static function ( $name, $term ) use ( &$received ) {
				$received = array( $name, $term );

				return $name;
			},
			10,
			2
		);

		$links = $this->get_links( $this->get_output( $category ) );

		$this->assertSame( 'Fish &amp; &lt;i&gt;Chips&lt;/i&gt;', $received[0] );
		$this->assertSame( $category, $received[1] );
		$this->assertSame( 'Fish &amp; &lt;i&gt;Chips&lt;/i&gt;', $links[0]['content'] );
	}

	/**
	 * Tests that the result of the `list_cats` filter is the content of the link.
	 *
	 * @ticket 66497
	 */
	public function test_should_use_the_result_of_the_list_cats_filter() {
		$category = $this->create_category();

		add_filter(
			'list_cats',
			static function () {
				return 'Filtered name';
			}
		);

		$links = $this->get_links( $this->get_output( $category ) );

		$this->assertSame( 'Filtered name', $links[0]['content'] );
	}

	/**
	 * Tests that nothing is output when the name is empty, for each style.
	 *
	 * @ticket 66497
	 *
	 * @dataProvider data_styles
	 *
	 * @param string $style Style of the output.
	 */
	public function test_should_not_output_an_element_for_an_empty_name( $style ) {
		$category = $this->create_category();

		add_filter( 'list_cats', '__return_empty_string' );

		$this->assertSame( '', $this->get_output( $category, array( 'style' => $style ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_styles() {
		return array(
			'list'  => array( 'list' ),
			'break' => array( 'none' ),
		);
	}

	/**
	 * Tests that the description is the title of the link, without tags, when it is asked for.
	 *
	 * @ticket 66497
	 */
	public function test_should_use_the_description_without_tags_as_the_title_when_asked_for() {
		$category = $this->create_category( array( 'description' => '<b>Bold</b> & "quoted"' ) );

		$links = $this->get_links( $this->get_output( $category, array( 'use_desc_for_title' => 1 ) ) );

		$this->assertSame( 'Bold & "quoted"', $links[0]['title'] );
	}

	/**
	 * Tests that the description is not used as the title unless it is asked for.
	 *
	 * @ticket 66497
	 */
	public function test_should_not_use_the_description_as_the_title_unless_asked_for() {
		$category = $this->create_category( array( 'description' => 'A description' ) );

		$links = $this->get_links( $this->get_output( $category, array( 'use_desc_for_title' => 0 ) ) );

		$this->assertNull( $links[0]['title'] );
	}

	/**
	 * Tests that there is no title for an empty description.
	 *
	 * @ticket 66497
	 */
	public function test_should_not_add_a_title_for_an_empty_description() {
		$category = $this->create_category( array( 'description' => '' ) );

		$links = $this->get_links( $this->get_output( $category, array( 'use_desc_for_title' => 1 ) ) );

		$this->assertNull( $links[0]['title'] );
	}

	/**
	 * Tests that the description goes through the `category_description` filter, with the category.
	 *
	 * @ticket 66497
	 */
	public function test_should_apply_the_category_description_filter_to_the_title() {
		$category = $this->create_category( array( 'description' => 'Original' ) );

		$received = null;
		add_filter(
			'category_description',
			static function ( $description, $term ) use ( &$received ) {
				$received = array( $description, $term );

				return '<em>Filtered</em> description';
			},
			10,
			2
		);

		$links = $this->get_links( $this->get_output( $category, array( 'use_desc_for_title' => 1 ) ) );

		$this->assertSame( 'Original', $received[0] );
		$this->assertSame( $category, $received[1] );
		$this->assertSame( 'Filtered description', $links[0]['title'] );
	}

	/**
	 * Tests that the filter of the attributes of the link gets the attributes, the category, the depth, the arguments and the current ID.
	 *
	 * @ticket 66497
	 */
	public function test_should_pass_the_arguments_to_the_category_list_link_attributes_filter() {
		$category = $this->create_category( array( 'description' => '' ) );
		$args     = $this->get_args( array( 'use_desc_for_title' => 1 ) );

		$received = null;
		add_filter(
			'category_list_link_attributes',
			static function ( $atts, $term, $depth, $filter_args, $current_object_id ) use ( &$received ) {
				$received = array( $atts, $term, $depth, $filter_args, $current_object_id );

				$atts['data-name'] = 'a value';
				$atts['rel']       = 'tag';

				return $atts;
			},
			10,
			5
		);

		$output = '';
		$this->walker->start_el( $output, $category, 2, $args, 17 );

		$this->assertSame( array( 'href' => get_term_link( $category ) ), $received[0] );
		$this->assertSame( $category, $received[1] );
		$this->assertSame( 2, $received[2] );
		$this->assertSame( $args, $received[3] );
		$this->assertSame( 17, $received[4] );
		$this->assertStringContainsString( ' data-name="a value"', $output );
		$this->assertStringContainsString( ' rel="tag"', $output );
	}

	/**
	 * Tests that the values of the attributes are escaped.
	 *
	 * @ticket 66497
	 */
	public function test_should_escape_the_attributes_of_the_link() {
		$category = $this->create_category();

		add_filter(
			'category_list_link_attributes',
			static function ( $atts ) {
				$atts['data-name'] = '"><script>alert(1)</script>';

				return $atts;
			}
		);

		$output = $this->get_output( $category );

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( 'data-name="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $output );
	}

	/**
	 * Tests that the address of the link is escaped as a URL.
	 *
	 * @ticket 66497
	 */
	public function test_should_escape_the_address_of_the_link_as_a_url() {
		$category = $this->create_category();

		add_filter(
			'term_link',
			static function () {
				return 'https://example.org/?a=1&b="2"';
			}
		);

		$output = $this->get_output( $category );

		$this->assertStringContainsString( 'href="' . esc_url( 'https://example.org/?a=1&b="2"' ) . '"', $output );
		$this->assertStringContainsString( 'href="https://example.org/?a=1&#038;b=2"', $output, 'The ampersand should be encoded and the quotes removed.' );
	}

	/**
	 * Tests that the classes of the item are the ones of the `category_css_class` filter, with their arguments.
	 *
	 * @ticket 66497
	 */
	public function test_should_pass_the_arguments_to_the_category_css_class_filter() {
		$category = $this->create_category();
		$args     = $this->get_args();

		$received = null;
		add_filter(
			'category_css_class',
			static function ( $classes, $term, $depth, $filter_args ) use ( &$received ) {
				$received = array( $classes, $term, $depth, $filter_args );

				$classes[] = 'added-class';

				return $classes;
			},
			10,
			4
		);

		$output = '';
		$this->walker->start_el( $output, $category, 3, $args );

		$this->assertSame( array( 'cat-item', 'cat-item-' . $category->term_id ), $received[0] );
		$this->assertSame( $category, $received[1] );
		$this->assertSame( 3, $received[2] );
		$this->assertSame( $args, $received[3] );
		$this->assertStringContainsString( ' class="cat-item cat-item-' . $category->term_id . ' added-class"', $output );
	}

	/**
	 * Tests that the item has no class attribute when the filter removes the classes, and that the classes are escaped.
	 *
	 * @ticket 66497
	 */
	public function test_should_omit_or_escape_the_classes_of_the_filter() {
		$category = $this->create_category();

		add_filter( 'category_css_class', '__return_empty_array' );

		$this->assertStringNotContainsString( 'class=', $this->get_output( $category ) );

		remove_filter( 'category_css_class', '__return_empty_array' );
		add_filter(
			'category_css_class',
			static function () {
				return array( 'a"b' );
			}
		);

		$this->assertStringContainsString( ' class="a&quot;b"', $this->get_output( $category ) );
	}

	/**
	 * Tests that the item of the list has the classes of the category.
	 *
	 * @ticket 66497
	 */
	public function test_should_output_a_list_item_with_the_classes_of_the_category() {
		$category = $this->create_category();

		$output = $this->get_output( $category );

		$this->assertMatchesRegularExpression( '#^\s*<li class="cat-item cat-item-' . $category->term_id . '"><a #', $output );
		$this->assertStringNotContainsString( '</li>', $output, 'The item should be closed by end_el().' );
	}

	/**
	 * Tests that the post count is shown, with a number format, only when it is asked for.
	 *
	 * @ticket 66497
	 *
	 * @dataProvider data_counts
	 *
	 * @param int    $count    Count of the category.
	 * @param string $expected Expected text after the link.
	 */
	public function test_should_show_the_post_count_when_asked_for( $count, $expected ) {
		$category        = $this->create_category();
		$category->count = $count;

		$this->assertStringContainsString( '</a>' . $expected . "\n", $this->get_output( $category, array( 'show_count' => 1 ) ) );
		$this->assertStringNotContainsString( '(' . number_format_i18n( $count ) . ')', $this->get_output( $category, array( 'show_count' => 0 ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_counts() {
		return array(
			'none'                => array( 0, ' (0)' ),
			'one'                 => array( 1, ' (1)' ),
			'a thousand and more' => array( 1234, ' (1,234)' ),
		);
	}

	/**
	 * Tests that the feed of the category is linked, with a text.
	 *
	 * @ticket 66497
	 */
	public function test_should_link_to_the_feed_with_a_text() {
		$category = $this->create_category();

		$output = $this->get_output( $category, array( 'feed' => 'RSS' ) );
		$links  = $this->get_links( $output );

		$this->assertCount( 2, $links );
		$this->assertSame( get_term_feed_link( $category, $category->taxonomy, '' ), $links[1]['href'] );
		$this->assertSame( 'RSS', $links[1]['content'] );
		$this->assertStringContainsString( ' (<a href=', $output, 'The link to the feed should be in parentheses.' );
		$this->assertStringContainsString( '</a>)', $output );
	}

	/**
	 * Tests that the feed of the category is linked, with an image, and without parentheses.
	 *
	 * @ticket 66497
	 */
	public function test_should_link_to_the_feed_with_an_image() {
		$category = $this->create_category( array( 'name' => 'Imaged' ) );

		$output = $this->get_output( $category, array( 'feed_image' => 'https://example.org/feed.png' ) );
		$links  = $this->get_links( $output );

		$this->assertCount( 2, $links );
		$this->assertSame( get_term_feed_link( $category, $category->taxonomy, '' ), $links[1]['href'] );
		$this->assertSame( "<img src='https://example.org/feed.png' alt=\"Feed for all posts filed under Imaged\" />", $links[1]['content'] );
		$this->assertStringNotContainsString( '(<a href=', $output );
	}

	/**
	 * Tests that the text of the feed is the alternative text of the image when both are used.
	 *
	 * @ticket 66497
	 */
	public function test_should_use_the_feed_text_as_the_alternative_text_of_the_image() {
		$category = $this->create_category();

		$links = $this->get_links(
			$this->get_output(
				$category,
				array(
					'feed'       => 'Subscribe',
					'feed_image' => 'https://example.org/feed.png',
				)
			)
		);

		$this->assertStringContainsString( 'alt="Subscribe"', $links[1]['content'] );
	}

	/**
	 * Tests that the type of the feed is used in the link of the feed.
	 *
	 * @ticket 66497
	 */
	public function test_should_use_the_feed_type() {
		$category = $this->create_category();

		$links = $this->get_links(
			$this->get_output(
				$category,
				array(
					'feed'      => 'Atom',
					'feed_type' => 'atom',
				)
			)
		);

		$this->assertSame( get_term_feed_link( $category, $category->taxonomy, 'atom' ), $links[1]['href'] );
		$this->assertNotSame( get_term_feed_link( $category, $category->taxonomy, 'rss2' ), $links[1]['href'] );
	}

	/**
	 * Tests that there is no link to the feed unless it is asked for.
	 *
	 * @ticket 66497
	 */
	public function test_should_not_link_to_the_feed_unless_asked_for() {
		$category = $this->create_category();

		$this->assertCount( 1, $this->get_links( $this->get_output( $category ) ) );
	}

	/**
	 * Tests the output of a style that is not a list, with a separator.
	 *
	 * @ticket 66497
	 */
	public function test_should_output_the_link_and_the_separator_for_styles_that_are_not_lists() {
		$category = $this->create_category( array( 'name' => 'Separated' ) );

		$output = $this->get_output(
			$category,
			array(
				'style'     => 'none',
				'separator' => ' | ',
			)
		);

		$this->assertMatchesRegularExpression( '#^\s*<a [^>]*>Separated</a> \| \s*$#', $output );
		$this->assertStringNotContainsString( '<li', $output );
	}

	/**
	 * Tests the output of a style that is not a list, without a separator.
	 *
	 * @ticket 66497
	 */
	public function test_should_output_the_link_and_a_line_break_for_styles_that_are_not_lists_without_a_separator() {
		$category = $this->create_category( array( 'name' => 'Broken' ) );

		$output = $this->get_output( $category, array( 'style' => 'none' ) );

		$this->assertMatchesRegularExpression( '#^\s*<a [^>]*>Broken</a><br />\s*$#', $output );
		$this->assertStringNotContainsString( '<li', $output );
	}

	/**
	 * Tests that the output is added to the existing output.
	 *
	 * @ticket 66497
	 */
	public function test_should_add_to_the_existing_output() {
		$category = $this->create_category();
		$output   = 'Existing output';

		$this->walker->start_el( $output, $category, 0, $this->get_args() );

		$this->assertStringStartsWith( 'Existing output', $output );
		$this->assertGreaterThan( strlen( 'Existing output' ), strlen( $output ) );
	}
}
