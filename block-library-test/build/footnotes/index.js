"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _richText = require("@wordpress/rich-text");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _format = require("./format");
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/footnotes",
  title: "Footnotes",
  category: "text",
  description: "Display footnotes added to the page.",
  keywords: ["references"],
  textdomain: "default",
  usesContext: ["postId", "postType"],
  supports: {
    __experimentalBorder: {
      radius: true,
      color: true,
      width: true,
      style: true,
      __experimentalDefaultControls: {
        radius: false,
        color: false,
        width: false,
        style: false
      }
    },
    color: {
      background: true,
      link: true,
      text: true,
      __experimentalDefaultControls: {
        link: true,
        text: true
      }
    },
    html: false,
    multiple: false,
    reusable: false,
    inserter: false,
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalTextDecoration: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalWritingMode: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    }
  },
  style: "wp-block-footnotes"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.formatListNumbered,
  edit: _edit.default
};
(0, _richText.registerFormatType)(_format.formatName, _format.format);
const init = () => {
  (0, _initBlock.default)({
    name,
    metadata,
    settings
  });
};
exports.init = init;
//# sourceMappingURL=index.js.map