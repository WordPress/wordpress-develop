"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
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
  name: "core/code",
  title: "Code",
  category: "text",
  description: "Display code snippets that respect your spacing and tabs.",
  textdomain: "default",
  attributes: {
    content: {
      type: "rich-text",
      source: "rich-text",
      selector: "code",
      __unstablePreserveWhiteSpace: true
    }
  },
  supports: {
    align: ["wide"],
    anchor: true,
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
    spacing: {
      margin: ["top", "bottom"],
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
    __experimentalBorder: {
      radius: true,
      color: true,
      width: true,
      style: true,
      __experimentalDefaultControls: {
        width: true,
        color: true
      }
    },
    color: {
      text: true,
      background: true,
      gradients: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    }
  },
  style: "wp-block-code"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.code,
  example: {
    attributes: {
      /* eslint-disable @wordpress/i18n-no-collapsible-whitespace */
      // translators: Preserve \n markers for line breaks
      content: (0, _i18n.__)('// A “block” is the abstract term used\n// to describe units of markup that\n// when composed together, form the\n// content or layout of a page.\nregisterBlockType( name, settings );')
      /* eslint-enable @wordpress/i18n-no-collapsible-whitespace */
    }
  },
  merge(attributes, attributesToMerge) {
    return {
      content: attributes.content + '\n\n' + attributesToMerge.content
    };
  },
  transforms: _transforms.default,
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