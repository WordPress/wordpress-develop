"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _variations = _interopRequireDefault(require("./variations"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/search",
  title: "Search",
  category: "widgets",
  description: "Help visitors find your content.",
  keywords: ["find"],
  textdomain: "default",
  attributes: {
    label: {
      type: "string",
      __experimentalRole: "content"
    },
    showLabel: {
      type: "boolean",
      "default": true
    },
    placeholder: {
      type: "string",
      "default": "",
      __experimentalRole: "content"
    },
    width: {
      type: "number"
    },
    widthUnit: {
      type: "string"
    },
    buttonText: {
      type: "string",
      __experimentalRole: "content"
    },
    buttonPosition: {
      type: "string",
      "default": "button-outside"
    },
    buttonUseIcon: {
      type: "boolean",
      "default": false
    },
    query: {
      type: "object",
      "default": {}
    },
    isSearchFieldHidden: {
      type: "boolean",
      "default": false
    }
  },
  supports: {
    align: ["left", "center", "right"],
    color: {
      gradients: true,
      __experimentalSkipSerialization: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    interactivity: true,
    typography: {
      __experimentalSkipSerialization: true,
      __experimentalSelector: ".wp-block-search__label, .wp-block-search__input, .wp-block-search__button",
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
      width: true,
      __experimentalSkipSerialization: true,
      __experimentalDefaultControls: {
        color: true,
        radius: true,
        width: true
      }
    },
    html: false
  },
  editorStyle: "wp-block-search-editor",
  style: "wp-block-search"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.search,
  example: {
    attributes: {
      buttonText: (0, _i18n.__)('Search'),
      label: (0, _i18n.__)('Search')
    },
    viewportWidth: 400
  },
  variations: _variations.default,
  edit: _edit.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map