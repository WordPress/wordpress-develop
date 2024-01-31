"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.QueryPaginationArrowControls = QueryPaginationArrowControls;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
/**
 * WordPress dependencies
 */

function QueryPaginationArrowControls({
  value,
  onChange
}) {
  return (0, _react.createElement)(_components.__experimentalToggleGroupControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Arrow'),
    value: value,
    onChange: onChange,
    help: (0, _i18n.__)('A decorative arrow appended to the next and previous page link.'),
    isBlock: true
  }, (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "none",
    label: (0, _i18n._x)('None', 'Arrow option for Query Pagination Next/Previous blocks')
  }), (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "arrow",
    label: (0, _i18n._x)('Arrow', 'Arrow option for Query Pagination Next/Previous blocks')
  }), (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "chevron",
    label: (0, _i18n._x)('Chevron', 'Arrow option for Query Pagination Next/Previous blocks')
  }));
}
//# sourceMappingURL=query-pagination-arrow-controls.js.map