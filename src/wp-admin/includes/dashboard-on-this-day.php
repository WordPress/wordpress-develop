<?php
/**
 * WordPress Dashboard "On This Day" widget.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 7.1.0
 */

/**
 * Registers the On This Day dashboard widget when matching posts exist.
 *
 * Designed to be the single entry point called from the dashboard setup
 * routine.
 *
 * @since 7.1.0
 */
function wp_dashboard_on_this_day_setup() {
	$posts = wp_dashboard_on_this_day_get_posts();

	if ( empty( $posts ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'wp_dashboard_on_this_day',
		sprintf(
			'<span class="wp-on-this-day-title">%s <span class="wp-on-this-day-date">%s</span></span>',
			esc_html__( 'On This Day' ),
			/* translators: Date format for the On This Day widget date, without year. See https://www.php.net/manual/datetime.format.php */
			esc_html( wp_date( _x( 'F jS', 'on this day date format' ) ) )
		),
		'wp_dashboard_on_this_day'
	);
}

/**
 * Renders the On This Day dashboard widget.
 *
 * Outputs the matching posts grouped by publication year, newest year first.
 *
 * @since 7.1.0
 *
 * @global WP_Post $post Global post object.
 */
function wp_dashboard_on_this_day() {
	global $post;

	$posts = wp_dashboard_on_this_day_get_posts();

	if ( empty( $posts ) ) {
		return;
	}

	$posts_by_year = array();
	$post_count    = count( $posts );

	foreach ( $posts as $current_post ) {
		$year = get_the_date( 'Y', $current_post );

		if ( ! isset( $posts_by_year[ $year ] ) ) {
			$posts_by_year[ $year ] = array();
		}

		$posts_by_year[ $year ][] = $current_post;
	}
	?>
	<div class="wp-on-this-day-widget">
		<p class="wp-on-this-day-summary">
			<?php
			printf(
				esc_html(
					/* translators: %s: Number of posts. */
					_n(
						'%s post has been published on this day:',
						'%s posts have been published on this day:',
						$post_count
					)
				),
				esc_html( number_format_i18n( $post_count ) )
			);
			?>
		</p>
		<ul class="wp-on-this-day-years">
			<?php foreach ( $posts_by_year as $year => $year_posts ) : ?>
				<li class="wp-on-this-day-year">
					<h3 class="wp-on-this-day-year-heading"><?php echo esc_html( $year ); ?></h3>
					<ul class="wp-on-this-day-posts">
						<?php
						foreach ( $year_posts as $post ) {
							setup_postdata( $post );
							_wp_dashboard_on_this_day_post();
						}
						?>
					</ul>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
	wp_reset_postdata();
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
	$today      = current_datetime();
	$year       = (int) $today->format( 'Y' );
	$date_query = array(
		'relation' => 'AND',
		array(
			'before' => array( 'year' => $year ),
		),
		_wp_dashboard_on_this_day_date_query_clause( $today ),
	);

	$args = array(
		'post_type'              => 'post',
		'post_status'            => array( 'publish' ),
		'posts_per_page'         => 10,
		'ignore_sticky_posts'    => true,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'date_query'             => $date_query,
	);

	/**
	 * Filters the arguments used to query posts for the On This Day dashboard widget.
	 *
	 * @since 7.1.0
	 *
	 * @param array $args WP_Query arguments.
	 */
	$args = apply_filters( 'wp_dashboard_on_this_day_query_args', $args );

	$query = new WP_Query( $args );

	return $query->posts;
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

/**
 * Renders a single linked post title for the On This Day dashboard widget.
 *
 * Must be called with the global post set up via `setup_postdata()`.
 *
 * @since 7.1.0
 * @access private
 */
function _wp_dashboard_on_this_day_post() {
	$title = get_the_title();

	if ( '' === trim( $title ) ) {
		$title = __( '(no title)' );
	}

	$author_id   = (int) get_post_field( 'post_author', get_the_ID() );
	$author_name = $author_id > 0 ? (string) get_the_author() : '';
	$show_author = '' !== trim( $author_name ) && get_current_user_id() !== $author_id;
	?>
	<li class="wp-on-this-day-post">
		<a href="<?php the_permalink(); ?>"><?php echo esc_html( $title ); ?></a>
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
	<?php
}
