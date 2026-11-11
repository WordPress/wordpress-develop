<?php

/**
 * Tests for deleting note anchor snapshots once the post links to the note.
 *
 * @group comment
 * @group notes
 *
 * @covers ::wp_delete_persisted_note_anchors
 * @covers ::_wp_sanitize_note_anchor
 */
class Tests_Comment_WpDeletePersistedNoteAnchors extends WP_UnitTestCase {

	/**
	 * A snapshot as the editor stores it.
	 *
	 * @var list<array{path: list<int>, content: string}>
	 */
	private const ANCHOR = array(
		array(
			'path'    => array( 0 ),
			'content' => "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->",
		),
	);

	/**
	 * Creates a top-level note with a snapshot on a new post.
	 *
	 * @return array{post_id: int, note_id: int}
	 */
	private function create_note_with_anchor(): array {
		$post_id = self::factory()->post->create();
		$note_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => 'note',
				'comment_content' => 'A note.',
			)
		);
		add_comment_meta( $note_id, '_wp_note_anchor', self::ANCHOR );

		return array(
			'post_id' => $post_id,
			'note_id' => $note_id,
		);
	}

	/**
	 * Returns paragraph block markup linked to the given note IDs.
	 *
	 * @param int[] $note_ids Note IDs.
	 * @return string Block markup.
	 */
	private function paragraph_with_notes( array $note_ids ): string {
		$attrs = wp_json_encode( array( 'metadata' => array( 'noteId' => $note_ids ) ) );
		return "<!-- wp:paragraph $attrs -->\n<p>Hello</p>\n<!-- /wp:paragraph -->";
	}

	public function test_registers_note_anchor_meta(): void {
		$this->assertTrue( registered_meta_key_exists( 'comment', '_wp_note_anchor' ) );
	}

	public function test_strips_disallowed_html_without_unfiltered_html(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertFalse( current_user_can( 'unfiltered_html' ) );

		$value = _wp_sanitize_note_anchor(
			array(
				array(
					'path'    => array( 0, '2' ),
					'content' => "<!-- wp:paragraph {\"metadata\":{\"noteId\":[3]}} -->\n<p><mark class=\"wp-note\" data-id=\"new\">Hi</mark><script>alert(1)</script></p>\n<!-- /wp:paragraph -->",
				),
				'not an anchor',
			)
		);

		$this->assertSame(
			array(
				array(
					'path'    => array( 0, 2 ),
					'content' => "<!-- wp:paragraph {\"metadata\":{\"noteId\":[3]}} -->\n<p><mark class=\"wp-note\" data-id=\"new\">Hi</mark></p>\n<!-- /wp:paragraph -->",
				),
			),
			$value
		);
	}

	public function test_keeps_html_with_unfiltered_html(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}
		wp_set_current_user( $user_id );
		$anchor = array(
			array(
				'path'    => array( 0 ),
				'content' => '<!-- wp:html --><script>alert(1)</script><!-- /wp:html -->',
			),
		);

		$this->assertSame( $anchor, _wp_sanitize_note_anchor( $anchor ) );
	}

	public function test_deletes_anchor_when_saved_content_links_to_note(): void {
		$ids = $this->create_note_with_anchor();

		wp_update_post(
			array(
				'ID'           => $ids['post_id'],
				'post_content' => $this->paragraph_with_notes( array( $ids['note_id'] ) ),
			)
		);

		$this->assertSame( '', get_comment_meta( $ids['note_id'], '_wp_note_anchor', true ) );
	}

	public function test_deletes_anchor_when_note_is_linked_from_an_inner_block(): void {
		$ids     = $this->create_note_with_anchor();
		$content = "<!-- wp:group -->\n<div class=\"wp-block-group\">" . $this->paragraph_with_notes( array( $ids['note_id'] ) ) . "</div>\n<!-- /wp:group -->";

		wp_update_post(
			array(
				'ID'           => $ids['post_id'],
				'post_content' => $content,
			)
		);

		$this->assertSame( '', get_comment_meta( $ids['note_id'], '_wp_note_anchor', true ) );
	}

	public function test_keeps_anchor_when_saved_content_does_not_link_to_note(): void {
		$ids = $this->create_note_with_anchor();

		wp_update_post(
			array(
				'ID'           => $ids['post_id'],
				'post_content' => "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->",
			)
		);

		$this->assertSame( self::ANCHOR, get_comment_meta( $ids['note_id'], '_wp_note_anchor', true ) );
	}

	public function test_keeps_anchor_of_a_note_on_another_post(): void {
		$ids   = $this->create_note_with_anchor();
		$other = self::factory()->post->create();

		wp_update_post(
			array(
				'ID'           => $other,
				'post_content' => $this->paragraph_with_notes( array( $ids['note_id'] ) ),
			)
		);

		$this->assertSame( self::ANCHOR, get_comment_meta( $ids['note_id'], '_wp_note_anchor', true ) );
	}

	public function test_deletes_anchor_when_note_id_is_not_in_a_list(): void {
		$ids     = $this->create_note_with_anchor();
		$content = '<!-- wp:paragraph {"metadata":{"noteId":' . $ids['note_id'] . '}} -->' . "\n<p>Hello</p>\n<!-- /wp:paragraph -->";

		wp_update_post(
			array(
				'ID'           => $ids['post_id'],
				'post_content' => $content,
			)
		);

		$this->assertSame( '', get_comment_meta( $ids['note_id'], '_wp_note_anchor', true ) );
	}

	/**
	 * @dataProvider data_malformed_metadata
	 *
	 * @param string $attrs Block attributes as JSON.
	 */
	public function test_ignores_malformed_metadata( string $attrs ): void {
		$ids     = $this->create_note_with_anchor();
		$content = "<!-- wp:paragraph $attrs -->\n<p>Hello</p>\n<!-- /wp:paragraph -->";

		wp_update_post(
			array(
				'ID'           => $ids['post_id'],
				'post_content' => $content,
			)
		);

		$this->assertSame( self::ANCHOR, get_comment_meta( $ids['note_id'], '_wp_note_anchor', true ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_malformed_metadata(): array {
		return array(
			'note id not a number' => array( '{"metadata":{"noteId":"not-a-number"}}' ),
			'note id an object'    => array( '{"metadata":{"noteId":{"a":"x"}}}' ),
			'metadata a string'    => array( '{"metadata":"x"}' ),
		);
	}
}
