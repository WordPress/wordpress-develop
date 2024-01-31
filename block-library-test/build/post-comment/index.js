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
  __experimental: "fse",
  name: "core/post-comment",
  title: "Comment (deprecated)",
  category: "theme",
  allowedBlocks: ["core/avatar", "core/comment-author-name", "core/comment-content", "core/comment-date", "core/comment-edit-link", "core/comment-reply-link"],
  description: "This block is deprecated. Please use the Comments block instead.",
  textdomain: "default",
  attributes: {
    commentId: {
      type: "number"
    }
  },
  providesContext: {
    commentId: "commentId"
  },
  supports: {
    html: false,
    inserter: false
  }
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.comment,
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