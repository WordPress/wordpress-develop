/**
 * Handles delayed execution of before/after inline scripts for defer/async scripts.
 *
 * @output wp-includes/js/wp-delayed-inline-script-loader.js
 */

(function (window, document) {
	var nonce = document.currentScript.nonce,
		done = new Set();

	/**
	 * Runs an inline script.
	 *
	 * @param {HTMLScriptElement} script Script to run.
	 */
	function runInlineScript( script ) {
		var newScript;
		script.dataset.wpDone = '1';
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
	 * Determines whether a script was loaded.
	 *
	 * @param {string} dep Dependency handle.
	 * @returns {boolean} Whether dependency was done.
	 */
	function isDependencyDone(dep) {
		return done.has(dep);
	}

	/**
	 * Runs the supplied inline scripts if all of their dependencies have been done.
	 *
	 * @param {NodeList<HTMLScriptElement>} scripts Scripts to run if ready,
	 */
	function runReadyInlineScripts(scripts) {
		var i, len, deps;
		for (i = 0, len = scripts.length; i < len; i++) {
			deps = scripts[i].dataset.wpDeps.split(/,/);
			if ( deps.every(isDependencyDone) ) {
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
		var matches, scripts, handle, script, currentNode;
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
		done.add( handle );

		currentNode = event.target;

		// First, run all inline after scripts which are associated with this handle.
		script = document.querySelector(
			'script:not([src])[type="text/template"][id="' + handle + '-js-after"]'
		);
		if ( script instanceof HTMLScriptElement ) {
			runInlineScript(script);
			currentNode = script; // @todo We cannot rely on this because currentNode may be in the <head> and the next before script could be in the footer.
		}

		// Next, run all pending inline before scripts for all dependents for which all dependencies have loaded.
		// TODO: We can iterate over the following siblings
		// If currentNode.parentNode === document.head, we'll need to look for following siblings as well as all nodes in body.
		// If currentNode.parentNode === document.body, we only need to look at the following siblings.
		scripts = document.querySelectorAll(
			'script:not([src])[type="text/template"][data-wp-deps][id$="-js-before"]:not([data-wp-done])'
		);
		runReadyInlineScripts(scripts);
	}
	document.addEventListener("load", onScriptLoad, true);

	window.addEventListener(
		"load",
		function() {
			document.removeEventListener("load", onScriptLoad, true);
		},
		{ once: true }
	);
})(window, document);
