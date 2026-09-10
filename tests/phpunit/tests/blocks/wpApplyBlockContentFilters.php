<?php
/**
 * Tests for _wp_apply_block_content_filters().
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::_wp_apply_block_content_filters
 */
class Tests_Blocks_WpApplyBlockContentFilters extends WP_UnitTestCase {
	const TEST_BLOCK_NAME           = 'tests/apply-block-content-filters';
	const TEST_EXCEPTION_BLOCK_NAME = 'tests/apply-block-content-filters-exception';
	const TEST_EMBED_HANDLER        = 'apply_block_content_filters_embed';
	const TEST_SHORTCODE            = 'apply_block_content_filters_shortcode';

	/**
	 * The original use_smilies option value.
	 *
	 * @var string
	 */
	private $original_use_smilies;

	/**
	 * Context received by the wp_content_img_tag filter.
	 *
	 * @var string|null
	 */
	private $filtered_image_context;

	public function set_up() {
		parent::set_up();

		$this->original_use_smilies   = get_option( 'use_smilies' );
		$this->filtered_image_context = null;

		update_option( 'use_smilies', 1 );
		smilies_init();

		register_block_type(
			self::TEST_BLOCK_NAME,
			array(
				'render_callback' => static function ( $attributes ) {
					return sprintf(
						'<div class="apply-block-content-filters-block">%s</div>',
						esc_html( $attributes['label'] )
					);
				},
			)
		);

		register_block_type(
			self::TEST_EXCEPTION_BLOCK_NAME,
			array(
				'render_callback' => static function () {
					throw new Exception( 'Block rendering failed.' );
				},
			)
		);
	}

