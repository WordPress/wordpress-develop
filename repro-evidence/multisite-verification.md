### Multisite network verification

The original repro (`before-fix-debug.log` / `after-fix-output.txt`) was against a single-site
install. Since roles are stored per-site (`{$blog_prefix}user_roles`), the same broken-role
scenario can occur independently on any site in a network, so this was verified separately
against the multisite code paths.

**Why a live network site wasn't used**: `WP_User_Query` doesn't need `switch_to_blog()` to
query another site's users — it calls `WP_Roles::for_site( $blog_id )` directly
(`class-wp-user-query.php:458`), which reloads and re-initializes roles for that site's
`user_roles` option in place. That's the multisite-specific code path worth covering, and
the core test suite's multisite mode (`tests/phpunit/multisite.xml`, `WP_TESTS_MULTISITE=1`)
exercises it directly, so it was used instead of a second manual wp-cli repro.

**Test added**: `test_capability_query_with_role_missing_capabilities_key_on_other_site` in
`tests/phpunit/tests/user/query.php`. It creates a sub-site, adds a role with no `capabilities`
key to *that* site's `user_roles` option, then — from the main site's context — runs a
`WP_User_Query` with `blog_id` set to the sub-site. This forces `WP_Roles::for_site()` to load
and re-`init_roles()` the broken role without ever switching the global blog context.

**Before the fix** (`class-wp-roles.php` / `class-wp-user-query.php` reverted to trunk, same
test):
```
1) Tests_User_Query::test_capability_query_with_role_missing_capabilities_key_on_other_site
Undefined array key "capabilities"

/Users/.../src/wp-includes/class-wp-roles.php:310
/Users/.../src/wp-includes/class-wp-roles.php:350
/Users/.../src/wp-includes/class-wp-user-query.php:458
/Users/.../src/wp-includes/class-wp-user-query.php:79
/Users/.../tests/phpunit/tests/user/query.php:2037

ERRORS!
Tests: 1, Assertions: 0, Errors: 1.
```

**After the fix**: test passes.

**Full multisite regression check**: ran the `user`/`capabilities`/`multisite` groups under
`multisite.xml` (`WP_TESTS_MULTISITE=1`) — 763 tests, 3137 assertions, all passing, no
regressions introduced by extending coverage to the network case.
