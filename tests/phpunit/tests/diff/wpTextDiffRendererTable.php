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
	 * @ticket 48973
	 * @covers WP_Text_Diff_Renderer_Table::_changed
	 * @dataProvider data_changed_block_limits
	 *
	 * @param string $limit Name of the limit argument.
	 * @param int    $value Limit that the 3 old and 3 new lines exceed.
	 */
	public function test_changed_lists_deletions_before_additions_above_a_block_limit( $limit, $value ) {
		$renderer = $this->diff_renderer_table;
		$old      = array( 'alpha & old', 'bravo & old', 'charlie & old' );
		$new      = array( 'alpha & new', 'bravo & new', 'charlie & new' );
		$fallback = $renderer->_deleted( $old ) . $renderer->_added( $new );

		$this->assertNotSame( $fallback, $renderer->_changed( $old, $new ) );

		$limited = new WP_Text_Diff_Renderer_Table( array( $limit => $value ) );
		$this->assertSame( $fallback, $limited->_changed( $old, $new ) );
	}

	/**
	 * Data provider for test_changed_lists_deletions_before_additions_above_a_block_limit().
	 *
	 * @return array
	 */
	public static function data_changed_block_limits() {
		return array(
			'line pairs' => array( 'max_changed_line_pairs', 8 ),
			'lines'      => array( 'max_changed_lines', 5 ),
		);
	}

	/**
	 * @ticket 66095
	 * @covers WP_Text_Diff_Renderer_Table::_changed
	 * @dataProvider data_long_line_pairs
	 *
	 * @param string $old_line Old line.
	 * @param string $new_line New line.
	 */
	public function test_changed_skips_word_highlighting_when_a_line_exceeds_the_length_limit( $old_line, $new_line ) {
		$renderer = $this->diff_renderer_table;

		$this->assertSame(
			'<tr>' . $renderer->deletedLine( str_replace( '&', '&amp;', $old_line ) ) . $renderer->addedLine( str_replace( '&', '&amp;', $new_line ) ) . "</tr>\n",
			$renderer->_changed( array( $old_line ), array( $new_line ) )
		);
	}

	/**
	 * Data provider for test_changed_skips_word_highlighting_when_a_line_exceeds_the_length_limit().
	 *
	 * @return array
	 */
	public static function data_long_line_pairs() {
		$short = str_repeat( 'x', 9000 ) . '&old';
		$long  = str_repeat( 'x', 9000 ) . '&' . str_repeat( 'y', 2999 );

		return array(
			'long old line' => array( $long, $short ),
			'long new line' => array( $short, $long ),
		);
	}

	/**
	 * @ticket 66095
	 * @covers ::wp_text_diff
	 */
	public function test_wp_text_diff_limits_can_be_raised_through_args() {
		$old = str_repeat( 'x', 12000 ) . ' old';
		$new = str_repeat( 'x', 12000 ) . ' new';

		$this->assertStringNotContainsString( '<del>old</del>', wp_text_diff( $old, $new ) );
		$this->assertStringContainsString( '<del>old</del>', wp_text_diff( $old, $new, array( 'max_word_diff_line_length' => 20000 ) ) );
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
			'_show_split_view'     => array(
				'property_name' => '_show_split_view',
				'expected'      => true,
			),
			'inline_diff_renderer' => array(
				'property_name' => 'inline_diff_renderer',
				'expected'      => 'WP_Text_Diff_Renderer_inline',
			),
			'_diff_threshold'      => array(
				'property_name' => '_diff_threshold',
				'expected'      => 0.6,
			),
		);
	}
}
