<?php
/**
 * Tests for wp_localize_jquery_ui_datepicker().
 *
 * @group dependencies
 * @group scripts
 * @covers ::wp_localize_jquery_ui_datepicker
 */
class Tests_Dependencies_WpLocalizeJqueryUiDatepicker extends WP_UnitTestCase {
	/**
	 * Original scripts registry.
	 *
	 * @var WP_Scripts|null
	 */
	private $old_wp_scripts;

	public function set_up() {
		parent::set_up();

		$this->old_wp_scripts  = $GLOBALS['wp_scripts'] ?? null;
		$GLOBALS['wp_scripts'] = null;
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;

		parent::tear_down();
	}

	/**
	 * Tests that ordinal suffixes are omitted from the datepicker format.
	 *
	 * @ticket 39991
	 * @dataProvider data_date_formats
	 *
	 * @param string $date_format       PHP date format.
	 * @param string $datepicker_format Expected jQuery UI date format.
	 */
	public function test_date_format( $date_format, $datepicker_format ) {
		update_option( 'date_format', $date_format );
		wp_enqueue_script( 'jquery-ui-datepicker' );

		wp_localize_jquery_ui_datepicker();

		$inline_scripts = wp_scripts()->get_data( 'jquery-ui-datepicker', 'after' );

		$this->assertIsArray( $inline_scripts );
		$this->assertStringContainsString(
			'"dateFormat":' . wp_json_encode( $datepicker_format ),
			implode( '', $inline_scripts )
		);
	}

	/**
	 * Provides date formats with ordinal tokens and ordinary format controls.
	 *
	 * @return array[]
	 */
	public static function data_date_formats() {
		return array(
			'ordinal day'                 => array( 'jS F Y', 'd MM yy' ),
			'ordinal zero-padded day'     => array( 'dS M Y', 'dd M yy' ),
			'ordinal after weekday'       => array( 'l, jS F Y', 'DD, d MM yy' ),
			'escaped literal suffix'      => array( 'j\S F Y', 'd\S MM yy' ),
			'ordinal after escaped slash' => array( 'j\\\\S F Y', 'd\\\\ MM yy' ),
			'literal after escaped slash' => array( 'j\\\\\S F Y', 'd\\\\\S MM yy' ),
			'ordinary long date'          => array( 'F j, Y', 'MM d, yy' ),
			'ordinary numeric date'       => array( 'Y-m-d', 'yy-mm-dd' ),
		);
	}

	/**
	 * Tests that an unqueued datepicker is not localized.
	 *
	 * @ticket 39991
	 */
	public function test_does_not_localize_unqueued_datepicker() {
		wp_scripts();

		wp_localize_jquery_ui_datepicker();

		$this->assertFalse( wp_scripts()->get_data( 'jquery-ui-datepicker', 'after' ) );
	}
}
