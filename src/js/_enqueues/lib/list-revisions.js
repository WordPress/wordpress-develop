/**
 * @output wp-includes/js/wp-list-revisions.js
 */

/**
 * Hides the revisions radio buttons to stop selecting reverse comparisons
 */

( () => {
	const init = () => {
		const pr = document.getElementById( 'post-revisions' );
		if ( ! pr ) {
			return;
		}

		const inputs = pr.getElementsByTagName( 'input' );
		if ( inputs.length === 0 ) {
			return;
		}

		const update = () => {
			let checkCount = 0;
			for ( let i = 0; i < inputs.length; i++ ) {
				checkCount += inputs[ i ].checked ? 1 : 0;
				const side = inputs[ i ].getAttribute( 'name' );
				if (
					! inputs[ i ].checked &&
					( ( 'left' === side && 1 > checkCount ) ||
						( 'right' === side &&
							1 < checkCount &&
							( ! inputs[ i - 1 ] ||
								! inputs[ i - 1 ].checked ) ) ) &&
					! (
						inputs[ i + 1 ] &&
						inputs[ i + 1 ].checked &&
						'right' === inputs[ i + 1 ].getAttribute( 'name' )
					)
				) {
					inputs[ i ].style.visibility = 'hidden';
				} else if ( 'left' === side || 'right' === side ) {
					inputs[ i ].style.visibility = 'visible';
				}
			}
		};

		pr.addEventListener( 'click', update );
		update();
	};
	window.addEventListener( 'load', init, false );
} )();
