"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _compose = require("@wordpress/compose");
var _components = require("@wordpress/components");
var _edit = _interopRequireDefault(require("./edit"));
var _edit2 = _interopRequireDefault(require("./v1/edit"));
var _shared = require("./shared");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/*
 * Using a wrapper around the logic to load the edit for v1 of Gallery block
 * or the refactored version with InnerBlocks. This is to prevent conditional
 * use of hooks lint errors if adding this logic to the top of the edit component.
 */
function GalleryEditWrapper(props) {
  if (!(0, _shared.isGalleryV2Enabled)()) {
    return (0, _react.createElement)(_edit2.default, {
      ...props
    });
  }
  return (0, _react.createElement)(_edit.default, {
    ...props
  });
}
var _default = exports.default = (0, _compose.compose)([_components.withNotices])(GalleryEditWrapper);
//# sourceMappingURL=edit-wrapper.js.map