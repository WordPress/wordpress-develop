<?php

class Theme_Upgrader_Data_Provider {
	public  $theme_name = 'upgrader-test-theme';
	public  $packages;
	private $package_versions;
	public  $error_data_stats;
	private $update_themes;
	private $success_results;

	public function init() {
		$this->packages         = array(
			'old'          => DIR_TESTDATA . '/upgrader/upgrader-test-theme-1.0.zip',
			'new'          => DIR_TESTDATA . '/upgrader/upgrader-test-theme-1.1.zip',
			'doesnotexist' => DIR_TESTDATA . '/upgrader/upgrader-test-theme-99999.zip',
		);
		$this->package_versions = array(
			'old'          => '1.0',
			'new'          => '1.1',
			'doesnotexist' => '99999',
		);

		$this->error_data_stats = array(
			array(
				'process'          => 'download_package',
				'update_type'      => 'automatic_theme_update',
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
				'process'          => 'theme_install',
				'update_type'      => 'automatic_theme_update',
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
		$this->update_themes    = (object) array(
			'last_checked' => time(),
			'checked'      => array(
				'upgrader-test-theme' => '1.0',
			),
			'response'     => array(
				'upgrader-test-theme' => array(
					'theme'        => 'upgrader-test-theme',
					'new_version'  => '', // added in the $this->get_update_themes() method.
					'url'          => 'https://wordpress.org/themes/upgrader-test-theme/',
					'package'      => '', // added in the $this->get_update_themes() method.
					'requires'     => '5.3',
					'requires_php' => '5.6',
				),
			),
		);
		$this->success_results = array(
			'source'             => '', // added in the $this->get_upgrade_results method.
			'source_files'       => array(
				'functions.php',
				'index.php',
				'style.css',
			),
			'destination'        => WP_CONTENT_DIR . '/themes/upgrader-test-theme/',
			'destination_name'   => 'upgrader-test-theme',
			'local_destination'  => WP_CONTENT_DIR . '/themes',
			'remote_destination' => WP_CONTENT_DIR . '/themes/upgrader-test-theme/',
			'clear_destination'  => true,
		);
	}

	public function get_update_themes( $upgrade_package ) {
		$this->update_themes->response['upgrader-test-theme']['new_version'] = $this->package_versions[ $upgrade_package ];
		$this->update_themes->response['upgrader-test-theme']['package'] = $this->packages[ $upgrade_package ];
		return $this->update_themes;
	}

	public function get_messages( $type, $package = '' ) {
		switch ( $type ) {
			case 'success_install':
				return array(
					'<p>Unpacking the package&#8230;</p>' . "\n" .
					'<p>Installing the theme&#8230;</p>' . "\n" .
					'<p>Theme installed successfully.</p>',
				);
			case 'success_upgrade':
				return array(
					'<p>Unpacking the update&#8230;</p>' . "\n" .
					'<p>Installing the latest version&#8230;</p>' . "\n" .
					'<p>Removing the old version of the theme&#8230;</p>' . "\n" .
					'<p>Theme updated successfully.</p>',
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
		$this->success_results['source'] = WP_CONTENT_DIR . "/upgrade/upgrader-test-theme-{$this->package_versions[ $upgrade_version ]}/upgrader-test-theme/";
		return $this->success_results;
	}
}
