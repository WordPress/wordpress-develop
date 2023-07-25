<?php
require_once ABSPATH . 'wp-includes/Text/Diff/Renderer.php';
require_once ABSPATH . 'wp-includes/class-wp-text-diff-renderer-table.php';

/**
 * @group diff
 */
class Tests_Diff_WpTextDiffRendererTable extends WP_UnitTestCase {
	/**
	 * @var WP_Text_Diff_Renderer_Table
	 */
	private $diff_renderer_table;

	public static function set_up_before_class() {
		parent::set_up_before_class();
	}

	public function set_up() {
		parent::set_up();
		$this->diff_renderer_table = new WP_Text_Diff_Renderer_Table();
	}

	/**
	 * @dataProvider data_should_allow_predefined_dynamic_properties
	 * @ticket       58898
	 *
	 * @covers       WP_Text_Diff_Renderer_Table::__set
	 * @covers       WP_Text_Diff_Renderer_Table::__get
	 *
	 * @param string $property_name Name of the class property.
	 */
	public function test_should_allow_predefined_dynamic_properties( $property_name ) {
		$value = uniqid();

		// Calling the getter first to make sure it doesn't cause errors.
		$this->diff_renderer_table->$property_name;

		$this->diff_renderer_table->$property_name = $value;
		$this->assertSame( $value, $this->diff_renderer_table->$property_name );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_allow_predefined_dynamic_properties() {
		// This code doesn't have access to self::$renderer_table, so the WP_Text_Diff_Renderer_Table object has to be instantiated this way.
		$renderer_table         = new WP_Text_Diff_Renderer_Table();
		$compat_fields_property = new ReflectionProperty( $renderer_table, 'compat_fields' );
		$compat_fields_property->setAccessible( true );

		$predefined_properties = $compat_fields_property->getValue( $renderer_table );

		$compat_fields_property->setAccessible( false );
		$predefined_properties = array_map(
			function ( $property_name ) {
				return array( $property_name );
			},
			$predefined_properties
		);

		return $predefined_properties;
	}

	/**
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__get
	 */
	public function test_should_not_allow_to_get_dynamic_properties() {
		$this->enable_doing_it_wrong_error();
		$property_name = uniqid();
		$this->setExpectedIncorrectUsage( 'WP_Text_Diff_Renderer_Table::__get' );
		$this->expectNotice();
		$this->expectNoticeMessageMatches( '/^.+' . $property_name . '.+$/' );

		// Invoking WP_Text_Diff_Renderer_Table::__get.
		$this->diff_renderer_table->$property_name;
	}

	/**
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__set
	 */
	public function test_should_not_allow_to_set_dynamic_properties() {
		$this->enable_doing_it_wrong_error();
		$property_name = uniqid();
		$this->setExpectedIncorrectUsage( 'WP_Text_Diff_Renderer_Table::__set' );
		$this->expectNotice();
		$this->expectNoticeMessageMatches( '/^.+' . $property_name . '.+$/' );

		// Invoking WP_Text_Diff_Renderer_Table::__set.
		$this->diff_renderer_table->$property_name = 'value';
	}

	/**
	 * This function is needed to remove the filter and disable triggering
	 * the "doing it wrong" error.
	 */
	private function enable_doing_it_wrong_error() {
		add_filter( 'doing_it_wrong_trigger_error', '__return_true', 9999 );
	}
}
