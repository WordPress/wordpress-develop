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
  name: "core/verse",
  title: "Verse",
  category: "text",
  description: "Insert poetry. Use special spacing formats. Or quote song lyrics.",
  keywords: ["poetry", "poem"],
  textdomain: "default",
  attributes: {
    content: {
      type: "rich-text",
      source: "rich-text",
      selector: "pre",
      __unstablePreserveWhiteSpace: true,
      __experimentalRole: "content"
    },
    textAlign: {
      type: "string"
    }
  },
  supports: {
    anchor: true,
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    typography: {
      fontSize: true,
      __experimentalFontFamily: true,
      lineHeight: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
    __experimentalBorder: {
      radius: true,
      width: true,
      color: true,
      style: true
    }
  },
  style: "wp-block-verse",
  editorStyle: "wp-block-verse-editor"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.verse,
  example: {
    attributes: {
      /* eslint-disable @wordpress/i18n-no-collapsible-whitespace */
      // translators: Sample content for the Verse block. Can be replaced with a more locale-adequate work.
      content: (0, _i18n.__)('WHAT was he doing, the great god Pan,\n	Down in the reeds by the river?\nSpreading ruin and scattering ban,\nSplashing and paddling with hoofs of a goat,\nAnd breaking the golden lilies afloat\n    With the dragon-fly on the river.')
      /* eslint-enable @wordpress/i18n-no-collapsible-whitespace */
    }
  },
  transforms: _transforms.default,
  deprecated: _deprecated.default,
  merge(attributes, attributesToMerge) {
    return {
      content: attributes.content + '\n\n' + attributesToMerge.content
    };
  },
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