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
  name: "core/post-author",
  title: "Author",
  category: "theme",
  description: "Display post author details such as name, avatar, and bio.",
  textdomain: "default",
  attributes: {
    textAlign: {
      type: "string"
    },
    avatarSize: {
      type: "number",
      "default": 48
    },
    showAvatar: {
      type: "boolean",
      "default": true
    },
    showBio: {
      type: "boolean"
    },
    byline: {
      type: "string"
    },
    isLink: {
      type: "boolean",
      "default": false
    },
    linkTarget: {
      type: "string",
      "default": "_self"
    }
  },
  usesContext: ["postType", "postId", "queryId"],
  supports: {
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
    },
    color: {
      gradients: true,
      link: true,
      __experimentalDuotone: ".wp-block-post-author__avatar img",
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    }
  },
  style: "wp-block-post-author"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.postAuthor,
  edit: _edit.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map