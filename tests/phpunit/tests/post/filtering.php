<?php

/**
 * Save and fetch posts to make sure content is properly filtered.
 *
 * These tests don't care what code is responsible for filtering
 * or how it is called, just that it happens when a post is saved.
 *
 * @group post
 * @group formatting
 */
class Tests_Post_Filtering extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		update_option( 'use_balanceTags', 1 );
		kses_init_filters();
	}

	public function tear_down() {
		kses_remove_filters();
		parent::tear_down();
	}

	// A simple test to make sure unclosed tags are fixed.
	public function test_post_content_unknown_tag() {

		$content = <<<EOF
<foobar>no such tag</foobar>
EOF;

		$expected = <<<EOF
no such tag
EOF;

		$id   = self::factory()->post->create( array( 'post_content' => $content ) );
		$post = get_post( $id );

		$this->assertEqualHTML( $expected, $post->post_content );
	}

	// A simple test to make sure unbalanced tags are fixed.
	public function test_post_content_unbalanced_tag() {

		$content = <<<EOF
<i>italics
EOF;

		$expected = <<<EOF
<i>italics</i>
EOF;

		$id   = self::factory()->post->create( array( 'post_content' => $content ) );
		$post = get_post( $id );

		$this->assertEqualHTML( $expected, $post->post_content );
	}

	// Test KSES filtering of disallowed attribute.
	public function test_post_content_disallowed_attr() {

		$content = <<<EOF
<img src='foo' width='500' href='shlorp' />
EOF;

		$expected = <<<EOF
<img src='foo' width='500' />
EOF;

		$id   = self::factory()->post->create( array( 'post_content' => $content ) );
		$post = get_post( $id );

		$this->assertEqualHTML( $expected, $post->post_content );
	}

	/**
	 * test kses bug. xhtml does not require space before closing empty element
	 *
	 * @ticket 12394
	 */
	public function test_post_content_xhtml_empty_elem() {
		$content = <<<EOF
<img src='foo' width='500' height='300'/>
EOF;

		$expected = <<<EOF
<img src='foo' width='500' height='300' />
EOF;

		$id   = self::factory()->post->create( array( 'post_content' => $content ) );
		$post = get_post( $id );

		$this->assertEqualHTML( $expected, $post->post_content );
	}

	// Make sure unbalanced tags are untouched when the balance option is off.
	public function test_post_content_nobalance_nextpage_more() {

		update_option( 'use_balanceTags', 0 );

		$content = <<<EOF
<em>some text<!--nextpage-->
that's continued after the jump</em>
<!--more-->
<p>and the next page
<!--nextpage-->
breaks the graf</p>
EOF;

		$id   = self::factory()->post->create( array( 'post_content' => $content ) );
		$post = get_post( $id );

		$this->assertEqualHTML( $content, $post->post_content );
	}

	/**
	 * Ensures that a user without `unfiltered_html` keeps the content after a NOSCRIPT element.
	 *
	 * @ticket 66674
	 */
	public function test_post_content_keeps_content_after_noscript_for_contributor() {
		$contributor_id = self::factory()->user->create( array( 'role' => 'contributor' ) );
		wp_set_current_user( $contributor_id );
		$this->assertFalse( current_user_can( 'unfiltered_html' ), 'Contributors should not have unfiltered_html.' );

		// Attach the save filters the way WordPress does for the current user.
		kses_init();

		$id = wp_insert_post(
			array(
				'post_author'  => $contributor_id,
				'post_content' => '<p>Before</p><noscript><img src="a.jpg"></noscript><p>After</p>',
			)
		);

		$this->assertEqualHTML( '<p>Before</p><p>After</p>', get_post( $id )->post_content );
	}
}
