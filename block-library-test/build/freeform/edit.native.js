"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _edit = _interopRequireDefault(require("../missing/edit"));
/**
 * Internal dependencies
 */

const ClassicEdit = props => (0, _react.createElement)(_edit.default, {
  ...props,
  attributes: {
    ...props.attributes,
    originalName: props.name
  }
});
var _default = exports.default = ClassicEdit;
//# sourceMappingURL=edit.native.js.map