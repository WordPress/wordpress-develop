/**
 * @output wp-includes/js/wp-list-revisions.js
 */

/**
 * Hides the revisions radio buttons to stop selecting reverse comparisons
 *
 * @param {Window} w The global window object.
 */

(function(w) {
	var init = function() {
		var pr = document.getElementById('post-revisions'),
		inputs = pr ? pr.getElementsByTagName('input') : [],
		update = function() {
			var i, checkCount = 0, side;
			for ( i = 0; i < inputs.length; i++ ) {
				checkCount += inputs[i].checked ? 1 : 0;
				side = inputs[i].getAttribute('name');
				if ( ! inputs[i].checked &&
				( 'left' == side && 1 > checkCount || 'right' == side && 1 < checkCount && ( ! inputs[i-1] || ! inputs[i-1].checked ) ) &&
				! ( inputs[i+1] && inputs[i+1].checked && 'right' == inputs[i+1].getAttribute('name') ) )
					inputs[i].style.visibility = 'hidden';
				else if ( 'left' == side || 'right' == side )
					inputs[i].style.visibility = 'visible';
			}
		};

		if ( ! pr ) {
			return;
		}

		pr.onclick = update;
		update();
	};
	w.addEventListener('load', init, false);
})(window);
