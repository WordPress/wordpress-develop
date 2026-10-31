/* global tinymce, switchEditors */
( function( $ ) {
	var editor, textarea, setUserSetting;

	QUnit.module( 'switchEditors: list bookmarks', {
		beforeEach: function( assert ) {
			var done = assert.async();

			setUserSetting = window.setUserSetting;
			window.setUserSetting = function() {};
			$( '#qunit-fixture' ).append( '<div id="wp-list-editor-wrap"><textarea id="list-editor"></textarea></div>' );
			textarea = document.getElementById( 'list-editor' );
			tinymce.init( {
				selector: '#list-editor',
				theme: false,
				skin: false,
				fix_list_elements: true,
				init_instance_callback: function( instance ) {
					editor = instance;
					done();
				}
			} );
		},
		afterEach: function() {
			editor.remove();
			window.setUserSetting = setUserSetting;
		}
	} );

	function switchToVisual( html, start, end ) {
		editor.hide();
		textarea.value = html;
		textarea.setSelectionRange( start, end === undefined ? start : end );
		switchEditors.go( 'list-editor', 'tmce' );
	}

	[ 'ul', 'ol' ].forEach( function( tag ) {
		var html = '<' + tag + '>\n<li>A</li>\n<li>B</li>\n<li>C</li>\n<li>D</li>\n<li>E</li>\n</' + tag + '>';

		[ 'A', 'B', 'C', 'D', 'E' ].forEach( function( item ) {
			QUnit.test( tag + ': cursor after ' + item + ' closing tag (#66379)', function( assert ) {
				var position = html.indexOf( item + '</li>' ) + 6;

				editor.setContent( html );
				var expected = editor.getContent();
				switchToVisual( html, position );

				assert.strictEqual( editor.getContent(), expected, 'List markup is unchanged.' );
				assert.strictEqual( editor.selection.getNode().textContent, item, 'Cursor remains in the preceding item.' );
				assert.strictEqual( editor.dom.select( '.mce_SELRES_start, .mce_SELRES_end' ).length, 0, 'Bookmarks are removed.' );
			} );
		} );

		QUnit.test( tag + ': selection between list items (#66379)', function( assert ) {
			var start = html.indexOf( 'A</li>' ) + 6,
				end = html.indexOf( 'D</li>' ) + 6;

			editor.setContent( html );
			var expected = editor.getContent();
			switchToVisual( html, start, end );

			assert.strictEqual( editor.getContent(), expected, 'List markup is unchanged.' );
			assert.strictEqual( editor.selection.getContent( { format: 'text' } ).replace( /\s/g, '' ), 'BCD', 'Selected text is preserved.' );
		} );
	} );

	[
		[ 'Whitespace after an item', '<ul><li>A</li>\n\t |<li>B</li></ul>' ],
		[ 'Inside a closing tag', '<ul><li>A</l|i><li>B</li></ul>' ],
		[ 'Before a closing tag', '<ul><li>A|</li><li>B</li></ul>' ],
		[ 'Nested list', '<ul><li>A<ol><li>B</li>|<li>C</li></ol></li><li>D</li></ul>' ],
		[ 'Uppercase tags', '<UL><LI>A</LI>|<LI>B</LI></UL>' ],
		[ 'Item with attributes', '<ol start="3"><li value="4"><strong>A</strong></li>|<li>B</li></ol>' ],
		[ 'Paragraph control', '<p>A</p>|<p>B</p>' ]
	].forEach( function( testCase ) {
		QUnit.test( testCase[0] + ' (#66379)', function( assert ) {
			var position = testCase[1].indexOf( '|' ),
				html = testCase[1].replace( '|', '' );

			editor.setContent( html );
			var expected = editor.getContent();
			switchToVisual( html, position );

			assert.strictEqual( editor.getContent(), expected, 'Content is unchanged.' );
		} );
	} );
}( jQuery ) );
