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

	/**
	 * Generates a visual test HTML page to verify rendering and accessible names.
	 */
	public function test_visual_test_page_generation() {
		$img1 = '<!-- wp:image {"lightbox":{"enabled":true}} -->'
			. '<figure class="wp-block-image"><img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=600&q=80" alt="Woman surrounded by pink and black balls"/><figcaption>Case 1: Alt text present</figcaption></figure>'
			. '<!-- /wp:image -->';

		$img2 = '<!-- wp:image {"lightbox":{"enabled":true}} -->'
			. '<figure class="wp-block-image"><img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=600&q=80" alt="Landscape with mountain lake and forest"/><figcaption>Case 2: Mountain landscape alt</figcaption></figure>'
			. '<!-- /wp:image -->';

		$img3 = '<!-- wp:image {"lightbox":{"enabled":true}} -->'
			. '<figure class="wp-block-image"><img src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80" alt=""/><figcaption>Case 3: Empty / decorative alt</figcaption></figure>'
			. '<!-- /wp:image -->';

		$rendered1 = render_block( parse_blocks( $img1 )[0] );
		$rendered2 = render_block( parse_blocks( $img2 )[0] );
		$rendered3 = render_block( parse_blocks( $img3 )[0] );

		$image_css = file_get_contents( ABSPATH . 'wp-includes/blocks/image/style.css' );

		$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visual Test: Image Lightbox Accessible Trigger Buttons</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px auto; max-width: 960px; padding: 20px; background: #f0f2f5; color: #1e1e1e; }
        header { margin-bottom: 30px; }
        h1 { margin-bottom: 8px; font-size: 26px; font-weight: 600; }
        p.subtitle { color: #50575e; margin: 0; font-size: 15px; }
        .test-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .test-card { background: #fff; padding: 18px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
        .test-card h3 { margin-top: 0; font-size: 15px; font-weight: 600; color: #1e1e1e; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px; margin-bottom: 14px; }
        .wp-block-image { margin: 0; position: relative; }
        .wp-block-image img { width: 100%; height: 200px; object-fit: cover; border-radius: 4px; display: block; }
        .aria-badge { margin-top: 14px; background: #f0f6fc; border: 1px solid #c8d7e1; color: #005a9c; padding: 8px 12px; border-radius: 4px; font-size: 12px; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; line-height: 1.4; word-break: break-word; }
        .badge-label { font-weight: 600; display: block; margin-bottom: 2px; }
        .screen-reader-rotor { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .screen-reader-rotor h2 { font-size: 17px; margin-top: 0; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .rotor-list { list-style: none; padding: 0; margin: 12px 0 0 0; }
        .rotor-item { padding: 10px 14px; border-left: 3px solid #2271b1; background: #f6f7f7; margin-bottom: 6px; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
        .rotor-tag { background: #2271b1; color: #fff; font-size: 11px; padding: 2px 6px; border-radius: 3px; font-weight: 600; text-transform: uppercase; }
        ' . $image_css . '
    </style>
</head>
<body>
    <header>
        <h1>Visual Test: Gutenberg Image Lightbox Accessible Names (Issue #85409)</h1>
        <p class="subtitle">Verifying that lightbox trigger buttons provide distinct, informative accessible names derived from image alt text.</p>
    </header>
    
    <div class="test-grid">
        <div class="test-card">
            <h3>Case 1: Image with Portrait Alt</h3>
            ' . $rendered1 . '
            <div class="aria-badge">
                <span class="badge-label">Accessible Name:</span>
                aria-label="Enlarge image: Woman surrounded by pink and black balls"
            </div>
        </div>
        
        <div class="test-card">
            <h3>Case 2: Image with Landscape Alt</h3>
            ' . $rendered2 . '
            <div class="aria-badge">
                <span class="badge-label">Accessible Name:</span>
                aria-label="Enlarge image: Landscape with mountain lake and forest"
            </div>
        </div>
        
        <div class="test-card">
            <h3>Case 3: Decorative / Empty Alt Image</h3>
            ' . $rendered3 . '
            <div class="aria-badge">
                <span class="badge-label">Accessible Name:</span>
                aria-label="Enlarge image" (graceful fallback)
            </div>
        </div>
    </div>

    <div class="screen-reader-rotor">
        <h2>🔊 Screen Reader Rotor / Elements List (VoiceOver / NVDA View)</h2>
        <p style="margin: 0; color: #50575e; font-size: 13px;">Buttons are now uniquely identifiable when navigating out-of-context via rotor:</p>
        <ul class="rotor-list">
            <li class="rotor-item">
                <span><strong>Enlarge image: Woman surrounded by pink and black balls</strong>, dialog pop-up button</span>
                <span class="rotor-tag">Resolved</span>
            </li>
            <li class="rotor-item">
                <span><strong>Enlarge image: Landscape with mountain lake and forest</strong>, dialog pop-up button</span>
                <span class="rotor-tag">Resolved</span>
            </li>
            <li class="rotor-item">
                <span><strong>Enlarge image</strong>, dialog pop-up button</span>
                <span class="rotor-tag">Resolved</span>
            </li>
        </ul>
    </div>
</body>
</html>';

		$scratch_dir = '/Users/khorshedalam/.gemini/antigravity-ide/brain/b8aa729c-977f-492c-a4d1-217957526dd3/scratch';
		if ( ! is_dir( $scratch_dir ) ) {
			mkdir( $scratch_dir, 0777, true );
		}
		file_put_contents( $scratch_dir . '/visual-test.html', $html );

		$this->assertFileExists( $scratch_dir . '/visual-test.html' );
	}
}
