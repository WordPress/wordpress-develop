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
		require_once ABSPATH . 'wp-includes/Text/Exception.php';
		require_once ABSPATH . 'wp-includes/wp-diff.php';
	}

	public function set_up() {
		parent::set_up();
		$this->diff_renderer_table = new WP_Text_Diff_Renderer_Table();
	}

	/**
	 * Long lines should remain complete without expensive word-level highlighting.
	 *
	 * @ticket 66095
	 * @dataProvider data_long_changed_lines
	 * @covers WP_Text_Diff_Renderer_Table::_changed
	 *
	 * @param string $orig Original line.
	 * @param string $revised Revised line.
	 */
	public function test_long_changed_lines_use_line_level_diff( $orig, $revised ) {
		foreach ( array( true, false ) as $show_split_view ) {
			$renderer = new WP_Text_Diff_Renderer_Table( array( 'show_split_view' => $show_split_view ) );
			$actual   = $renderer->_changed( array( $orig ), array( $revised ) );

			$this->assertStringContainsString( $renderer->deletedLine( htmlspecialchars( $orig ) ), $actual );
			$this->assertStringContainsString( $renderer->addedLine( htmlspecialchars( $revised ) ), $actual );
			$this->assertStringNotContainsString( '<ins>', $actual );
			$this->assertStringNotContainsString( '<del>', $actual );
		}
	}

	/**
	 * @return array[]
	 */
	public function data_long_changed_lines() {
		return array(
			'long original line' => array( str_repeat( 'a', 9995 ) . ' old!!', str_repeat( 'a', 9995 ) . ' new!' ),
			'long revised line'  => array( str_repeat( 'a', 9995 ) . ' old!', str_repeat( 'a', 9995 ) . ' new!!' ),
			'long HTML lines'    => array( str_repeat( '<p>a & b</p>', 22000 ), str_repeat( '<p>b & a</p>', 21000 ) ),
			'multibyte lines'    => array( str_repeat( '日本語', 1200 ) . ' old', str_repeat( '日本語', 1200 ) . ' new' ),
		);
	}

	/**
	 * @ticket 66095
	 * @covers WP_Text_Diff_Renderer_Table::_changed
	 */
	public function test_changed_lines_at_length_limit_keep_word_highlighting() {
		$prefix = str_repeat( 'a', 9996 ) . ' ';
		$actual = $this->diff_renderer_table->_changed( array( $prefix . 'old' ), array( $prefix . 'new' ) );

		$this->assertStringContainsString( '<del>old</del>', $actual );
		$this->assertStringContainsString( '<ins>new</ins>', $actual );
	}

	/**
	 * @ticket 66095
	 * @covers WP_Text_Diff_Renderer_Table::_changed
	 */
	public function test_long_lines_do_not_disable_highlighting_for_other_lines() {
		$long_line = str_repeat( 'a', 10001 );
		$actual    = wp_text_diff(
			$long_line . " old\nUnchanged context\nA short old line",
			$long_line . " new\nUnchanged context\nA short new line"
		);

		$this->assertStringContainsString( $long_line . ' old', $actual );
		$this->assertStringContainsString( $long_line . ' new', $actual );
		$this->assertStringContainsString( 'Unchanged context', $actual );
		$this->assertStringContainsString( '<del>old</del>', $actual );
		$this->assertStringContainsString( '<ins>new</ins>', $actual );
	}

	/**
	 * @ticket 66095
	 * @covers WP_Text_Diff_Renderer_Table::_changed
	 */
	public function test_revisions_with_long_lines_can_be_compared_and_restored() {
		require_once ABSPATH . 'wp-admin/includes/revision.php';

		$original    = '<div>' . str_repeat( 'a b ', 60000 ) . '</div>';
		$revised     = '<div>' . str_repeat( 'b a ', 57000 ) . '</div>';
		$post_id     = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => $original,
			)
		);
		$revision_id = _wp_put_post_revision( $post_id );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $revised,
			)
		);

		$diffs        = wp_get_revision_ui_diff( $post_id, $revision_id, $post_id );
		$content_diff = wp_list_filter( $diffs, array( 'id' => 'post_content' ) );
		$content_diff = reset( $content_diff );

		$this->assertStringContainsString( htmlspecialchars( $original ), $content_diff['diff'] );
		$this->assertStringContainsString( htmlspecialchars( $revised ), $content_diff['diff'] );
		$this->assertSame( $post_id, wp_restore_post_revision( $revision_id ) );
		$this->assertSame( $original, get_post_field( 'post_content', $post_id ) );
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
