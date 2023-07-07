/* global wp */

jQuery( function( $ ) {
	var FooSuperClass, BarSubClass, foo, bar, ConstructorTestClass, newConstructor, constructorTest, $mockElement, mockString,
	firstInitialValue, firstValueInstance, valuesInstance, wasCallbackFired, mockValueCallback;

	QUnit.module( 'Customize Base: Class' );

	FooSuperClass = wp.customize.Class.extend(
		{
			initialize: function( instanceProps ) {
				$.extend( this, instanceProps || {} );
			},
			protoProp: 'protoPropValue'
		},
		{
			staticProp: 'staticPropValue'
		}
	);
	QUnit.test( 'FooSuperClass is a function', function( assert ) {
		assert.equal( typeof FooSuperClass, 'function' );
	});
	QUnit.test( 'FooSuperClass prototype has protoProp', function( assert ) {
		assert.equal( FooSuperClass.prototype.protoProp, 'protoPropValue' );
	});
	QUnit.test( 'FooSuperClass does not have protoProp', function( assert ) {
		assert.equal( typeof FooSuperClass.protoProp, 'undefined' );
	});
	QUnit.test( 'FooSuperClass has staticProp', function( assert ) {
		assert.equal( FooSuperClass.staticProp, 'staticPropValue' );
	});
	QUnit.test( 'FooSuperClass prototype does not have staticProp', function( assert ) {
		assert.equal( typeof FooSuperClass.prototype.staticProp, 'undefined' );
	});

	foo = new FooSuperClass( { instanceProp: 'instancePropValue' } );
	QUnit.test( 'FooSuperClass instance foo extended Class', function( assert ) {
		assert.equal( foo.extended( wp.customize.Class ), true );
	});
	QUnit.test( 'foo instance has protoProp', function( assert ) {
		assert.equal( foo.protoProp, 'protoPropValue' );
	});
	QUnit.test( 'foo instance does not have staticProp', function( assert ) {
		assert.equal( typeof foo.staticProp, 'undefined' );
	});
	QUnit.test( 'FooSuperClass instance foo ran initialize() and has supplied instanceProp', function( assert ) {
		assert.equal( foo.instanceProp, 'instancePropValue' );
	});

	// @todo Test Class.applicator?
	// @todo Do we test object.instance?

	QUnit.module( 'Customize Base: Subclass' );

	BarSubClass = FooSuperClass.extend(
		{
			initialize: function ( instanceProps ) {
				FooSuperClass.prototype.initialize.call( this, instanceProps );
				this.subInstanceProp = 'subInstancePropValue';
			},
			subProtoProp: 'subProtoPropValue'
		},
		{
			subStaticProp: 'subStaticPropValue'
		}
	);
	QUnit.test( 'BarSubClass prototype has subProtoProp', function( assert ) {
		assert.equal( BarSubClass.prototype.subProtoProp, 'subProtoPropValue' );
	});
	QUnit.test( 'BarSubClass prototype has parent FooSuperClass protoProp', function( assert ) {
		assert.equal( BarSubClass.prototype.protoProp, 'protoPropValue' );
	});

	bar = new BarSubClass( { instanceProp: 'instancePropValue' } );
	QUnit.test( 'BarSubClass instance bar its initialize() and parent initialize() run', function( assert ) {
		assert.equal( bar.instanceProp, 'instancePropValue' );
		assert.equal( bar.subInstanceProp, 'subInstancePropValue' );
	});

	QUnit.test( 'BarSubClass instance bar extended FooSuperClass', function( assert ) {
		assert.equal( bar.extended( FooSuperClass ), true );
	});


	// Implements todo: Test Class.constructor() manipulation.
	QUnit.module( 'Customize Base: Constructor Manipulation' );

	newConstructor = function ( instanceProps ) {
			$.extend( this , instanceProps || {} );
	};

	ConstructorTestClass = wp.customize.Class.extend(
		{
			constructor : newConstructor,
			protoProp: 'protoPropValue'
		},
		{
			staticProp: 'staticPropValue'
		}
	);

	QUnit.test( 'New constructor added to class', function( assert ) {
		assert.equal( ConstructorTestClass.prototype.constructor , newConstructor );
	});
	QUnit.test( 'Class with new constructor has protoPropValue', function( assert ) {
		assert.equal( ConstructorTestClass.prototype.protoProp , 'protoPropValue' );
	});

	constructorTest = new ConstructorTestClass( { instanceProp: 'instancePropValue' } );
		QUnit.test( 'ConstructorTestClass instance constructorTest has the new constructor', function( assert ) {
		assert.equal( constructorTest.constructor, newConstructor );
	});

	QUnit.test( 'ConstructorTestClass instance constructorTest extended Class', function( assert ) {
		assert.equal( constructorTest.extended( wp.customize.Class ), true );
	});

	QUnit.test( 'ConstructorTestClass instance constructorTest has the added instance property', function( assert ) {
		assert.equal( constructorTest.instanceProp , 'instancePropValue' );
	});


	QUnit.module( 'Customize Base: wp.customizer.ensure' );

	$mockElement = $( '<div id="mockElement"></div>' );

	QUnit.test( 'Handles jQuery argument', function( assert ) {
		assert.equal( wp.customize.ensure( $mockElement ) , $mockElement );
	});

	mockString = '<div class="mockString"></div>';

	QUnit.test( 'Handles string argument', function( assert ) {
		assert.ok( wp.customize.ensure( mockString ) instanceof jQuery );
	});


	QUnit.module( 'Customize Base: Value Class' );

	firstInitialValue = true;
	firstValueInstance = new wp.customize.Value( firstInitialValue );

	QUnit.test( 'Initialized with the right value', function( assert ) {
		assert.equal( firstValueInstance.get() , firstInitialValue );
	});

	QUnit.test( '.set() works', function( assert ) {
		firstValueInstance.set( false );
		assert.equal( firstValueInstance.get() , false );
	});

	QUnit.test( '.bind() adds new callback that fires on set()', function( assert ) {
		wasCallbackFired = false;
		mockValueCallback = function() {
			wasCallbackFired = true;
		};
		firstValueInstance.bind( mockValueCallback );
		firstValueInstance.set( 'newValue' );
		assert.ok( wasCallbackFired );
	});

	QUnit.module( 'Customize Base: Values Class' );

	valuesInstance = new wp.customize.Values();

	QUnit.test( 'Correct events are triggered when adding to or removing from Values collection', function( assert ) {
		var hasFooOnAdd = false,
			hasFooOnRemove = false,
			hasFooOnRemoved = true,
			valuePassedToAdd = false,
			valuePassedToRemove = false,
			valuePassedToRemoved = false,
			wasEventFiredOnRemoval = false,
			fooValue = new wp.customize.Value( 'foo' );

		// Test events when adding new value.
		valuesInstance.bind( 'add', function( value ) {
			hasFooOnAdd = valuesInstance.has( 'foo' );
			valuePassedToAdd = value;
		} );
		valuesInstance.add( 'foo', fooValue );
		assert.ok( hasFooOnAdd );
		assert.equal( valuePassedToAdd.get(), fooValue.get() );

		// Test events when removing the value.
		valuesInstance.bind( 'remove', function( value ) {
			hasFooOnRemove = valuesInstance.has( 'foo' );
			valuePassedToRemove = value;
			wasEventFiredOnRemoval = true;
		} );
		valuesInstance.bind( 'removed', function( value ) {
			hasFooOnRemoved = valuesInstance.has( 'foo' );
			valuePassedToRemoved = value;
			wasEventFiredOnRemoval = true;
		} );
		valuesInstance.remove( 'foo' );
		assert.ok( hasFooOnRemove );
		assert.equal( valuePassedToRemove.get(), fooValue.get() );
		assert.ok( ! hasFooOnRemoved );
		assert.equal( valuePassedToRemoved.get(), fooValue.get() );

		// Confirm no events are fired when nonexistent value is removed.
		wasEventFiredOnRemoval = false;
		valuesInstance.remove( 'bar' );
		assert.ok( ! wasEventFiredOnRemoval );
	});

	QUnit.module( 'Customize Base: Notification' );
	QUnit.test( 'Notification object exists and has expected properties', function ( assert ) {
		var notification = new wp.customize.Notification( 'mycode', {
			'message': 'Hello World',
			'type': 'update',
			'setting': 'blogname',
			'fromServer': true,
			'data': { 'foo': 'bar' }
		} );

		assert.equal( 'mycode', notification.code );
		assert.equal( 'Hello World', notification.message );
		assert.equal( 'update', notification.type );
		assert.equal( 'blogname', notification.setting );
		assert.equal( true, notification.fromServer );
		assert.deepEqual( { 'foo': 'bar' }, notification.data );

		notification = new wp.customize.Notification( 'mycode2', {
			'message': 'Hello Space'
		} );
		assert.equal( 'mycode2', notification.code );
		assert.equal( 'Hello Space', notification.message );
		assert.equal( 'error', notification.type );
		assert.equal( null, notification.data );
	} );

	QUnit.module( 'Customize Base: utils.parseQueryString' );
	QUnit.test( 'wp.customize.utils.parseQueryString works', function( assert ) {
		var queryParams;
		queryParams = wp.customize.utils.parseQueryString( 'a=1&b=2' );
		assert.ok( _.isEqual( queryParams, { a: '1', b: '2' } ) );

		queryParams = wp.customize.utils.parseQueryString( 'a+b=1&b=Hello%20World' );
		assert.ok( _.isEqual( queryParams, { 'a_b': '1', b: 'Hello World' } ) );

		queryParams = wp.customize.utils.parseQueryString( 'a%20b=1&b=Hello+World' );
		assert.ok( _.isEqual( queryParams, { 'a_b': '1', b: 'Hello World' } ) );

		queryParams = wp.customize.utils.parseQueryString( 'a=1&b' );
		assert.ok( _.isEqual( queryParams, { 'a': '1', b: null } ) );

		queryParams = wp.customize.utils.parseQueryString( 'a=1&b=' );
		assert.ok( _.isEqual( queryParams, { 'a': '1', b: '' } ) );
	} );
});
