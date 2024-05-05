<?php

class TracTickets {
	/**
	 * When open tickets for a Trac installation is requested, the results are stored here.
	 *
	 * @var array
	 */
	protected static $trac_ticket_cache = array();

	/**
	 * Checks if Trac ticket #$ticket_id is resolved.
	 *
	 * @return bool|null True if the ticket is resolved, false if not resolved, null on error.
	 */
	public static function isTracTicketClosed( $trac_url, $ticket_id ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! extension_loaded( 'openssl' ) ) {
			$trac_url = preg_replace( '/^https:/', 'http:', $trac_url );
		}

		if ( ! isset( self::$trac_ticket_cache[ $trac_url ] ) ) {
			// In case you're running the tests offline, keep track of open tickets.
			$file    = DIR_TESTDATA . '/.trac-ticket-cache.' . str_replace( array( 'http://', 'https://', '/' ), array( '', '', '-' ), rtrim( $trac_url, '/' ) );
			$tickets = @file_get_contents( $trac_url . '/query?status=%21closed&format=csv&col=id' );

			// Check if our HTTP request failed.
			if ( false === $tickets ) {
				if ( file_exists( $file ) ) {
					register_shutdown_function( array( 'TracTickets', 'usingLocalCache' ) );
					$tickets = file_get_contents( $file );
				} else {
					register_shutdown_function( array( 'TracTickets', 'forcingKnownBugs' ) );
					self::$trac_ticket_cache[ $trac_url ] = array();
					return true; // Assume the ticket is closed, which means it gets run.
				}
			} else {
				$tickets = substr( $tickets, 2 ); // Remove 'id' column header.
				$tickets = trim( $tickets );
				file_put_contents( $file, $tickets );
			}

			$tickets = explode( "\r\n", $tickets );

			self::$trac_ticket_cache[ $trac_url ] = $tickets;
		}

		return ! in_array( $ticket_id, self::$trac_ticket_cache[ $trac_url ], true );
	}

	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public static function usingLocalCache() {
		echo PHP_EOL . "\x1b[0m\x1b[30;43m\x1b[2K";
		echo 'Info: Trac was inaccessible, so a local ticket status cache was used.' . PHP_EOL;
		echo "\x1b[0m\x1b[2K";
	}

	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public static function forcingKnownBugs() {
		echo PHP_EOL . "\x1b[0m\x1b[37;41m\x1b[2K";
		echo "Error: Trac was inaccessible, so known bugs weren't able to be skipped." . PHP_EOL;
		echo "\x1b[0m\x1b[2K";
	}
}
