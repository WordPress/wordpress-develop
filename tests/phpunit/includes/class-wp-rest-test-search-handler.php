<?php
/**
 * REST API: WP_REST_Test_Search_Handler class
 *
 * @package WordPress
 * @subpackage REST_API
 */

/**
 * Test class extending WP_REST_Search_Handler
 */
class WP_REST_Test_Search_Handler extends WP_REST_Search_Handler {

	protected $items = array();

	public function __construct( $amount = 10 ) {
		$this->type = 'test';

		$this->subtypes = array( 'test_first_type', 'test_second_type' );

		$this->items = array();
		for ( $i = 1; $i <= $amount; $i++ ) {
			$subtype = $i > $amount / 2 ? 'test_second_type' : 'test_first_type';

			$this->items[ $i ] = (object) array(
				'test_id'    => $i,
				'test_title' => sprintf( 'Title %d', $i ),
				'test_url'   => sprintf( home_url( '/tests/%d' ), $i ),
				'test_type'  => $subtype,
			);
		}
	}

	public function search_items( WP_REST_Request $request ) {
		$subtypes = $request[ WP_REST_Search_Controller::PROP_SUBTYPE ];
		if ( in_array( WP_REST_Search_Controller::TYPE_ANY, $subtypes, true ) ) {
			$subtypes = $this->subtypes;
		}

		$results = array();
		foreach ( $subtypes as $subtype ) {
			$results = array_merge( $results, wp_list_filter( array_values( $this->items ), array( 'test_type' => $subtype ) ) );
		}

		$results = wp_list_sort( $results, 'test_id', 'DESC' );

		$number = (int) $request['per_page'];
		$offset = (int) $request['per_page'] * ( (int) $request['page'] - 1 );

		$total = count( $results );

		$results = array_slice( $results, $offset, $number );

		return array(
			self::RESULT_IDS   => wp_list_pluck( $results, 'test_id' ),
			self::RESULT_TOTAL => $total,
		);
	}

	public function prepare_item( $id, array $fields ) {
		$test = $this->items[ $id ];

		$data = array();

		if ( in_array( WP_REST_Search_Controller::PROP_ID, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_ID ] = (int) $test->test_id;
		}

		if ( in_array( WP_REST_Search_Controller::PROP_TITLE, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_TITLE ] = $test->test_title;
		}

		if ( in_array( WP_REST_Search_Controller::PROP_URL, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_URL ] = $test->test_url;
		}

		if ( in_array( WP_REST_Search_Controller::PROP_TYPE, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_TYPE ] = $this->type;
		}

		if ( in_array( WP_REST_Search_Controller::PROP_SUBTYPE, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_SUBTYPE ] = $test->test_type;
		}

		return $data;
	}

	public function prepare_item_links( $id ) {
		return array();
	}
}
