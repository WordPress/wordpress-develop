<?php
/**
 * Block rendering tests.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 */

/**
 * Tests for block rendering functions
 *
 * @since 5.0.0
 *
 * @group blocks
 */
class WP_Test_Block_Render extends WP_UnitTestCase {
	/**
	 * The location of the fixtures to test with.
	 *
	 * @since 5.0.0
	 * @var string
	 */
	protected static $fixtures_dir;

	/**
	 * Test block instance number.
	 *
	 * @since 5.0.0
	 *
	 * @var int
	 */
	protected $test_block_instance_number = 0;

	/**
	 * Tear down after each test.
	 *
	 * @since 5.0.0
	 */
	public function tearDown() {
		$this->test_block_instance_number = 0;

		$registry = WP_Block_Type_Registry::get_instance();
		if ( $registry->is_registered( 'core/test' ) ) {
			$registry->unregister( 'core/test' );
		}
		if ( $registry->is_registered( 'core/dynamic' ) ) {
			$registry->unregister( 'core/dynamic' );
		}

		parent::tearDown();
	}

	/**
	 * @ticket 45109
	 */
	public function test_do_blocks_removes_comments() {
		$original_html = file_get_contents( DIR_TESTDATA . '/blocks/do-blocks-original.html' );
		$expected_html = file_get_contents( DIR_TESTDATA . '/blocks/do-blocks-expected.html' );

		$actual_html = do_blocks( $original_html );

		$this->assertSameIgnoreEOL( $expected_html, $actual_html );
	}

	/**
	 * @ticket 45109
	 */
	public function test_the_content() {
		add_shortcode( 'someshortcode', array( $this, 'handle_shortcode' ) );

		$classic_content = "Foo\n\n[someshortcode]\n\nBar\n\n[/someshortcode]\n\nBaz";
		$block_content   = "<!-- wp:core/paragraph -->\n<p>Foo</p>\n<!-- /wp:core/paragraph -->\n\n<!-- wp:core/shortcode -->[someshortcode]\n\nBar\n\n[/someshortcode]<!-- /wp:core/shortcode -->\n\n<!-- wp:core/paragraph -->\n<p>Baz</p>\n<!-- /wp:core/paragraph -->";

		$classic_filtered_content = apply_filters( 'the_content', $classic_content );
		$block_filtered_content   = apply_filters( 'the_content', $block_content );

		// Block rendering add some extra blank lines, but we're not worried about them.
		$block_filtered_content = preg_replace( "/\n{2,}/", "\n", $block_filtered_content );

		remove_shortcode( 'someshortcode' );

		$this->assertSame( trim( $classic_filtered_content ), trim( $block_filtered_content ) );
	}

	function handle_shortcode( $atts, $content ) {
		return $content;
	}

	/**
	 * @ticket 45495
	 */
	function test_nested_calls_to_the_content() {
		register_block_type(
			'core/test',
			array(
				'render_callback' => array(
					$this,
					'dynamic_the_content_call',
				),
			)
		);

		$content = "foo\n\nbar";

		$the_content = apply_filters( 'the_content', '<!-- wp:core/test -->' . $content . '<!-- /wp:core/test -->' );

		$this->assertSame( $content, $the_content );
	}

	function dynamic_the_content_call( $attrs, $content ) {
		apply_filters( 'the_content', '' );
		return $content;
	}

	public function test_can_nest_at_least_so_deep() {
		$minimum_depth = 99;

		$content = 'deep inside';
		for ( $i = 0; $i < $minimum_depth; $i++ ) {
			$content = '<!-- wp:core/test -->' . $content . '<!-- /wp:core/test -->';
		}

		$this->assertSame( 'deep inside', do_blocks( $content ) );
	}

	public function test_can_nest_at_least_so_deep_with_dynamic_blocks() {
		$minimum_depth = 99;

		$content = '0';
		for ( $i = 0; $i < $minimum_depth; $i++ ) {
			$content = '<!-- wp:core/test -->' . $content . '<!-- /wp:core/test -->';
		}

		register_block_type(
			'core/test',
			array(
				'render_callback' => array(
					$this,
					'render_dynamic_incrementer',
				),
			)
		);

		$this->assertSame( $minimum_depth, (int) do_blocks( $content ) );
	}

	public function render_dynamic_incrementer( $attrs, $content ) {
		return (string) ( 1 + (int) $content );
	}

