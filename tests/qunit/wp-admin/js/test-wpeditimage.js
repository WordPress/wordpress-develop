/* global tinymce */

QUnit.test( 'Caption is preserved when the image width attribute is missing', function( assert ) {
	var done = assert.async();
	var textarea = document.createElement( 'textarea' );
	var shortcode = '[caption id="attachment_12345" align="alignnone" width="150"]<img class="wp-image-12345 size-thumbnail" src="https://example.org/image.png" alt="" height="150" /> My caption[/caption]';

	document.body.appendChild( textarea );

	tinymce.init( {
		target: textarea,
		plugins: 'wpeditimage',
		menubar: false,
		toolbar: false,
		setup: function( editor ) {
			editor.on( 'init', function() {
				var content = editor.wpSetImgCaption( shortcode );
				var output = editor.wpGetImgCaption( content );

				assert.ok( output.indexOf( '[caption' ) !== -1, 'The caption shortcode is retained.' );
				assert.ok( output.indexOf( 'width="150"' ) !== -1, 'The caption width is retained.' );
				assert.ok( output.indexOf( 'My caption[/caption]' ) !== -1, 'The caption text is retained.' );

				editor.remove();
				textarea.remove();
				done();
			} );
		}
	} );
} );
