"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = EnhancedPaginationControl;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _utils = require("../../utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function EnhancedPaginationControl({
  enhancedPagination,
  setAttributes,
  clientId
}) {
  const {
    hasUnsupportedBlocks
  } = (0, _utils.useUnsupportedBlocks)(clientId);
  let help = (0, _i18n.__)('Browsing between pages requires a full page reload.');
  if (enhancedPagination) {
    help = (0, _i18n.__)("Browsing between pages won't require a full page reload, unless non-compatible blocks are detected.");
  } else if (hasUnsupportedBlocks) {
    help = (0, _i18n.__)("Force page reload can't be disabled because there are non-compatible blocks inside the Query block.");
  }
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ToggleControl, {
    label: (0, _i18n.__)('Force page reload'),
    help: help,
    checked: !enhancedPagination,
    disabled: hasUnsupportedBlocks,
    onChange: value => {
      setAttributes({
        enhancedPagination: !value
      });
    }
  }));
}
//# sourceMappingURL=enhanced-pagination-control.js.map