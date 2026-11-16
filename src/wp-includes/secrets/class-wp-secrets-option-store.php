<?php
/**
 * Secrets API: WP_Secrets_Option_Store class
 *
 * @package WordPress
 * @subpackage Secrets
 * @since 7.2.0
 */

/**
 * The default secret store.
 *
 * Keeps records as rows in the options tables, always with autoload disabled, and
 * always excluded from options.php and the REST settings endpoint.
 *
 * Site-scope secrets live under '_wp_secret_{name}' via get_option()/update_option().
 * Network-scope secrets live under '_wp_network_secret_{name}' via the *_site_option()
 * functions, which on a non-multisite install are themselves backed by wp_options –
 * so on a single site, site- and network-scope secrets differ only by prefix, in the
 * same table; on a real network, network-scope rows live in wp_sitemeta instead.
 *
 * Converting a single site to multisite does not move its network-scope rows: they
 * stay in what becomes the main site's options table. They are still read from there
 * on the main network, and are moved to wp_sitemeta, or removed, only when the secret
 * is next written or deleted. A read never moves them.
 *
 * @since 7.2.0
 */
final class WP_Secrets_Option_Store extends WP_Secrets_Store {

	/**
	 * Option name prefix for site-scope secrets.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	const SITE_PREFIX = '_wp_secret_';

	/**
	 * Option name prefix for network-scope secrets.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	const NETWORK_PREFIX = '_wp_network_secret_';

	/**
	 * Reads a secret's stored record.
	 *
	 * get_option() and get_site_option() return the default both when the option does
	 * not exist and when their query failed, so a default is checked against the database
	 * before the secret is reported as absent.
	 *
	 * @since 7.2.0
	 *
	 * @param string $name    The secret's namespaced name.
	 * @param bool   $network Whether this is a network-scope secret.
	 * @return array<mixed>|null|WP_Error
	 */
	public function get( string $name, bool $network = false ) {
		$option_name = $this->option_name( $name, $network );
		$value       = $network ? get_site_option( $option_name, null ) : get_option( $option_name, null );

		if ( null === $value ) {
			$value = $this->read_stored_value( $option_name, $network );
		}

		if ( null === $value && $network ) {
			$value = $this->read_pre_conversion_value( $option_name );
		}

		if ( null === $value || is_wp_error( $value ) ) {
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return new WP_Error(
				WP_SECRETS_ERROR_RECORD_MALFORMED,
				__( 'The stored secret record is not an array.' )
			);
		}

		return $value;
	}

	/**
	 * Writes a secret's record.
	 *
	 * @since 7.2.0
	 *
	 * @param string       $name    The secret's namespaced name.
	 * @param array<mixed> $record  The record to store.
	 * @param bool         $network Whether this is a network-scope secret.
	 * @return true|WP_Error
	 */
	public function set( string $name, array $record, bool $network = false ) {
		$option_name = $this->option_name( $name, $network );

		if ( $network ) {
			$updated = update_site_option( $option_name, $record );
		} else {
			$updated = update_option( $option_name, $record, false );
		}

		/*
		 * update_option()/update_site_option() return false both on genuine
		 * failure and when the new value already equals the stored one – a
		 * documented ambiguity in the Options API. Read back and compare rather
		 * than treating every false as a failure.
		 */
		if ( ! $updated ) {
			$current = $network ? get_site_option( $option_name ) : get_option( $option_name );
			$updated = $current === $record;
		}

		if ( $updated ) {
			if ( $network ) {
				// The record now lives in wp_sitemeta. A copy from before the conversion to multisite is outdated.
				$this->delete_pre_conversion_value( $option_name );
			}

			return true;
		}

		return new WP_Error(
			WP_SECRETS_ERROR_STORE_UNAVAILABLE,
			__( 'The secret record could not be written.' )
		);
	}

	/**
	 * Deletes a secret's record.
	 *
	 * @since 7.2.0
	 *
	 * @param string $name    The secret's namespaced name.
	 * @param bool   $network Whether this is a network-scope secret.
	 * @return true|WP_Error
	 */
	public function delete( string $name, bool $network = false ) {
		$option_name = $this->option_name( $name, $network );
		$deleted     = $network ? delete_site_option( $option_name ) : delete_option( $option_name );

		if ( $network && $this->get_pre_conversion_site_id() ) {
			/*
			 * A record from before the conversion to multisite would be read again once
			 * this one is gone, so it is deleted too, and neither may remain.
			 */
			$this->delete_pre_conversion_value( $option_name );
		} elseif ( $deleted ) {
			return true;
		}

		/*
		 * delete_*_option() also returns false when the option never existed.
		 * Deleting something already absent is not a failure.
		 */
		if ( null === $this->get( $name, $network ) ) {
			return true;
		}

		return new WP_Error(
			WP_SECRETS_ERROR_STORE_UNAVAILABLE,
			__( 'The secret record could not be deleted.' )
		);
	}

