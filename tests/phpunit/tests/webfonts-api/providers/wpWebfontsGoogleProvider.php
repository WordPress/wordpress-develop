<?php

/**
 * @group  webfonts
 * @covers WP_Webfonts_Google_Provider
 */
class Tests_Webfonts_API_wpWebfontsGoogleProvider extends WP_UnitTestCase {
	private $provider;

	public static function set_up_before_class() {
		require_once ABSPATH . WPINC . '/webfonts-api/providers/class-wp-webfonts-provider.php';
		require_once ABSPATH . WPINC . '/webfonts-api/providers/class-wp-webfonts-google-provider.php';
	}

	public function set_up() {
		parent::set_up();

		$this->provider = new WP_Webfonts_Google_Provider();
	}

	/**
	 * @covers WP_Webfonts_Google_Provider::set_webfonts
	 */
	public function test_set_webfonts() {
		$webfonts = array(
			'open-sans.normal.400' => array(
				'provider'     => 'google',
				'font-family'  => 'Open Sans',
				'font-style'   => 'normal',
				'font-weight'  => '400',
				'font-display' => 'fallback',
			),
			'open-sans.italic.700' => array(
				'provider'     => 'google',
				'font-family'  => 'Open Sans',
				'font-style'   => 'italic',
				'font-weight'  => '700',
				'font-display' => 'fallback',
			),
			'roboto.normal.900'    => array(
				'provider'     => 'google',
				'font-family'  => 'Roboto',
				'font-style'   => 'normal',
				'font-weight'  => '900',
				'font-display' => 'fallback',
			),
		);

		$this->provider->set_webfonts( $webfonts );

		$property = $this->get_webfonts_property();
		$this->assertSame( $webfonts, $property->getValue( $this->provider ) );
	}

	/**
	 * @covers WP_Webfonts_Google_Provider::build_collection_api_urls
	 *
	 * @dataProvider data_build_collection_api_urls
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfonts Webfonts input.
	 * @param array $expected Expected urls.
	 */
	public function test_build_collection_api_urls( array $webfonts, array $expected ) {
		$property = new ReflectionProperty( $this->provider, 'webfonts' );
		$property->setAccessible( true );
		$property->setValue( $this->provider, $webfonts );

		$method = new ReflectionMethod( $this->provider, 'build_collection_api_urls' );
		$method->setAccessible( true );
		$actual = $method->invoke( $this->provider );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data Provider.
	 *
	 * @return array
	 */
	public function data_build_collection_api_urls() {
		return array(
			'single font-family + single variation'    => array(
				'webfonts' => array(
					'open-sans.normal.400' => array(
						'provider'     => 'google',
						'font-family'  => 'Open Sans',
						'font-style'   => 'normal',
						'font-weight'  => '400',
						'font-display' => 'fallback',
						'is-external'  => true,
					),
				),
				'expected' => array(
					'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400&display=fallback',
				),
			),
			'single font-family + multiple variations' => array(
				'webfonts' => array(
					'open-sans.normal.400' => array(
						'provider'     => 'google',
						'font-family'  => 'Open Sans',
						'font-style'   => 'normal',
						'font-weight'  => '400',
						'font-display' => 'fallback',
						'is-external'  => true,
					),
					'open-sans.italic.700' => array(
						'provider'     => 'google',
						'font-family'  => 'Open Sans',
						'font-style'   => 'italic',
						'font-weight'  => '700',
						'font-display' => 'fallback',
						'is-external'  => true,
					),
				),
				'expected' => array(
					'https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,400;1,700&display=fallback',
				),
			),
			'multiple font-families and variations'    => array(
				'webfonts' => array(
					'open-sans.normal.400' => array(
						'provider'     => 'google',
						'font-family'  => 'Open Sans',
						'font-style'   => 'normal',
						'font-weight'  => '400',
						'font-display' => 'fallback',
						'is-external'  => true,
					),
					'open-sans.italic.700' => array(
						'provider'     => 'google',
						'font-family'  => 'Open Sans',
						'font-style'   => 'italic',
						'font-weight'  => '700',
						'font-display' => 'fallback',
						'is-external'  => true,
					),
					'roboto.normal.900'    => array(
						'provider'     => 'google',
						'font-family'  => 'Roboto',
						'font-style'   => 'normal',
						'font-weight'  => '900',
						'font-display' => 'fallback',
						'is-external'  => true,
					),
				),
				'expected' => array(
					'https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,400;1,700&family=Roboto:wght@900&display=fallback',
				),
			),
		);
	}

	private function get_webfonts_property() {
		$property = new ReflectionProperty( $this->provider, 'webfonts' );
		$property->setAccessible( true );

		return $property;
	}
}
