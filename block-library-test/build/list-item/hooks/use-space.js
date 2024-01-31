"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useSpace;
var _compose = require("@wordpress/compose");
var _keycodes = require("@wordpress/keycodes");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _useIndentListItem = _interopRequireDefault(require("./use-indent-list-item"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function useSpace(clientId) {
  const {
    getSelectionStart,
    getSelectionEnd,
    getBlockIndex
  } = (0, _data.useSelect)(_blockEditor.store);
  const indentListItem = (0, _useIndentListItem.default)(clientId);
  return (0, _compose.useRefEffect)(element => {
    function onKeyDown(event) {
      const {
        keyCode,
        shiftKey,
        altKey,
        metaKey,
        ctrlKey
      } = event;
      if (event.defaultPrevented || keyCode !== _keycodes.SPACE ||
      // Only override when no modifiers are pressed.
      shiftKey || altKey || metaKey || ctrlKey) {
        return;
      }
      if (getBlockIndex(clientId) === 0) {
        return;
      }
      const selectionStart = getSelectionStart();
      const selectionEnd = getSelectionEnd();
      if (selectionStart.offset === 0 && selectionEnd.offset === 0) {
        event.preventDefault();
        indentListItem();
      }
    }
    element.addEventListener('keydown', onKeyDown);
    return () => {
      element.removeEventListener('keydown', onKeyDown);
    };
  }, [clientId, indentListItem]);
}
//# sourceMappingURL=use-space.js.map