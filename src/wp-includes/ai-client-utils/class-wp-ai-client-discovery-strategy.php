<?php
/**
 * WP AI Client: WP_AI_Client_Discovery_Strategy class
 *
 * @package WordPress
 * @subpackage AI
 * @since 6.8.0
 */

use WordPress\AiClientDependencies\Http\Discovery\Psr18ClientDiscovery;
use WordPress\AiClientDependencies\Http\Discovery\Strategy\DiscoveryStrategy;
use Psr\Http\Client\ClientInterface;

/**
 * Discovery strategy for WordPress HTTP client.
 *
 * Registers the WordPress HTTP client adapter with the HTTPlug discovery system
 * so the AI Client SDK can find and use it automatically.
 *
 * @since 6.8.0
 */
class WP_AI_Client_Discovery_Strategy implements DiscoveryStrategy {

	/**
	 * Initializes and registers the discovery strategy.
	 *
	 * @since 6.8.0
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
	 * @since 6.8.0
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
			'Psr\Http\Message\RequestFactoryInterface',
			'Psr\Http\Message\ResponseFactoryInterface',
			'Psr\Http\Message\ServerRequestFactoryInterface',
			'Psr\Http\Message\StreamFactoryInterface',
			'Psr\Http\Message\UploadedFileFactoryInterface',
			'Psr\Http\Message\UriFactoryInterface',
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
	 * @since 6.8.0
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
