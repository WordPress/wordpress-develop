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
  name: "core/comment-reply-link",
  title: "Comment Reply Link",
  category: "theme",
  ancestor: ["core/comment-template"],
  description: "Displays a link to reply to a comment.",
  textdomain: "default",
  usesContext: ["commentId"],
  attributes: {
    textAlign: {
      type: "string"
    }
  },
  supports: {
    color: {
      gradients: true,
      link: true,
      text: false,
      __experimentalDefaultControls: {
        background: true,
        link: true
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
        fontSize: true
      }
    },
    html: false
  }
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  edit: _edit.default,
  icon: _icons.commentReplyLink
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map