<?php
/**
 * Tests for the Image and Gallery block lightbox trigger button accessible labels.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 *
 * @covers ::block_core_image_render_lightbox
 * @covers ::render_block_core_gallery
 */
class Tests_Blocks_BlockCoreImageRenderLightbox extends WP_UnitTestCase {

	/**
	 * Sets up each test method.
	 */
	public function set_up() {
		parent::set_up();
		$GLOBALS['wp_interactivity'] = new WP_Interactivity_API();
	}

	/**
	 * Tear down each test method.
	 */
	public function tear_down() {
		$GLOBALS['wp_interactivity'] = new WP_Interactivity_API();
		parent::tear_down();
	}

	/**
	 * Tests that the lightbox trigger button includes the alt text in its accessible label.
	 */
	public function test_image_lightbox_trigger_button_with_alt_text() {
		$content = '<figure class="wp-block-image"><img src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2026/10/cherry-blossoms.jpg" alt="Cherry blossoms in spring"/></figure>';

		$parsed_blocks = parse_blocks( '<!-- wp:image {"lightbox":{"enabled":true}} -->' . $content . '<!-- /wp:image -->' );
		$rendered      = render_block( $parsed_blocks[0] );

		$this->assertStringContainsString( 'class="lightbox-trigger"', $rendered );
		$this->assertStringContainsString( 'aria-label="Enlarge image: Cherry blossoms in spring"', $rendered );

		$state = wp_interactivity_state( 'core/image' );
		$this->assertNotEmpty( $state['metadata'] );

		$metadata = reset( $state['metadata'] );
		$this->assertSame( 'Enlarge image: Cherry blossoms in spring', $metadata['triggerButtonAriaLabel'] );
		$this->assertSame( 'Enlarged image: Cherry blossoms in spring', $metadata['customAriaLabel'] );
	}

	/**
	 * Tests that the lightbox trigger button falls back to "Enlarge image" when alt text is empty.
	 */
	public function test_image_lightbox_trigger_button_with_empty_alt_text() {
		$content = '<figure class="wp-block-image"><img src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2026/10/landscape.jpg" alt=""/></figure>';

		$parsed_blocks = parse_blocks( '<!-- wp:image {"lightbox":{"enabled":true}} -->' . $content . '<!-- /wp:image -->' );
		$rendered      = render_block( $parsed_blocks[0] );

		$this->assertStringContainsString( 'class="lightbox-trigger"', $rendered );
		$this->assertStringContainsString( 'aria-label="Enlarge image"', $rendered );

		$state    = wp_interactivity_state( 'core/image' );
		$metadata = reset( $state['metadata'] );
		$this->assertSame( 'Enlarge image', $metadata['triggerButtonAriaLabel'] );
	}

	/**
	 * Tests that the lightbox trigger button falls back to "Enlarge image" when alt attribute is omitted.
	 */
	public function test_image_lightbox_trigger_button_with_missing_alt_attribute() {
		$content = '<figure class="wp-block-image"><img src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2026/10/abstract.jpg"/></figure>';

		$parsed_blocks = parse_blocks( '<!-- wp:image {"lightbox":{"enabled":true}} -->' . $content . '<!-- /wp:image -->' );
		$rendered      = render_block( $parsed_blocks[0] );

		$this->assertStringContainsString( 'class="lightbox-trigger"', $rendered );
		$this->assertStringContainsString( 'aria-label="Enlarge image"', $rendered );

		$state    = wp_interactivity_state( 'core/image' );
		$metadata = reset( $state['metadata'] );
		$this->assertSame( 'Enlarge image', $metadata['triggerButtonAriaLabel'] );
	}

	/**
	 * Tests that the gallery block lightbox trigger button includes image index and alt text.
	 */
	public function test_gallery_lightbox_trigger_button_with_alt_text() {
		$gallery_content = '<!-- wp:gallery {"linkTo":"none"} -->'
			. '<figure class="wp-block-gallery has-nested-images columns-default is-cropped">'
			. '<!-- wp:image {"lightbox":{"enabled":true}} -->'
			. '<figure class="wp-block-image"><img src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2026/10/first.jpg" alt="First photo"/></figure>'
			. '<!-- /wp:image -->'
			. '<!-- wp:image {"lightbox":{"enabled":true}} -->'
			. '<figure class="wp-block-image"><img src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2026/10/second.jpg" alt="Second photo"/></figure>'
			. '<!-- /wp:image -->'
			. '</figure>'
			. '<!-- /wp:gallery -->';

		$parsed_blocks = parse_blocks( $gallery_content );
		$rendered      = render_block( $parsed_blocks[0] );

		$this->assertStringContainsString( 'data-wp-interactive="core/gallery"', $rendered );

		$state = wp_interactivity_state( 'core/image' );
		$this->assertCount( 2, $state['metadata'] );

		$items = array_values( $state['metadata'] );
		$this->assertSame( 'Enlarge image 1 of 2: First photo', $items[0]['triggerButtonAriaLabel'] );
		$this->assertSame( 'Enlarge image 2 of 2: Second photo', $items[1]['triggerButtonAriaLabel'] );
	}

	/**
	 * Tests that the gallery block lightbox trigger button falls back to "Enlarge image X of Y" when alt text is empty.
	 */
	public function test_gallery_lightbox_trigger_button_with_empty_alt_text() {
		$gallery_content = '<!-- wp:gallery {"linkTo":"none"} -->'
			. '<figure class="wp-block-gallery has-nested-images columns-default is-cropped">'
			. '<!-- wp:image {"lightbox":{"enabled":true}} -->'
			. '<figure class="wp-block-image"><img src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2026/10/first.jpg" alt=""/></figure>'
			. '<!-- /wp:image -->'
			. '<!-- wp:image {"lightbox":{"enabled":true}} -->'
			. '<figure class="wp-block-image"><img src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2026/10/second.jpg" alt=""/></figure>'
			. '<!-- /wp:image -->'
			. '</figure>'
			. '<!-- /wp:gallery -->';

		$parsed_blocks = parse_blocks( $gallery_content );
		$rendered      = render_block( $parsed_blocks[0] );

		$this->assertStringContainsString( 'data-wp-interactive="core/gallery"', $rendered );

		$state = wp_interactivity_state( 'core/image' );
		$this->assertCount( 2, $state['metadata'] );

		$items = array_values( $state['metadata'] );
		$this->assertSame( 'Enlarge image 1 of 2', $items[0]['triggerButtonAriaLabel'] );
		$this->assertSame( 'Enlarge image 2 of 2', $items[1]['triggerButtonAriaLabel'] );
	}
}
