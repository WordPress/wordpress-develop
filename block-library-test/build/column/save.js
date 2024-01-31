"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function save({
  attributes
}) {
  const {
    verticalAlignment,
    width
  } = attributes;
  const wrapperClasses = (0, _classnames.default)({
    [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment
  });
  let style;
  if (width && /\d/.test(width)) {
    // Numbers are handled for backward compatibility as they can be still provided with templates.
    let flexBasis = Number.isFinite(width) ? width + '%' : width;
    // In some cases we need to round the width to a shorter float.
    if (!Number.isFinite(width) && width?.endsWith('%')) {
      const multiplier = 1000000000000;
      // Shrink the number back to a reasonable float.
      flexBasis = Math.round(Number.parseFloat(width) * multiplier) / multiplier + '%';
    }
    style = {
      flexBasis
    };
  }
  const blockProps = _blockEditor.useBlockProps.save({
    className: wrapperClasses,
    style
  });
  const innerBlocksProps = _blockEditor.useInnerBlocksProps.save(blockProps);
  return (0, _react.createElement)("div", {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=save.js.map