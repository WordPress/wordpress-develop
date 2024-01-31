"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _transforms = _interopRequireDefault(require("./transforms"));
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
  name: "core/tag-cloud",
  title: "Tag Cloud",
  category: "widgets",
  description: "A cloud of your most used tags.",
  textdomain: "default",
  attributes: {
    numberOfTags: {
      type: "number",
      "default": 45,
      minimum: 1,
      maximum: 100
    },
    taxonomy: {
      type: "string",
      "default": "post_tag"
    },
    showTagCounts: {
      type: "boolean",
      "default": false
    },
    smallestFontSize: {
      type: "string",
      "default": "8pt"
    },
    largestFontSize: {
      type: "string",
      "default": "22pt"
    }
  },
  styles: [{
    name: "default",
    label: "Default",
    isDefault: true
  }, {
    name: "outline",
    label: "Outline"
  }],
  supports: {
    html: false,
    align: true,
    spacing: {
      margin: true,
      padding: true
    },
    typography: {
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true,
      __experimentalLetterSpacing: true
    }
  },
  editorStyle: "wp-block-tag-cloud-editor"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.tag,
  example: {},
  edit: _edit.default,
  transforms: _transforms.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map