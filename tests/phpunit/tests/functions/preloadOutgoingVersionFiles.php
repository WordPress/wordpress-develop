<?php

/**
 * @group upgrade
 *
 * @covers ::preload_outgoing_version_files
 */
class Tests_Functions_PreloadOutgoingVersionFiles extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . '/wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . '/wp-admin/includes/class-wp-filesystem-direct.php';
		require_once ABSPATH . '/wp-admin/includes/update-core.php';
	}

	/**
	 * @ticket
	 * @dataProvider data_preload_outgoing_version_files
	 *
	 * @global $wp_filesystem  The filesystem.
	 * @global $wp_version     The WordPress version.
	 *
	 * @param array $args {
	 *     @type string $wp_base             Base path of the WordPress installation.
	 *     @type array  $preload_paths       An array of paths to preload, relative to $wp_base.
	 *     @type array  $skip_preload_paths  An array of paths to skip when preloading, relative to $wp_base.
	 *     @type mixed  $wp_filesystem       The filesystem.
	 *     @type string $wp_version          The WordPress version.
	 * }
	 */
	public function test_preload_outgoing_version_files( array $args ) {
		global $wp_filesystem, $wp_version, ${ $args['to_preload'] };

		if ( isset( $args['wp_filesystem'] ) ) {
			$wp_filesystem = $args['wp_filesystem'];

			if ( str_starts_with( $wp_filesystem, 'WP_Filesystem' ) ) {
				$wp_filesystem = new $args['wp_filesystem']( '' );
			}
		}

		if ( isset( $args['wp_version'] ) ) {
			$wp_version = $args['wp_version'];
		}

		preload_outgoing_version_files( $args['wp_base'], $args['preload_paths'] );
		$this->assertIsObject( ${ $args['to_preload'] }, "{$args['to_preload']} was not preloaded." );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_preload_outgoing_version_files() {
		return array(
			'a file path'      => array(
				'args' => array(
					'wp_base'       => DIR_TESTDATA,
					'wp_filesystem' => 'WP_Filesystem_Direct',
					'preload_paths' => array( 'classes/sample_1/class-sample-one.php' ),
					'to_preload'    => 'sample_one',
				),
			),
			'a directory path' => array(
				'args' => array(
					'wp_base'       => DIR_TESTDATA,
					'wp_filesystem' => 'WP_Filesystem_Direct',
					'preload_paths' => array( 'classes/sample_2/' ),
					'to_preload'    => 'sample_two',
				),
			),
		);
	}

	/**
	 * @ticket
	 * @dataProvider data_preload_outgoing_version_files_skips
	 *
	 * @global $wp_filesystem  The filesystem.
	 * @global $wp_version     The WordPress version.
	 *
	 * @param array $args {
	 *     @type string $wp_base             Base path of the WordPress installation.
	 *     @type array  $preload_paths       An array of paths to preload, relative to $wp_base.
	 *     @type array  $skip_preload_paths  An array of paths to skip when preloading, relative to $wp_base.
	 *     @type mixed  $wp_filesystem       The filesystem.
	 *     @type string $wp_version          The WordPress version.
	 * }
	 */
	public function test_preload_outgoing_version_files_skips( array $args ) {
		global $wp_filesystem, $wp_version, ${ $args['to_preload'] };

		if ( isset( $args['wp_filesystem'] ) ) {
			$wp_filesystem = $args['wp_filesystem'];

			if ( str_starts_with( $wp_filesystem, 'WP_Filesystem' ) ) {
				$wp_filesystem = new $args['wp_filesystem']( '' );
			}
		}

		if ( isset( $args['wp_version'] ) ) {
			$wp_version = $args['wp_version'];
		}

		preload_outgoing_version_files( $args['wp_base'], $args['preload_paths'], $args['skip_preload_paths'] );
		$this->assertIsNotObject( ${ $args['to_preload'] }, "{$args['to_preload']} was preloaded." );
	}


	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_preload_outgoing_version_files_skips() {
		return array(
			'no version check and a file path'      => array(
				'args' => array(
					'wp_base'            => DIR_TESTDATA,
					'wp_filesystem'      => 'WP_Filesystem_Direct',
					'preload_paths'      => array( 'classes/sample_3/class-sample-three.php' ),
					'skip_preload_paths' => array( array( 'classes/sample_3/class-sample-three.php' ) ),
					'to_preload'         => 'sample_three',
				),
			),
			'no version check and a directory path' => array(
				'args' => array(
					'wp_base'            => DIR_TESTDATA,
					'wp_filesystem'      => 'WP_Filesystem_Direct',
					'preload_paths'      => array( 'classes/sample_4/' ),
					'skip_preload_paths' => array( array( 'classes/sample_4/class-sample-four.php' ) ),
					'to_preload'         => 'sample_four',
				),
			),
			'a version check and a file path'       => array(
				'args' => array(
					'wp_base'            => DIR_TESTDATA,
					'wp_filesystem'      => 'WP_Filesystem_Direct',
					'preload_paths'      => array( 'classes/sample_5/class-sample-five.php' ),
					'skip_preload_paths' => array( array( 'classes/sample_5/class-sample-five.php', '5.8.2', '>' ) ),
					'to_preload'         => 'sample_five',
				),
			),
			'a version check and a directory path'  => array(
				'args' => array(
					'wp_base'            => DIR_TESTDATA,
					'wp_filesystem'      => 'WP_Filesystem_Direct',
					'preload_paths'      => array( 'classes/sample_6/' ),
					'skip_preload_paths' => array( array( 'classes/sample_6/class-sample-six.php', '5.8.2', '>' ) ),
					'to_preload'         => 'sample_six',
				),
			),
		);
	}

	/**
	 * @ticket
	 * @dataProvider data_preload_outgoing_version_files_returns_wp_error
	 *
	 * @global $wp_filesystem  The filesystem.
	 * @global $wp_version     The WordPress version.
	 *
	 * @param array $args {
	 *     @type string $wp_base             Base path of the WordPress installation.
	 *     @type array  $preload_paths       An array of paths to preload, relative to $wp_base.
	 *     @type array  $skip_preload_paths  An array of paths to skip when preloading, relative to $wp_base.
	 *     @type mixed  $wp_filesystem       The filesystem.
	 *     @type string $wp_version          The WordPress version.
	 *     @type string $expected            The expected error code.
	 * }
	 */
	public function test_preload_outgoing_version_files_returns_wp_error( array $args ) {
		global $wp_filesystem, $wp_version;

		if ( isset( $args['wp_filesystem'] ) ) {
			$wp_filesystem = $args['wp_filesystem'];

			if ( str_starts_with( $wp_filesystem, 'WP_Filesystem' ) ) {
				$wp_filesystem = new $args['wp_filesystem']( '' );
			}
		}

		if ( isset( $args['wp_version'] ) ) {
			$wp_version = $args['wp_version'];
		}

		$actual = preload_outgoing_version_files( $args['wp_base'], $args['preload_paths'] );

		$this->assertInstanceOf(
			'WP_Error',
			$actual,
			'Did not return a WP_Error object.'
		);

		$this->assertSame(
			$args['expected'],
			$actual->get_error_codes()[0],
			"Did not return error: '{$args['expected']}'"
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_preload_outgoing_version_files_returns_wp_error() {
		return array(
			'no filesystem -> fs_unavailable' => array(
				'args' => array(
					'wp_base'       => DIR_TESTDATA,
					'wp_filesystem' => '',
					'preload_paths' => array(),
					'expected'      => 'fs_unavailable',
				),
			),
			'no preload paths -> empty_paths' => array(
				'args' => array(
					'wp_base'       => DIR_TESTDATA,
					'wp_filesystem' => 'WP_Filesystem_Direct',
					'preload_paths' => array(),
					'expected'      => 'empty_paths',
				),
			),
			'file path that does not exist -> path_does_not_exist' => array(
				'args' => array(
					'wp_base'       => DIR_TESTDATA,
					'wp_filesystem' => 'WP_Filesystem_Direct',
					'preload_paths' => array( 'classes/sample_0/class-sample-zero.php' ),
					'expected'      => 'path_does_not_exist',
				),
			),
			'directory path that does not exist -> path_does_not_exist' => array(
				'args' => array(
					'wp_base'       => DIR_TESTDATA,
					'wp_filesystem' => 'WP_Filesystem_Direct',
					'preload_paths' => array( 'classes/sample_0/' ),
					'expected'      => 'path_does_not_exist',
				),
			),
			'a non-PHP file -> bad_file'      => array(
				'args' => array(
					'wp_base'       => ABSPATH,
					'wp_filesystem' => 'WP_Filesystem_Direct',
					'preload_paths' => array( 'readme.html' ),
					'expected'      => 'bad_file',
				),
			),
		);
	}
}
