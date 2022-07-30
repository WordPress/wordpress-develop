<?php

/**
 * @group formatting
 * @ticket 46133
 */
class Tests_Formatting_ExcerptRemoveBlocks extends WP_UnitTestCase {

	public static $post_id;

	public $content = '
<!-- wp:paragraph -->
<p>paragraph</p>
<!-- /wp:paragraph -->
<!-- wp:latest-posts {"postsToShow":3,"displayPostDate":true,"order":"asc","orderBy":"title"} /-->
<!-- wp:spacer -->
<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->
<!-- wp:columns {"columns":1} -->
<div class="wp-block-columns has-1-columns">
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:archives {"displayAsDropdown":false,"showPostCounts":false} /-->
		
		<!-- wp:paragraph -->
		<p>paragraph inside column</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->
';

	public $filtered_content = '

<p>paragraph</p>




		<p>paragraph inside column</p>
		
';

	/**
	 * Fake block rendering function.
	 *
	 * @since 5.2.0
	 *
	 * @return string Block output.
	 */
	public function render_fake_block() {
		return get_the_excerpt( self::$post_id );
	}

	/**
	 * Set up.
	 *
	 * @since 5.2.0
	 */
	public function set_up() {
		parent::set_up();
		self::$post_id = $this->factory()->post->create(
			array(
				'post_excerpt' => '', // Empty excerpt, so it has to be generated.
				'post_content' => '<!-- wp:core/fake /-->',
			)
		);
		register_block_type(
			'core/fake',
			array(
				'render_callback' => array( $this, 'render_fake_block' ),
			)
		);
	}

	/**
	 * Tear down.
	 *
	 * @since 5.2.0
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();
		$registry->unregister( 'core/fake' );

		parent::tear_down();
	}

	/**
	 * Tests excerpt_remove_blocks().
	 *
	 * @ticket 46133
	 *
	 * @covers ::excerpt_remove_blocks
	 */
	public function test_excerpt_remove_blocks() {
		// Simple dynamic block..
		$content = '<!-- wp:core/block /-->';

		$this->assertEmpty( excerpt_remove_blocks( $content ) );

		// Dynamic block with options, embedded in other content.
		$this->assertSame( $this->filtered_content, excerpt_remove_blocks( $this->content ) );
	}

	/**
	 * Tests that dynamic blocks don't cause an out-of-memory error.
	 *
	 * When dynamic blocks happen to generate an excerpt, they can cause an
	 * infinite loop if that block is part of the post's content.
	 *
	 * `wp_trim_excerpt()` applies the `the_content` filter, which has
	 * `do_blocks` attached to it, trying to render the block which again will
	 * attempt to return an excerpt of that post.
	 *
	 * This infinite loop can be avoided by stripping dynamic blocks before
	 * `the_content` gets applied, just like shortcodes.
	 *
	 * @ticket 46133
	 *
	 * @covers ::do_blocks
	 */
	public function test_excerpt_infinite_loop() {
		$query = new WP_Query(
			array(
				'post__in' => array( self::$post_id ),
			)
		);
		$query->the_post();
		$this->assertEmpty( do_blocks( '<!-- wp:core/fake /-->' ) );
	}
}
