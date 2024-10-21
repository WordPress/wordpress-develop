<?php

/**
 * @group post
 * @group template
 *
 * @covers ::wp_dropdown_pages
 */
class Tests_Post_wpDropdownPages extends WP_UnitTestCase {

	public function test_wp_dropdown_pages() {
		$none = wp_dropdown_pages( array( 'echo' => 0 ) );
		$this->assertEmpty( $none );

		$bump          = '&nbsp;&nbsp;&nbsp;';
		$page_id       = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$child_id      = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_id,
			)
		);
		$grandchild_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $child_id,
			)
		);

		$title1 = get_post( $page_id )->post_title;
		$title2 = get_post( $child_id )->post_title;
		$title3 = get_post( $grandchild_id )->post_title;

		$lineage = <<<LINEAGE
<select name='page_id' id='page_id'>
	<option class="level-0" value="$page_id">$title1</option>
	<option class="level-1" value="$child_id">{$bump}$title2</option>
	<option class="level-2" value="$grandchild_id">{$bump}{$bump}$title3</option>
</select>

LINEAGE;

		$output = wp_dropdown_pages( array( 'echo' => 0 ) );
		$this->assertSameIgnoreEOL( $lineage, $output );

		$depth = <<<DEPTH
<select name='page_id' id='page_id'>
	<option class="level-0" value="$page_id">$title1</option>
</select>

DEPTH;

		$output = wp_dropdown_pages(
			array(
				'echo'  => 0,
				'depth' => 1,
			)
		);
		$this->assertSameIgnoreEOL( $depth, $output );

		$option_none = <<<NONE
<select name='page_id' id='page_id'>
	<option value="Woo">Hoo</option>
	<option class="level-0" value="$page_id">$title1</option>
</select>

NONE;

		$output = wp_dropdown_pages(
			array(
				'echo'              => 0,
				'depth'             => 1,
				'show_option_none'  => 'Hoo',
				'option_none_value' => 'Woo',
			)
		);
		$this->assertSameIgnoreEOL( $option_none, $output );

		$option_no_change = <<<NO
<select name='page_id' id='page_id'>
	<option value="-1">Burrito</option>
	<option value="Woo">Hoo</option>
	<option class="level-0" value="$page_id">$title1</option>
</select>

NO;

		$output = wp_dropdown_pages(
			array(
				'echo'                  => 0,
				'depth'                 => 1,
				'show_option_none'      => 'Hoo',
				'option_none_value'     => 'Woo',
				'show_option_no_change' => 'Burrito',
			)
		);
		$this->assertSameIgnoreEOL( $option_no_change, $output );
	}

	/**
	 * @ticket 12494
	 */
	public function test_wp_dropdown_pages_value_field_should_default_to_ID() {
		$p = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);

		$found = wp_dropdown_pages(
			array(
				'echo' => 0,
			)
		);

		// Should contain page ID by default.
		$this->assertStringContainsString( 'value="' . $p . '"', $found );
	}

	/**
	 * @ticket 12494
	 */
	public function test_wp_dropdown_pages_value_field_ID() {
		$p = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);

		$found = wp_dropdown_pages(
			array(
				'echo'        => 0,
				'value_field' => 'ID',
			)
		);

		$this->assertStringContainsString( 'value="' . $p . '"', $found );
	}

	/**
	 * @ticket 12494
	 */
	public function test_wp_dropdown_pages_value_field_post_name() {
		$p = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'foo',
			)
		);

		$found = wp_dropdown_pages(
			array(
				'echo'        => 0,
				'value_field' => 'post_name',
			)
		);

		$this->assertStringContainsString( 'value="foo"', $found );
	}

	/**
	 * @ticket 12494
	 */
	public function test_wp_dropdown_pages_value_field_should_fall_back_on_ID_when_an_invalid_value_is_provided() {
		$p = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'foo',
			)
		);

		$found = wp_dropdown_pages(
			array(
				'echo'        => 0,
				'value_field' => 'foo',
			)
		);

		$this->assertStringContainsString( 'value="' . $p . '"', $found );
	}

	/**
	 * @ticket 30082
	 */
	public function test_wp_dropdown_pages_should_not_contain_class_attribute_when_no_class_is_passed() {
		$p = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'foo',
			)
		);

		$found = wp_dropdown_pages(
			array(
				'echo' => 0,
			)
		);

		$this->assertDoesNotMatchRegularExpression( '/<select[^>]+class=\'/', $found );
	}

	/**
	 * @ticket 30082
	 */
	public function test_wp_dropdown_pages_should_obey_class_parameter() {
		$p = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'foo',
			)
		);

		$found = wp_dropdown_pages(
			array(
				'echo'  => 0,
				'class' => 'bar',
			)
		);

		$this->assertMatchesRegularExpression( '/<select[^>]+class=\'bar\'/', $found );
	}
}
