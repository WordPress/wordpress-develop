<?php
/**
 * Installer for handling custom installation paths.
 */

namespace WordPress\Composer;

use Composer\PartialComposer;
use Composer\IO\IOInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Installer\BinaryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use React\Promise\PromiseInterface;

/**
 * Installer class.
 */
final class CustomInstaller extends LibraryInstaller {
	/**
	 * Custom installer paths configuration.
	 */
	private array $installerPaths = array();

	/**
	 * Initializes library installer.
	 */
	public function __construct(
		IOInterface $io,
		PartialComposer $composer,
		?string $type = 'library',
		?Filesystem $filesystem = null,
		?BinaryInstaller $binaryInstaller = null
	) {
		parent::__construct( $io, $composer, $type, $filesystem, $binaryInstaller );

		$this->installerPaths = $this->composer->getPackage()->getExtra()['installer-paths'];
	}

	/**
	 * Check if this installer supports the given package type.
	 *
	 * @param string $packageType The package type.
	 * @return bool
	 */
	public function supports( $packageType ) {
		return true;
	}

	/**
	 * Get the installation path for a package.
	 *
	 * @param PackageInterface $package The package.
	 * @return string The installation path.
	 */
	public function getInstallPath( PackageInterface $package ) {
		$packageName = $package->getName();

		if ( ! isset( $this->installerPaths[ $packageName ] ) ) {
			return parent::getInstallPath( $package );
		}

		return realpath( getcwd() ) . '/' . $this->installerPaths[ $packageName ]['target'];
	}

	/**
	 * Install a package.
	 *
	 * @param InstalledRepositoryInterface $repo    The installed repository.
	 * @param PackageInterface             $package The package to install.
	 * @return PromiseInterface|null
	 */
	public function install( InstalledRepositoryInterface $repo, PackageInterface $package ) {
		$installer = parent::install( $repo, $package );

		if ( $installer instanceof PromiseInterface ) {
			return $installer->then( fn() => $this->modifyPaths( $package ) );
		}

		$this->modifyPaths( $package );
		return null;
	}

	/**
	 * Update a package.
	 *
	 * @param InstalledRepositoryInterface $repo    The installed repository.
	 * @param PackageInterface             $initial The initial package.
	 * @param PackageInterface             $target  The target package.
	 * @return PromiseInterface|null
	 */
	public function update( InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target ) {
		$updater = parent::update( $repo, $initial, $target );

		if ( $updater instanceof PromiseInterface ) {
			return $updater->then( fn() => $this->modifyPaths( $target ) );
		}

		$this->modifyPaths( $target );
		return null;
	}

	/**
	 * Modify installation paths based on source subdirectory and ignore patterns.
	 *
	 * @param PackageInterface $package The package.
	 */
	private function modifyPaths( PackageInterface $package ): void {
		$installPath = $this->getInstallPath( $package );

		$this->applySourceSubdirectory( $package, $installPath );
		$this->applyReplacements( $package, $installPath );
		$this->applyIgnorePatterns( $package, $installPath );
	}

	/**
	 * Apply file replacements from custom replacement files.
	 *
	 * @param PackageInterface $package     The package.
	 * @param string           $installPath The installation path.
	 */
	private function applyReplacements( PackageInterface $package, string $installPath ): void {
		$packageName = $package->getName();

		if ( ! isset( $this->installerPaths[ $packageName ]['replace'] ) ) {
			return;
		}

		$filesystem      = new Filesystem();
		$replacementsDir = realpath( getcwd() ) . '/tools/composer/replacements';

		foreach ( $this->installerPaths[ $packageName ]['replace'] as $target => $source ) {
			$sourcePath = $replacementsDir . '/' . $source;

			if ( ! file_exists( $sourcePath ) ) {
				throw new \RuntimeException( "Replacement file 'tools/composer/replacements/{$source}' does not exist for package '{$packageName}'." );
			}

			$filesystem->copy( $sourcePath, $installPath . '/' . $target );
		}
	}

	/**
	 * Apply ignore patterns to remove unwanted files after installation.
	 *
	 * @param PackageInterface $package The package.
	 * @param string           $installPath The installation path.
	 */
	private function applyIgnorePatterns( PackageInterface $package, string $installPath ): void {
		$packageName = $package->getName();

		if ( ! isset( $this->installerPaths[ $packageName ]['ignore'] ) ) {
			return;
		}

		$filesystem = new Filesystem();

		foreach ( $this->installerPaths[ $packageName ]['ignore'] as $pattern ) {
			$matches = glob( $installPath . '/' . $pattern );

			if ( empty( $matches ) ) {
				throw new \RuntimeException( "Failed to glob pattern '{$pattern}' in package '{$package->getName()}'." );
			}

			foreach ( $matches as $path ) {
				$filesystem->remove( $path );
			}
		}
	}

	/**
	 * Apply source subdirectory to flatten directory structure.
	 *
	 * @param PackageInterface $package     The package.
	 * @param string           $installPath The installation path.
	 */
	private function applySourceSubdirectory( PackageInterface $package, string $installPath ): void {
		$packageName = $package->getName();

		if ( ! isset( $this->installerPaths[ $packageName ]['source'] ) ) {
			return;
		}

		$sourceSubdir = $this->installerPaths[ $packageName ]['source'];
		$sourceDir    = $installPath . '/' . $sourceSubdir;

		if ( ! is_dir( $sourceDir ) ) {
			throw new \RuntimeException( "Source directory '{$sourceSubdir}' does not exist in package '{$packageName}'." );
		}

		$filesystem = new Filesystem();
		$tempDir    = $installPath . '_temp';

		$filesystem->rename( $sourceDir, $tempDir );
		$filesystem->removeDirectory( $installPath );
		$filesystem->rename( $tempDir, $installPath );
	}
}
