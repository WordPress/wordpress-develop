"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
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
  name: "core/comments-pagination",
  title: "Comments Pagination",
  category: "theme",
  parent: ["core/comments"],
  allowedBlocks: ["core/comments-pagination-previous", "core/comments-pagination-numbers", "core/comments-pagination-next"],
  description: "Displays a paginated navigation to next/previous set of comments, when applicable.",
  textdomain: "default",
  attributes: {
    paginationArrow: {
      type: "string",
      "default": "none"
    }
  },
  providesContext: {
    "comments/paginationArrow": "paginationArrow"
  },
  supports: {
    align: true,
    reusable: false,
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
    layout: {
      allowSwitching: false,
      allowInheriting: false,
      "default": {
        type: "flex"
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
    }
  },
  editorStyle: "wp-block-comments-pagination-editor",
  style: "wp-block-comments-pagination"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.queryPagination,
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