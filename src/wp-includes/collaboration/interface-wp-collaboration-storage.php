<?php
/**
 * WP_Collaboration_Storage interface
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Interface for collaboration storage backends used by the collaborative editing server.
 *
 * @since 7.0.0
 *
 * @phpstan-type AwarenessState array{client_id: int, state: array<string, mixed>, wp_user_id: int}
 */
interface WP_Collaboration_Storage {
	/**
	 * Adds a collaboration update to a given room.
	 *
	 * @since 7.0.0
	 *
	 * @param string $room   Room identifier.
	 * @param mixed  $update Serializable update, opaque to the storage implementation.
	 * @return bool True on success, false on failure.
	 */
	public function add_update( string $room, $update ): bool;

	/**
	 * Gets awareness state for a given room.
	 *
	 * Returns entries that have been updated within the timeout window.
	 *
	 * @since 7.0.0
	 *
	 * @param string $room    Room identifier.
	 * @param int    $timeout Seconds before an awareness entry is considered expired.
	 * @return array<int, array> Awareness entries.
	 * @phpstan-return array<int, AwarenessState>
	 */
	public function get_awareness_state( string $room, int $timeout = 30 ): array;

	/**
	 * Gets the current cursor for a given room. This should return a monotonically
	 * increasing integer that represents the last update that was returned for the
	 * room during the current request. This allows clients to retrieve updates
	 * after a specific cursor on subsequent requests.
	 *
	 * @since 7.0.0
	 *
	 * @param string $room Room identifier.
	 * @return int Current cursor for the room.
	 */
	public function get_cursor( string $room ): int;

	/**
	 * Gets the total number of stored updates for a given room.
	 *
	 * @since 7.0.0
	 *
	 * @param string $room Room identifier.
	 * @return int Total number of updates.
	 */
	public function get_update_count( string $room ): int;

	/**
	 * Retrieves updates from a room after the given cursor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $room   Room identifier.
	 * @param int    $cursor Return updates after this cursor.
	 * @return array<int, mixed> Updates.
	 */
	public function get_updates_after_cursor( string $room, int $cursor ): array;

	/**
	 * Removes updates from a room that are older than the provided cursor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $room   Room identifier.
	 * @param int    $cursor Remove updates with markers <= this cursor.
	 * @return bool True on success, false on failure.
	 */
	public function remove_updates_before_cursor( string $room, int $cursor ): bool;

	/**
	 * Sets awareness state for a given client in a room.
	 *
	 * @since 7.0.0
	 *
	 * @param string               $room       Room identifier.
	 * @param int                  $client_id  Client identifier.
	 * @param array<string, mixed> $state      Serializable awareness state for this client.
	 * @param int                  $wp_user_id WordPress user ID that owns this client.
	 * @return bool True on success, false on failure.
	 */
	public function set_awareness_state( string $room, int $client_id, array $state, int $wp_user_id ): bool;
}
