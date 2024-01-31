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
var _variations = _interopRequireDefault(require("./variations"));
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
  name: "core/query",
  title: "Query Loop",
  category: "theme",
  description: "An advanced block that allows displaying post types based on different query parameters and visual configurations.",
  textdomain: "default",
  attributes: {
    queryId: {
      type: "number"
    },
    query: {
      type: "object",
      "default": {
        perPage: null,
        pages: 0,
        offset: 0,
        postType: "post",
        order: "desc",
        orderBy: "date",
        author: "",
        search: "",
        exclude: [],
        sticky: "",
        inherit: true,
        taxQuery: null,
        parents: []
      }
    },
    tagName: {
      type: "string",
      "default": "div"
    },
    namespace: {
      type: "string"
    },
    enhancedPagination: {
      type: "boolean",
      "default": false
    }
  },
  providesContext: {
    queryId: "queryId",
    query: "query",
    displayLayout: "displayLayout",
    enhancedPagination: "enhancedPagination"
  },
  supports: {
    interactivity: true,
    align: ["wide", "full"],
    html: false,
    layout: true
  },
  editorStyle: "wp-block-query-editor",
  style: "wp-block-query"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.loop,
  edit: _edit.default,
  save: _save.default,
  variations: _variations.default,
  deprecated: _deprecated.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map