<?php
/**
 * Class for rendering the On This Day dashboard widget.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 7.1.0
 */

/**
 * Core class used by the "On This Day" dashboard widget.
 *
 * Renders published posts that were published on this calendar day in
 * previous years.
 *
 * @since 7.1.0
 */
class WP_Dashboard_Widget_On_This_Day {
	/**
	 * Number of posts to fetch for the widget. -1 fetches all matches for the day.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const POSTS_PER_PAGE = -1;

	/**
	 * Dashboard widget ID.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	const WIDGET_ID = 'wp_dashboard_on_this_day';

	/**
	 * Registers the dashboard widget and its supporting assets.
	 *
	 * Designed to be the single entry point called from the dashboard
	 * setup routine. It enqueues assets and registers the widget.
	 *
	 * @since 7.1.0
	 */
	public static function register_widget() {
		if ( ! self::has_posts() ) {
			return;
		}

		wp_enqueue_style( 'on-this-day' );

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			sprintf(
				'<span class="wp-on-this-day-title" data-wp-otd-window-label="%s">%s</span>',
				esc_attr( self::get_window_label() ),
				esc_html__( 'On This Day' )
			),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

	/**
	 * Renders the dashboard widget output.
	 *
	 * @since 7.1.0
	 */
	public static function render_dashboard_widget() {
		$posts = self::get_posts_on_this_day();

		if ( empty( $posts ) ) {
			return;
		}

		echo '<div class="wp-on-this-day-widget">';
		self::render_posts( $posts );
		echo '</div>';
	}

	/**
	 * Retrieves published posts from all authors that were published on this
	 * calendar day in previous years.
	 *
	 * The date constraint matches today's month and day, combined with a
	 * `before` clause anchored to January 1 of the current year. Results are
	 * cached by WP_Query's native query caching.
	 *
	 * @since 7.1.0
	 *
	 * @return WP_Post[] Array of posts ordered by newest first.
	 */
	public static function get_posts_on_this_day() {
		$today      = current_datetime();
		$year       = (int) $today->format( 'Y' );
		$date_query = array(
			'relation' => 'AND',
			array(
				'before' => array( 'year' => $year ),
			),
			self::get_date_query_clause( $today ),
		);

		$args = array(
			'post_type'              => 'post',
			'post_status'            => array( 'publish' ),
			'posts_per_page'         => self::POSTS_PER_PAGE,
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
	 *
	 * @param DateTimeInterface $date Date to build the clause for.
	 * @return array Date query clause.
	 */
	protected static function get_date_query_clause( $date ) {
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
	 * Determines whether posts are available for the widget.
	 *
	 * @since 7.1.0
	 *
	 * @return bool True when matching posts exist, false otherwise.
	 */
	public static function has_posts() {
		return ! empty( self::get_posts_on_this_day() );
	}

	/**
	 * Renders the post list grouped by publication year.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post[] $posts Posts to render, most recent first.
	 */
	protected static function render_posts( $posts ) {
		global $post;

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
						<?php foreach ( $year_posts as $post ) : ?>
							<?php
							setup_postdata( $post );
							self::render_post();
							?>
						<?php endforeach; ?>
					</ul>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Returns the current month and day for display in the widget title.
	 *
	 * @since 7.1.0
	 *
	 * @return string Current month and day.
	 */
	public static function get_window_label() {
		/* translators: Date format for a specific On This Day date, without year. See https://www.php.net/manual/datetime.format.php */
		return wp_date( _x( 'F jS', 'on this day date format' ) );
	}

	/**
	 * Renders a single linked post title.
	 *
	 * @since 7.1.0
	 */
	protected static function render_post() {
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
}
