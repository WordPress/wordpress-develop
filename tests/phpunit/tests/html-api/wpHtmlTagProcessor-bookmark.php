<?php
/**
 * Unit tests covering WP_HTML_Tag_Processor bookmark functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 */

/**
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Tag_Processor
 */
class Tests_HtmlApi_wpHtmlTagProcessor_Bookmark extends WP_UnitTestCase {

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_bookmark
	 */
	public function test_set_bookmark() {
		$p = new WP_HTML_Tag_Processor( '<ul><li>One</li><li>Two</li><li>Three</li></ul>' );
		$p->next_tag( 'li' );
		$this->assertTrue( $p->set_bookmark( 'first li' ), 'Could not allocate a "first li" bookmark' );
		$p->next_tag( 'li' );
		$this->assertTrue( $p->set_bookmark( 'second li' ), 'Could not allocate a "second li" bookmark' );
		$this->assertTrue( $p->set_bookmark( 'first li' ), 'Could not move the "first li" bookmark' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::release_bookmark
	 */
	public function test_release_bookmark() {
		$p = new WP_HTML_Tag_Processor( '<ul><li>One</li><li>Two</li><li>Three</li></ul>' );
		$p->next_tag( 'li' );
		$this->assertFalse( $p->release_bookmark( 'first li' ), 'Released a non-existing bookmark' );
		$p->set_bookmark( 'first li' );
		$this->assertTrue( $p->release_bookmark( 'first li' ), 'Could not release a bookmark' );
	}

	/**
	 * @ticket 57788
	 *
	 * @covers WP_HTML_Tag_Processor::has_bookmark
	 */
	public function test_has_bookmark_returns_false_if_bookmark_does_not_exist() {
		$p = new WP_HTML_Tag_Processor( '<div>Test</div>' );
		$this->assertFalse( $p->has_bookmark( 'my-bookmark' ) );
	}

	/**
	 * @ticket 57788
	 *
	 * @covers WP_HTML_Tag_Processor::has_bookmark
	 */
	public function test_has_bookmark_returns_true_if_bookmark_exists() {
		$p = new WP_HTML_Tag_Processor( '<div>Test</div>' );
		$p->next_tag();
		$p->set_bookmark( 'my-bookmark' );
		$this->assertTrue( $p->has_bookmark( 'my-bookmark' ) );
	}

	/**
	 * @ticket 57788
	 *
	 * @covers WP_HTML_Tag_Processor::has_bookmark
	 */
	public function test_has_bookmark_returns_false_if_bookmark_has_been_released() {
		$p = new WP_HTML_Tag_Processor( '<div>Test</div>' );
		$p->next_tag();
		$p->set_bookmark( 'my-bookmark' );
		$p->release_bookmark( 'my-bookmark' );
		$this->assertFalse( $p->has_bookmark( 'my-bookmark' ) );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::seek
	 */
	public function test_seek() {
		$p = new WP_HTML_Tag_Processor( '<ul><li>One</li><li>Two</li><li>Three</li></ul>' );
		$p->next_tag( 'li' );
		$p->set_bookmark( 'first li' );

		$p->next_tag( 'li' );
		$p->set_attribute( 'foo-2', 'bar-2' );

		$p->seek( 'first li' );
		$p->set_attribute( 'foo-1', 'bar-1' );

		$this->assertSame(
			'<ul><li foo-1="bar-1">One</li><li foo-2="bar-2">Two</li><li>Three</li></ul>',
			$p->get_updated_html(),
			'Did not seek to the intended bookmark locations'
		);
	}

	/**
	 * @ticket 57787
	 *
	 * @covers WP_HTML_Tag_Processor::seek
	 */
	public function test_seeks_to_tag_closer_bookmark() {
		$p = new WP_HTML_Tag_Processor( '<div>First</div><span>Second</span>' );
		$p->next_tag( array( 'tag_closers' => 'visit' ) );
		$p->set_bookmark( 'first' );
		$p->next_tag( array( 'tag_closers' => 'visit' ) );
		$p->set_bookmark( 'second' );

		$p->seek( 'first' );
		$p->seek( 'second' );

		$this->assertSame(
			'DIV',
			$p->get_tag(),
			'Did not seek to the intended bookmark location'
		);
	}

	/**
	 * WP_HTML_Tag_Processor used to test for the diffs affecting
	 * the adjusted bookmark position while simultaneously adjusting
	 * the bookmark in question. As a result, updating the bookmarks
	 * of a next tag while removing two subsequent attributes in
	 * a previous tag unfolded like this:
	 *
	 * 1. Check if the first removed attribute is before the bookmark:
	 *
	 * <button twenty_one_characters 7_chars></button><button></button>
	 *         ^-------------------^                  ^
	 *           diff applied here           the bookmark is here
	 *
	 *    (Yes it is)
	 *
	 * 2. Move the bookmark to the left by the attribute length:
	 *
	 * <button twenty_one_characters 7_chars></button><button></button>
	 *                           ^
	 *                   the bookmark is here
	 *
	 * 3. Check if the second removed attribute is before the bookmark:
	 *
	 * <button twenty_one_characters 7_chars></button><button></button>
	 *                           ^   ^-----^
	 *                    bookmark    diff
	 *
	 *    This time, it isn't!
	 *
	 * The fix in the WP_HTML_Tag_Processor involves doing all the checks
	 * before moving the bookmark. This test is here to guard us from
	 * the erroneous behavior accidentally returning one day.
	 *
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::seek
	 * @covers WP_HTML_Tag_Processor::set_bookmark
	 */
	public function test_removing_long_attributes_doesnt_break_seek() {
		$input = <<<HTML
		<button twenty_one_characters 7_chars></button><button></button>
HTML;
		$p     = new WP_HTML_Tag_Processor( $input );
		$p->next_tag( 'button' );
		$p->set_bookmark( 'first' );
		$p->next_tag( 'button' );
		$p->set_bookmark( 'second' );

		$this->assertTrue(
			$p->seek( 'first' ),
			'Seek() to the first button has failed'
		);
		$p->remove_attribute( 'twenty_one_characters' );
		$p->remove_attribute( '7_chars' );

		$this->assertTrue(
			$p->seek( 'second' ),
			'Seek() to the second button has failed'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::seek
	 * @covers WP_HTML_Tag_Processor::set_bookmark
	 */
	public function test_bookmarks_complex_use_case() {
		$input           = <<<HTML
<div selected class="merge-message" checked>
	<div class="select-menu d-inline-block">
		<div checked class="BtnGroup MixedCaseHTML position-relative" />
		<div checked class="BtnGroup MixedCaseHTML position-relative">
			<button type="button" class="merge-box-button btn-group-merge rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Merge pull request
			</button>

			<button type="button" class="merge-box-button btn-group-squash rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Squash and merge
			</button>

			<button type="button" class="merge-box-button btn-group-rebase rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Rebase and merge
			</button>

			<button aria-label="Select merge method" disabled="disabled" type="button" data-view-component="true" class="select-menu-button btn BtnGroup-item"></button>
		</div>
	</div>
</div>
HTML;
		$expected_output = <<<HTML
<div selected class="merge-message" checked>
	<div class="select-menu d-inline-block">
		<div  class="BtnGroup MixedCaseHTML position-relative" />
		<div checked class="BtnGroup MixedCaseHTML position-relative">
			<button type="submit" class="merge-box-button btn-group-merge rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Merge pull request
			</button>

			<button  class="hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Squash and merge
			</button>

			<button id="rebase-and-merge"     disabled="">
			  Rebase and merge
			</button>

			<button id="last-button"     ></button>
		</div>
	</div>
</div>
HTML;
		$p               = new WP_HTML_Tag_Processor( $input );
		$p->next_tag( 'div' );
		$p->next_tag( 'div' );
		$p->next_tag( 'div' );
		$p->set_bookmark( 'first div' );
		$p->next_tag( 'button' );
		$p->set_bookmark( 'first button' );
		$p->next_tag( 'button' );
		$p->set_bookmark( 'second button' );
		$p->next_tag( 'button' );
		$p->set_bookmark( 'third button' );
		$p->next_tag( 'button' );
		$p->set_bookmark( 'fourth button' );

		$p->seek( 'first button' );
		$p->set_attribute( 'type', 'submit' );

		$this->assertTrue(
			$p->seek( 'third button' ),
			'Seek() to the third button failed'
		);
		$p->remove_attribute( 'class' );
		$p->remove_attribute( 'type' );
		$p->remove_attribute( 'aria-expanded' );
		$p->set_attribute( 'id', 'rebase-and-merge' );
		$p->remove_attribute( 'data-details-container' );

		$this->assertTrue(
			$p->seek( 'first div' ),
			'Seek() to the first div failed'
		);
		$p->set_attribute( 'checked', false );

		$this->assertTrue(
			$p->seek( 'fourth button' ),
			'Seek() to the fourth button failed'
		);
		$p->set_attribute( 'id', 'last-button' );
		$p->remove_attribute( 'class' );
		$p->remove_attribute( 'type' );
		$p->remove_attribute( 'checked' );
		$p->remove_attribute( 'aria-label' );
		$p->remove_attribute( 'disabled' );
		$p->remove_attribute( 'data-view-component' );

		$this->assertTrue(
			$p->seek( 'second button' ),
			'Seek() to the second button failed'
		);
		$p->remove_attribute( 'type' );
		$p->set_attribute( 'class', 'hx_create-pr-button' );

		$this->assertSame(
			$expected_output,
			$p->get_updated_html(),
			'Performing several attribute updates on different tags does not produce the expected HTML snippet'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::seek
	 */
	public function test_updates_bookmark_for_additions_after_both_sides() {
		$p = new WP_HTML_Tag_Processor( '<div>First</div><div>Second</div>' );
		$p->next_tag();
		$p->set_bookmark( 'first' );
		$p->next_tag();
		$p->add_class( 'second' );

		$p->seek( 'first' );
		$p->add_class( 'first' );

		$this->assertSame(
			'<div class="first">First</div><div class="second">Second</div>',
			$p->get_updated_html(),
			'The bookmark was updated incorrectly in response to HTML markup updates'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::seek
	 */
	public function test_updates_bookmark_for_additions_before_both_sides() {
		$p = new WP_HTML_Tag_Processor( '<div>First</div><div>Second</div>' );
		$p->next_tag();
		$p->set_bookmark( 'first' );
		$p->next_tag();
		$p->set_bookmark( 'second' );

		$p->seek( 'first' );
		$p->add_class( 'first' );

		$p->seek( 'second' );
		$p->add_class( 'second' );

		$this->assertSame(
			'<div class="first">First</div><div class="second">Second</div>',
			$p->get_updated_html(),
			'The bookmark was updated incorrectly in response to HTML markup updates'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::seek
	 */
	public function test_updates_bookmark_for_deletions_after_both_sides() {
		$p = new WP_HTML_Tag_Processor( '<div>First</div><div disabled>Second</div>' );
		$p->next_tag();
		$p->set_bookmark( 'first' );
		$p->next_tag();
		$p->remove_attribute( 'disabled' );

		$p->seek( 'first' );
		$p->set_attribute( 'untouched', true );

		$this->assertSame(
			/*
			 * It shouldn't be necessary to assert the extra space after the tag
			 * following the attribute removal, but doing so makes the test easier
			 * to see than it would be if parsing the output HTML for proper
			 * validation. If the Tag Processor changes so that this space no longer
			 * appears then this test should be updated to reflect that. The space
			 * is not required.
			 */
			'<div untouched>First</div><div >Second</div>',
			$p->get_updated_html(),
			'The bookmark was incorrectly in response to HTML markup updates'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::seek
	 */
	public function test_updates_bookmark_for_deletions_before_both_sides() {
		$p = new WP_HTML_Tag_Processor( '<div disabled>First</div><div>Second</div>' );
		$p->next_tag();
		$p->set_bookmark( 'first' );
		$p->next_tag();
		$p->set_bookmark( 'second' );

		$p->seek( 'first' );
		$p->remove_attribute( 'disabled' );

		$p->seek( 'second' );
		$p->set_attribute( 'safe', true );

		$this->assertSame(
			/*
			 * It shouldn't be necessary to assert the extra space after the tag
			 * following the attribute removal, but doing so makes the test easier
			 * to see than it would be if parsing the output HTML for proper
			 * validation. If the Tag Processor changes so that this space no longer
			 * appears then this test should be updated to reflect that. The space
			 * is not required.
			 */
			'<div >First</div><div safe>Second</div>',
			$p->get_updated_html(),
			'The bookmark was updated incorrectly in response to HTML markup updates'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_bookmark
	 */
	public function test_limits_the_number_of_bookmarks() {
		$p = new WP_HTML_Tag_Processor( '<ul><li>One</li><li>Two</li><li>Three</li></ul>' );
		$p->next_tag( 'li' );

		for ( $i = 0; $i < WP_HTML_Tag_Processor::MAX_BOOKMARKS; $i++ ) {
			$this->assertTrue( $p->set_bookmark( "bookmark $i" ), "Could not allocate the bookmark #$i" );
		}

		$this->setExpectedIncorrectUsage( 'WP_HTML_Tag_Processor::set_bookmark' );
		$this->assertFalse( $p->set_bookmark( 'final bookmark' ), "Allocated $i bookmarks, which is one above the limit" );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::seek
	 */
	public function test_limits_the_number_of_seek_calls() {
		$p = new WP_HTML_Tag_Processor( '<ul><li>One</li><li>Two</li><li>Three</li></ul>' );
		$p->next_tag( 'li' );
		$p->set_bookmark( 'bookmark' );

		for ( $i = 0; $i < WP_HTML_Tag_Processor::MAX_SEEK_OPS; $i++ ) {
			$this->assertTrue( $p->seek( 'bookmark' ), 'Could not seek to the "bookmark"' );
		}

		$this->setExpectedIncorrectUsage( 'WP_HTML_Tag_Processor::seek' );
		$this->assertFalse( $p->seek( 'bookmark' ), "$i-th seek() to the bookmark succeeded, even though it should exceed the allowed limit" );
	}
}
