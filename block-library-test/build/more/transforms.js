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
    isMatch: node => node.dataset && node.dataset.block === 'core/more',
    transform(node) {
      const {
        customText,
        noTeaser
      } = node.dataset;
      const attrs = {};
      // Don't copy unless defined and not an empty string.
      if (customText) {
        attrs.customText = customText;
      }
      // Special handling for boolean.
      if (noTeaser === '') {
        attrs.noTeaser = true;
      }
      return (0, _blocks.createBlock)('core/more', attrs);
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map