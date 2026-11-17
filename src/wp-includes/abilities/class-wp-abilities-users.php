<?php
/**
 * Abilities API: WP_Abilities_Users class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.2.0
 */

declare( strict_types = 1 );

/**
 * Core class used to register user-related abilities.
 *
 * Registers the read-only `core/users-query` ability, which retrieves a paginated
 * list of readable WordPress users, optionally filtered by IDs, slugs, usernames,
 * roles, or published-post authorship. Field-level access is enforced per user by
 * omitting fields the current user cannot view.
 *
 * Also registers the `core/user-create`, `core/user-update`, and `core/user-delete`
 * abilities, which write a single user through Core's regular user functions and
 * return the written user in the same shape as `core/users-query`.
 *
 * Unlike the core abilities registered directly in wp_register_core_abilities() as
 * self-contained closures, the user abilities live in a dedicated class
 * because their callbacks and schemas share helpers: the callbacks read the input
 * through the same code, and the input schemas, output schemas, and field
 * normalization are built from the same field definitions.
 *
 * Only register() is public. The ability callbacks are closures that call private
 * methods, so callers go through the Abilities API, such as
 * `wp_get_ability( 'core/users-query' )->execute()`, which validates the input and
 * checks permissions before running them.
 *
 * @since 7.2.0
 * @since 7.3.0 Added the `core/user-create`, `core/user-update`, and `core/user-delete` abilities.
 *
 * @access private
 */
final class WP_Abilities_Users {

	/**
	 * The ability category used for user abilities.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	private const CATEGORY = 'user';

	/**
	 * Default number of users returned per page, unless `where` lists users by ID, slug, or username.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of users returned per page.
	 *
	 * Each identifier list in `where` holds at most this many values, so the users it
	 * lists fit on one page.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * The WP_User_Query argument that each identifier list in `where` sets.
	 *
	 * The lists filter the results but do not order them, as in the REST users controller,
	 * so results keep WP_User_Query's default order, by login.
	 *
	 * @since 7.2.0
	 * @var array<string, string>
	 */
	private const IDENTIFIER_LISTS = array(
		'ids'       => 'include',
		'slugs'     => 'nicename__in',
		'usernames' => 'login__in',
	);

	/**
	 * Default fields returned when the caller does not request a field subset.
	 *
	 * @since 7.2.0
	 * @var string[]
	 */
	private const DEFAULT_FIELDS = array(
		'id',
		'name',
		'link',
		'slug',
		'avatar_urls',
	);

	/**
	 * The user data key each profile attribute of the write abilities is saved as.
	 *
	 * The username and the email are not listed: they are passed to the saves separately.
	 *
	 * @since 7.3.0
	 * @var array<string, string>
	 */
	private const PROFILE_ATTRIBUTES = array(
		'name'        => 'display_name',
		'first_name'  => 'first_name',
		'last_name'   => 'last_name',
		'nickname'    => 'nickname',
		'slug'        => 'user_nicename',
		'description' => 'description',
		'url'         => 'user_url',
		'locale'      => 'locale',
	);

	/**
	 * Registers all user abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.2.0
	 * @since 7.3.0 Registers the `core/user-create`, `core/user-update`, and `core/user-delete` abilities.
	 */
	public function register(): void {
		$this->register_users_query();
		$this->register_user_create();
		$this->register_user_update();
		$this->register_user_delete();
	}

