<?php

/**
 * Tests for the got_mod_rewrite() function.
 *
 * @group admin
 * @group rewrite
 *
 * @covers ::got_mod_rewrite
 */
class Tests_got_mod_rewrite extends WP_UnitTestCase {

	/**
	 * Original value of the `$is_apache` global.
	 */
	private ?bool $original_is_apache;

	/**
	 * Backs up the `$is_apache` global.
	 *
	 * @global bool|null $is_apache
	 */
	public function set_up() {
		global $is_apache;
		parent::set_up();
		$this->original_is_apache = $is_apache;
	}

	/**
	 * Restores the `$is_apache` global.
	 *
	 * @global bool|null $is_apache
	 */
	public function tear_down() {
		$GLOBALS['is_apache'] = $this->original_is_apache;
		parent::tear_down();
	}

	/**
	 * Tests that got_mod_rewrite() depends on whether the server is Apache, and that the filter overrides it.
	 *
	 * @ticket 65134
	 *
	 * @dataProvider data_got_mod_rewrite
	 *
	 * @param bool $is_apache    Whether the server is Apache.
	 * @param bool $filter_value Value to return via the 'got_rewrite' filter.
	 */
	public function test_got_mod_rewrite( bool $is_apache, bool $filter_value ): void {
		$GLOBALS['is_apache'] = $is_apache;

		/*
		 * On Apache, apache_mod_loaded() falls back to the default of true when the module list
		 * cannot be determined (as in the CLI), and it always returns false for other servers.
		 * So this covers the server check and the default value, not actual module detection,
		 * which would require apache_get_modules() to be available.
		 */
		$this->assertSame( $is_apache, got_mod_rewrite(), 'Expected the unfiltered result to be true on Apache (via the default value) and false otherwise.' );

		$filtered_value = null;
		add_filter(
			'got_rewrite',
			static function ( $got_rewrite ) use ( $filter_value, &$filtered_value ) {
				$filtered_value = $got_rewrite;
				return $filter_value;
			}
		);

		$this->assertSame( $filter_value, got_mod_rewrite(), 'Expected the filter to override the detected value.' );
		$this->assertSame( $is_apache, $filtered_value, 'Expected the filter to receive the detected value.' );
	}

	/**
	 * Data provider for {@see self::test_got_mod_rewrite()}.
	 *
	 * @return array<string, array{
	 *     is_apache:    bool,
	 *     filter_value: bool,
	 * }>
	 */
	public function data_got_mod_rewrite(): array {
		return array(
			'Apache, filter returns true'      => array(
				'is_apache'    => true,
				'filter_value' => true,
			),
			'Apache, filter returns false'     => array(
				'is_apache'    => true,
				'filter_value' => false,
			),
			'Non-Apache, filter returns true'  => array(
				'is_apache'    => false,
				'filter_value' => true,
			),
			'Non-Apache, filter returns false' => array(
				'is_apache'    => false,
				'filter_value' => false,
			),
		);
	}
}
