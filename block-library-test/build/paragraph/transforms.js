"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const {
  name: name
} = {
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
const transforms = {
  from: [{
    type: 'raw',
    // Paragraph is a fallback and should be matched last.
    priority: 20,
    selector: 'p',
    schema: ({
      phrasingContentSchema,
      isPaste
    }) => ({
      p: {
        children: phrasingContentSchema,
        attributes: isPaste ? [] : ['style', 'id']
      }
    }),
    transform(node) {
      const attributes = (0, _blocks.getBlockAttributes)(name, node.outerHTML);
      const {
        textAlign
      } = node.style || {};
      if (textAlign === 'left' || textAlign === 'center' || textAlign === 'right') {
        attributes.align = textAlign;
      }
      return (0, _blocks.createBlock)(name, attributes);
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map