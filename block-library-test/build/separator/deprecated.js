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

const v1 = {
  attributes: {
    color: {
      type: 'string'
    },
    customColor: {
      type: 'string'
    }
  },
  save({
    attributes
  }) {
    const {
      color,
      customColor
    } = attributes;

    // the hr support changing color using border-color, since border-color
    // is not yet supported in the color palette, we use background-color
    const backgroundClass = (0, _blockEditor.getColorClassName)('background-color', color);
    // the dots styles uses text for the dots, to change those dots color is
    // using color, not backgroundColor
    const colorClass = (0, _blockEditor.getColorClassName)('color', color);
    const className = (0, _classnames.default)({
      'has-text-color has-background': color || customColor,
      [backgroundClass]: backgroundClass,
      [colorClass]: colorClass
    });
    const style = {
      backgroundColor: backgroundClass ? undefined : customColor,
      color: colorClass ? undefined : customColor
    };
    return (0, _react.createElement)("hr", {
      ..._blockEditor.useBlockProps.save({
        className,
        style
      })
    });
  },
  migrate(attributes) {
    const {
      color,
      customColor,
      ...restAttributes
    } = attributes;
    return {
      ...restAttributes,
      backgroundColor: color ? color : undefined,
      opacity: 'css',
      style: customColor ? {
        color: {
          background: customColor
        }
      } : undefined
    };
  }
};
var _default = exports.default = [v1];
//# sourceMappingURL=deprecated.js.map