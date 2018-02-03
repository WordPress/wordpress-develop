<?php
/**
 * Tests for error handling and the WP_Error class.
 *
 * @group general
 * @group errors
 *
 * @coversDefaultClass WP_Error
 */
class Tests_WP_Error extends WP_UnitTestCase {

	/**
	 * WP_Error fixture.
	 *
	 * @var WP_Error
	 */
	public $WP_Error;

	/**
	 * Set up.
	 */
	public function setUp() {
		parent::setUp();

		$this->WP_Error = new WP_Error();
	}

	public function test_WP_Error_should_be_of_type_WP_Error() {
		$this->assertWPError( $this->WP_Error );
	}

	public function test_WP_Error_with_default_empty_parameters_should_add_no_errors() {
		$this->assertEmpty( $this->WP_Error->errors );
	}

	public function test_WP_Error_with_empty_code_should_add_no_code() {
		$this->assertSame( '', $this->WP_Error->get_error_code() );
	}

	public function test_WP_Error_with_empty_code_should_add_no_message() {
		$this->assertSame( '', $this->WP_Error->get_error_message() );
	}

	public function test_WP_Error_with_empty_code_should_add_no_error_data() {
		$this->assertEmpty( $this->WP_Error->error_data );
	}

	public function test_WP_Error_with_code_and_empty_message_should_add_error_with_that_code() {
		$wp_error = new WP_Error( 'code' );

		$this->assertSame( 'code', $wp_error->get_error_code() );
	}

	public function test_WP_Error_with_code_and_empty_message_should_add_error_with_that_code_and_empty_message() {
		$wp_error = new WP_Error( 'code' );

		$this->assertSame( '', $wp_error->get_error_message( 'code' ) );
	}

	public function test_WP_Error_with_code_and_empty_message_and_empty_data_should_add_error_but_not_associated_data() {
		$wp_error = new WP_Error( 'code' );

		$this->assertSame( null, $wp_error->get_error_data( 'code' ) );
	}

	public function test_WP_Error_with_code_and_empty_message_and_non_empty_data_should_add_error_with_empty_message_and_that_stored_data() {
		$wp_error = new WP_Error( 'code', '', 'data' );

		$this->assertSame( 'data', $wp_error->get_error_data( 'code' ) );
	}

	public function test_WP_Error_with_code_and_message_should_add_error_with_that_code() {
		$wp_error = new WP_Error( 'code', 'message' );

		$this->assertSame( 'code', $wp_error->get_error_code() );
	}

	public function test_WP_Error_with_code_and_message_should_add_error_with_that_message() {
		$wp_error = new WP_Error( 'code', 'message' );

		$this->assertSame( 'message', $wp_error->get_error_message( 'code' ) );
	}

	public function test_WP_Error_with_code_and_message_and_data_should_add_error_with_that_code() {
		$wp_error = new WP_Error( 'code', 'message', 'data' );

		$this->assertSame( 'code', $wp_error->get_error_code() );
	}

	public function test_WP_Error_with_code_and_message_and_data_should_add_error_with_that_message() {
		$wp_error = new WP_Error( 'code', 'message', 'data' );

		$this->assertSame( 'message', $wp_error->get_error_message( 'code' ) );
	}

