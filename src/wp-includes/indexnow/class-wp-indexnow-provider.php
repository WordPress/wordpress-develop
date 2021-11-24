<?php
/**
 * IndexNow: WP_IndexNow_Provider class
 *
 * This class implements the IndexNow API to submit the urls to search engine.
 *
 * @package WordPress
 * @subpackage IndexNow
 * @since 5.9.0
 */

/**
 * Class WP_IndexNow_Provider.
 *
 * @since 5.9.0
 */

class WP_IndexNow_Provider {

	private $search_engine_url;
	const SUBMIT_API_PATH = '/indexnow/';
	const HOST            = 'host';
	const KEY             = 'key';
	const URL_LIST        = 'urlList';
	const BODY            = 'body';
	const HEADERS         = 'headers';
	const CONTENT_TYPE    = 'Content-Type';
	const KEY_LOCATION    = 'keyLocation';
	const HTTP            = 'http://';
	const HTTPS           = 'https://';
	/**
	 * WP_IndexNow_Provider constructor.
	 *
	 * @since 5.9.0
	 */
	public function __construct( $url ) {
		$this->search_engine_url = $url;
	}

	/**
	 * Removes scheme/protocol from thr url.
	 *
	 * @since 5.9.0
	 */
	private function remove_scheme( $url ) {
		if ( self::HTTP === substr( $url, 0, 7 ) ) {
			return substr( $url, 7 );
		}
		if ( self::HTTPS === substr( $url, 0, 8 ) ) {
			return substr( $url, 8 );
		}
		return $url;
	}

	/**
	 * Submits the url to search engine.
	 *
	 * @since 5.9.0
	 *
	 * @return string returns response message appropriately.
	 */
	public function submit_url( $site_url, $url, $api_key ) {
		$data = json_encode(
			array(
				self::HOST         => $this->remove_scheme( $site_url ),
				self::KEY          => $api_key,
				self::KEY_LOCATION => trailingslashit( $site_url ) . $api_key . '.txt',
				self::URL_LIST     => array( $url ),
			)
		);

			$response = wp_remote_post(
				$this->search_engine_url . self::SUBMIT_API_PATH,
				array(
					self::BODY    => $data,
					self::HEADERS => array( self::CONTENT_TYPE => 'application/json' ),
				)
			);

		if ( is_wp_error( $response ) ) {
			return 'error:WP_Error';
		}
		if ( isset( $response['errors'] ) ) {
			return 'error:RequestFailed';
		}
		try {
			if ( 200 === $response['response']['code'] ) {
				return 'success';
			} else {
				if ( $response['response']['code'] >= 500 ) {
					return 'error:' . $response['response']['message'];
				} else {
					$message = json_decode( $response['body'] );
					return 'error:' . $message;
				}
			}
		} catch ( \Throwable $th ) {
			return 'error:RequestFailed';
		}
	}

}
