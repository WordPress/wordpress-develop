import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { SVG, Rect } from '@wordpress/components';
export const circle = (size, color) => createElement(SVG, {
  fill: "none",
  xmlns: "http://www.w3.org/2000/svg"
}, createElement(Rect, {
  width: size,
  height: size,
  rx: size / 2,
  fill: color
}));
export const circleOutline = (size, color) => createElement(SVG, {
  width: size,
  height: size,
  fill: "none",
  xmlns: "http://www.w3.org/2000/svg"
}, createElement(Rect, {
  x: "0.5",
  y: "0.5",
  width: size - 1,
  height: size - 1,
  rx: size / 2,
  stroke: color
}));
export const square = (size, color) => createElement(SVG, {
  fill: "none",
  xmlns: "http://www.w3.org/2000/svg"
}, createElement(Rect, {
  width: size,
  height: size,
  fill: color
}));
//# sourceMappingURL=icons.native.js.map