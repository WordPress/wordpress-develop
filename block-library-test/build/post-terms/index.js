"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _hooks = require("@wordpress/hooks");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _hooks2 = _interopRequireDefault(require("./hooks"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/post-terms",
  title: "Post Terms",
  category: "theme",
  description: "Post terms.",
  textdomain: "default",
  attributes: {
    term: {
      type: "string"
    },
    textAlign: {
      type: "string"
    },
    separator: {
      type: "string",
      "default": ", "
    },
    prefix: {
      type: "string",
      "default": ""
    },
    suffix: {
      type: "string",
      "default": ""
    }
  },
  usesContext: ["postId", "postType"],
  supports: {
    html: false,
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true,
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
  },
  style: "wp-block-post-terms"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.postCategories,
  edit: _edit.default
};
const init = () => {
  (0, _hooks.addFilter)('blocks.registerBlockType', 'core/template-part', _hooks2.default);
  return (0, _initBlock.default)({
    name,
    metadata,
    settings
  });
};
exports.init = init;
//# sourceMappingURL=index.js.map