	public function test_WP_Error_with_code_and_message_and_data_should_add_error_with_that_data() {
		$wp_error = new WP_Error( 'code', 'message', 'data' );

		$this->assertSame( 'data', $wp_error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::get_error_codes()
	 */
	public function test_get_error_codes_with_no_errors_should_return_empty_array() {
		$this->assertEmpty( $this->WP_Error->get_error_codes() );
	}

	/**
	 * @covers ::get_error_codes()
	 */
	public function test_get_error_codes_with_one_error_should_return_an_array_with_only_that_code() {
		$this->WP_Error->add( 'code', 'message' );

		$this->assertEqualSets( array( 'code' ), $this->WP_Error->get_error_codes() );
	}

	/**
	 * @covers ::get_error_codes()
	 */
	public function test_get_error_codes_with_multiple_errors_should_return_an_array_of_those_codes() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code2', 'message2' );

		$expected = array( 'code', 'code2' );

		$this->assertEqualSets( $expected, $this->WP_Error->get_error_codes() );
	}

	/**
	 * @covers ::get_error_code()
	 */
	public function test_get_error_code_with_no_errors_should_return_an_empty_string() {
		$this->assertSame( '', $this->WP_Error->get_error_code() );
	}

	/**
	 * @covers ::get_error_code()
	 */
	public function test_get_error_code_with_one_error_should_return_that_error_code() {
		$this->WP_Error->add( 'code', 'message' );

		$this->assertSame( 'code', $this->WP_Error->get_error_code() );
	}

	/**
	 * @covers ::get_error_code()
	 */
	public function test_get_error_code_with_multiple_errors_should_return_only_the_first_error_code() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code2', 'message2' );

		$this->assertSame( 'code', $this->WP_Error->get_error_code() );
	}

	/**
	 * @covers ::get_error_messages()
	 */
	public function test_get_error_messages_with_empty_code_and_no_errors_should_return_an_empty_array() {
		$this->assertEmpty( $this->WP_Error->get_error_messages() );
	}

	/**
	 * @covers ::get_error_messages()
	 */
	public function test_get_error_messages_with_empty_code_one_error_should_return_an_array_with_that_message() {
		$this->WP_Error->add( 'code', 'message' );

		$this->assertEqualSets( array( 'message' ), $this->WP_Error->get_error_messages() );
	}

	/**
	 * @covers ::get_error_messages()
	 */
	public function test_get_error_messages_with_empty_code_multiple_errors_should_return_an_array_of_messages() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code2', 'message2' );

