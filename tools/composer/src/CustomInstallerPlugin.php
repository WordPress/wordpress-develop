<?php
/**
 * Custom Composer installer plugin for WordPress.
 */

namespace WordPress\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Custom installer plugin class.
 */
final class CustomInstallerPlugin implements PluginInterface {
	/**
	 * Apply plugin.
	 *
	 * @param Composer    $composer The Composer instance.
	 * @param IOInterface $io       The IO interface.
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$installer = new CustomInstaller( $io, $composer );
		$composer->getInstallationManager()->addInstaller( $installer );
	}

	/**
	 * Remove any hooks from Composer.
	 *
	 * @param Composer    $composer The Composer instance.
	 * @param IOInterface $io       The IO interface.
	 */
	public function deactivate( Composer $composer, IOInterface $io ) {
		// Nothing to do here.
	}

	/**
	 * Prepare the plugin to be uninstalled.
	 *
	 * @param Composer    $composer The Composer instance.
	 * @param IOInterface $io       The IO interface.
	 */
	public function uninstall( Composer $composer, IOInterface $io ) {
		// Nothing to do here.
	}
}
