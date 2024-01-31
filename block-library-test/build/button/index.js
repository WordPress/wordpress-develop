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
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/button",
  title: "Button",
  category: "design",
  parent: ["core/buttons"],
  description: "Prompt visitors to take action with a button-style link.",
  keywords: ["link"],
  textdomain: "default",
  usesContext: ["pattern/overrides"],
  attributes: {
    tagName: {
      type: "string",
      "enum": ["a", "button"],
      "default": "a"
    },
    type: {
      type: "string",
      "default": "button"
    },
    textAlign: {
      type: "string"
    },
    url: {
      type: "string",
      source: "attribute",
      selector: "a",
      attribute: "href",
      __experimentalRole: "content"
    },
    title: {
      type: "string",
      source: "attribute",
      selector: "a,button",
      attribute: "title",
      __experimentalRole: "content"
    },
    text: {
      type: "rich-text",
      source: "rich-text",
      selector: "a,button",
      __experimentalRole: "content"
    },
    linkTarget: {
      type: "string",
      source: "attribute",
      selector: "a",
      attribute: "target",
      __experimentalRole: "content"
    },
    rel: {
      type: "string",
      source: "attribute",
      selector: "a",
      attribute: "rel",
      __experimentalRole: "content"
    },
    placeholder: {
      type: "string"
    },
    backgroundColor: {
      type: "string"
    },
    textColor: {
      type: "string"
    },
    gradient: {
      type: "string"
    },
    width: {
      type: "number"
    }
  },
  supports: {
    anchor: true,
    align: false,
    alignWide: false,
    color: {
      __experimentalSkipSerialization: true,
      gradients: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
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
    },
    reusable: false,
    shadow: true,
    spacing: {
      __experimentalSkipSerialization: true,
      padding: ["horizontal", "vertical"],
      __experimentalDefaultControls: {
        padding: true
      }
    },
    __experimentalBorder: {
      color: true,
      radius: true,
      style: true,
      width: true,
      __experimentalSkipSerialization: true,
      __experimentalDefaultControls: {
        color: true,
        radius: true,
        style: true,
        width: true
      }
    },
    __experimentalSelector: ".wp-block-button .wp-block-button__link"
  },
  styles: [{
    name: "fill",
    label: "Fill",
    isDefault: true
  }, {
    name: "outline",
    label: "Outline"
  }],
  editorStyle: "wp-block-button-editor",
  style: "wp-block-button"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.button,
  example: {
    attributes: {
      className: 'is-style-fill',
      text: (0, _i18n.__)('Call to Action')
    }
  },
  edit: _edit.default,
  save: _save.default,
  deprecated: _deprecated.default,
  merge: (a, {
    text = ''
  }) => ({
    ...a,
    text: (a.text || '') + text
  })
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map