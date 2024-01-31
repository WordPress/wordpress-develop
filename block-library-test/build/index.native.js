"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.registerCoreBlocks = exports.coreBlocks = exports.NEW_BLOCK_TYPES = void 0;
var _reactNative = require("react-native");
var _blocks = require("@wordpress/blocks");
var _hooks = require("@wordpress/hooks");
var paragraph = _interopRequireWildcard(require("./paragraph"));
var image = _interopRequireWildcard(require("./image"));
var heading = _interopRequireWildcard(require("./heading"));
var quote = _interopRequireWildcard(require("./quote"));
var gallery = _interopRequireWildcard(require("./gallery"));
var archives = _interopRequireWildcard(require("./archives"));
var audio = _interopRequireWildcard(require("./audio"));
var button = _interopRequireWildcard(require("./button"));
var calendar = _interopRequireWildcard(require("./calendar"));
var categories = _interopRequireWildcard(require("./categories"));
var code = _interopRequireWildcard(require("./code"));
var columns = _interopRequireWildcard(require("./columns"));
var column = _interopRequireWildcard(require("./column"));
var cover = _interopRequireWildcard(require("./cover"));
var embed = _interopRequireWildcard(require("./embed"));
var file = _interopRequireWildcard(require("./file"));
var html = _interopRequireWildcard(require("./html"));
var mediaText = _interopRequireWildcard(require("./media-text"));
var latestComments = _interopRequireWildcard(require("./latest-comments"));
var latestPosts = _interopRequireWildcard(require("./latest-posts"));
var list = _interopRequireWildcard(require("./list"));
var listItem = _interopRequireWildcard(require("./list-item"));
var missing = _interopRequireWildcard(require("./missing"));
var more = _interopRequireWildcard(require("./more"));
var nextpage = _interopRequireWildcard(require("./nextpage"));
var preformatted = _interopRequireWildcard(require("./preformatted"));
var pullquote = _interopRequireWildcard(require("./pullquote"));
var reusableBlock = _interopRequireWildcard(require("./block"));
var rss = _interopRequireWildcard(require("./rss"));
var search = _interopRequireWildcard(require("./search"));
var separator = _interopRequireWildcard(require("./separator"));
var shortcode = _interopRequireWildcard(require("./shortcode"));
var spacer = _interopRequireWildcard(require("./spacer"));
var table = _interopRequireWildcard(require("./table"));
var textColumns = _interopRequireWildcard(require("./text-columns"));
var verse = _interopRequireWildcard(require("./verse"));
var video = _interopRequireWildcard(require("./video"));
var tagCloud = _interopRequireWildcard(require("./tag-cloud"));
var classic = _interopRequireWildcard(require("./freeform"));
var group = _interopRequireWildcard(require("./group"));
var buttons = _interopRequireWildcard(require("./buttons"));
var socialLink = _interopRequireWildcard(require("./social-link"));
var socialLinks = _interopRequireWildcard(require("./social-links"));
var _transformationCategories = require("./utils/transformation-categories");
function _getRequireWildcardCache(e) { if ("function" != typeof WeakMap) return null; var r = new WeakMap(), t = new WeakMap(); return (_getRequireWildcardCache = function (e) { return e ? t : r; })(e); }
function _interopRequireWildcard(e, r) { if (!r && e && e.__esModule) return e; if (null === e || "object" != typeof e && "function" != typeof e) return { default: e }; var t = _getRequireWildcardCache(r); if (t && t.has(e)) return t.get(e); var n = { __proto__: null }, a = Object.defineProperty && Object.getOwnPropertyDescriptor; for (var u in e) if ("default" !== u && Object.prototype.hasOwnProperty.call(e, u)) { var i = a ? Object.getOwnPropertyDescriptor(e, u) : null; i && (i.get || i.set) ? Object.defineProperty(n, u, i) : n[u] = e[u]; } return n.default = e, t && t.set(e, n), n; }
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const coreBlocks = exports.coreBlocks = [
// Common blocks are grouped at the top to prioritize their display
// in various contexts — like the inserter and auto-complete components.
paragraph, image, heading, gallery, list, listItem, quote,
// Register all remaining core blocks.
shortcode, archives, audio, button, calendar, categories, code, columns, column, cover, embed, group, file, html, mediaText, latestComments, latestPosts, missing, more, nextpage, preformatted, pullquote, rss, search, separator, reusableBlock, spacer, table, tagCloud, textColumns, verse, video, classic, buttons, socialLink, socialLinks].reduce((accumulator, block) => {
  accumulator[block.name] = block;
  return accumulator;
}, {});

