# PHPUnit Tests

PHPUnit is the official testing framework chosen by the core team to test PHP code.

For more information, please review the relevant Core Handbook pages:
- [PHP: PHPUnit](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [Writing PHP Tests](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/)

## Run the full matrix on a pull request

Add the `Full PHPUnit Matrix` label to a pull request in `WordPress/wordpress-develop` to run the same PHP and database combinations as the weekly scheduled run. The label-triggered workflow uses the same path filters as normal PHPUnit runs.

If you cannot apply labels, leave a PR comment asking a reviewer with triage access to add it. Fork PRs follow GitHub's usual workflow approval requirements.

Adding the label starts a separate run without another code push. An existing reduced-matrix run continues alongside it; its checks and results are preserved. New commits run the full matrix while the label remains. Remove and reapply it to request another run for the current revision, or remove it to return subsequent runs to the reduced matrix.

Unrelated labels skip only the label-triggered workflow's `full-matrix` job and do not replace or cancel PHPUnit checks.

Look for `PHPUnit Tests (full matrix)` in GitHub Actions and the PHPUnit checks on the PR. Each run tests the PR's merge commit and retains its own logs and results, so check the revision before relying on an older run.
