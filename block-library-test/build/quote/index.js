"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
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
  name: "core/quote",
  title: "Quote",
  category: "text",
  description: "Give quoted text visual emphasis. \"In quoting others, we cite ourselves.\" \u2014 Julio Cort\xE1zar",
  keywords: ["blockquote", "cite"],
  textdomain: "default",
  attributes: {
    value: {
      type: "string",
      source: "html",
      selector: "blockquote",
      multiline: "p",
      "default": "",
      __experimentalRole: "content"
    },
    citation: {
      type: "rich-text",
      source: "rich-text",
      selector: "cite",
      __experimentalRole: "content"
    },
    align: {
      type: "string"
    }
  },
  supports: {
    anchor: true,
    html: false,
    __experimentalOnEnter: true,
    __experimentalOnMerge: true,
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalLetterSpacing: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    color: {
      gradients: true,
      heading: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    layout: {
      allowEditing: false
    },
    spacing: {
      blockGap: true
    }
  },
  styles: [{
    name: "default",
    label: "Default",
    isDefault: true
  }, {
    name: "plain",
    label: "Plain"
  }],
  editorStyle: "wp-block-quote-editor",
  style: "wp-block-quote"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.quote,
  example: {
    attributes: {
      citation: 'Julio Cortázar'
    },
    innerBlocks: [{
      name: 'core/paragraph',
      attributes: {
        content: (0, _i18n.__)('In quoting others, we cite ourselves.')
      }
    }]
  },
  transforms: _transforms.default,
  edit: _edit.default,
  save: _save.default,
  deprecated: _deprecated.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map