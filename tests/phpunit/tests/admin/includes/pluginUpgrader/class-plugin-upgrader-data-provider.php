<?php

class Plugin_Upgrader_Data_Provider {
	public  $plugin_name = 'hello-dolly/hello.php';
	public  $packages;
	private $package_versions;
	public  $error_data_stats;
	private $update_plugins;
	private $success_results;

	public function init() {
		$this->packages         = array(
			'old'          => DIR_TESTDATA . '/upgrader/hello-dolly-1.6.zip',
			'new'          => DIR_TESTDATA . '/upgrader/hello-dolly-1.7.2.zip',
			'doesnotexist' => DIR_TESTDATA . '/upgrader/hello-dolly-99999.zip',
		);
		$this->package_versions = array(
			'old'          => '1.6',
			'new'          => '1.7.2',
			'doesnotexist' => '99999',
		);

		$this->error_data_stats = array(
			array(
				'process'          => 'download_package',
				'update_type'      => 'automatic_plugin_update',
				'name'             => null,
				'update_version'   => null,
				'success'          => false,
				'fs_method'        => 'direct',
				'fs_method_forced' => true,
				'fs_method_direct' => '',
				'error_code'       => 'no_package',
				'error_message'    => 'Installation package not available.',
				'error_data'       => null,
			),
			array(
				'process'          => 'plugin_install',
				'update_type'      => 'automatic_plugin_update',
				'name'             => null,
				'update_version'   => null,
				'success'          => false,
				'fs_method'        => 'direct',
				'fs_method_forced' => true,
				'fs_method_direct' => '',
				'error_code'       => 'no_package',
				'error_message'    => 'Installation package not available.',
				'error_data'       => null,
			),
		);
		$this->update_plugins   = (object) array(
			'last_checked' => time(),
			'checked'      => array(
				$this->plugin_name => '1.6',
			),
			'response'     => array(
				$this->plugin_name => (object) array(
					'id'          => 'w.org/plugins/hello-dolly',
					'slug'        => 'hello-dolly',
					'plugin'      => $this->plugin_name,
					'new_version' => '', // added in the $this->get_update_plugins() method.
					'url'         => 'https://wordpress.org/themes/upgrader-test-theme/',
					'package'     => '', // added in the $this->get_update_plugins() method.
				),
			),
		);
		$this->success_results  = array(
			'source'             => '', // added in the $this->get_upgrade_results method.
			'source_files'       => array(
				'hello.php',
			),
			'destination'        => WP_PLUGIN_DIR . '/hello-dolly/',
			'destination_name'   => 'hello-dolly',
			'local_destination'  => WP_PLUGIN_DIR,
			'remote_destination' => WP_PLUGIN_DIR . '/hello-dolly/',
			'clear_destination'  => true,
		);
	}

	public function get_update_plugins( $upgrade_package ) {
		$this->update_plugins->response[ $this->plugin_name ]->new_version = $this->package_versions[ $upgrade_package ];
		$this->update_plugins->response[ $this->plugin_name ]->package     = $this->packages[ $upgrade_package ];

		return $this->update_plugins;
	}

	public function get_messages( $type, $package = '' ) {
		switch ( $type ) {
			case 'success_install':
				return array(
					'<p>Unpacking the package&#8230;</p>' . "\n" .
					'<p>Installing the plugin&#8230;</p>' . "\n" .
					'<p>Plugin installed successfully.</p>',
				);
			case 'success_upgrade':
				return array(
					'<p>Unpacking the update&#8230;</p>' . "\n" .
					'<p>Installing the latest version&#8230;</p>' . "\n" .
					'<p>Removing the old version of the plugin&#8230;</p>' . "\n" .
					'<p>Plugin updated successfully.</p>',
				);
			case 'not_available':
				return array(
					'<p>Installation package not available.</p>',
				);
			case 'failed_update':
				return array(
					'<p>Unpacking the update&#8230;</p>' . "\n" .
					"<p>The package could not be installed. PCLZIP_ERR_MISSING_FILE (-4) : Missing archive file &#039;{$this->packages[ $package ]}&#039;</p>",
				);
			case 'failed_install':
				return array(
					'<p>Unpacking the package&#8230;</p>' . "\n" .
					"<p>The package could not be installed. PCLZIP_ERR_MISSING_FILE (-4) : Missing archive file &#039;{$this->packages[ $package ]}&#039;</p>",
				);
		}
	}

	public function get_upgrade_results( $upgrade_version ) {
		$this->success_results['source'] = WP_CONTENT_DIR . "/upgrade/hello-dolly-{$this->package_versions[ $upgrade_version ]}/hello-dolly/";

		return $this->success_results;
	}
}
