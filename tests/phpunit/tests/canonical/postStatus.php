<?php

/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_PostStatus extends WP_Canonical_UnitTestCase {

	/**
	 * User IDs.
	 *
	 * @var array
	 */
	public static $users;

	/**
	 * Post Objects.
	 *
	 * @var array
	 */
	public static $posts;

	public static function wpSetupBeforeClass( $factory ) {
		self::setup_custom_types();
		self::$users = array(
			'anon'           => 0,
			'subscriber'     => $factory->user->create( array( 'role' => 'subscriber' ) ),
			'content_author' => $factory->user->create( array( 'role' => 'author' ) ),
			'editor'         => $factory->user->create( array( 'role' => 'editor' ) ),
		);

		$post_statuses = array( 'publish', 'future', 'draft', 'pending', 'private', 'auto-draft', 'trac-5272-status' );
		foreach ( $post_statuses as $post_status ) {
			$post_date = '';
			if ( 'future' === $post_status ) {
				$post_date = strftime( '%Y-%m-%d %H:%M:%S', strtotime( '+1 year' ) );
			}

			self::$posts[ $post_status ] = $factory->post->create_and_get(
				array(
					'post_type'    => 'post',
					'post_title'   => "$post_status post",
					'post_name'    => "{$post_status}-post",
					'post_status'  => $post_status,
					'post_content' => "Prevent canonical redirect exposing post slugs.\n\n<!--nextpage-->Page 2",
					'post_author'  => self::$users['content_author'],
					'post_date'    => $post_date,
				)
			);

			// Add fake attachment to the post (file upload not needed).
			self::$posts[ "{$post_status}-attachment" ] = $factory->post->create_and_get(
				array(
					'post_type'    => 'attachment',
					'post_title'   => "$post_status inherited attachment",
					'post_name'    => "{$post_status}-inherited-attachment",
					'post_status'  => 'inherit',
					'post_content' => "Prevent canonical redirect exposing post via attachments.\n\n<!--nextpage-->Page 2",
					'post_author'  => self::$users['content_author'],
					'post_parent'  => self::$posts[ $post_status ]->ID,
					'post_date'    => $post_date,
				)
			);

			// Set up a page with same.
			self::$posts[ "page-$post_status" ] = $factory->post->create_and_get(
				array(
					'post_type'    => 'page',
					'post_title'   => "$post_status page",
					'post_name'    => "{$post_status}-page",
					'post_status'  => $post_status,
					'post_content' => "Prevent canonical redirect exposing page slugs.\n\n<!--nextpage-->Page 2",
					'post_author'  => self::$users['content_author'],
					'post_date'    => $post_date,
				)
			);
		}

		self::$posts['trac-5272-cpt'] = $factory->post->create_and_get(
			array(
				'post_type'    => 'trac-5272-cpt',
				'post_title'   => 'trac-5272-cpt post',
				'post_name'    => 'trac-5272-cpt-post',
				'post_status'  => 'private',
				'post_content' => 'Prevent canonical redirect exposing trac-5272-cpt titles.',
				'post_author'  => self::$users['content_author'],
			)
		);

		// Add fake attachment to the cpt (file upload not needed).
		self::$posts['trac-5272-cpt-attachment'] = $factory->post->create_and_get(
			array(
				'post_type'    => 'attachment',
				'post_title'   => 'trac-5272-cpt post inherited attachment',
				'post_name'    => 'trac-5272-cpt-post-inherited-attachment',
				'post_status'  => 'inherit',
				'post_content' => "Prevent canonical redirect exposing post via attachments.\n\n<!--nextpage-->Page 2",
				'post_author'  => self::$users['content_author'],
				'post_parent'  => self::$posts['trac-5272-cpt']->ID,
			)
		);

		// Post for trashing.
		self::$posts['trash'] = $factory->post->create_and_get(
			array(
				'post_type'    => 'post',
				'post_title'   => 'trash post',
				'post_name'    => 'trash-post',
				'post_status'  => 'publish',
				'post_content' => "Prevent canonical redirect exposing post slugs.\n\n<!--nextpage-->Page 2",
				'post_author'  => self::$users['content_author'],
			)
		);

		self::$posts['trash-attachment'] = $factory->post->create_and_get(
			array(
				'post_type'    => 'attachment',
				'post_title'   => 'trash post inherited attachment',
				'post_name'    => 'trash-post-inherited-attachment',
				'post_status'  => 'inherit',
				'post_content' => "Prevent canonical redirect exposing post via attachments.\n\n<!--nextpage-->Page 2",
				'post_author'  => self::$users['content_author'],
				'post_parent'  => self::$posts['trash']->ID,
			)
		);

		// Page for trashing.
		self::$posts['page-trash'] = $factory->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_title'   => 'trash page',
				'post_name'    => 'trash-page',
				'post_status'  => 'publish',
				'post_content' => "Prevent canonical redirect exposing page slugs.\n\n<!--nextpage-->Page 2",
				'post_author'  => self::$users['content_author'],
			)
		);
		wp_trash_post( self::$posts['trash']->ID );
		wp_trash_post( self::$posts['page-trash']->ID );
	}

	function setUp() {
		parent::setUp();
		self::setup_custom_types();
	}

	/**
	 * Set up a custom post type and private status.
	 *
	 * This needs to be called both in the class setup and
	 * test setup.
	 */
	public static function setup_custom_types() {
		// Register custom post type.
		register_post_type(
			'trac-5272-cpt',
			array(
				'public'  => true,
				'rewrite' => array(
					'slug' => 'trac-5272-cpt',
				),
			)
		);

		// Register custom private post status.
		register_post_status(
			'trac-5272-status',
			array(
				'private' => true,
			)
		);
	}

	/**
	 * Test canonical redirect does not reveal private posts presence.
	 *
	 * @ticket 5272
	 * @dataProvider data_canonical_redirects_to_ugly_permalinks
	 *
	 * @param string $post_key  Post key used for creating fixtures.
	 * @param string $user_role User role.
	 * @param string $requested Requested URL.
	 * @param string $expected  Expected URL.
	 */
	public function test_canonical_redirects_to_ugly_permalinks( $post_key, $user_role, $requested, $expected ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '' );
		$post = self::$posts[ $post_key ];
		clean_post_cache( $post->ID );
		if ( isset( self::$posts[ "{$post_key}-attachment" ] ) ) {
			$attachment = self::$posts[ "{$post_key}-attachment" ];
			clean_post_cache( $attachment->ID );

			/*
			* The dataProvider runs before the fixures are set up, therefore the
			* post and attachment IDs are placeholders that needs to be replaced.
			*/
			$requested = str_replace( '%ID-A%', $attachment->ID, $requested );
			$expected  = str_replace( '%ID-A%', $attachment->ID, $expected );
		}

		$requested = str_replace( '%ID%', $post->ID, $requested );
		$expected  = str_replace( '%ID%', $post->ID, $expected );

		$this->assertCanonical( $requested, $expected );
	}

	/**
	 * Data provider for test_canonical_redirects_to_ugly_permalinks.
	 *
	 * @return array[] Array of arguments for tests {
	 *     @type string $post_key  Post key used for creating fixtures.
	 *     @type string $user_role User role.
	 *     @type string $requested Requested URL.
	 *     @type string $expected  Expected URL.
	 * }
	 */
	function data_canonical_redirects_to_ugly_permalinks() {
		$data = array();
		$all_user_list     = array( 'anon', 'subscriber', 'content_author', 'editor' );
		$select_allow_list = array( 'content_author', 'editor' );
		$select_block_list = array( 'anon', 'subscriber' );
		// All post/page keys
		$all_user_post_keys    = array( 'publish' );
		$select_user_post_keys = array( 'private', 'trac-5272-status' );
		$no_user_post_keys     = array( 'future', 'draft', 'pending', 'auto-draft' ); // Excludes trash for attachment rules.

		foreach ( $all_user_post_keys as $post_key ) {
			foreach ( $all_user_list as $user ) {
				/*
				 * In the event `redirect_canonical()` is updated to redirect ugly permalinks
				 * to a canonical ugly version, these expected values can be changed.
				 */
				$data[] = array(
					"page-$post_key",
					$user,
					'/?post_type=page&p=%ID%',
					'/?post_type=page&p=%ID%',
				);

				$data[] = array(
					$post_key,
					$user,
					"/?name=$post_key-post",
					"/?name=$post_key-post",
				);

				$data[] = array(
					$post_key,
					$user,
					'/?feed=rss&p=%ID%',
					'/?feed=rss2&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?feed=rss&page_id=%ID%',
					'/?feed=rss2&page_id=%ID%',
				);
			}
		}

		foreach ( $select_user_post_keys as $post_key ) {
			foreach ( $select_allow_list as $user ) {
				/*
				 * In the event `redirect_canonical()` is updated to redirect ugly permalinks
				 * to a canonical ugly version, these expected values can be changed.
				 */
				$data[] = array(
					"page-$post_key",
					$user,
					'/?post_type=page&p=%ID%',
					'/?post_type=page&p=%ID%',
				);

				$data[] = array(
					$post_key,
					$user,
					"/?name=$post_key-post",
					"/?name=$post_key-post",
				);

				$data[] = array(
					$post_key,
					$user,
					'/?feed=rss&p=%ID%',
					'/?feed=rss2&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?feed=rss&page_id=%ID%',
					'/?feed=rss2&page_id=%ID%',
				);
			}

			foreach ( $select_block_list as $user ) {
				/*
				 * In the event `redirect_canonical()` is updated to redirect ugly permalinks
				 * to a canonical ugly version, these expected values MUST NOT be changed.
				 */
				$data[] = array(
					"page-$post_key",
					$user,
					'/?post_type=page&p=%ID%',
					'/?post_type=page&p=%ID%',
				);

				$data[] = array(
					$post_key,
					$user,
					"/?name=$post_key-post",
					"/?name=$post_key-post",
				);

				$data[] = array(
					$post_key,
					$user,
					'/?feed=rss&p=%ID%',
					'/?feed=rss&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?feed=rss&page_id=%ID%',
					'/?feed=rss&page_id=%ID%',
				);
			}
		}

		foreach ( $no_user_post_keys as $post_key ) {
			foreach ( $all_user_list as $user ) {
				/*
				 * In the event `redirect_canonical()` is updated to redirect ugly permalinks
				 * to a canonical ugly version, these expected values MUST NOT be changed.
				 */
				$data[] = array(
					"page-$post_key",
					$user,
					'/?post_type=page&p=%ID%',
					'/?post_type=page&p=%ID%',
				);

				$data[] = array(
					$post_key,
					$user,
					"/?name=$post_key-post",
					"/?name=$post_key-post",
				);

				$data[] = array(
					$post_key,
					$user,
					'/?feed=rss&p=%ID%',
					'/?feed=rss&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?feed=rss&page_id=%ID%',
					'/?feed=rss&page_id=%ID%',
				);
			}
		}

		foreach ( array( 'trash' ) as $post_key ) {
			foreach ( $all_user_list as $user ) {
				/*
				 * In the event `redirect_canonical()` is updated to redirect ugly permalinks
				 * to a canonical ugly version, these expected values MUST NOT be changed.
				 */
				$data[] = array(
					"page-$post_key",
					$user,
					'/?post_type=page&p=%ID%',
					'/?post_type=page&p=%ID%',
				);

				$data[] = array(
					$post_key,
					$user,
					"/?name=$post_key-post",
					"/?name=$post_key-post",
				);

				$data[] = array(
					$post_key,
					$user,
					'/?feed=rss&p=%ID%',
					'/?feed=rss&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?feed=rss&page_id=%ID%',
					'/?feed=rss&page_id=%ID%',
				);
			}
		}

		return $data;
	}

	/**
	 * Test canonical redirect does not reveal private slugs.
	 *
	 * @ticket 5272
	 * @dataProvider data_canonical_redirects_to_pretty_permalinks
	 *
	 * @param string $post_key  Post key used for creating fixtures.
	 * @param string $user_role User role.
	 * @param string $requested Requested URL.
	 * @param string $expected  Expected URL.
	 */
	public function test_canonical_redirects_to_pretty_permalinks( $post_key, $user_role, $requested, $expected ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		$post = self::$posts[ $post_key ];
		clean_post_cache( $post->ID );
		if ( isset( self::$posts[ "{$post_key}-attachment" ] ) ) {
			$attachment = self::$posts[ "{$post_key}-attachment" ];
			clean_post_cache( $attachment->ID );

			/*
			* The dataProvider runs before the fixures are set up, therefore the
			* post and attachment IDs are placeholders that needs to be replaced.
			*/
			$requested = str_replace( '%ID-A%', $attachment->ID, $requested );
			$expected  = str_replace( '%ID-A%', $attachment->ID, $expected );
		}

		$requested = str_replace( '%ID%', $post->ID, $requested );
		$expected  = str_replace( '%ID%', $post->ID, $expected );

		$this->assertCanonical( $requested, $expected );
	}

	/**
	 * Data provider for test_canonical_redirects_to_pretty_permalinks.
	 *
	 * @return array[] Array of arguments for tests {
	 *     @type string $post_key  Post key used for creating fixtures.
	 *     @type string $user_role User role.
	 *     @type string $requested Requested URL.
	 *     @type string $expected  Expected URL.
	 * }
	 */
	function data_canonical_redirects_to_pretty_permalinks() {
		$data = array();
		$all_user_list     = array( 'anon', 'subscriber', 'content_author', 'editor' );
		$select_allow_list = array( 'content_author', 'editor' );
		$select_block_list = array( 'anon', 'subscriber' );
		// All post/page keys
		$all_user_post_keys    = array( 'publish' );
		$select_user_post_keys = array( 'private', 'trac-5272-status' );
		$no_user_post_keys     = array( 'future', 'draft', 'pending', 'auto-draft' ); // Excludes trash for attachment rules.

		foreach ( $all_user_post_keys as $post_key ) {
			foreach ( $all_user_list as $user ) {
				$data[] = array(
					$post_key,
					$user,
					'/?p=%ID%',
					"/$post_key-post/",
				);

				$data[] = array(
					"$post_key",
					$user,
					'/?attachment_id=%ID-A%',
					"/$post_key-post/{$post_key}-inherited-attachment/",
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?post_type=page&p=%ID%',
					"/$post_key-page/",
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?page_id=%ID%',
					"/$post_key-page/",
				);

				$data[] = array(
					$post_key,
					$user,
					"/?name=$post_key-post",
					"/$post_key-post/",
				);

				$data[] = array(
					$post_key,
					$user,
					'/?feed=rss&p=%ID%',
					"/$post_key-post/feed/",
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?feed=rss&page_id=%ID%',
					"/$post_key-page/feed/",
				);
			}
		}

		foreach ( $select_user_post_keys as $post_key ) {
			foreach ( $select_allow_list as $user ) {
				$data[] = array(
					$post_key,
					$user,
					'/?p=%ID%',
					"/$post_key-post/",
				);

				$data[] = array(
					"$post_key",
					$user,
					'/?attachment_id=%ID-A%',
					"/$post_key-post/{$post_key}-inherited-attachment/",
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?post_type=page&p=%ID%',
					"/$post_key-page/",
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?page_id=%ID%',
					"/$post_key-page/",
				);

				$data[] = array(
					$post_key,
					$user,
					"/?name=$post_key-post",
					"/$post_key-post/",
				);

				$data[] = array(
					$post_key,
					$user,
					'/?feed=rss&p=%ID%',
					"/$post_key-post/feed/",
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?feed=rss&page_id=%ID%',
					"/$post_key-page/feed/",
				);
			}

			foreach ( $select_block_list as $user ) {
				$data[] = array(
					$post_key,
					$user,
					'/?p=%ID%',
					'/?p=%ID%',
				);

				$data[] = array(
					"$post_key",
					$user,
					'/?attachment_id=%ID-A%',
					'/?attachment_id=%ID-A%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?post_type=page&p=%ID%',
					'/?post_type=page&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?page_id=%ID%',
					'/?page_id=%ID%',
				);

				$data[] = array(
					$post_key,
					$user,
					"/?name=$post_key-post",
					"/?name=$post_key-post",
				);

				$data[] = array(
					$post_key,
					$user,
					'/?feed=rss&p=%ID%',
					'/?feed=rss&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?feed=rss&page_id=%ID%',
					'/?feed=rss&page_id=%ID%',
				);
			}
		}

		foreach ( $no_user_post_keys as $post_key ) {
			foreach ( $all_user_list as $user ) {
				$data[] = array(
					$post_key,
					$user,
					'/?p=%ID%',
					'/?p=%ID%',
				);

				$data[] = array(
					"$post_key",
					$user,
					'/?attachment_id=%ID-A%',
					'/?attachment_id=%ID-A%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?post_type=page&p=%ID%',
					'/?post_type=page&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?page_id=%ID%',
					'/?page_id=%ID%',
				);

				$data[] = array(
					$post_key,
					$user,
					"/?name=$post_key-post",
					"/?name=$post_key-post",
				);

				$data[] = array(
					$post_key,
					$user,
					'/?feed=rss&p=%ID%',
					'/?feed=rss&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?feed=rss&page_id=%ID%',
					'/?feed=rss&page_id=%ID%',
				);
			}
		}

		foreach ( array( 'trash' ) as $post_key ) {
			foreach ( $all_user_list as $user ) {
				$data[] = array(
					$post_key,
					$user,
					'/?p=%ID%',
					'/?p=%ID%',
				);

				$data[] = array(
					"$post_key",
					$user,
					'/?attachment_id=%ID-A%',
					'/trash-post-inherited-attachment/',
				);

				$data[] = array(
					"$post_key",
					$user,
					'/trash-post/trash-post-inherited-attachment/',
					'/trash-post-inherited-attachment/',
				);

				$data[] = array(
					"$post_key",
					$user,
					'/trash-post__trashed/trash-post-inherited-attachment/',
					'/trash-post-inherited-attachment/',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?post_type=page&p=%ID%',
					'/?post_type=page&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?page_id=%ID%',
					'/?page_id=%ID%',
				);

				$data[] = array(
					$post_key,
					$user,
					"/?name=$post_key-post",
					"/?name=$post_key-post",
				);

				$data[] = array(
					$post_key,
					$user,
					'/?feed=rss&p=%ID%',
					'/?feed=rss&p=%ID%',
				);

				$data[] = array(
					"page-$post_key",
					$user,
					'/?feed=rss&page_id=%ID%',
					'/?feed=rss&page_id=%ID%',
				);
			}
		}

		return $data;
	}
}
