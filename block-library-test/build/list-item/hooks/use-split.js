"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useSplit;
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

function useSplit(clientId) {
  // We can not rely on the isAfterOriginal parameter of the callback,
  // because if the value after the split is empty isAfterOriginal is false
  // while the value is in fact after the original. So to avoid that issue we use
  // a flag where the first execution of the callback is false (it is the before value)
  // and the second execution is true, it is the after value.
  const isAfter = (0, _element.useRef)(false);
  const {
    getBlock
  } = (0, _data.useSelect)(_blockEditor.store);
  return (0, _element.useCallback)(value => {
    const block = getBlock(clientId);
    if (isAfter.current) {
      return (0, _blocks.cloneBlock)(block, {
        content: value
      });
    }
    isAfter.current = true;
    return (0, _blocks.createBlock)(block.name, {
      ...block.attributes,
      content: value
    });
  }, [clientId, getBlock]);
}
//# sourceMappingURL=use-split.js.map