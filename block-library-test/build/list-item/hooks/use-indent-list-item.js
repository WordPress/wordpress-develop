"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useIndentListItem;
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

function useIndentListItem(clientId) {
  const {
    replaceBlocks,
    selectionChange,
    multiSelect
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    getBlock,
    getPreviousBlockClientId,
    getSelectionStart,
    getSelectionEnd,
    hasMultiSelection,
    getMultiSelectedBlockClientIds
  } = (0, _data.useSelect)(_blockEditor.store);
  return (0, _element.useCallback)(() => {
    const _hasMultiSelection = hasMultiSelection();
    const clientIds = _hasMultiSelection ? getMultiSelectedBlockClientIds() : [clientId];
    const clonedBlocks = clientIds.map(_clientId => (0, _blocks.cloneBlock)(getBlock(_clientId)));
    const previousSiblingId = getPreviousBlockClientId(clientId);
    const newListItem = (0, _blocks.cloneBlock)(getBlock(previousSiblingId));
    // If the sibling has no innerBlocks, create a new `list` block.
    if (!newListItem.innerBlocks?.length) {
      newListItem.innerBlocks = [(0, _blocks.createBlock)('core/list')];
    }
    // A list item usually has one `list`, but it's possible to have
    // more. So we need to preserve the previous `list` blocks and
    // merge the new blocks to the last `list`.
    newListItem.innerBlocks[newListItem.innerBlocks.length - 1].innerBlocks.push(...clonedBlocks);

    // We get the selection start/end here, because when
    // we replace blocks, the selection is updated too.
    const selectionStart = getSelectionStart();
    const selectionEnd = getSelectionEnd();
    // Replace the previous sibling of the block being indented and the indented blocks,
    // with a new block whose attributes are equal to the ones of the previous sibling and
    // whose descendants are the children of the previous sibling, followed by the indented blocks.
    replaceBlocks([previousSiblingId, ...clientIds], [newListItem]);
    if (!_hasMultiSelection) {
      selectionChange(clonedBlocks[0].clientId, selectionEnd.attributeKey, selectionEnd.clientId === selectionStart.clientId ? selectionStart.offset : selectionEnd.offset, selectionEnd.offset);
    } else {
      multiSelect(clonedBlocks[0].clientId, clonedBlocks[clonedBlocks.length - 1].clientId);
    }
  }, [clientId]);
}
//# sourceMappingURL=use-indent-list-item.js.map