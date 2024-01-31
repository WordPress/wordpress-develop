"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CommentsInspectorControls;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

function CommentsInspectorControls({
  attributes: {
    tagName
  },
  setAttributes
}) {
  const htmlElementMessages = {
    section: (0, _i18n.__)("The <section> element should represent a standalone portion of the document that can't be better represented by another element."),
    aside: (0, _i18n.__)("The <aside> element should represent a portion of a document whose content is only indirectly related to the document's main content.")
  };
  return (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('HTML element'),
    options: [{
      label: (0, _i18n.__)('Default (<div>)'),
      value: 'div'
    }, {
      label: '<section>',
      value: 'section'
    }, {
      label: '<aside>',
      value: 'aside'
    }],
    value: tagName,
    onChange: value => setAttributes({
      tagName: value
    }),
    help: htmlElementMessages[tagName]
  })));
}
//# sourceMappingURL=comments-inspector-controls.js.map