<?php

/**
 * Tests for site-scoped hook subscriptions.
 *
 * @group hooks
 * @ticket 66097
 */
class Tests_Hooks_SiteScoped extends WP_UnitTestCase {

	/**
	 * The site ID in use before each test.
	 *
	 * @var int
	 */
	private $original_blog_id;

	public function set_up() {
		parent::set_up();

		$this->original_blog_id = get_current_blog_id();
	}

	public function tear_down() {
		$GLOBALS['blog_id'] = $this->original_blog_id;

		parent::tear_down();
	}

	/**
	 * @covers ::add_filter
	 * @covers WP_Hook::add_filter
	 * @covers ::apply_filters
	 */
	public function test_legacy_global_registration_retains_its_shape_and_runs_on_every_site() {
		$hook_name = __FUNCTION__;
		$callback  = static function ( $value ) {
			return $value + 1;
		};

		$this->assertTrue( add_filter( $hook_name, $callback ) );

		$function_id = _wp_filter_build_unique_id( $hook_name, $callback, 10 );
		$this->assertSame(
			array(
				'function'      => $callback,
				'accepted_args' => 1,
			),
			$GLOBALS['wp_filter'][ $hook_name ]->callbacks[10][ $function_id ]
		);

		$this->assertSame( 1, apply_filters( $hook_name, 0 ) );

		$GLOBALS['blog_id'] = $this->original_blog_id + 1;

		$this->assertSame( 1, apply_filters( $hook_name, 0 ) );
	}

	/**
	 * @covers ::add_filter
	 * @covers ::apply_filters
	 */
	public function test_site_scoped_filter_only_runs_on_the_matching_site() {
		$hook_name = __FUNCTION__;
		$blog_id   = $this->original_blog_id;

		add_filter(
			$hook_name,
			static function ( $value ) {
				return $value . '-filtered';
			},
			10,
			1,
			$blog_id
		);

		$this->assertSame( 'value-filtered', apply_filters( $hook_name, 'value' ) );

		$GLOBALS['blog_id'] = $blog_id + 1;

		$this->assertSame( 'value', apply_filters( $hook_name, 'value' ) );
	}

	/**
	 * @covers ::add_action
	 * @covers ::has_action
	 * @covers ::remove_action
	 * @covers ::do_action
	 */
	public function test_site_scoped_action_uses_the_public_action_api() {
		$hook_name  = __FUNCTION__;
		$blog_id    = $this->original_blog_id;
		$call_count = 0;
		$callback   = static function () use ( &$call_count ) {
			++$call_count;
		};

		$this->assertTrue( add_action( $hook_name, $callback, 10, 0, $blog_id ) );
		$this->assertTrue( has_action( $hook_name, $callback, 10, $blog_id ) );

		do_action( $hook_name );
		$GLOBALS['blog_id'] = $blog_id + 1;
		do_action( $hook_name );

		$this->assertSame( 1, $call_count );
		$this->assertTrue( remove_action( $hook_name, $callback, 10, $blog_id ) );
		$this->assertFalse( has_action( $hook_name, $callback, 10, $blog_id ) );
	}

	/**
	 * @covers ::add_filter
	 * @covers ::apply_filters
	 */
	public function test_global_and_site_registrations_have_independent_identities_and_retain_order() {
		$blog_id  = $this->original_blog_id;
		$callback = static function ( $value, $scope = 'global' ) {
			$value[] = $scope;

			return $value;
		};

		$site_first_hook = __FUNCTION__ . '_site_first';
		add_filter( $site_first_hook, $callback, 0, 2, $blog_id );
		add_filter( $site_first_hook, $callback, 0, 1 );

		$this->assertSame(
			array( 'site', 'global' ),
			apply_filters( $site_first_hook, array(), 'site' )
		);
		$this->assertSame( 0, has_filter( $site_first_hook, $callback ) );
		$this->assertTrue( has_filter( $site_first_hook, $callback, 0, $blog_id ) );
		$this->assertTrue( has_filter( $site_first_hook, $callback, 0, null ) );

		$global_first_hook = __FUNCTION__ . '_global_first';
		add_filter( $global_first_hook, $callback, 0, 1 );
		add_filter( $global_first_hook, $callback, 0, 2, $blog_id );

		$this->assertSame(
			array( 'global', 'site' ),
			apply_filters( $global_first_hook, array(), 'site' )
		);
	}

