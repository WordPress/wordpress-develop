/* global wp, twemoji */

/*
 * The elements built here are deliberately left out of the document. wp-emoji observes the body for
 * added nodes, so attaching them would set that observer going in the middle of a test and call the
 * Twemoji stand-in again behind the assertions.
 */

const EMOJI = '😀'; // Grinning face.

/**
 * Builds a detached element containing the given text.
 *
 * @param {string} text        Text to place inside the element.
 * @param {string} [className] Class attribute for the element.
 *
 * @return {HTMLElement} The element.
 */
function emojiFixtureElement( text, className ) {
	const element = document.createElement( 'div' );

	if ( className ) {
		element.setAttribute( 'class', className );
	}

	element.appendChild( document.createTextNode( text ) );

	return element;
}

/**
 * Parses a fixture element and returns the params wp-emoji handed to Twemoji.
 *
 * @param {Object} [args] Additional options for wp.emoji.parse().
 *
 * @return {Object} The params Twemoji was called with.
 */
function paramsFromParse( args ) {
	wp.emoji.parse( emojiFixtureElement( EMOJI ), args );

	return twemoji.lastParams;
}

QUnit.module( 'wp.emoji.test' );

QUnit.test( 'recognizes a surrogate pair emoji', function ( assert ) {
	assert.strictEqual( wp.emoji.test( EMOJI ), true, 'A grinning face is an emoji.' );
} );

QUnit.test( 'recognizes an emoji within surrounding text', function ( assert ) {
	assert.strictEqual( wp.emoji.test( 'before ' + EMOJI + ' after' ), true, 'An emoji is found among other text.' );
} );

QUnit.test( 'recognizes a single code point emoji', function ( assert ) {
	assert.strictEqual( wp.emoji.test( '❤' ), true, 'A heavy black heart is an emoji.' );
} );

QUnit.test( 'does not recognize plain text', function ( assert ) {
	assert.strictEqual( wp.emoji.test( 'Hello world' ), false, 'Plain text contains no emoji.' );
} );

QUnit.test( 'does not recognize a copyright sign', function ( assert ) {
	assert.strictEqual( wp.emoji.test( '©' ), false, 'The copyright sign is excluded from the test.' );
} );

QUnit.test( 'returns false for values which are not text', function ( assert ) {
	assert.strictEqual( wp.emoji.test( '' ), false, 'An empty string contains no emoji.' );
	assert.strictEqual( wp.emoji.test( null ), false, 'Null contains no emoji.' );
	assert.strictEqual( wp.emoji.test( undefined ), false, 'Undefined contains no emoji.' );
} );

QUnit.module( 'wp.emoji.parse', {
	beforeEach: function () {
		twemoji.calls = 0;
		twemoji.lastObject = null;
		twemoji.lastParams = null;
		window._wpemojiSettings.supports = {
			everything: false,
			everythingExceptFlag: false,
			flag: false,
			emoji: false
		};
	}
} );

QUnit.test( 'does nothing when the browser supports every emoji', function ( assert ) {
	window._wpemojiSettings.supports.everything = true;

	const element = emojiFixtureElement( EMOJI );

	assert.strictEqual( wp.emoji.parse( element ), element, 'The element is returned as it was.' );
	assert.strictEqual( twemoji.calls, 0, 'Twemoji is not called.' );
} );

QUnit.test( 'does nothing when given an element with no child nodes', function ( assert ) {
	const element = document.createElement( 'div' );

	assert.strictEqual( wp.emoji.parse( element ), element, 'The element is returned as it was.' );
	assert.strictEqual( twemoji.calls, 0, 'Twemoji is not called.' );
} );

QUnit.test( 'does nothing when given nothing to parse', function ( assert ) {
	assert.strictEqual( wp.emoji.parse( null ), null, 'Null is returned as it was.' );
	assert.strictEqual( twemoji.calls, 0, 'Twemoji is not called.' );
} );

QUnit.test( 'passes a string on to Twemoji', function ( assert ) {
	wp.emoji.parse( EMOJI );

	assert.strictEqual( twemoji.calls, 1, 'Twemoji is called once.' );
	assert.strictEqual( twemoji.lastObject, EMOJI, 'Twemoji is given the string.' );
} );

