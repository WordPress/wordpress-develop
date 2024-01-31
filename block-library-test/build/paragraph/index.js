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
  name: "core/paragraph",
  title: "Paragraph",
  category: "text",
  description: "Start with the basic building block of all narrative.",
  keywords: ["text"],
  textdomain: "default",
  usesContext: ["postId", "pattern/overrides"],
  attributes: {
    align: {
      type: "string"
    },
    content: {
      type: "rich-text",
      source: "rich-text",
      selector: "p",
      __experimentalRole: "content"
    },
    dropCap: {
      type: "boolean",
      "default": false
    },
    placeholder: {
      type: "string"
    },
    direction: {
      type: "string",
      "enum": ["ltr", "rtl"]
    }
  },
  supports: {
    anchor: true,
    className: false,
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
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalTextDecoration: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalWritingMode: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    __experimentalSelector: "p",
    __unstablePasteTextInline: true
  },
  editorStyle: "wp-block-paragraph-editor",
  style: "wp-block-paragraph"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.paragraph,
  example: {
    attributes: {
      content: (0, _i18n.__)('In a village of La Mancha, the name of which I have no desire to call to mind, there lived not long since one of those gentlemen that keep a lance in the lance-rack, an old buckler, a lean hack, and a greyhound for coursing.')
    }
  },
  __experimentalLabel(attributes, {
    context
  }) {
    const customName = attributes?.metadata?.name;
    if (context === 'list-view' && customName) {
      return customName;
    }
    if (context === 'accessibility') {
      if (customName) {
        return customName;
      }
      const {
        content
      } = attributes;
      return !content || content.length === 0 ? (0, _i18n.__)('Empty') : content;
    }
  },
  transforms: _transforms.default,
  deprecated: _deprecated.default,
  merge(attributes, attributesToMerge) {
    return {
      content: (attributes.content || '') + (attributesToMerge.content || '')
    };
  },
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