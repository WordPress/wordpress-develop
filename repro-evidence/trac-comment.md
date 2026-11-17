Opened a PR that builds on the patches already here: https://github.com/WordPress/wordpress-develop/pull/12931

It combines the `isset()` guard from #8351 with the `is_array()` robustness from #8823, and extends the same fix to `WP_Roles::init_roles()`, which has the identical unguarded `$role_data['capabilities']` assumption and runs on effectively every request that initializes roles, not just capability-filtered `WP_User_Query` calls. Neither of the existing PRs covers that second call site.

Added the unit test coverage requested above — two tests covering both failure modes (missing key, and a non-array value), in `tests/phpunit/tests/user/query.php`. Verified they fail at the exact reported line (`class-wp-user-query.php:485`) without the fix and pass with it, and ran the full `user`/`capabilities` groups (1,343 tests) to confirm no regressions.

Also reproduced the original crash against a real WordPress install using the exact reported code path (`wp_dropdown_users()` with a capability filter) to confirm the fatal error and its resolution outside of the test suite — details in the PR description.

Also verified the multisite network case: `WP_User_Query` can load another site's roles via `WP_Roles::for_site( $blog_id )` without `switch_to_blog()`, so a role missing `capabilities` on any one site in a network hits the same bug. Added a third test for that path and ran the `user`/`capabilities`/`multisite` groups under `WP_TESTS_MULTISITE=1` (763 tests, 3,137 assertions) — no regressions.
