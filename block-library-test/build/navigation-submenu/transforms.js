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
  to: [{
    type: 'block',
    blocks: ['core/navigation-link'],
    isMatch: (attributes, block) => block?.innerBlocks?.length === 0,
    transform: attributes => (0, _blocks.createBlock)('core/navigation-link', attributes)
  }, {
    type: 'block',
    blocks: ['core/spacer'],
    isMatch: (attributes, block) => block?.innerBlocks?.length === 0,
    transform: () => {
      return (0, _blocks.createBlock)('core/spacer');
    }
  }, {
    type: 'block',
    blocks: ['core/site-logo'],
    isMatch: (attributes, block) => block?.innerBlocks?.length === 0,
    transform: () => {
      return (0, _blocks.createBlock)('core/site-logo');
    }
  }, {
    type: 'block',
    blocks: ['core/home-link'],
    isMatch: (attributes, block) => block?.innerBlocks?.length === 0,
    transform: () => {
      return (0, _blocks.createBlock)('core/home-link');
    }
  }, {
    type: 'block',
    blocks: ['core/social-links'],
    isMatch: (attributes, block) => block?.innerBlocks?.length === 0,
    transform: () => {
      return (0, _blocks.createBlock)('core/social-links');
    }
  }, {
    type: 'block',
    blocks: ['core/search'],
    isMatch: (attributes, block) => block?.innerBlocks?.length === 0,
    transform: () => {
      return (0, _blocks.createBlock)('core/search');
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map