	/**
	 * @group ms-required
	 *
	 * @covers ::add_filter
	 * @covers ::apply_filters
	 */
	public function test_site_scoped_callbacks_follow_switch_to_blog_and_restore_current_blog() {
		$hook_name     = __FUNCTION__;
		$other_blog_id = self::factory()->blog->create();

		add_filter(
			$hook_name,
			static function ( $value ) {
				$value[] = 'global';

				return $value;
			}
		);
		add_filter(
			$hook_name,
			static function ( $value ) {
				$value[] = 'original';

				return $value;
			},
			10,
			1,
			$this->original_blog_id
		);
		add_filter(
			$hook_name,
			static function ( $value ) {
				$value[] = 'other';

				return $value;
			},
			10,
			1,
			$other_blog_id
		);

		$original_result = apply_filters( $hook_name, array() );

		switch_to_blog( $other_blog_id );
		try {
			$other_result = apply_filters( $hook_name, array() );
		} finally {
			restore_current_blog();
		}

		$restored_result = apply_filters( $hook_name, array() );

		$this->assertSame( array( 'global', 'original' ), $original_result );
		$this->assertSame( array( 'global', 'other' ), $other_result );
		$this->assertSame( array( 'global', 'original' ), $restored_result );
		$this->assertSame( $this->original_blog_id, get_current_blog_id() );
	}

	/**
	 * @group ms-required
	 *
	 * @covers ::add_filter
	 * @covers ::apply_filters
	 */
	public function test_same_callback_has_independent_global_and_site_identities_across_real_site_switches() {
		$hook_name     = __FUNCTION__;
		$other_blog_id = self::factory()->blog->create();
		$callback      = static function ( $value ) {
			$value[] = get_current_blog_id();

			return $value;
		};

		add_filter( $hook_name, $callback );
		add_filter( $hook_name, $callback, 10, 1, $this->original_blog_id );
		add_filter( $hook_name, $callback, 10, 1, $other_blog_id );

		$this->assertCount( 3, $GLOBALS['wp_filter'][ $hook_name ]->callbacks[10] );

		$original_result = apply_filters( $hook_name, array() );

		switch_to_blog( $other_blog_id );
		try {
			$other_result = apply_filters( $hook_name, array() );
		} finally {
			restore_current_blog();
		}

		$this->assertSame(
			array( $this->original_blog_id, $this->original_blog_id ),
			$original_result
		);
		$this->assertSame( array( $other_blog_id, $other_blog_id ), $other_result );
		$this->assertSame( $this->original_blog_id, get_current_blog_id() );
	}

	/**
	 * @covers ::add_filter
	 * @covers ::apply_filters
	 */
	public function test_reregistering_one_scope_updates_only_that_scope_without_moving_it() {
		$hook_name = __FUNCTION__;
		$blog_id   = $this->original_blog_id;
		$callback  = static function ( $value, $suffix = '-default' ) {
			return $value . $suffix;
		};

		add_filter( $hook_name, $callback, 10, 1, $blog_id );
		add_filter(
			$hook_name,
			static function ( $value ) {
				return $value . '-middle';
			},
			10
		);
		add_filter( $hook_name, $callback, 10, 2, $blog_id );

		$this->assertCount( 2, $GLOBALS['wp_filter'][ $hook_name ]->callbacks[10] );
		$this->assertSame( 'value-site-middle', apply_filters( $hook_name, 'value', '-site' ) );
	}

	/**
	 * @covers ::add_filter
	 * @covers ::apply_filters
	 */
	public function test_scope_is_checked_immediately_before_each_callback() {
		$hook_name   = __FUNCTION__;
		$initial_id  = $this->original_blog_id;
		$switched_id = $initial_id + 1;

		add_filter(
			$hook_name,
			static function ( $value ) use ( $switched_id ) {
				$GLOBALS['blog_id'] = $switched_id;
				$value[]            = 'switch';

				return $value;
			},
			10
		);
		add_filter(
			$hook_name,
			static function ( $value ) {
				$value[] = 'new-site';

				return $value;
			},
			10,
			1,
			$switched_id
		);
		add_filter(
			$hook_name,
			static function ( $value ) {
				$value[] = 'old-site';

				return $value;
			},
			10,
			1,
			$initial_id
		);

		$this->assertSame( array( 'switch', 'new-site' ), apply_filters( $hook_name, array() ) );
	}

	/**
	 * @covers ::add_filter
	 * @covers ::apply_filters
	 */
	public function test_first_site_scoped_callback_added_during_dispatch_runs_at_a_later_priority() {
		$hook_name = __FUNCTION__;
		$blog_id   = $this->original_blog_id;
		$scoped    = static function ( $value ) {
			return $value . 'scoped';
		};

		add_filter(
			$hook_name,
			static function ( $value ) use ( $blog_id, $hook_name, $scoped ) {
				add_filter( $hook_name, $scoped, 20, 1, $blog_id );

				return $value . 'global-';
			},
			10
		);

		$this->assertSame( 'global-scoped', apply_filters( $hook_name, '' ) );
	}

