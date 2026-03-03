<?php
/**
 * WP_Sync_Table_Storage class
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Core class that provides an interface for storing and retrieving sync
 * updates and awareness data during a collaborative session.
 *
 * Data is stored in the dedicated `sync_updates` database table.
 *
 * @since 7.0.0
 *
 * @access private
 */
class WP_Sync_Table_Storage implements WP_Sync_Storage {
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
	 * Adds a sync update to a given room.
	 *
	 * @since 7.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $room   Room identifier.
	 * @param mixed  $update Sync update.
	 * @return bool True on success, false on failure.
	 */
	public function add_update( string $room, $update ): bool {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->sync_updates,
			array(
				'room'       => $room,
				'client_id'  => $update['client_id'],
				'type'       => $update['type'],
				'data'       => $update['data'],
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Gets awareness state for a given room.
	 *
	 * Awareness is ephemeral and stored as a transient rather than
	 * in the sync_updates table.
	 *
	 * @since 7.0.0
	 *
	 * @param string $room Room identifier.
	 * @return array<int, mixed> Awareness state.
	 */
	public function get_awareness_state( string $room ): array {
		$awareness = get_transient( $this->get_awareness_transient_key( $room ) );

		if ( ! is_array( $awareness ) ) {
			return array();
		}

		return array_values( $awareness );
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
	 * Retrieves sync updates from a room after a given cursor.
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
	 * @return array<int, mixed> Sync updates.
	 */
	public function get_updates_after_cursor( string $room, int $cursor ): array {
		global $wpdb;

		// Snapshot the current max ID for this room to define a stable upper bound.
		$max_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE( MAX( id ), 0 ) FROM {$wpdb->sync_updates} WHERE room = %s",
				$room
			)
		);

		$this->room_cursors[ $room ] = $max_id;

		if ( 0 === $max_id || $max_id <= $cursor ) {
			$this->room_update_counts[ $room ] = 0;
			return array();
		}

		// Count total updates for this room (used by compaction threshold logic).
		// Bounded by max_id to stay consistent with the snapshot window above.
		$this->room_update_counts[ $room ] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->sync_updates} WHERE room = %s AND id <= %d",
				$room,
				$max_id
			)
		);

		// Fetch updates after the cursor up to the snapshot boundary.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT client_id, type, data FROM {$wpdb->sync_updates} WHERE room = %s AND id > %d AND id <= %d ORDER BY id ASC",
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
			$updates[] = array(
				'client_id' => (int) $row->client_id,
				'type'      => $row->type,
				'data'      => $row->data,
			);
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
				"DELETE FROM {$wpdb->sync_updates} WHERE room = %s AND id < %d",
				$room,
				$cursor
			)
		);

		return false !== $result;
	}

	/**
	 * Returns the transient key used to store awareness state for a room.
	 *
	 * The room name is hashed with md5 to guarantee the key stays within
	 * the 172-character limit imposed by the wp_options option_name column
	 * (varchar 191 minus the 19-character `_transient_timeout_` prefix).
	 *
	 * @since 7.0.0
	 *
	 * @param string $room Room identifier.
	 * @return string Transient key.
	 */
	private function get_awareness_transient_key( string $room ): string {
		return 'sync_awareness_' . md5( $room );
	}

	/**
	 * Sets awareness state for a given room.
	 *
	 * Awareness is ephemeral and stored as a transient with a short timeout.
	 *
	 * @since 7.0.0
	 *
	 * @param string            $room      Room identifier.
	 * @param array<int, mixed> $awareness Serializable awareness state.
	 * @return bool True on success, false on failure.
	 */
	public function set_awareness_state( string $room, array $awareness ): bool {
		// Awareness is high-frequency, short-lived data (cursor positions, selections)
		// that doesn't need cursor-based history. Transients avoid row churn in the table.
		return set_transient( $this->get_awareness_transient_key( $room ), $awareness, MINUTE_IN_SECONDS );
	}
}