	/**
	 * Lists the names of every secret in this store, for this scope.
	 *
	 * @since 7.2.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param bool $network Whether to list network-scope secrets.
	 * @return string[]|WP_Error
	 */
	public function list_names( bool $network = false ) {
		global $wpdb;

		$prefix  = $network ? self::NETWORK_PREFIX : self::SITE_PREFIX;
		$pattern = $wpdb->esc_like( $prefix ) . '%';

		if ( $network && is_multisite() ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND site_id = %d",
					$pattern,
					get_current_network_id()
				)
			);
		} else {
			$result = $wpdb->query(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
					$pattern
				)
			);
		}

		/*
		 * Failure is taken from wpdb::query(), because wpdb::get_col() returns an
		 * empty array for a failed query, which would read as no secrets.
		 */
		if ( false === $result ) {
			return new WP_Error(
				WP_SECRETS_ERROR_STORE_UNAVAILABLE,
				__( 'Could not list secrets.' )
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reads the result of the prepared query above.
		$option_names = $wpdb->get_col();

		if ( $network && $this->get_pre_conversion_site_id() ) {
			$pre_conversion_names = $this->list_pre_conversion_option_names( $pattern );

			if ( is_wp_error( $pre_conversion_names ) ) {
				return $pre_conversion_names;
			}

			$option_names = array_unique( array_merge( $option_names, $pre_conversion_names ) );
		}

		$names = array();

		foreach ( $option_names as $option_name ) {
			if ( ! is_string( $option_name ) ) {
				continue;
			}

			$names[] = substr( $option_name, strlen( $prefix ) );
		}

		return $names;
	}

	/**
	 * Reads an option's value from the database, bypassing the options cache.
	 *
	 * A failed query in get_option() or get_site_option() also caches the option as
	 * missing. When the row exists, this clears that cache entry, so the failed read
	 * does not outlive this call.
	 *
	 * @since 7.2.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $option_name The option name.
	 * @param bool   $network     Whether this is a network-scope secret.
	 * @return mixed The stored value, null if there is no row, or WP_Error if the read failed.
	 */
	private function read_stored_value( string $option_name, bool $network ) {
		global $wpdb;

		if ( $network && is_multisite() ) {
			$network_id     = get_current_network_id();
			$cache_group    = 'site-options';
			$cache_key      = "$network_id:$option_name";
			$notoptions_key = "$network_id:notoptions";
			$result         = $wpdb->query(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d LIMIT 1",
					$option_name,
					$network_id
				)
			);
		} else {
			$cache_group    = 'options';
			$cache_key      = $option_name;
			$notoptions_key = 'notoptions';
			$result         = $wpdb->query(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					$option_name
				)
			);
		}

		if ( false === $result ) {
			return new WP_Error(
				WP_SECRETS_ERROR_STORE_UNAVAILABLE,
				__( 'The secret record could not be read.' )
			);
		}

		if ( ! $wpdb->num_rows ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reads the result of the prepared query above.
		$stored = $wpdb->get_var();

		// get_var() returns null for a row with no value. That row exists, so it must not read as absent.
		$value = maybe_unserialize( $stored ?? '' );

		wp_cache_delete( $cache_key, $cache_group );

		$notoptions = wp_cache_get( $notoptions_key, $cache_group );

		if ( is_array( $notoptions ) && isset( $notoptions[ $option_name ] ) ) {
			unset( $notoptions[ $option_name ] );
			wp_cache_set( $notoptions_key, $notoptions, $cache_group );
		}

		return $value;
	}

	/**
	 * Returns the ID of the site whose options table holds network-scope records stored
	 * before the install was converted to multisite.
	 *
	 * That is the main network's main site. Other networks were created after the
	 * conversion, so they have no such records.
	 *
	 * @since 7.2.0
	 *
	 * @return int The site ID, or 0 if no records can be left from before a conversion.
	 */
	private function get_pre_conversion_site_id(): int {
		if ( ! is_multisite() || get_current_network_id() !== get_main_network_id() ) {
			return 0;
		}

		return get_main_site_id( get_main_network_id() );
	}

	/**
	 * Reads a network-scope record stored before the install was converted to multisite.
	 *
	 * @since 7.2.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $option_name The option name.
	 * @return mixed The stored value, null if there is none, or WP_Error if the read failed.
	 */
	private function read_pre_conversion_value( string $option_name ) {
		global $wpdb;

		$site_id = $this->get_pre_conversion_site_id();

		if ( ! $site_id ) {
			return null;
		}

		$result = $wpdb->query(
			$wpdb->prepare(
				'SELECT option_value FROM %i WHERE option_name = %s LIMIT 1',
				$wpdb->get_blog_prefix( $site_id ) . 'options',
				$option_name
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				WP_SECRETS_ERROR_STORE_UNAVAILABLE,
				__( 'The secret record could not be read.' )
			);
		}

		if ( ! $wpdb->num_rows ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reads the result of the prepared query above.
		$stored = $wpdb->get_var();

		return maybe_unserialize( $stored ?? '' );
	}

	/**
	 * Deletes a network-scope record stored before the install was converted to multisite.
	 *
	 * @since 7.2.0
	 *
	 * @param string $option_name The option name.
	 */
	private function delete_pre_conversion_value( string $option_name ): void {
		$site_id = $this->get_pre_conversion_site_id();

		if ( $site_id ) {
			delete_blog_option( $site_id, $option_name );
		}
	}

	/**
	 * Lists the option names of network-scope records stored before the install was
	 * converted to multisite.
	 *
	 * @since 7.2.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $pattern The LIKE pattern for network-scope option names.
	 * @return string[]|WP_Error
	 */
	private function list_pre_conversion_option_names( string $pattern ) {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				'SELECT option_name FROM %i WHERE option_name LIKE %s',
				$wpdb->get_blog_prefix( $this->get_pre_conversion_site_id() ) . 'options',
				$pattern
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				WP_SECRETS_ERROR_STORE_UNAVAILABLE,
				__( 'Could not list secrets.' )
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reads the result of the prepared query above.
		return $wpdb->get_col();
	}

	/**
	 * Builds the option name a secret is stored under.
	 *
	 * @since 7.2.0
	 *
	 * @param string $name    The secret's namespaced name.
	 * @param bool   $network Whether this is a network-scope secret.
	 * @return string
	 */
	private function option_name( string $name, bool $network ): string {
		return ( $network ? self::NETWORK_PREFIX : self::SITE_PREFIX ) . $name;
	}
}
