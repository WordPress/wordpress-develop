"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.QueryPaginationLabelControl = QueryPaginationLabelControl;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
/**
 * WordPress dependencies
 */

function QueryPaginationLabelControl({
  value,
  onChange
}) {
  return (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show label text'),
    help: (0, _i18n.__)('Toggle off to hide the label text, e.g. "Next Page".'),
    onChange: onChange,
    checked: value === true
  });
}
//# sourceMappingURL=query-pagination-label-control.js.map