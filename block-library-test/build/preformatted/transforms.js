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
    blocks: ['core/code', 'core/paragraph'],
    transform: ({
      content,
      anchor
    }) => (0, _blocks.createBlock)('core/preformatted', {
      content,
      anchor
    })
  }, {
    type: 'raw',
    isMatch: node => node.nodeName === 'PRE' && !(node.children.length === 1 && node.firstChild.nodeName === 'CODE'),
    schema: ({
      phrasingContentSchema
    }) => ({
      pre: {
        children: phrasingContentSchema
      }
    })
  }],
  to: [{
    type: 'block',
    blocks: ['core/paragraph'],
    transform: attributes => (0, _blocks.createBlock)('core/paragraph', attributes)
  }, {
    type: 'block',
    blocks: ['core/code'],
    transform: attributes => (0, _blocks.createBlock)('core/code', attributes)
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map