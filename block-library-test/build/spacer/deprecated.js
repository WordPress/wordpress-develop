"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const deprecated = [{
  attributes: {
    height: {
      type: 'number',
      default: 100
    },
    width: {
      type: 'number'
    }
  },
  migrate(attributes) {
    const {
      height,
      width
    } = attributes;
    return {
      ...attributes,
      width: width !== undefined ? `${width}px` : undefined,
      height: height !== undefined ? `${height}px` : undefined
    };
  },
  save({
    attributes
  }) {
    return (0, _react.createElement)("div", {
      ..._blockEditor.useBlockProps.save({
        style: {
          height: attributes.height,
          width: attributes.width
        },
        'aria-hidden': true
      })
    });
  }
}];
var _default = exports.default = deprecated;
//# sourceMappingURL=deprecated.js.map