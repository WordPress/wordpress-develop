"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  __experimental: true,
  name: "core/table-of-contents",
  title: "Table of Contents",
  category: "layout",
  description: "Summarize your post with a list of headings. Add HTML anchors to Heading blocks to link them here.",
  keywords: ["document outline", "summary"],
  textdomain: "default",
  attributes: {
    headings: {
      type: "array",
      items: {
        type: "object"
      },
      "default": []
    },
    onlyIncludeCurrentPage: {
      type: "boolean",
      "default": false
    }
  },
  supports: {
    html: false,
    color: {
      text: true,
      background: true,
      gradients: true,
      link: true
    },
    spacing: {
      margin: true,
      padding: true
    },
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
    }
  },
  example: {}
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.tableOfContents,
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