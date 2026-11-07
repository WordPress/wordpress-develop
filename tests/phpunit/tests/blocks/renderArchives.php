<?php
/**
 * Tests for the server-side rendering of the Archives block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderArchives extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/archives',
			'attrs'     => array(),
		);

		// Posts of two years, three months, and two days of a month.
		foreach ( array( '2020-03-15 10:00:00', '2020-03-20 10:00:00', '2021-05-01 10:00:00' ) as $date ) {
			self::factory()->post->create(
				array(
					'post_date'  => $date,
					'post_title' => 'Post of ' . substr( $date, 0, 10 ),
				)
			);
		}
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Returns the text of the links of the markup.
	 *
	 * @param string $markup The markup.
	 * @return string[] The texts, decoded.
	 */
	private function get_link_texts( $markup ) {
		preg_match_all( '/<a href=\'[^\']*\'>(.*?)<\/a>/', $markup, $matches );

		return array_map( 'html_entity_decode', $matches[1] );
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
	 * Tests the list of the archives of each type.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 *
	 * @dataProvider data_list_types
	 *
	 * @param array    $attributes The attributes of the block.
	 * @param string[] $expected   The expected texts of the links.
	 */
	public function test_should_list_the_archives( $attributes, $expected ) {
		$markup = render_block_core_archives( $attributes );

		$this->assertStringStartsWith( '<ul ', $markup );
		$this->assertStringEndsWith( '</ul>', $markup );
		$this->assertContains( 'wp-block-archives-list', explode( ' ', $this->get_element( $markup, 'ul' )['class'] ) );
		$this->assertSame( $expected, $this->get_link_texts( $markup ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_list_types() {
		return array(
			'default is monthly' => array( array(), array( 'May 2021', 'March 2020' ) ),
			'monthly'            => array( array( 'type' => 'monthly' ), array( 'May 2021', 'March 2020' ) ),
			'yearly'             => array( array( 'type' => 'yearly' ), array( '2021', '2020' ) ),
			'daily'              => array( array( 'type' => 'daily' ), array( 'May 1, 2021', 'March 20, 2020', 'March 15, 2020' ) ),
			'by post'            => array( array( 'type' => 'postbypost' ), array( 'Post of 2021-05-01', 'Post of 2020-03-20', 'Post of 2020-03-15' ) ),
			'alphabetical'       => array( array( 'type' => 'alpha' ), array( 'Post of 2020-03-15', 'Post of 2020-03-20', 'Post of 2021-05-01' ) ),
		);
	}

	/**
	 * Tests the post counts.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 */
	public function test_should_show_the_post_counts() {
		$markup = render_block_core_archives( array( 'showPostCounts' => true ) );

		$this->assertMatchesRegularExpression( '/March 2020<\/a>&nbsp;\(2\)/', $markup );
		$this->assertMatchesRegularExpression( '/May 2021<\/a>&nbsp;\(1\)/', $markup );

		$this->assertStringNotContainsString( '&nbsp;(', render_block_core_archives( array( 'showPostCounts' => false ) ) );
		$this->assertStringNotContainsString( '&nbsp;(', render_block_core_archives( array() ) );
	}

	/**
	 * Tests the message when there are no archives.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 */
	public function test_should_show_a_message_when_there_are_no_archives() {
		foreach ( get_posts(
			array(
				'numberposts' => -1,
				'post_status' => 'any',
			)
		) as $post ) {
			wp_delete_post( $post->ID, true );
		}

		$markup = render_block_core_archives( array() );

		$this->assertStringStartsWith( '<div ', $markup );
		$this->assertStringEndsWith( '>No archives to show.</div>', $markup );
		$this->assertContains( 'wp-block-archives-list', explode( ' ', $this->get_element( $markup, 'div' )['class'] ) );
	}

	/**
	 * Tests the filter of the arguments of the list.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 */
	public function test_should_filter_the_arguments_of_the_list() {
		$received = null;

		add_filter(
			'widget_archives_args',
			static function ( $args ) use ( &$received ) {
				$received = $args;

				$args['type'] = 'yearly';

				return $args;
			}
		);

		$markup = render_block_core_archives(
			array(
				'type'           => 'monthly',
				'showPostCounts' => true,
			)
		);

		$this->assertSame(
			array(
				'type'            => 'monthly',
				'show_post_count' => true,
			),
			$received
		);
		$this->assertSame( array( '2021', '2020' ), $this->get_link_texts( $markup ) );
	}

	/**
	 * Tests the dropdown.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 */
	public function test_should_render_the_dropdown() {
		$markup = render_block_core_archives( array( 'displayAsDropdown' => true ) );

		$wrapper = $this->get_element( $markup, 'div' );
		$label   = $this->get_element( $markup, 'label' );
		$select  = $this->get_element( $markup, 'select' );

		$this->assertContains( 'wp-block-archives-dropdown', explode( ' ', $wrapper['class'] ) );
		$this->assertMatchesRegularExpression( '/^wp-block-archives-\d+$/', $select['id'] );
		$this->assertSame( $select['id'], $label['for'] );
		$this->assertSame( 'archive-dropdown', $select['name'] );
		$this->assertStringContainsString( '>Archives</label>', $markup );
		$this->assertStringContainsString( '<option value="">Select Month</option>', $markup );
		$this->assertMatchesRegularExpression( "/<option value='http:\\/\\/example.org\\/2021\\/05\\/'> May 2021 <\\/option>/", $markup );
		$this->assertMatchesRegularExpression( "/<option value='http:\\/\\/example.org\\/2020\\/03\\/'> March 2020 <\\/option>/", $markup );
		$this->assertStringNotContainsString( '<ul', $markup );
	}

	/**
	 * Tests the label of the dropdown for each type.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 *
	 * @dataProvider data_dropdown_labels
	 *
	 * @param string|null $type     The type of the archives.
	 * @param string      $expected The expected first option.
	 */
	public function test_should_label_the_dropdown_by_type( $type, $expected ) {
		$attributes = array( 'displayAsDropdown' => true );

		if ( null !== $type ) {
			$attributes['type'] = $type;
		}

		$this->assertStringContainsString( '<option value="">' . $expected . '</option>', render_block_core_archives( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_dropdown_labels() {
		return array(
			'default' => array( null, 'Select Month' ),
			'yearly'  => array( 'yearly', 'Select Year' ),
			'monthly' => array( 'monthly', 'Select Month' ),
			'daily'   => array( 'daily', 'Select Day' ),
			'weekly'  => array( 'weekly', 'Select Week' ),
			'by post' => array( 'postbypost', 'Select Post' ),
			'alpha'   => array( 'alpha', 'Select Post' ),
		);
	}

	/**
	 * Tests the label of the dropdown for the type that the filter gives.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 */
	public function test_should_label_the_dropdown_by_the_type_of_the_filter() {
		$received = null;

		add_filter(
			'widget_archives_dropdown_args',
			static function ( $args ) use ( &$received ) {
				$received = $args;

				$args['type'] = 'yearly';

				return $args;
			}
		);

		$markup = render_block_core_archives(
			array(
				'displayAsDropdown' => true,
				'showPostCounts'    => true,
			)
		);

		$this->assertSame(
			array(
				'type'            => 'monthly',
				'format'          => 'option',
				'show_post_count' => true,
			),
			$received
		);
		$this->assertStringContainsString( '<option value="">Select Year</option>', $markup );
		$this->assertStringContainsString( "<option value='http://example.org/2021/'> 2021 &nbsp;(1)</option>", $markup );
	}

	/**
	 * Tests the post counts in the dropdown.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 */
	public function test_should_show_the_post_counts_in_the_dropdown() {
		$markup = render_block_core_archives(
			array(
				'displayAsDropdown' => true,
				'showPostCounts'    => true,
			)
		);

		$this->assertStringContainsString( 'March 2020 &nbsp;(2)', $markup );
		$this->assertStringNotContainsString( '&nbsp;(', render_block_core_archives( array( 'displayAsDropdown' => true ) ) );
	}

	/**
	 * Tests the visibility of the label of the dropdown.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 */
	public function test_should_hide_the_label_of_the_dropdown_unless_it_is_shown() {
		$hidden = $this->get_element( render_block_core_archives( array( 'displayAsDropdown' => true ) ), 'label' );
		$shown  = $this->get_element(
			render_block_core_archives(
				array(
					'displayAsDropdown' => true,
					'showLabel'         => true,
				)
			),
			'label'
		);

		$this->assertSame( 'wp-block-archives__label screen-reader-text', $hidden['class'] );
		$this->assertSame( 'wp-block-archives__label', $shown['class'] );
	}

	/**
	 * Tests that the texts of the dropdown are escaped.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 */
	public function test_should_escape_the_texts_of_the_dropdown() {
		add_filter(
			'gettext',
			static function ( $translation, $text ) {
				if ( 'Archives' === $text || 'Select Month' === $text ) {
					return "$text <i>&</i>";
				}

				return $translation;
			},
			10,
			2
		);

		$markup = render_block_core_archives( array( 'displayAsDropdown' => true ) );

		$this->assertStringContainsString( '>Archives &lt;i&gt;&amp;&lt;/i&gt;</label>', $markup );
		$this->assertStringContainsString( '<option value="">Select Month &lt;i&gt;&amp;&lt;/i&gt;</option>', $markup );
	}

	/**
	 * Tests that each dropdown gets its own ID.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 */
	public function test_should_give_each_dropdown_its_own_id() {
		$first  = $this->get_element( render_block_core_archives( array( 'displayAsDropdown' => true ) ), 'select' );
		$second = $this->get_element( render_block_core_archives( array( 'displayAsDropdown' => true ) ), 'select' );

		$this->assertNotSame( $first['id'], $second['id'] );
	}

	/**
	 * Tests the script that is added after the dropdown.
	 *
	 * @ticket 66593
	 *
	 * @covers ::render_block_core_archives
	 */
	public function test_should_add_the_script_after_the_dropdown() {
		$markup = render_block_core_archives( array( 'displayAsDropdown' => true ) );
		$id     = $this->get_element( $markup, 'select' )['id'];

		$this->assertMatchesRegularExpression( '/<\/select>\s*<script/', $markup );
		$this->assertStringContainsString( wp_json_encode( array( $id, home_url() ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $markup );
		$this->assertStringNotContainsString( '<script', substr( $markup, 0, strpos( $markup, '</select>' ) ), 'The list should have no script.' );
		$this->assertStringNotContainsString( '<script', render_block_core_archives( array() ) );
	}

	/**
	 * Tests the script of the dropdown.
	 *
	 * @ticket 66593
	 *
	 * @covers ::block_core_archives_build_dropdown_script
	 */
	public function test_build_dropdown_script() {
		$script = block_core_archives_build_dropdown_script( 'the-id' );

		$this->assertStringStartsWith( '<script', $script );
		$this->assertStringEndsWith( '</script>' . "\n", $script );
		$this->assertStringContainsString( '} )( ["the-id","' . home_url() . '"] );', $script );
		$this->assertStringContainsString( '//# sourceURL=block_core_archives_build_dropdown_script', $script );
	}

	/**
	 * Tests that the values in the script are escaped.
	 *
	 * @ticket 66593
	 *
	 * @covers ::block_core_archives_build_dropdown_script
	 */
	public function test_build_dropdown_script_escapes_the_values() {
		$script = block_core_archives_build_dropdown_script( '</script><b>"x"' );

		$this->assertStringNotContainsString( '</script><b>', $script );
		$this->assertStringContainsString( '\u003C/script\u003E\u003Cb\u003E\"x\"', $script );
		$this->assertStringContainsString( '"' . home_url() . '"', $script, 'The slashes of the home URL should not be escaped.' );
	}
}
