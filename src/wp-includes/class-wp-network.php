<?php
/**
 * Network API: WP_Network class
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 4.4.0
 */

/**
 * Core class used for interacting with a multisite network.
 *
 * This class is used during load to populate the `$current_site` global and
 * setup the current network.
 *
 * This class is most useful in WordPress multi-network installations where the
 * ability to interact with any network of sites is required.
 *
 * @since 4.4.0
 *
 * @property int $id
 * @property int $site_id
 */
#[AllowDynamicProperties]
class WP_Network {

	/**
	 * Network ID.
	 *
	 * @since 4.4.0
	 * @since 4.6.0 Converted from public to private to explicitly enable more intuitive
	 *              access via magic methods. As part of the access change, the type was
	 *              also changed from `string` to `int`.
	 * @var int
	 */
	private $id;

	/**
	 * Domain of the network.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	public $domain = '';

	/**
	 * Path of the network.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	public $path = '';

	/**
	 * The ID of the network's main site.
	 *
	 * Named "blog" vs. "site" for legacy reasons. A main site is mapped to
	 * the network when the network is created.
	 *
	 * A numeric string, for compatibility reasons.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	private $blog_id = '0';

	/**
	 * Domain used to set cookies for this network.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	public $cookie_domain = '';

	/**
	 * Name of this network.
	 *
	 * Named "site" vs. "network" for legacy reasons.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	public $site_name = '';

	/**
	 * Retrieves a network from the database by its ID.
	 *
	 * @since 4.4.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $network_id The ID of the network to retrieve.
	 * @return WP_Network|false The network's object if found. False if not.
	 */
	public static function get_instance( $network_id ) {
		global $wpdb;

		$network_id = (int) $network_id;
		if ( ! $network_id ) {
			return false;
		}

		$_network = wp_cache_get( $network_id, 'networks' );

		if ( false === $_network ) {
			$_network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d LIMIT 1", $network_id ) );

			if ( null === $_network ) {
				$_network = -1;
			} elseif ( empty( $_network ) || is_wp_error( $_network ) ) {
				return false;
			}

			wp_cache_add( $network_id, $_network, 'networks' );
		}

		if ( is_numeric( $_network ) ) {
			return false;
		}

		return new WP_Network( $_network );
	}

	/**
	 * Creates a new WP_Network object.
	 *
	 * Will populate object properties from the object provided and assign other
	 * default properties based on that information.
	 *
	 * @since 4.4.0
	 *
	 * @param WP_Network|object $network A network object.
	 */
	public function __construct( $network ) {
		foreach ( get_object_vars( $network ) as $key => $value ) {
			$this->__set( $key, $value );
		}

		$this->_set_site_name();
		$this->_set_cookie_domain();
	}

	/**
	 * Getter.
	 *
	 * Allows current multisite naming conventions when getting properties.
	 *
	 * @since 4.6.0
	 *
	 * @param string $key Property to get.
	 * @return mixed Value of the property. Null if not available.
	 */
	public function __get( $key ) {
		switch ( $key ) {
			case 'id':
				return (int) $this->id;
			case 'blog_id':
				return (string) $this->get_main_site_id();
			case 'site_id':
				return $this->get_main_site_id();
		}

		return null;
	}

	/**
	 * Isset-er.
	 *
	 * Allows current multisite naming conventions when checking for properties.
	 *
	 * @since 4.6.0
	 *
	 * @param string $key Property to check if set.
	 * @return bool Whether the property is set.
	 */
	public function __isset( $key ) {
		switch ( $key ) {
			case 'id':
			case 'blog_id':
			case 'site_id':
				return true;
		}

		return false;
	}

	/**
	 * Setter.
	 *
	 * Allows current multisite naming conventions while setting properties.
	 *
	 * @since 4.6.0
	 *
	 * @param string $key   Property to set.
	 * @param mixed  $value Value to assign to the property.
	 */
	public function __set( $key, $value ) {
		switch ( $key ) {
			case 'id':
				$this->id = (int) $value;
				break;
			case 'blog_id':
			case 'site_id':
				$this->blog_id = (string) $value;
				break;
			default:
				$this->$key = $value;
		}
	}

