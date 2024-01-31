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
  name: "core/file",
  title: "File",
  category: "media",
  description: "Add a link to a downloadable file.",
  keywords: ["document", "pdf", "download"],
  textdomain: "default",
  attributes: {
    id: {
      type: "number"
    },
    href: {
      type: "string"
    },
    fileId: {
      type: "string",
      source: "attribute",
      selector: "a:not([download])",
      attribute: "id"
    },
    fileName: {
      type: "rich-text",
      source: "rich-text",
      selector: "a:not([download])"
    },
    textLinkHref: {
      type: "string",
      source: "attribute",
      selector: "a:not([download])",
      attribute: "href"
    },
    textLinkTarget: {
      type: "string",
      source: "attribute",
      selector: "a:not([download])",
      attribute: "target"
    },
    showDownloadButton: {
      type: "boolean",
      "default": true
    },
    downloadButtonText: {
      type: "rich-text",
      source: "rich-text",
      selector: "a[download]"
    },
    displayPreview: {
      type: "boolean"
    },
    previewHeight: {
      type: "number",
      "default": 600
    }
  },
  supports: {
    anchor: true,
    align: true,
    spacing: {
      margin: true,
      padding: true
    },
    color: {
      gradients: true,
      link: true,
      text: false,
      __experimentalDefaultControls: {
        background: true,
        link: true
      }
    },
    interactivity: true
  },
  editorStyle: "wp-block-file-editor",
  style: "wp-block-file"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.file,
  example: {
    attributes: {
      href: 'https://upload.wikimedia.org/wikipedia/commons/d/dd/Armstrong_Small_Step.ogg',
      fileName: (0, _i18n._x)('Armstrong_Small_Step', 'Name of the file')
    }
  },
  transforms: _transforms.default,
  deprecated: _deprecated.default,
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