"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _classnames = _interopRequireDefault(require("classnames"));
/**
 * WordPress dependencies
 */

/**
 * External dependencies
 */

function save({
  attributes
}) {
  const {
    type
  } = attributes;
  return (0, _react.createElement)("div", {
    ..._blockEditor.useInnerBlocksProps.save(_blockEditor.useBlockProps.save({
      className: (0, _classnames.default)('wp-block-form-submission-notification', {
        [`form-notification-type-${type}`]: type
      })
    }))
  });
}
//# sourceMappingURL=save.js.map