/**
 * Adds functionality for password visibility buttons to toggle between text and password input types.
 *
 * @since 6.2.0
 * @output wp-admin/js/password-toggle.js
 */

( function () {
	var toggleElements, status, input, icon, label;

	toggleElements = document.querySelectorAll( '.pwd-toggle' );

	toggleElements.forEach( function (toggle) {
		toggle.classList.remove( 'hide-if-no-js' );
		toggle.addEventListener( 'click', togglePassword );
	} );

	function togglePassword() {
		status = this.getAttribute( 'data-toggle' );
		input = this.parentElement.children.namedItem( 'pwd' );
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
