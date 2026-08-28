/* global wp */

jQuery( function( $ ) {
	var FooSuperClass, BarSubClass, foo, bar, ConstructorTestClass, newConstructor, constructorTest, $mockElement, mockString,
	ApplicatorTestClass, ArityTestClass,
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

	ApplicatorTestClass = wp.customize.Class.extend({
		initialize: function( firstArg, secondArg ) {
			this.firstArg = firstArg;
			this.secondArg = secondArg;

			// The instance is extended before initialize() runs.
			this.hadExtraPropDuringInitialize = ( 'extraPropValue' === this.extraProp );
		}
	});
	QUnit.test( 'Class.applicator supplies the array as the initialize() arguments', function( assert ) {
		var applicatorTest = new ApplicatorTestClass(
			wp.customize.Class.applicator,
			[ 'firstArgValue', 'secondArgValue' ]
		);
		assert.equal( applicatorTest.firstArg, 'firstArgValue' );
		assert.equal( applicatorTest.secondArg, 'secondArgValue' );
	});
	QUnit.test( 'Class.applicator extends the instance before initialize() runs', function( assert ) {
		var applicatorTest = new ApplicatorTestClass(
			wp.customize.Class.applicator,
			[ 'firstArgValue' ],
			{ extraProp: 'extraPropValue' }
		);
		assert.equal( applicatorTest.firstArg, 'firstArgValue' );
		assert.equal( applicatorTest.extraProp, 'extraPropValue' );
		assert.ok( applicatorTest.hadExtraPropDuringInitialize );
	});

	ArityTestClass = wp.customize.Class.extend({
		initialize: function( ...initializeArgs ) {
			this.initializeArgCount = initializeArgs.length;
		}
	});
	QUnit.test( 'Class supplies initialize() with exactly the arguments it was given', function( assert ) {
		assert.equal( ( new ArityTestClass() ).initializeArgCount, 0 );
		assert.equal( ( new ArityTestClass( 'a' ) ).initializeArgCount, 1 );
		assert.equal( ( new ArityTestClass( 'a', 'b' ) ).initializeArgCount, 2 );
		assert.equal( ( new ArityTestClass( 'a', 'b', 'c' ) ).initializeArgCount, 3 );
		assert.equal( ( new ArityTestClass( 'a', 'b', 'c', 'd' ) ).initializeArgCount, 4 );

		// The applicator form supplies the arguments as an array instead.
		assert.equal(
			( new ArityTestClass( wp.customize.Class.applicator, [ 'a', 'b' ] ) ).initializeArgCount,
			2
		);
	});

	QUnit.test( 'Class instance with an instance() method is callable as a function', function( assert ) {
		var instanceTest = new wp.customize.Value( 'initialValue' );

		// Calling with no arguments delegates to get().
		assert.equal( instanceTest(), 'initialValue' );

		// Calling with arguments delegates to set().
		instanceTest( 'updatedValue' );
		assert.equal( instanceTest.get(), 'updatedValue' );
		assert.equal( instanceTest(), 'updatedValue' );
	});

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

	QUnit.test( '.bind() and .unbind() accept multiple callbacks', function( assert ) {
		var value = new wp.customize.Value( 'firstValue' ),
			firstCallbackCount = 0,
			secondCallbackCount = 0,
			firstCallback = function() {
				firstCallbackCount++;
			},
			secondCallback = function() {
				secondCallbackCount++;
			};

		value.bind( firstCallback, secondCallback );
		value.set( 'secondValue' );
		assert.equal( firstCallbackCount, 1 );
		assert.equal( secondCallbackCount, 1 );

		value.unbind( firstCallback, secondCallback );
		value.set( 'thirdValue' );
		assert.equal( firstCallbackCount, 1, 'First callback no longer fires once unbound.' );
		assert.equal( secondCallbackCount, 1, 'Second callback no longer fires once unbound.' );
	});

	QUnit.test( '.link() follows multiple values, and .unlink() stops following them', function( assert ) {
		var follower = new wp.customize.Value( 'followerValue' ),
			firstLeader = new wp.customize.Value( 'firstLeaderValue' ),
			secondLeader = new wp.customize.Value( 'secondLeaderValue' );

		follower.link( firstLeader, secondLeader );

		firstLeader.set( 'firstLeaderUpdated' );
		assert.equal( follower.get(), 'firstLeaderUpdated' );

		secondLeader.set( 'secondLeaderUpdated' );
		assert.equal( follower.get(), 'secondLeaderUpdated' );

		// Linking is one-directional, so the leaders do not follow the follower.
		follower.set( 'followerUpdated' );
		assert.equal( firstLeader.get(), 'firstLeaderUpdated' );
		assert.equal( secondLeader.get(), 'secondLeaderUpdated' );

		follower.unlink( firstLeader, secondLeader );
		firstLeader.set( 'firstLeaderUpdatedAgain' );
		secondLeader.set( 'secondLeaderUpdatedAgain' );
		assert.equal( follower.get(), 'followerUpdated', 'Value stops following once unlinked.' );
	});

	QUnit.test( '.sync() keeps values updated in both directions, and .unsync() stops them', function( assert ) {
		var first = new wp.customize.Value( 'firstValue' ),
			second = new wp.customize.Value( 'secondValue' );

		first.sync( second );

		second.set( 'setOnSecond' );
		assert.equal( first.get(), 'setOnSecond' );

		first.set( 'setOnFirst' );
		assert.equal( second.get(), 'setOnFirst' );

		first.unsync( second );
		second.set( 'setOnSecondAgain' );
		assert.equal( first.get(), 'setOnFirst', 'Values stop tracking each other once unsynced.' );
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

	QUnit.test( '.create() passes the extra arguments to the new value\'s initialize()', function( assert ) {
		var collection = new wp.customize.Values();

		collection.create( 'createdValue', 'suppliedInitialValue', { extraProp: 'extraPropValue' } );

		assert.ok( collection.has( 'createdValue' ) );
		assert.equal( collection( 'createdValue' ).get(), 'suppliedInitialValue' );
		assert.equal( collection( 'createdValue' ).extraProp, 'extraPropValue' );
	});

	QUnit.test( '.when() invokes the callback once every requested value exists', function( assert ) {
		var collection = new wp.customize.Values(),
			passedValues = null;

		collection.add( 'existingValue', new wp.customize.Value( 'existingValueContents' ) );

		collection.when( 'existingValue', 'pendingValue', function( existingValue, pendingValue ) {
			passedValues = [ existingValue.get(), pendingValue.get() ];
		} );

		assert.equal( passedValues, null, 'Callback waits for the value that does not exist yet.' );

		collection.add( 'pendingValue', new wp.customize.Value( 'pendingValueContents' ) );

		/*
		 * The promise returned by when() resolves by way of a timer, and this
		 * suite wraps every test with sinon's fake timers, so the clock has to
		 * be advanced before the callback runs.
		 */
		this.clock.tick( 10 );

		assert.deepEqual(
			passedValues,
			[ 'existingValueContents', 'pendingValueContents' ],
			'Callback is invoked with every requested value.'
		);
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
