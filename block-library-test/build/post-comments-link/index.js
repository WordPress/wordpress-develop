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
  name: "core/post-comments-link",
  title: "Comments Link",
  category: "theme",
  description: "Displays the link to the current post comments.",
  textdomain: "default",
  usesContext: ["postType", "postId"],
  attributes: {
    textAlign: {
      type: "string"
    }
  },
  supports: {
    html: false,
    color: {
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
    }
  }
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  edit: _edit.default,
  icon: _icons.postCommentsCount
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map