QUnit.test( 'uses the SVG images', function ( assert ) {
	const params = paramsFromParse();

	assert.strictEqual( params.base, window._wpemojiSettings.svgUrl, 'The SVG URL is used as the base.' );
	assert.strictEqual( params.ext, window._wpemojiSettings.svgExt, 'The SVG extension is used.' );
} );

QUnit.test( 'gives each image the emoji class by default', function ( assert ) {
	assert.strictEqual( paramsFromParse().className, 'emoji', 'The class name defaults to emoji.' );
} );

QUnit.test( 'allows the class name to be replaced', function ( assert ) {
	assert.strictEqual( paramsFromParse( { className: 'custom' } ).className, 'custom', 'The given class name is used.' );
} );

QUnit.test( 'marks each image as an image for assistive technology', function ( assert ) {
	assert.deepEqual( paramsFromParse().attributes(), { role: 'img' }, 'The role attribute is set.' );
} );

QUnit.test( 'allows the attributes to be replaced', function ( assert ) {
	const imgAttr = { 'aria-hidden': 'true' };

	assert.deepEqual( paramsFromParse( { imgAttr: imgAttr } ).attributes(), imgAttr, 'The given attributes are used.' );
} );

QUnit.test( 'ignores attributes which are not an object', function ( assert ) {
	assert.deepEqual( paramsFromParse( { imgAttr: 'not an object' } ).attributes(), { role: 'img' }, 'The default attributes are used.' );
} );

QUnit.module( 'wp.emoji.parse callback', {
	beforeEach: function () {
		twemoji.calls = 0;
		twemoji.lastParams = null;
		window._wpemojiSettings.supports = {
			everything: false,
			everythingExceptFlag: false,
			flag: false,
			emoji: false
		};
	}
} );

QUnit.test( 'builds the image source from the base and extension', function ( assert ) {
	const params = paramsFromParse();

	assert.strictEqual(
		params.callback( '1f600', params ),
		window._wpemojiSettings.svgUrl + '1f600' + window._wpemojiSettings.svgExt,
		'The source is the base, the icon and the extension.'
	);
} );

QUnit.test( 'leaves the characters TinyMCE offers in its character map alone', function ( assert ) {
	const params = paramsFromParse();

	[ 'a9', 'ae', '2122', '2194', '2660', '2663', '2665', '2666' ].forEach( function ( icon ) {
		assert.strictEqual( params.callback( icon, params ), false, icon + ' is left as it is.' );
	} );
} );

QUnit.test( 'replaces only flags when everything but flags is supported', function ( assert ) {
	window._wpemojiSettings.supports.everythingExceptFlag = true;

	const params = paramsFromParse();

	assert.strictEqual( params.callback( '1f600', params ), false, 'A grinning face is left as it is.' );
	assert.strictEqual(
		params.callback( '1f1e8-1f1f6', params ),
		window._wpemojiSettings.svgUrl + '1f1e8-1f1f6' + window._wpemojiSettings.svgExt,
		'A country flag is replaced.'
	);
	assert.strictEqual(
		params.callback( '1f3f3-fe0f-200d-1f308', params ),
		window._wpemojiSettings.svgUrl + '1f3f3-fe0f-200d-1f308' + window._wpemojiSettings.svgExt,
		'The rainbow flag is replaced.'
	);
} );

QUnit.module( 'wp.emoji.parse doNotParse', {
	beforeEach: function () {
		window._wpemojiSettings.supports = {
			everything: false,
			everythingExceptFlag: false,
			flag: false,
			emoji: false
		};
	}
} );

QUnit.test( 'excludes an element carrying the exclusion class', function ( assert ) {
	const doNotParse = paramsFromParse().doNotParse;

	assert.strictEqual( doNotParse( emojiFixtureElement( '', 'wp-exclude-emoji' ) ), true, 'The class on its own excludes.' );
	assert.strictEqual( doNotParse( emojiFixtureElement( '', 'one wp-exclude-emoji two' ) ), true, 'The class among others excludes.' );
} );

