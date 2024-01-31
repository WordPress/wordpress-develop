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
  name: "core/read-more",
  title: "Read More",
  category: "theme",
  description: "Displays the link of a post, page, or any other content-type.",
  textdomain: "default",
  attributes: {
    content: {
      type: "string"
    },
    linkTarget: {
      type: "string",
      "default": "_self"
    }
  },
  usesContext: ["postId"],
  supports: {
    html: false,
    color: {
      gradients: true,
      text: true
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true,
      __experimentalLetterSpacing: true,
      __experimentalTextDecoration: true,
      __experimentalDefaultControls: {
        fontSize: true,
        textDecoration: true
      }
    },
    spacing: {
      margin: ["top", "bottom"],
      padding: true,
      __experimentalDefaultControls: {
        padding: true
      }
    },
    __experimentalBorder: {
      color: true,
      radius: true,
      width: true,
      __experimentalDefaultControls: {
        width: true
      }
    }
  },
  style: "wp-block-read-more"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.link,
  edit: _edit.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map