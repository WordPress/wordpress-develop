/* global wp, jQuery */
jQuery( function() {
	QUnit.module( 'shortcode' );

	QUnit.test( 'next() should find the shortcode', function( assert ) {
		var result;

		// Basic.
		result = wp.shortcode.next( 'foo', 'this has the [foo] shortcode' );
		assert.equal( result.index, 13, 'foo shortcode found at index 13' );

		result = wp.shortcode.next( 'foo', 'this has the [foo param="foo"] shortcode' );
		assert.equal( result.index, 13, 'foo shortcode with params found at index 13' );
	});

	QUnit.test( 'next() should not find shortcodes that are not there', function( assert ) {
		var result;

		// Not found.
		result = wp.shortcode.next( 'bar', 'this has the [foo] shortcode' );
		assert.equal( result, undefined, 'bar shortcode not found' );

		result = wp.shortcode.next( 'bar', 'this has the [foo param="bar"] shortcode' );
		assert.equal( result, undefined, 'bar shortcode not found with params' );
	});

	QUnit.test( 'next() should find the shortcode when told to start looking beyond the start of the string', function( assert ) {
		var result;

		// Starting at indices.
		result = wp.shortcode.next( 'foo', 'this has the [foo] shortcode', 12 );
		assert.equal( result.index, 13, 'foo shortcode found before index 13' );

		result = wp.shortcode.next( 'foo', 'this has the [foo] shortcode', 13 );
		assert.equal( result.index, 13, 'foo shortcode found at index 13' );

		result = wp.shortcode.next( 'foo', 'this has the [foo] shortcode', 14 );
		assert.equal( result, undefined, 'foo shortcode not found after index 13' );
	});

	QUnit.test( 'next() should find the second instances of the shortcode when the starting indice is after the start of the first one', function( assert ) {
		var result;

		result = wp.shortcode.next( 'foo', 'this has the [foo] shortcode [foo] twice', 14 );
		assert.equal( result.index, 29, 'foo shortcode found the second foo at index 29' );
	});


	QUnit.test( 'next() should not find escaped shortcodes', function( assert ) {
		var result;

		// Escaped.
		result = wp.shortcode.next( 'foo', 'this has the [[foo]] shortcode' );
		assert.equal( result, undefined, 'foo shortcode not found when escaped' );

		result = wp.shortcode.next( 'foo', 'this has the [[foo param="foo"]] shortcode' );
		assert.equal( result, undefined, 'foo shortcode not found when escaped with params' );
	});

	QUnit.test( 'next() should find shortcodes that are incorrectly escaped by newlines', function( assert ) {
		var result;

		result = wp.shortcode.next( 'foo', 'this has the [\n[foo]] shortcode' );
		assert.equal( result.index, 15, 'shortcode found when incorrectly escaping the start of it' );

		result = wp.shortcode.next( 'foo', 'this has the [[foo]\n] shortcode' );
		assert.equal( result.index, 14, 'shortcode found when incorrectly escaping the end of it' );
	});

	QUnit.test( 'next() should still work when there are not equal ammounts of square brackets', function( assert ) {
		var result;

		result = wp.shortcode.next( 'foo', 'this has the [[foo] shortcode' );
		assert.equal( result.index, 14, 'shortcode found when there are offset square brackets' );

		result = wp.shortcode.next( 'foo', 'this has the [foo]] shortcode' );
		assert.equal( result.index, 13, 'shortcode found when there are offset square brackets' );
	});

	QUnit.test( 'next() should find the second instances of the shortcode when the first one is escaped', function( assert ) {
		var result;


		result = wp.shortcode.next( 'foo', 'this has the [[foo]] shortcode [foo] twice' );
		assert.equal( result.index, 31, 'foo shortcode found the non-escaped foo at index 31' );
	});

	QUnit.test( 'next() should not find shortcodes that are not full matches', function( assert ) {
		var result;

		// Stubs.
		result = wp.shortcode.next( 'foo', 'this has the [foobar] shortcode' );
		assert.equal( result, undefined, 'stub does not trigger match' );

		result = wp.shortcode.next( 'foobar', 'this has the [foo] shortcode' );
		assert.equal( result, undefined, 'stub does not trigger match' );
	});

	QUnit.test( 'replace() should replace the shortcode', function( assert ) {
		var result;

		// Basic.
		result = wp.shortcode.replace( 'foo', 'this has the [foo] shortcode', shortcodeReplaceCallback );
		assert.equal( result, 'this has the bar shortcode', 'foo replaced with bar' );

		result = wp.shortcode.replace( 'foo', 'this has the [foo param="foo"] shortcode', shortcodeReplaceCallback );
		assert.equal( result, 'this has the bar shortcode', 'foo and params replaced with bar' );
	});

	QUnit.test( 'replace() should not replace the shortcode when it does not match', function( assert ) {
		var result;

		// Not found.
		result = wp.shortcode.replace( 'bar', 'this has the [foo] shortcode', shortcodeReplaceCallback );
		assert.equal( result, 'this has the [foo] shortcode', 'bar not found' );

		result = wp.shortcode.replace( 'bar', 'this has the [foo param="bar"] shortcode', shortcodeReplaceCallback );
		assert.equal( result, 'this has the [foo param="bar"] shortcode', 'bar not found with params' );
	});

	QUnit.test( 'replace() should remove shortcodes when the callback returns an empty string', function( assert ) {
		var examples = [
			[ '[foo]', '' ],
			[ 'before [foo /] after', 'before  after' ],
			[ 'before [foo param="bar"]remove me[/foo] after', 'before  after' ],
			[ 'before [foo]first[/foo] between [foo /] and [foo]remove me[/foo] after', 'before  between  and  after' ]
		];

		jQuery.each( examples, function( index, example ) {
			var result = wp.shortcode.replace( 'foo', example[0], function() {
				return '';
			} );

			assert.strictEqual( result, example[1], example[0] );
		} );
	});

	QUnit.test( 'replace() should preserve escaping when the callback returns an empty string', function( assert ) {
		var calls = 0,
			result = wp.shortcode.replace( 'foo', 'before [[foo]] [foo] after', function() {
				calls++;
				return '';
			} );

		assert.strictEqual( result, 'before [[foo]]  after', 'Escaped shortcodes are preserved' );
		assert.strictEqual( calls, 1, 'The callback only runs for unescaped shortcodes' );

		jQuery.each( [ [ '[[foo]', '[' ], [ '[foo]]', ']' ] ], function( index, example ) {
			result = wp.shortcode.replace( 'foo', example[0], function() {
				return '';
			} );

			assert.strictEqual( result, example[1], 'Unmatched brackets are preserved in ' + example[0] );
		} );
	});

	QUnit.test( 'replace() should preserve shortcodes for other falsey callback results', function( assert ) {
		jQuery.each( [ false, null, undefined, 0, NaN ], function( index, value ) {
			var result = wp.shortcode.replace( 'foo', 'before [foo] after', function() {
				return value;
			} );

			assert.strictEqual( result, 'before [foo] after', 'Preserves the shortcode for ' + value );
		} );
	});

	QUnit.test( 'replace() should accept nonempty callback strings', function( assert ) {
		jQuery.each( [ '0', ' ' ], function( index, value ) {
			var result = wp.shortcode.replace( 'foo', 'before [foo] after', function() {
				return value;
			} );

			assert.strictEqual( result, 'before ' + value + ' after', 'Replaces the shortcode with "' + value + '"' );
		} );
	});

	QUnit.test( 'replace() should replace the shortcode in all instances of its use', function( assert ) {
		var result;

		// Multiple instances.
		result = wp.shortcode.replace( 'foo', 'this has the [foo] shortcode [foo] twice', shortcodeReplaceCallback );
		assert.equal( result, 'this has the bar shortcode bar twice', 'foo replaced with bar twice' );

		result = wp.shortcode.replace( 'foo', 'this has the [foo param="foo"] shortcode [foo] twice', shortcodeReplaceCallback );
		assert.equal( result, 'this has the bar shortcode bar twice', 'foo and params replaced with bar twice' );
	});

	QUnit.test( 'replace() should not replace the escaped shortcodes', function( assert ) {
		var result;

		// Escaped.
		result = wp.shortcode.replace( 'foo', 'this has the [[foo]] shortcode', shortcodeReplaceCallback );
		assert.equal( result, 'this has the [[foo]] shortcode', 'escaped foo not replaced' );

		result = wp.shortcode.replace( 'foo', 'this has the [[foo param="bar"]] shortcode', shortcodeReplaceCallback );
		assert.equal( result, 'this has the [[foo param="bar"]] shortcode', 'escaped foo with params not replaced' );

		result = wp.shortcode.replace( 'foo', 'this [foo] has the [[foo param="bar"]] shortcode escaped', shortcodeReplaceCallback );
		assert.equal( result, 'this bar has the [[foo param="bar"]] shortcode escaped', 'escaped foo with params not replaced but unescaped foo replaced' );
	});

	QUnit.test( 'replace() should replace improperly escaped shortcodes that include newlines', function( assert ) {
		var result;

		result = wp.shortcode.replace( 'foo', 'this [foo] has the [[foo param="bar"]\n] shortcode ', shortcodeReplaceCallback );
		assert.equal( result, 'this bar has the [bar\n] shortcode ', 'escaping with newlines should not actually escape the content' );

		result = wp.shortcode.replace( 'foo', 'this [foo] has the [\n[foo param="bar"]] shortcode ', shortcodeReplaceCallback );
		assert.equal( result, 'this bar has the [\nbar] shortcode ', 'escaping with newlines should not actually escape the content' );
	});

	QUnit.test( 'replace() should not replace the shortcode when it is an incomplete match', function( assert ) {
		var result;

		// Stubs.
		result = wp.shortcode.replace( 'foo', 'this has the [foobar] shortcode', shortcodeReplaceCallback );
		assert.equal( result, 'this has the [foobar] shortcode', 'stub not replaced' );

		result = wp.shortcode.replace( 'foobar', 'this has the [foo] shortcode', shortcodeReplaceCallback );
		assert.equal( result, 'this has the [foo] shortcode', 'stub not replaced' );
	});

	/**
	 * A callback function for the replace tests.
	 */
	function shortcodeReplaceCallback( ) {
		return 'bar';
	}

    QUnit.test( 'attrs() should return named attributes created with single, double, and no quotes', function( assert ) {
        var expected = {
            'named': {
                'param': 'foo',
                'another': 'bar',
                'andagain': 'baz'
            }, 'numeric' : []
        };

        assert.deepEqual( wp.shortcode.attrs('param="foo" another=\'bar\' andagain=baz'), expected, 'attr parsed all three named types');
    });

    QUnit.test( 'attrs() should return numeric attributes in the order they are used', function( assert ) {
        var expected = {
            'named': {}, 'numeric' : ['foo', 'bar', 'baz']
        };

        assert.deepEqual( wp.shortcode.attrs('foo bar baz'), expected, 'attr parsed numeric attributes');
    });

    QUnit.test( 'attrs() should return numeric attributes in the order they are used when they have named attributes in between', function( assert ) {
        var expected = {
            'named': { 'not': 'a blocker'  }, 'numeric' : ['foo', 'bar', 'baz']
        };

        assert.deepEqual( wp.shortcode.attrs('foo not="a blocker" bar baz'), expected, 'attr parsed numeric attributes');
    });

	QUnit.test( 'attrs() should return numeric attributes created with single, double, and no quotes', function( assert ) {
		var expected = {
			'named': {}, 'numeric' : ['foo', 'bar', 'baz']
		};

		assert.deepEqual( wp.shortcode.attrs('foo "bar" \'baz\''), expected, 'attr parsed numeric attributes');
	});
	
	QUnit.test( 'attrs() should return mixed attributes created with single, double, and no quotes', function( assert ) {
		var expected = {
			'named': { a: 'foo', b: 'bar', c: 'baz' }, 'numeric' : ['foo', 'bar', 'baz']
		};

		assert.deepEqual( wp.shortcode.attrs('a="foo" b=\'bar\' c=baz foo "bar" \'baz\''), expected, 'attr parsed numeric attributes');
	});

	QUnit.test( 'string() should accept attrs in any order', function( assert ) {
		var expected = '[short abc123 foo="bar"]';
		var result;

		result = wp.shortcode.string({
			tag   : 'short',
			type  : 'single',
			attrs : {
				named   : { foo : 'bar' },
				numeric : [ 'abc123' ]
			}
		});
		assert.deepEqual( result, expected, 'attributes are accepted in any order' );

		result = wp.shortcode.string({
			tag   : 'short',
			type  : 'single',
			attrs : {
				numeric : [ 'abc123' ],
				named   : { foo : 'bar' }
			}
		});
		assert.deepEqual( result, expected, 'attributes are accepted in any order' );
	});
});
