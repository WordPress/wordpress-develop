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
    blocks: ['core/site-logo'],
    transform: ({
      isLink,
      linkTarget
    }) => {
      return (0, _blocks.createBlock)('core/site-logo', {
        isLink,
        linkTarget
      });
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map