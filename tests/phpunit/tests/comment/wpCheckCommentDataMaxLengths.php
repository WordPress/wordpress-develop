<?php

/**
 * Tests for wp_check_comment_data_max_lengths().
 *
 * @group comment
 *
 * @covers ::wp_check_comment_data_max_lengths
 */
class Tests_Comment_WpCheckCommentDataMaxLengths extends WP_UnitTestCase {

	/**
	 * Tests that data within the limits of every field is accepted.
	 *
	 * @ticket 66494
	 */
	public function test_should_accept_data_within_the_limits() {
		$this->assertTrue(
			wp_check_comment_data_max_lengths(
				array(
					'comment_author'       => 'Bob',
					'comment_author_email' => 'bob@example.org',
					'comment_author_url'   => 'https://example.org/',
					'comment_content'      => 'Yes, we can!',
				)
			)
		);
	}

	/**
	 * Tests that data without any of the fields is accepted.
	 *
	 * @ticket 66494
	 */
	public function test_should_accept_data_without_the_fields() {
		$this->assertTrue( wp_check_comment_data_max_lengths( array() ) );
		$this->assertTrue( wp_check_comment_data_max_lengths( array( 'comment_post_ID' => 1 ) ) );
	}

	/**
	 * Tests that a value that is as long as the limit is accepted.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $field Name of the field.
	 */
	public function test_should_accept_a_value_as_long_as_the_limit( $field ) {
		$max_lengths = wp_get_comment_fields_max_lengths();

		$this->assertTrue( wp_check_comment_data_max_lengths( array( $field => str_repeat( 'a', $max_lengths[ $field ] ) ) ) );
	}

	/**
	 * Tests that a value that is one byte longer than the limit is rejected, with the error of the field.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $field         Name of the field.
	 * @param string $error_code    Expected error code.
	 * @param string $error_message Expected error message.
	 */
	public function test_should_reject_a_value_longer_than_the_limit( $field, $error_code, $error_message ) {
		$max_lengths = wp_get_comment_fields_max_lengths();

		$result = wp_check_comment_data_max_lengths( array( $field => str_repeat( 'a', $max_lengths[ $field ] + 1 ) ) );

		$this->assertWPError( $result );
		$this->assertSame( $error_code, $result->get_error_code() );
		$this->assertSame( $error_message, $result->get_error_message() );
		$this->assertSame( 200, $result->get_error_data() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_fields() {
		return array(
			'author'  => array( 'comment_author', 'comment_author_column_length', '<strong>Error:</strong> Your name is too long.' ),
			'email'   => array( 'comment_author_email', 'comment_author_email_column_length', '<strong>Error:</strong> Your email address is too long.' ),
			'url'     => array( 'comment_author_url', 'comment_author_url_column_length', '<strong>Error:</strong> Your URL is too long.' ),
			'content' => array( 'comment_content', 'comment_content_column_length', '<strong>Error:</strong> Your comment is too long.' ),
		);
	}

	/**
	 * Tests that the length of the author and of the content is counted in bytes.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_multibyte_fields
	 *
	 * @param string $field Name of the field.
	 */
	public function test_should_count_the_author_and_content_in_bytes( $field ) {
		$max_lengths = wp_get_comment_fields_max_lengths();

		// Each character takes two bytes, so this has fewer characters than the limit, but more bytes.
		$value = str_repeat( 'é', (int) floor( $max_lengths[ $field ] / 2 ) + 1 );

		$this->assertLessThanOrEqual( $max_lengths[ $field ], mb_strlen( $value, 'UTF-8' ) );
		$this->assertGreaterThan( $max_lengths[ $field ], strlen( $value ) );

		$result = wp_check_comment_data_max_lengths( array( $field => $value ) );

		$this->assertWPError( $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_multibyte_fields() {
		return array(
			'author'  => array( 'comment_author' ),
			'content' => array( 'comment_content' ),
		);
	}

	/**
	 * Tests that the fields are checked in the order author, email, URL and content,
	 * and that the first field that is too long is the one that is reported.
	 *
	 * @ticket 66494
	 */
	public function test_should_report_the_first_field_that_is_too_long() {
		$max_lengths = wp_get_comment_fields_max_lengths();

		$error_codes = array(
			'comment_author'       => 'comment_author_column_length',
			'comment_author_email' => 'comment_author_email_column_length',
			'comment_author_url'   => 'comment_author_url_column_length',
			'comment_content'      => 'comment_content_column_length',
		);

		// Every field is too long. The data is in the reverse of the order in which the fields are checked.
		$comment_data = array();
		foreach ( array_reverse( array_keys( $error_codes ) ) as $field ) {
			$comment_data[ $field ] = str_repeat( 'a', $max_lengths[ $field ] + 1 );
		}

		// Take away each field after it was reported, to see which one comes next.
		foreach ( $error_codes as $field => $error_code ) {
			$result = wp_check_comment_data_max_lengths( $comment_data );

			$this->assertWPError( $result, "An error should be reported while the $field field is too long." );
			$this->assertSame( $error_code, $result->get_error_code(), "The $field field should be reported next." );

			unset( $comment_data[ $field ] );
		}

		$this->assertTrue( wp_check_comment_data_max_lengths( $comment_data ), 'There should be no error when no field is left.' );
	}

	/**
	 * Tests that the limits come from the `wp_get_comment_fields_max_lengths` filter.
	 *
	 * @ticket 66494
	 */
	public function test_should_use_the_limits_of_the_wp_get_comment_fields_max_lengths_filter() {
		add_filter(
			'wp_get_comment_fields_max_lengths',
			static function ( $lengths ) {
				$lengths['comment_author'] = 5;

				return $lengths;
			}
		);

		$this->assertTrue( wp_check_comment_data_max_lengths( array( 'comment_author' => 'Alice' ) ) );

		$result = wp_check_comment_data_max_lengths( array( 'comment_author' => 'Alice!' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_author_column_length', $result->get_error_code() );
	}
}