	/**
	 * @covers ::add_filter
	 * @covers ::apply_filters
	 */
	public function test_nested_dispatch_uses_the_current_site_and_restored_site() {
		$outer_hook  = __FUNCTION__ . '_outer';
		$inner_hook  = __FUNCTION__ . '_inner';
		$initial_id  = $this->original_blog_id;
		$switched_id = $initial_id + 1;

		add_filter(
			$inner_hook,
			static function ( $value ) {
				return $value . 'inner-';
			},
			10,
			1,
			$switched_id
		);
		add_filter(
			$outer_hook,
			static function ( $value ) use ( $inner_hook, $initial_id, $switched_id ) {
				$GLOBALS['blog_id'] = $switched_id;

				try {
					return apply_filters( $inner_hook, $value );
				} finally {
					$GLOBALS['blog_id'] = $initial_id;
				}
			}
		);
		add_filter(
			$outer_hook,
			static function ( $value ) {
				return $value . 'outer';
			},
			10,
			1,
			$initial_id
		);

		$this->assertSame( 'inner-outer', apply_filters( $outer_hook, '' ) );
		$this->assertSame( $initial_id, get_current_blog_id() );
	}

	/**
	 * @covers ::add_action
	 * @covers ::do_action
	 * @covers WP_Hook::do_all_hook
	 */
	public function test_site_scope_is_applied_to_the_all_hook() {
		$hook_name = __FUNCTION__;
		$blog_id   = $this->original_blog_id;
		$seen      = array();
		$callback  = static function ( $current_hook ) use ( &$seen, $hook_name ) {
			if ( $hook_name === $current_hook ) {
				$seen[] = $current_hook;
			}
		};

		add_action( 'all', $callback, 10, 1, $blog_id );

		do_action( $hook_name );
		$GLOBALS['blog_id'] = $blog_id + 1;
		do_action( $hook_name );

		$this->assertSame( array( $hook_name ), $seen );
		$this->assertTrue( remove_action( 'all', $callback, 10, $blog_id ) );
	}

	/**
	 * @covers ::apply_filters_ref_array
	 * @covers ::do_action_ref_array
	 */
	public function test_site_scope_is_applied_to_reference_array_variants() {
		$filter_hook = __FUNCTION__ . '_filter';
		$action_hook = __FUNCTION__ . '_action';
		$blog_id     = $this->original_blog_id;
		$action_runs = 0;

		add_filter(
			$filter_hook,
			static function ( $value ) {
				return $value . '-filtered';
			},
			10,
			1,
			$blog_id
		);
		add_action(
			$action_hook,
			static function () use ( &$action_runs ) {
				++$action_runs;
			},
			10,
			0,
			$blog_id
		);

		$this->assertSame( 'value-filtered', apply_filters_ref_array( $filter_hook, array( 'value' ) ) );
		do_action_ref_array( $action_hook, array() );

		$GLOBALS['blog_id'] = $blog_id + 1;

		$this->assertSame( 'value', apply_filters_ref_array( $filter_hook, array( 'value' ) ) );
		do_action_ref_array( $action_hook, array() );
		$this->assertSame( 1, $action_runs );
	}

	/**
	 * @covers ::has_filter
	 */
	public function test_lookup_can_target_any_global_or_exact_site_scope() {
		$hook_name     = __FUNCTION__;
		$callback      = '__return_null';
		$blog_id       = $this->original_blog_id;
		$other_blog_id = $blog_id + 1;

		add_filter( $hook_name, $callback, 10, 1 );
		add_filter( $hook_name, $callback, 10, 1, $blog_id );
		add_filter( $hook_name, $callback, 10, 1, $other_blog_id );

		$this->assertSame( 10, has_filter( $hook_name, $callback ) );
		$this->assertTrue( has_filter( $hook_name, $callback, 10, false ) );
		$this->assertTrue( has_filter( $hook_name, $callback, 10, null ) );
		$this->assertTrue( has_filter( $hook_name, $callback, 10, $blog_id ) );
		$this->assertTrue( has_filter( $hook_name, $callback, 10, $other_blog_id ) );
		$this->assertFalse( has_filter( $hook_name, $callback, 10, $other_blog_id + 1 ) );
	}

