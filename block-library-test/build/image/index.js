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
  name: "core/image",
  title: "Image",
  category: "media",
  usesContext: ["allowResize", "imageCrop", "fixedHeight", "pattern/overrides"],
  description: "Insert an image to make a visual statement.",
  keywords: ["img", "photo", "picture"],
  textdomain: "default",
  attributes: {
    url: {
      type: "string",
      source: "attribute",
      selector: "img",
      attribute: "src",
      __experimentalRole: "content"
    },
    alt: {
      type: "string",
      source: "attribute",
      selector: "img",
      attribute: "alt",
      "default": "",
      __experimentalRole: "content"
    },
    caption: {
      type: "rich-text",
      source: "rich-text",
      selector: "figcaption",
      __experimentalRole: "content"
    },
    lightbox: {
      type: "object",
      enabled: {
        type: "boolean"
      }
    },
    title: {
      type: "string",
      source: "attribute",
      selector: "img",
      attribute: "title",
      __experimentalRole: "content"
    },
    href: {
      type: "string",
      source: "attribute",
      selector: "figure > a",
      attribute: "href",
      __experimentalRole: "content"
    },
    rel: {
      type: "string",
      source: "attribute",
      selector: "figure > a",
      attribute: "rel"
    },
    linkClass: {
      type: "string",
      source: "attribute",
      selector: "figure > a",
      attribute: "class"
    },
    id: {
      type: "number",
      __experimentalRole: "content"
    },
    width: {
      type: "string"
    },
    height: {
      type: "string"
    },
    aspectRatio: {
      type: "string"
    },
    scale: {
      type: "string"
    },
    sizeSlug: {
      type: "string"
    },
    linkDestination: {
      type: "string"
    },
    linkTarget: {
      type: "string",
      source: "attribute",
      selector: "figure > a",
      attribute: "target"
    }
  },
  supports: {
    interactivity: true,
    align: ["left", "center", "right", "wide", "full"],
    anchor: true,
    color: {
      text: false,
      background: false
    },
    filter: {
      duotone: true
    },
    __experimentalBorder: {
      color: true,
      radius: true,
      width: true,
      __experimentalSkipSerialization: true,
      __experimentalDefaultControls: {
        color: true,
        radius: true,
        width: true
      }
    }
  },
  selectors: {
    border: ".wp-block-image img, .wp-block-image .wp-block-image__crop-area, .wp-block-image .components-placeholder",
    filter: {
      duotone: ".wp-block-image img, .wp-block-image .components-placeholder"
    }
  },
  styles: [{
    name: "default",
    label: "Default",
    isDefault: true
  }, {
    name: "rounded",
    label: "Rounded"
  }],
  editorStyle: "wp-block-image-editor",
  style: "wp-block-image"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.image,
  example: {
    attributes: {
      sizeSlug: 'large',
      url: 'https://s.w.org/images/core/5.3/MtBlanc1.jpg',
      // translators: Caption accompanying an image of the Mont Blanc, which serves as an example for the Image block.
      caption: (0, _i18n.__)('Mont Blanc appears—still, snowy, and serene.')
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
      const {
        caption,
        alt,
        url
      } = attributes;
      if (!url) {
        return (0, _i18n.__)('Empty');
      }
      if (!alt) {
        return caption || '';
      }

      // This is intended to be read by a screen reader.
      // A period simply means a pause, no need to translate it.
      return alt + (caption ? '. ' + caption : '');
    }
  },
  getEditWrapperProps(attributes) {
    return {
      'data-align': attributes.align
    };
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