"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _i18n = require("@wordpress/i18n");
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
  name: "core/list",
  title: "List",
  category: "text",
  allowedBlocks: ["core/list-item"],
  description: "Create a bulleted or numbered list.",
  keywords: ["bullet list", "ordered list", "numbered list"],
  textdomain: "default",
  attributes: {
    ordered: {
      type: "boolean",
      "default": false,
      __experimentalRole: "content"
    },
    values: {
      type: "string",
      source: "html",
      selector: "ol,ul",
      multiline: "li",
      __unstableMultilineWrapperTags: ["ol", "ul"],
      "default": "",
      __experimentalRole: "content"
    },
    type: {
      type: "string"
    },
    start: {
      type: "number"
    },
    reversed: {
      type: "boolean"
    },
    placeholder: {
      type: "string"
    }
  },
  supports: {
    anchor: true,
    className: false,
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
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
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
    __unstablePasteTextInline: true,
    __experimentalSelector: "ol,ul",
    __experimentalOnMerge: true,
    __experimentalSlashInserter: true
  },
  editorStyle: "wp-block-list-editor",
  style: "wp-block-list"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.list,
  example: {
    innerBlocks: [{
      name: 'core/list-item',
      attributes: {
        content: (0, _i18n.__)('Alice.')
      }
    }, {
      name: 'core/list-item',
      attributes: {
        content: (0, _i18n.__)('The White Rabbit.')
      }
    }, {
      name: 'core/list-item',
      attributes: {
        content: (0, _i18n.__)('The Cheshire Cat.')
      }
    }, {
      name: 'core/list-item',
      attributes: {
        content: (0, _i18n.__)('The Mad Hatter.')
      }
    }, {
      name: 'core/list-item',
      attributes: {
        content: (0, _i18n.__)('The Queen of Hearts.')
      }
    }]
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