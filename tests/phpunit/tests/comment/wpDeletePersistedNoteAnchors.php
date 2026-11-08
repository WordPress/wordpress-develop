<?php

/**
 * Tests for deleting note anchor snapshots once the post links to the note.
 *
 * @group comment
 * @group notes
 *
 * @covers ::wp_delete_persisted_note_anchors
 * @covers ::_wp_get_note_ids_from_blocks
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

	public function test_ignores_malformed_note_ids(): void {
		$ids     = $this->create_note_with_anchor();
		$content = '<!-- wp:paragraph {"metadata":{"noteId":"not-a-number"}} -->' . "\n<p>Hello</p>\n<!-- /wp:paragraph -->";

		wp_update_post(
			array(
				'ID'           => $ids['post_id'],
				'post_content' => $content,
			)
		);

		$this->assertSame( self::ANCHOR, get_comment_meta( $ids['note_id'], '_wp_note_anchor', true ) );
	}

	/**
	 * @dataProvider data_get_note_ids_from_blocks
	 *
	 * @param string $content  Block markup.
	 * @param int[]  $expected Expected note IDs.
	 */
	public function test_get_note_ids_from_blocks( string $content, array $expected ): void {
		$this->assertSame( $expected, _wp_get_note_ids_from_blocks( parse_blocks( $content ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: int[]}>
	 */
	public function data_get_note_ids_from_blocks(): array {
		return array(
			'no notes'          => array( "<!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph -->", array() ),
			'single id'         => array( '<!-- wp:paragraph {"metadata":{"noteId":5}} --><p>Hi</p><!-- /wp:paragraph -->', array( 5 ) ),
			'list of ids'       => array( '<!-- wp:paragraph {"metadata":{"noteId":[5,"7"]}} --><p>Hi</p><!-- /wp:paragraph -->', array( 5, 7 ) ),
			'nested'            => array( '<!-- wp:group {"metadata":{"noteId":[1]}} --><div><!-- wp:paragraph {"metadata":{"noteId":[2]}} --><p>Hi</p><!-- /wp:paragraph --></div><!-- /wp:group -->', array( 1, 2 ) ),
			'malformed id'      => array( '<!-- wp:paragraph {"metadata":{"noteId":{"a":"x"}}} --><p>Hi</p><!-- /wp:paragraph -->', array() ),
			'metadata a string' => array( '<!-- wp:paragraph {"metadata":"x"} --><p>Hi</p><!-- /wp:paragraph -->', array() ),
		);
	}
}
