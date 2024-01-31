"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = _default;
var _blockEditor = require("@wordpress/block-editor");
var _lockUnlock = require("../lock-unlock");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const {
  cleanEmptyObject
} = (0, _lockUnlock.unlock)(_blockEditor.privateApis);

/**
 * Migrates the current style.typography.fontFamily attribute,
 * whose value was "var:preset|font-family|helvetica-arial",
 * to the style.fontFamily attribute, whose value will be "helvetica-arial".
 *
 * @param {Object} attributes The current attributes
 * @return {Object} The updated attributes.
 */
function _default(attributes) {
  if (!attributes?.style?.typography?.fontFamily) {
    return attributes;
  }
  const {
    fontFamily,
    ...typography
  } = attributes.style.typography;
  return {
    ...attributes,
    style: cleanEmptyObject({
      ...attributes.style,
      typography
    }),
    fontFamily: fontFamily.split('|').pop()
  };
}
//# sourceMappingURL=migrate-font-family.js.map