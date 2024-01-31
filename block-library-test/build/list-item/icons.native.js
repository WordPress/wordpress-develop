"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.square = exports.circleOutline = exports.circle = void 0;
var _react = require("react");
var _components = require("@wordpress/components");
/**
 * WordPress dependencies
 */

const circle = (size, color) => (0, _react.createElement)(_components.SVG, {
  fill: "none",
  xmlns: "http://www.w3.org/2000/svg"
}, (0, _react.createElement)(_components.Rect, {
  width: size,
  height: size,
  rx: size / 2,
  fill: color
}));
exports.circle = circle;
const circleOutline = (size, color) => (0, _react.createElement)(_components.SVG, {
  width: size,
  height: size,
  fill: "none",
  xmlns: "http://www.w3.org/2000/svg"
}, (0, _react.createElement)(_components.Rect, {
  x: "0.5",
  y: "0.5",
  width: size - 1,
  height: size - 1,
  rx: size / 2,
  stroke: color
}));
exports.circleOutline = circleOutline;
const square = (size, color) => (0, _react.createElement)(_components.SVG, {
  fill: "none",
  xmlns: "http://www.w3.org/2000/svg"
}, (0, _react.createElement)(_components.Rect, {
  width: size,
  height: size,
  fill: color
}));
exports.square = square;
//# sourceMappingURL=icons.native.js.map