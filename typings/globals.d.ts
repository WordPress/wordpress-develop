/// <reference types="jquery" />
/// <reference types="underscore" />
/// <reference types="codemirror/addon/lint/lint" />
/// <reference types="codemirror/addon/hint/show-hint" />

interface Window {
	wp: any;
	jQuery: JQueryStatic;
	_: _.UnderscoreStatic;
	Backbone: any;
	HTMLHint: typeof import('htmlhint').HTMLHint;
}

declare var wp: any;
declare var jQuery: JQueryStatic;
declare var _: _.UnderscoreStatic;
declare var Backbone: any;
declare var HTMLHint: typeof import('htmlhint').HTMLHint;
