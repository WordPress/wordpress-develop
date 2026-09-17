A role stored without a `capabilities` key, or with a non-array value for it, causes two separate problems, not just one:

1. A fatal `TypeError` in `WP_User_Query::prepare_query()` when querying by capability, since `array_filter()` is called directly on `$role_data['capabilities']`. This is the crash originally reported on the ticket, via `wp_dropdown_users()` on the classic Author meta box.
2. The same unguarded assumption exists in `WP_Roles::init_roles()`, which runs on effectively every request that initializes roles — not just capability-filtered queries. Neither #8351 nor #8823 addresses this second call site.

This can happen when a plugin registers a role without capabilities and is later deactivated, leaving the malformed role behind in the site's `user_roles` option.

This PR builds on the investigation already done in #8351 (by @geekofshire) and #8823 (by @umeshnevase) — thank you both. It combines the `is_array()` robustness from #8823 with the more readable guard-clause shape from #8351, extends the same fix to `WP_Roles::init_roles()`, and adds the unit test coverage that @johnbillion asked for and that both prior PRs were still missing.

### Reproduction

Reproduced against a real WordPress install (not just the unit tests) by creating a role with no `capabilities` key and triggering the exact code path from the original report (`wp_dropdown_users()` with a capability filter):

**Before the fix** (`wp-content/debug.log`):
```
[07-Aug-2026 10:00:41 UTC] PHP Warning:  Undefined array key "capabilities" in wp-includes/class-wp-roles.php on line 310
[07-Aug-2026 10:00:41 UTC] PHP Warning:  Undefined array key "capabilities" in wp-includes/class-wp-roles.php on line 310
[07-Aug-2026 10:00:41 UTC] PHP Warning:  Undefined array key "capabilities" in wp-includes/class-wp-user-query.php on line 485
[07-Aug-2026 10:00:41 UTC] PHP Fatal error:  Uncaught TypeError: array_filter(): Argument #1 ($array) must be of type array, null given in wp-includes/class-wp-user-query.php:485
Stack trace:
#0 wp-includes/class-wp-user-query.php(485): array_filter(NULL)
#1 wp-includes/class-wp-user-query.php(79): WP_User_Query->prepare_query(Array)
#2 wp-includes/user.php(879): WP_User_Query->__construct(Array)
#3 wp-includes/user.php(1810): get_users(Array)
#4 ...: wp_dropdown_users(Array)
  thrown in wp-includes/class-wp-user-query.php on line 485
```

**After the fix**, same broken role, same trigger: no warnings, no errors, `debug.log` isn't even created, and the query returns results correctly instead of crashing.

### Testing

- Added two unit tests covering both failure modes (missing key, and a non-array value). Verified they fail at the correct line (`class-wp-user-query.php:485`, matching the original report) without the fix, and pass with it.
- Ran the full `user` and `capabilities` test groups (1,343 tests, 4,530 assertions) to confirm no regressions.
- Added a third test for the multisite network case: `WP_User_Query` doesn't need `switch_to_blog()` to query another site's users — it calls `WP_Roles::for_site( $blog_id )` directly, which reloads and re-initializes roles for that site in place. A role missing `capabilities` on a *different* site in the network hits the same unguarded code, so this exercises that path with the core test suite's multisite mode (`WP_TESTS_MULTISITE=1`). Verified it fails at `class-wp-roles.php:310` via `for_site()` without the fix, and passes with it. Ran the `user`/`capabilities`/`multisite` groups under multisite (763 tests, 3,137 assertions) to confirm no regressions there either. Details in `repro-evidence/multisite-verification.md`.

Trac ticket: https://core.trac.wordpress.org/ticket/62600

## Use of AI Tools

AI assistance: Yes
Tool(s): Claude Code
Model(s): Claude Sonnet 5
Used for: Investigating the root cause (including finding the second, unreported crash site in `WP_Roles::init_roles()`), implementing the fix in both files, writing and running the unit tests, and reproducing the bug against a live WordPress install to verify the before/after behavior shown above. All changes were reviewed and directed by me.

---
**This Pull Request is for code review only. Please keep all other discussion in the Trac ticket. Do not merge this Pull Request. See [GitHub Pull Requests for Code Review](https://make.wordpress.org/core/handbook/contribute/git/github-pull-requests-for-code-review/) in the Core Handbook for more details.**
