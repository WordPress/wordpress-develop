"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _i18n = require("@wordpress/i18n");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _deprecated = _interopRequireDefault(require("./deprecated"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
var _transforms = _interopRequireDefault(require("./transforms"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/heading",
  title: "Heading",
  category: "text",
  description: "Introduce new sections and organize content to help visitors (and search engines) understand the structure of your content.",
  keywords: ["title", "subtitle"],
  textdomain: "default",
  usesContext: ["pattern/overrides"],
  attributes: {
    textAlign: {
      type: "string"
    },
    content: {
      type: "rich-text",
      source: "rich-text",
      selector: "h1,h2,h3,h4,h5,h6",
      __experimentalRole: "content"
    },
    level: {
      type: "number",
      "default": 2
    },
    placeholder: {
      type: "string"
    }
  },
  supports: {
    align: ["wide", "full"],
    anchor: true,
    className: true,
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalWritingMode: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    __unstablePasteTextInline: true,
    __experimentalSlashInserter: true
  },
  editorStyle: "wp-block-heading-editor",
  style: "wp-block-heading"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.heading,
  example: {
    attributes: {
      content: (0, _i18n.__)('Code is Poetry'),
      level: 2
    }
  },
  __experimentalLabel(attributes, {
    context
  }) {
    const {
      content,
      level
    } = attributes;
    const customName = attributes?.metadata?.name;

    // In the list view, use the block's content as the label.
    // If the content is empty, fall back to the default label.
    if (context === 'list-view' && (customName || content)) {
      return attributes?.metadata?.name || content;
    }
    if (context === 'accessibility') {
      return !content || content.length === 0 ? (0, _i18n.sprintf)( /* translators: accessibility text. %s: heading level. */
      (0, _i18n.__)('Level %s. Empty.'), level) : (0, _i18n.sprintf)( /* translators: accessibility text. 1: heading level. 2: heading content. */
      (0, _i18n.__)('Level %1$s. %2$s'), level, content);
    }
  },
  transforms: _transforms.default,
  deprecated: _deprecated.default,
  merge(attributes, attributesToMerge) {
    return {
      content: (attributes.content || '') + (attributesToMerge.content || '')
    };
  },
  edit: _edit.default,
  save: _save.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map