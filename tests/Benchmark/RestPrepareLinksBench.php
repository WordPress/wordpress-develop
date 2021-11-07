<?php
// phpcs:ignoreFile
declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/src/wp-load.php';

/**
 * @Revs(10000)
 * @Iterations(5)
 * @BeforeClassMethods("setUpBeforeClass")
 */
class RestPrepareLinksBench {
	private $server;

	private $request;

	private static $taxonomy = 'category';

	public static function setUpBeforeClass() {
		$count = wp_count_terms(
			[
				'taxonomy'   => self::$taxonomy,
				'hide_empty' => false,
			]
		);

		if ( $count < 100 ) {
			/*
			 * This benchmark is designed to test performance on sites with many
			 * terms. You can generate categories for this class using WP-CLI:
			 *
			 * `wp term generate category --count=100`
			 *
			 * Comment the line below if you'd like to also run the benchmark on
			 * sites with fewer terms.
			 */
			throw new Exception( 'Not enough terms in ' . self::$taxonomy );
		}
	}

	/**
	 * @BeforeMethods("setUp")
	 * @Warmup(2)
	 */
	public function bench_default() {
		$this->server->dispatch( $this->request );
	}

	/**
	 * @BeforeMethods({"setUp", "addFilter"})
	 * @AfterMethods("removeFilter")
	 * @Warmup(2)
	 */
	public function bench_with_filter() {
		$this->server->dispatch( $this->request );
	}

	public function setUp() {
		$this->server  = rest_get_server();
		$this->request = new WP_REST_Request( 'GET', rest_get_route_for_taxonomy_items( self::$taxonomy ) );
		$this->request->set_param( 'per_page', 100 );
		$this->request->set_param( 'orderby', 'name' );
		$this->request->set_param( 'order', 'asc' );
		$this->request->set_param( '_fields', [ 'id', 'name', 'parent' ] );
		$this->request->set_param( '_locale', 'user' );
	}

	public function addFilter() {
		add_filter( 'rest_url', [ $this, 'filter' ] );
	}

	public function removeFilter() {
		remove_filter( 'rest_url', [ $this, 'filter' ] );
	}

	public function filter( $url ) {
		return $url;
	}
}
