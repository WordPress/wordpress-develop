"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _deprecated = _interopRequireDefault(require("./deprecated"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
var _transforms = _interopRequireDefault(require("./transforms"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/pullquote",
  title: "Pullquote",
  category: "text",
  description: "Give special visual emphasis to a quote from your text.",
  textdomain: "default",
  attributes: {
    value: {
      type: "rich-text",
      source: "rich-text",
      selector: "p",
      __experimentalRole: "content"
    },
    citation: {
      type: "rich-text",
      source: "rich-text",
      selector: "cite",
      __experimentalRole: "content"
    },
    textAlign: {
      type: "string"
    }
  },
  supports: {
    anchor: true,
    align: ["left", "right", "wide", "full"],
    color: {
      gradients: true,
      background: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
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
    __experimentalBorder: {
      color: true,
      radius: true,
      style: true,
      width: true,
      __experimentalDefaultControls: {
        color: true,
        radius: true,
        style: true,
        width: true
      }
    },
    __experimentalStyle: {
      typography: {
        fontSize: "1.5em",
        lineHeight: "1.6"
      }
    }
  },
  editorStyle: "wp-block-pullquote-editor",
  style: "wp-block-pullquote"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.pullquote,
  example: {
    attributes: {
      value:
      // translators: Quote serving as example for the Pullquote block. Attributed to Matt Mullenweg.
      (0, _i18n.__)('One of the hardest things to do in technology is disrupt yourself.'),
      citation: (0, _i18n.__)('Matt Mullenweg')
    }
  },
  transforms: _transforms.default,
  edit: _edit.default,
  save: _save.default,
  deprecated: _deprecated.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map