	/**
	 * Registers the read-only `core/users-query` ability.
	 *
	 * Unlike `core/get-user-info`, which only returns the current user's own profile,
	 * this ability is generic and reads any user the current user is allowed to see.
	 *
	 * @since 7.2.0
	 */
	private function register_users_query(): void {
		wp_register_ability(
			'core/users-query',
			array(
				'label'               => __( 'Query Users' ),
				'description'         => __( 'Retrieves a paginated list of readable WordPress users, optionally filtered by IDs, slugs, usernames, roles, or published-post authorship. Callers without permission to list users only see public authors. To read your own profile, use `core/get-user-info`. Requires an authenticated user.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_users_query_input_schema(),
				'output_schema'       => $this->get_users_query_output_schema(),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_users_query( $input );
				},
				'permission_callback' => function ( $input = array() ): bool {
					return $this->check_permission( $input );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Registers the `core/user-create` ability.
	 *
	 * @since 7.3.0
	 */
	private function register_user_create(): void {
		wp_register_ability(
			'core/user-create',
			array(
				'label'                 => __( 'Create User' ),
				'description'           => __( 'Creates a user and returns it. No password is set: WordPress generates one and emails the new user a link to choose their own, and the site administrator gets the usual new user notice. On multisite, the user becomes a member of the current site. Requires permission to create users.' ),
				'category'              => self::CATEGORY,
				'input_schema'          => $this->get_user_create_input_schema(),
				'output_schema'         => $this->get_user_item_schema(),
				'execute_callback'      => function ( $input = array() ) {
					return $this->execute_user_create( $input );
				},
				'required_capabilities' => array( 'create_users' ),
				'meta'                  => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Registers the `core/user-update` ability.
	 *
	 * @since 7.3.0
	 */
	private function register_user_update(): void {
		wp_register_ability(
			'core/user-update',
			array(
				'label'               => __( 'Update User' ),
				'description'         => __( 'Updates the given attributes of a user and returns the user. Attributes that are left out keep their stored values. Changing roles requires permission to promote the user; changing any other attribute requires permission to edit the user. Requires an authenticated user.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_user_update_input_schema(),
				'output_schema'       => $this->get_user_item_schema(),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_user_update( $input );
				},
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Registers the `core/user-delete` ability.
	 *
	 * @since 7.3.0
	 */
	private function register_user_delete(): void {
		wp_register_ability(
			'core/user-delete',
			array(
				'label'               => __( 'Delete User' ),
				'description'         => __( 'Deletes a user. `reassign` is required: the ID of the user who takes over the content of the deleted user, or false to delete the content with the user. With false, posts and pages go to the Trash where the site has one, and other content is deleted permanently (media too, unless the site keeps a Trash for media); posts of a type that is not deleted with its author, such as patterns, stay as they are. On multisite, the user is removed from the current site instead and the account stays in the network; there, false leaves the content in place. Requires permission to delete users, or on multisite to remove users.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_user_delete_input_schema(),
				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'outcome' ),
					'properties'           => array(
						'outcome' => array(
							'type'        => 'string',
							'description' => __( 'What happened to the user: `deleted`, or `removed` from the current site on multisite.' ),
						),
					),
				),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_user_delete( $input );
				},
				'permission_callback' => static function (): bool {
					return current_user_can( is_multisite() ? 'remove_users' : 'delete_users' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Permission callback for the `core/users-query` ability.
	 *
	 * Performs request-level checks only, and never looks users up. Which users are
	 * returned is decided by the query arguments in {@see self::execute_users_query()}.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	private function check_permission( $input = array() ): bool {
		$input = rest_sanitize_object( $input );

		if ( ! is_user_logged_in() ) {
			return false;
		}

		/*
		 * A `where` that is not an object is let through, so that the execute callback can
		 * report it as an invalid filter.
		 */
		$where = $this->get_input_object( $input, 'where' ) ?? array();

		// Roles and usernames are not public, so only callers who can list users may filter by them.
		return ( empty( $where['roles'] ) && empty( $where['usernames'] ) ) || current_user_can( 'list_users' );
	}

	/**
	 * Executes the `core/users-query` ability.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed>|WP_Error A page of user data, or a WP_Error on failure.
	 */
	private function execute_users_query( $input = array() ) {
		$input  = rest_sanitize_object( $input );
		$fields = $this->normalize_fields( $input );

		/*
		 * A `where` that is not an object cannot be honored. Reject it rather than reading it
		 * as no filter at all, which would widen the request to every readable user.
		 */
		$where = $this->get_input_object( $input, 'where' );
		if ( null === $where ) {
			return $this->invalid_filter_error( 'where', __( 'The where filter must be an object.' ) );
		}

		// Pagination that is not an object falls back to the defaults, like page values that cannot be parsed.
		$pagination = $this->get_input_object( $input, 'pagination' ) ?? array();
		$page       = $this->parse_filter_int( $pagination['page'] ?? 1, 1 ) ?? 1;

		/*
		 * On transports that skip schema validation, a filter that cannot be honored must
		 * not be dropped, which would silently widen the query. Reject it instead, so the
		 * query fails closed, as the content query does with its filters. WP_User_Query
		 * ignores an empty list, so a list with no valid value left would return every user.
		 */
		$query_args = array();
		foreach ( self::IDENTIFIER_LISTS as $key => $query_var ) {
			if ( ! isset( $where[ $key ] ) ) {
				continue;
			}

			$query_args[ $query_var ] = 'ids' === $key ? $this->normalize_id_list( $where[ $key ] ) : $this->normalize_string_list( $where[ $key ] );
			if ( empty( $query_args[ $query_var ] ) ) {
				return $this->invalid_filter_error(
					"where[{$key}]",
					/* translators: %s: The name of the filter, such as ids. */
					sprintf( __( 'The %s filter must list one or more valid values.' ), $key )
				);
			}
		}

		/*
		 * An explicit `per_page` always wins. Otherwise a request that lists users by ID, slug,
		 * or username returns every matching user on the first page, since the input schema
		 * caps each list at the maximum page size.
		 */
		$default_per_page     = empty( $query_args ) ? self::DEFAULT_PER_PAGE : self::MAX_PER_PAGE;
		$per_page             = min( self::MAX_PER_PAGE, $this->parse_filter_int( $pagination['per_page'] ?? null, 1 ) ?? $default_per_page );
		$query_args['number'] = $per_page;
		$query_args['paged']  = $page;

		if ( isset( $where['roles'] ) ) {
			$query_args['role__in'] = $this->normalize_string_list( $where['roles'] );
			if ( empty( $query_args['role__in'] ) ) {
				return $this->invalid_filter_error( 'where[roles]', __( 'The roles filter must list one or more role names.' ) );
			}
		}

		/*
		 * The permission callback already refuses the role and username filters to callers
		 * who cannot list users. Refuse them here too, rather than dropping them, which would
		 * widen the query to every public author, so a direct call fails closed.
		 */
		$can_list_users = current_user_can( 'list_users' );
		if ( isset( $where['roles'] ) && ! $can_list_users ) {
			return new WP_Error(
				'users_cannot_filter_by_role',
				__( 'Sorry, you are not allowed to filter users by role.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( isset( $where['usernames'] ) && ! $can_list_users ) {
			return new WP_Error(
				'users_cannot_filter_by_username',
				__( 'Sorry, you are not allowed to filter users by username.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$has_published_posts = $this->normalize_has_published_posts( $where );
		if ( array_key_exists( 'has_published_posts', $where ) && null === $has_published_posts ) {
			return $this->invalid_filter_error( 'where[has_published_posts]', __( 'The has_published_posts filter must be true or list one or more post type names.' ) );
		}

		/*
		 * Callers who cannot list users only see public authors, as in the REST users
		 * controller, so the filter is always applied for them. This intentionally excludes
		 * the caller's own account when they have no published posts, which
		 * `core/get-user-info` returns instead.
		 */
		if ( null !== $has_published_posts || ! $can_list_users ) {
			/*
			 * The post types are always resolved here rather than passed as `true`.
			 * WP_User_Query reads `true` as `get_post_types( array( 'public' => true ) )`,
			 * which is not the same as the publicly viewable set the rest of the ability
			 * uses. Resolving here also picks up post types registered or unregistered
			 * after the input schema was built.
			 */
			$public_post_types = $this->get_public_post_types();

			$has_published_posts = is_array( $has_published_posts )
				? array_values( array_intersect( $public_post_types, $has_published_posts ) )
				: $public_post_types;

			if ( empty( $has_published_posts ) ) {
				return $this->prepare_output( array(), 0, 0 );
			}

			$query_args['has_published_posts'] = $has_published_posts;
		}

		$query = new WP_User_Query( $query_args );

		/*
		 * The rows and the totals come from the same query, so they agree. As in the REST
		 * users controller, rows are not filtered by site membership afterwards: on
		 * multisite, WP_User_Query already limits the query to members of the current site.
		 */
		$total_users = $query->get_total();
		$total_pages = (int) ceil( $total_users / $per_page );

		/*
		 * A page past the last one does not exist, so report it as not found instead of
		 * returning a bare empty list. A genuinely empty result set still returns zero
		 * totals and no error.
		 */
		if ( $total_users > 0 && $page > $total_pages ) {
			return $this->invalid_page_number_error();
		}

		$users = array();
		foreach ( $query->get_results() as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			$users[] = $this->format_user( $user, $fields );
		}

		return $this->prepare_output( $users, $total_users, $total_pages );
	}

	/**
	 * Executes the `core/user-create` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input Optional. The ability input, as prepared by WP_Ability::execute(). Default empty array.
	 * @return array<string, mixed>|WP_Error The new user, or a WP_Error on failure.
	 */
	private function execute_user_create( array $input = array() ) {
		// Checked again after the gate, whose result a filter can change.
		if ( ! current_user_can( 'create_users' ) ) {
			return new WP_Error(
				'user_cannot_create',
				__( 'Sorry, you are not allowed to create new users.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$data  = $input['data'] ?? array();
		$roles = null;
		if ( array_key_exists( 'roles', $data ) ) {
			$roles = $this->check_roles( $data['roles'], null );
			if ( is_wp_error( $roles ) ) {
				return $roles;
			}
		}

		$error = $this->check_attributes( $data, null );
		if ( $error ) {
			return $error;
		}

		$password = wp_generate_password( 24 );
		$profile  = $this->get_profile_userdata( $data );

		if ( is_multisite() ) {
			$signup = wpmu_validate_user_signup( $data['username'], $data['email'] );
			if ( is_wp_error( $signup['errors'] ) && $signup['errors']->has_errors() ) {
				$codes = $signup['errors']->get_error_codes();
				$param = 'data';
				$code  = $codes[0];
				$paths = array(
					'user_name'  => 'data[username]',
					'user_email' => 'data[email]',
				);
				foreach ( $paths as $field_code => $path ) {
					if ( in_array( $field_code, $codes, true ) ) {
						$param = $path;
						$code  = $field_code;
						break;
					}
				}

				return $this->invalid_attribute_error( $param, $signup['errors']->get_error_message( $code ) );
			}

			$user_id = wpmu_create_user( $data['username'], $password, $data['email'] );
			if ( ! $user_id ) {
				return $this->create_failed_error();
			}

			$result = empty( $profile ) ? true : wp_update_user( array( 'ID' => $user_id ) + $profile );
			if ( ! is_wp_error( $result ) ) {
				$result = add_user_to_blog( get_current_blog_id(), $user_id, '' );
			}

			if ( is_wp_error( $result ) ) {
				/*
				 * Undo the network account this call created, and only that one, so a failed
				 * call leaves the number of users unchanged. The first error is returned
				 * whatever the cleanup returns.
				 */
				require_once ABSPATH . 'wp-admin/includes/ms.php';
				wpmu_delete_user( $user_id );

				return $this->save_error( $result, array_keys( $data ) );
			}
		} else {
			$userdata = array(
				'user_login' => wp_slash( $data['username'] ),
				'user_email' => wp_slash( $data['email'] ),
				'user_pass'  => $password,
			) + $profile;

			// The roles are set after the save, so the user never holds the default role.
			if ( null !== $roles ) {
				$userdata['role'] = '';
			}

			$user_id = wp_insert_user( $userdata );
			if ( is_wp_error( $user_id ) ) {
				return $this->save_error( $user_id, array_keys( $data ) );
			}
			if ( ! $user_id ) {
				return $this->create_failed_error();
			}
		}

		if ( null !== $roles ) {
			$this->set_user_roles( $user_id, $roles );
		}

		wp_send_new_user_notifications( $user_id, 'both' );

		return $this->format_written_user( $user_id, $input );
	}

	/**
	 * Executes the `core/user-update` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input Optional. The ability input, as prepared by WP_Ability::execute(). Default empty array.
	 * @return array<string, mixed>|WP_Error The updated user, or a WP_Error on failure.
	 */
	private function execute_user_update( array $input = array() ) {
		// Checked again after the gate, whose result a filter can change.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'user_cannot_edit',
				__( 'Sorry, you are not allowed to edit this user.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$target = $this->get_target_user( $input );
		if ( ! $target ) {
			return $this->not_found_error();
		}

		// Only the keys of `data` choose the capability, before any value is read.
		$data = $input['data'] ?? array();
		$keys = array_keys( $data );

		if ( in_array( 'roles', $keys, true ) && ! current_user_can( 'promote_user', $target->ID ) ) {
			return new WP_Error(
				'user_cannot_edit_roles',
				__( 'Sorry, you are not allowed to edit roles of this user.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( array( 'roles' ) !== $keys && ! current_user_can( 'edit_user', $target->ID ) ) {
			return new WP_Error(
				'user_cannot_edit',
				__( 'Sorry, you are not allowed to edit this user.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$attributes = array_merge( array( 'email', 'roles' ), array_keys( self::PROFILE_ATTRIBUTES ) );
		$sent       = array_values( array_intersect( $keys, $attributes ) );
		if ( empty( $sent ) ) {
			return $this->get_validation_error(
				'data',
				array(),
				array(
					'type'          => 'object',
					'minProperties' => 1,
				)
			);
		}

		$roles = null;
		if ( array_key_exists( 'roles', $data ) ) {
			$roles = $this->check_roles( $data['roles'], $target );
			if ( is_wp_error( $roles ) ) {
				return $roles;
			}
		}

		$error = $this->check_attributes( $data, $target );
		if ( $error ) {
			return $error;
		}

		// The save never receives a role or a username.
		$userdata = $this->get_profile_userdata( $data );
		if ( array_key_exists( 'email', $data ) ) {
			$userdata['user_email'] = wp_slash( $data['email'] );
		}

		if ( ! empty( $userdata ) ) {
			$result = wp_update_user( array( 'ID' => $target->ID ) + $userdata );
			if ( is_wp_error( $result ) ) {
				return $this->save_error( $result, $sent );
			}
		}

		if ( null !== $roles ) {
			$this->set_user_roles( $target->ID, $roles );
		}

		return $this->format_written_user( $target->ID, $input );
	}

	/**
	 * Executes the `core/user-delete` ability.
	 *
	 * On multisite, the user is removed from the current site, and the network account stays.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input Optional. The ability input, as prepared by WP_Ability::execute(). Default empty array.
	 * @return array<string, string>|WP_Error The outcome, or a WP_Error on failure.
	 */
	private function execute_user_delete( array $input = array() ) {
		// Checked again after the gate, whose result a filter can change.
		if ( ! current_user_can( is_multisite() ? 'remove_users' : 'delete_users' ) ) {
			return $this->cannot_delete_error();
		}

		$target = $this->get_target_user( $input );
		if ( ! $target ) {
			return $this->not_found_error();
		}

		if ( ! current_user_can( is_multisite() ? 'remove_user' : 'delete_user', $target->ID ) ) {
			return $this->cannot_delete_error();
		}

		// Only `false` reassigns nothing. Every other value must name a user.
		$reassign = $input['reassign'] ?? null;
		if ( false !== $reassign ) {
			$reassign_user = is_int( $reassign ) && $reassign >= 1 ? get_userdata( $reassign ) : false;

			/*
			 * Intentionally strict: on multisite, the user who takes over the content must be a
			 * member of the current site, although the removal would hand it to any user.
			 */
			if ( ! $reassign_user || $reassign_user->ID === $target->ID || ! is_user_member_of_blog( $reassign_user->ID ) ) {
				return $this->invalid_attribute_error( 'reassign', __( 'Invalid user ID for reassignment.' ), 'user_invalid_reassign' );
			}

			$reassign = $reassign_user->ID;
		}

		// The stored state decides the outcome, not the return value of the deletion.
		if ( is_multisite() ) {
			remove_user_from_blog( $target->ID, get_current_blog_id(), false === $reassign ? 0 : $reassign );

			$outcome = 'removed';
			$done    = ! is_user_member_of_blog( $target->ID );
		} else {
			require_once ABSPATH . 'wp-admin/includes/user.php';

			if ( false === $reassign ) {
				wp_delete_user( $target->ID );
			} else {
				wp_delete_user( $target->ID, $reassign );
			}

			$outcome = 'deleted';
			$done    = ! get_userdata( $target->ID );
		}

		if ( ! $done ) {
			return new WP_Error(
				'user_delete_failed',
				__( 'The user cannot be deleted.' ),
				array( 'status' => 500 )
			);
		}

		return array( 'outcome' => $outcome );
	}

	/**
	 * Looks up the user that `where.id` names.
	 *
	 * The ID is read once, and the returned user is the one every later check and write
	 * uses. On multisite, a user who is not a member of the current site is not returned,
	 * for every caller, so it cannot be told apart from an ID that does not exist.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return WP_User|null The user, or null when the ID is invalid or names no member of the current site.
	 */
	private function get_target_user( array $input ): ?WP_User {
		$id   = $input['where']['id'] ?? 0;
		$user = $id >= 1 ? get_userdata( $id ) : false;

		return $user && is_user_member_of_blog( $user->ID ) ? $user : null;
	}

	/**
	 * Checks the roles to assign.
	 *
	 * Runs for each role in list order, whatever the input schema allowed, since a role
	 * can be unregistered, or the editable roles narrowed, after the schema was built.
	 *
	 * @since 7.3.0
	 *
	 * @param string[]     $roles  The `roles` input.
	 * @param WP_User|null $target The user to update, or null on create.
	 * @return string[]|WP_Error The roles, or a WP_Error for the first role that cannot be assigned.
	 */
	private function check_roles( array $roles, ?WP_User $target ) {
		$error = $this->get_validation_error(
			'data[roles]',
			$roles,
			array(
				'type'     => 'array',
				'minItems' => 1,
			)
		);
		if ( $error ) {
			return $error;
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$editable_roles = get_editable_roles();

		// Users cannot give themselves a role that would take away their right to edit users.
		$is_own_role_change = $target && get_current_user_id() === $target->ID && ! ( is_multisite() && current_user_can( 'manage_sites' ) );

		foreach ( $roles as $role ) {
			$role_object = get_role( $role );
			if ( ! $role_object ) {
				return $this->invalid_attribute_error(
					'data[roles]',
					/* translators: %s: Role key. */
					sprintf( __( 'The role %s does not exist.' ), $role )
				);
			}

			// A role with an empty entry in the editable roles is not editable, like a missing one.
			if ( ( $is_own_role_change && ! $role_object->has_cap( 'edit_users' ) ) || empty( $editable_roles[ $role ] ) ) {
				return new WP_Error(
					'user_cannot_assign_role',
					__( 'Sorry, you are not allowed to give users that role.' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}
		}

		return $roles;
	}

	/**
	 * Checks the attributes to save, in a fixed order.
	 *
	 * The checks that depend on the save's sanitizing compute the value as the save
	 * does. A value that ends empty, too long or taken is refused here; everything else
	 * is stored as the save sanitizes it.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data   The `data` input.
	 * @param WP_User|null $target The user to update, or null on create.
	 * @return WP_Error|null A WP_Error for the first attribute that cannot be saved, or null.
	 */
	private function check_attributes( array $data, ?WP_User $target ): ?WP_Error {
		if ( ! $target ) {
			$error = $this->get_validation_error(
				'data[username]',
				$data,
				array(
					'type'     => 'object',
					'required' => array( 'username' ),
				),
				'data'
			);
			if ( $error ) {
				return $error;
			}

			$username = $data['username'];

			/*
			 * Intentionally strict: a username that strict sanitizing would change is refused,
			 * also when a filter of validate_username() accepts it.
			 */
			if ( sanitize_user( $username, true ) !== $username || ! validate_username( $username ) ) {
				return $this->invalid_attribute_error( 'data[username]', __( 'This username is invalid because it uses illegal characters. Please enter a valid username.' ) );
			}

			/** This filter is documented in wp-includes/user.php */
			$illegal_logins = array_map( 'strtolower', (array) apply_filters( 'illegal_user_logins', array() ) );
			if ( in_array( strtolower( $username ), $illegal_logins, true ) ) {
				return $this->invalid_attribute_error( 'data[username]', __( 'Sorry, that username is not allowed.' ) );
			}

			if ( mb_strlen( $username ) > 60 ) {
				return $this->invalid_attribute_error( 'data[username]', __( 'Username may not be longer than 60 characters.' ) );
			}

			if ( username_exists( $username ) ) {
				return $this->invalid_attribute_error( 'data[username]', __( 'Sorry, that username already exists!' ), 'user_exists' );
			}

			$error = $this->get_validation_error(
				'data[email]',
				$data,
				array(
					'type'     => 'object',
					'required' => array( 'email' ),
				),
				'data'
			);
			if ( $error ) {
				return $error;
			}
		}

		if ( array_key_exists( 'email', $data ) ) {
			if ( ! is_email( $data['email'] ) ) {
				return $this->invalid_attribute_error( 'data[email]', __( 'Invalid email address.' ) );
			}

			/*
			 * Intentionally strict: an address that the save would store differently from the
			 * address sent is refused, rather than stored changed.
			 */
			/** This filter is documented in wp-includes/user.php */
			if ( wp_unslash( apply_filters( 'pre_user_email', wp_slash( $data['email'] ) ) ) !== $data['email'] ) {
				return $this->invalid_attribute_error( 'data[email]', __( 'Invalid email address.' ) );
			}

			// Intentionally strict: the address is refused past the column size rather than cut short.
			$error = $this->get_validation_error(
				'data[email]',
				$data['email'],
				array(
					'type'      => 'string',
					'maxLength' => 100,
				)
			);
			if ( $error ) {
				return $error;
			}

			// The address of another user is refused, the target's own is not.
			$email_user_id = email_exists( $data['email'] );
			if ( $email_user_id && ( ! $target || $email_user_id !== $target->ID ) ) {
				return $this->invalid_attribute_error( 'data[email]', __( 'Sorry, that email address is already used!' ), 'user_exists' );
			}
		}

		if ( array_key_exists( 'name', $data ) ) {
			/*
			 * Intentionally strict: the name is counted as the save would store it, and refused
			 * when that is empty or past the column size, rather than saved empty or cut short.
			 */
			/** This filter is documented in wp-includes/user.php */
			$name  = wp_unslash( apply_filters( 'pre_user_display_name', wp_slash( $data['name'] ) ) );
			$error = $this->get_validation_error(
				'data[name]',
				$name,
				array(
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 250,
				)
			);
			if ( $error ) {
				return $error;
			}
		}

		if ( array_key_exists( 'nickname', $data ) ) {
			// Intentionally strict: a nickname with nothing left after sanitizing is refused rather than saved empty.
			$error = $this->get_validation_error(
				'data[nickname]',
				sanitize_text_field( $data['nickname'] ),
				array(
					'type'      => 'string',
					'minLength' => 1,
				)
			);
			if ( $error ) {
				return $error;
			}
		}

		if ( array_key_exists( 'slug', $data ) ) {
			/** This filter is documented in wp-includes/user.php */
			$slug = (string) apply_filters( 'pre_user_nicename', sanitize_title( sanitize_user( wp_slash( $data['slug'] ), true ) ) );
			if ( '' === $slug ) {
				return $this->invalid_attribute_error( 'data[slug]', __( 'Invalid slug.' ) );
			}

			if ( mb_strlen( $slug ) > 50 ) {
				return $this->invalid_attribute_error( 'data[slug]', __( 'Nicename may not be longer than 50 characters.' ) );
			}

			// Intentionally strict: the slug of another user is refused rather than saved with a numeric suffix.
			$slug_user = get_user_by( 'slug', $slug );
			if ( $slug_user && ( ! $target || $slug_user->ID !== $target->ID ) ) {
				return $this->invalid_attribute_error( 'data[slug]', __( 'Invalid slug.' ) );
			}
		}

		if ( array_key_exists( 'url', $data ) && '' !== $data['url'] ) {
			/** This filter is documented in wp-includes/user.php */
			$url = (string) apply_filters( 'pre_user_url', wp_slash( $data['url'] ) );

			// Intentionally strict: a URL with nothing left after sanitizing is refused rather than saved empty.
			$error = $this->get_validation_error(
				'data[url]',
				$url,
				array(
					'type'      => 'string',
					'minLength' => 1,
				)
			);
			if ( $error ) {
				return $error;
			}

			if ( mb_strlen( $url ) > 100 ) {
				return $this->invalid_attribute_error( 'data[url]', __( 'User URL may not be longer than 100 characters.' ) );
			}
		}

		if ( array_key_exists( 'locale', $data ) ) {
			$error = $this->get_validation_error(
				'data[locale]',
				$data['locale'],
				array(
					'type' => 'string',
					'enum' => $this->get_locales(),
				)
			);
			if ( $error ) {
				return $error;
			}
		}

		return null;
	}

	/**
	 * Builds the user data for a save from the profile attributes, key by key.
	 *
	 * Values are slashed, since the saves expect slashed data.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data The `data` input.
	 * @return array<string, mixed> The user data, keyed as the saves expect.
	 */
	private function get_profile_userdata( array $data ): array {
		$userdata = array();
		foreach ( self::PROFILE_ATTRIBUTES as $name => $key ) {
			if ( array_key_exists( $name, $data ) ) {
				$userdata[ $key ] = wp_slash( $data[ $name ] );
			}
		}

		return $userdata;
	}

	/**
	 * Gives a user exactly the given roles, in the given order.
	 *
	 * @since 7.3.0
	 *
	 * @param int      $user_id The user ID.
	 * @param string[] $roles   The roles, at least one.
	 */
	private function set_user_roles( int $user_id, array $roles ): void {
		$user = new WP_User( $user_id );
		$user->set_role( (string) array_shift( $roles ) );
		foreach ( $roles as $role ) {
			$user->add_role( $role );
		}
	}

	/**
	 * Formats a user that was just written.
	 *
	 * @since 7.3.0
	 *
	 * @param int          $user_id The user ID.
	 * @param array<mixed> $input   The ability input.
	 * @return array<string, mixed> The formatted user data.
	 */
	private function format_written_user( int $user_id, array $input ): array {
		/*
		 * Intentionally strict: a caller who may neither edit this user nor list users gets
		 * the ID only, so a write never shows a user that the caller could not otherwise read.
		 */
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_user', $user_id ) && ! current_user_can( 'list_users' ) ) {
			return array( 'id' => $user_id );
		}

		return $this->format_user( new WP_User( $user_id ), $this->normalize_fields( $input ) );
	}

	/**
	 * Builds the error for an error that a save returned.
	 *
	 * An error about an attribute that was sent is reported for that attribute. Every
	 * other error keeps its code, message and data, carries no `params`, and gets status
	 * 500 when it has no status yet.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Error $error The error the save returned.
	 * @param string[] $sent  The names of the attributes that were sent.
	 * @return WP_Error The error to return.
	 */
	private function save_error( WP_Error $error, array $sent ): WP_Error {
		$attributes = array(
			'existing_user_login'    => 'username',
			'empty_user_login'       => 'username',
			'user_login_too_long'    => 'username',
			'invalid_username'       => 'username',
			'existing_user_email'    => 'email',
			'empty_user_nicename'    => 'slug',
			'user_nicename_too_long' => 'slug',
			'user_url_too_long'      => 'url',
		);

		$code = (string) $error->get_error_code();
		if ( isset( $attributes[ $code ] ) && in_array( $attributes[ $code ], $sent, true ) ) {
			return $this->invalid_attribute_error(
				"data[{$attributes[ $code ]}]",
				$error->get_error_message(),
				str_starts_with( $code, 'existing_' ) ? 'user_exists' : 'user_invalid_attribute'
			);
		}

		$data = $error->get_error_data();
		$error->add_data( array_merge( array( 'status' => 500 ), is_array( $data ) ? $data : array() ) );

		return $error;
	}

	/**
	 * Returns the locales a user can be given: the site default, `en_US`, and the installed languages.
	 *
	 * @since 7.3.0
	 *
	 * @return string[] The locales.
	 */
	private function get_locales(): array {
		return array_values( array_unique( array_merge( array( '', 'en_US' ), get_available_languages() ) ) );
	}

	/**
	 * Reads an object member of the input, such as `where` or `pagination`.
	 *
	 * Accepts what schema validation reads as an object: an array, a `stdClass`, or an
	 * empty string. Only the REST run controller converts input to the schema types, so
	 * callers that bypass it, such as a direct WP_Ability::execute() call, can pass any of them.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @param string       $key   The member name.
	 * @return array<mixed>|null The member as an array, an empty array when it is omitted,
	 *                           or null when it is not an object.
	 */
	private function get_input_object( array $input, string $key ): ?array {
		if ( ! array_key_exists( $key, $input ) ) {
			return array();
		}

		return rest_is_object( $input[ $key ] ) ? rest_sanitize_object( $input[ $key ] ) : null;
	}

	/**
	 * Parses a raw input value into an integer of at least a minimum, or null when invalid.
	 *
	 * Accepts the whole numbers that the integer schema accepts: native integers, floats such
	 * as the 2.0 that JSON encoders produce, and integer strings such as "2", "2.0", or "+2".
	 * Only the REST run controller converts input to the schema types, so other callers, such
	 * as the MCP adapter or a direct WP_Ability::execute() call, pass them on unconverted.
	 * Anything else, such as a fraction, is rejected rather than coerced: a malformed ID would
	 * otherwise resolve to another user.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $value The raw input value.
	 * @param int   $min   The smallest acceptable value.
	 * @return int|null The parsed integer, or null when the value is not an integer >= $min.
	 */
	private function parse_filter_int( $value, int $min ): ?int {
		if ( is_int( $value ) ) {
			return $value >= $min ? $value : null;
		}

		if ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
			$int = (int) $value;

			return $int >= $min ? $int : null;
		}

		if ( ! rest_is_integer( $value ) ) {
			return null;
		}

		/*
		 * Other whole numbers are read as floats, which hold every integer only up to 2 ** 53.
		 * Past that, the value may not be the one that was sent, and casting a float beyond the
		 * integer range wraps it to an arbitrary integer, such as the ID of another user.
		 */
		$number = (float) $value;

		return $number >= $min && $number <= 2 ** 53 ? (int) $number : null;
	}

	/**
	 * Returns publicly viewable post types.
	 *
	 * Uses {@see is_post_type_viewable()} rather than the `public` registration
	 * argument, since `public` alone does not guarantee a post type is viewable
	 * on the front end. Deliberately resolved on every call rather than cached:
	 * post types can be unregistered or re-registered with different arguments
	 * between the ability being registered and the ability being used.
	 *
	 * @since 7.2.0
	 *
	 * @return string[] Publicly viewable post type names.
	 */
	private function get_public_post_types(): array {
		return array_values( array_filter( get_post_types(), 'is_post_type_viewable' ) );
	}

	/**
	 * Returns the requested field paths, or a lean default set when none are given.
	 *
	 * An empty or absent `fields` value selects a lean set of common read fields.
	 * Otherwise the requested paths are returned. The input schema has already
	 * validated them against the paths from {@see self::get_field_paths()} before the
	 * ability executes, and {@see self::format_user()} ignores any other path.
	 *
	 * The `id` field is always included, matching the REST users controller
	 * where `id` is present in every context. This also guarantees the result
	 * is never empty, so it always serializes as a JSON object.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return string[] List of requested field paths.
	 */
	private function normalize_fields( array $input ): array {
		$fields = isset( $input['fields'] ) ? $this->normalize_string_list( $input['fields'] ) : array();
		if ( empty( $fields ) ) {
			$fields = self::DEFAULT_FIELDS;
		}

		if ( ! in_array( 'id', $fields, true ) ) {
			array_unshift( $fields, 'id' );
		}

		return $fields;
	}

	/**
	 * Returns the user field definitions, keyed by field name in output order.
	 *
	 * This is the single source of truth for the ability's user fields: the output
	 * schema uses the definitions directly, while the input schema `fields` enum lists
	 * each field, followed by the path of each of its sub-fields, such as
	 * `registered.gmt`. The field set is deliberately unconditional:
	 * the registered schemas are a registration-time snapshot, so conditional
	 * availability (such as `avatar_urls` honoring the `show_avatars` option) is
	 * enforced per call in {@see self::format_user()} instead of here, where the
	 * option could change between registration and use.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> User field definitions.
	 */
	private function get_user_properties(): array {
		return array(
			'id'          => array(
				'type'        => 'integer',
				'description' => __( 'The user ID.' ),
			),
			'name'        => array(
				'type'        => 'string',
				'description' => __( 'The display name for the user.' ),
			),
			'description' => array(
				'type'        => 'string',
				'description' => __( 'Description of the user. Present when the current user can view it.' ),
			),
			'url'         => array(
				/*
				 * Unlike the REST users controller, `url` declares no `uri` format. It is
				 * empty for users without a website, and clients that check formats,
				 * such as the abilities JS client when it re-validates the output, would
				 * reject the empty string and fail the whole call.
				 */
				'type'        => 'string',
				'description' => __( 'URL of the user. Present when the current user can view it.' ),
			),
			'link'        => array(
				'type'        => 'string',
				'format'      => 'uri',
				'description' => __( 'Author archive URL for the user.' ),
			),
			'slug'        => array(
				'type'        => 'string',
				'description' => __( 'An alphanumeric identifier for the user.' ),
			),
			'avatar_urls' => array(
				'type'                 => 'object',
				'description'          => __( 'Avatar URLs for the user, keyed by image size in pixels. A size is null when no avatar URL can be resolved for it. Present when the show_avatars option is enabled.' ),
				'additionalProperties' => array(
					'type'   => array( 'string', 'null' ),
					'format' => 'uri',
				),
			),
			'username'    => array(
				'type'        => 'string',
				'description' => __( 'Login name for the user. Present when the current user can view it.' ),
			),
			'email'       => array(
				'type'        => array( 'string', 'null' ),
				'format'      => 'email',
				'description' => __( 'The email address for the user. Null when the user has no stored address, or when the stored address is not a valid email. Present when the current user can view it.' ),
			),
			'first_name'  => array(
				'type'        => 'string',
				'description' => __( 'First name for the user. Present when the current user can view it.' ),
			),
			'last_name'   => array(
				'type'        => 'string',
				'description' => __( 'Last name for the user. Present when the current user can view it.' ),
			),
			'nickname'    => array(
				'type'        => 'string',
				'description' => __( 'The nickname for the user. Present when the current user can view it.' ),
			),
			'locale'      => array(
				'type'        => 'string',
				'description' => __( 'Locale for the user. Present when the current user can view it.' ),
			),
			'registered'  => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'site' => array(
						'type'        => array( 'string', 'null' ),
						'format'      => 'date-time',
						'description' => __( "The registration date, in ISO 8601 format using the site's timezone. Null when the stored date is not a valid date." ),
					),
					'gmt'  => array(
						'type'        => array( 'string', 'null' ),
						'format'      => 'date-time',
						'description' => __( 'The registration date, in ISO 8601 format as GMT. Null when the stored date is not a valid date.' ),
					),
				),
				'description'          => __( 'The registration date for the user. Present when the current user can view it.' ),
			),
			'roles'       => array(
				'type'        => 'array',
				'description' => __( 'Roles assigned to the user. Present when the current user can view them.' ),
				/*
				 * Output roles are not pinned to an enum. The schema is a
				 * registration-time snapshot, but a role can be registered after
				 * registration and still be held by a returned user; a snapshot enum
				 * would reject that legitimate value during output validation and
				 * fail the whole call. This also matches the REST users controller,
				 * whose `roles` output items are plain strings.
				 */
				'items'       => array(
					'type' => 'string',
				),
			),
		);
	}

	/**
	 * Returns the field paths the `fields` input accepts, in output order.
	 *
	 * Each user field is followed by the path of each sub-field its definition declares,
	 * such as `registered`, `registered.site` and `registered.gmt`. Requesting a field
	 * returns all of its sub-fields, while a sub-field path returns only that sub-field.
	 *
	 * @since 7.2.0
	 *
	 * @return string[] The field paths.
	 */
	private function get_field_paths(): array {
		$paths = array();
		foreach ( $this->get_user_properties() as $name => $schema ) {
			$paths[] = $name;

			foreach ( array_keys( $schema['properties'] ?? array() ) as $sub_field ) {
				$paths[] = "{$name}.{$sub_field}";
			}
		}

		return $paths;
	}

	/**
	 * Checks whether a field path is requested.
	 *
	 * A field such as `registered` is requested when it or one of its paths, such as
	 * `registered.gmt`, is requested, and a path such as `registered.gmt` is requested
	 * when it or its whole field is.
	 *
	 * @since 7.2.0
	 *
	 * @param string   $path   The field path, such as `registered` or `registered.gmt`.
	 * @param string[] $fields The requested field paths.
	 * @return bool Whether the field path is requested.
	 */
	private function is_field_requested( string $path, array $fields ): bool {
		return rest_is_field_included( $path, $fields );
	}

	/**
	 * Normalizes a mixed value into a list of user IDs.
	 *
	 * Accepts a list, a single ID, or a comma-separated string, which it parses with
	 * wp_parse_list(), as schema validation does. Each item is read with parse_filter_int(),
	 * so an item that is not a whole number of at least 1, such as a fraction or a negative
	 * number, is dropped rather than truncated or made positive, which would read it as the
	 * ID of another user.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $value Raw value.
	 * @return int[] Unique user IDs.
	 */
	private function normalize_id_list( $value ): array {
		$items = is_array( $value ) || is_string( $value ) ? wp_parse_list( $value ) : array( $value );
		$ids   = array();
		foreach ( $items as $item ) {
			$ids[] = $this->parse_filter_int( $item, 1 );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Normalizes a mixed value into a list of strings.
	 *
	 * Accepts arrays and CSV strings, which it parses with wp_parse_list(), as schema
	 * validation does. Schema validation accepts a CSV string for an array, and only the
	 * REST run controller converts input to the schema types, so callers that bypass it,
	 * such as a direct WP_Ability::execute() call, can pass one. Empty and duplicate
	 * items need no handling here, because validation has already rejected them.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $value Raw value.
	 * @return string[] Normalized strings.
	 */
	private function normalize_string_list( $value ): array {
		if ( ! is_array( $value ) && ! is_string( $value ) ) {
			return array();
		}

		return array_values( array_filter( wp_parse_list( $value ), 'is_string' ) );
	}

	/**
	 * Normalizes the `has_published_posts` filter.
	 *
	 * Accepts the string and integer forms of `true` alongside the native boolean.
	 * Schema validation accepts them, and only the REST run controller converts input
	 * to the schema types, so callers that bypass it, such as a direct
	 * WP_Ability::execute() call, can pass one.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $where The `where` filters.
	 * @return bool|string[]|null Normalized query value, or null when omitted.
	 */
	private function normalize_has_published_posts( array $where ) {
		if ( ! array_key_exists( 'has_published_posts', $where ) ) {
			return null;
		}

		$value = $where['has_published_posts'];

		if ( rest_is_boolean( $value ) && rest_sanitize_boolean( $value ) ) {
			return true;
		}

		$post_types = $this->normalize_string_list( $value );

		return empty( $post_types ) ? null : $post_types;
	}

	/**
	 * Builds the input schema for the `core/users-query` ability.
	 *
	 * The input has three members: `where` selects the users, `fields` selects the fields
	 * returned for each user, and `pagination` selects the page of results. The `where`
	 * filters can be combined in any way, and a user must match all of them.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_users_query_input_schema(): array {
		/*
		 * Input enums intentionally reflect roles and post types available at
		 * ability registration time. This makes the schema a stable contract that
		 * developers can filter when registering the ability.
		 */
		$role_names        = array_keys( wp_roles()->roles );
		$public_post_types = $this->get_public_post_types();

		return array(
			'type'                 => 'object',
			'default'              => array(),
			'additionalProperties' => false,
			'properties'           => array(
				'where'      => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'ids'                 => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'maxItems'    => self::MAX_PER_PAGE,
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'description' => __( 'Limit results to users with these IDs. If `pagination.per_page` is omitted, every matching user is returned on the first page.' ),
						),
						'slugs'               => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'maxItems'    => self::MAX_PER_PAGE,
							'items'       => array(
								'type' => 'string',
							),
							'description' => __( 'Limit results to users with these slugs. If `pagination.per_page` is omitted, every matching user is returned on the first page.' ),
						),
						'usernames'           => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'maxItems'    => self::MAX_PER_PAGE,
							'items'       => array(
								'type' => 'string',
							),
							'description' => __( 'Limit results to users with these usernames. Requires permission to list users. If `pagination.per_page` is omitted, every matching user is returned on the first page.' ),
						),
						'roles'               => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'items'       => array(
								'type' => 'string',
								'enum' => $role_names,
							),
							'description' => __( 'Filter users by one or more roles. Requires permission to list users.' ),
						),
						'has_published_posts' => array(
							'anyOf'       => array(
								// True matches authors of any publicly viewable post type.
								array(
									'type' => 'boolean',
									'enum' => array( true ),
								),
								// A list matches authors of the listed post types.
								array(
									'type'        => 'array',
									'uniqueItems' => true,
									'minItems'    => 1,
									'items'       => array(
										'type' => 'string',
										'enum' => $public_post_types,
									),
								),
							),
							'description' => __( 'Limit results to users with published posts. Use true for all publicly viewable post types, or provide post type names.' ),
						),
					),
					'description'          => __( 'Selects the users to return. Every given filter must match, and a list matches users with any of its values. Results are ordered by username, and values that match no readable user are left out rather than causing an error. If omitted, every readable user is returned. Callers without permission to list users only see public authors, who have published posts in a publicly viewable post type. To read your own profile, use the `core/get-user-info` ability.' ),
				),
				'fields'     => $this->get_fields_input_schema( __( 'Limit each returned user to these fields. A field such as `registered` returns all of its sub-fields, while a path such as `registered.gmt` returns only that sub-field. If omitted or empty, a lean set of common read fields is returned. The `id` field is always included, and fields the current user cannot view are omitted rather than causing an error.' ) ),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'page'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Page of results to return. Requesting a page beyond the last one is an error. Check `pagination.total_pages` in the result before requesting later pages.' ),
						),
						'per_page' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => self::MAX_PER_PAGE,
							'description' => __( 'Maximum number of users to return per page.' ),
						),
					),
					'description'          => __( 'Selects the page of results to return.' ),
				),
			),
		);
	}

	/**
	 * Builds the output schema for the `core/users-query` ability.
	 *
	 * Every request returns the same shape: a page of users in `items`, and the totals
	 * across all pages in `pagination`.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_users_query_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'items', 'pagination' ),
			'properties'           => array(
				'items'      => array(
					'type'        => 'array',
					'description' => __( 'The readable users on the requested page.' ),
					'items'       => $this->get_user_item_schema(),
				),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'total', 'total_pages' ),
					'properties'           => array(
						'total'       => array(
							'type'        => 'integer',
							'description' => __( 'Total number of users matching the request, across all pages.' ),
						),
						'total_pages' => array(
							'type'        => 'integer',
							'description' => __( 'Total number of result pages available for the request.' ),
						),
					),
					'description'          => __( 'The totals of the request, across all pages.' ),
				),
			),
		);
	}

	/**
	 * Formats a user into the ability output shape.
	 *
	 * Fields are selected with {@see self::is_field_requested()}, so `registered` returns
	 * both of its sub-fields, while `registered.site` or `registered.gmt` returns only that
	 * one. Only the requested fields the current user can see are included, except `id`,
	 * which {@see self::normalize_fields()} always requests.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_User  $user   The user object.
	 * @param string[] $fields The requested field paths.
	 * @return array<string, mixed> The formatted user data.
	 */
	private function format_user( WP_User $user, array $fields ): array {
		$can_view_sensitive = get_current_user_id() === $user->ID || current_user_can( 'edit_user', $user->ID );

		$data = array();

		if ( $this->is_field_requested( 'id', $fields ) ) {
			$data['id'] = $user->ID;
		}
		if ( $this->is_field_requested( 'name', $fields ) ) {
			$data['name'] = (string) $user->display_name;
		}
		if ( $this->is_field_requested( 'description', $fields ) ) {
			$data['description'] = (string) $user->description;
		}
		if ( $this->is_field_requested( 'url', $fields ) ) {
			$data['url'] = (string) $user->user_url;
		}
		if ( $this->is_field_requested( 'link', $fields ) ) {
			$data['link'] = (string) get_author_posts_url( $user->ID, $user->user_nicename );
		}
		if ( $this->is_field_requested( 'slug', $fields ) ) {
			$data['slug'] = (string) $user->user_nicename;
		}
		/*
		 * The schemas always declare avatar_urls; availability is enforced here,
		 * since the option can change after the schemas are registered.
		 */
		if ( $this->is_field_requested( 'avatar_urls', $fields ) && get_option( 'show_avatars' ) ) {
			$data['avatar_urls'] = array_map(
				static function ( $url ) {
					return is_string( $url ) ? $url : null;
				},
				rest_get_avatar_urls( $user )
			);
		}

		if ( $can_view_sensitive ) {
			if ( $this->is_field_requested( 'username', $fields ) ) {
				$data['username'] = (string) $user->user_login;
			}
			if ( $this->is_field_requested( 'email', $fields ) ) {
				$data['email'] = is_email( $user->user_email ) ? (string) $user->user_email : null;
			}
			if ( $this->is_field_requested( 'first_name', $fields ) ) {
				$data['first_name'] = (string) $user->first_name;
			}
			if ( $this->is_field_requested( 'last_name', $fields ) ) {
				$data['last_name'] = (string) $user->last_name;
			}
			if ( $this->is_field_requested( 'nickname', $fields ) ) {
				$data['nickname'] = (string) $user->nickname;
			}
			if ( $this->is_field_requested( 'locale', $fields ) ) {
				$data['locale'] = (string) get_user_locale( $user );
			}

			$registered = array();
			if ( $this->is_field_requested( 'registered.site', $fields ) ) {
				$registered['site'] = $this->format_registered_date( $user, wp_timezone() );
			}
			if ( $this->is_field_requested( 'registered.gmt', $fields ) ) {
				$registered['gmt'] = $this->format_registered_date( $user, new DateTimeZone( 'UTC' ) );
			}
			if ( ! empty( $registered ) ) {
				$data['registered'] = $registered;
			}
		}

		/*
		 * Roles reveal a user's privilege level, so they are gated like the other
		 * sensitive fields: visible only for the current user or a user the caller
		 * can edit. `list_users` alone (which grants no edit rights) is not enough,
		 * matching the REST users controller, where `roles` is an edit-context
		 * field and rows the caller cannot edit are dropped from collections.
		 */
		if ( $this->is_field_requested( 'roles', $fields ) && $can_view_sensitive ) {
			$data['roles'] = array_values( $user->roles );
		}

		return $data;
	}

	/**
	 * Formats the user's registration date as an ISO 8601 string in a timezone.
	 *
	 * The `user_registered` column holds the date in GMT, so it is parsed as UTC
	 * whatever the PHP default timezone is, then converted to the given timezone.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_User      $user     The user object.
	 * @param DateTimeZone $timezone The timezone to format the date in.
	 * @return string|null The ISO 8601 date, or null when the stored date is not a valid date.
	 */
	private function format_registered_date( WP_User $user, DateTimeZone $timezone ): ?string {
		$datetime = date_create_immutable_from_format( 'Y-m-d H:i:s', (string) $user->user_registered, new DateTimeZone( 'UTC' ) );
		if ( ! $datetime ) {
			return null;
		}

		/*
		 * A malformed stored date can still parse, since out-of-range parts roll over: the
		 * zero date, the column default, formats with a negative year, and converting a
		 * date at either end of the range can push its year outside four digits. The
		 * `date-time` format rejects these, which would fail output validation for the
		 * whole call, so they are reported as null too, like an unusable email.
		 */
		$date = $datetime->setTimezone( $timezone )->format( 'c' );

		return false !== rest_parse_date( $date ) ? $date : null;
	}

	/**
	 * Builds the ability output for a page of formatted users.
	 *
	 * @since 7.2.0
	 *
	 * @param array<int, array<string, mixed>> $items       The formatted users on the page.
	 * @param int                              $total       Total number of users across all pages.
	 * @param int                              $total_pages Total number of pages.
	 * @return array<string, mixed> The ability output.
	 */
	private function prepare_output( array $items, int $total, int $total_pages ): array {
		return array(
			'items'      => $items,
			'pagination' => array(
				'total'       => $total,
				'total_pages' => $total_pages,
			),
		);
	}

	/**
	 * Builds the error for a page past the last page of results.
	 *
	 * A page that does not exist is reported as not found rather than as an empty list,
	 * so callers can tell it apart from a request that matches no users.
	 *
	 * @since 7.2.0
	 *
	 * @return WP_Error The invalid page number error.
	 */
	private function invalid_page_number_error(): WP_Error {
		return new WP_Error(
			'users_invalid_page_number',
			__( 'The page number requested is larger than the number of pages available.' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Builds the error for a filter that cannot be honored.
	 *
	 * As in the REST API's `rest_invalid_param` errors, the error data maps the filter's
	 * input path to the message under `params`, so callers can tell which filter failed
	 * without parsing the translated message.
	 *
	 * @since 7.2.0
	 *
	 * @param string $param   The filter's input path, such as `where[ids]`.
	 * @param string $message The error message.
	 * @return WP_Error The invalid filter error.
	 */
	private function invalid_filter_error( string $param, string $message ): WP_Error {
		return new WP_Error(
			'users_invalid_filter',
			$message,
			array(
				'status' => 400,
				'params' => array( $param => $message ),
			)
		);
	}

	/**
	 * Builds the error for an input value that cannot be applied.
	 *
	 * The error data maps the value's input path to the message under `params`.
	 *
	 * @since 7.3.0
	 *
	 * @param string $param   The input path, such as `data[email]`.
	 * @param string $message The error message.
	 * @param string $code    Optional. The error code. Default 'user_invalid_attribute'.
	 * @return WP_Error The error.
	 */
	private function invalid_attribute_error( string $param, string $message, string $code = 'user_invalid_attribute' ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array(
				'status' => 400,
				'params' => array( $param => $message ),
			)
		);
	}

	/**
	 * Validates a value against a schema, and builds the error for it when it fails.
	 *
	 * Used for the checks the input schema also makes, since a filter can skip schema
	 * validation, and for checks on a computed value, such as its length after sanitizing,
	 * so the error has a schema validation message.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $param  The input path the error is reported for.
	 * @param mixed                $value  The value to validate.
	 * @param array<string, mixed> $schema The schema to validate the value against.
	 * @param string               $path   Optional. The path the message names. Default the input path.
	 * @return WP_Error|null The error, or null when the value is valid.
	 */
	private function get_validation_error( string $param, $value, array $schema, string $path = '' ): ?WP_Error {
		$result = rest_validate_value_from_schema( $value, $schema, '' === $path ? $param : $path );

		return is_wp_error( $result ) ? $this->invalid_attribute_error( $param, $result->get_error_message() ) : null;
	}

	/**
	 * Builds the error for a `where.id` that names no member of the current site.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_Error The not found error.
	 */
	private function not_found_error(): WP_Error {
		return new WP_Error( 'user_not_found', __( 'Invalid user ID.' ), array( 'status' => 404 ) );
	}

	/**
	 * Builds the error for a caller who may not delete, or on multisite remove, the user.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_Error The permission error.
	 */
	private function cannot_delete_error(): WP_Error {
		return new WP_Error(
			'user_cannot_delete',
			is_multisite() ? __( 'Sorry, you are not allowed to remove users.' ) : __( 'Sorry, you are not allowed to delete this user.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Builds the error for a create save that returned no user.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_Error The error.
	 */
	private function create_failed_error(): WP_Error {
		return new WP_Error( 'user_create_failed', __( 'Error creating new user.' ), array( 'status' => 500 ) );
	}

	/**
	 * Builds the schema of the `fields` input.
	 *
	 * @since 7.3.0
	 *
	 * @param string $description The description of the input.
	 * @return array<string, mixed> The `fields` JSON Schema.
	 */
	private function get_fields_input_schema( string $description ): array {
		return array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => $this->get_field_paths(),
			),
			'description' => $description,
		);
	}

	/**
	 * Builds the schema of the `fields` input of the create and update abilities.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The `fields` JSON Schema.
	 */
	private function get_user_fields_input_schema(): array {
		return $this->get_fields_input_schema( __( 'Limit the returned user to these fields. A field such as `registered` returns all of its sub-fields, while a path such as `registered.gmt` returns only that sub-field. If omitted or empty, a lean set of common read fields is returned. The `id` field is always included, and fields the current user cannot view are omitted rather than causing an error.' ) );
	}

	/**
	 * Builds the schema of a user, as returned by the user abilities.
	 *
	 * Only `id` is required, because it is always returned. The other fields are optional
	 * because the `fields` input lets the caller request any subset, and restricted fields
	 * are omitted when unavailable.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The user JSON Schema.
	 */
	private function get_user_item_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => $this->get_user_properties(),
		);
	}

	/**
	 * Builds the schema of the `where` input of the update and delete abilities.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The `where` JSON Schema.
	 */
	private function get_where_id_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => array(
				'id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Unique identifier for the user.' ),
				),
			),
			'description'          => __( 'Selects the user. On multisite, only members of the current site can be selected.' ),
		);
	}

	/**
	 * Builds the attribute schemas of the `data` input of the create and update abilities.
	 *
	 * The `roles` and `locale` enums hold the roles and languages available when the
	 * ability is registered. The callbacks check both again, since either can change later.
	 *
	 * @since 7.3.0
	 *
	 * @param bool $is_create Whether the schemas are for the create ability, which also takes a username.
	 * @return array<string, mixed> The attribute JSON Schemas, keyed by attribute name.
	 */
	private function get_user_data_properties( bool $is_create ): array {
		$properties = array();
		if ( $is_create ) {
			$properties['username'] = array(
				'type'        => 'string',
				'minLength'   => 1,
				'description' => __( 'Login name for the user.' ),
			);
		}

		return $properties + array(
			'email'       => array(
				'type'        => 'string',
				'format'      => 'email',
				'maxLength'   => 100,
				'description' => __( 'The email address for the user.' ),
			),
			'name'        => array(
				'type'        => 'string',
				'minLength'   => 1,
				'maxLength'   => 250,
				'description' => __( 'Display name for the user.' ),
			),
			'first_name'  => array(
				'type'        => 'string',
				'description' => __( 'First name for the user.' ),
			),
			'last_name'   => array(
				'type'        => 'string',
				'description' => __( 'Last name for the user.' ),
			),
			'nickname'    => array(
				'type'        => 'string',
				'minLength'   => 1,
				'description' => __( 'The nickname for the user.' ),
			),
			'slug'        => array(
				'type'        => 'string',
				'minLength'   => 1,
				'description' => __( 'An alphanumeric identifier for the user.' ),
			),
			'description' => array(
				'type'        => 'string',
				'description' => __( 'Description of the user.' ),
			),
			'url'         => array(
				'type'        => 'string',
				'description' => __( 'URL of the user.' ),
			),
			'locale'      => array(
				'type'        => 'string',
				'enum'        => $this->get_locales(),
				'description' => __( 'Locale for the user.' ),
			),
			'roles'       => array(
				'type'        => 'array',
				'uniqueItems' => true,
				'minItems'    => 1,
				'items'       => array(
					'type' => 'string',
					'enum' => array_keys( wp_roles()->roles ),
				),
				'description' => __( 'Roles assigned to the user.' ),
			),
		);
	}

	/**
	 * Builds the input schema for the `core/user-create` ability.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_user_create_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'data' ),
			'properties'           => array(
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'username', 'email' ),
					'properties'           => $this->get_user_data_properties( true ),
					'description'          => __( 'The attributes of the new user. Without `roles`, the user gets the default role of the site, or on multisite no role on the current site.' ),
				),
				'fields' => $this->get_user_fields_input_schema(),
			),
		);
	}

	/**
	 * Builds the input schema for the `core/user-update` ability.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_user_update_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where', 'data' ),
			'properties'           => array(
				'where'  => $this->get_where_id_schema(),
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'minProperties'        => 1,
					'properties'           => $this->get_user_data_properties( false ),
					'description'          => __( 'The attributes to change. `roles` replaces all roles of the user.' ),
				),
				'fields' => $this->get_user_fields_input_schema(),
			),
		);
	}

	/**
	 * Builds the input schema for the `core/user-delete` ability.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_user_delete_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where', 'reassign' ),
			'properties'           => array(
				'where'    => $this->get_where_id_schema(),
				'reassign' => array(
					/*
					 * Intentionally strict: 0, an empty string and null are refused rather than read
					 * as reassigning nothing, so the content is only left unassigned when false is
					 * sent. True passes this schema and is refused by the execute callback.
					 */
					'type'        => array( 'integer', 'boolean' ),
					'minimum'     => 1,
					'description' => __( 'The ID of the user who takes over the content of the deleted user, or false to reassign nothing. True is not accepted.' ),
				),
			),
		);
	}
}
