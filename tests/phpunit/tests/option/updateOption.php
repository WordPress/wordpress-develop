<?php

/**
 * @group option
 */
class Tests_Option_UpdateOption extends WP_UnitTestCase {
	/**
	 * @ticket 31047
	 *
	 * @covers ::add_filter
	 * @covers ::update_option
	 * @covers ::remove_filter
	 */
	public function test_should_respect_default_option_filter_when_option_does_not_yet_exist_in_database() {
		add_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );
		$added = update_option( 'doesnotexist', 'bar' );
		remove_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );

		$this->assertTrue( $added );
		$this->assertSame( 'bar', get_option( 'doesnotexist' ) );
	}

	/**
	 * @ticket 26394
	 *
	 * @covers ::update_option
	 * @covers ::wp_load_alloptions
	 * @covers ::get_option
	 */
	public function test_should_set_autoload_yes_for_nonexistent_option_when_autoload_param_is_missing() {
		$this->flush_cache();
		update_option( 'test_update_option_default', 'value' );
		$this->flush_cache();

		// Populate the alloptions cache, which includes autoload=yes options.
		wp_load_alloptions();

		$before = get_num_queries();
		$value  = get_option( 'test_update_option_default' );
		$after  = get_num_queries();

		$this->assertSame( $before, $after );
		$this->assertSame( $value, 'value' );
	}

	/**
	 * @ticket 26394
	 *
	 * @covers ::update_option
	 * @covers ::wp_load_alloptions
	 * @covers ::get_option
	 */
	public function test_should_set_autoload_yes_for_nonexistent_option_when_autoload_param_is_yes() {
		$this->flush_cache();
		update_option( 'test_update_option_default', 'value', 'yes' );
		$this->flush_cache();

		// Populate the alloptions cache, which includes autoload=yes options.
		wp_load_alloptions();

		$before = get_num_queries();
		$value  = get_option( 'test_update_option_default' );
		$after  = get_num_queries();

		$this->assertSame( $before, $after );
		$this->assertSame( $value, 'value' );
	}

	/**
	 * @ticket 26394
	 *
	 * @covers ::update_option
	 * @covers ::wp_load_alloptions
	 * @covers ::get_option
	 */
	public function test_should_set_autoload_no_for_nonexistent_option_when_autoload_param_is_no() {
		$this->flush_cache();
		update_option( 'test_update_option_default', 'value', 'no' );
		$this->flush_cache();

		// Populate the alloptions cache, which does not include autoload=no options.
		wp_load_alloptions();

		$before = get_num_queries();
		$value  = get_option( 'test_update_option_default' );
		$after  = get_num_queries();

		// Database has been hit.
		$this->assertSame( $before + 1, $after );
		$this->assertSame( $value, 'value' );
	}

	/**
	 * @ticket 26394
	 *
	 * @covers ::update_option
	 * @covers ::wp_load_alloptions
	 * @covers ::get_option
	 */
	public function test_should_set_autoload_no_for_nonexistent_option_when_autoload_param_is_false() {
		$this->flush_cache();
		update_option( 'test_update_option_default', 'value', false );
		$this->flush_cache();

		// Populate the alloptions cache, which does not include autoload=no options.
		wp_load_alloptions();

		$before = get_num_queries();
		$value  = get_option( 'test_update_option_default' );
		$after  = get_num_queries();

		// Database has been hit.
		$this->assertSame( $before + 1, $after );
		$this->assertSame( $value, 'value' );
	}

	/**
	 * @ticket 26394
	 *
	 * @covers ::update_option
	 * @covers ::wp_load_alloptions
	 * @covers ::get_option
	 */
	public function test_autoload_should_be_updated_for_existing_option_when_value_is_changed() {
		add_option( 'foo', 'bar', '', 'no' );
		$updated = update_option( 'foo', 'bar2', true );
		$this->assertTrue( $updated );

		$this->flush_cache();

		// Populate the alloptions cache, which includes autoload=yes options.
		wp_load_alloptions();

		$before = get_num_queries();
		$value  = get_option( 'foo' );

		$this->assertSame( $before, get_num_queries() );
		$this->assertSame( $value, 'bar2' );
	}

	/**
	 * @ticket 26394
	 *
	 * @covers ::update_option
	 * @covers ::wp_load_alloptions
	 * @covers ::get_option
	 */
	public function test_autoload_should_not_be_updated_for_existing_option_when_value_is_unchanged() {
		add_option( 'foo', 'bar', '', 'yes' );
		$updated = update_option( 'foo', 'bar', false );
		$this->assertFalse( $updated );

		$this->flush_cache();

		// Populate the alloptions cache, which includes autoload=yes options.
		wp_load_alloptions();

		$before = get_num_queries();
		$value  = get_option( 'foo' );

		// 'foo' should still be autoload=yes, so we should see no additional querios.
		$this->assertSame( $before, get_num_queries() );
		$this->assertSame( $value, 'bar' );
	}

	/**
	 * @ticket 26394
	 *
	 * @covers ::update_option
	 * @covers ::wp_load_alloptions
	 * @covers ::get_option
	 */
	public function test_autoload_should_not_be_updated_for_existing_option_when_value_is_changed_but_no_value_of_autoload_is_provided() {
		add_option( 'foo', 'bar', '', 'yes' );

		// Don't pass a value for `$autoload`.
		$updated = update_option( 'foo', 'bar2' );
		$this->assertTrue( $updated );

		$this->flush_cache();

		// Populate the alloptions cache, which includes autoload=yes options.
		wp_load_alloptions();

		$before = get_num_queries();
		$value  = get_option( 'foo' );

		// 'foo' should still be autoload=yes, so we should see no additional queries.
		$this->assertSame( $before, get_num_queries() );
		$this->assertSame( $value, 'bar2' );
	}

	/**
	 * @ticket 38903
	 *
	 * @covers ::add_option
	 * @covers ::get_num_queries
	 * @covers ::update_option
	 */
	public function test_update_option_array_with_object() {
		$array_w_object = array(
			'url'       => 'http://src.wordpress-develop.dev/wp-content/uploads/2016/10/cropped-Blurry-Lights.jpg',
			'meta_data' => (object) array(
				'attachment_id' => 292,
				'height'        => 708,
				'width'         => 1260,
			),
		);

		// Add the option, it did not exist before this.
		add_option( 'array_w_object', $array_w_object );

		$num_queries_pre_update = get_num_queries();

		// Update the option using the same array with an object for the value.
		$this->assertFalse( update_option( 'array_w_object', $array_w_object ) );

		// Check that no new database queries were performed.
		$this->assertSame( $num_queries_pre_update, get_num_queries() );
	}

	/**
	 * @ticket 21989
	 *
	 * @covers ::add_option
	 * @covers ::add_filter
	 * @covers ::update_option
	 * @covers ::remove_filter
	 * @covers ::get_option
	 */
	public function test_stored_sanitized_value_from_update_of_nonexistent_option_should_be_same_as_that_from_add_option() {
		$before    = 'x';
		$sanitized = $this->_append_y( $before );

		// Add the comparison option, it did not exist before this.
		add_filter( 'sanitize_option_doesnotexist_filtered_add', array( $this, '_append_y' ) );
		add_option( 'doesnotexist_filtered_add', $before );
		remove_filter( 'sanitize_option_doesnotexist_filtered_add', array( $this, '_append_y' ) );

		// Add the option, it did not exist before this.
		add_filter( 'sanitize_option_doesnotexist_filtered_update', array( $this, '_append_y' ) );
		$added = update_option( 'doesnotexist_filtered_update', $before );
		remove_filter( 'sanitize_option_doesnotexist_filtered_update', array( $this, '_append_y' ) );

		$after = get_option( 'doesnotexist_filtered_update' );

		// Check all values match.
		$this->assertTrue( $added );
		$this->assertSame( get_option( 'doesnotexist_filtered_add' ), $after );
		$this->assertSame( $sanitized, $after );
	}

	/**
	 * `add_filter()` callback for test_stored_sanitized_value_from_update_of_nonexistent_option_should_be_same_as_that_from_add_option().
	 */
	public function _append_y( $value ) {
		return $value . '_y';
	}

	/**
	 * `add_filter()` callback for test_should_respect_default_option_filter_when_option_does_not_yet_exist_in_database().
	 */
	public function __return_foo() {
		return 'foo';
	}
}
