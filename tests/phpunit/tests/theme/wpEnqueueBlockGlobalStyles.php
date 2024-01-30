<?php

require_once __DIR__ . '/base.php';

/**
 * Tests wp_enqueue_block_global_styles().
 *
 * @group themes
 *
 * @covers ::wp_enqueue_block_global_styles
 */

class Tests_Theme_WpEnqueueBlockGlobalStyles extends WP_Theme_UnitTestCase {
            
    /**
    * Test blocks to unregister at cleanup.
    *
    * @var array
    */
    private $test_blocks = array();

    public function set_up() {
        parent::set_up();
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
    }

    public function tear_down() {
        // Unregister test blocks.
        if ( ! empty( $this->test_blocks ) ) {
            foreach ( $this->test_blocks as $test_block ) {
                unregister_block_type( $test_block );
            }
            $this->test_blocks = array();
        }

        parent::tear_down();
    }

    /**
     * @ticket 56915
     * @ticket 60280
     */
    public function test_third_party_blocks_inline_styles_not_register_to_global_styles() {
        switch_theme( 'block-theme' );

        wp_enqueue_block_global_styles();

        $this->assertNotContains(
            '.wp-block-my-third-party-block{background-color: hotpink;}',
            $this->get_global_styles_blocks()
        );
    }

    /**
     * @ticket 56915
     * @ticket 60280
     */
    public function test_third_party_blocks_inline_styles_get_registered_to_global_styles() {
        $this->set_up_third_party_block();


        $this->assertNotContains(
            '.wp-block-my-third-party-block{background-color: hotpink;}',
            $this->get_global_styles_blocks(),
            'Third party block inline style should not be registered before running wp_enqueue_block_global_styles()'
        );

        wp_enqueue_block_global_styles();

        $this->assertContains(
            '.wp-block-my-third-party-block{background-color: hotpink;}',
            $this->get_global_styles_blocks(),
            'Third party block inline style should be registered after running wp_enqueue_block_global_styles()'
        );

    }

    /**
	 * @ticket 56915
     * @ticket 60280
	 */
	public function test_blocks_inline_styles_get_rendered() {
		wp_enqueue_block_global_styles();

		$actual = get_echo( 'wp_print_styles' );

		$this->assertStringContainsString(
			'.wp-block-my-third-party-block{background-color: hotpink;}',
			$actual,
			'Third party block inline style should render'
		);
		$this->assertStringContainsString(
			'.wp-block-post-featured-image',
			$actual,
			'Core block should render'
		);
	}

    /**
	 * @ticket 57868
     * @ticket 60280
	 */
	public function test_third_party_blocks_inline_styles_for_elements_get_rendered() {
        wp_enqueue_block_global_styles();

		$actual = get_echo( 'wp_print_styles' );

		$this->assertStringContainsString(
			'.wp-block-my-third-party-block cite{color: white;}',
			$actual
		);
	}


    private function set_up_third_party_block() {
		switch_theme( 'block-theme' );

		$name     = 'my/third-party-block';
		$settings = array(
			'icon'            => 'text',
			'category'        => 'common',
			'render_callback' => 'foo',
		);
		register_block_type( $name, $settings );

		$this->test_blocks[] = $name;
	}

    private function get_global_styles_blocks() {
        $actual = wp_styles()->get_data( 'global-styles-blocks', 'after' );
        return is_array( $actual ) ? $actual : array();
    }
}