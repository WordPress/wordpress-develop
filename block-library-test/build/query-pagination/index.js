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
var _deprecated = _interopRequireDefault(require("./deprecated"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/query-pagination",
  title: "Pagination",
  category: "theme",
  parent: ["core/query"],
  allowedBlocks: ["core/query-pagination-previous", "core/query-pagination-numbers", "core/query-pagination-next"],
  description: "Displays a paginated navigation to next/previous set of posts, when applicable.",
  textdomain: "default",
  attributes: {
    paginationArrow: {
      type: "string",
      "default": "none"
    },
    showLabel: {
      type: "boolean",
      "default": true
    }
  },
  usesContext: ["queryId", "query"],
  providesContext: {
    paginationArrow: "paginationArrow",
    showLabel: "showLabel"
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
  editorStyle: "wp-block-query-pagination-editor",
  style: "wp-block-query-pagination"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.queryPagination,
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