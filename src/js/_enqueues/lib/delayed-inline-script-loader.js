/**
 * Handles delayed execution of before/after inline scripts for defer/async scripts.
 *
 * @output wp-includes/js/wp-delayed-inline-script-loader.js
 */

(function (window, document) {
	var nonce = document.currentScript.nonce,
		doneDependencies = new Set();

	/**
	 * Determines whether a script was loaded.
	 *
	 * @param {string} dep Dependency handle.
	 * @returns {boolean} Whether dependency was done.
	 */
	function isDependencyDone(dep) {
		return doneDependencies.has(dep);
	}

	/**
	 * Runs an inline script.
	 *
	 * @param {HTMLScriptElement} script Script to run.
	 */
	function runInlineScript(script) {
		var newScript;
		script.dataset.wpDone = "1";
		if (nonce && nonce !== script.nonce) {
			console.error(
				"CSP nonce check failed for after inline script. Execution aborted.",
				script
			);
			return;
		}
		newScript = script.cloneNode(true);
		newScript.type = "text/javascript";
		script.parentNode.replaceChild(newScript, script);
	}

	/**
	 * Runs the supplied inline scripts if all of their dependencies have been done.
	 *
	 * @param {NodeList<HTMLScriptElement>} scripts Scripts to run if ready.
	 */
	function runReadyInlineScripts(scripts) {
		var i, len, deps;
		for (i = 0, len = scripts.length; i < len; i++) {
			deps = scripts[i].dataset.wpDeps.split(/,/);
			if (deps.every(isDependencyDone)) {
				runInlineScript(scripts[i]);
			}
		}
	}

	/**
	 * Runs whenever a load event happens.
	 *
	 * @param {Event} event Event.
	 */
	function onScriptLoad(event) {
		var matches, handle, script;
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

		// TODO: Consider adding a data attribute which specifically contains the handle.
		matches = event.target.id.match(/^(.+)-js$/);
		if (!matches) {
			return;
		}
		handle = matches[1];
		doneDependencies.add(handle);

		// First, run all inline after scripts which are associated with this handle.
		script = document.querySelector(
			'script:not([src])[type="text/template"][id="' + handle + '-js-after"]'
		);
		if (script instanceof HTMLScriptElement) {
			runInlineScript(script);
		}

		// Next, run all pending inline before scripts for all dependents for which all dependencies have loaded.
		runReadyInlineScripts(
			document.querySelectorAll(
				'script:not([src])[type="text/template"][data-wp-deps][id$="-js-before"]:not([data-wp-done])'
			)
		);
	}
	document.addEventListener("load", onScriptLoad, true);

	window.addEventListener(
		"load",
		function () {
			document.removeEventListener("load", onScriptLoad, true);
		},
		{ once: true }
	);
})(window, document);
