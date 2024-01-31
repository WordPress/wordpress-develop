"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.useOnEnter = useOnEnter;
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _keycodes = require("@wordpress/keycodes");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

function useOnEnter(props) {
  const {
    batch
  } = (0, _data.useRegistry)();
  const {
    moveBlocksToPosition,
    replaceInnerBlocks,
    duplicateBlocks,
    insertBlock
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    getBlockRootClientId,
    getBlockIndex,
    getBlockOrder,
    getBlockName,
    getBlock,
    getNextBlockClientId,
    canInsertBlockType
  } = (0, _data.useSelect)(_blockEditor.store);
  const propsRef = (0, _element.useRef)(props);
  propsRef.current = props;
  return (0, _compose.useRefEffect)(element => {
    function onKeyDown(event) {
      if (event.defaultPrevented) {
        return;
      }
      if (event.keyCode !== _keycodes.ENTER) {
        return;
      }
      const {
        content,
        clientId
      } = propsRef.current;

      // The paragraph should be empty.
      if (content.length) {
        return;
      }
      const wrapperClientId = getBlockRootClientId(clientId);
      if (!(0, _blocks.hasBlockSupport)(getBlockName(wrapperClientId), '__experimentalOnEnter', false)) {
        return;
      }
      const order = getBlockOrder(wrapperClientId);
      const position = order.indexOf(clientId);

      // If it is the last block, exit.
      if (position === order.length - 1) {
        let newWrapperClientId = wrapperClientId;
        while (!canInsertBlockType(getBlockName(clientId), getBlockRootClientId(newWrapperClientId))) {
          newWrapperClientId = getBlockRootClientId(newWrapperClientId);
        }
        if (typeof newWrapperClientId === 'string') {
          event.preventDefault();
          moveBlocksToPosition([clientId], wrapperClientId, getBlockRootClientId(newWrapperClientId), getBlockIndex(newWrapperClientId) + 1);
        }
        return;
      }
      const defaultBlockName = (0, _blocks.getDefaultBlockName)();
      if (!canInsertBlockType(defaultBlockName, getBlockRootClientId(wrapperClientId))) {
        return;
      }
      event.preventDefault();

      // If it is in the middle, split the block in two.
      const wrapperBlock = getBlock(wrapperClientId);
      batch(() => {
        duplicateBlocks([wrapperClientId]);
        const blockIndex = getBlockIndex(wrapperClientId);
        replaceInnerBlocks(wrapperClientId, wrapperBlock.innerBlocks.slice(0, position));
        replaceInnerBlocks(getNextBlockClientId(wrapperClientId), wrapperBlock.innerBlocks.slice(position + 1));
        insertBlock((0, _blocks.createBlock)(defaultBlockName), blockIndex + 1, getBlockRootClientId(wrapperClientId), true);
      });
    }
    element.addEventListener('keydown', onKeyDown);
    return () => {
      element.removeEventListener('keydown', onKeyDown);
    };
  }, []);
}
//# sourceMappingURL=use-enter.js.map