QUnit.test( 'does not treat a longer class name as the exclusion class', function ( assert ) {
	const doNotParse = paramsFromParse().doNotParse;

	assert.strictEqual( doNotParse( emojiFixtureElement( '', 'wp-exclude-emoji-wrapper' ) ), false, 'A class which begins with it does not exclude.' );
	assert.strictEqual( doNotParse( emojiFixtureElement( '', 'my-wp-exclude-emoji' ) ), false, 'A class which ends with it does not exclude.' );
	assert.strictEqual( doNotParse( emojiFixtureElement( '', 'wp-exclude-emojis' ) ), false, 'A plural of it does not exclude.' );
} );

QUnit.test( 'does not exclude an unrelated element', function ( assert ) {
	const doNotParse = paramsFromParse().doNotParse;

	assert.strictEqual( doNotParse( emojiFixtureElement( '', 'unrelated' ) ), false, 'Another class does not exclude.' );
	assert.strictEqual( doNotParse( emojiFixtureElement( '' ) ), false, 'No class at all does not exclude.' );
} );

QUnit.module( 'wp.emoji.parse onerror', {
	beforeEach: function () {
		window._wpemojiSettings.supports = {
			everything: false,
			everythingExceptFlag: false,
			flag: false,
			emoji: false
		};
	}
} );

QUnit.test( 'puts the emoji character back when the image cannot be loaded', function ( assert ) {
	const parent = document.createElement( 'p' );
	const image = document.createElement( 'img' );

	image.alt = EMOJI;
	parent.appendChild( image );

	paramsFromParse().onerror.call( image );

	assert.strictEqual( parent.textContent, EMOJI, 'The parent holds the emoji character.' );
	assert.strictEqual( parent.contains( image ), false, 'The image is gone.' );
} );

QUnit.test( 'marks the image it removed, so that it is not put back', function ( assert ) {
	const parent = document.createElement( 'p' );
	const image = document.createElement( 'img' );

	image.alt = EMOJI;
	parent.appendChild( image );

	paramsFromParse().onerror.call( image );

	assert.strictEqual( image.dataset.error, 'load-failed', 'The image carries the marker the observer looks for.' );
} );

QUnit.test( 'does nothing to an image which is not in the document', function ( assert ) {
	const image = document.createElement( 'img' );

	image.alt = EMOJI;

	paramsFromParse().onerror.call( image );

	assert.strictEqual( image.dataset.error, undefined, 'The image is left unmarked.' );
} );

/*
 * Unlike everything above, these do attach their elements, because what is under test is the
 * observer wp-emoji sets on the body.
 */
QUnit.module( 'wp-emoji mutation observer', {
	beforeEach: function () {
		window._wpemojiSettings.supports = {
			everything: false,
			everythingExceptFlag: false,
			flag: false,
			emoji: false
		};
	}
} );

/**
 * Waits for pending mutation records to be delivered to the observer.
 *
 * Records reach an observer in a microtask, so settling on one puts this behind the observer's own
 * callback. A timer cannot be used to wait: every test here is wrapped by sinon-test, which swaps
 * the timers for a fake clock that nothing in this file advances.
 *
 * @return {Promise} A promise which settles once the observer has run.
 */
function afterMutations() {
	return Promise.resolve();
}

/**
 * Adds an element to the fixture, and returns once the observer has seen it.
 *
 * @param {HTMLElement} element Element to add.
 *
 * @return {Promise} A promise which settles once the observer has run.
 */
function attachToFixture( element ) {
	document.getElementById( 'qunit-fixture' ).appendChild( element );

	return afterMutations();
}

/**
 * Adds an element of the given kind, holding an emoji, to the fixture.
 *
 * @param {string} namespace Namespace URI for the element.
 * @param {string} name      Local name for the element.
 *
 * @return {Promise} A promise which settles once the observer has run.
 */
function attachNamespacedElement( namespace, name ) {
	const element = document.createElementNS( namespace, name );

	element.appendChild( document.createTextNode( EMOJI ) );

	return attachToFixture( element );
}

/**
 * Builds a paragraph holding an emoji image, as Twemoji would have left it.
 *
 * @param {string} [error] Value for the data-error attribute.
 *
 * @return {{ paragraph: HTMLParagraphElement, image: HTMLImageElement }} The paragraph and the image within it.
 */