	/**
	 * Returns the main site ID for the network.
	 *
	 * Internal method used by the magic getter for the 'blog_id' and 'site_id'
	 * properties.
	 *
	 * @since 4.9.0
	 *
	 * @return int The ID of the main site.
	 */
	private function get_main_site_id() {
		/**
		 * Filters the main site ID.
		 *
		 * Returning a positive integer will effectively short-circuit the function.
		 *
		 * @since 4.9.0
		 *
		 * @param int|null   $main_site_id If a positive integer is returned, it is interpreted as the main site ID.
		 * @param WP_Network $network      The network object for which the main site was detected.
		 */
		$main_site_id = (int) apply_filters( 'pre_get_main_site_id', null, $this );

		if ( 0 < $main_site_id ) {
			return $main_site_id;
		}

		if ( 0 < (int) $this->blog_id ) {
			return (int) $this->blog_id;
		}

		if ( ( defined( 'DOMAIN_CURRENT_SITE' ) && defined( 'PATH_CURRENT_SITE' )
			&& DOMAIN_CURRENT_SITE === $this->domain && PATH_CURRENT_SITE === $this->path )
			|| ( defined( 'SITE_ID_CURRENT_SITE' ) && (int) SITE_ID_CURRENT_SITE === $this->id )
		) {
			if ( defined( 'BLOG_ID_CURRENT_SITE' ) ) {
				$this->blog_id = (string) BLOG_ID_CURRENT_SITE;

				return (int) $this->blog_id;
			}

			if ( defined( 'BLOGID_CURRENT_SITE' ) ) { // Deprecated.
				$this->blog_id = (string) BLOGID_CURRENT_SITE;

				return (int) $this->blog_id;
			}
		}

		$site = get_site();
		if ( $site->domain === $this->domain && $site->path === $this->path ) {
			$main_site_id = (int) $site->id;
		} else {

			$main_site_id = get_network_option( $this->id, 'main_site' );
			if ( false === $main_site_id ) {
				$_sites       = get_sites(
					array(
						'fields'     => 'ids',
						'number'     => 1,
						'domain'     => $this->domain,
						'path'       => $this->path,
						'network_id' => $this->id,
					)
				);
				$main_site_id = ! empty( $_sites ) ? array_shift( $_sites ) : 0;

				update_network_option( $this->id, 'main_site', $main_site_id );
			}
		}

		$this->blog_id = (string) $main_site_id;

		return (int) $this->blog_id;
	}

	/**
	 * Sets the site name assigned to the network if one has not been populated.
	 *
	 * @since 4.4.0
	 */
	private function _set_site_name() {
		if ( ! empty( $this->site_name ) ) {
			return;
		}

		$default         = ucfirst( $this->domain );
		$this->site_name = get_network_option( $this->id, 'site_name', $default );
	}

	/**
	 * Sets the cookie domain based on the network domain if one has
	 * not been populated.
	 *
	 * @todo What if the domain of the network doesn't match the current site?
	 *
	 * @since 4.4.0
	 */
	private function _set_cookie_domain() {
		if ( ! empty( $this->cookie_domain ) ) {
			return;
		}
		$domain              = parse_url( $this->domain, PHP_URL_HOST );
		$this->cookie_domain = is_string( $domain ) ? $domain : $this->domain;
		if ( str_starts_with( $this->cookie_domain, 'www.' ) ) {
			$this->cookie_domain = substr( $this->cookie_domain, 4 );
		}
	}

