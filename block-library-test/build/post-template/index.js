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
  name: "core/post-template",
  title: "Post Template",
  category: "theme",
  parent: ["core/query"],
  description: "Contains the block elements used to render a post, like the title, date, featured image, content or excerpt, and more.",
  textdomain: "default",
  usesContext: ["queryId", "query", "displayLayout", "templateSlug", "previewPostType", "enhancedPagination"],
  supports: {
    reusable: false,
    html: false,
    align: ["wide", "full"],
    layout: true,
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
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
    },
    spacing: {
      blockGap: {
        __experimentalDefault: "1.25em"
      },
      __experimentalDefaultControls: {
        blockGap: true
      }
    }
  },
  style: "wp-block-post-template",
  editorStyle: "wp-block-post-template-editor"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.layout,
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