	public function tear_down() {
		remove_shortcode( self::TEST_SHORTCODE );
		wp_embed_unregister_handler( self::TEST_EMBED_HANDLER );
		remove_filter( 'wp_content_img_tag', array( $this, 'filter_content_image_tag' ) );

		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::TEST_BLOCK_NAME ) ) {
			unregister_block_type( self::TEST_BLOCK_NAME );
		}

		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::TEST_EXCEPTION_BLOCK_NAME ) ) {
			unregister_block_type( self::TEST_EXCEPTION_BLOCK_NAME );
		}

		update_option( 'use_smilies', $this->original_use_smilies );

		parent::tear_down();
	}

	/**
	 * Filters content image tags.
	 *
	 * @param string $filtered_image Full img tag with attributes.
	 * @param string $context        Context for the image tag.
	 * @return string Filtered image tag.
	 */
	public function filter_content_image_tag( $filtered_image, $context ) {
		$this->filtered_image_context = $context;

		if ( str_contains( $filtered_image, 'class="content-image"' ) ) {
			return str_replace( '<img ', '<img data-filtered="yes" ', $filtered_image );
		}

		return $filtered_image;
	}

	/**
	 * Renders the test embed handler.
	 *
	 * @return string Embed markup.
	 */
	public function render_test_embed() {
		return '<div class="apply-block-content-filters-embed">Embedded content</div>';
	}

	/**
	 * Tests that each expected content filter is applied.
	 *
	 * @ticket 65586
	 */
	public function test_applies_content_filters() {
		add_filter( 'wp_content_img_tag', array( $this, 'filter_content_image_tag' ), 10, 2 );

		wp_embed_register_handler(
			self::TEST_EMBED_HANDLER,
			'#https?://example\.com/apply-block-content-filters#i',
			array( $this, 'render_test_embed' )
		);

		add_shortcode(
			self::TEST_SHORTCODE,
			static function () {
				return '<!-- wp:tests/apply-block-content-filters {"label":"Shortcode block output"} /-->';
			}
		);

		$content = sprintf(
			"This shouldn't use a straight apostrophe, and this should become a smilie: :mrgreen:\n\n<p>[%s]</p>\n\n<!-- wp:tests/apply-block-content-filters {\"label\":\"Direct block output\"} /-->\n\n<img src=\"https://example.org/image.jpg\" alt=\"\" class=\"content-image\" />\n\nhttps://example.com/apply-block-content-filters",
			self::TEST_SHORTCODE
		);

		$output = _wp_apply_block_content_filters( $content, 'test-context' );

		$this->assertStringContainsString(
			'This shouldn&#8217;t use a straight apostrophe',
			$output,
			'wptexturize() should convert straight apostrophes to curly apostrophe entities.'
		);
		$this->assertStringContainsString(
			'class="wp-smiley"',
			$output,
			'convert_smilies() should process smilies in the filtered content.'
		);
		$this->assertStringContainsString(
			'<div class="apply-block-content-filters-block">Shortcode block output</div>',
			$output,
			'do_shortcode() should run before do_blocks() so block markup returned by a shortcode is rendered.'
		);
		$this->assertStringNotContainsString(
			'<p><div class="apply-block-content-filters-block">Shortcode block output</div></p>',
			$output,
			'shortcode_unautop() should remove paragraph wrappers around standalone shortcodes.'
		);
		$this->assertStringContainsString(
			'<div class="apply-block-content-filters-block">Direct block output</div>',
			$output,
			'do_blocks() should render block markup in the filtered content.'
		);
		$this->assertStringNotContainsString(
			'<!-- wp:tests/apply-block-content-filters',
			$output,
			'do_blocks() should remove block comments from rendered output.'
		);
		$this->assertStringContainsString(
			'data-filtered="yes"',
			$output,
			'wp_filter_content_tags() should filter image tags in the filtered content.'
		);
		$this->assertSame(
			'test-context',
			$this->filtered_image_context,
			'wp_filter_content_tags() should receive the context passed to _wp_apply_block_content_filters().'
		);
		$this->assertStringContainsString(
			'<div class="apply-block-content-filters-embed">Embedded content</div>',
			$output,
			'WP_Embed::autoembed() should process URLs on their own line.'
		);
		$this->assertStringNotContainsString(
			'https://example.com/apply-block-content-filters',
			$output,
			'WP_Embed::autoembed() should replace the original URL with embed markup.'
		);
	}

	/**
	 * Tests that seen IDs are set during block rendering and cleared afterward.
	 *
	 * @ticket 65586
	 */
	public function test_marks_and_clears_seen_id() {
		$seen_ids               = array();
		$seen_ids_during_render = null;

		unregister_block_type( self::TEST_BLOCK_NAME );
		register_block_type(
			self::TEST_BLOCK_NAME,
			array(
				'render_callback' => static function () use ( &$seen_ids, &$seen_ids_during_render ) {
					$seen_ids_during_render = $seen_ids;

					return '<div class="apply-block-content-filters-block">Nested block rendered while recursion guard was active</div>';
				},
			)
		);

		$output = _wp_apply_block_content_filters(
			'<!-- wp:tests/apply-block-content-filters /-->',
			'test-context',
			$seen_ids,
			'test-id'
		);

		$this->assertStringContainsString(
			'Nested block rendered while recursion guard was active',
			$output,
			'The test block should render while the ID is marked as seen.'
		);
		$this->assertSame(
			array( 'test-id' => true ),
			$seen_ids_during_render,
			'The ID should be marked as seen while do_blocks() renders nested blocks.'
		);
		$this->assertSame(
			array(),
			$seen_ids,
			'The seen ID should be cleared after do_blocks() completes.'
		);
	}

	/**
	 * Tests that seen IDs are cleared when block rendering throws an exception.
	 *
	 * @ticket 65586
	 */
	public function test_clears_seen_id_when_block_rendering_throws() {
		$seen_ids = array();

		try {
			_wp_apply_block_content_filters(
				'<!-- wp:tests/apply-block-content-filters-exception /-->',
				'test-context',
				$seen_ids,
				'test-id'
			);
			$this->fail( 'Expected block rendering to throw an exception.' );
		} catch ( Exception $exception ) {
			$this->assertSame(
				'Block rendering failed.',
				$exception->getMessage(),
				'The exception from the nested block render callback should be rethrown.'
			);
		}

		$this->assertSame(
			array(),
			$seen_ids,
			'The seen ID should be cleared when do_blocks() throws.'
		);
	}
}
