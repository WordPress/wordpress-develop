<?php
/**
 * Tests for the server-side rendering of the Post Date block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_post_date
 */
class Tests_Blocks_RenderPostDate extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * ID of a post published on a known date, and modified later.
	 *
	 * @var int
	 */
	private static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		self::$post_id = $factory->post->create(
			array(
				'post_date'     => '2023-05-06 10:11:12',
				'post_date_gmt' => '2023-05-06 10:11:12',
			)
		);

		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => '2024-07-08 09:10:11',
				'post_modified_gmt' => '2024-07-08 09:10:11',
			),
			array( 'ID' => self::$post_id )
		);
		clean_post_cache( self::$post_id );
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		update_option( 'timezone_string', 'UTC' );
		update_option( 'date_format', 'Y-m-d' );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Context of the block, with the post by default.
	 * @return string|null The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/post-date',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array(
			'postId'   => self::$post_id,
			'postType' => 'post',
		) : $context;

		return render_block_core_post_date( $attributes, '', $block );
	}

	/**
	 * Tests the date of the post.
	 *
	 * @ticket 66543
	 */
	public function test_should_render_the_publish_date_of_the_post() {
		$this->assertSame( '<div ><time datetime="2023-05-06T10:11:12+00:00">2023-05-06</time></div>', $this->render() );
	}

	/**
	 * Tests the modified date of the post.
	 *
	 * @ticket 66543
	 */
	public function test_should_render_the_modified_date_when_it_is_requested() {
		$this->assertSame(
			'<div class="wp-block-post-date__modified-date"><time datetime="2024-07-08T09:10:11+00:00">2024-07-08</time></div>',
			$this->render( array( 'displayType' => 'modified' ) )
		);
	}

	/**
	 * Tests the date of the attributes.
	 *
	 * @ticket 66543
	 */
	public function test_should_use_the_datetime_of_the_attributes() {
		$this->assertSame(
			'<div ><time datetime="2020-01-02T03:04:05+00:00">2020-01-02</time></div>',
			$this->render( array( 'datetime' => '2020-01-02T03:04:05+00:00' ) )
		);
	}

	/**
	 * Tests that the date of a binding is not replaced.
	 *
	 * @ticket 66543
	 */
	public function test_should_not_replace_the_datetime_of_a_binding() {
		$output = $this->render(
			array(
				'metadata' => array(
					'bindings' => array(
						'datetime' => array(
							'source' => 'core/post-data',
							'args'   => array( 'field' => 'date' ),
						),
					),
				),
			)
		);

		$this->assertSame( '', $output, 'The date of the binding is not in the attributes, so nothing is rendered.' );
	}

	/**
	 * Tests that the date of the post is used when the binding of the date is not complete.
	 *
	 * @ticket 66543
	 *
	 * @dataProvider data_incomplete_bindings
	 *
	 * @param array $binding Binding of the date.
	 */
	public function test_should_use_the_date_of_the_post_when_the_binding_is_not_complete( $binding ) {
		$output = $this->render( array( 'metadata' => array( 'bindings' => array( 'datetime' => $binding ) ) ) );

		$this->assertSame( '<div ><time datetime="2023-05-06T10:11:12+00:00">2023-05-06</time></div>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_bindings() {
		return array(
			'only a source' => array( array( 'source' => 'core/post-data' ) ),
			'only args'     => array( array( 'args' => array( 'field' => 'date' ) ) ),
			'no values'     => array( array() ),
		);
	}

	/**
	 * Tests that the formatted date is escaped.
	 *
	 * @ticket 66543
	 */
	public function test_should_escape_the_formatted_date() {
		$output = $this->render( array( 'format' => '\\<\\b\\>j\\<\\/\\b\\>' ) );

		$this->assertStringContainsString( '>&lt;b&gt;6&lt;/b&gt;</time>', $output );
	}

	/**
	 * Tests that nothing is rendered for an empty date.
	 *
	 * @ticket 66543
	 *
	 * @dataProvider data_empty_dates
	 *
	 * @param mixed $datetime Value of the attribute.
	 */
	public function test_should_render_nothing_for_an_empty_date( $datetime ) {
		$this->assertSame( '', $this->render( array( 'datetime' => $datetime ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_dates() {
		return array(
			'an empty string' => array( '' ),
			'zero'            => array( '0' ),
		);
	}

	/**
	 * Tests the format of the date.
	 *
	 * @ticket 66543
	 *
	 * @dataProvider data_formats
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected text of the date.
	 */
	public function test_should_use_the_format_of_the_block( $attributes, $expected ) {
		$this->assertStringContainsString( '>' . $expected . '</time>', $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_formats() {
		return array(
			'no format'    => array( array(), '2023-05-06' ),
			'an empty one' => array( array( 'format' => '' ), '2023-05-06' ),
			'a format'     => array( array( 'format' => 'F j, Y' ), 'May 6, 2023' ),
			'a time'       => array( array( 'format' => 'H:i' ), '10:11' ),
		);
	}

	/**
	 * Tests the time difference of a date in the past.
	 *
	 * @ticket 66543
	 */
	public function test_should_render_the_time_difference_of_a_past_date() {
		$this->assertMatchesRegularExpression( '#>\d+ (year|years|month|months) ago</time>#', $this->render( array( 'format' => 'human-diff' ) ) );
	}

	/**
	 * Tests the time difference of a date in the future.
	 *
	 * @ticket 66543
	 */
	public function test_should_render_the_time_difference_of_a_future_date() {
		$output = $this->render(
			array(
				'format'   => 'human-diff',
				'datetime' => gmdate( 'c', time() + 5 * DAY_IN_SECONDS ),
			)
		);

		$this->assertMatchesRegularExpression( '#>\d+ days? from now</time>#', $output );
	}

	/**
	 * Tests that the datetime is escaped.
	 *
	 * @ticket 66543
	 */
	public function test_should_escape_the_datetime_and_the_text() {
		$output = $this->render( array( 'datetime' => '2020-01-02"><script>' ) );

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( 'datetime="2020-01-02&quot;&gt;&lt;script&gt;"', $output );
	}

	/**
	 * Tests the link to the post.
	 *
	 * @ticket 66543
	 */
	public function test_should_link_the_date_to_the_post() {
		$this->assertSame(
			'<div ><a href="' . esc_url( get_permalink( self::$post_id ) ) . '"><time datetime="2023-05-06T10:11:12+00:00">2023-05-06</time></a></div>',
			$this->render( array( 'isLink' => true ) )
		);
	}

	/**
	 * Tests that the date is not linked without a post or unless it is requested.
	 *
	 * @ticket 66543
	 */
	public function test_should_not_link_the_date_without_a_post_or_unless_it_is_requested() {
		$attributes = array( 'datetime' => '2020-01-02T03:04:05+00:00' );

		$this->assertStringNotContainsString( '<a ', $this->render( array_merge( $attributes, array( 'isLink' => true ) ), array() ), 'There should be no link without a post.' );
		$this->assertStringNotContainsString( '<a ', $this->render( array_merge( $attributes, array( 'isLink' => false ) ) ), 'There should be no link for false.' );
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66543
	 */
	public function test_should_escape_the_url_of_the_link() {
		add_filter(
			'post_link',
			static function () {
				return 'https://example.org/?a=1&b=2';
			}
		);

		$this->assertStringContainsString( '<a href="https://example.org/?a=1&#038;b=2">', $this->render( array( 'isLink' => true ) ) );
	}

	/**
	 * Tests the classes of the block.
	 *
	 * @ticket 66543
	 *
	 * @dataProvider data_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_classes_of_the_attributes( $attributes, $expected ) {
		$this->assertStringStartsWith( $expected, $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_classes() {
		$link_color = array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) );

		return array(
			'no classes'                  => array( array(), '<div >' ),
			'a text alignment'            => array( array( 'textAlign' => 'center' ), '<div class="has-text-align-center">' ),
			'a link color'                => array( array( 'style' => $link_color ), '<div class="has-link-color">' ),
			'a text alignment and a link' => array(
				array(
					'textAlign' => 'right',
					'style'     => $link_color,
				),
				'<div class="has-text-align-right has-link-color">',
			),
			'another element'             => array( array( 'style' => array( 'elements' => array( 'heading' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), '<div >' ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66543
	 *
	 * @covers ::register_block_core_post_date
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-date' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-date' );
		}

		register_block_core_post_date();
		$block_type = $registry->get_registered( 'core/post-date' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-date' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_date', $block_type->render_callback );
	}
}
