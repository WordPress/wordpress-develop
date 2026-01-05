/* globals wp, Backbone */
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

	// Test non-string type edge cases
	QUnit.test( 'Attachments should not process null order value', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: null
			}
		});

		assert.strictEqual( collection.props.get('order'), null,
			'Null order should remain null (not processed by normalization)' );
	});

	QUnit.test( 'Attachments should not process undefined order value', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: undefined
			}
		});

		assert.strictEqual( collection.props.get('order'), undefined,
			'Undefined order should remain undefined (not processed by normalization)' );
	});

	QUnit.test( 'Attachments should not process numeric order value', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: 123
			}
		});

		assert.strictEqual( collection.props.get('order'), 123,
			'Numeric order should remain unchanged (not processed by normalization)' );
	});

	QUnit.test( 'Attachments should not process boolean true order value', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: true
			}
		});

		assert.strictEqual( collection.props.get('order'), true,
			'Boolean true order should remain unchanged (not processed by normalization)' );
	});

	QUnit.test( 'Attachments should not process boolean false order value', function( assert ) {
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: false
			}
		});

		assert.strictEqual( collection.props.get('order'), false,
			'Boolean false order should remain unchanged (not processed by normalization)' );
	});

	QUnit.test( 'Attachments should not process object order value', function( assert ) {
		var orderObj = { value: 'ASC' };
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: orderObj
			}
		});

		assert.strictEqual( collection.props.get('order'), orderObj,
			'Object order should remain unchanged (not processed by normalization)' );
	});

	QUnit.test( 'Attachments should not process array order value', function( assert ) {
		var orderArray = ['ASC', 'DESC'];
		var collection = new wp.media.model.Attachments( [], {
			props: {
				order: orderArray
			}
		});

		assert.strictEqual( collection.props.get('order'), orderArray,
			'Array order should remain unchanged (not processed by normalization)' );
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

	// Test Query model inheritance
	QUnit.test( 'Query should inherit order normalization from Attachments', function( assert ) {
		var query = new wp.media.model.Query( [], {
			props: {
				order: 'asc',
				query: true
			}
		});

		assert.strictEqual( query.props.get('order'), 'ASC',
			'Query model should normalize order through inheritance from Attachments' );
		assert.ok( query instanceof wp.media.model.Attachments,
			'Query should be instance of Attachments' );
	});

	QUnit.test( 'Query should default invalid order to "DESC"', function( assert ) {
		var query = new wp.media.model.Query( [], {
			props: {
				order: 'random',
				query: true
			}
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
