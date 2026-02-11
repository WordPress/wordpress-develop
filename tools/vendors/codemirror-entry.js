// Import CodeMirror core to be exposed as window.wp.CodeMirror.
import CodeMirror from 'codemirror/lib/codemirror';

// Keymaps
import 'codemirror/keymap/emacs';
import 'codemirror/keymap/sublime';
import 'codemirror/keymap/vim';

// Addons (Hinting)
import 'codemirror/addon/hint/show-hint';
import 'codemirror/addon/hint/anyword-hint';
import 'codemirror/addon/hint/css-hint';
import 'codemirror/addon/hint/html-hint';
import 'codemirror/addon/hint/javascript-hint';
import 'codemirror/addon/hint/sql-hint';
import 'codemirror/addon/hint/xml-hint';

// Addons (Linting)
import 'codemirror/addon/lint/lint';
import 'codemirror/addon/lint/css-lint';
import 'codemirror/addon/lint/html-lint';

import '../../src/js/_enqueues/vendor/codemirror/javascript-lint';
import 'codemirror/addon/lint/json-lint';

// Addons (Other)
import 'codemirror/addon/comment/comment';
import 'codemirror/addon/comment/continuecomment';
import 'codemirror/addon/fold/xml-fold';
import 'codemirror/addon/mode/overlay';
import 'codemirror/addon/edit/closebrackets';
import 'codemirror/addon/edit/closetag';
import 'codemirror/addon/edit/continuelist';
import 'codemirror/addon/edit/matchbrackets';
import 'codemirror/addon/edit/matchtags';
import 'codemirror/addon/edit/trailingspace';
import 'codemirror/addon/dialog/dialog';
import 'codemirror/addon/display/autorefresh';
import 'codemirror/addon/display/fullscreen';
import 'codemirror/addon/display/panel';
import 'codemirror/addon/display/placeholder';
import 'codemirror/addon/display/rulers';
import 'codemirror/addon/fold/brace-fold';
import 'codemirror/addon/fold/comment-fold';
import 'codemirror/addon/fold/foldcode';
import 'codemirror/addon/fold/foldgutter';
import 'codemirror/addon/fold/indent-fold';
import 'codemirror/addon/fold/markdown-fold';
import 'codemirror/addon/merge/merge';
import 'codemirror/addon/mode/loadmode';
import 'codemirror/addon/mode/multiplex';
import 'codemirror/addon/mode/simple';
import 'codemirror/addon/runmode/runmode';
import 'codemirror/addon/runmode/colorize';
import 'codemirror/addon/runmode/runmode-standalone';
import 'codemirror/addon/scroll/annotatescrollbar';
import 'codemirror/addon/scroll/scrollpastend';
import 'codemirror/addon/scroll/simplescrollbars';
import 'codemirror/addon/search/search';
import 'codemirror/addon/search/jump-to-line';
import 'codemirror/addon/search/match-highlighter';
import 'codemirror/addon/search/matchesonscrollbar';
import 'codemirror/addon/search/searchcursor';
import 'codemirror/addon/tern/tern';
import 'codemirror/addon/tern/worker';
import 'codemirror/addon/wrap/hardwrap';
import 'codemirror/addon/selection/active-line';
import 'codemirror/addon/selection/mark-selection';
import 'codemirror/addon/selection/selection-pointer';

// Modes
import 'codemirror/mode/meta';
import 'codemirror/mode/clike/clike';
import 'codemirror/mode/css/css';
import 'codemirror/mode/diff/diff';
import 'codemirror/mode/htmlmixed/htmlmixed';
import 'codemirror/mode/http/http';
import 'codemirror/mode/javascript/javascript';
import 'codemirror/mode/jsx/jsx';
import 'codemirror/mode/markdown/markdown';
import 'codemirror/mode/gfm/gfm';
import 'codemirror/mode/nginx/nginx';
import 'codemirror/mode/php/php';
import 'codemirror/mode/sass/sass';
import 'codemirror/mode/shell/shell';
import 'codemirror/mode/sql/sql';
import 'codemirror/mode/xml/xml';
import 'codemirror/mode/yaml/yaml';

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
