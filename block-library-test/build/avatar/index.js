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
  name: "core/avatar",
  title: "Avatar",
  category: "theme",
  description: "Add a user\u2019s avatar.",
  textdomain: "default",
  attributes: {
    userId: {
      type: "number"
    },
    size: {
      type: "number",
      "default": 96
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
  usesContext: ["postType", "postId", "commentId"],
  supports: {
    html: false,
    align: true,
    alignWide: false,
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
    __experimentalBorder: {
      __experimentalSkipSerialization: true,
      radius: true,
      width: true,
      color: true,
      style: true,
      __experimentalDefaultControls: {
        radius: true
      }
    },
    color: {
      text: false,
      background: false,
      __experimentalDuotone: "img"
    }
  },
  selectors: {
    border: ".wp-block-avatar img"
  },
  editorStyle: "wp-block-avatar-editor",
  style: "wp-block-avatar"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.commentAuthorAvatar,
  edit: _edit.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map