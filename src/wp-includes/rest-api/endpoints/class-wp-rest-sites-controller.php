<?php
/**
 * REST API: WP_REST_Sites_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 6.x.x
 */

/**
 * Core class to access posts via the REST API.
 *
 * @since 6.x.x
 *
 * @see WP_REST_Controller
 */
class WP_REST_Sites_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'sites';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_sites' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => array(
						'page'     => array(
							'type'     => 'integer',
							'required' => false,
							'minimum'  => 1,
							'default'  => 1,
						),
						'per_page' => array(
							'type'     => 'integer',
							'required' => false,
							'minimum'  => 1,
							'maximum'  => 200,
							'default'  => 20,
						),
						'search'   => array(
							'type'     => 'string',
							'required' => false,
						),
						'orderby'  => array(
							'type'     => 'string',
							'required' => false,
							'enum'     => array(
								'id',
								'url',
								'domain',
								'path',
								'registered',
								'last_updated',
								'blog_id'
							),
							'default'  => 'id',
						),
						'order'    => array(
							'type'     => 'string',
							'required' => false,
							'enum'     => array( 'asc', 'desc' ),
							'default'  => 'asc',
						),
					),
				),
			)
		);
	}

	/**
	 * Check if the current user has permissions to manage sites.
	 *
	 * @return bool
	 */
	public function permissions(): bool {
		return current_user_can( 'manage_network' );
	}

	/**
	 * Map DataViews field names to get_sites() orderby keys.
	 *
	 * @param string $field The field name to map.
	 *
	 * @return string The mapped field name, or 'id' if not recognized.
	 */
	private function map_orderby( string $field ): string {
		$map = array(
			'blog_id'      => 'id',
			'url'          => 'home_url',
			'domain'       => 'domain',
			'path'         => 'path',
			'registered'   => 'registered',
			'last_updated' => 'last_updated',
		);

		return $map[ $field ] ?? 'id';
	}

	/**
	 * GET /sites — list sites with basic paging/search/sort.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function list_sites( WP_REST_Request $request ): WP_REST_Response {
		$page              = max( 1, (int) $request->get_param( 'page' ) );
		$per_page          = max( 1, min( 200, (int) $request->get_param( 'per_page' ) ) );
		$search            = (string) ( $request->get_param( 'search' ) ?? '' );
		$orderby           = $this->map_orderby( (string) ( $request->get_param( 'orderby' ) ?? 'id' ) );
		$orderby_for_query = ( 'home_url' === $orderby ) ? 'domain' : $orderby;
		$order             = strtolower( (string) ( $request->get_param( 'order' ) ?? 'asc' ) ) === 'desc' ? 'DESC' : 'ASC';

		$args = array(
			'number'  => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => $orderby_for_query,
			'order'   => $order,
		);
		if ( '' !== $search ) {
			$args['search'] = '*' . $search . '*';
		}

		$items = array_map(
			static function ( \WP_Site $s ): array {
				$id = (int) $s->blog_id;

				return array(
					'id'           => $id,
					'blog_id'      => $id,
					'domain'       => $s->domain,
					'path'         => $s->path,
					'public'       => (int) get_blog_status( $id, 'public' ),
					'spam'         => (int) get_blog_status( $id, 'spam' ),
					'archived'     => (int) get_blog_status( $id, 'archived' ),
					'deleted'      => (int) get_blog_status( $id, 'deleted' ),
					'mature'       => (int) get_blog_status( $id, 'mature' ),
					'registered'   => $s->registered,
					'last_updated' => '0000-00-00 00:00:00' === $s->last_updated ? null : $s->last_updated,
					'admin_url'    => get_admin_url( $id ),
					'home_url'     => get_home_url( $id, '/' ),
				);
			},
			get_sites( $args )
		);

		if ( 'home_url' === $orderby ) {
			usort(
				$items,
				static function ( array $a, array $b ) use ( $order ): int {
					return ( 'DESC' === $order )
						? strcmp( $b['home_url'], $a['home_url'] )
						: strcmp( $a['home_url'], $b['home_url'] );
				}
			);
		}

		$total = (int) get_sites(
			array_merge(
				$args,
				array(
					'count'  => true,
					'offset' => 0,
					'number' => 0,
				)
			)
		);

		return new WP_REST_Response(
			array(
				'items' => $items,
				'total' => $total,
			),
			200
		);
	}
}
