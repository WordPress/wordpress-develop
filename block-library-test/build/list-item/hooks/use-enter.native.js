"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useEnter;
var _blocks = require("@wordpress/blocks");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _useOutdentListItem = _interopRequireDefault(require("./use-outdent-list-item"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function useEnter(props, preventDefault) {
  const {
    replaceBlocks,
    selectionChange
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    getBlock,
    getBlockRootClientId,
    getBlockIndex,
    getBlockName
  } = (0, _data.useSelect)(_blockEditor.store);
  const propsRef = (0, _element.useRef)(props);
  propsRef.current = props;
  const outdentListItem = (0, _useOutdentListItem.default)();
  return {
    onEnter() {
      const {
        content,
        clientId
      } = propsRef.current;
      if (content.length) {
        return;
      }
      preventDefault.current = true;
      if (getBlockName(getBlockRootClientId(getBlockRootClientId(propsRef.current.clientId))) === 'core/list-item') {
        outdentListItem();
        return;
      }
      // Here we are in top level list so we need to split.
      const topParentListBlock = getBlock(getBlockRootClientId(clientId));
      const blockIndex = getBlockIndex(clientId);
      const head = (0, _blocks.cloneBlock)({
        ...topParentListBlock,
        innerBlocks: topParentListBlock.innerBlocks.slice(0, blockIndex)
      });
      const middle = (0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)());
      // Last list item might contain a `list` block innerBlock
      // In that case append remaining innerBlocks blocks.
      const after = [...(topParentListBlock.innerBlocks[blockIndex].innerBlocks[0]?.innerBlocks || []), ...topParentListBlock.innerBlocks.slice(blockIndex + 1)];
      const tail = after.length ? [(0, _blocks.cloneBlock)({
        ...topParentListBlock,
        innerBlocks: after
      })] : [];
      replaceBlocks(topParentListBlock.clientId, [head, middle, ...tail], 1);
      // We manually change the selection here because we are replacing
      // a different block than the selected one.
      selectionChange(middle.clientId);
    }
  };
}
//# sourceMappingURL=use-enter.native.js.map