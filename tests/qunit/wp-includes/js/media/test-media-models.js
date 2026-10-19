/* globals wp */
/* jshint qunit: true */
/* eslint-env qunit */
/* eslint-disable no-magic-numbers */

( function() {
	'use strict';

	QUnit.module( 'Media Models - Order Normalization' );

	// Test valid uppercase values
	QUnit.test( 'Attachments should accept uppercase "ASC" order', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: 'ASC'
			}
		});

		assert.strictEqual( collection.props.get('order'), 'ASC',
			'Order should remain ASC when passed as uppercase' );
	});

	QUnit.test( 'Attachments should accept uppercase "DESC" order', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: 'DESC'
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Order should remain DESC when passed as uppercase' );
	});

	// Test lowercase normalization
	QUnit.test( 'Attachments should normalize lowercase "asc" to uppercase', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: 'asc'
			}
		});

		assert.strictEqual( collection.props.get('order'), 'ASC',
			'Order should be converted from lowercase asc to uppercase ASC' );
	});

	QUnit.test( 'Attachments should normalize lowercase "desc" to uppercase', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: 'desc'
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Order should be converted from lowercase desc to uppercase DESC' );
	});

	// Test mixed case normalization
	QUnit.test( 'Attachments should normalize mixed case "AsC" to uppercase', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: 'AsC'
			}
		});

		assert.strictEqual( collection.props.get('order'), 'ASC',
			'Order should be converted from mixed case AsC to uppercase ASC' );
	});

	QUnit.test( 'Attachments should normalize mixed case "DeSc" to uppercase', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: 'DeSc'
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Order should be converted from mixed case DeSc to uppercase DESC' );
	});

	// Test invalid string values
	QUnit.test( 'Attachments should default invalid string order to "DESC"', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: 'invalid'
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Invalid string order should default to DESC' );
	});

	QUnit.test( 'Attachments should default empty string order to "DESC"', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: ''
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Empty string order should default to DESC' );
	});

	/*
	 * An unset order is left alone so the existing 'DESC' fallbacks in
	 * Attachments.comparator() still apply. Any value that *is* set gets
	 * normalized, otherwise a truthy non-string would sort ascending.
	 */
	QUnit.test( 'Attachments should leave a null order value unset', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: null
			}
		});

		assert.strictEqual( collection.props.get('order'), null,
			'Null order should remain null' );
	});

	QUnit.test( 'Attachments should leave an undefined order value unset', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: undefined
			}
		});

		assert.strictEqual( collection.props.get('order'), undefined,
			'Undefined order should remain undefined' );
	});

	QUnit.test( 'Attachments should default a numeric order to "DESC"', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: 123
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Numeric order should default to DESC' );
	});

	QUnit.test( 'Attachments should default a boolean true order to "DESC"', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: true
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Boolean true order should default to DESC' );
	});

	QUnit.test( 'Attachments should default a boolean false order to "DESC"', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: false
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Boolean false order should default to DESC' );
	});

	QUnit.test( 'Attachments should default an object order to "DESC"', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: { value: 'ASC' }
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Object order should default to DESC' );
	});

	QUnit.test( 'Attachments should default an array order to "DESC"', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: ['ASC', 'DESC']
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Array order should default to DESC' );
	});

	// Test when no order property is provided
	QUnit.test( 'Attachments should work when no order property is provided', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				orderby: 'date'
			}
		});

		assert.strictEqual( collection.props.get('order'), undefined,
			'Order should be undefined when not provided' );
	});

	/*
	 * Query no longer normalizes the order itself, it relies on inheriting the
	 * normalization above. Note these pass `args` rather than `props.query`:
	 * setting `query` would kick off a server request via `_requery()`.
	 */
	QUnit.test( 'Query should inherit order normalization from Attachments', function( assert ) {
		var query = new wp.media.model.Query( [], {
			props: {
				order: 'asc'
			},
			args: {}
		});

		assert.strictEqual( query.props.get('order'), 'ASC',
			'Query model should normalize order through inheritance from Attachments' );
		assert.ok( query instanceof wp.media.model.Attachments,
			'Query should be instance of Attachments' );
	});

	QUnit.test( 'Query should default invalid order to "DESC"', function( assert ) {
		var query = new wp.media.model.Query( [], {
			props: {
				order: 'random'
			},
			args: {}
		});

		assert.strictEqual( query.props.get('order'), 'DESC',
			'Query model should default invalid order to DESC' );
	});

	// Test whitespace handling
	QUnit.test( 'Attachments should handle order with whitespace', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: '  asc  '
			}
		});

		assert.notStrictEqual( collection.props.get('order'), 'ASC',
			'Order with whitespace should not match ASC exactly' );
		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Order with whitespace should default to DESC as it does not match ASC/DESC after toUpperCase' );
	});

	// Test unicode characters
	QUnit.test( 'Attachments should handle order with unicode characters', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: 'asc\u200B'  // Zero-width space
			}
		});

		assert.strictEqual( collection.props.get('order'), 'DESC',
			'Order with unicode characters should default to DESC' );
	});

})();
