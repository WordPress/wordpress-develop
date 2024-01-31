"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

const orderOptions = [{
  label: (0, _i18n.__)('Newest to oldest'),
  value: 'date/desc'
}, {
  label: (0, _i18n.__)('Oldest to newest'),
  value: 'date/asc'
}, {
  /* translators: label for ordering posts by title in ascending order */
  label: (0, _i18n.__)('A → Z'),
  value: 'title/asc'
}, {
  /* translators: label for ordering posts by title in descending order */
  label: (0, _i18n.__)('Z → A'),
  value: 'title/desc'
}];
function OrderControl({
  order,
  orderBy,
  onChange
}) {
  return (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Order by'),
    value: `${orderBy}/${order}`,
    options: orderOptions,
    onChange: value => {
      const [newOrderBy, newOrder] = value.split('/');
      onChange({
        order: newOrder,
        orderBy: newOrderBy
      });
    }
  });
}
var _default = exports.default = OrderControl;
//# sourceMappingURL=order-control.js.map