<?php
/**
 * AI Security Client - Wrapper for WordPress AI Client.
 *
 * @package WordPress
 * @subpackage AI_Security
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AI_Security;

use WordPress\AiClient\AiClient;

/**
 * AI Security Client class.
 *
 * @since 7.1.0
 */
class Client {

	/**
	 * Instance of this class.
	 *
	 * @since 7.1.0
	 * @var Client|null
	 */
	private static ?Client $instance = null;

	/**
	 * Get instance.
	 *
	 * @since 7.1.0
	 * @return Client
	 */
	public static function get_instance(): Client {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get AI client status.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	public function get_status(): array {
		$ai_support = function_exists( 'wp_supports_ai' ) ? wp_supports_ai() : false;
		$registry   = AiClient::defaultRegistry();

		return array(
			'connected' => $ai_support && null !== $registry,
			'ai_support' => $ai_support,
			'providers_count' => $registry ? count( $registry->getRegisteredProviderIds() ) : 0,
		);
	}

	/**
	 * Analyze code for vulnerabilities using AI.
	 *
	 * @since 7.1.0
	 * @param string $code Code to analyze.
	 * @return array|null
	 */
	public function analyze_code( string $code ): ?array {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return null;
		}

		$prompt = wp_ai_client_prompt(
			'You are a security expert. Analyze this PHP code for vulnerabilities. ' .
			'Look for: SQL injection, XSS, CSRF, RCE, path traversal, unsafe deserialization, ' .
			'input validation issues, and security best practices violations. ' .
			'Return JSON with: vulnerabilities (array of objects with type, line, severity, description), ' .
			'recommendations (array of strings). If no issues found, return empty arrays.'
		);

		try {
			$result = $prompt
				->with_text( $code )
				->as_json_response(
					array(
						'vulnerabilities'   => 'array',
						'recommendations'   => 'array',
					)
				)
				->generate_text();

			if ( is_wp_error( $result ) ) {
				return null;
			}

			$data = json_decode( $result->get_text_content(), true );
			return $data ?? null;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Analyze a request for threats.
	 *
	 * @since 7.1.0
	 * @param array $request Request data.
	 * @return array|null
	 */
	public function analyze_request( array $request ): ?array {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return null;
		}

		$request_summary = json_encode( $request );

		$prompt = wp_ai_client_prompt(
			'You are a web security expert. Analyze this HTTP request for potential threats. ' .
			'Look for: SQL injection attempts, XSS payloads, command injection, ' .
			'path traversal, brute force patterns, DDoS patterns, and other malicious activity. ' .
			'Return JSON with: is_threat (bool), threat_type (string or null), ' .
			'confidence (0-100), details (string).'
		);

		try {
			$result = $prompt
				->with_text( $request_summary )
				->as_json_response(
					array(
						'is_threat'    => 'boolean',
						'threat_type'  => 'string|null',
						'confidence'   => 'integer',
						'details'      => 'string',
					)
				)
				->generate_text();

			if ( is_wp_error( $result ) ) {
				return null;
			}

			$data = json_decode( $result->get_text_content(), true );
			return $data ?? null;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Get security recommendations.
	 *
	 * @since 7.1.0
	 * @param string $context Context (plugin, theme, overall).
	 * @return array|null
	 */
	public function get_recommendations( string $context = 'overall' ): ?array {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return null;
		}

		$prompt = wp_ai_client_prompt(
			'You are a WordPress security expert. Provide security hardening recommendations ' .
			'for a WordPress ' . $context . '. Focus on: configuration, code practices, ' .
			'monitoring, and prevention. Return JSON with recommendations as an array of strings.'
		);

		try {
			$result = $prompt
				->as_json_response(
					array(
						'recommendations' => 'array',
					)
				)
				->generate_text();

			if ( is_wp_error( $result ) ) {
				return null;
			}

			$data = json_decode( $result->get_text_content(), true );
			return $data ?? null;
		} catch ( \Exception $e ) {
			return null;
		}
	}
}