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

function save(props) {
  const {
    attributes: {
      iconBackgroundColorValue,
      iconColorValue,
      showLabels,
      size
    }
  } = props;
  const className = (0, _classnames.default)(size, {
    'has-visible-labels': showLabels,
    'has-icon-color': iconColorValue,
    'has-icon-background-color': iconBackgroundColorValue
  });
  const blockProps = _blockEditor.useBlockProps.save({
    className
  });
  const innerBlocksProps = _blockEditor.useInnerBlocksProps.save(blockProps);
  return (0, _react.createElement)("ul", {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=save.js.map