		$this->assertEqualSets( array( 'message', 'message2' ), $this->WP_Error->get_error_messages() );
	}

	/**
	 * @covers ::get_error_messages()
	 */
	public function test_get_error_messages_with_an_invalid_code_should_return_an_empty_array() {
		$this->assertEmpty( $this->WP_Error->get_error_messages( 'code' ) );
	}

	/**
	 * @covers ::get_error_messages()
	 */
	public function test_get_error_messages_with_one_error_should_return_an_array_with_that_message() {
		$this->WP_Error->add( 'code', 'message' );

		$this->assertEqualSets( array( 'message' ), $this->WP_Error->get_error_messages( 'code' ) );
	}

	/**
	 * @covers ::get_error_messages()
	 */
	public function test_get_error_messages_with_multiple_errors_same_code_should_return_an_array_with_all_messages() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code', 'message2' );

		$this->assertequalSets( array( 'message', 'message2' ), $this->WP_Error->get_error_messages( 'code' ) );
	}

	/**
	 * @covers ::get_error_message()
	 */
	public function test_get_error_message_with_empty_code_and_no_errors_should_return_an_empty_string() {
		$this->assertSame( '', $this->WP_Error->get_error_message() );
	}

	/**
	 * @covers ::get_error_message()
	 */
	public function test_get_error_message_with_empty_code_and_one_error_should_return_that_message() {
		$this->WP_Error->add( 'code', 'message' );

		$this->assertSame( 'message', $this->WP_Error->get_error_message() );
	}

	/**
	 * @covers ::get_error_message()
	 */
	public function test_get_error_message_with_empty_code_and_multiple_errors_should_return_the_first_message() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code2', 'message2' );

		$this->assertSame( 'message', $this->WP_Error->get_error_message() );
	}

	/**
	 * @covers ::get_error_message()
	 */
	public function test_get_error_message_with_empty_code_and_multiple_errors_multiple_codes_should_return_the_first_message() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code2', 'message2' );
		$this->WP_Error->add( 'code', 'message2' );

		$this->assertSame( 'message', $this->WP_Error->get_error_message() );
	}

	/**
	 * @covers ::get_error_message()
	 */
	public function test_get_error_message_with_invalid_code_and_no_errors_should_return_empty_string() {
		$this->assertSame( '', $this->WP_Error->get_error_message( 'invalid' ) );
	}

	/**
	 * @covers ::get_error_message()
	 */
	public function test_get_error_message_with_invalid_code_and_one_error_should_return_an_empty_string() {
		$this->WP_Error->add( 'code', 'message' );

		$this->assertSame( '', $this->WP_Error->get_error_message( 'invalid' ) );
	}

	/**
	 * @covers ::get_error_message()
	 */
	public function test_get_error_message_with_invalid_code_and_multiple_errors_should_return_an_empty_string() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code2', 'message2' );

		$this->assertSame( '', $this->WP_Error->get_error_message( 'invalid' ) );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_empty_code_and_no_errors_should_evaluate_as_null() {
		$this->assertSame( null, $this->WP_Error->get_error_data() );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_empty_code_one_error_no_data_should_evaluate_as_null() {
		$this->WP_Error->add( 'code', 'message' );

		$this->assertSame( null, $this->WP_Error->get_error_data() );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_empty_code_multiple_errors_no_data_should_evaluate_as_null() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code2', 'message2' );

		$this->assertSame( null, $this->WP_Error->get_error_data() );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_empty_code_and_one_error_with_data_should_return_that_data() {
		$expected = array( 'data-key' => 'data-value' );
		$this->WP_Error->add( 'code', 'message', $expected );

		$this->assertEqualSetsWithIndex( $expected, $this->WP_Error->get_error_data() );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_empty_code_and_multiple_errors_different_codes_should_return_the_last_data_of_the_first_code() {
		$expected = array( 'data-key' => 'data-value' );
		$this->WP_Error->add( 'code', 'message', $expected );
		$this->WP_Error->add( 'code2', 'message2', 'data2' );

		$this->assertEqualSetsWithIndex( $expected, $this->WP_Error->get_error_data() );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_empty_code_and_multiple_errors_same_code_should_return_the_last_data_of_the_first_code() {
		$this->WP_Error->add( 'code', 'message', 'data' );
		$this->WP_Error->add( 'code', 'message2', 'data2' );
		$this->WP_Error->add( 'code2', 'message2', 'data3' );

		$this->assertSame( 'data2', $this->WP_Error->get_error_data() );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_code_and_no_errors_should_evaluate_as_null() {
		$this->assertSame( null, $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_code_and_one_error_with_no_data_should_evaluate_as_null() {
		$this->WP_Error->add( 'code', 'message' );

		$this->assertSame( null, $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_code_and_one_error_with_data_should_return_that_data() {
		$expected = array( 'data-key' => 'data-value' );
		$this->WP_Error->add( 'code', 'message', $expected );

		$this->assertEqualSetsWithIndex( $expected, $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_code_and_multiple_errors_different_codes_should_return_the_last_stored_data_of_the_code() {
		$expected = array( 'data3' );
		$this->WP_Error->add( 'code', 'message', 'data' );
		$this->WP_Error->add( 'code2', 'message2', 'data2' );
		$this->WP_Error->add( 'code', 'message3', $expected );

		$this->assertEqualSetsWithIndex( $expected, $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::get_error_data()
	 */
	public function test_get_error_data_with_code_and_multiple_errors_same_code_should_return_the_last_stored_data() {
		$this->WP_Error->add( 'code', 'message', 'data' );
		$this->WP_Error->add( 'code', 'message2', 'data2' );
		$this->WP_Error->add( 'code2', 'message3', 'data3' );

		$this->assertSame( 'data2', $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_empty_code_empty_message_empty_data_should_add_empty_key_to_errors_array() {
		$this->WP_Error->add( '', '', 'data' );

		$this->assertArrayHasKey( '', $this->WP_Error->errors );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_empty_code_empty_message_empty_data_should_add_empty_message_to_errors_array_under_empty_key() {
		$this->WP_Error->add( '', '', 'data' );

		$this->assertEqualSetsWithIndex( array( '' => array( '' ) ), $this->WP_Error->errors );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_empty_code_empty_message_empty_data_should_not_alter_data() {
		$this->WP_Error->add( '', '', '' );

		$this->assertEmpty( $this->WP_Error->error_data );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_empty_code_empty_message_non_empty_data_should_store_data_under_an_empty_code_key() {
		$this->WP_Error->add( '', '', 'data' );

		$this->assertEqualSetsWithIndex( array( '' => 'data' ), $this->WP_Error->error_data );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_code_empty_message_empty_data_should_add_error_with_code() {
		$this->WP_Error->add( 'code', '' );

		$this->assertSame( 'code', $this->WP_Error->get_error_code() );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_code_empty_message_empty_data_should_add_error_with_empty_message() {
		$this->WP_Error->add( 'code', '' );

		$this->assertSame( '', $this->WP_Error->get_error_message( 'code' ) );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_code_empty_message_empty_data_should_not_add_error_data() {
		$this->WP_Error->add( 'code', '' );

		$this->assertSame( null, $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_code_and_message_and_empty_data_should_should_add_error_with_that_message() {
		$this->WP_Error->add( 'code', 'message' );

		$this->assertSame( 'message', $this->WP_Error->get_error_message( 'code' ) );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_code_and_message_and_empty_data_should_not_alter_stored_data() {
		$this->WP_Error->add( 'code', 'message' );

		$this->assertSame( null, $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_code_and_empty_message_and_data_should_add_error_with_that_code() {
		$this->WP_Error->add( 'code', '', 'data' );

		$this->assertSame( 'code', $this->WP_Error->get_error_code() );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_code_and_empty_message_and_data_should_store_that_data() {
		$this->WP_Error->add( 'code', '', 'data' );

		$this->assertSame( 'data', $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_code_and_message_and_data_should_add_an_error_with_that_code() {
		$this->WP_Error->add( 'code', 'message', 'data' );

		$this->assertSame( 'code', $this->WP_Error->get_error_code() );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_code_and_message_and_data_should_add_an_error_with_that_message() {
		$this->WP_Error->add( 'code', 'message', 'data' );

		$this->assertSame( 'message', $this->WP_Error->get_error_message( 'code' ) );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_with_code_and_message_and_data_should_store_that_data() {
		$this->WP_Error->add( 'code', 'message', 'data' );

		$this->assertSame( 'data', $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_multiple_times_with_the_same_code_should_add_additional_messages_for_that_code() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code', 'message2' );

		$expected = array( 'message', 'message2' );

		$this->assertEqualSets( $expected, $this->WP_Error->get_error_messages( 'code' ) );
	}

	/**
	 * @covers ::add()
	 */
	public function test_add_multiple_times_with_the_same_code_and_different_data_should_store_only_the_last_added_data() {
		$this->WP_Error->add( 'code', 'message', 'data-bar' );
		$this->WP_Error->add( 'code', 'message2', 'data-baz' );

		$this->assertSame( 'data-baz', $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::add_data()
	 */
	public function test_add_data_with_empty_data_empty_code_should_create_orphaned_data_with_no_error() {
		$this->WP_Error->add_data( '' );

		$this->assertEmpty( $this->WP_Error->errors );
	}

	/**
	 * @covers ::add_data()
	 */
	public function test_add_data_with_empty_data_empty_code_no_errors_should_create_data_under_an_empty_code_key() {
		$this->WP_Error->add_data( '' );

		$this->assertEqualSets( array( '' => '' ), $this->WP_Error->error_data );
	}

	/**
	 * @covers ::add_data()
	 */
	public function test_add_data_with_data_empty_code_and_one_error_should_store_the_data_under_that_code() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add_data( 'data' );

		$this->assertSame( 'data', $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::add_data()
	 */
	public function test_add_data_with_data_empty_code_and_multiple_errors_with_different_codes_should_store_it_under_the_first_code() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code2', 'message2' );

		$this->WP_Error->add_data( 'data' );

		$this->assertSame( 'data', $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::add_data()
	 */
	public function test_add_data_with_data_empty_code_and_multiple_errors_with_same_code_should_store_it_under_the_first_code() {
		$this->WP_Error->add( 'code', 'message' );
		$this->WP_Error->add( 'code2', 'message2' );
		$this->WP_Error->add( 'code', 'message3' );

		$this->WP_Error->add_data( 'data' );

		$this->assertSame( 'data', $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::add_data()
	 */
	public function test_add_data_with_data_and_code_and_no_errors_should_create_orphaned_data_with_no_error() {
		$this->WP_Error->add_data( 'data', 'code' );

		$this->assertEmpty( $this->WP_Error->errors );
	}

	/**
	 * @covers ::add_data()
	 */
	public function test_add_data_with_data_and_code_no_errors_should_create_data_under_that_code_key() {
		$this->WP_Error->add_data( 'data', 'code' );

		$this->assertEqualSets( array( 'code' => 'data' ), $this->WP_Error->error_data );
	}

	/**
	 * @covers ::add_data()
	 */
	public function test_add_data_with_data_and_code_one_error_different_code_should_create_orphaned_data_with_no_error() {
		$this->WP_Error->add( 'code', 'message' );

		$this->WP_Error->add_data( 'data', 'code2' );

		$this->assertEqualSetsWithIndex( array( 'code' => array( 'message' ) ), $this->WP_Error->errors );
	}

	/**
	 * @covers ::add_data()
	 */
	public function test_add_data_with_data_and_code_one_error_different_code_should_create_data_under_that_code_key() {
		$this->WP_Error->add( 'code', 'message' );

		$this->WP_Error->add_data( 'data', 'code2' );

		$this->assertEqualSetsWithIndex( array( 'code2' => 'data' ), $this->WP_Error->error_data );
	}

	/**
	 * @covers ::add_data()
	 */
	public function test_add_data_with_data_and_code_should_add_data() {
		$this->WP_Error->add( 'code', 'message' );

		$this->WP_Error->add_data( 'data', 'code' );

		$this->assertSame( 'data', $this->WP_Error->get_error_data( 'code' ) );
	}

	/**
	 * @covers ::remove()
	 */
	public function test_remove_with_no_errors_should_affect_nothing() {
		$before = $this->WP_Error->errors;

		$this->WP_Error->remove( 'code' );

		$after = $this->WP_Error->errors;

		$this->assertEqualSetsWithIndex( $before, $after );
	}

	/**
	 * @covers ::remove()
	 */
	public function test_remove_empty_code_no_errors_should_affect_nothing() {
		$before = $this->WP_Error->errors;

		$this->WP_Error->remove( '' );

		$after = $this->WP_Error->errors;

		$this->assertEqualSetsWithIndex( $before, $after );
	}

	/**
	 * @covers ::remove()
	 */
	public function test_remove_empty_code_and_one_error_with_empty_string_code_should_remove_error() {
		$before = $this->WP_Error->errors;

		$this->WP_Error->add( '', 'message' );

		$this->WP_Error->remove( '' );

		$after = $this->WP_Error->errors;

		$this->assertEqualSetsWithIndex( $before, $after );
	}

	/**
	 * @covers ::remove()
	 */
	public function test_remove_empty_code_and_one_error_with_empty_string_code_should_remove_error_data() {
		$this->WP_Error->add( '', 'message', 'data' );

		$this->WP_Error->remove( '' );

		$after = $this->WP_Error->error_data;

		$this->assertEmpty( $this->WP_Error->error_data );
	}

	/**
	 * @covers ::remove()
	 */
	public function test_remove_should_remove_the_error_with_the_given_code() {
		$this->WP_Error->add( 'code', 'message' );

		$this->WP_Error->remove( 'code' );

		$this->assertEmpty( $this->WP_Error->errors );
	}

	/**
	 * @covers ::remove()
	 */
	public function test_remove_should_remove_the_error_data_associated_with_the_given_code() {
		$this->WP_Error->add( 'code', 'message', 'data' );

		$this->WP_Error->remove( 'code' );

		$this->assertEmpty( $this->WP_Error->error_data );
	}

}
