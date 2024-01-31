"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.convertToListItems = convertToListItems;
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

function convertBlockToList(block) {
  const list = (0, _blocks.switchToBlockType)(block, 'core/list');
  if (list) {
    return list;
  }
  const paragraph = (0, _blocks.switchToBlockType)(block, 'core/paragraph');
  if (!paragraph) {
    return null;
  }
  return (0, _blocks.switchToBlockType)(paragraph, 'core/list');
}
function convertToListItems(blocks) {
  const listItems = [];
  for (let block of blocks) {
    if (block.name === 'core/list-item') {
      listItems.push(block);
    } else if (block.name === 'core/list') {
      listItems.push(...block.innerBlocks);
    } else if (block = convertBlockToList(block)) {
      for (const {
        innerBlocks
      } of block) {
        listItems.push(...innerBlocks);
      }
    }
  }
  return listItems;
}
//# sourceMappingURL=utils.js.map