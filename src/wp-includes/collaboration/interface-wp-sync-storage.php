<?php
/**
 * WP_Sync_Storage interface
 *
 * @package WordPress
 */

interface WP_Sync_Storage {
	/**
	 * Initializes the storage mechanism.
	 *
	 * @since 6.8.0
	 */
	public function init(): void;

	/**
	 * Adds a sync update to a given room.
	 *
	 * @since 6.8.0
	 *
	 * @param string $room   Room identifier.
	 * @param array  $update Sync update.
	 */
	public function add_update( string $room, array $update ): void;

	/**
	 * Retrieves sync updates for a given room.
	 *
	 * @since 6.8.0
	 *
	 * @param string $room Room identifier.
	 * @return array Array of sync updates.
	 */
	public function get_all_updates( string $room ): array;

	/**
	 * Gets awareness state for a given room.
	 *
	 * @since 6.8.0
	 *
	 * @param string $room Room identifier.
	 * @return array Merged awareness state.
	 */
	public function get_awareness_state( string $room ): array;

	/**
	 * Removes all updates for a given room.
	 *
	 * @since 6.8.0
	 *
	 * @param string $room Room identifier.
	 */
	public function remove_all_updates( string $room ): void;

	/**
	 * Sets awareness state for a given room.
	 *
	 * @since 6.8.0
	 *
	 * @param string $room      Room identifier.
	 * @param array  $awareness Merged awareness state.
	 */
	public function set_awareness_state( string $room, array $awareness ): void;
}
