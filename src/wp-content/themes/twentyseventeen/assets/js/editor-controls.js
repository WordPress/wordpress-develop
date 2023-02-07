/**
 * Modifies the editor controls based on default Twenty Seventeen styles.
 */

window.addEventListener( 'load', function () {
	reverseItalicControl();
} );

/**
 * Reverses the the Italic (`I`) control on the block editor toolbar.
 *
 * @see https://core.trac.wordpress.org/ticket/56747#comment:25
 */
function reverseItalicControl() {
	var editor = document.querySelector( '#editor' );

	// Reverse on click.
	editor.addEventListener( 'click', eventHandler );

	/*
	 * Reverse on keyboard navigation.
	 * Both `keyup` and `keydown` are needed here.
	 */
	editor.addEventListener( 'keydown', function ( event ) {
		// Only fire on arrow keys.
		if ( 'ArrowUp' === event.key || 'ArrowDown' === event.key || 'ArrowLeft' === event.key || 'ArrowRight' === event.key ) {
			eventHandler();
		}
	} );
	editor.addEventListener( 'keyup', function ( event ) {
		// Only fire on arrow keys.
		if ( 'ArrowUp' === event.key || 'ArrowDown' === event.key || 'ArrowLeft' === event.key || 'ArrowRight' === event.key ) {
			eventHandler();
		}
	} );

	// Reverse on mouse/keyboard selection.
	editor.addEventListener( 'selectstart', function () {
		document.addEventListener( 'selectionchange', eventHandler );
	} );
	editor.addEventListener( 'mouseup', function () {
		document.removeEventListener( 'selectionchange', eventHandler );
	} );

	function eventHandler() {
		var btnItalic = document.querySelector( '#editor .components-toolbar button[aria-label="Italic"]' );
		var selection = document.getSelection();

		if ( selection.focusNode ) {
			var parentNode = selection.focusNode.parentNode;

			if ( btnItalic && parentNode.nodeName ) {
				if ( btnItalic && 'FIGCAPTION' === parentNode.parentNode.nodeName && ( 'EM' === parentNode.nodeName || 'I' === parentNode.nodeName || 'CITE' === parentNode.nodeName ) ) {
					btnItalic.classList.remove( 'is-pressed' );
					btnItalic.ariaPressed = 'false';
				} else if ( btnItalic && 'FIGCAPTION' === parentNode.nodeName ) {
					btnItalic.classList.add( 'is-pressed' );
					btnItalic.ariaPressed = 'true';
				}
			}
		}
	}
}
