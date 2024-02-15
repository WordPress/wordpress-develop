# Visual Regression Tests in WordPress Core

These tests make use of Jest and Puppeteer, with a setup very similar to that of the e2e tests, together with [jest-image-snapshot](https://github.com/americanexpress/jest-image-snapshot) for generating the visual diffs.

## How to Run the Tests Locally

1. Check out trunk.
2. Run `npm run test:visual` to generate some base snapshots.
3. Check out the feature branch to be tested.
4. Run `npm run test:visual` again. If any tests fail, the diff images can be found in `tests/visual-regression/specs/__image_snapshots__/__diff_output__`.

