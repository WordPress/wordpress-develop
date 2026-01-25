// JSHINT has some GPL Compatability issues, so we are faking it out and using espree for validation
// Based on https://github.com/jquery/esprima/blob/gh-pages/demo/validate.js which is MIT licensed

var fakeJSHINT = function() {
	var syntax;
	var that = this;
	this.data = [];
	this.convertError = function( error ){
		return {
			line: error.lineNumber,
			character: error.column,
			reason: error.description,
			code: 'E'
		};
	};
	this.parse = function( code ){
		try {
			syntax = window.espree.parse(code, {
				ecmaVersion: 'latest',
				loc: true,
				sourceType: 'module'
			});
			that.data = [];
		} catch (e) {
			that.data.push( that.convertError( e ) );
		}
	};
};

window.JSHINT = function( text ){
	fakeJSHINT.parse( text );
};
window.JSHINT.data = function(){
	return {
		errors: fakeJSHINT.data
	};
};


