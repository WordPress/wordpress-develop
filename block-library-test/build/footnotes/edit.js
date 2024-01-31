"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = FootnotesEdit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _coreData = require("@wordpress/core-data");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _icons = require("@wordpress/icons");
/**
 * WordPress dependencies
 */

function FootnotesEdit({
  context: {
    postType,
    postId
  }
}) {
  const [meta, updateMeta] = (0, _coreData.useEntityProp)('postType', postType, 'meta', postId);
  const footnotesSupported = 'string' === typeof meta?.footnotes;
  const footnotes = meta?.footnotes ? JSON.parse(meta.footnotes) : [];
  const blockProps = (0, _blockEditor.useBlockProps)();
  if (!footnotesSupported) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_components.Placeholder, {
      icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
        icon: _icons.formatListNumbered
      }),
      label: (0, _i18n.__)('Footnotes'),
      instructions: (0, _i18n.__)('Footnotes are not supported here. Add this block to post or page content.')
    }));
  }
  if (!footnotes.length) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_components.Placeholder, {
      icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
        icon: _icons.formatListNumbered
      }),
      label: (0, _i18n.__)('Footnotes'),
      instructions: (0, _i18n.__)('Footnotes found in blocks within this document will be displayed here.')
    }));
  }
  return (0, _react.createElement)("ol", {
    ...blockProps
  }, footnotes.map(({
    id,
    content
  }) => /* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */
  (0, _react.createElement)("li", {
    key: id,
    onMouseDown: event => {
      // When clicking on the list item (not on descendants),
      // focus the rich text element since it's only 1px wide when
      // empty.
      if (event.target === event.currentTarget) {
        event.target.firstElementChild.focus();
        event.preventDefault();
      }
    }
  }, (0, _react.createElement)(_blockEditor.RichText, {
    id: id,
    tagName: "span",
    value: content,
    identifier: id
    // To do: figure out why the browser is not scrolling
    // into view when it receives focus.
    ,
    onFocus: event => {
      if (!event.target.textContent.trim()) {
        event.target.scrollIntoView();
      }
    },
    onChange: nextFootnote => {
      updateMeta({
        ...meta,
        footnotes: JSON.stringify(footnotes.map(footnote => {
          return footnote.id === id ? {
            content: nextFootnote,
            id
          } : footnote;
        }))
      });
    }
  }), ' ', (0, _react.createElement)("a", {
    href: `#${id}-link`
  }, "\u21A9\uFE0E"))));
}
//# sourceMappingURL=edit.js.map