<?php

/**
 * Class used for updating/installing plugins during tests.
 *
 * @see WP_Upgrader
 */
class Dummy_Plugin_Upgrader extends Plugin_Upgrader {
	/**
	 * Pretend to run an upgrade/installation.
	 *
	 * @param array $options {
	 *     Array or string of arguments for upgrading/installing a package.
	 *
	 *     @type string $package                     The full path or URI of the package to install.
	 *                                               Default empty.
	 *     @type string $destination                 The full path to the destination folder.
	 *                                               Default empty.
	 *     @type bool   $clear_destination           Whether to delete any files already in the
	 *                                               destination folder. Default false.
	 *     @type bool   $clear_working               Whether to delete the files from the working
	 *                                               directory after copying them to the destination.
	 *                                               Default true.
	 *     @type bool   $abort_if_destination_exists Whether to abort the installation if the destination
	 *                                               folder already exists. When true, `$clear_destination`
	 *                                               should be false. Default true.
	 *     @type bool   $is_multi                    Whether this run is one of multiple upgrade/installation
	 *                                               actions being performed in bulk. When true, the skin
	 *                                               WP_Upgrader::header() and WP_Upgrader::footer()
	 *                                               aren't called. Default false.
	 *     @type array  $hook_extra                  Extra arguments to pass to the filter hooks called by
	 *                                               WP_Upgrader::run().
	 * }
	 * @return array|false|WP_Error The result from self::install_package() on success, otherwise a WP_Error,
	 *                              or false if unable to connect to the filesystem.
	 *
	 * @phpstan-param array{package?: string, destination?: string, clear_destination?: bool, clear_working?: bool, abort_if_destination_exists?: bool, is_multi?: bool, hook_extra?: array<mixed>} $options
	 * @phpstan-return array{source: string, source_files: string[], destination: string, destination_name: string, local_destination: string, remote_destination: string, clear_destination: mixed}|false|WP_Error
	 */
	public function run( $options ) {
		$defaults = array(
			'package'                           => '',
			// Please always pass this.
							'destination'       => '',
			// ...and this.
							'clear_destination' => false,
			'clear_working'                     => true,
			'abort_if_destination_exists'       => true,
			// Abort if the destination directory exists. Pass clear_destination as false please.
							'is_multi'          => false,
			'hook_extra'                        => array(),
		// Pass any extra $hook_extra args here, this will be passed to any hooked filters.
		);

		$options = wp_parse_args( $options, $defaults );

		$result = array(
			'source'             => '/tmp/source',
			'source_files'       => array(
				'foo.txt',
				'bar.txt',
			),
			'destination'        => '/tmp/destination',
			'destination_name'   => 'destination',
			'local_destination'  => '/tmp/destination',
			'remote_destination' => '/tmp/destination',
			'clear_destination'  => $options['clear_destination'],
		);

		$this->result = $result;

		return $result;
	}
}
