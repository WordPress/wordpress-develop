"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/latest-comments",
  title: "Latest Comments",
  category: "widgets",
  description: "Display a list of your most recent comments.",
  keywords: ["recent comments"],
  textdomain: "default",
  attributes: {
    commentsToShow: {
      type: "number",
      "default": 5,
      minimum: 1,
      maximum: 100
    },
    displayAvatar: {
      type: "boolean",
      "default": true
    },
    displayDate: {
      type: "boolean",
      "default": true
    },
    displayExcerpt: {
      type: "boolean",
      "default": true
    }
  },
  supports: {
    align: true,
    html: false,
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
  editorStyle: "wp-block-latest-comments-editor",
  style: "wp-block-latest-comments"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.comment,
  example: {},
  edit: _edit.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map