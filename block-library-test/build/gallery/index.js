"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _deprecated = _interopRequireDefault(require("./deprecated"));
var _editWrapper = _interopRequireDefault(require("./edit-wrapper"));
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
  name: "core/gallery",
  title: "Gallery",
  category: "media",
  allowedBlocks: ["core/image"],
  description: "Display multiple images in a rich gallery.",
  keywords: ["images", "photos"],
  textdomain: "default",
  attributes: {
    images: {
      type: "array",
      "default": [],
      source: "query",
      selector: ".blocks-gallery-item",
      query: {
        url: {
          type: "string",
          source: "attribute",
          selector: "img",
          attribute: "src"
        },
        fullUrl: {
          type: "string",
          source: "attribute",
          selector: "img",
          attribute: "data-full-url"
        },
        link: {
          type: "string",
          source: "attribute",
          selector: "img",
          attribute: "data-link"
        },
        alt: {
          type: "string",
          source: "attribute",
          selector: "img",
          attribute: "alt",
          "default": ""
        },
        id: {
          type: "string",
          source: "attribute",
          selector: "img",
          attribute: "data-id"
        },
        caption: {
          type: "rich-text",
          source: "rich-text",
          selector: ".blocks-gallery-item__caption"
        }
      }
    },
    ids: {
      type: "array",
      items: {
        type: "number"
      },
      "default": []
    },
    shortCodeTransforms: {
      type: "array",
      items: {
        type: "object"
      },
      "default": []
    },
    columns: {
      type: "number",
      minimum: 1,
      maximum: 8
    },
    caption: {
      type: "rich-text",
      source: "rich-text",
      selector: ".blocks-gallery-caption"
    },
    imageCrop: {
      type: "boolean",
      "default": true
    },
    randomOrder: {
      type: "boolean",
      "default": false
    },
    fixedHeight: {
      type: "boolean",
      "default": true
    },
    linkTarget: {
      type: "string"
    },
    linkTo: {
      type: "string"
    },
    sizeSlug: {
      type: "string",
      "default": "large"
    },
    allowResize: {
      type: "boolean",
      "default": false
    }
  },
  providesContext: {
    allowResize: "allowResize",
    imageCrop: "imageCrop",
    fixedHeight: "fixedHeight"
  },
  supports: {
    anchor: true,
    align: true,
    html: false,
    units: ["px", "em", "rem", "vh", "vw"],
    spacing: {
      margin: true,
      padding: true,
      blockGap: ["horizontal", "vertical"],
      __experimentalSkipSerialization: ["blockGap"],
      __experimentalDefaultControls: {
        blockGap: true,
        margin: false,
        padding: false
      }
    },
    color: {
      text: false,
      background: true,
      gradients: true
    },
    layout: {
      allowSwitching: false,
      allowInheriting: false,
      allowEditing: false,
      "default": {
        type: "flex"
      }
    }
  },
  editorStyle: "wp-block-gallery-editor",
  style: "wp-block-gallery"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.gallery,
  example: {
    attributes: {
      columns: 2
    },
    innerBlocks: [{
      name: 'core/image',
      attributes: {
        url: 'https://s.w.org/images/core/5.3/Glacial_lakes%2C_Bhutan.jpg'
      }
    }, {
      name: 'core/image',
      attributes: {
        url: 'https://s.w.org/images/core/5.3/Sediment_off_the_Yucatan_Peninsula.jpg'
      }
    }]
  },
  transforms: _transforms.default,
  edit: _editWrapper.default,
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