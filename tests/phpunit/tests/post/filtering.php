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

	/**
	 * @ticket 6297
	 */
	public function test_post_content_balances_tags_across_nextpage() {
		$content  = '<em>first page<!--nextpage-->second page</em>';
		$expected = '<em>first page</em><!--nextpage-->second page';

		$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$this->assertSame( $expected, get_post( $post_id )->post_content );
	}

	/**
	 * @ticket 6297
	 */
	public function test_post_content_preserves_nextpage_block_delimiter() {
		$delimiter = "<!-- wp:nextpage -->\n<!--nextpage-->\n<!-- /wp:nextpage -->";
		$content   = '<em>first page' . $delimiter . 'second page</em>';
		$expected  = '<em>first page</em>' . $delimiter . 'second page';

		$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$this->assertSame( $expected, get_post( $post_id )->post_content );
	}

	/**
	 * @ticket 6297
	 */
	public function test_post_content_does_not_balance_at_more_boundary() {
		$content = '<blockquote>Above<!--more-->Below</blockquote>';

		$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$this->assertSame( $content, get_post( $post_id )->post_content );
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
}
