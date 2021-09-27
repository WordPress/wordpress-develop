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
	private const SUBMIT_API_PATH = '/indexnow/';
	private const HOST            = 'host';
	private const KEY             = 'key';
	private const URL_LIST        = 'urlList';
	private const BODY            = 'body';
	private const HEADERS         = 'headers';
	private const CONTENT_TYPE    = 'Content-Type';
	private const KEY_LOCATION    = 'keyLocation';
	
	/**
	 * WP_IndexNow_Provider constructor.
	 *
	 * @since 5.9.0
	 */
	public function __construct( $url ) {
		$this->search_engine_url = $url;
	}

	/**
	 * Submits the url to search engine.
	 *
	 * @since 5.9.0
	 * 
	 * @return string returns response message appropriately.
	 */
	public function submit_url( $siteUrl, $url, $api_key ) {
		$data = json_encode(
			array(
				self::HOST     => $siteUrl,
				self::KEY      => $api_key,
				self::KEY_LOCATION => trailingslashit($siteUrl) . $api_key.'.txt',
				self::URL_LIST => array( $url ),
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
			if ( $response['response']['code'] === 200 ) {
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
