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
  name: "core/comment-author-name",
  title: "Comment Author Name",
  category: "theme",
  ancestor: ["core/comment-template"],
  description: "Displays the name of the author of the comment.",
  textdomain: "default",
  attributes: {
    isLink: {
      type: "boolean",
      "default": true
    },
    linkTarget: {
      type: "string",
      "default": "_self"
    },
    textAlign: {
      type: "string"
    }
  },
  usesContext: ["commentId"],
  supports: {
    html: false,
    spacing: {
      margin: true,
      padding: true
    },
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true,
        link: true
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
    }
  }
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.commentAuthorName,
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