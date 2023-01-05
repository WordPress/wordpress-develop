/**
 * Adds show/hide toggle for the setup config database password.
 *
 * @since 6.2.0
 * @output wp-admin/js/password-toggle.js
 */

( function () {
	var toggle, status, input, icon, label;

	toggle = document.querySelector( '.pwd-toggle' );
	toggle.classList.remove('hide-if-no-js');
	toggle.addEventListener( 'click', togglePassword );

	function togglePassword() {
		status = toggle.getAttribute( 'data-toggle' );
		input = document.getElementById( 'pwd' );
		icon = toggle.getElementsByClassName( 'dashicons' )[ 0 ];
		label = toggle.getElementsByClassName( 'text' )[ 0 ];

		if ( 0 === parseInt( status, 10 ) ) {
			toggle.setAttribute( 'data-toggle', 1 );
			input.setAttribute( 'type', 'text' );
			icon.classList.remove( 'dashicons-visibility' );
			icon.classList.add( 'dashicons-hidden' );
		} else {
			toggle.setAttribute( 'data-toggle', 0 );
			input.setAttribute( 'type', 'password' );
			icon.classList.remove( 'dashicons-hidden' );
			icon.classList.add( 'dashicons-visibility' );
		}
	}
} )();
