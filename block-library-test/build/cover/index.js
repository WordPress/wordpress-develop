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
var _variations = _interopRequireDefault(require("./variations"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/cover",
  title: "Cover",
  category: "media",
  description: "Add an image or video with a text overlay.",
  textdomain: "default",
  attributes: {
    url: {
      type: "string"
    },
    useFeaturedImage: {
      type: "boolean",
      "default": false
    },
    id: {
      type: "number"
    },
    alt: {
      type: "string",
      "default": ""
    },
    hasParallax: {
      type: "boolean",
      "default": false
    },
    isRepeated: {
      type: "boolean",
      "default": false
    },
    dimRatio: {
      type: "number",
      "default": 100
    },
    overlayColor: {
      type: "string"
    },
    customOverlayColor: {
      type: "string"
    },
    isUserOverlayColor: {
      type: "boolean"
    },
    backgroundType: {
      type: "string",
      "default": "image"
    },
    focalPoint: {
      type: "object"
    },
    minHeight: {
      type: "number"
    },
    minHeightUnit: {
      type: "string"
    },
    gradient: {
      type: "string"
    },
    customGradient: {
      type: "string"
    },
    contentPosition: {
      type: "string"
    },
    isDark: {
      type: "boolean",
      "default": true
    },
    allowedBlocks: {
      type: "array"
    },
    templateLock: {
      type: ["string", "boolean"],
      "enum": ["all", "insert", "contentOnly", false]
    },
    tagName: {
      type: "string",
      "default": "div"
    }
  },
  usesContext: ["postId", "postType"],
  supports: {
    anchor: true,
    align: true,
    html: false,
    spacing: {
      padding: true,
      margin: ["top", "bottom"],
      blockGap: true,
      __experimentalDefaultControls: {
        padding: true,
        blockGap: true
      }
    },
    __experimentalBorder: {
      color: true,
      radius: true,
      style: true,
      width: true,
      __experimentalDefaultControls: {
        color: true,
        radius: true,
        style: true,
        width: true
      }
    },
    color: {
      __experimentalDuotone: "> .wp-block-cover__image-background, > .wp-block-cover__video-background",
      heading: true,
      text: true,
      background: false,
      __experimentalSkipSerialization: ["gradients"],
      enableContrastChecker: false
    },
    dimensions: {
      aspectRatio: true
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
    },
    layout: {
      allowJustification: false
    }
  },
  editorStyle: "wp-block-cover-editor",
  style: "wp-block-cover"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.cover,
  example: {
    attributes: {
      customOverlayColor: '#065174',
      dimRatio: 40,
      url: 'https://s.w.org/images/core/5.3/Windbuchencom.jpg'
    },
    innerBlocks: [{
      name: 'core/paragraph',
      attributes: {
        content: (0, _i18n.__)('<strong>Snow Patrol</strong>'),
        align: 'center',
        style: {
          typography: {
            fontSize: 48
          },
          color: {
            text: 'white'
          }
        }
      }
    }]
  },
  transforms: _transforms.default,
  save: _save.default,
  edit: _edit.default,
  deprecated: _deprecated.default,
  variations: _variations.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map