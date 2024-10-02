/**
 * Mock the deprecateL10nObject() for tests.
 *
 * deprecateL10nObject() is part of wp-admin/js/common.js which requires
 * some HTML markup to exist. Instead of adding all the markup this adds
 * a noop version of deprecateL10nObject(). This makes it possible
 * to test wp-admin/js/dashboard.js.
 */
window.wp = window.wp || {};
window.wp.deprecateL10nObject = function () {};
