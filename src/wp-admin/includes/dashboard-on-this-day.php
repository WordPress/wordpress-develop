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
	$query      = _wp_dashboard_on_this_day_get_posts_query();
	$posts      = $query->posts;
	$post_count = (int) $query->found_posts;

	if ( empty( $posts ) ) {
		// Placeholder shown when a user reveals the hidden widget via Screen
		// Options on a day with no matching posts.
		echo '<p>' . esc_html__( 'No posts were published on this day in previous years.' ) . '</p>';
		return;
	}

	$displayed_post_count = count( $posts );

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
		<ul id="wp-on-this-day-posts" class="wp-on-this-day-posts">
			<?php _wp_dashboard_on_this_day_render_posts( $posts ); ?>
		</ul>
		<?php if ( $post_count > $displayed_post_count ) : ?>
			<p class="wp-on-this-day-load-more">
				<button
					type="button"
					class="button-link"
					data-wp-on-this-day-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_dashboard_on_this_day_load_more' ) ); ?>"
					data-wp-on-this-day-offset="<?php echo esc_attr( $displayed_post_count ); ?>"
					aria-controls="wp-on-this-day-posts"
				>
					<?php
					printf(
						esc_html(
							/* translators: %s: Number of additional posts. */
							_n( 'Show %s more post', 'Show %s more posts', $post_count - $displayed_post_count )
						),
						esc_html( number_format_i18n( $post_count - $displayed_post_count ) )
					);
					?>
				</button>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renders On This Day posts grouped by publication year.
 *
 * @since 7.1.0
 * @access private
 *
 * @param WP_Post[] $posts Posts to render.
 */
function _wp_dashboard_on_this_day_render_posts( $posts ) {
	$posts_by_year = array();

	foreach ( $posts as $post ) {
		$year = get_the_date( 'Y', $post );

		if ( ! isset( $posts_by_year[ $year ] ) ) {
			$posts_by_year[ $year ] = array();
		}

		$posts_by_year[ $year ][] = $post;
	}

	foreach ( $posts_by_year as $year => $year_posts ) {
		?>
		<li class="wp-on-this-day-year" data-wp-on-this-day-year="<?php echo esc_attr( $year ); ?>">
			<h3><?php echo esc_html( $year ); ?></h3>
			<ul class="wp-on-this-day-year-posts">
				<?php foreach ( $year_posts as $year_post ) : ?>
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
			</ul>
		</li>
		<?php
	}
}

/**
 * Retrieves published posts from all authors that were published on this
 * calendar day in previous years.
 *
 * The date constraint matches today's month and day, combined with a
 * `before` clause anchored to January 1 of the current year. Up to ten posts
 * are returned; use the `wp_dashboard_on_this_day_query_args` filter to change
 * the limit. Results are cached by WP_Query's native query caching.
 *
 * @since 7.1.0
 *
 * @return WP_Post[] Array of posts ordered by newest first.
 */
function wp_dashboard_on_this_day_get_posts() {
	$query = _wp_dashboard_on_this_day_get_posts_query(
		array(
			'no_found_rows' => true,
		)
	);

	return $query->posts;
}

/**
 * Retrieves the WP_Query for On This Day posts.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array $query_args Additional query arguments.
 * @return WP_Query Query for matching posts.
 */
function _wp_dashboard_on_this_day_get_posts_query( $query_args = array() ) {
	$today      = current_datetime();
	$year       = (int) $today->format( 'Y' );
	$date_query = array(
		'relation' => 'AND',
		array(
			'before' => array( 'year' => $year ),
		),
		_wp_dashboard_on_this_day_date_query_clause( $today ),
	);

	$args = array_merge(
		array(
			'post_type'              => 'post',
			'post_status'            => array( 'publish' ),
			'posts_per_page'         => 10,
			'ignore_sticky_posts'    => true,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'date_query'             => $date_query,
		),
		$query_args
	);

	/**
	 * Filters the arguments used to query posts for the On This Day dashboard widget.
	 *
	 * @since 7.1.0
	 *
	 * @param array $args WP_Query arguments.
	 */
	$args = apply_filters( 'wp_dashboard_on_this_day_query_args', $args );

	return new WP_Query( $args );
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
