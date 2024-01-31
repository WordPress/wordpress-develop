"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _deprecated = _interopRequireDefault(require("./deprecated"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
var _variations = _interopRequireDefault(require("./variations"));
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  __experimental: true,
  name: "core/form-input",
  title: "Input Field",
  category: "common",
  ancestor: ["core/form"],
  description: "The basic building block for forms.",
  keywords: ["input", "form"],
  textdomain: "default",
  icon: "forms",
  attributes: {
    type: {
      type: "string",
      "default": "text"
    },
    name: {
      type: "string"
    },
    label: {
      type: "rich-text",
      "default": "Label",
      selector: ".wp-block-form-input__label-content",
      source: "rich-text",
      __experimentalRole: "content"
    },
    inlineLabel: {
      type: "boolean",
      "default": false
    },
    required: {
      type: "boolean",
      "default": false,
      selector: ".wp-block-form-input__input",
      source: "attribute",
      attribute: "required"
    },
    placeholder: {
      type: "string",
      selector: ".wp-block-form-input__input",
      source: "attribute",
      attribute: "placeholder",
      __experimentalRole: "content"
    },
    value: {
      type: "string",
      "default": "",
      selector: "input",
      source: "attribute",
      attribute: "value"
    },
    visibilityPermissions: {
      type: "string",
      "default": "all"
    }
  },
  supports: {
    anchor: true,
    reusable: false,
    spacing: {
      margin: ["top", "bottom"]
    },
    __experimentalBorder: {
      radius: true,
      __experimentalSkipSerialization: true,
      __experimentalDefaultControls: {
        radius: true
      }
    }
  },
  style: ["wp-block-form-input"]
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  deprecated: _deprecated.default,
  edit: _edit.default,
  save: _save.default,
  variations: _variations.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map