<?php

/**
 * Tests for the edit class checks in Text_Diff and Text_Diff_Renderer.
 *
 * @group diff
 */
final class Tests_Diff_TextDiff extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-includes/Text/Diff.php';
		require_once ABSPATH . 'wp-includes/Text/Diff/Renderer.php';
	}

	/**
	 * @ticket 66418
	 *
	 * @covers Text_Diff::countAddedLines
	 * @covers Text_Diff::countDeletedLines
	 * @covers Text_Diff::isEmpty
	 * @covers Text_Diff::lcs
	 *
	 * @dataProvider data_edit_counts
	 *
	 * @param non-empty-string[] $from_lines      Lines before the change.
	 * @param non-empty-string[] $to_lines        Lines after the change.
	 * @param non-negative-int   $added           Expected number of added lines.
	 * @param non-negative-int   $deleted         Expected number of deleted lines.
	 * @param bool               $is_empty        Whether the diff is expected to have no changes.
	 * @param non-negative-int   $unchanged_lines Expected number of unchanged lines.
	 */
	public function test_edit_counts( array $from_lines, array $to_lines, int $added, int $deleted, bool $is_empty, int $unchanged_lines ) {
		$diff = new Text_Diff( 'native', array( $from_lines, $to_lines ) );

		$this->assertSame( $added, $diff->countAddedLines(), 'Added lines do not match.' );
		$this->assertSame( $deleted, $diff->countDeletedLines(), 'Deleted lines do not match.' );
		$this->assertSame( $is_empty, $diff->isEmpty(), 'isEmpty() does not match.' );
		$this->assertSame( $unchanged_lines, $diff->lcs(), 'Unchanged lines do not match.' );
	}

	/**
	 * Data provider for {@see self::test_edit_counts()}.
	 *
	 * @return array<non-falsy-string, array{ non-empty-string[], non-empty-string[], non-negative-int, non-negative-int, bool, non-negative-int }>
	 */
	public static function data_edit_counts(): array {
		return array(
			'both empty'           => array( array(), array(), 0, 0, true, 0 ),
			'identical'            => array( array( 'a', 'b' ), array( 'a', 'b' ), 0, 0, true, 2 ),
			'add only'             => array( array( 'a' ), array( 'a', 'b' ), 1, 0, false, 1 ),
			'delete only'          => array( array( 'a', 'b' ), array( 'a' ), 0, 1, false, 1 ),
			'changed only'         => array( array( 'a', 'b', 'c' ), array( 'x', 'b', 'z' ), 2, 2, false, 1 ),
			'change, copy and add' => array( array( 'a', 'b' ), array( 'x', 'b', 'c' ), 2, 1, false, 1 ),
		);
	}

	/**
	 * @ticket 66418
	 *
	 * @covers Text_Diff_Renderer::render
	 */
	public function test_render_keeps_context_lines_from_copied_blocks() {
		$diff     = new Text_Diff( 'native', array( array( 'a', 'b', 'c', 'd', 'e' ), array( 'a', 'b', 'X', 'd', 'e' ) ) );
		$renderer = new Text_Diff_Renderer(
			array(
				'leading_context_lines'  => 1,
				'trailing_context_lines' => 1,
			)
		);

		$this->assertSame( "2,4c2,4\n  b\n< c\n---\n> X\n  d\n", $renderer->render( $diff ) );
	}
}
