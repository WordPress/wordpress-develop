/**
 * @output wp-includes/js/wp-deprecate-l10n-object.js
 */

/** @namespace wp */
window.wp = window.wp || {};

( function( wp ) {
	'use strict';

	var __ = wp.i18n.__,
		sprintf = wp.i18n.sprintf;

	/**
	 * Throws an error for a deprecated property.
	 *
	 * @since 5.5.1
	 *
	 * @param {string} propName    The property that was used.
	 * @param {string} version     The version of WordPress that deprecated the property.
	 * @param {string} replacement The property that should have been used.
	 */
	function deprecatedProperty( propName, version, replacement ) {
		var message;

		if ( 'undefined' !== typeof replacement ) {
			message = sprintf(
				/* translators: 1: Deprecated property name, 2: Version number, 3: Alternative property name. */
				__( '%1$s is deprecated since version %2$s! Use %3$s instead.' ),
				propName,
				version,
				replacement
			);
		} else {
			message = sprintf(
				/* translators: 1: Deprecated property name, 2: Version number. */
				__( '%1$s is deprecated since version %2$s with no alternative available.' ),
				propName,
				version
			);
		}

		window.console.warn( message );
	}

	/**
	 * Deprecate all properties on an object.
	 *
	 * @since 5.5.1
	 * @since 5.6.0 Added the `version` parameter.
	 *
	 * @param {string} name       The name of the object, i.e. commonL10n.
	 * @param {object} l10nObject The object to deprecate the properties on.
	 * @param {string} version    The version of WordPress that deprecated the property.
	 *
	 * @return {object} The object with all its properties deprecated.
	 */
	function deprecateL10nObject( name, l10nObject, version ) {
		var deprecatedObject = {};

		Object.keys( l10nObject ).forEach( function( key ) {
			var prop = l10nObject[ key ];
			var propName = name + '.' + key;

			if ( 'object' === typeof prop ) {
				Object.defineProperty( deprecatedObject, key, { get: function() {
					deprecatedProperty( propName, version, prop.alternative );
					return prop.func();
				} } );
			} else {
				Object.defineProperty( deprecatedObject, key, { get: function() {
					deprecatedProperty( propName, version, 'wp.i18n' );
					return prop;
				} } );
			}
		} );

		return deprecatedObject;
	}

	wp.deprecateL10nObject = deprecateL10nObject;

} )( window.wp );
