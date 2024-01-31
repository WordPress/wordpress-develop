"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.toggleLabel = exports.noButton = exports.buttonWithIcon = exports.buttonOutside = exports.buttonOnly = exports.buttonInside = void 0;
var _react = require("react");
var _components = require("@wordpress/components");
/**
 * WordPress dependencies
 */

const buttonOnly = exports.buttonOnly = (0, _react.createElement)(_components.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24"
}, (0, _react.createElement)(_components.Rect, {
  x: "7",
  y: "10",
  width: "10",
  height: "4",
  rx: "1",
  fill: "currentColor"
}));
const buttonOutside = exports.buttonOutside = (0, _react.createElement)(_components.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24"
}, (0, _react.createElement)(_components.Rect, {
  x: "4.75",
  y: "15.25",
  width: "6.5",
  height: "9.5",
  transform: "rotate(-90 4.75 15.25)",
  stroke: "currentColor",
  strokeWidth: "1.5",
  fill: "none"
}), (0, _react.createElement)(_components.Rect, {
  x: "16",
  y: "10",
  width: "4",
  height: "4",
  rx: "1",
  fill: "currentColor"
}));
const buttonInside = exports.buttonInside = (0, _react.createElement)(_components.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24"
}, (0, _react.createElement)(_components.Rect, {
  x: "4.75",
  y: "15.25",
  width: "6.5",
  height: "14.5",
  transform: "rotate(-90 4.75 15.25)",
  stroke: "currentColor",
  strokeWidth: "1.5",
  fill: "none"
}), (0, _react.createElement)(_components.Rect, {
  x: "14",
  y: "10",
  width: "4",
  height: "4",
  rx: "1",
  fill: "currentColor"
}));
const noButton = exports.noButton = (0, _react.createElement)(_components.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24"
}, (0, _react.createElement)(_components.Rect, {
  x: "4.75",
  y: "15.25",
  width: "6.5",
  height: "14.5",
  transform: "rotate(-90 4.75 15.25)",
  stroke: "currentColor",
  fill: "none",
  strokeWidth: "1.5"
}));
const buttonWithIcon = exports.buttonWithIcon = (0, _react.createElement)(_components.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24"
}, (0, _react.createElement)(_components.Rect, {
  x: "4.75",
  y: "7.75",
  width: "14.5",
  height: "8.5",
  rx: "1.25",
  stroke: "currentColor",
  fill: "none",
  strokeWidth: "1.5"
}), (0, _react.createElement)(_components.Rect, {
  x: "8",
  y: "11",
  width: "8",
  height: "2",
  fill: "currentColor"
}));
const toggleLabel = exports.toggleLabel = (0, _react.createElement)(_components.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24"
}, (0, _react.createElement)(_components.Rect, {
  x: "4.75",
  y: "17.25",
  width: "5.5",
  height: "14.5",
  transform: "rotate(-90 4.75 17.25)",
  stroke: "currentColor",
  fill: "none",
  strokeWidth: "1.5"
}), (0, _react.createElement)(_components.Rect, {
  x: "4",
  y: "7",
  width: "10",
  height: "2",
  fill: "currentColor"
}));
//# sourceMappingURL=icons.js.map