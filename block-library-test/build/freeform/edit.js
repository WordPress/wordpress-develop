"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = FreeformEdit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _keycodes = require("@wordpress/keycodes");
var _convertToBlocksButton = _interopRequireDefault(require("./convert-to-blocks-button"));
var _modal = _interopRequireDefault(require("./modal"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const {
  wp
} = window;
function isTmceEmpty(editor) {
  // When tinyMce is empty the content seems to be:
  // <p><br data-mce-bogus="1"></p>
  // avoid expensive checks for large documents
  const body = editor.getBody();
  if (body.childNodes.length > 1) {
    return false;
  } else if (body.childNodes.length === 0) {
    return true;
  }
  if (body.childNodes[0].childNodes.length > 1) {
    return false;
  }
  return /^\n?$/.test(body.innerText || body.textContent);
}
function FreeformEdit(props) {
  const {
    clientId
  } = props;
  const canRemove = (0, _data.useSelect)(select => select(_blockEditor.store).canRemoveBlock(clientId), [clientId]);
  const [isIframed, setIsIframed] = (0, _element.useState)(false);
  const ref = (0, _compose.useRefEffect)(element => {
    setIsIframed(element.ownerDocument !== document);
  }, []);
  return (0, _react.createElement)(_react.Fragment, null, canRemove && (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_convertToBlocksButton.default, {
    clientId: clientId
  }))), (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)({
      ref
    })
  }, isIframed ? (0, _react.createElement)(_modal.default, {
    ...props
  }) : (0, _react.createElement)(ClassicEdit, {
    ...props
  })));
}
function ClassicEdit({
  clientId,
  attributes: {
    content
  },
  setAttributes,
  onReplace
}) {
  const {
    getMultiSelectedBlockClientIds
  } = (0, _data.useSelect)(_blockEditor.store);
  const didMount = (0, _element.useRef)(false);
  (0, _element.useEffect)(() => {
    if (!didMount.current) {
      return;
    }
    const editor = window.tinymce.get(`editor-${clientId}`);
    const currentContent = editor?.getContent();
    if (currentContent !== content) {
      editor.setContent(content || '');
    }
  }, [content]);
  (0, _element.useEffect)(() => {
    const {
      baseURL,
      suffix
    } = window.wpEditorL10n.tinymce;
    didMount.current = true;
    window.tinymce.EditorManager.overrideDefaults({
      base_url: baseURL,
      suffix
    });
    function onSetup(editor) {
      let bookmark;
      if (content) {
        editor.on('loadContent', () => editor.setContent(content));
      }
      editor.on('blur', () => {
        bookmark = editor.selection.getBookmark(2, true);
        // There is an issue with Chrome and the editor.focus call in core at https://core.trac.wordpress.org/browser/trunk/src/js/_enqueues/lib/link.js#L451.
        // This causes a scroll to the top of editor content on return from some content updating dialogs so tracking
        // scroll position until this is fixed in core.
        const scrollContainer = document.querySelector('.interface-interface-skeleton__content');
        const scrollPosition = scrollContainer.scrollTop;

        // Only update attributes if we aren't multi-selecting blocks.
        // Updating during multi-selection can overwrite attributes of other blocks.
        if (!getMultiSelectedBlockClientIds()?.length) {
          setAttributes({
            content: editor.getContent()
          });
        }
        editor.once('focus', () => {
          if (bookmark) {
            editor.selection.moveToBookmark(bookmark);
            if (scrollContainer.scrollTop !== scrollPosition) {
              scrollContainer.scrollTop = scrollPosition;
            }
          }
        });
        return false;
      });
      editor.on('mousedown touchstart', () => {
        bookmark = null;
      });
      const debouncedOnChange = (0, _compose.debounce)(() => {
        const value = editor.getContent();
        if (value !== editor._lastChange) {
          editor._lastChange = value;
          setAttributes({
            content: value
          });
        }
      }, 250);
      editor.on('Paste Change input Undo Redo', debouncedOnChange);

      // We need to cancel the debounce call because when we remove
      // the editor (onUnmount) this callback is executed in
      // another tick. This results in setting the content to empty.
      editor.on('remove', debouncedOnChange.cancel);
      editor.on('keydown', event => {
        if (_keycodes.isKeyboardEvent.primary(event, 'z')) {
          // Prevent the gutenberg undo kicking in so TinyMCE undo stack works as expected.
          event.stopPropagation();
        }
        if ((event.keyCode === _keycodes.BACKSPACE || event.keyCode === _keycodes.DELETE) && isTmceEmpty(editor)) {
          // Delete the block.
          onReplace([]);
          event.preventDefault();
          event.stopImmediatePropagation();
        }
        const {
          altKey
        } = event;
        /*
         * Prevent Mousetrap from kicking in: TinyMCE already uses its own
         * `alt+f10` shortcut to focus its toolbar.
         */
        if (altKey && event.keyCode === _keycodes.F10) {
          event.stopPropagation();
        }
      });
      editor.on('init', () => {
        const rootNode = editor.getBody();

        // Create the toolbar by refocussing the editor.
        if (rootNode.ownerDocument.activeElement === rootNode) {
          rootNode.blur();
          editor.focus();
        }
      });
    }
    function initialize() {
      const {
        settings
      } = window.wpEditorL10n.tinymce;
      wp.oldEditor.initialize(`editor-${clientId}`, {
        tinymce: {
          ...settings,
          inline: true,
          content_css: false,
          fixed_toolbar_container: `#toolbar-${clientId}`,
          setup: onSetup
        }
      });
    }
    function onReadyStateChange() {
      if (document.readyState === 'complete') {
        initialize();
      }
    }
    if (document.readyState === 'complete') {
      initialize();
    } else {
      document.addEventListener('readystatechange', onReadyStateChange);
    }
    return () => {
      document.removeEventListener('readystatechange', onReadyStateChange);
      wp.oldEditor.remove(`editor-${clientId}`);
    };
  }, []);
  function focus() {
    const editor = window.tinymce.get(`editor-${clientId}`);
    if (editor) {
      editor.focus();
    }
  }
  function onToolbarKeyDown(event) {
    // Prevent WritingFlow from kicking in and allow arrows navigation on the toolbar.
    event.stopPropagation();
    // Prevent Mousetrap from moving focus to the top toolbar when pressing `alt+f10` on this block toolbar.
    event.nativeEvent.stopImmediatePropagation();
  }

  // Disable reasons:
  //
  // jsx-a11y/no-static-element-interactions
  //  - the toolbar itself is non-interactive, but must capture events
  //    from the KeyboardShortcuts component to stop their propagation.

  /* eslint-disable jsx-a11y/no-static-element-interactions */
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("div", {
    key: "toolbar",
    id: `toolbar-${clientId}`,
    className: "block-library-classic__toolbar",
    onClick: focus,
    "data-placeholder": (0, _i18n.__)('Classic'),
    onKeyDown: onToolbarKeyDown
  }), (0, _react.createElement)("div", {
    key: "editor",
    id: `editor-${clientId}`,
    className: "wp-block-freeform block-library-rich-text__tinymce"
  }));
  /* eslint-enable jsx-a11y/no-static-element-interactions */
}
//# sourceMappingURL=edit.js.map