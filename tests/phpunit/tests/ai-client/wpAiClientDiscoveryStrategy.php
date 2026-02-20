<?php
/**
 * Tests for WP_AI_Client_Discovery_Strategy.
 *
 * @group ai-client
 * @covers WP_AI_Client_Discovery_Strategy
 */
class Tests_AI_Client_Discovery_Strategy extends WP_UnitTestCase {

	/**
	 * Saved strategies to restore after each test.
	 *
	 * @var array
	 */
	private $saved_strategies;

	/**
	 * Saves discovery strategies before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->saved_strategies = WordPress\AiClientDependencies\Http\Discovery\ClassDiscovery::getStrategies();
	}

	/**
	 * Restores discovery strategies after each test.
	 */
	public function tear_down() {
		WordPress\AiClientDependencies\Http\Discovery\ClassDiscovery::setStrategies(
			is_array( $this->saved_strategies ) ? $this->saved_strategies : iterator_to_array( $this->saved_strategies )
		);
		parent::tear_down();
	}

	/**
	 * Test that the strategy implements DiscoveryStrategy interface.
	 *
	 * @ticket 64591
	 */
	public function test_implements_discovery_strategy() {
		$this->assertTrue(
			is_a(
				WP_AI_Client_Discovery_Strategy::class,
				WordPress\AiClientDependencies\Http\Discovery\Strategy\DiscoveryStrategy::class,
				true
			)
		);
	}

	/**
	 * Test init prepends strategy to discovery.
	 *
	 * @ticket 64591
	 */
	public function test_init_prepends_strategy() {
		// Clear strategies to isolate test.
		WordPress\AiClientDependencies\Http\Discovery\ClassDiscovery::setStrategies( array() );

		WP_AI_Client_Discovery_Strategy::init();

		$strategies = WordPress\AiClientDependencies\Http\Discovery\ClassDiscovery::getStrategies();
		$strategies = is_array( $strategies ) ? $strategies : iterator_to_array( $strategies );

		$this->assertNotEmpty( $strategies );
		$this->assertSame( WP_AI_Client_Discovery_Strategy::class, $strategies[0] );
	}

	/**
	 * Test getCandidates for ClientInterface returns a closure that creates WP_AI_Client_HTTP_Client.
	 *
	 * @ticket 64591
	 */
	public function test_get_candidates_client_interface() {
		$candidates = WP_AI_Client_Discovery_Strategy::getCandidates(
			WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface::class
		);

		$this->assertCount( 1, $candidates );
		$this->assertArrayHasKey( 'class', $candidates[0] );
		$this->assertIsCallable( $candidates[0]['class'] );

		$client = $candidates[0]['class']();
		$this->assertInstanceOf( WP_AI_Client_HTTP_Client::class, $client );
	}

	/**
	 * Test getCandidates for RequestFactoryInterface returns PSR17 Factory class.
	 *
	 * @ticket 64591
	 */
	public function test_get_candidates_request_factory() {
		$candidates = WP_AI_Client_Discovery_Strategy::getCandidates(
			'WordPress\AiClientDependencies\Psr\Http\Message\RequestFactoryInterface'
		);

		$this->assertCount( 1, $candidates );
		$this->assertSame( WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory::class, $candidates[0]['class'] );
	}

	/**
	 * Test getCandidates for ResponseFactoryInterface returns PSR17 Factory class.
	 *
	 * @ticket 64591
	 */
	public function test_get_candidates_response_factory() {
		$candidates = WP_AI_Client_Discovery_Strategy::getCandidates(
			'WordPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface'
		);

		$this->assertCount( 1, $candidates );
		$this->assertSame( WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory::class, $candidates[0]['class'] );
	}

	/**
	 * Test getCandidates for StreamFactoryInterface returns PSR17 Factory class.
	 *
	 * @ticket 64591
	 */
	public function test_get_candidates_stream_factory() {
		$candidates = WP_AI_Client_Discovery_Strategy::getCandidates(
			'WordPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface'
		);

		$this->assertCount( 1, $candidates );
		$this->assertSame( WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory::class, $candidates[0]['class'] );
	}

	/**
	 * Test getCandidates for UriFactoryInterface returns PSR17 Factory class.
	 *
	 * @ticket 64591
	 */
	public function test_get_candidates_uri_factory() {
		$candidates = WP_AI_Client_Discovery_Strategy::getCandidates(
			'WordPress\AiClientDependencies\Psr\Http\Message\UriFactoryInterface'
		);

		$this->assertCount( 1, $candidates );
		$this->assertSame( WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory::class, $candidates[0]['class'] );
	}

	/**
	 * Test getCandidates for unknown type returns empty array.
	 *
	 * @ticket 64591
	 */
	public function test_get_candidates_unknown_type() {
		$candidates = WP_AI_Client_Discovery_Strategy::getCandidates( 'UnknownType' );
		$this->assertSame( array(), $candidates );
	}
}
