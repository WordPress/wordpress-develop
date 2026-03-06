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
 * Data is stored in the dedicated `collaboration` database table.
 *
 * @since 7.0.0
 *
 * @access private
 */
class WP_Collaboration_Table_Storage implements WP_Collaboration_Storage {
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

		$result = $wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'         => $room,
				'event_type'   => 'sync_update',
				'update_value' => wp_json_encode( $update ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Gets awareness state for a given room.
	 *
	 * Retrieves per-client awareness rows from the collaboration table,
	 * cleaning up expired entries inline.
	 *
	 * @since 7.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $room    Room identifier.
	 * @param int    $timeout Seconds before an awareness entry is considered expired.
	 * @return array<int, array{client_id: int, state: mixed, wp_user_id: int}> Awareness entries.
	 */
	public function get_awareness_state( string $room, int $timeout = 30 ): array {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $timeout );

		// Clean up expired awareness rows for this room.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->collaboration} WHERE room = %s AND event_type = 'awareness' AND created_at < %s",
				$room,
				$cutoff
			)
		);

		// Fetch active awareness rows.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT client_id, update_value FROM {$wpdb->collaboration} WHERE room = %s AND event_type = 'awareness' AND created_at >= %s",
				$room,
				$cutoff
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$entries = array();
		foreach ( $rows as $row ) {
			$decoded = json_decode( $row->update_value, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$entries[] = array(
					'client_id'  => (int) $row->client_id,
					'state'      => $decoded['state'],
					'wp_user_id' => $decoded['wp_user_id'],
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
	 * Uses a snapshot approach: captures MAX(id) first, then fetches rows
	 * WHERE id > cursor AND id <= max_id. Updates arriving after the snapshot
	 * are deferred to the next poll, never lost.
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

		// Snapshot the current max ID for sync_update rows in this room.
		$max_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE( MAX( id ), 0 ) FROM {$wpdb->collaboration} WHERE room = %s AND event_type = 'sync_update'",
				$room
			)
		);

		$this->room_cursors[ $room ] = $max_id;

		if ( 0 === $max_id || $max_id <= $cursor ) {
			$this->room_update_counts[ $room ] = 0;
			return array();
		}

		// Count total sync_update rows for this room (used by compaction threshold logic).
		$this->room_update_counts[ $room ] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->collaboration} WHERE room = %s AND event_type = 'sync_update' AND id <= %d",
				$room,
				$max_id
			)
		);

		// Fetch sync updates after the cursor up to the snapshot boundary.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT update_value FROM {$wpdb->collaboration} WHERE room = %s AND event_type = 'sync_update' AND id > %d AND id <= %d ORDER BY id ASC",
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
			$decoded = json_decode( $row->update_value, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$updates[] = $decoded;
			}
		}

		return $updates;
	}

	/**
	 * Removes updates from a room that are older than the given cursor.
	 *
	 * Uses a single atomic DELETE query, avoiding the race-prone
	 * "delete all, re-add some" pattern.
	 *
	 * @since 7.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $room   Room identifier.
	 * @param int    $cursor Remove updates with id < this cursor.
	 * @return bool True on success, false on failure.
	 */
	public function remove_updates_before_cursor( string $room, int $cursor ): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->collaboration} WHERE room = %s AND event_type = 'sync_update' AND id < %d",
				$room,
				$cursor
			)
		);

		return false !== $result;
	}

	/**
	 * Sets awareness state for a given client in a room.
	 *
	 * Uses INSERT … ON DUPLICATE KEY UPDATE so the row is never absent —
	 * it is either inserted or updated atomically. Each client writes only
	 * its own row, eliminating the race condition inherent in shared-state
	 * approaches.
	 *
	 * @since 7.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $room       Room identifier.
	 * @param int    $client_id  Client identifier.
	 * @param array  $state      Serializable awareness state for this client.
	 * @param int    $wp_user_id WordPress user ID that owns this client.
	 * @return bool True on success, false on failure.
	 */
	public function set_awareness_state( string $room, int $client_id, array $state, int $wp_user_id ): bool {
		global $wpdb;

		$update_value = wp_json_encode(
			array(
				'state'      => $state,
				'wp_user_id' => $wp_user_id,
			)
		);

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->collaboration} (room, event_type, client_id, update_value, created_at)
				VALUES (%s, 'awareness', %d, %s, %s)
				ON DUPLICATE KEY UPDATE update_value = VALUES(update_value), created_at = VALUES(created_at)",
				$room,
				$client_id,
				$update_value,
				current_time( 'mysql', true )
			)
		);

		return false !== $result;
	}
}
