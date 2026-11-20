<?php

/**
 * Tests for WP_Text_Diff_Renderer_Table.
 *
 * @group diff
 */
class Tests_Diff_WpTextDiffRendererTable extends WP_UnitTestCase {
	/**
	 * @var WP_Text_Diff_Renderer_Table
	 */
	private $diff_renderer_table;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once ABSPATH . 'wp-includes/Text/Diff.php';
		require_once ABSPATH . 'wp-includes/Text/Diff/Renderer.php';
		require_once ABSPATH . 'wp-includes/Text/Diff/Renderer/inline.php';
		require_once ABSPATH . 'wp-includes/class-wp-text-diff-renderer-table.php';
		require_once ABSPATH . 'wp-includes/class-wp-text-diff-renderer-inline.php';
	}

	public function set_up() {
		parent::set_up();
		$this->diff_renderer_table = new WP_Text_Diff_Renderer_Table();
	}

	/**
	 * @ticket 66095
	 * @covers ::wp_text_diff
	 */
	public function test_wp_text_diff_highlights_a_changed_word_in_a_long_table() {
		$rows = str_repeat( '<tr><td>unchanged cell</td></tr>', 599 );
		$old  = "<table>$rows<tr><td>one old cell</td></tr></table>";
		$new  = "<table>$rows<tr><td>one new cell</td></tr></table>";

		$diff = wp_text_diff( $old, $new );

		$this->assertStringContainsString( '<del>old</del>', $diff );
		$this->assertStringContainsString( '<ins>new</ins>', $diff );
		$this->assertStringContainsString( "class='diff-context'", $diff );
	}

	/**
	 * @ticket 66095
	 * @ticket 48973
	 * @covers WP_Text_Diff_Renderer_Table::_changed
	 */
	public function test_changed_lists_deletions_before_additions_above_the_pair_limit() {
		$renderer = $this->diff_renderer_table;
		$old      = array( 'alpha & old', 'bravo & old', 'charlie & old' );
		$new      = array( 'alpha & new', 'bravo & new', 'charlie & new' );
		$diff     = $renderer->_changed( $old, $new );

		$this->assertStringContainsString( '<del>old</del>', $diff );
		$this->assertStringContainsString( '<ins>new</ins>', $diff );

		$renderer->_max_changed_line_pairs = 4;
		$this->assertSame( $renderer->_deleted( $old ) . $renderer->_added( $new ), $renderer->_changed( $old, $new ) );
	}

	/**
	 * @ticket 66095
	 * @covers WP_Text_Diff_Renderer_Table::_changed
	 */
	public function test_changed_keeps_long_unbroken_lines_in_one_escaped_row() {
		$prefix   = str_repeat( 'x', 9000 );
		$suffix   = str_repeat( 'y', 2999 );
		$renderer = $this->diff_renderer_table;
		$diff     = $renderer->_changed( array( $prefix . '&old' ), array( $prefix . '&' . $suffix ) );

		$this->assertSame(
			'<tr>' . $renderer->deletedLine( $prefix . '&amp;old' ) . $renderer->addedLine( $prefix . '&amp;' . $suffix ) . "</tr>\n",
			$diff
		);
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__get()
	 *
	 * @param string $property_name Property name to get.
	 * @param mixed $expected       Expected value.
	 */
	public function test_should_get_compat_fields( $property_name, $expected ) {
		$this->assertSame( $expected, $this->diff_renderer_table->$property_name );
	}

	/**
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__get()
	 */
	public function test_should_throw_deprecation_when_getting_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_Text_Diff_Renderer_Table::__get(): ' .
			'The property `undeclared_property` is not declared. Getting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->assertNull( $this->diff_renderer_table->undeclared_property, 'Getting a dynamic property should return null from WP_Text_Diff_Renderer_Table::__get()' );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__set()
	 *
	 * @param string $property_name Property name to set.
	 */
	public function test_should_set_compat_fields( $property_name ) {
		$value                                     = uniqid();
		$this->diff_renderer_table->$property_name = $value;

		$this->assertSame( $value, $this->diff_renderer_table->$property_name );
	}

	/**
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__set()
	 */
	public function test_should_throw_deprecation_when_setting_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_Text_Diff_Renderer_Table::__set(): ' .
			'The property `undeclared_property` is not declared. Setting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->diff_renderer_table->undeclared_property = 'some value';
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__isset()
	 *
	 * @param string $property_name Property name to check.
	 * @param mixed $expected       Expected value.
	 */
	public function test_should_isset_compat_fields( $property_name, $expected ) {
		$actual = isset( $this->diff_renderer_table->$property_name );
		if ( is_null( $expected ) ) {
			$this->assertFalse( $actual );
		} else {
			$this->assertTrue( $actual );
		}
	}

	/**
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__isset()
	 */
	public function test_should_throw_deprecation_when_isset_of_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_Text_Diff_Renderer_Table::__isset(): ' .
			'The property `undeclared_property` is not declared. Checking `isset()` on a dynamic property ' .
			'is deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->assertFalse( isset( $this->diff_renderer_table->undeclared_property ), 'Checking a dynamic property should return false from WP_Text_Diff_Renderer_Table::__isset()' );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__unset()
	 *
	 * @param string $property_name Property name to unset.
	 */
	public function test_should_unset_compat_fields( $property_name ) {
		unset( $this->diff_renderer_table->$property_name );
		$this->assertFalse( isset( $this->diff_renderer_table->$property_name ) );
	}

	/**
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__unset()
	 */
	public function test_should_throw_deprecation_when_unset_of_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_Text_Diff_Renderer_Table::__unset(): ' .
			'A property `undeclared_property` is not declared. Unsetting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		unset( $this->diff_renderer_table->undeclared_property );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_compat_fields() {
		return array(
			'_show_split_view'           => array(
				'property_name' => '_show_split_view',
				'expected'      => true,
			),
			'inline_diff_renderer'       => array(
				'property_name' => 'inline_diff_renderer',
				'expected'      => 'WP_Text_Diff_Renderer_inline',
			),
			'_diff_threshold'            => array(
				'property_name' => '_diff_threshold',
				'expected'      => 0.6,
			),
			'_max_word_diff_line_length' => array(
				'property_name' => '_max_word_diff_line_length',
				'expected'      => 10000,
			),
			'_max_changed_line_pairs'    => array(
				'property_name' => '_max_changed_line_pairs',
				'expected'      => 100000,
			),
		);
	}
}
