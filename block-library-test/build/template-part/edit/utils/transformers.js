"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.transformWidgetToBlock = transformWidgetToBlock;
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

/**
 * Converts a widget entity record into a block.
 *
 * @param {Object} widget The widget entity record.
 * @return {Object} a block (converted from the entity record).
 */
function transformWidgetToBlock(widget) {
  if (widget.id_base !== 'block') {
    let attributes;
    if (widget._embedded.about[0].is_multi) {
      attributes = {
        idBase: widget.id_base,
        instance: widget.instance
      };
    } else {
      attributes = {
        id: widget.id
      };
    }
    return switchLegacyWidgetType((0, _blocks.createBlock)('core/legacy-widget', attributes));
  }
  const parsedBlocks = (0, _blocks.parse)(widget.instance.raw.content, {
    __unstableSkipAutop: true
  });
  if (!parsedBlocks.length) {
    return undefined;
  }
  const block = parsedBlocks[0];
  if (block.name === 'core/widget-group') {
    return (0, _blocks.createBlock)((0, _blocks.getGroupingBlockName)(), undefined, transformInnerBlocks(block.innerBlocks));
  }
  if (block.innerBlocks.length > 0) {
    return (0, _blocks.cloneBlock)(block, undefined, transformInnerBlocks(block.innerBlocks));
  }
  return block;
}

/**
 * Switch Legacy Widget to the first matching transformation block.
 *
 * @param {Object} block Legacy Widget block object
 * @return {Object|undefined} a block
 */
function switchLegacyWidgetType(block) {
  const transforms = (0, _blocks.getPossibleBlockTransformations)([block]).filter(item => {
    // The block without any transformations can't be a wildcard.
    if (!item.transforms) {
      return true;
    }
    const hasWildCardFrom = item.transforms?.from?.find(from => from.blocks && from.blocks.includes('*'));
    const hasWildCardTo = item.transforms?.to?.find(to => to.blocks && to.blocks.includes('*'));

    // Skip wildcard transformations.
    return !hasWildCardFrom && !hasWildCardTo;
  });
  if (!transforms.length) {
    return undefined;
  }
  return (0, _blocks.switchToBlockType)(block, transforms[0].name);
}
function transformInnerBlocks(innerBlocks = []) {
  return innerBlocks.flatMap(block => {
    if (block.name === 'core/legacy-widget') {
      return switchLegacyWidgetType(block);
    }
    return (0, _blocks.createBlock)(block.name, block.attributes, transformInnerBlocks(block.innerBlocks));
  }).filter(block => !!block);
}
//# sourceMappingURL=transformers.js.map