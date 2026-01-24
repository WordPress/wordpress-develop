<?php
/**
 * Test that escaped shortcodes do not expand in excerpts.
 */

class Tests_Post_GetExcerpt extends WP_UnitTestCase {

    function test_escaped_shortcode_not_expanded() {
        $post_id = self::factory()->post->create(array(
            'post_content' => 'This is a test \[gallery] shortcode.'
        ));

        $excerpt = get_the_excerpt($post_id);

        // Should still contain the escaped shortcode
        $this->assertStringContainsString('\[gallery]', $excerpt);

        // Should NOT contain <div class="gallery">
        $this->assertStringNotContainsString('<div class="gallery">', $excerpt);
    }
}
