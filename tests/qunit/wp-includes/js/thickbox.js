/* jshint evil: true */

(function (QUnit) {
	QUnit.module('thickbox');

	QUnit.test(
		'initializes when concatenated after a strict-mode script',
		function (assert) {
			var done = assert.async();

			assert.expect(1);

			jQuery
				.get('../../src/js/_enqueues/vendor/thickbox/thickbox.js')
				.done(function (source) {
					var thickboxL10n = {
						loadingAnimation: 'loading.gif',
					};
					var fakejQuery = function () {
						return {
							ready: function (callback) {
								callback();
							},
							on: function () {
								return this;
							},
						};
					};

					assert.doesNotThrow(function () {
						Function(
							'jQuery',
							'thickboxL10n',
							'document',
							'Image',
							'"use strict";\n' + source
						)(fakejQuery, thickboxL10n, {}, window.Image);
					});

					done();
				})
				.fail(function () {
					assert.ok(false, 'Could not load thickbox.js source.');
					done();
				});
		}
	);
})(window.QUnit);
