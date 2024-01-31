"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _deprecated = _interopRequireDefault(require("./deprecated"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/comments-title",
  title: "Comments Title",
  category: "theme",
  ancestor: ["core/comments"],
  description: "Displays a title with the number of comments.",
  textdomain: "default",
  usesContext: ["postId", "postType"],
  attributes: {
    textAlign: {
      type: "string"
    },
    showPostTitle: {
      type: "boolean",
      "default": true
    },
    showCommentsCount: {
      type: "boolean",
      "default": true
    },
    level: {
      type: "number",
      "default": 2
    }
  },
  supports: {
    anchor: false,
    align: true,
    html: false,
    __experimentalBorder: {
      radius: true,
      color: true,
      width: true,
      style: true
    },
    color: {
      gradients: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
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
        fontSize: true,
        __experimentalFontFamily: true,
        __experimentalFontStyle: true,
        __experimentalFontWeight: true
      }
    }
  }
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.title,
  edit: _edit.default,
  deprecated: _deprecated.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map