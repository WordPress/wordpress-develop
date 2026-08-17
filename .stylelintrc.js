/** @type {import('stylelint').Config} */
module.exports = {
	extends: '@wordpress/stylelint-config/scss-stylistic',
	plugins: [
		'stylelint-plugin-logical-css',
		'@wordpress/theme/stylelint-plugins/no-token-fallback-values',
	],
	reportNeedlessDisables: true,
	rules: {
		'at-rule-empty-line-before': [
			'always',
			{
				except: [ 'blockless-after-blockless' ],
				ignore: [ 'first-nested', 'after-comment' ],
			},
		],
		'at-rule-no-unknown': null,
		'comment-empty-line-before': null,
		'declaration-property-unit-allowed-list': [
			{
				'line-height': [],
			},
		],
		'declaration-property-value-allowed-list': [
			{
				'flex-direction': '/^(?!(row|column)-reverse).*$/',
			},
			{
				message: ( property, value ) =>
					`Avoid "${ value }" value for the "${ property }" property. For accessibility reasons, visual, reading, and DOM order must match. Only use the reverse values when they do not affect reading order, meaning, and interaction.`,
			},
		],
		'font-family-no-missing-generic-family-keyword': [
			true,
			{
				ignoreFontFamilies: [ 'dashicons' ],
			},
		],
		'font-weight-notation': 'numeric',
		'function-disallowed-list': [ 'rgb' ],
		'no-descending-specificity': null,
		'no-invalid-position-at-import-rule': null,
		'property-disallowed-list': [
			[ 'order' ],
			{
				message:
					'Avoid the order property. For accessibility reasons, visual, reading, and DOM order must match. Only use the order property when it does not affect reading order, meaning, and interaction.',
			},
		],
		'rule-empty-line-before': [
			'always',
			{
				except: [ 'first-nested' ],
				ignore: [ 'after-comment' ],
			},
		],
		'selector-class-pattern': [
			'^[a-z][a-z0-9]*(?:(?:__|--|-)[a-z0-9]+)*$',
			{
				message:
					'Class selector should use lowercase class segments separated with hyphens, double hyphens, or double underscores',
			},
		],
		'selector-id-pattern': [
			'^([a-z][a-z0-9]*)(-[a-z0-9]+)*$',
			{
				message:
					'ID selector should use lowercase and separate words with hyphens',
			},
		],
		'selector-no-qualifying-type': [
			true,
			{
				severity: 'warning',
			},
		],
		'value-keyword-case': [
			'lower',
			{
				ignoreKeywords: [
					'currentColor',
					'optimizeLegibility'
				],
			},
		],
		'scss/at-else-empty-line-before': null,
		'scss/at-extend-no-missing-placeholder': null,
		'scss/at-if-closing-brace-newline-after': null,
		'scss/at-if-closing-brace-space-after': null,
		'scss/comment-no-empty': null,
		'scss/load-partial-extension': null,
		'scss/no-global-function-names': null,
		'scss/operator-no-newline-after': null,
		'scss/operator-no-unspaced': null,
		'scss/selector-no-redundant-nesting-selector': null,
		'@stylistic/max-empty-lines': 1,
		'@stylistic/max-line-length': null,
		'@stylistic/selector-descendant-combinator-no-non-space': true,
		// Keep these lines. Don't change their order otherwise indentation will break.
		// It's not about a literal 'execution order', but rather a compatibility issue.
		'@stylistic/block-closing-brace-newline-before': 'always',
		'@stylistic/block-opening-brace-newline-after': 'always',
		'@stylistic/declaration-block-semicolon-newline-after': 'always',
		'@stylistic/selector-list-comma-space-after': 'always-single-line',
		'@stylistic/selector-list-comma-newline-after': 'always',
		'@stylistic/declaration-colon-newline-after': 'always-multi-line',
		'@stylistic/indentation': 'tab',
		// End keep these lines.
	},
	reportDescriptionlessDisables: true,
	ignorePath: '.stylelintignore',
};


