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
 * Renders the current user's published posts that were published in the
 * seven-day date window centered on today in previous years.
 *
 * @since 7.1.0
 */
class WP_On_This_Day {
	/**
	 * Number of posts to fetch for the widget.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const POSTS_PER_PAGE = 50;

	/**
	 * Days before today included in the date window.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const WINDOW_BEFORE_DAYS = 3;

	/**
	 * Days after today included in the date window.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const WINDOW_AFTER_DAYS = 3;

	/**
	 * Object cache group used for storing widget results.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	const CACHE_GROUP = 'on_this_day';

	/**
	 * Cache version. Bump when the rendered markup changes so stale
	 * entries from older releases are naturally ignored.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const CACHE_VERSION = 1;

	/**
	 * Approximate maximum number of characters shown in each post excerpt.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const EXCERPT_CHAR_COUNT = 160;

	/**
	 * Registers the dashboard widget and its supporting hooks and assets.
	 *
	 * Designed to be the single entry point called from the dashboard
	 * setup routine. It enqueues assets and registers the widget.
	 *
	 * @since 7.1.0
	 */
	public static function register_widget() {
		wp_enqueue_style( 'on-this-day' );
		wp_enqueue_script( 'on-this-day' );

		wp_add_dashboard_widget(
			'dashboard_on_this_day',
			sprintf(
				'<span class="on-this-day-title" data-otd-window-label="%s">%s</span>',
				esc_attr( self::get_window_label() ),
				esc_html__( 'On This Day' )
			),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

	/**
	 * Renders the dashboard widget output.
	 *
	 * The rendered HTML is cached per user, locale, and site date. The
	 * cache key also incorporates the posts group's `last_changed` token,
	 * so any post mutation (publish, edit, delete, trash) automatically
	 * invalidates the entry on the next read, and entries roll over
	 * naturally at midnight.
	 *
	 * Note: I made the trade-off to ignore `date_format` and `time_format`
	 * option changes. They do not bust the cache; stale date strings clear
	 * on the next post mutation or at midnight.
	 *
	 * @since 7.1.0
	 */
	public static function render_dashboard_widget() {
		$user_id = get_current_user_id();

		$cache_key = sprintf(
			'render:v%d:%d:%s:%s:%s',
			self::CACHE_VERSION,
			$user_id,
			determine_locale(),
			current_time( 'Y-m-d' ),
			wp_cache_get_last_changed( 'posts' )
		);

		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( ! is_string( $cached ) ) {
			$posts = self::get_posts( $user_id );

			ob_start();
			if ( empty( $posts ) ) {
				self::render_empty_state();
			} else {
				self::render_posts( $posts );
			}
			$cached = ob_get_clean();

			wp_cache_set( $cache_key, $cached, self::CACHE_GROUP, DAY_IN_SECONDS );
		}

		echo '<div class="on-this-day-widget">';
		echo '<div class="on-this-day-scroll">';
		// Already escaped at write time by the render_* methods below.
		echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Retrieves posts by a given author that were published in the
	 * seven-day window centered on today in previous years.
	 *
	 * The "selected date window, prior year" constraint is expressed as a
	 * `date_query`: clauses pinning each `month`/`day`, combined with a
	 * `before` clause anchored to January 1 of the current year.
	 *
	 * @since 7.1.0
	 *
	 * @param int $user_id Author ID to query posts for.
	 * @return WP_Post[] Array of posts ordered by newest first.
	 */
	public static function get_posts( $user_id ) {
		$year       = (int) current_time( 'Y' );
		$date_query = array(
			'relation' => 'AND',
			array(
				'before' => array( 'year' => $year ),
			),
			array_merge(
				array( 'relation' => 'OR' ),
				self::get_window_date_query_clauses()
			),
		);

		$args = array(
			'author'              => (int) $user_id,
			'post_type'           => 'post',
			'post_status'         => array( 'publish' ),
			'posts_per_page'      => self::POSTS_PER_PAGE,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'date_query'          => $date_query,
		);

		/**
		 * Filters the arguments used to query posts for the On This Day dashboard widget.
		 *
		 * @since 7.1.0
		 *
		 * @param array $args    WP_Query arguments.
		 * @param int   $user_id The author ID the query is scoped to.
		 */
		$args = apply_filters( 'dashboard_on_this_day_query_args', $args, $user_id );

		$query = new WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Returns a human-readable label for the active date window.
	 *
	 * @since 7.1.0
	 *
	 * @return string Date range label.
	 */
	public static function get_window_label() {
		$now   = current_datetime();
		$start = $now->modify( '-' . self::WINDOW_BEFORE_DAYS . ' days' );
		$end   = $now->modify( '+' . self::WINDOW_AFTER_DAYS . ' days' );

		return sprintf(
			/* translators: 1: Start date, 2: End date. */
			__( '%1$s - %2$s' ),
			wp_date( 'F j', $start->getTimestamp(), $start->getTimezone() ),
			wp_date( 'F j', $end->getTimestamp(), $end->getTimezone() )
		);
	}

	/**
	 * Extracts a plain-text excerpt from HTML source using the HTML API.
	 *
	 * Walks the input as HTML5 tokens, collecting the contents of `#text`
	 * nodes only, so script, style, and comment contents are skipped by
	 * construction rather than via regex stripping. A space is emitted on
	 * non-inline tag boundaries to keep word boundaries between adjacent
	 * block elements (e.g. `<p>One</p><p>Two</p>` -> "One Two") without
	 * adding artificial spaces around inline formatting.
	 *
	 * Length is measured in Unicode characters via `mb_strlen()`, which
	 * is more language-fair than word counting (CJK languages do not
	 * separate words with whitespace).
	 *
	 * @since 7.1.0
	 *
	 * @param string $source    HTML source to extract text from.
	 * @param int    $max_chars Approximate character limit before truncation.
	 * @return string Plain-text excerpt.
	 */
	protected static function extract_excerpt_text( $source, $max_chars ) {
		$source = strip_shortcodes( (string) $source );

		if ( '' === trim( $source ) ) {
			return '';
		}

		$processor = new WP_HTML_Tag_Processor( $source );
		$parts     = array();
		$length    = 0;

		$inline_tags = array(
			'A',
			'ABBR',
			'B',
			'BIG',
			'CODE',
			'DEL',
			'EM',
			'FONT',
			'I',
			'INS',
			'MARK',
			'Q',
			'S',
			'SAMP',
			'SMALL',
			'SPAN',
			'STRONG',
			'SUB',
			'SUP',
			'TIME',
			'VAR',
		);

		while ( $processor->next_token() ) {
			$token_type = $processor->get_token_type();

			if ( '#tag' === $token_type ) {
				$tag_name = $processor->get_tag();

				if ( ! in_array( $tag_name, $inline_tags, true ) ) {
					$parts[] = ' ';
				}
				continue;
			}

			if ( '#text' !== $token_type ) {
				continue;
			}

			$chunk   = $processor->get_modifiable_text();
			$parts[] = $chunk;
			$length += mb_strlen( $chunk );

			if ( $length >= $max_chars ) {
				break;
			}
		}
		$separator = _wp_can_use_pcre_u() ? '~[\s\p{Z}]+~u' : '~\s+~';
		return trim( preg_replace( $separator, ' ', implode( '', $parts ) ) );
	}

	/**
	 * Builds date query clauses for each day in the 7-day window
	 * centered on today.
	 *
	 * @since 7.1.0
	 *
	 * @return array[] Date query clauses.
	 */
	protected static function get_window_date_query_clauses() {
		$date    = current_datetime();
		$clauses = array();

		for ( $offset = -self::WINDOW_BEFORE_DAYS; $offset <= self::WINDOW_AFTER_DAYS; $offset++ ) {
			$day_date  = $date->modify( ( $offset >= 0 ? '+' : '' ) . $offset . ' days' );
			$clauses[] = array(
				'month' => (int) $day_date->format( 'n' ),
				'day'   => (int) $day_date->format( 'j' ),
			);
		}

		return $clauses;
	}

	/**
	 * Renders the empty state shown when no matching posts exist.
	 *
	 * Outputs rendered HTML that has already been escaped at write time.
	 * Callers must echo the captured buffer as-is to avoid double-escaping.
	 *
	 * @since 7.1.0
	 */
	protected static function render_empty_state() {
		$calendar_icon = WP_Icons_Registry::get_instance()->get_registered_icon( 'core/calendar' );
		?>
		<div class="on-this-day-empty">
			<div class="on-this-day-empty-icon" aria-hidden="true">
				<?php
				if ( ! empty( $calendar_icon['content'] ) ) {
					// SVG content is sanitized by WP_Icons_Registry.
					echo $calendar_icon['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
			<p class="on-this-day-empty-text">
				<?php esc_html_e( 'Your blogging memories are still being made.' ); ?><br>
				<?php esc_html_e( 'Check back again soon.' ); ?>
			</p>
			<p class="on-this-day-empty-cta">
				<a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Write a new post' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the post list or carousel for the widget.
	 *
	 * Outputs rendered HTML that has already been escaped at write time.
	 * Callers must echo the captured buffer as-is to avoid double-escaping.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post[] $posts Posts to render, most recent first.
	 */
	protected static function render_posts( $posts ) {
		$is_carousel = count( $posts ) > 1;

		if ( $is_carousel ) :
			?>
			<div class="on-this-day-carousel" role="region" aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'On This Day posts' ); ?>">
			<?php
		endif;
		?>
		<ul class="on-this-day-timeline<?php echo $is_carousel ? ' is-carousel' : ''; ?>">
			<?php foreach ( $posts as $index => $post ) : ?>
				<?php self::render_post( $post, $is_carousel && 0 === $index ); ?>
			<?php endforeach; ?>
		</ul>
		<?php
		if ( $is_carousel ) :
			$total = count( $posts );
			?>
				<p class="on-this-day-carousel-pagination" aria-live="polite">
					<button type="button" class="on-this-day-carousel-prev" aria-label="<?php esc_attr_e( 'Previous post' ); ?>">&larr;</button>
					<span class="on-this-day-carousel-counter">
						<?php
						printf(
							/* translators: 1: Current slide number, 2: Total slides. */
							esc_html__( '%1$s of %2$s' ),
							'<span class="on-this-day-carousel-current">1</span>',
							'<span class="on-this-day-carousel-total">' . esc_html( number_format_i18n( $total ) ) . '</span>'
						);
						?>
					</span>
					<button type="button" class="on-this-day-carousel-next" aria-label="<?php esc_attr_e( 'Next post' ); ?>">&rarr;</button>
				</p>
			</div>
			<?php
		endif;
	}

	/**
	 * Renders a single post row.
	 *
	 * Outputs rendered HTML that has already been escaped at write time.
	 * Callers must echo the captured buffer as-is to avoid double-escaping.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post $post      Post object to render.
	 * @param bool    $is_active Whether this slide is initially active in a carousel.
	 */
	protected static function render_post( $post, $is_active = false ) {
		$view_link = get_permalink( $post->ID );

		$title = get_the_title( $post );
		if ( '' === trim( $title ) ) {
			$title = __( '(no title)' );
		}

		$excerpt = self::extract_excerpt_text(
			has_excerpt( $post ) ? $post->post_excerpt : $post->post_content,
			self::EXCERPT_CHAR_COUNT
		);

		$current_year = (int) current_time( 'Y' );
		$post_year    = (int) get_the_date( 'Y', $post );
		$years_ago    = max( 1, $current_year - $post_year );
		$date_full    = get_the_date( get_option( 'date_format' ), $post );
		$time_str     = get_the_time( get_option( 'time_format' ), $post );
		$time_iso     = get_the_time( 'c', $post );
		$image_url    = self::get_post_image_url( $post );
		?>
		<li class="on-this-day-post<?php echo $image_url ? ' has-image' : ''; ?><?php echo $is_active ? ' is-active' : ''; ?>">
			<p class="on-this-day-post-when">
				<span class="on-this-day-post-years-ago">
				<?php
				printf(
					/* translators: %s: Number of years, e.g. "1 year ago" or "5 years ago". */
					esc_html( _n( '%s year ago', '%s years ago', $years_ago ) ),
					esc_html( number_format_i18n( $years_ago ) )
				);
				?>
				</span>
				<time class="on-this-day-post-time" datetime="<?php echo esc_attr( $time_iso ); ?>">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: Full post date, 2: Post time. */
						__( '%1$s at %2$s' ),
						$date_full,
						$time_str
					)
				);
				?>
				</time>
			</p>

			<div class="on-this-day-post-row">
				<?php if ( $image_url ) : ?>
					<div class="on-this-day-post-image">
						<a href="<?php echo esc_url( $view_link ); ?>" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
							<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy" />
						</a>
					</div>
				<?php endif; ?>
				<div class="on-this-day-post-content">
					<span class="screen-reader-text"><?php esc_html_e( 'Published post' ); ?></span>

					<h4 class="on-this-day-post-title">
						<a href="<?php echo esc_url( $view_link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $title ); ?></a>
					</h4>

					<?php if ( $excerpt ) : ?>
						<p class="on-this-day-post-excerpt"><?php echo esc_html( $excerpt ); ?></p>
					<?php endif; ?>

					<div class="on-this-day-post-actions">
						<a class="on-this-day-post-action button button-primary button-compact" href="<?php echo esc_url( $view_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View' ); ?></a>
						<button
							type="button"
							class="on-this-day-post-action on-this-day-post-share button-link is-compact"
							data-otd-share-url="<?php echo esc_attr( $view_link ); ?>"
							data-otd-share-title="<?php echo esc_attr( $title ); ?>"
							data-otd-share-label="<?php esc_attr_e( 'Share' ); ?>"
							data-otd-share-copied="<?php esc_attr_e( 'Link copied!' ); ?>"
							data-otd-share-shared="<?php esc_attr_e( 'Shared!' ); ?>"
						><?php esc_html_e( 'Share' ); ?></button>
					</div>
				</div>
			</div>
		</li>
		<?php
	}

	/**
	 * Returns a URL to a representative image for the post.
	 *
	 * Prefers the featured image. Falls back to the first image in the
	 * post content or the first image in the first gallery.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post $post Post object.
	 * @return string Image URL, or empty string.
	 */
	protected static function get_post_image_url( $post ) {
		if ( has_post_thumbnail( $post ) ) {
			$url = get_the_post_thumbnail_url( $post, 'medium_large' );
			if ( $url ) {
				return $url;
			}
		}

		if ( ! empty( $post->post_content ) ) {
			$processor = new WP_HTML_Tag_Processor( $post->post_content );
			while ( $processor->next_tag( 'IMG' ) ) {
				$src = $processor->get_attribute( 'src' );
				if ( is_string( $src ) && '' !== trim( $src ) ) {
					return $src;
				}
			}
		}

		$gallery_images = get_post_gallery_images( $post );
		if ( ! empty( $gallery_images[0] ) ) {
			return $gallery_images[0];
		}

		return '';
	}
}
