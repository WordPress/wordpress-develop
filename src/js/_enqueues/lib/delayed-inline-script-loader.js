/**
 * Handles delayed execution of before/after inline scripts for defer/async scripts.
 *
 * @output wp-includes/js/wp-delayed-inline-script-loader.js
 */

(function (window, document) {
	var nonce = document.currentScript.nonce;

	/**
	 * Load event handler.
	 *
	 * @param {Event} event Event.
	 */
	function onScriptLoad(event) {
		var i, len, newScript, matches, scripts, handle;
		if (
			!(
				event.target instanceof HTMLScriptElement ||
				event.target.async ||
				event.target.defer ||
				event.target.id
			)
		) {
			return;
		}
		matches = event.target.id.match(/^(.+)-js$/);
		if (!matches) {
			return;
		}
		handle = matches[1];
		scripts = document.querySelectorAll(
			// TODO: Handle multiple deps.
			'[type="text/template"][data-wp-deps="' + handle + '"]' // TODO: Consider text/plain instead.
		);
		for (i = 0, len = scripts.length; i < len; i++) {
			if (nonce && nonce !== scripts[i].nonce) {
				console.error(
					"CSP nonce check failed for after inline script. Execution aborted.",
					scripts[i]
				);
				continue;
			}
			newScript = scripts[i].cloneNode(true);
			newScript.type = "text/javascript";
			scripts[i].parentNode.replaceChild(newScript, scripts[i]);
		}
	}
	document.addEventListener("load", onScriptLoad, true);

	window.addEventListener(
		"load",
		() => {
			document.removeEventListener("load", onScriptLoad, true);
		},
		{ once: true }
	);
})(window, document);
