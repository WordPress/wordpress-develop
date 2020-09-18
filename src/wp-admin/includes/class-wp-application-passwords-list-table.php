<?php
/**
 * List Table API: WP_Application_Passwords_List_Table class
 *
 * @package WordPress
 * @subpackage Administration
 * @since ?.?.0
 */

/**
 * Class for displaying the list of application password items.
 *
 * @since ?.?.0
 * @access private
 *
 * @see WP_List_Table
 */
class WP_Application_Passwords_List_Table extends WP_List_Table {

	/**
	 * Get a list of columns.
	 *
	 * @since ?.?.0
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'name'      => wp_strip_all_tags( __( 'Name' ) ),
			'created'   => wp_strip_all_tags( __( 'Created' ) ),
			'last_used' => wp_strip_all_tags( __( 'Last Used' ) ),
			'last_ip'   => wp_strip_all_tags( __( 'Last IP' ) ),
			'revoke'    => wp_strip_all_tags( __( 'Revoke' ) ),
		);
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @since ?.?.0
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();
		$primary  = 'name';
		$this->_column_headers = array( $columns, $hidden, $sortable, $primary );
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @since ?.?.0
	 * @access protected
	 *
	 * @param object $item The current item.
	 * @param string $column_name The current column name.
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'name':
				return esc_html( $item['name'] );
			case 'created':
				if ( empty( $item['created'] ) ) {
					return '&mdash;';
				}
				return date( get_option( 'date_format', 'r' ), $item['created'] );
			case 'last_used':
				if ( empty( $item['last_used'] ) ) {
					return '&mdash;';
				}
				return date( get_option( 'date_format', 'r' ), $item['last_used'] );
			case 'last_ip':
				if ( empty( $item['last_ip'] ) ) {
					return '&mdash;';
				}
				return $item['last_ip'];
			case 'revoke':
				return get_submit_button( __( 'Revoke' ), 'delete', 'revoke-application-password', false );
			default:
				return '';
		}
	}

	/**
	 * Generates custom table navigation to prevent conflicting nonces.
	 *
	 * @since ?.?.0
	 * @access protected
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 */
	protected function display_tablenav( $which ) {
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">

			<?php if ( 'bottom' === $which ) : ?>
			<div class="alignright">
				<?php submit_button( __( 'Revoke all application passwords' ), 'delete', 'revoke-all-application-passwords', false ); ?>
			</div>
			<?php endif; ?>

			<div class="alignleft actions bulkactions">
				<?php $this->bulk_actions( $which ); ?>
			</div>
			<?php
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>

			<br class="clear" />
		</div>
		<?php
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @since ?.?.0
	 *
	 * @param object $item The current item.
	 */
	public function single_row( $item ) {
		echo '<tr data-slug="' . esc_attr( WP_Application_Passwords::password_unique_slug( $item ) ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}
}
