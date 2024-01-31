"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.useInnerBlocks = useInnerBlocks;
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const EMPTY_ARRAY = [];
function useInnerBlocks(clientId) {
  return (0, _data.useSelect)(select => {
    const {
      getBlock,
      getBlocks,
      hasSelectedInnerBlock
    } = select(_blockEditor.store);

    // This relies on the fact that `getBlock` won't return controlled
    // inner blocks, while `getBlocks` does. It might be more stable to
    // introduce a selector like `getUncontrolledInnerBlocks`, just in
    // case `getBlock` is fixed.
    const _uncontrolledInnerBlocks = getBlock(clientId).innerBlocks;
    const _hasUncontrolledInnerBlocks = !!_uncontrolledInnerBlocks?.length;
    const _controlledInnerBlocks = _hasUncontrolledInnerBlocks ? EMPTY_ARRAY : getBlocks(clientId);
    return {
      innerBlocks: _hasUncontrolledInnerBlocks ? _uncontrolledInnerBlocks : _controlledInnerBlocks,
      hasUncontrolledInnerBlocks: _hasUncontrolledInnerBlocks,
      uncontrolledInnerBlocks: _uncontrolledInnerBlocks,
      controlledInnerBlocks: _controlledInnerBlocks,
      isInnerBlockSelected: hasSelectedInnerBlock(clientId, true)
    };
  }, [clientId]);
}
//# sourceMappingURL=use-inner-blocks.js.map