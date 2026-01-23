// Import CodeMirror core to be exposed as window.wp.CodeMirror.
var CodeMirror = require( 'codemirror/lib/codemirror' );

// Keymaps
require( 'codemirror/keymap/emacs' );
require( 'codemirror/keymap/sublime' );
require( 'codemirror/keymap/vim' );

// Addons (Hinting)
require( 'codemirror/addon/hint/show-hint' );
require( 'codemirror/addon/hint/anyword-hint' );
require( 'codemirror/addon/hint/css-hint' );
require( 'codemirror/addon/hint/html-hint' );
require( 'codemirror/addon/hint/javascript-hint' );
require( 'codemirror/addon/hint/sql-hint' );
require( 'codemirror/addon/hint/xml-hint' );

// Addons (Linting)
require( 'codemirror/addon/lint/lint' );
require( 'codemirror/addon/lint/css-lint' );
require( 'codemirror/addon/lint/html-lint' );
require( 'codemirror/addon/lint/javascript-lint' );
require( 'codemirror/addon/lint/json-lint' );

// Addons (Other)
require( 'codemirror/addon/comment/comment' );
require( 'codemirror/addon/comment/continuecomment' );
require( 'codemirror/addon/fold/xml-fold' );
require( 'codemirror/addon/mode/overlay' );
require( 'codemirror/addon/edit/closebrackets' );
require( 'codemirror/addon/edit/closetag' );
require( 'codemirror/addon/edit/continuelist' );
require( 'codemirror/addon/edit/matchbrackets' );
require( 'codemirror/addon/edit/matchtags' );
require( 'codemirror/addon/edit/trailingspace' );
require( 'codemirror/addon/dialog/dialog' );
require( 'codemirror/addon/display/autorefresh' );
require( 'codemirror/addon/display/fullscreen' );
require( 'codemirror/addon/display/panel' );
require( 'codemirror/addon/display/placeholder' );
require( 'codemirror/addon/display/rulers' );
require( 'codemirror/addon/fold/brace-fold' );
require( 'codemirror/addon/fold/comment-fold' );
require( 'codemirror/addon/fold/foldcode' );
require( 'codemirror/addon/fold/foldgutter' );
require( 'codemirror/addon/fold/indent-fold' );
require( 'codemirror/addon/fold/markdown-fold' );
require( 'codemirror/addon/merge/merge' );
require( 'codemirror/addon/mode/loadmode' );
require( 'codemirror/addon/mode/multiplex' );
require( 'codemirror/addon/mode/simple' );
require( 'codemirror/addon/runmode/runmode' );
require( 'codemirror/addon/runmode/colorize' );
require( 'codemirror/addon/runmode/runmode-standalone' );
require( 'codemirror/addon/scroll/annotatescrollbar' );
require( 'codemirror/addon/scroll/scrollpastend' );
require( 'codemirror/addon/scroll/simplescrollbars' );
require( 'codemirror/addon/search/search' );
require( 'codemirror/addon/search/jump-to-line' );
require( 'codemirror/addon/search/match-highlighter' );
require( 'codemirror/addon/search/matchesonscrollbar' );
require( 'codemirror/addon/search/searchcursor' );
require( 'codemirror/addon/tern/tern' );
require( 'codemirror/addon/tern/worker' );
require( 'codemirror/addon/wrap/hardwrap' );
require( 'codemirror/addon/selection/active-line' );
require( 'codemirror/addon/selection/mark-selection' );
require( 'codemirror/addon/selection/selection-pointer' );

// Modes
require( 'codemirror/mode/meta' );
require( 'codemirror/mode/clike/clike' );
require( 'codemirror/mode/css/css' );
require( 'codemirror/mode/diff/diff' );
require( 'codemirror/mode/htmlmixed/htmlmixed' );
require( 'codemirror/mode/http/http' );
require( 'codemirror/mode/javascript/javascript' );
require( 'codemirror/mode/jsx/jsx' );
require( 'codemirror/mode/markdown/markdown' );
require( 'codemirror/mode/gfm/gfm' );
require( 'codemirror/mode/nginx/nginx' );
require( 'codemirror/mode/php/php' );
require( 'codemirror/mode/sass/sass' );
require( 'codemirror/mode/shell/shell' );
require( 'codemirror/mode/sql/sql' );
require( 'codemirror/mode/xml/xml' );
require( 'codemirror/mode/yaml/yaml' );

/**
 * Please note that the codemirror-standalone "runmode" addon is setting `window.CodeMirror`
 * as "a minimal CodeMirror needed to use runMode". So this `window.CodeMirror` is _different_
 * from `window.wp.CodeMirror`. It is not known if the former is actually being used by extensions.
 *
 * @see https://github.com/codemirror/codemirror5/blob/78555dd4ac9bc691f081eec8266a01d3fbcc0d4e/src/addon/runmode/codemirror-standalone.js#L5-L24
 */
if ( ! window.wp ) {
	window.wp = {};
}
window.wp.CodeMirror = CodeMirror;
