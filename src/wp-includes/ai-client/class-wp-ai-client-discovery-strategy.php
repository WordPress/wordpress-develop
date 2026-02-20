<?php
/**
 * WP AI Client: WP_AI_Client_Discovery_Strategy class
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.0.0
 */

use WordPress\AiClientDependencies\Http\Discovery\Psr18ClientDiscovery;
use WordPress\AiClientDependencies\Http\Discovery\Strategy\DiscoveryStrategy;
use WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface;

/**
 * Discovery strategy for WordPress HTTP client.
 *
 * Registers the WordPress HTTP client adapter with the HTTPlug discovery system
 * so the AI Client SDK can find and use it automatically.
 *
 * @since 7.0.0
 * @internal Intended only to register WordPress's HTTP client so that the PHP AI Client SDK can use it.
 * @access private
 */
class WP_AI_Client_Discovery_Strategy implements DiscoveryStrategy {

	/**
	 * Initializes and registers the discovery strategy.
	 *
	 * @since 7.0.0
	 */
	public static function init() {
		if ( ! class_exists( '\WordPress\AiClientDependencies\Http\Discovery\Psr18ClientDiscovery' ) ) {
			return;
		}

		Psr18ClientDiscovery::prependStrategy( self::class );
	}

	/**
	 * Gets candidates for discovery.
	 *
	 * @since 7.0.0
	 *
	 * @param string $type The type of discovery.
	 * @return array<array<string, mixed>> List of candidates.
	 */
	public static function getCandidates( $type ) {
		if ( ClientInterface::class === $type ) {
			return array(
				array(
					'class' => static function () {
						return self::create_wordpress_client();
					},
				),
			);
		}

		$psr17_factories = array(
			'WordPress\AiClientDependencies\Psr\Http\Message\RequestFactoryInterface',
			'WordPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface',
			'WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestFactoryInterface',
			'WordPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface',
			'WordPress\AiClientDependencies\Psr\Http\Message\UploadedFileFactoryInterface',
			'WordPress\AiClientDependencies\Psr\Http\Message\UriFactoryInterface',
		);

		if ( in_array( $type, $psr17_factories, true ) ) {
			return array(
				array(
					'class' => WP_AI_Client_PSR17_Factory::class,
				),
			);
		}

		return array();
	}

	/**
	 * Creates an instance of the WordPress HTTP client.
	 *
	 * @since 7.0.0
	 *
	 * @return WP_AI_Client_HTTP_Client
	 */
	private static function create_wordpress_client() {
		$psr17_factory = new WP_AI_Client_PSR17_Factory();
		return new WP_AI_Client_HTTP_Client(
			$psr17_factory,
			$psr17_factory
		);
	}
}
