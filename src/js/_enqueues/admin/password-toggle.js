/**
 * Adds show/hide toggle for the setup config database password.
 *
 * @since 6.2.0
 * @output wp-admin/js/password-toggle.js
 */

( function () {
	var toggle, status, input, icon, label;

	toggle = document.querySelectorAll('.pwd-toggle');

	toggle.forEach( function (t) {
		t.classList.remove('hide-if-no-js');
		t.addEventListener( 'click', togglePassword );
	} );

	function togglePassword() {
		status = this.getAttribute( 'data-toggle' );
		input = document.getElementById( 'pwd' );
		icon = this.getElementsByClassName( 'dashicons' )[ 0 ];
		label = this.getElementsByClassName( 'text' )[ 0 ];

		if ( 0 === parseInt( status, 10 ) ) {
			this.setAttribute( 'data-toggle', 1 );
			input.setAttribute( 'type', 'text' );
			icon.classList.remove( 'dashicons-visibility' );
			icon.classList.add( 'dashicons-hidden' );
		} else {
			this.setAttribute( 'data-toggle', 0 );
			input.setAttribute( 'type', 'password' );
			icon.classList.remove( 'dashicons-hidden' );
			icon.classList.add( 'dashicons-visibility' );
		}
	}
} )();
