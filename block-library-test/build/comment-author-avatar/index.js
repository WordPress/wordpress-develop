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
  __experimental: "fse",
  name: "core/comment-author-avatar",
  title: "Comment Author Avatar (deprecated)",
  category: "theme",
  ancestor: ["core/comment-template"],
  description: "This block is deprecated. Please use the Avatar block instead.",
  textdomain: "default",
  attributes: {
    width: {
      type: "number",
      "default": 96
    },
    height: {
      type: "number",
      "default": 96
    }
  },
  usesContext: ["commentId"],
  supports: {
    html: false,
    inserter: false,
    __experimentalBorder: {
      radius: true,
      width: true,
      color: true,
      style: true
    },
    color: {
      background: true,
      text: false,
      __experimentalDefaultControls: {
        background: true
      }
    },
    spacing: {
      __experimentalSkipSerialization: true,
      margin: true,
      padding: true
    }
  }
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