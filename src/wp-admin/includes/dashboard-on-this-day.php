<?php
/**
 * WordPress Dashboard "On This Day" widget.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 7.1.0
 */

/**
 * Registers the On This Day dashboard widget.
 *
 * Designed to be the single entry point called from the dashboard setup
 * routine. The widget is always registered so that it remains available in
 * Screen Options and keeps its user-customized position. When there are no
 * matching posts, a marker class is added to the postbox so the widget can be
 * hidden with CSS.
 *
 * @since 7.1.0
 */
function wp_dashboard_on_this_day_setup() {
	add_filter( 'postbox_classes_dashboard_wp_dashboard_on_this_day', 'wp_dashboard_on_this_day_postbox_classes' );

	wp_add_dashboard_widget(
		'wp_dashboard_on_this_day',
		__( 'On This Day' ),
		'wp_dashboard_on_this_day'
	);
}

/**
 * Hides the On This Day postbox when there are no posts to show.
 *
 * Adds the core `hidden` class so the widget stays registered — preserving its
 * Screen Options entry and user-customized position — while being hidden when
 * empty. A user can still reveal it via Screen Options, in which case the
 * placeholder message is shown.
 *
 * @since 7.1.0
 *
 * @param string[] $classes An array of postbox classes.
 * @return string[] Filtered postbox classes.
 */
function wp_dashboard_on_this_day_postbox_classes( $classes ) {
	if ( empty( wp_dashboard_on_this_day_get_posts() ) ) {
		$classes[] = 'hidden';
	}

	return $classes;
}

/**
 * Renders the On This Day dashboard widget.
 *
 * Outputs the matching posts grouped by publication year, newest year first.
 *
 * @since 7.1.0
 */
