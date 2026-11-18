<?php

/**
 * @group oembed
 *
 * @covers ::wp_oembed_add_vimeo_start_time
 */
class Tests_oEmbed_wpOembedAddVimeoStartTime extends WP_UnitTestCase {

	const VIMEO_PROVIDER = 'https://vimeo.com/api/oembed.{format}?maxwidth=500&maxheight=750&url=https%3A%2F%2Fvimeo.com%2F81400335&dnt=1';

	/**
	 * The provider URL requested by WP_oEmbed::fetch().
	 *
	 * @var string|null
	 */
	private $requested_url;

	/**
	 * @ticket 66643
	 *
	 * @dataProvider data_adds_start_time
	 *
	 * @param string $url        Vimeo URL being embedded.
	 * @param int    $start_time Expected `start_time` argument.
	 */
	public function test_adds_start_time( $url, $start_time ) {
		$actual = wp_oembed_add_vimeo_start_time( self::VIMEO_PROVIDER, $url );

		$this->assertSame( self::VIMEO_PROVIDER . '&start_time=' . $start_time, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_adds_start_time() {
		return array(
			'seconds only'               => array( 'https://vimeo.com/81400335#t=62', 62 ),
			'seconds with suffix'        => array( 'https://vimeo.com/81400335#t=62s', 62 ),
			'minutes and seconds'        => array( 'https://vimeo.com/81400335#t=1m2s', 62 ),
			'hours, minutes and seconds' => array( 'https://vimeo.com/81400335#t=1h2m3s', 3723 ),
			'hours only'                 => array( 'https://vimeo.com/81400335#t=1h', 3600 ),
			'uppercase units'            => array( 'https://vimeo.com/81400335#t=1M2S', 62 ),
			'with query string'          => array( 'https://vimeo.com/81400335?share=copy#t=10', 10 ),
			'with other fragment args'   => array( 'https://vimeo.com/81400335#foo=bar&t=10', 10 ),
			'channel URL'                => array( 'https://vimeo.com/channels/staffpicks/81400335#t=10', 10 ),
		);
	}

	/**
	 * @ticket 66643
	 *
	 * @dataProvider data_does_not_add_start_time
	 *
	 * @param string $provider oEmbed provider URL.
	 * @param string $url      URL being embedded.
	 */
	public function test_does_not_add_start_time( $provider, $url ) {
		$this->assertSame( $provider, wp_oembed_add_vimeo_start_time( $provider, $url ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_does_not_add_start_time() {
		return array(
			'no fragment'            => array( self::VIMEO_PROVIDER, 'https://vimeo.com/81400335' ),
			'empty fragment'         => array( self::VIMEO_PROVIDER, 'https://vimeo.com/81400335#' ),
			'fragment without t'     => array( self::VIMEO_PROVIDER, 'https://vimeo.com/81400335#foo=bar' ),
			'empty timecode'         => array( self::VIMEO_PROVIDER, 'https://vimeo.com/81400335#t=' ),
			'zero timecode'          => array( self::VIMEO_PROVIDER, 'https://vimeo.com/81400335#t=0' ),
			'invalid timecode'       => array( self::VIMEO_PROVIDER, 'https://vimeo.com/81400335#t=abc' ),
			'negative timecode'      => array( self::VIMEO_PROVIDER, 'https://vimeo.com/81400335#t=-10' ),
			'array timecode'         => array( self::VIMEO_PROVIDER, 'https://vimeo.com/81400335#t[]=10' ),
			'start_time already set' => array( self::VIMEO_PROVIDER . '&start_time=5', 'https://vimeo.com/81400335#t=10' ),
			'other provider'         => array( 'https://www.youtube.com/oembed?url=x', 'https://vimeo.com/81400335#t=10' ),
			'lookalike host'         => array( 'https://vimeo.com.example.org/api/oembed.json', 'https://vimeo.com/81400335#t=10' ),
		);
	}

	/**
	 * @ticket 66643
	 *
	 * @covers WP_oEmbed::fetch
	 */
	public function test_fetch_passes_start_time_to_vimeo() {
		add_filter( 'pre_http_request', array( $this, 'capture_request' ), 10, 3 );

		_wp_oembed_get_object()->fetch( 'https://vimeo.com/api/oembed.{format}', 'https://vimeo.com/81400335#t=1m2s' );

		$this->assertIsString( $this->requested_url, 'No request was made to the provider.' );

		$query = array();
		wp_parse_str( (string) wp_parse_url( $this->requested_url, PHP_URL_QUERY ), $query );

		$this->assertSame( '62', $query['start_time'] ?? null, 'The start_time argument is not set.' );
		$this->assertSame( 'https://vimeo.com/81400335#t=1m2s', $query['url'], 'The url argument was changed.' );
	}

	/**
	 * Captures the provider URL and short-circuits the HTTP request.
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 * @return array A fake HTTP response.
	 */
	public function capture_request( $response, $parsed_args, $url ) {
		$this->requested_url = $url;

		return array(
			'headers'  => array(),
			'body'     => '{"type":"video","version":"1.0","html":"<iframe></iframe>"}',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