/**
 * Function to register a block variations e.g. social icons different types.
 *
 * @param {Object} block The block which variations will be registered.
 */
const registerBlockVariations = block => {
  const {
    metadata,
    settings,
    name
  } = block;
  if (!settings.variations) {
    return;
  }
  [...settings.variations].sort((a, b) => a.title.localeCompare(b.title)).forEach(v => {
    (0, _blocks.registerBlockType)(`${name}-${v.name}`, {
      ...metadata,
      name: `${name}-${v.name}`,
      ...settings,
      icon: v.icon(),
      title: v.title,
      variations: []
    });
  });
};

// Only enable code block for development
// eslint-disable-next-line no-undef
const devOnly = block => !!__DEV__ ? block : null;

// eslint-disable-next-line no-unused-vars
const iOSOnly = block => _reactNative.Platform.OS === 'ios' ? block : devOnly(block);

// Hide the Classic block and SocialLink block
(0, _hooks.addFilter)('blocks.registerBlockType', 'core/react-native-editor', (settings, name) => {
  const hiddenBlocks = ['core/freeform', 'core/social-link'];
  if (hiddenBlocks.includes(name) && (0, _blocks.hasBlockSupport)(settings, 'inserter', true)) {
    settings.supports = {
      ...settings.supports,
      inserter: false
    };
  }
  return settings;
});
(0, _hooks.addFilter)('blocks.registerBlockType', 'core/react-native-editor', (settings, name) => {
  if (!settings.transforms) {
    return settings;
  }
  if (!settings.transforms.supportedMobileTransforms) {
    return {
      ...settings,
      transforms: {
        ...settings.transforms,
        supportedMobileTransforms: (0, _transformationCategories.transformationCategory)(name)
      }
    };
  }
  return settings;
});

/**
 * Function to register core blocks provided by the block editor.
 *
 * @example
 * ```js
 * import { registerCoreBlocks } from '@wordpress/block-library';
 *
 * registerCoreBlocks();
 * ```
 */
const registerCoreBlocks = () => {
  // When adding new blocks to this list please also consider updating /src/block-support/supported-blocks.json in the Gutenberg-Mobile repo
  [paragraph, heading, devOnly(code), missing, more, image, video, nextpage, separator, list, listItem, quote, mediaText, preformatted, gallery, columns, column, group, classic, button, spacer, shortcode, buttons, latestPosts, verse, cover, socialLink, socialLinks, pullquote, file, audio, reusableBlock, search, embed].filter(Boolean).forEach(({
    init
  }) => init());
  registerBlockVariations(socialLink);
  (0, _blocks.setDefaultBlockName)(paragraph.name);
  (0, _blocks.setFreeformContentHandlerName)(classic.name);
  (0, _blocks.setUnregisteredTypeHandlerName)(missing.name);
  if (group) {
    (0, _blocks.setGroupingBlockName)(group.name);
  }
};

/**
 * Dictates which block types are considered "new." For each of the block types
 * below, if the native host app does not already have an impression count set,
 * an initial count will be set. When a block type's impression count is greater
 * than 0, a "new" badge is displayed on the block type within the block
 * inserter.
 *
 * With the below example, the Audio block will be displayed as "new" until its
 * impression count reaches 0, which occurs by various actions decrementing
 * the impression count.
 *
 * {
 * 	[ audio.name ]: 40
 * }
 *
 * @constant {{ string, number }}
 */
exports.registerCoreBlocks = registerCoreBlocks;
const NEW_BLOCK_TYPES = exports.NEW_BLOCK_TYPES = {};
//# sourceMappingURL=index.native.js.map