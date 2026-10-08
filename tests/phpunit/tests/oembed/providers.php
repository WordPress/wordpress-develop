<?php
/**
 * Tests for oEmbed providers.
 *
 * @group oembed
 * @group external-http
 */

class Tests_Post_Embed_Providers extends WP_UnitTestCase {

	public static $matched_provider_keys = array();

	public function get_oembed_providers() {
		$wp_oembed = _wp_oembed_get_object();
		return $wp_oembed->providers;
	}

	public function get_oembed_provider_with_key( $url ) {
		$providers = $this->get_oembed_providers();

		foreach ( $providers as $matchmask => $data ) {
			list( $provider_url, $regex ) = $data;
			$provider_key                 = $matchmask;

			// Turn the asterisk-type provider URLs into regex.
			if ( ! $regex ) {
				$matchmask = '#' . str_replace( '___wildcard___', '(.+)', preg_quote( str_replace( '*', '___wildcard___', $matchmask ), '#' ) ) . '#i';
				$matchmask = preg_replace( '|^#http\\\://|', '#https?\://', $matchmask );
			}

			if ( preg_match( $matchmask, $url ) ) {
				$provider = str_replace( '{format}', 'json', $provider_url ); // JSON is easier to deal with than XML.
				break;
			}
		}

		if ( ! isset( $provider ) ) {
			return false;
		}

		return array(
			'provider_url' => $provider_url,
			'provider_key' => $provider_key,
			'matchmask'    => $matchmask,
			'provider'     => $provider,
		);
	}

	/**
	 * @dataProvider data_providers
	 */
	public function test_providers( $url ) {
		$provider = $this->get_oembed_provider_with_key( $url );
		$this->assertNotFalse( $provider, 'No provider found for URL: ' . $url );

		// var_dump( $provider ); // For debugging purposes, can be removed later.

		self::$matched_provider_keys[ $provider['provider_key'] ] = true;

		$html = wp_oembed_get( $url );
		$this->assertNotEmpty( $html, 'No HTML returned for URL: ' . $url );
	}

	public function data_providers() {
		return array(
			'youtube watch (www)'            => array( 'https://www.youtube.com/watch?v=btPJPFnesV4' ),
			'youtube watch (no www)'         => array( 'https://youtube.com/watch?v=btPJPFnesV4' ),
			'youtube watch (m)'              => array( 'https://m.youtube.com/watch?v=btPJPFnesV4' ),

			'youtube playlist (www)'         => array( 'https://www.youtube.com/playlist?list=PLK1wqzZ8S6RzYWi1mCsT_dZngxD5XRWUM' ),
			'youtube playlist (no www)'      => array( 'https://youtube.com/playlist?list=PLK1wqzZ8S6RzYWi1mCsT_dZngxD5XRWUM' ),
			'youtube playlist (m)'           => array( 'https://m.youtube.com/playlist?list=PLK1wqzZ8S6RzYWi1mCsT_dZngxD5XRWUM' ),

			'youtube short (www)'            => array( 'https://www.youtube.com/shorts/VWqp3wJKIGk' ),
			'youtube short (no www)'         => array( 'https://youtube.com/shorts/VWqp3wJKIGk' ),
			'youtube short (m)'              => array( 'https://m.youtube.com/shorts/VWqp3wJKIGk' ),

			// This is the ABC News Australia broadcast, running since Feb 20, 2022.
			'youtube live (www)'             => array( 'https://www.youtube.com/live/vOTiJkg1voo' ),
			'youtube live (no www)'          => array( 'https://youtube.com/live/vOTiJkg1voo' ),
			'youtube live (m)'               => array( 'https://m.youtube.com/live/vOTiJkg1voo' ),

			'youtu\.be short link'           => array( 'https://youtu.be/btPJPFnesV4' ),

			'vimeo video (www)'              => array( 'https://www.vimeo.com/1092329526' ),
			'vimeo video (no www)'           => array( 'https://vimeo.com/1092329526' ),

			'dailymotion (www)'              => array( 'https://www.dailymotion.com/video/x9iwk50' ),
			'dailymotion (no www)'           => array( 'https://dailymotion.com/video/x9iwk50' ),
			'dailymotion (short link)'       => array( 'https://dai.ly/x9iwk50' ),

			'flickr photo (www)'             => array( 'https://www.flickr.com/photos/wceu/54572915529/' ),
			'flickr photo (no www)'          => array( 'https://flickr.com/photos/wceu/54572915529/' ),
			'flickr photo (short)'           => array( 'https://flic.kr/p/2r9qsuH' ),

			'flickr photo in album (www)'    => array( 'https://www.flickr.com/photos/wceu/54572915529/in/album-72177720326409151/' ),
			'flickr photo in album (no www)' => array( 'https://flickr.com/photos/wceu/54572915529/in/album-72177720326409151/' ),

			'flickr album (www)'             => array( 'https://www.flickr.com/photos/wceu/sets/72177720326409151/' ),
			'flickr album (no-www)'          => array( 'https://flickr.com/photos/wceu/sets/72177720326409151/' ),
			'flickr album (short)'           => array( 'https://flic.kr/s/aHBqjCfFG4' ),
		);
	}

	/**
	 * Test all providers have been tested.
	 */
	public function test_tested_providers() {
		$this->markTestIncomplete( 'Currently working on the data provider to ensure all providers are tested.' );
		$providers             = array_keys( $this->get_oembed_providers() );
		$matched_provider_keys = array_keys( self::$matched_provider_keys );

		$this->assertSameSets( $providers, $matched_provider_keys, 'Not all providers were tested.' );
	}
}
