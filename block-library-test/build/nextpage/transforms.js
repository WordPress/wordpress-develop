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
    type: 'raw',
    schema: {
      'wp-block': {
        attributes: ['data-block']
      }
    },
    isMatch: node => node.dataset && node.dataset.block === 'core/nextpage',
    transform() {
      return (0, _blocks.createBlock)('core/nextpage', {});
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map