function wp_dashboard_on_this_day() {
	$posts_by_year = wp_dashboard_on_this_day_get_posts();

	if ( empty( $posts_by_year ) ) {
		// Placeholder shown when a user reveals the hidden widget via Screen
		// Options on a day with no matching posts.
		echo '<p>' . esc_html__( 'No posts were published on this day in previous years.' ) . '</p>';
		return;
	}

	$post_count = 0;

	foreach ( $posts_by_year as $year_data ) {
		$post_count += $year_data['total'];
	}

	/* translators: Date format for the On This Day widget date, without year. See https://www.php.net/manual/datetime.format.php */
	$date = '<strong>' . esc_html( wp_date( _x( 'F jS', 'on this day date format' ) ) ) . '</strong>';
	?>
	<div class="wp-on-this-day-widget">
		<p>
			<?php
			if ( 1 === $post_count ) {
				printf(
					/* translators: %s: Date, without year. */
					esc_html__( 'One post has been published on %s:' ),
					$date // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Date is escaped above.
				);
			} else {
				printf(
					esc_html(
						/* translators: 1: Number of posts, 2: Date, without year. */
						_n(
							'%1$s post has been published on %2$s:',
							'%1$s posts have been published on %2$s:',
							$post_count
						)
					),
					esc_html( number_format_i18n( $post_count ) ),
					$date // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Date is escaped above.
				);
			}
			?>
		</p>
		<ul>
			<?php foreach ( $posts_by_year as $year => $year_data ) : ?>
				<li>
					<h3><?php echo esc_html( $year ); ?></h3>
					<ul>
						<?php foreach ( $year_data['posts'] as $year_post ) : ?>
							<?php
							$title = get_the_title( $year_post );

							if ( '' === trim( $title ) ) {
								$title = __( '(no title)' );
							}

							$author_id   = (int) $year_post->post_author;
							$author_name = $author_id > 0 ? (string) get_the_author_meta( 'display_name', $author_id ) : '';
							$show_author = '' !== trim( $author_name ) && get_current_user_id() !== $author_id;
							?>
							<li>
								<a href="<?php echo esc_url( get_permalink( $year_post ) ); ?>"><?php echo esc_html( $title ); ?></a>
								<?php if ( $show_author ) : ?>
									<?php
									echo '<span class="wp-on-this-day-post-author">' . esc_html(
										sprintf(
											/* translators: %s: Post author's display name. */
											__( 'by %s' ),
											$author_name
										)
									) . '</span>';
									?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
						<?php
						$remaining = $year_data['total'] - count( $year_data['posts'] );

						if ( $remaining > 0 ) :
							$remaining_text = sprintf(
								/* translators: %s: Number of additional posts published in that year. */
								_n( '%s more post', '%s more posts', $remaining ),
								number_format_i18n( $remaining )
							);
							?>
							<li class="wp-on-this-day-more">
								<?php if ( '' !== $year_data['archive_link'] ) : ?>
									<a href="<?php echo esc_url( $year_data['archive_link'] ); ?>"><?php echo esc_html( $remaining_text ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $remaining_text ); ?>
								<?php endif; ?>
							</li>
						<?php endif; ?>
					</ul>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

/**
 * Retrieves published posts from all authors that were published on this
 * calendar day in previous years, grouped by publication year.
 *
 * The date constraint matches today's month and day, combined with a `before`
 * clause anchored to January 1 of the current year. Rather than capping the
 * widget as a whole — which lets recent, prolific years crowd out older ones
 * entirely — a small number of posts is kept for each year that has any,
 * newest year first.
 *
 * A single query reads the ID and publication date of every matching post,
 * which is what allows the per-year totals to be accurate even when only a
 * few posts from a year are displayed. Only the posts that will be displayed
 * are then loaded in full. Use the `wp_dashboard_on_this_day_query_args`
 * filter to change the post types, statuses, or per-year limit.
 *
 * @since 7.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return array[] Array of per-year data keyed by four-digit year, newest year
 *                 first. Each entry is an array with the following keys:
 *
 *     @type WP_Post[] $posts        The posts to display, newest first.
 *     @type int       $total        Total number of matching posts that year,
 *                                   including those not in `$posts`.
 *     @type string    $archive_link URL of the day archive listing every post
 *                                   from that day, or an empty string when no
 *                                   single day archive covers the year's posts.
 */
function wp_dashboard_on_this_day_get_posts() {
	global $wpdb;

	$today = current_datetime();

	$args = array(
		'post_type'      => 'post',
		'post_status'    => array( 'publish' ),
		'posts_per_year' => 5,
	);

	/**
	 * Filters the arguments used to gather posts for the On This Day dashboard widget.
	 *
	 * @since 7.1.0
	 *
	 * @param array $args {
	 *     Arguments for the widget's query.
	 *
	 *     @type string|string[] $post_type      Post type or types to include. Default 'post'.
	 *     @type string|string[] $post_status    Post status or statuses to include. Default 'publish'.
	 *     @type int             $posts_per_year Maximum posts to display for each year. Any
	 *                                           further posts from that year are summarized
	 *                                           with a link to the day archive. Default 5.
	 * }
	 */
	$args = apply_filters( 'wp_dashboard_on_this_day_query_args', $args );

	$post_types     = array_filter( array_map( 'strval', (array) ( $args['post_type'] ?? 'post' ) ) );
	$post_statuses  = array_filter( array_map( 'strval', (array) ( $args['post_status'] ?? 'publish' ) ) );
	$posts_per_year = (int) ( $args['posts_per_year'] ?? 5 );

	if ( empty( $post_types ) || empty( $post_statuses ) || $posts_per_year < 1 ) {
		return array();
	}

	$clause = _wp_dashboard_on_this_day_date_query_clause( $today );

	$date_query = new WP_Date_Query(
		array(
			'relation' => 'AND',
			array(
				'before' => array( 'year' => (int) $today->format( 'Y' ) ),
			),
			$clause,
		),
		"{$wpdb->posts}.post_date"
	);

	$sql = $wpdb->prepare(
		"SELECT ID, post_date FROM {$wpdb->posts}
		WHERE post_type IN (" . implode( ',', array_fill( 0, count( $post_types ), '%s' ) ) . ')
		AND post_status IN (' . implode( ',', array_fill( 0, count( $post_statuses ), '%s' ) ) . ')',
		array_merge( $post_types, $post_statuses )
	) . $date_query->get_sql() . " ORDER BY {$wpdb->posts}.post_date DESC";

	// A merged leap day clause spans two calendar days, so no single day archive covers it.
	$archive_month = isset( $clause['relation'] ) ? 0 : (int) $today->format( 'm' );
	$archive_day   = isset( $clause['relation'] ) ? 0 : (int) $today->format( 'd' );

	$last_changed  = wp_cache_get_last_changed( 'posts' );
	$cache_key     = 'on_this_day:' . md5( "$sql|$posts_per_year" ) . ":$last_changed";
	$posts_by_year = wp_cache_get( $cache_key, 'post-queries' );

	if ( false === $posts_by_year ) {
		$posts_by_year = array();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Both clauses above are prepared.
		$rows = $wpdb->get_results( $sql );

		// Rows arrive newest first, so each year is filled in display order.
		foreach ( $rows as $row ) {
			$year = (int) substr( $row->post_date, 0, 4 );

			if ( ! isset( $posts_by_year[ $year ] ) ) {
				$posts_by_year[ $year ] = array(
					'post_ids'     => array(),
					'total'        => 0,
					'archive_link' => $archive_month > 0 ? get_day_link( $year, $archive_month, $archive_day ) : '',
				);
			}

			++$posts_by_year[ $year ]['total'];

			if ( count( $posts_by_year[ $year ]['post_ids'] ) < $posts_per_year ) {
				$posts_by_year[ $year ]['post_ids'][] = (int) $row->ID;
			}
		}

		wp_cache_set( $cache_key, $posts_by_year, 'post-queries' );
	}

	if ( empty( $posts_by_year ) ) {
		return array();
	}

	$post_ids = array_merge( ...array_column( $posts_by_year, 'post_ids' ) );

	_prime_post_caches( $post_ids, false, false );

	foreach ( $posts_by_year as $year => $year_data ) {
		$posts = array_values( array_filter( array_map( 'get_post', $year_data['post_ids'] ) ) );

		unset( $posts_by_year[ $year ]['post_ids'] );

		$posts_by_year[ $year ]['posts'] = $posts;
	}

	return $posts_by_year;
}

/**
 * Builds the date query clause for today's anniversary date.
 *
 * On February 28 in a non-leap year, February 29 posts are included so
 * leap-day anniversaries still appear.
 *
 * @since 7.1.0
 * @access private
 *
 * @param DateTimeInterface $date Date to build the clause for.
 * @return array Date query clause.
 */
function _wp_dashboard_on_this_day_date_query_clause( $date ) {
	$month  = (int) $date->format( 'm' );
	$day    = (int) $date->format( 'd' );
	$clause = array(
		'month' => $month,
		'day'   => $day,
	);

	// Display leap day posts on Feb 28 in non leap years.
	if (
		28 === $day
		&& 2 === $month
		&& false === (bool) $date->format( 'L' )
	) {
		$clause = array(
			'relation' => 'OR',
			$clause,
			array(
				'month' => 2,
				'day'   => 29,
			),
		);
	}

	return $clause;
}
