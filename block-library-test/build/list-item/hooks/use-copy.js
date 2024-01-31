"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useCopy;
var _compose = require("@wordpress/compose");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
/**
 * WordPress dependencies
 */

function useCopy(clientId) {
  const {
    getBlockRootClientId,
    getBlockName,
    getBlockAttributes
  } = (0, _data.useSelect)(_blockEditor.store);
  return (0, _compose.useRefEffect)(node => {
    function onCopy(event) {
      // The event propagates through all nested lists, so don't override
      // when copying nested list items.
      if (event.clipboardData.getData('__unstableWrapperBlockName')) {
        return;
      }
      const rootClientId = getBlockRootClientId(clientId);
      event.clipboardData.setData('__unstableWrapperBlockName', getBlockName(rootClientId));
      event.clipboardData.setData('__unstableWrapperBlockAttributes', JSON.stringify(getBlockAttributes(rootClientId)));
    }
    node.addEventListener('copy', onCopy);
    node.addEventListener('cut', onCopy);
    return () => {
      node.removeEventListener('copy', onCopy);
      node.removeEventListener('cut', onCopy);
    };
  }, []);
}
//# sourceMappingURL=use-copy.js.map