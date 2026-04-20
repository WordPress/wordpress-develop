<?php
/**
 * WP_Collaboration_Table_Storage class
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Core class that provides an interface for storing and retrieving
 * updates and awareness data during a collaborative session.
 *
 * All data is stored in the single `collaboration` database table,
 * discriminated by the `type` column. Awareness reads are served from
 * the persistent object cache when available, falling back to the
 * database — similar to the transient pattern but without wp_options.
 *
 * This class intentionally fires no actions or filters. Collaboration
 * queries run on every poll (0.5–1 s per editor tab), so hook overhead
 * would degrade the real-time editing loop for all active sessions.
 *
 * @since 7.0.0
 *
 * @access private
 *
 * @phpstan-type AwarenessState array{client_id: string, state: array<string, mixed>, user_id: int}
 */
class WP_Collaboration_Table_Storage {
	/**
	 * Cache of cursors by room.
	 *
	 * @since 7.0.0
	 * @var array<string, int>
	 */
	private array $room_cursors = array();

	/**
	 * Cache of update counts by room.
	 *
	 * @since 7.0.0
	 * @var array<string, int>
	 */
	private array $room_update_counts = array();

	/**
	 * Adds an update to a given room.
	 *
	 * @since 7.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $room   Room identifier.
	 * @param mixed  $update Update data.
	 * @return bool True on success, false on failure.
	 */
	public function add_update( string $room, $update ): bool {
		global $wpdb;

		if ( '' === $room || empty( $update['type'] ) || empty( $update['client_id'] ) ) {
			return false;
		}

		$result = $wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'      => $room,
				'type'      => $update['type'] ?? '',
				'client_id' => $update['client_id'] ?? '',
				'data'      => wp_json_encode( $update ),
				'date_gmt'  => gmdate( 'Y-m-d H:i:s' ),
				'user_id'   => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Gets awareness state for a given room.
	 *
	 * Checks the persistent object cache first. On a cache miss, queries
	 * the collaboration table for awareness rows and primes the cache
	 * with the result. When no persistent cache is available the in-memory
	 * WP_Object_Cache is used, which provides no cross-request benefit
	 * but keeps the code path identical.
	 *
	 * Expired rows are filtered by the WHERE clause on cache miss;
	 * actual deletion is handled by cron via
	 * wp_delete_old_collaboration_data().
	 *
	 * @since 7.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $room    Room identifier.
	 * @param int    $timeout Seconds before an awareness entry is considered expired.
	 * @return array<int, array> Awareness entries.
	 * @phpstan-return list<AwarenessState>
	 */
	public function get_awareness_state( string $room, int $timeout = 30 ): array {
		global $wpdb;

		$cutoff_timestamp = time() - $timeout;
		$cutoff_mysql     = gmdate( 'Y-m-d H:i:s', $cutoff_timestamp );

		if ( wp_using_ext_object_cache() ) {
			$cache_key = 'awareness::' . str_replace( '/', ':', $room );
			$cached    = wp_cache_get( $cache_key, 'collaboration' );

			if ( false === $cached || ! is_string( $cached ) ) {
				// Room not set/corrupted.
				return array();
			}

			$cached = json_decode( $cached, true );

			// Deterministic ordering.
			$cached_awareness = wp_list_sort( $cached, 'client_id' );

			// Remove out of date entries.
			foreach ( $cached_awareness as $index => $client_awareness ) {
				if ( empty( $client_awareness['timestamp'] ) || $client_awareness['timestamp'] < $cutoff_timestamp ) {
					unset( $cached_awareness[ $index ] );
				}
			}

			return array_values( $cached_awareness );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT client_id, user_id, data FROM {$wpdb->collaboration} WHERE room = %s AND type = 'awareness' AND date_gmt >= %s",
				$room,
				$cutoff_mysql
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$entries = array();
		foreach ( $rows as $row ) {
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
	 * Gets the current cursor for a given room.
	 *
	 * The cursor is set during get_updates_after_cursor() and represents the
	 * maximum row ID at the time updates were retrieved.
	 *
	 * @since 7.0.0
	 *
	 * @param string $room Room identifier.
	 * @return int Current cursor for the room.
	 */
	public function get_cursor( string $room ): int {
		return $this->room_cursors[ $room ] ?? 0;
	}

	/**
	 * Gets the number of updates stored for a given room.
	 *
	 * @since 7.0.0
	 *
	 * @param string $room Room identifier.
	 * @return int Number of updates stored for the room.
	 */
	public function get_update_count( string $room ): int {
		return $this->room_update_counts[ $room ] ?? 0;
	}

	/**
	 * Retrieves updates from a room after a given cursor.
	 *
	 * @since 7.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $room   Room identifier.
	 * @param int    $cursor Return updates after this cursor.
	 * @return array<int, mixed> Updates.
	 */
	public function get_updates_after_cursor( string $room, int $cursor ): array {
		global $wpdb;

		/*
		 * Uses a snapshot approach: captures MAX(id) and COUNT(*) in a single
		 * query, then fetches rows WHERE id > cursor AND id <= max_id. Updates
		 * arriving after the snapshot are deferred to the next poll, never lost.
		 *
		 * Only retrieves non-awareness rows — awareness rows are handled
		 * separately via get_awareness_state().
		 */

		/* Snapshot the current max ID and total row count in a single query. */
		$snapshot = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE( MAX( id ), 0 ) AS max_id, COUNT(*) AS total FROM {$wpdb->collaboration} WHERE room = %s AND type != 'awareness'",
				$room
			)
		);

		if ( ! $snapshot ) {
			$this->room_cursors[ $room ]       = 0;
			$this->room_update_counts[ $room ] = 0;
			return array();
		}

		$max_id = (int) $snapshot->max_id;
		$total  = (int) $snapshot->total;

		$this->room_cursors[ $room ] = $max_id;

		if ( 0 === $max_id || $max_id <= $cursor ) {
			/*
			 * Preserve the real row count so the server can still
			 * trigger compaction when updates have accumulated but
			 * no new ones arrived since the client's last poll.
			 */
			$this->room_update_counts[ $room ] = $total;
			return array();
		}

		$this->room_update_counts[ $room ] = $total;

		/* Fetch updates after the cursor up to the snapshot boundary. */
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT data FROM {$wpdb->collaboration} WHERE room = %s AND type != 'awareness' AND id > %d AND id <= %d ORDER BY id ASC",
				$room,
				$cursor,
				$max_id
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$updates = array();
		foreach ( $rows as $row ) {
			$decoded = json_decode( $row->data, true );
			if ( is_array( $decoded ) ) {
				$updates[] = $decoded;
			}
		}

		return $updates;
	}

	/**
	 * Removes updates from a room up to and including the given cursor.
	 *
	 * @since 7.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $room   Room identifier.
	 * @param int    $cursor Remove updates up to and including this cursor.
	 * @return bool True on success, false on failure.
	 */
	public function remove_updates_through_cursor( string $room, int $cursor ): bool {
		global $wpdb;

		// Uses a single atomic DELETE query, avoiding the race-prone
		// "delete all, re-add some" pattern.
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->collaboration} WHERE room = %s AND type != 'awareness' AND id <= %d",
				$room,
				$cursor
			)
		);

