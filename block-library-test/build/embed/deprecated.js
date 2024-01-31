"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */
/**
 * Internal dependencies
 */
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/embed",
  title: "Embed",
  category: "embed",
  description: "Add a block that displays content pulled from other sites, like Twitter or YouTube.",
  textdomain: "default",
  attributes: {
    url: {
      type: "string",
      __experimentalRole: "content"
    },
    caption: {
      type: "rich-text",
      source: "rich-text",
      selector: "figcaption",
      __experimentalRole: "content"
    },
    type: {
      type: "string",
      __experimentalRole: "content"
    },
    providerNameSlug: {
      type: "string",
      __experimentalRole: "content"
    },
    allowResponsive: {
      type: "boolean",
      "default": true
    },
    responsive: {
      type: "boolean",
      "default": false,
      __experimentalRole: "content"
    },
    previewable: {
      type: "boolean",
      "default": true,
      __experimentalRole: "content"
    }
  },
  supports: {
    align: true,
    spacing: {
      margin: true
    }
  },
  editorStyle: "wp-block-embed-editor",
  style: "wp-block-embed"
};
/**
 * WordPress dependencies
 */
const {
  attributes: blockAttributes
} = metadata;

// In #41140 support was added to global styles for caption elements which added a `wp-element-caption` classname
// to the embed figcaption element.
const v2 = {
  attributes: blockAttributes,
  save({
    attributes
  }) {
    const {
      url,
      caption,
      type,
      providerNameSlug
    } = attributes;
    if (!url) {
      return null;
    }
    const className = (0, _classnames.default)('wp-block-embed', {
      [`is-type-${type}`]: type,
      [`is-provider-${providerNameSlug}`]: providerNameSlug,
      [`wp-block-embed-${providerNameSlug}`]: providerNameSlug
    });
    return (0, _react.createElement)("figure", {
      ..._blockEditor.useBlockProps.save({
        className
      })
    }, (0, _react.createElement)("div", {
      className: "wp-block-embed__wrapper"
    }, `\n${url}\n` /* URL needs to be on its own line. */), !_blockEditor.RichText.isEmpty(caption) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "figcaption",
      value: caption
    }));
  }
};
const v1 = {
  attributes: blockAttributes,
  save({
    attributes: {
      url,
      caption,
      type,
      providerNameSlug
    }
  }) {
    if (!url) {
      return null;
    }
    const embedClassName = (0, _classnames.default)('wp-block-embed', {
      [`is-type-${type}`]: type,
      [`is-provider-${providerNameSlug}`]: providerNameSlug
    });
    return (0, _react.createElement)("figure", {
      className: embedClassName
    }, `\n${url}\n` /* URL needs to be on its own line. */, !_blockEditor.RichText.isEmpty(caption) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "figcaption",
      value: caption
    }));
  }
};
const deprecated = [v2, v1];
var _default = exports.default = deprecated;
//# sourceMappingURL=deprecated.js.map