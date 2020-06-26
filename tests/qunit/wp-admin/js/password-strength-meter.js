/* global passwordStrength, wp, jQuery */
jQuery( function() {
	QUnit.module( 'password-strength-meter' );

	QUnit.test( 'mismatched passwords should return 5', function( assert ) {
		assert.equal( passwordStrength( 'password1', 'username', 'password2' ), 5, 'mismatched passwords return 5' );
	});

	QUnit.test( 'passwords shorter than 4 characters should return 0', function( assert ) {
		assert.equal( passwordStrength( 'abc', 'username', 'abc' ), 0, 'short passwords return 0' );
	});

	QUnit.test( 'long complicated passwords should return 4', function( assert ) {
		var password = function( length ) {
			var i, n, retVal = '',
				possibility = 'abcdefghijklnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			for ( i = 0, n = possibility.length; i < length; i++ ) {
				retVal += possibility.charAt( Math.floor( Math.random() * n ) );
			}
			return retVal + 'aB2'; // Add a lower case, uppercase and number just to make sure we always have one of each.
		},
		twofifty = password( 250 );

		assert.equal( passwordStrength( twofifty, 'username', twofifty ), 4, '250 character complicated password returns 4' );
	});

	QUnit.test( 'short uncomplicated passwords should return 0', function( assert ) {
		var letters = 'aaaa',
			numbers = '1111',
			password = 'password',
			uppercase = 'AAAA';
		assert.equal( passwordStrength( letters, 'username', letters ), 0, 'password of `' + letters + '` returns 0' );
		assert.equal( passwordStrength( numbers, 'username', numbers ), 0, 'password of `' + numbers + '` returns 0' );
		assert.equal( passwordStrength( uppercase, 'username', uppercase ), 0, 'password of `' + uppercase + '` returns 0' );
		assert.equal( passwordStrength( password, 'username', password ), 0, 'password of `' + password + '` returns 0' );
	});

	QUnit.test( 'zxcvbn password tests should return the score we expect', function( assert ) {
		var passwords, i;
		passwords = [
			{ pw: 'zxcvbn', score: 0 },
			{ pw: 'qwER43@!', score: 2 },
			{ pw: 'Tr0ub4dour&3', score: 2 },
			{ pw: 'correcthorsebatterystaple', score: 4 },
			{ pw: 'coRrecth0rseba++ery9.23.2007staple$', score: 4 },
			{ pw: 'D0g..................', score: 1 },
			{ pw: 'abcdefghijk987654321', score: 1 },
			{ pw: 'neverforget13/3/1997', score: 3 },
			{ pw: '1qaz2wsx3edc', score: 0 },
			{ pw: 'temppass22', score: 1 },
			{ pw: 'briansmith', score: 1 },
			{ pw: 'briansmith4mayor', score: 4 },
			{ pw: 'password1', score: 0 },
			{ pw: 'viking', score: 0 },
			{ pw: 'thx1138', score: 0 },
			{ pw: 'ScoRpi0ns', score: 1 },
			{ pw: 'do you know', score: 3 },
			{ pw: 'ryanhunter2000', score: 3 },
			{ pw: 'rianhunter2000', score: 3 },
			{ pw: 'asdfghju7654rewq', score: 3 },
			{ pw: 'AOEUIDHG&*()LS_', score: 3 },
			{ pw: '12345678', score: 0 },
			{ pw: 'defghi6789', score: 1 },
			{ pw: 'rosebud', score: 0 },
			{ pw: 'Rosebud', score: 0 },
			{ pw: 'ROSEBUD', score: 0 },
			{ pw: 'rosebuD', score: 0 },
			{ pw: 'ros3bud99', score: 1 },
			{ pw: 'r0s3bud99', score: 1 },
			{ pw: 'R0$38uD99', score: 2 },
			{ pw: 'verlineVANDERMARK', score: 4 },
			{ pw: 'eheuczkqyq', score: 3 },
			{ pw: 'rWibMFACxAUGZmxhVncy', score: 4 },
			{ pw: 'Ba9ZyWABu99[BK#6MBgbH88Tofv)vs$w', score: 4 },
			{ pw: 'foo foo foo foo', score: 2 }
		];

		for ( i = 0; i < passwords.length; i++ ) {
			assert.equal( passwordStrength( passwords[i].pw, 'username', passwords[i].pw ), passwords[i].score, 'password of `' + passwords[i].pw + '` returns ' + passwords[i].score );
		}
	});

	QUnit.test( 'disallowed words in password should be penalized', function( assert ) {
		var allowedPasswordScore, penalizedPasswordScore,
			allowedPassword   = 'a[janedoefoe]4',
			penalizedPassword = 'a[johndoefoe]4',
			disallowedList    = [ 'extra', 'johndoefoe', 'superfluous' ];

		allowedPasswordScore = passwordStrength( allowedPassword, disallowedList, allowedPassword );
		penalizedPasswordScore = passwordStrength( penalizedPassword, disallowedList, penalizedPassword );

		assert.ok( penalizedPasswordScore < allowedPasswordScore, 'Penalized password scored ' + penalizedPasswordScore + '; allowed password scored: ' + allowedPasswordScore );
	});

	QUnit.test( 'user input disallowed list array should contain expected words', function( assert ) {
		var disallowedList = wp.passwordStrength.userInputDisallowedList();

		assert.ok( jQuery.isArray( disallowedList ), 'disallowed list is an array' );
		assert.ok( jQuery.inArray( 'WordPress', disallowedList ) > -1, 'disallowed list contains "WordPress" from page title' );
		assert.ok( jQuery.inArray( 'tests', disallowedList ) > -1, 'disallowed list contains "tests" from site URL' );
	});
});
