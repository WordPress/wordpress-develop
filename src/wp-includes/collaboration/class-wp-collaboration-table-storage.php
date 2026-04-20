<?php
/**
 * WP_Collaboration_Table_Storage class
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Core class that provides an interface for storing and retrieving
 * collaboration updates during a collaborative session.
 *
 * Update data is stored in the `collaboration` database table as an
 * append-only log. Awareness (presence) data is stored separately in
 * the `presence` table via the Presence API functions.
 *
 * This class intentionally fires no actions or filters. Collaboration
 * queries run on every poll (0.5–1 s per editor tab), so hook overhead
 * would degrade the real-time editing loop for all active sessions.
 *
 * @since 7.0.0
 *
 * @access private
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
				'client_id' => $update['client_id'] ?? '',
				'data'      => wp_json_encode( $update ),
				'date_gmt'  => gmdate( 'Y-m-d H:i:s' ),
				'user_id'   => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Gets awareness state for a given room.
	 *
	 * Delegates to the Presence API which uses a dedicated table with
	 * atomic upserts via UNIQUE KEY (room, client_id).
	 *
	 * @since 7.0.0
	 *
	 * @param string $room    Room identifier.
	 * @param int    $timeout Seconds before an awareness entry is considered expired.
	 * @return array<int, array> Awareness entries.
	 */
	public function get_awareness_state( string $room, int $timeout = 30 ): array {
		return wp_get_presence( $room, $timeout );
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
		 */

		/* Snapshot the current max ID and total row count in a single query. */
		$snapshot = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE( MAX( id ), 0 ) AS max_id, COUNT(*) AS total FROM {$wpdb->collaboration} WHERE room = %s",
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
				"SELECT data FROM {$wpdb->collaboration} WHERE room = %s AND id > %d AND id <= %d ORDER BY id ASC",
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

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->collaboration} WHERE room = %s AND id <= %d",
				$room,
				$cursor
			)
		);

		return false !== $result;
	}

	/**
	 * Sets awareness state for a given client in a room.
	 *
	 * Delegates to the Presence API which uses INSERT ... ON DUPLICATE KEY UPDATE
	 * for atomic upserts, eliminating race conditions.
	 *
	 * @since 7.0.0
	 *
	 * @param string               $room      Room identifier.
	 * @param string               $client_id Client identifier.
	 * @param array<string, mixed> $state     Serializable awareness state for this client.
	 * @param int                  $user_id   WordPress user ID that owns this client.
	 * @return bool True on success, false on failure.
	 */
	public function set_awareness_state( string $room, string $client_id, array $state, int $user_id ): bool {
		return wp_set_presence( $room, $client_id, $state, $user_id );
	}
}
