<?php
/**
 * Administration menu functions.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Sorts submenu items added by admin menu callbacks alphabetically.
 *
 * Items registered before the menu callbacks retain their order. New items are
 * grouped after them, separated by a divider, and sorted by their displayed title.
 *
 * @since 7.2.0
 *
 * @param array $submenu          The submenu items to sort.
 * @param array $original_submenu The submenu items registered before menu callbacks.
 */
function _wp_sort_submenu_items( &$submenu, $original_submenu ) {
	$core_late_submenu_slugs = array( 'theme-editor.php', 'plugin-editor.php' );

	foreach ( $submenu as $parent => $items ) {
		$core_items      = array();
		$extension_items = array();
		$position        = 0;

		foreach ( $items as $index => $item ) {
			$is_original_item  = isset( $original_submenu[ $parent ][ $index ][2] )
				&& isset( $item[2] )
				&& $original_submenu[ $parent ][ $index ][2] === $item[2];
			$is_late_core_item = isset( $item[2] ) && in_array( $item[2], $core_late_submenu_slugs, true );

			if ( $is_original_item || $is_late_core_item ) {
				$core_items[] = $item;
			} else {
				$extension_items[] = array(
					'position' => $position,
					'item'     => $item,
				);
			}

			++$position;
		}

		if ( empty( $extension_items ) ) {
			continue;
		}

		usort(
			$extension_items,
			static function ( $a, $b ) {
				$title_comparison = strnatcasecmp( wp_strip_all_tags( $a['item'][0] ), wp_strip_all_tags( $b['item'][0] ) );

				return 0 !== $title_comparison ? $title_comparison : $a['position'] <=> $b['position'];
			}
		);
		$extension_items = array_column( $extension_items, 'item' );

		if ( ! empty( $core_items ) ) {
			$separator          = array( '', '', 'wp-submenu-separator', '', 'wp-submenu-separator' );
			$submenu[ $parent ] = array_merge( $core_items, array( $separator ), $extension_items );
		} else {
			$submenu[ $parent ] = $extension_items;
		}
	}
}
