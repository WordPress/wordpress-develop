( function( QUnit, wp ) {
	QUnit.module( 'editor' );

	QUnit.test( 'wp.editor.autop handles line breaks inside [caption] shortcodes correctly (#66373)', function( assert ) {
		var tests = [
			{
				message: 'Preserves <br />\\n inside [caption] without doubling to <br /><br />',
				input: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br />\nCaption line 2[/caption]',
				expected: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br />Caption line 2[/caption]\n'
			},
			{
				message: 'Preserves <br>\\n inside [caption] without doubling to <br><br>',
				input: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br>\nCaption line 2[/caption]',
				expected: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br>Caption line 2[/caption]\n'
			},
			{
				message: 'Preserves <br />\\r\\n (Windows newlines) inside [caption] without doubling',
				input: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br />\r\nCaption line 2[/caption]',
				expected: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br />Caption line 2[/caption]\n'
			},
			{
				message: 'Preserves attributes on <br> followed by newline inside [caption]',
				input: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br class="custom" />\nCaption line 2[/caption]',
				expected: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br class="custom" />Caption line 2[/caption]\n'
			},
			{
				message: 'Preserves <br /> without trailing newline inside [caption]',
				input: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br />Caption line 2[/caption]',
				expected: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br />Caption line 2[/caption]\n'
			},
			{
				message: 'Converts plain newline \\n inside [caption] to <br />',
				input: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1\nCaption line 2[/caption]',
				expected: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br />Caption line 2[/caption]\n'
			},
			{
				message: 'Handles multiple lines with <br />\\n inside [caption]',
				input: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Line 1<br />\nLine 2<br />\nLine 3[/caption]',
				expected: '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Line 1<br />Line 2<br />Line 3[/caption]\n'
			}
		];

		_.each( tests, function( test ) {
			assert.equal( wp.editor.autop( test.input ), test.expected, test.message );
		} );
	} );

	QUnit.test( 'wp.editor.autop maintains stability across repeated autop and removep cycles (#66373)', function( assert ) {
		var content = '[caption id="attachment_123" align="alignnone" width="300"]<img src="test.jpg" /> Caption line 1<br />\nCaption line 2[/caption]';
		var firstPass = wp.editor.autop( content );

		// Simulating repeated toggling between Text and Visual editors.
		var current = firstPass;
		for ( var i = 0; i < 5; i++ ) {
			current = wp.editor.autop( wp.editor.removep( current ) );
		}

		assert.equal( current, firstPass, 'Content does not accumulate extra <br> tags over multiple editor toggle cycles' );
	} );
} )( window.QUnit, window.wp );
