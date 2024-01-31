"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useOutdentListItem;
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

function useOutdentListItem() {
  const registry = (0, _data.useRegistry)();
  const {
    moveBlocksToPosition,
    removeBlock,
    insertBlock,
    updateBlockListSettings
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    getBlockRootClientId,
    getBlockName,
    getBlockOrder,
    getBlockIndex,
    getSelectedBlockClientIds,
    getBlock,
    getBlockListSettings
  } = (0, _data.useSelect)(_blockEditor.store);
  function getParentListItemId(id) {
    const listId = getBlockRootClientId(id);
    const parentListItemId = getBlockRootClientId(listId);
    if (!parentListItemId) return;
    if (getBlockName(parentListItemId) !== 'core/list-item') return;
    return parentListItemId;
  }
  return (0, _element.useCallback)((clientIds = getSelectedBlockClientIds()) => {
    if (!Array.isArray(clientIds)) {
      clientIds = [clientIds];
    }
    if (!clientIds.length) return;
    const firstClientId = clientIds[0];

    // Can't outdent if it's not a list item.
    if (getBlockName(firstClientId) !== 'core/list-item') return;
    const parentListItemId = getParentListItemId(firstClientId);

    // Can't outdent if it's at the top level.
    if (!parentListItemId) return;
    const parentListId = getBlockRootClientId(firstClientId);
    const lastClientId = clientIds[clientIds.length - 1];
    const order = getBlockOrder(parentListId);
    const followingListItems = order.slice(getBlockIndex(lastClientId) + 1);
    registry.batch(() => {
      if (followingListItems.length) {
        let nestedListId = getBlockOrder(firstClientId)[0];
        if (!nestedListId) {
          const nestedListBlock = (0, _blocks.cloneBlock)(getBlock(parentListId), {}, []);
          nestedListId = nestedListBlock.clientId;
          insertBlock(nestedListBlock, 0, firstClientId, false);
          // Immediately update the block list settings, otherwise
          // blocks can't be moved here due to canInsert checks.
          updateBlockListSettings(nestedListId, getBlockListSettings(parentListId));
        }
        moveBlocksToPosition(followingListItems, parentListId, nestedListId);
      }
      moveBlocksToPosition(clientIds, parentListId, getBlockRootClientId(parentListItemId), getBlockIndex(parentListItemId) + 1);
      if (!getBlockOrder(parentListId).length) {
        const shouldSelectParent = false;
        removeBlock(parentListId, shouldSelectParent);
      }
    });
  }, []);
}
//# sourceMappingURL=use-outdent-list-item.js.map