<?php
/**
 * Tests for the update_recently_edited() function.
 *
 * @group admin
 * @group admin-includes
 * @covers ::update_recently_edited
 */
class Tests_update_recently_edited extends WP_UnitTestCase {

	/**
	 * Tests that update_recently_edited() adds a new file to the list.
	 *
	 * @ticket 65174
	 */
	public function test_update_recently_edited_adds_new_file() {
		$file = 'test-file.php';
		update_recently_edited( $file );

		$this->assertSame( array( $file ), array_filter( array_values( get_option( 'recently_edited' ) ) ) );
	}

	/**
	 * Tests that update_recently_edited() maintains a maximum of 5 files.
	 *
	 * @ticket 65174
	 */
	public function test_update_recently_edited_maintains_max_5_files() {
		$files = array(
			'file1.php',
			'file2.php',
			'file3.php',
			'file4.php',
			'file5.php',
		);

		foreach ( $files as $file ) {
			update_recently_edited( $file );
		}

		$this->assertSame( array_reverse( $files ), array_filter( array_values( get_option( 'recently_edited' ) ) ) );

		$new_file = 'file6.php';
		update_recently_edited( $new_file );

		$expected = array(
			'file6.php',
			'file5.php',
			'file4.php',
			'file3.php',
			'file2.php',
		);

		$this->assertSame( $expected, array_filter( array_values( get_option( 'recently_edited' ) ) ) );
	}

	/**
	 * Tests that update_recently_edited() moves an existing file to the top.
	 *
	 * @ticket 65174
	 */
	public function test_update_recently_edited_moves_existing_file_to_top() {
		$files = array(
			'file1.php',
			'file2.php',
			'file3.php',
		);

		foreach ( $files as $file ) {
			update_recently_edited( $file );
		}

		// Current state: array( 'file3.php', 'file2.php', 'file1.php' )
		update_recently_edited( 'file1.php' );

		$expected = array(
			'file1.php',
			'file3.php',
			'file2.php',
		);

		$this->assertSame( $expected, array_filter( array_values( get_option( 'recently_edited' ) ) ) );
	}

	/**
	 * Tests update_recently_edited() with various inputs using a data provider.
	 *
	 * @ticket 65174
	 * @dataProvider data_update_recently_edited
	 *
	 * @param array  $initial_option The initial 'recently_edited' option.
	 * @param string $new_file       The new file to add.
	 * @param array  $expected       The expected 'recently_edited' option.
	 */
	public function test_update_recently_edited_cases( $initial_option, $new_file, $expected ) {
		if ( false !== $initial_option ) {
			update_option( 'recently_edited', $initial_option );
		} else {
			delete_option( 'recently_edited' );
		}

		update_recently_edited( $new_file );

		$this->assertSame( $expected, array_filter( array_values( get_option( 'recently_edited' ) ) ) );
	}

	/**
	 * Data provider for test_update_recently_edited_cases.
	 *
	 * @return array<string, array{
	 *     initial_option: array<int, string>|false,
	 *     new_file:       string,
	 *     expected:       array<int, string>,
	 * }>
	 */
	public function data_update_recently_edited(): array {
		return array(
			'empty_option'           => array(
				'initial_option' => false,
				'new_file'       => 'new.php',
				'expected'       => array( 'new.php' ),
			),
			'one_item'               => array(
				'initial_option' => array( 'old.php' ),
				'new_file'       => 'new.php',
				'expected'       => array( 'new.php', 'old.php' ),
			),
			'duplicate_item'         => array(
				'initial_option' => array( 'old.php', 'new.php' ),
				'new_file'       => 'new.php',
				'expected'       => array( 'new.php', 'old.php' ),
			),
			'max_items_at_limit'     => array(
				'initial_option' => array( '1.php', '2.php', '3.php', '4.php' ),
				'new_file'       => '5.php',
				'expected'       => array( '5.php', '1.php', '2.php', '3.php', '4.php' ),
			),
			'max_items_exceed_limit' => array(
				'initial_option' => array( '1.php', '2.php', '3.php', '4.php', '5.php' ),
				'new_file'       => '6.php',
				'expected'       => array( '6.php', '1.php', '2.php', '3.php', '4.php' ),
			),
		);
	}
}