	/**
	 * Retrieves the closest matching network for a domain and path.
	 *
	 * This will not necessarily return an exact match for a domain and path. Instead, it
	 * breaks the domain and path into pieces that are then used to match the closest
	 * possibility from a query.
	 *
	 * The intent of this method is to match a network during bootstrap for a
	 * requested site address.
	 *
	 * @since 4.4.0
	 *
	 * @param string   $domain   Domain to check.
	 * @param string   $path     Path to check.
	 * @param int|null $segments Path segments to use. Defaults to null, or the full path.
	 * @return WP_Network|false Network object if successful. False when no network is found.
	 */
	public static function get_by_path( $domain = '', $path = '', $segments = null ) {
		$domains = array( $domain );
		$pieces  = explode( '.', $domain );

		/*
		 * It's possible one domain to search is 'com', but it might as well
		 * be 'localhost' or some other locally mapped domain.
		 */
		while ( array_shift( $pieces ) ) {
			if ( ! empty( $pieces ) ) {
				$domains[] = implode( '.', $pieces );
			}
		}

		/*
		 * If we've gotten to this function during normal execution, there is
		 * more than one network installed. At this point, who knows how many
		 * we have. Attempt to optimize for the situation where networks are
		 * only domains, thus meaning paths never need to be considered.
		 *
		 * This is a very basic optimization; anything further could have
		 * drawbacks depending on the setup, so this is best done per-installation.
		 */
		$using_paths = true;
		if ( wp_using_ext_object_cache() ) {
			$using_paths = get_networks(
				array(
					'number'       => 1,
					'count'        => true,
					'path__not_in' => '/',
				)
			);
		}

		$paths = array();
		if ( $using_paths ) {
			$path_segments = array_filter( explode( '/', trim( $path, '/' ) ) );

			/**
			 * Filters the number of path segments to consider when searching for a site.
			 *
			 * @since 3.9.0
			 *
			 * @param int|null $segments The number of path segments to consider. WordPress by default looks at
			 *                           one path segment. The function default of null only makes sense when you
			 *                           know the requested path should match a network.
			 * @param string   $domain   The requested domain.
			 * @param string   $path     The requested path, in full.
			 */
			$segments = apply_filters( 'network_by_path_segments_count', $segments, $domain, $path );

			if ( ( null !== $segments ) && count( $path_segments ) > $segments ) {
				$path_segments = array_slice( $path_segments, 0, $segments );
			}

			while ( count( $path_segments ) ) {
				$paths[] = '/' . implode( '/', $path_segments ) . '/';
				array_pop( $path_segments );
			}

			$paths[] = '/';
		}

		/**
		 * Determines a network by its domain and path.
		 *
		 * This allows one to short-circuit the default logic, perhaps by
		 * replacing it with a routine that is more optimal for your setup.
		 *
		 * Return null to avoid the short-circuit. Return false if no network
		 * can be found at the requested domain and path. Otherwise, return
		 * an object from wp_get_network().
		 *
		 * @since 3.9.0
		 *
		 * @param null|false|WP_Network $network  Network value to return by path. Default null
		 *                                        to continue retrieving the network.
		 * @param string                $domain   The requested domain.
		 * @param string                $path     The requested path, in full.
		 * @param int|null              $segments The suggested number of paths to consult.
		 *                                        Default null, meaning the entire path was to be consulted.
		 * @param string[]              $paths    Array of paths to search for, based on `$path` and `$segments`.
		 */
		$pre = apply_filters( 'pre_get_network_by_path', null, $domain, $path, $segments, $paths );
		if ( null !== $pre ) {
			return $pre;
		}

		if ( ! $using_paths ) {
			$networks = get_networks(
				array(
					'number'     => 1,
					'orderby'    => array(
						'domain_length' => 'DESC',
					),
					'domain__in' => $domains,
				)
			);

			if ( ! empty( $networks ) ) {
				return array_shift( $networks );
			}

			return false;
		}

		$networks = get_networks(
			array(
				'orderby'    => array(
					'domain_length' => 'DESC',
					'path_length'   => 'DESC',
				),
				'domain__in' => $domains,
				'path__in'   => $paths,
			)
		);

		/*
		 * Domains are sorted by length of domain, then by length of path.
		 * The domain must match for the path to be considered. Otherwise,
		 * a network with the path of / will suffice.
		 */
		$found = false;
		foreach ( $networks as $network ) {
			if ( ( $network->domain === $domain ) || ( "www.{$network->domain}" === $domain ) ) {
				if ( in_array( $network->path, $paths, true ) ) {
					$found = true;
					break;
				}
			}
			if ( '/' === $network->path ) {
				$found = true;
				break;
			}
		}

		if ( true === $found ) {
			return $network;
		}

		return false;
	}
}
