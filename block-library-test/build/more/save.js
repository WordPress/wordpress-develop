"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _element = require("@wordpress/element");
/**
 * WordPress dependencies
 */

function save({
  attributes: {
    customText,
    noTeaser
  }
}) {
  const moreTag = customText ? `<!--more ${customText}-->` : '<!--more-->';
  const noTeaserTag = noTeaser ? '<!--noteaser-->' : '';
  return (0, _react.createElement)(_element.RawHTML, null, [moreTag, noTeaserTag].filter(Boolean).join('\n'));
}
//# sourceMappingURL=save.js.map