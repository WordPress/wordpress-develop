"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/post-featured-image",
  title: "Featured Image",
  category: "theme",
  description: "Display a post's featured image.",
  textdomain: "default",
  attributes: {
    isLink: {
      type: "boolean",
      "default": false
    },
    aspectRatio: {
      type: "string"
    },
    width: {
      type: "string"
    },
    height: {
      type: "string"
    },
    scale: {
      type: "string",
      "default": "cover"
    },
    sizeSlug: {
      type: "string"
    },
    rel: {
      type: "string",
      attribute: "rel",
      "default": ""
    },
    linkTarget: {
      type: "string",
      "default": "_self"
    },
    overlayColor: {
      type: "string"
    },
    customOverlayColor: {
      type: "string"
    },
    dimRatio: {
      type: "number",
      "default": 0
    },
    gradient: {
      type: "string"
    },
    customGradient: {
      type: "string"
    },
    useFirstImageFromPost: {
      type: "boolean",
      "default": false
    }
  },
  usesContext: ["postId", "postType", "queryId"],
  supports: {
    align: ["left", "right", "center", "wide", "full"],
    color: {
      __experimentalDuotone: "img, .wp-block-post-featured-image__placeholder, .components-placeholder__illustration, .components-placeholder::before",
      text: false,
      background: false
    },
    __experimentalBorder: {
      color: true,
      radius: true,
      width: true,
      __experimentalSelector: "img, .block-editor-media-placeholder, .wp-block-post-featured-image__overlay",
      __experimentalSkipSerialization: true,
      __experimentalDefaultControls: {
        color: true,
        radius: true,
        width: true
      }
    },
    html: false,
    spacing: {
      margin: true,
      padding: true
    }
  },
  editorStyle: "wp-block-post-featured-image-editor",
  style: "wp-block-post-featured-image"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.postFeaturedImage,
  edit: _edit.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map