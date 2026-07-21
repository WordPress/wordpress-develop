<?php
/**
 * PHP-Scoper configuration for bundling php-ai-client dependencies.
 *
 * Scopes all third-party namespaces (Http\*, Psr\*, etc.) to
 * WordPress\AiClientDependencies\* to avoid conflicts with plugin-bundled versions.
 *
 * @package WordPress
 */

use Isolated\Symfony\Component\Finder\Finder;

return array(
	'prefix' => 'WordPress\\AiClientDependencies',

	'finders' => array(
		// Include all PHP files in vendor (dependencies) so their namespaces get scoped.
		Finder::create()
			->files()
			->ignoreVCS( true )
			->notName( '/LICENSE|.*\\.md|.*\\.dist|Makefile/' )
			->exclude( array( 'composer', 'doc', 'test', 'test_old', 'tests', 'Tests', 'vendor-bin' ) )
			->in( 'vendor' ),

		// Include the AI client source files so `use` statements referencing
		// scoped dependency namespaces get updated. The AI client's own namespace
		// is excluded below, so its `namespace` declarations stay unchanged.
		Finder::create()
			->files()
			->ignoreVCS( true )
			->name( '*.php' )
			->in( 'src' ),
	),

	'exclude-namespaces' => array(
		// The AI client's own namespace must not be scoped.
		'WordPress\\AiClient',
	),

	'exclude-files' => array(),

	'exclude-constants' => array(
		// Preserve WordPress-compatible constants.
		'/^ABSPATH$/',
		'/^WPINC$/',
	),

	'exclude-functions' => array(),

	'patchers' => array(
		/**
		 * Fix php-http/discovery hardcoded class name strings.
		 *
		 * Discovery probes for external HTTP implementations using hardcoded FQCN strings.
		 * These must NOT be prefixed because they reference packages outside our bundle
		 * (e.g., GuzzleHttp\Client, Nyholm\Psr7\Factory\Psr17Factory).
		 */
		static function ( string $file_path, string $prefix, string $contents ): string {
			// Only patch php-http/discovery files.
			if ( false === strpos( $file_path, 'php-http/discovery' ) ) {
				return $contents;
			}

			// External package namespaces that Discovery probes for.
			// These must remain un-prefixed in hardcoded string references.
			$external_namespaces = array(
				'GuzzleHttp',
				'Http\\Adapter',
				'Http\\Client\\Curl',
				'Http\\Client\\Socket',
				'Http\\Client\\Buzz',
				'Http\\Client\\React',
				'Buzz',
				'Nyholm',
				'Laminas',
				'Symfony\\Component\\HttpClient',
				'Phalcon\\Http',
				'Slim\\Psr7',
				'Kriswallsmith',
			);

			foreach ( $external_namespaces as $ns ) {
				$escaped_ns     = preg_quote( $ns, '/' );
				$escaped_prefix = preg_quote( $prefix, '/' );

				// Remove prefix from string literals containing these namespaces.
				// Matches: 'WordPress\AiClientDependencies\GuzzleHttp\...' or "WordPress\AiClientDependencies\GuzzleHttp\..."
				$contents = preg_replace(
					'/([\'"])' . $escaped_prefix . '\\\\\\\\' . $escaped_ns . '/',
					'$1' . $ns,
					$contents
				);

				// Also handle double-backslash variants in string concatenation.
				$contents = preg_replace(
					'/([\'"])' . $escaped_prefix . '\\\\' . $escaped_ns . '/',
					'$1' . $ns,
					$contents
				);
			}

			return $contents;
		},
	),
);