	/**
	 * @covers ::has_filter
	 */
	public function test_lookup_without_a_callback_preserves_priority_ignored_behavior_and_applies_scope() {
		$hook_name = __FUNCTION__;
		$blog_id   = $this->original_blog_id;

		add_filter( $hook_name, '__return_null', 5, 1, $blog_id );
		add_filter( $hook_name, '__return_false', 10, 1 );

		$this->assertTrue( has_filter( $hook_name, false, 999, $blog_id ) );
		$this->assertTrue( has_filter( $hook_name, false, 999, null ) );
		$this->assertFalse( has_filter( $hook_name, false, 999, $blog_id + 1 ) );
	}

	/**
	 * @covers ::remove_filter
	 */
	public function test_removal_can_target_global_exact_site_or_all_scopes() {
		$hook_name     = __FUNCTION__;
		$callback      = '__return_null';
		$blog_id       = $this->original_blog_id;
		$other_blog_id = $blog_id + 1;

		add_filter( $hook_name, $callback, 10, 1 );
		add_filter( $hook_name, $callback, 10, 1, $blog_id );
		add_filter( $hook_name, $callback, 10, 1, $other_blog_id );

		$this->assertTrue( remove_filter( $hook_name, $callback, 10, $blog_id ) );
		$this->assertFalse( has_filter( $hook_name, $callback, 10, $blog_id ) );
		$this->assertTrue( has_filter( $hook_name, $callback, 10, null ) );
		$this->assertTrue( has_filter( $hook_name, $callback, 10, $other_blog_id ) );

		$this->assertTrue( remove_filter( $hook_name, $callback, 10, null ) );
		$this->assertFalse( has_filter( $hook_name, $callback, 10, null ) );
		$this->assertTrue( has_filter( $hook_name, $callback, 10, $other_blog_id ) );

		$this->assertTrue( remove_filter( $hook_name, $callback ) );
		$this->assertFalse( has_filter( $hook_name, $callback ) );
	}

	/**
	 * @dataProvider data_invalid_registration_blog_ids
	 *
	 * @covers ::add_filter
	 *
	 * @param mixed $blog_id Invalid site ID.
	 */
	public function test_invalid_registration_blog_id_returns_false_without_mutating_hooks( $blog_id ) {
		$hook_name = __FUNCTION__ . '_' . md5( serialize( $blog_id ) );

		$this->assertFalse( add_filter( $hook_name, '__return_null', 10, 1, $blog_id ) );
		$this->assertArrayNotHasKey( $hook_name, $GLOBALS['wp_filter'] );
	}

	/**
	 * Data provider for invalid registration site IDs.
	 *
	 * @return array[]
	 */
	public function data_invalid_registration_blog_ids() {
		return array(
			'false'                   => array( false ),
			'true'                    => array( true ),
			'zero'                    => array( 0 ),
			'negative integer'        => array( -1 ),
			'positive numeric string' => array( '1' ),
			'positive float'          => array( 1.0 ),
			'empty string'            => array( '' ),
			'array'                   => array( array( 1, 2 ) ),
			'object'                  => array( new stdClass() ),
		);
	}

	/**
	 * @covers ::has_filter
	 * @covers ::remove_filter
	 */
	public function test_invalid_lookup_and_removal_scope_does_not_match_or_remove_a_registration() {
		$hook_name = __FUNCTION__;
		$callback  = '__return_null';
		$blog_id   = $this->original_blog_id;

		add_filter( $hook_name, $callback, 10, 1, $blog_id );

		$this->assertFalse( has_filter( $hook_name, $callback, 10, 0 ) );
		$this->assertFalse( remove_filter( $hook_name, $callback, 10, 0 ) );
		$this->assertTrue( has_filter( $hook_name, $callback, 10, $blog_id ) );
	}

	/**
	 * @covers WP_Hook::build_preinitialized_hooks
	 */
	public function test_preinitialized_hooks_remain_global() {
		$hook_name = __FUNCTION__;
		$callback  = static function ( $value ) {
			return $value . '-filtered';
		};
		$filters   = array(
			$hook_name => array(
				10 => array(
					'legacy-id' => array(
						'function'      => $callback,
						'accepted_args' => 1,
					),
				),
			),
		);

		$hooks = WP_Hook::build_preinitialized_hooks( $filters );

		$GLOBALS['blog_id'] = $this->original_blog_id + 1;

		$this->assertSame( 'value-filtered', $hooks[ $hook_name ]->apply_filters( 'value', array( 'value' ) ) );
		$this->assertTrue( $hooks[ $hook_name ]->has_filter( $hook_name, $callback, 10, null ) );
		$this->assertFalse( $hooks[ $hook_name ]->has_filter( $hook_name, $callback, 10, get_current_blog_id() ) );
	}
}