	/**
	 * @ticket 45290
	 */
	public function test_blocks_arent_autopeed() {
		$expected_content = 'test';
		$test_content     = "<!-- wp:fake/block -->\n$expected_content\n<!-- /wp:fake/block -->";

		$current_priority = has_action( 'the_content', 'wpautop' );

		$filtered_content = trim( apply_filters( 'the_content', $test_content ) );

		$this->assertSame( $expected_content, $filtered_content );

		// Check that wpautop() is still defined in the same place.
		$this->assertSame( $current_priority, has_action( 'the_content', 'wpautop' ) );
		// ... and that the restore function has removed itself.
		$this->assertFalse( has_action( 'the_content', '_restore_wpautop_hook' ) );

		$test_content     = 'test';
		$expected_content = "<p>$test_content</p>";

		$current_priority = has_action( 'the_content', 'wpautop' );

		$filtered_content = trim( apply_filters( 'the_content', $test_content ) );

		$this->assertSame( $expected_content, $filtered_content );

		$this->assertSame( $current_priority, has_action( 'the_content', 'wpautop' ) );
		$this->assertFalse( has_action( 'the_content', '_restore_wpautop_hook' ) );
	}

	/**
	 * @ticket 45109
	 */
	public function data_do_block_test_filenames() {
		self::$fixtures_dir = DIR_TESTDATA . '/blocks/fixtures';

		$fixture_filenames = array_merge(
			glob( self::$fixtures_dir . '/*.json' ),
			glob( self::$fixtures_dir . '/*.html' )
		);

		$fixture_filenames = array_values(
			array_unique(
				array_map(
					array( $this, 'clean_fixture_filename' ),
					$fixture_filenames
				)
			)
		);

		return array_map(
			array( $this, 'pass_parser_fixture_filenames' ),
			$fixture_filenames
		);  }

	/**
	 * @dataProvider data_do_block_test_filenames
	 * @ticket 45109
	 */
	public function test_do_block_output( $html_filename, $server_html_filename ) {
		$html_path        = self::$fixtures_dir . '/' . $html_filename;
		$server_html_path = self::$fixtures_dir . '/' . $server_html_filename;

		foreach ( array( $html_path, $server_html_path ) as $filename ) {
			if ( ! file_exists( $filename ) ) {
				throw new Exception( "Missing fixture file: '$filename'" );
			}
		}

		$html          = do_blocks( self::strip_r( file_get_contents( $html_path ) ) );
		$expected_html = self::strip_r( file_get_contents( $server_html_path ) );

		$this->assertSame(
			$expected_html,
			$html,
			"File '$html_path' does not match expected value"
		);
	}

	/**
	 * @ticket 45109
	 */
	public function test_dynamic_block_rendering() {
		$settings = array(
			'render_callback' => array(
				$this,
				'render_test_block',
			),
		);
		register_block_type( 'core/test', $settings );

		// The duplicated dynamic blocks below are there to ensure that do_blocks() replaces each one-by-one.
		$post_content =
			'before' .
			'<!-- wp:core/test {"value":"b1"} --><!-- /wp:core/test -->' .
			'<!-- wp:core/test {"value":"b1"} --><!-- /wp:core/test -->' .
			'between' .
			'<!-- wp:core/test {"value":"b2"} /-->' .
			'<!-- wp:core/test {"value":"b2"} /-->' .
			'after';

		$updated_post_content = do_blocks( $post_content );
		$this->assertSame(
			$updated_post_content,
			'before' .
			'1:b1' .
			'2:b1' .
			'between' .
			'3:b2' .
			'4:b2' .
			'after'
		);
	}

	/**
	 * @ticket 45109
	 */
	public function test_global_post_persistence() {
		global $post;

		register_block_type(
			'core/test',
			array(
				'render_callback' => array(
					$this,
					'render_test_block_wp_query',
				),
			)
		);

		$posts = self::factory()->post->create_many( 5 );
		$post  = get_post( end( $posts ) );

		$global_post = $post;
		do_blocks( '<!-- wp:core/test /-->' );

		$this->assertSame( $global_post, $post );
	}

