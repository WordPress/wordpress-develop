<?php
/**
 * Tests for the server-side rendering of the Tag Cloud block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group taxonomy
 *
 * @covers ::render_block_core_tag_cloud
 */
class Tests_Blocks_RenderTagCloud extends WP_UnitTestCase {

	/**
	 * IDs of the tags, keyed by name.
	 *
	 * @var int[]
	 */
	private static $tags = array();

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$counts = array(
			'Alpha' => 1,
			'Beta'  => 2,
			'Gamma' => 4,
		);

		foreach ( $counts as $name => $count ) {
			self::$tags[ $name ] = $factory->tag->create( array( 'name' => $name ) );

			foreach ( $factory->post->create_many( $count ) as $post_id ) {
				wp_set_object_terms( $post_id, array( self::$tags[ $name ] ), 'post_tag' );
			}
		}
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block with the default attributes of the block, and the attributes that are passed.
	 *
	 * @param array $attributes Attributes of the block.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array() ) {
		return render_block_core_tag_cloud(
			array_merge(
				array(
					'numberOfTags'     => 45,
					'taxonomy'         => 'post_tag',
					'showTagCounts'    => false,
					'smallestFontSize' => '8pt',
					'largestFontSize'  => '22pt',
				),
				$attributes
			)
		);
	}

	/**
	 * Tests that the tags are rendered in a paragraph.
	 *
	 * @ticket 66524
	 */
	public function test_should_render_the_tags_in_a_paragraph() {
		$output = $this->render();

		$this->assertStringStartsWith( '<p >', $output );
		$this->assertStringEndsWith( '</p>', $output );

		foreach ( array( 'Alpha', 'Beta', 'Gamma' ) as $name ) {
			$this->assertStringContainsString( '>' . $name . '</a>', $output, "The tag '$name' should be rendered." );
		}
	}

	/**
	 * Tests that the tags are limited.
	 *
	 * @ticket 66524
	 */
	public function test_should_limit_the_number_of_tags() {
		$output = $this->render( array( 'numberOfTags' => 2 ) );

		$this->assertSame( 2, substr_count( $output, 'tag-cloud-link' ), 'Two tags should be rendered.' );
	}

	/**
	 * Tests that the count of the tags is shown when it is requested.
	 *
	 * @ticket 66524
	 */
	public function test_should_show_the_count_of_the_tags_when_it_is_requested() {
		$this->assertStringNotContainsString( 'tag-link-count', $this->render( array( 'showTagCounts' => false ) ), 'The counts should be hidden.' );
		$this->assertStringContainsString( 'tag-link-count', $this->render( array( 'showTagCounts' => true ) ), 'The counts should be shown.' );
	}

	/**
	 * Tests that the sizes go from the smallest to the largest font size.
	 *
	 * @ticket 66524
	 */
	public function test_should_use_the_smallest_and_the_largest_font_size() {
		$output = $this->render(
			array(
				'smallestFontSize' => '10px',
				'largestFontSize'  => '30px',
			)
		);

		$this->assertStringContainsString( 'font-size: 10px;', $output, 'The tag with the fewest posts should have the smallest size.' );
		$this->assertStringContainsString( 'font-size: 30px;', $output, 'The tag with the most posts should have the largest size.' );
	}

	/**
	 * Tests the unit of the font sizes.
	 *
	 * @ticket 66524
	 *
	 * @dataProvider data_units
	 *
	 * @param mixed  $smallest_font_size Smallest font size.
	 * @param string $expected           Expected unit.
	 */
	public function test_should_use_the_unit_of_the_smallest_font_size( $smallest_font_size, $expected ) {
		$output = $this->render(
			array(
				'smallestFontSize' => $smallest_font_size,
				'largestFontSize'  => '20',
			)
		);

		$this->assertMatchesRegularExpression( '/font-size: [0-9.]+' . preg_quote( $expected, '/' ) . ';/', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_units() {
		return array(
			'pixels'                       => array( '12px', 'px' ),
			'points'                       => array( '12pt', 'pt' ),
			'ems'                          => array( '1.5em', 'em' ),
			'a percentage'                 => array( '110%', '%' ),
			'a unit in capitals'           => array( '12PX', 'PX' ),
			'no unit'                      => array( '12', 'pt' ),
			'an empty string'              => array( '', 'pt' ),
			'a value that is not a string' => array( 12, 'pt' ),
			'a unit without a size'        => array( 'px', 'pt' ),
			'a size with a prefix'         => array( 'x12px', 'pt' ),
			'a size with a suffix'         => array( '12px;', 'pt' ),
			'null'                         => array( null, 'pt' ),
		);
	}

	/**
	 * Tests that the taxonomy of the block is used.
	 *
	 * @ticket 66524
	 */
	public function test_should_use_the_taxonomy_of_the_block() {
		$category_id = self::factory()->category->create( array( 'name' => 'Only category' ) );
		$post_id     = self::factory()->post->create();
		wp_set_object_terms( $post_id, array( $category_id ), 'category' );

		$output = $this->render( array( 'taxonomy' => 'category' ) );

		$this->assertStringContainsString( '>Only category</a>', $output, 'The category should be rendered.' );
		$this->assertStringNotContainsString( '>Alpha</a>', $output, 'The tags should not be rendered.' );
	}

	/**
	 * Tests that nothing is rendered when there are no tags.
	 *
	 * @ticket 66524
	 */
	public function test_should_render_nothing_without_tags() {
		register_taxonomy( 'wp_test_empty', 'post' );

		try {
			$this->assertSame( '', $this->render( array( 'taxonomy' => 'wp_test_empty' ) ) );
		} finally {
			unregister_taxonomy( 'wp_test_empty' );
		}
	}

	/**
	 * Tests that a placeholder is rendered without tags in a REST request.
	 *
	 * @ticket 66524
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_render_a_placeholder_without_tags_in_a_rest_request() {
		define( 'REST_REQUEST', true );

		register_taxonomy( 'wp_test_empty', 'post' );

		$this->assertSame(
			'<p >There&#8217;s no content to show here yet.</p>',
			$this->render( array( 'taxonomy' => 'wp_test_empty' ) )
		);
	}

	/**
	 * Tests that the tags are rendered in a REST request.
	 *
	 * @ticket 66524
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_render_the_tags_in_a_rest_request() {
		define( 'REST_REQUEST', true );

		$output = $this->render();

		$this->assertStringContainsString( '>Gamma</a>', $output );
		$this->assertStringNotContainsString( 'no content to show', $output );
	}
}
