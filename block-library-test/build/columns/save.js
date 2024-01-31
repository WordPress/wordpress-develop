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
    isStackedOnMobile,
    verticalAlignment
  } = attributes;
  const className = (0, _classnames.default)({
    [`are-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
    [`is-not-stacked-on-mobile`]: !isStackedOnMobile
  });
  const blockProps = _blockEditor.useBlockProps.save({
    className
  });
  const innerBlocksProps = _blockEditor.useInnerBlocksProps.save(blockProps);
  return (0, _react.createElement)("div", {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=save.js.map