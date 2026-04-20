<?php
/**
 * Presence API functions.
 *
 * Provides system-wide presence and awareness for WordPress using a
 * dedicated database table with atomic upserts.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Gets all present clients in a room, filtered by TTL.
 *
 * @since 7.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $room    The room identifier.
 * @param int    $timeout Optional. Timeout in seconds. Default 30.
 * @return array Array of presence entries.
 */
function wp_get_presence( $room, $timeout = 30 ) {
	global $wpdb;

	$cutoff = gmdate( 'Y-m-d H:i:s', time() - $timeout );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT client_id, user_id, data, date_gmt FROM {$wpdb->presence} WHERE room = %s AND date_gmt > %s ORDER BY client_id ASC",
			$room,
			$cutoff
		)
	);

	if ( ! $results ) {
		return array();
	}

	$entries = array();
	foreach ( $results as $row ) {
		$decoded = json_decode( $row->data, true );
		if ( is_array( $decoded ) ) {
			$entries[] = array(
				'client_id' => $row->client_id,
				'state'     => $decoded,
				'user_id'   => (int) $row->user_id,
			);
		}
	}

	return $entries;
}

/**
 * Upserts a client's presence state in a room.
 *
 * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic upserts
 * via the UNIQUE KEY (room, client_id). This eliminates race
 * conditions inherent in read-modify-write patterns.
 *
 * @since 7.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $room      The room identifier.
 * @param string $client_id The client identifier.
 * @param array  $state     The presence state data.
 * @param int    $user_id   Optional. The user ID. Default 0.
 * @return bool True on success, false on failure.
 */
function wp_set_presence( $room, $client_id, $state, $user_id = 0 ) {
	global $wpdb;

	if ( '' === $room || '' === $client_id ) {
		return false;
	}

	$data_json = wp_json_encode( $state );
	$now       = gmdate( 'Y-m-d H:i:s' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$result = $wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$wpdb->presence} (room, client_id, user_id, data, date_gmt)
			VALUES (%s, %s, %d, %s, %s)
			ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), data = VALUES(data), date_gmt = VALUES(date_gmt)",
			$room,
			$client_id,
			$user_id,
			$data_json,
			$now
		)
	);

	return false !== $result;
}

/**
 * Removes a client from a room.
 *
 * @since 7.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $room      The room identifier.
 * @param string $client_id The client identifier.
 * @return bool True on success, false on failure.
 */
function wp_remove_presence( $room, $client_id ) {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$result = $wpdb->delete(
		$wpdb->presence,
		array(
			'room'      => $room,
			'client_id' => $client_id,
		),
		array( '%s', '%s' )
	);

	return false !== $result;
}

/**
 * Removes all presence entries for a given user across all rooms.
 *
 * @since 7.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $user_id The user ID.
 * @return bool True on success, false on failure.
 */
function wp_remove_user_presence( $user_id ) {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$result = $wpdb->delete(
		$wpdb->presence,
		array( 'user_id' => $user_id ),
		array( '%d' )
	);

	return false !== $result;
}

/**
 * Deletes stale presence entries older than the given timeout.
 *
 * @since 7.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $timeout Optional. Timeout in seconds. Default 60.
 */
function wp_delete_expired_presence_data( $timeout = 60 ) {
	global $wpdb;

	$cutoff = gmdate( 'Y-m-d H:i:s', time() - $timeout );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->presence} WHERE date_gmt < %s",
			$cutoff
		)
	);
}