	public function test_render_latest_comments_on_password_protected_post() {
		$post_id      = self::factory()->post->create(
			array(
				'post_password' => 'password',
			)
		);
		$comment_text = wp_generate_password( 10, false );
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => $comment_text,
			)
		);
		$comments = do_blocks( '<!-- wp:latest-comments {"commentsToShow":1,"displayExcerpt":true} /-->' );

		$this->assertStringNotContainsString( $comment_text, $comments );
	}

	/**
	 * @ticket 45109
	 */
	public function test_dynamic_block_renders_string() {
		$settings = array(
			'render_callback' => array(
				$this,
				'render_test_block_numeric',
			),
		);

		register_block_type( 'core/test', $settings );
		$block_type = new WP_Block_Type( 'core/test', $settings );

		$rendered = $block_type->render();

		$this->assertSame( '10', $rendered );
		$this->assertIsString( $rendered );
	}

	public function test_dynamic_block_gets_inner_html() {
		register_block_type(
			'core/dynamic',
			array(
				'render_callback' => array(
					$this,
					'render_serialize_dynamic_block',
				),
			)
		);

		$output = do_blocks( '<!-- wp:dynamic -->inner<!-- /wp:dynamic -->' );

		$data = unserialize( base64_decode( $output ) );

		$this->assertSame( 'inner', $data[1] );
	}

	public function test_dynamic_block_gets_rendered_inner_blocks() {
		register_block_type(
			'core/test',
			array(
				'render_callback' => array(
					$this,
					'render_test_block_numeric',
				),
			)
		);

		register_block_type(
			'core/dynamic',
			array(
				'render_callback' => array(
					$this,
					'render_serialize_dynamic_block',
				),
			)
		);

		$output = do_blocks( '<!-- wp:dynamic -->before<!-- wp:test /-->after<!-- /wp:dynamic -->' );

		$data = unserialize( base64_decode( $output ) );

		$this->assertSame( 'before10after', $data[1] );
	}

	public function test_dynamic_block_gets_rendered_inner_dynamic_blocks() {
		register_block_type(
			'core/dynamic',
			array(
				'render_callback' => array(
					$this,
					'render_serialize_dynamic_block',
				),
			)
		);

		$output = do_blocks( '<!-- wp:dynamic -->before<!-- wp:dynamic -->deep inner<!-- /wp:dynamic -->after<!-- /wp:dynamic -->' );

		$data = unserialize( base64_decode( $output ) );

		$inner = $this->render_serialize_dynamic_block( array(), 'deep inner' );

		$this->assertSame( $data[1], 'before' . $inner . 'after' );
	}

	/**
	 * Helper function to remove relative paths and extension from a filename, leaving just the fixture name.
	 *
	 * @since 5.0.0
	 *
	 * @param string $filename The filename to clean.
	 * @return string The cleaned fixture name.
	 */
	protected function clean_fixture_filename( $filename ) {
		$filename = wp_basename( $filename );
		$filename = preg_replace( '/\..+$/', '', $filename );
		return $filename;
	}

	/**
	 * Helper function to return the filenames needed to test the parser output.
	 *
	 * @since 5.0.0
	 *
	 * @param string $filename The cleaned fixture name.
	 * @return array The input and expected output filenames for that fixture.
	 */
	protected function pass_parser_fixture_filenames( $filename ) {
		return array(
			"$filename.html",
			"$filename.server.html",
		);
	}

	/**
	 * Helper function to remove '\r' characters from a string.
	 *
	 * @since 5.0.0
	 *
	 * @param string $input The string to remove '\r' from.
	 * @return string The input string, with '\r' characters removed.
	 */
	protected function strip_r( $input ) {
		return str_replace( "\r", '', $input );
	}

	/**
	 * Test block rendering function.
	 *
	 * @since 5.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string Block output.
	 */
	public function render_test_block( $attributes ) {
		$this->test_block_instance_number += 1;
		return $this->test_block_instance_number . ':' . $attributes['value'];
	}

	/**
	 * Test block rendering function, returning numeric value.
	 *
	 * @since 5.0.0
	 *
	 * @return int Block output.
	 */
	public function render_test_block_numeric() {
		return 10;
	}

	/**
	 * Test block rendering function, returning base64 encoded serialised value.
	 *
	 * @since 5.0.0
	 *
	 * @return string Block output.
	 */
	public function render_serialize_dynamic_block( $attributes, $content ) {
		return base64_encode( serialize( array( $attributes, $content ) ) );
	}

	/**
	 * Test block rendering function, creating a new WP_Query instance.
	 *
	 * @since 5.0.0
	 *
	 * @return string Block output.
	 */
	public function render_test_block_wp_query() {
		$content = '';
		$recent  = new WP_Query(
			array(
				'numberposts'      => 10,
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'post_type'        => 'post',
				'post_status'      => 'draft, publish, future, pending, private',
				'suppress_filters' => true,
			)
		);

		while ( $recent->have_posts() ) {
			$recent->the_post();

			$content .= get_the_title();
		}

		wp_reset_postdata();

		return $content;
	}

}
