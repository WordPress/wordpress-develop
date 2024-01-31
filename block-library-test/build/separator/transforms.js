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
    type: 'enter',
    regExp: /^-{3,}$/,
    transform: () => (0, _blocks.createBlock)('core/separator')
  }, {
    type: 'raw',
    selector: 'hr',
    schema: {
      hr: {}
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map