		return false !== $result;
	}

	/**
	 * Sets awareness state for a given client in a room.
	 *
	 * Uses SELECT-then-UPDATE/INSERT: checks for an existing row by
	 * primary key, then updates or inserts accordingly. Each client
	 * writes only its own row, eliminating the race condition inherent
	 * in shared-state approaches.
	 *
	 * After writing, the cached awareness entries for the room are updated
	 * in-place so that subsequent get_awareness_state() calls from other
	 * clients hit the cache instead of the database. This is application-
	 * level deduplication: the shared collaboration table cannot carry a
	 * UNIQUE KEY on (room, client_id) because sync rows need multiple
	 * entries per room+client pair.
	 *
	 * @since 7.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string               $room      Room identifier.
	 * @param string               $client_id Client identifier.
	 * @param array<string, mixed> $state     Serializable awareness state for this client.
	 * @param int                  $user_id   WordPress user ID that owns this client.
	 * @return bool True on success, false on failure.
	 */
	public function set_awareness_state( string $room, string $client_id, array $state, int $user_id ): bool {
		global $wpdb;

		if ( '' === $room || '' === $client_id ) {
			return false;
		}

		wp_recursive_ksort( $state );

		/*
		 * Bucket the timestamp to 5-second intervals so most polls
		 * short-circuit without a database write. Ceil is used instead
		 * of floor to prevent the awareness timeout from being hit early.
		 */
		$now_timestamp = (int) ceil( time() / 5 ) * 5;
		$now_mysql     = gmdate( 'Y-m-d H:i:s', $now_timestamp );

		if ( wp_using_ext_object_cache() ) {
			$cache_key = 'awareness::' . str_replace( '/', ':', $room );
			$awareness = $this->get_awareness_state( $room );

			foreach ( $awareness as $index => $client_awareness ) {
				if ( $client_awareness['client_id'] === $client_id && $client_awareness['timestamp'] === $now_timestamp ) {
					// Cache already has the current client state, consider update a success (avoids cache thrashing).
					return true;
				}

				if ( $client_awareness['client_id'] === $client_id ) {
					// Remove stale cache entry for the current client, if it exists.
					unset( $awareness[ $index ] );
					break;
				}
			}

			$client_awareness = array(
				'client_id' => $client_id,
				'state'     => $state,
				'user_id'   => $user_id,
				'timestamp' => $now_timestamp,
			);

			$awareness[] = $client_awareness;

			// Sort awareness entries by client_id.
			$awareness = wp_list_sort( $awareness, 'client_id' );
			wp_cache_set( $cache_key, wp_json_encode( $awareness ), 'collaboration', HOUR_IN_SECONDS );

			return true;
		}

		$data = wp_json_encode( $state );

		/* Check if a row already exists. */
		$exists = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, date_gmt FROM {$wpdb->collaboration} WHERE room = %s AND type = 'awareness' AND client_id = %s LIMIT 1",
				$room,
				$client_id
			)
		);

		if ( $exists && $exists->date_gmt === $now_mysql ) {
			// Row already has the current date, consider update a success.
			return true;
		}

		if ( $exists ) {
			$result = $wpdb->update(
				$wpdb->collaboration,
				array(
					'user_id'  => $user_id,
					'data'     => $data,
					'date_gmt' => $now_mysql,
				),
				array( 'id' => $exists->id )
			);
		} else {
			$result = $wpdb->insert(
				$wpdb->collaboration,
				array(
					'room'      => $room,
					'type'      => 'awareness',
					'client_id' => $client_id,
					'user_id'   => $user_id,
					'data'      => $data,
					'date_gmt'  => $now_mysql,
				)
			);
		}

		if ( false === $result ) {
			return false;
		}

		return true;
	}
}
