# Visual Regression Tests in WordPress Core

These tests make use of Playwright, with a setup very similar to that of the e2e tests.

## How to Run the Tests Locally

1. Check out trunk.
2. Run `npm run test:visual` to generate some base snapshots.
3. Check out the feature branch to be tested.
4. Run `npm run test:visual` again. If any tests fail, the diff images can be found in `artifacts/`

