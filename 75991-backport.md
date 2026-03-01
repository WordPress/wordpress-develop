Media: Use Document-Isolation-Policy for cross-origin isolation on Chrome 137+.

Replace COEP/COOP headers with the new Document-Isolation-Policy header on Chrome 137+ for cross-origin isolation. DIP provides per-document isolation without breaking third-party page builder iframes (e.g. Elementor). Non-DIP browsers skip cross-origin isolation entirely, since COEP/COOP caused CORS failures for embeds.

* Add `wp_get_chrome_major_version()` helper to detect Chromium browser version.
* Add `wp_use_document_isolation_policy` filter for customization.
* Set `window.__documentIsolationPolicy` JS flag when DIP is active.
* Skip cross-origin isolation when a third-party editor action is detected.
* Send `Document-Isolation-Policy: isolate-and-credentialless` header instead of COEP/COOP.
* Skip the output buffer entirely on non-DIP browsers to prevent CORS failures.

Props adamsilverstein.
Fixes #XXXXX. See https://github.com/WordPress/gutenberg/pull/75991.