function emojiImageParagraph( error ) {
	const paragraph = document.createElement( 'p' );
	const image = document.createElement( 'img' );

	image.alt = EMOJI;

	if ( error ) {
		image.dataset.error = error;
	}

	paragraph.appendChild( image );

	return { paragraph, image };
}

QUnit.test( 'parses an element added to the document', async function ( assert ) {
	const element = emojiFixtureElement( EMOJI );

	twemoji.calls = 0;
	twemoji.lastObject = null;

	await attachToFixture( element );

	assert.strictEqual( twemoji.calls, 1, 'Twemoji is called once.' );
	assert.strictEqual( twemoji.lastObject, element, 'Twemoji is given the added element.' );
} );

QUnit.test( 'parses the containing element of an added text node', async function ( assert ) {
	const paragraph = document.createElement( 'p' );

	await attachToFixture( paragraph );

	twemoji.calls = 0;
	twemoji.lastObject = null;

	paragraph.appendChild( document.createTextNode( EMOJI ) );
	await afterMutations();

	assert.strictEqual( twemoji.calls, 1, 'Twemoji is called once.' );
	assert.strictEqual( twemoji.lastObject, paragraph, 'Twemoji is given the containing element.' );
} );

QUnit.test( 'leaves alone an image replaced by its own alternative text', async function ( assert ) {
	const nodes = emojiImageParagraph( 'load-failed' );

	await attachToFixture( nodes.paragraph );

	twemoji.calls = 0;

	nodes.paragraph.replaceChild( document.createTextNode( EMOJI ), nodes.image );
	await afterMutations();

	assert.strictEqual( twemoji.calls, 0, 'The text which replaced the image is not parsed back into one.' );
} );

QUnit.test( 'leaves an SVG element alone', async function ( assert ) {
	twemoji.calls = 0;

	await attachNamespacedElement( 'http://www.w3.org/2000/svg', 'svg' );

	assert.strictEqual( twemoji.calls, 0, 'An SVG element is not given to Twemoji, which would put an image inside it.' );
} );

QUnit.test( 'leaves a MathML element alone', async function ( assert ) {
	twemoji.calls = 0;

	await attachNamespacedElement( 'http://www.w3.org/1998/Math/MathML', 'math' );

	assert.strictEqual( twemoji.calls, 0, 'A MathML element is not given to Twemoji.' );
} );

QUnit.test( 'leaves alone a text node added within an SVG element', async function ( assert ) {
	const svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );

	await attachToFixture( svg );

	twemoji.calls = 0;

	svg.appendChild( document.createTextNode( EMOJI ) );
	await afterMutations();

	assert.strictEqual( twemoji.calls, 0, 'The SVG element containing the text is not parsed either.' );
} );

QUnit.test( 'parses other additions delivered alongside a fallback', async function ( assert ) {
	const nodes = emojiImageParagraph( 'load-failed' );

	await attachToFixture( nodes.paragraph );

	const other = emojiFixtureElement( EMOJI );

	twemoji.calls = 0;
	twemoji.lastObject = null;

	/*
	 * Both changes are made before waiting, so that the observer is given the two records in one
	 * call. The fallback comes first: recognizing it must not stop the rest of the records being
	 * looked at, which is the difference between skipping that record and abandoning the callback.
	 */
	nodes.paragraph.replaceChild( document.createTextNode( EMOJI ), nodes.image );
	document.getElementById( 'qunit-fixture' ).appendChild( other );

	await afterMutations();

	assert.strictEqual( twemoji.calls, 1, 'Twemoji is called once.' );
	assert.strictEqual( twemoji.lastObject, other, 'The element added alongside the fallback is still parsed.' );
} );

QUnit.test( 'parses the same replacement when the image was not marked', async function ( assert ) {
	const nodes = emojiImageParagraph();

	await attachToFixture( nodes.paragraph );

	twemoji.calls = 0;
	twemoji.lastObject = null;

	nodes.paragraph.replaceChild( document.createTextNode( EMOJI ), nodes.image );
	await afterMutations();

	assert.strictEqual( twemoji.calls, 1, 'Twemoji is called once.' );
	assert.strictEqual( twemoji.lastObject, nodes.paragraph, 'Without the marker, the element containing the replacement is parsed.' );
} );
