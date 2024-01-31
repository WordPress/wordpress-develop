"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

const transforms = {
  from: [{
    type: 'block',
    isMultiBlock: true,
    blocks: ['*'],
    __experimentalConvert(blocks) {
      const alignments = ['wide', 'full'];

      // Determine the widest setting of all the blocks to be grouped
      const widestAlignment = blocks.reduce((accumulator, block) => {
        const {
          align
        } = block.attributes;
        return alignments.indexOf(align) > alignments.indexOf(accumulator) ? align : accumulator;
      }, undefined);

      // Clone the Blocks to be Grouped
      // Failing to create new block references causes the original blocks
      // to be replaced in the switchToBlockType call thereby meaning they
      // are removed both from their original location and within the
      // new group block.
      const groupInnerBlocks = blocks.map(block => {
        return (0, _blocks.createBlock)(block.name, block.attributes, block.innerBlocks);
      });
      return (0, _blocks.createBlock)('core/group', {
        align: widestAlignment,
        layout: {
          type: 'constrained'
        }
      }, groupInnerBlocks);
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map