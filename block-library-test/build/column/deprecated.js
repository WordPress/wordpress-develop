"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const deprecated = [{
  attributes: {
    verticalAlignment: {
      type: 'string'
    },
    width: {
      type: 'number',
      min: 0,
      max: 100
    }
  },
  isEligible({
    width
  }) {
    return isFinite(width);
  },
  migrate(attributes) {
    return {
      ...attributes,
      width: `${attributes.width}%`
    };
  },
  save({
    attributes
  }) {
    const {
      verticalAlignment,
      width
    } = attributes;
    const wrapperClasses = (0, _classnames.default)({
      [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment
    });
    const style = {
      flexBasis: width + '%'
    };
    return (0, _react.createElement)("div", {
      className: wrapperClasses,
      style: style
    }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null));
  }
}];
var _default = exports.default = deprecated;
//# sourceMappingURL=deprecated.js.map