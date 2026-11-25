<?php
/**
 * Tests how wp_kses() handles NOSCRIPT elements in untrusted content.
 *
 * @group kses
 *
 * @covers ::wp_kses
 * @covers ::wp_sanitize_html_kses
 */
class Tests_Kses_Noscript extends WP_UnitTestCase {

	/**
	 * Tests that a NOSCRIPT element is removed with its contents and the content after it is kept.
	 *
	 * @ticket 66674
	 *
	 * @dataProvider data_noscript_elements_that_end_at_the_same_place
	 *
	 * @param string $content  Content to sanitize.
	 * @param string $expected Expected sanitized content.
	 */
	public function test_removes_noscript_and_keeps_following_content( $content, $expected ) {
		$this->assertEqualHTML( $expected, wp_kses_post( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_noscript_elements_that_end_at_the_same_place() {
		return array(
			'Image fallback'                        => array(
				'<p>Before</p><noscript><img src="a.jpg"></noscript><p>After</p>',
				'<p>Before</p><p>After</p>',
			),
			'Text only'                             => array(
				'<p>Before</p><noscript>Enable JavaScript.</noscript><p>After</p>',
				'<p>Before</p><p>After</p>',
			),
			'Empty'                                 => array(
				'<p>Before</p><noscript></noscript><p>After</p>',
				'<p>Before</p><p>After</p>',
			),
			'Upper and mixed case tag names'        => array(
				'<p>Before</p><NOSCRIPT><img src="a.jpg"></NoScript><p>After</p>',
				'<p>Before</p><p>After</p>',
			),
			'Whitespace before the closing bracket' => array(
				"<p>Before</p><noscript><img src=\"a.jpg\"></noscript\n><p>After</p>",
				'<p>Before</p><p>After</p>',
			),
			'Solidus after the closing tag name'    => array(
				'<p>Before</p><noscript><img src="a.jpg"></noscript/><p>After</p>',
				'<p>Before</p><p>After</p>',
			),
			'Two elements'                          => array(
				'<p>One</p><noscript>a</noscript><p>Two</p><noscript>b</noscript><p>Three</p>',
				'<p>One</p><p>Two</p><p>Three</p>',
			),
			'Similar closing tag inside'            => array(
				'<p>Before</p><noscript><!-- </noscriptx> --></noscript><p>After</p>',
				'<p>Before</p><p>After</p>',
			),
			'Squarespace lazy-loaded image'         => array(
				'<p>Intro paragraph.</p><noscript><img src="https://images.squarespace-cdn.com/content/v1/abc/photo.jpg" alt="" /></noscript><img class="thumb-image" src="https://images.squarespace-cdn.com/content/v1/abc/photo.jpg" alt="" /><p>Rest of the post.</p>',
				'<p>Intro paragraph.</p><img class="thumb-image" src="https://images.squarespace-cdn.com/content/v1/abc/photo.jpg" alt=""><p>Rest of the post.</p>',
			),
			'Closed elements inside'                => array(
				'<p>Before</p><noscript><p>Enable <b>JavaScript</b>.</p><div><span>Thanks</span></div></noscript><p>After</p>',
				'<p>Before</p><p>After</p>',
			),
			'Disqus embed code'                     => array(
				'<div id="disqus_thread"></div><noscript>Please enable JavaScript to view the <a href="https://disqus.com/?ref_noscript" rel="nofollow">comments powered by Disqus.</a></noscript><p>After</p>',
				'<div id="disqus_thread"></div><p>After</p>',
			),
		);
	}

	/**
	 * Tests that content is not kept after a NOSCRIPT element that could end in different
	 * places, depending on whether a browser has scripting enabled.
	 *
	 * With scripting enabled, a browser reads the contents of a NOSCRIPT element as plain
	 * text up to the first `</noscript`. With scripting disabled, it parses them as HTML.
	 *
	 * @ticket 66674
	 *
	 * @dataProvider data_noscript_elements_that_could_end_in_different_places
	 *
	 * @param string $content  Content to sanitize.
	 * @param string $expected Expected sanitized content.
	 */
	public function test_stops_at_ambiguous_noscript( $content, $expected ) {
		$this->assertEqualHTML( $expected, wp_kses_post( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_noscript_elements_that_could_end_in_different_places() {
		return array(
			'Closing tag inside a SCRIPT element'   => array(
				'a<noscript>b<script>c</noscript>d</script>e<h1>site</h1>',
				'a',
			),
			'Closing tag inside a comment'          => array(
				'<p>Before</p><noscript><!--</noscript><p>Inside</p>--></noscript><p>After</p>',
				'<p>Before</p>',
			),
			'Closing tag inside an attribute value' => array(
				'<p>Before</p><noscript><img alt="</noscript><p>Inside</p>"></noscript><p>After</p>',
				'<p>Before</p>',
			),
			'Closing tag inside a TEXTAREA element' => array(
				'<p>Before</p><noscript><textarea></noscript><p>Inside</p></textarea></noscript><p>After</p>',
				'<p>Before</p>',
			),
			'Nested NOSCRIPT element'               => array(
				'<p>Before</p><noscript><noscript></noscript><p>Inside</p></noscript><p>After</p>',
				'<p>Before</p>',
			),
			'Unclosed NOSCRIPT element'             => array(
				'<p>Before</p><noscript><p>Inside</p>',
				'<p>Before</p>',
			),
			'Unclosed P element inside'             => array(
				'<p>Before</p><noscript><p>Inside</noscript><p>After</p>',
				'<p>Before</p>',
			),
			'Unclosed DIV element inside'           => array(
				'<p>Before</p><noscript><div></noscript><p>Inside</p></div></noscript><p>After</p>',
				'<p>Before</p>',
			),
			'Unmatched closing tag inside'          => array(
				'<p>Before</p><noscript></span></noscript><p>After</p>',
				'<p>Before</p>',
			),
			'Closing tag for an element outside'    => array(
				'<div><p>Before</p><noscript></div><p>Inside</p></noscript><p>After</p></div>',
				'<div><p>Before</p></div>',
			),
			'SVG element inside'                    => array(
				'<p>Before</p><noscript><svg><![CDATA[ > </noscript> ]]></svg></noscript><p>After</p>',
				'<p>Before</p>',
			),
			'PLAINTEXT element inside'              => array(
				'<p>Before</p><noscript><plaintext></noscript><p>After</p>',
				'<p>Before</p>',
			),
			'Block delimiter inside'                => array(
				'<p>Before</p><noscript><!-- wp:paragraph --></noscript><p>After</p>',
				'<p>Before</p>',
			),
		);
	}

	/**
	 * Tests that the content after a NOSCRIPT element is kept when a user without
	 * the unfiltered_html capability saves a post.
	 *
	 * @ticket 66674
	 *
	 * @covers ::wp_insert_post
	 */
	public function test_post_content_after_noscript_is_saved() {
		kses_init_filters();

		try {
			$post_id = self::factory()->post->create(
				array(
					'post_content' => '<p>Before</p><noscript><img src="a.jpg"></noscript><p>After</p>',
				)
			);
		} finally {
			kses_remove_filters();
		}

		$this->assertEqualHTML( '<p>Before</p><p>After</p>', get_post( $post_id )->post_content );
	}
}
