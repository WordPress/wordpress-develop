"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _variations = _interopRequireDefault(require("./variations"));
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/post-navigation-link",
  title: "Post Navigation Link",
  category: "theme",
  description: "Displays the next or previous post link that is adjacent to the current post.",
  textdomain: "default",
  attributes: {
    textAlign: {
      type: "string"
    },
    type: {
      type: "string",
      "default": "next"
    },
    label: {
      type: "string"
    },
    showTitle: {
      type: "boolean",
      "default": false
    },
    linkLabel: {
      type: "boolean",
      "default": false
    },
    arrow: {
      type: "string",
      "default": "none"
    },
    taxonomy: {
      type: "string",
      "default": ""
    }
  },
  usesContext: ["postType"],
  supports: {
    reusable: false,
    html: false,
    color: {
      link: true
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
      __experimentalWritingMode: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    }
  },
  style: "wp-block-post-navigation-link"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  edit: _edit.default,
  variations: _variations.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map