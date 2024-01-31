"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.createUpgradedEmbedBlock = void 0;
exports.fallback = fallback;
exports.getAttributesFromPreview = exports.findMoreSuitableBlock = void 0;
exports.getClassNames = getClassNames;
exports.removeAspectRatioClasses = exports.matchesPatterns = exports.isFromWordPress = exports.hasAspectRatioClass = exports.getPhotoHtml = exports.getMergedAttributesWithPreview = exports.getEmbedInfoByProvider = void 0;
var _react = require("react");
var _dedupe = _interopRequireDefault(require("classnames/dedupe"));
var _memize = _interopRequireDefault(require("memize"));
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _blocks = require("@wordpress/blocks");
var _constants = require("./constants");
var _lockUnlock = require("../lock-unlock");
/**
 * External dependencies
 */
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/embed",
  title: "Embed",
  category: "embed",
  description: "Add a block that displays content pulled from other sites, like Twitter or YouTube.",
  textdomain: "default",
  attributes: {
    url: {
      type: "string",
      __experimentalRole: "content"
    },
    caption: {
      type: "rich-text",
      source: "rich-text",
      selector: "figcaption",
      __experimentalRole: "content"
    },
    type: {
      type: "string",
      __experimentalRole: "content"
    },
    providerNameSlug: {
      type: "string",
      __experimentalRole: "content"
    },
    allowResponsive: {
      type: "boolean",
      "default": true
    },
    responsive: {
      type: "boolean",
      "default": false,
      __experimentalRole: "content"
    },
    previewable: {
      type: "boolean",
      "default": true,
      __experimentalRole: "content"
    }
  },
  supports: {
    align: true,
    spacing: {
      margin: true
    }
  },
  editorStyle: "wp-block-embed-editor",
  style: "wp-block-embed"
};
const {
  name: DEFAULT_EMBED_BLOCK
} = metadata;

/** @typedef {import('@wordpress/blocks').WPBlockVariation} WPBlockVariation */

/**
 * Returns the embed block's information by matching the provided service provider
 *
 * @param {string} provider The embed block's provider
 * @return {WPBlockVariation} The embed block's information
 */
const getEmbedInfoByProvider = provider => (0, _blocks.getBlockVariations)(DEFAULT_EMBED_BLOCK)?.find(({
  name
}) => name === provider);

/**
 * Returns true if any of the regular expressions match the URL.
 *
 * @param {string} url      The URL to test.
 * @param {Array}  patterns The list of regular expressions to test agains.
 * @return {boolean} True if any of the regular expressions match the URL.
 */
exports.getEmbedInfoByProvider = getEmbedInfoByProvider;
const matchesPatterns = (url, patterns = []) => patterns.some(pattern => url.match(pattern));

/**
 * Finds the block variation that should be used for the URL,
 * based on the provided URL and the variation's patterns.
 *
 * @param {string} url The URL to test.
 * @return {WPBlockVariation} The block variation that should be used for this URL
 */
exports.matchesPatterns = matchesPatterns;
const findMoreSuitableBlock = url => (0, _blocks.getBlockVariations)(DEFAULT_EMBED_BLOCK)?.find(({
  patterns
}) => matchesPatterns(url, patterns));
exports.findMoreSuitableBlock = findMoreSuitableBlock;
const isFromWordPress = html => html && html.includes('class="wp-embedded-content"');
exports.isFromWordPress = isFromWordPress;
const getPhotoHtml = photo => {
  // If full image url not found use thumbnail.
  const imageUrl = photo.url || photo.thumbnail_url;

  // 100% width for the preview so it fits nicely into the document, some "thumbnails" are
  // actually the full size photo.
  const photoPreview = (0, _react.createElement)("p", null, (0, _react.createElement)("img", {
    src: imageUrl,
    alt: photo.title,
    width: "100%"
  }));
  return (0, _element.renderToString)(photoPreview);
};

/**
 * Creates a more suitable embed block based on the passed in props
 * and attributes generated from an embed block's preview.
 *
 * We require `attributesFromPreview` to be generated from the latest attributes
 * and preview, and because of the way the react lifecycle operates, we can't
 * guarantee that the attributes contained in the block's props are the latest
 * versions, so we require that these are generated separately.
 * See `getAttributesFromPreview` in the generated embed edit component.
 *
 * @param {Object} props                   The block's props.
 * @param {Object} [attributesFromPreview] Attributes generated from the block's most up to date preview.
 * @return {Object|undefined} A more suitable embed block if one exists.
 */
exports.getPhotoHtml = getPhotoHtml;
const createUpgradedEmbedBlock = (props, attributesFromPreview = {}) => {
  const {
    preview,
    attributes = {}
  } = props;
  const {
    url,
    providerNameSlug,
    type,
    ...restAttributes
  } = attributes;
  if (!url || !(0, _blocks.getBlockType)(DEFAULT_EMBED_BLOCK)) return;
  const matchedBlock = findMoreSuitableBlock(url);

  // WordPress blocks can work on multiple sites, and so don't have patterns,
  // so if we're in a WordPress block, assume the user has chosen it for a WordPress URL.
  const isCurrentBlockWP = providerNameSlug === 'wordpress' || type === _constants.WP_EMBED_TYPE;
  // If current block is not WordPress and a more suitable block found
  // that is different from the current one, create the new matched block.
  const shouldCreateNewBlock = !isCurrentBlockWP && matchedBlock && (matchedBlock.attributes.providerNameSlug !== providerNameSlug || !providerNameSlug);
  if (shouldCreateNewBlock) {
    return (0, _blocks.createBlock)(DEFAULT_EMBED_BLOCK, {
      url,
      ...restAttributes,
      ...matchedBlock.attributes
    });
  }
  const wpVariation = (0, _blocks.getBlockVariations)(DEFAULT_EMBED_BLOCK)?.find(({
    name
  }) => name === 'wordpress');

  // We can't match the URL for WordPress embeds, we have to check the HTML instead.
  if (!wpVariation || !preview || !isFromWordPress(preview.html) || isCurrentBlockWP) {
    return;
  }

  // This is not the WordPress embed block so transform it into one.
  return (0, _blocks.createBlock)(DEFAULT_EMBED_BLOCK, {
    url,
    ...wpVariation.attributes,
    // By now we have the preview, but when the new block first renders, it
    // won't have had all the attributes set, and so won't get the correct
    // type and it won't render correctly. So, we pass through the current attributes
    // here so that the initial render works when we switch to the WordPress
    // block. This only affects the WordPress block because it can't be
    // rendered in the usual Sandbox (it has a sandbox of its own) and it
    // relies on the preview to set the correct render type.
    ...attributesFromPreview
  });
};

/**
 * Determine if the block already has an aspect ratio class applied.
 *
 * @param {string} existingClassNames Existing block classes.
 * @return {boolean} True or false if the classnames contain an aspect ratio class.
 */
exports.createUpgradedEmbedBlock = createUpgradedEmbedBlock;
const hasAspectRatioClass = existingClassNames => {
  if (!existingClassNames) {
    return false;
  }
  return _constants.ASPECT_RATIOS.some(({
    className
  }) => existingClassNames.includes(className));
};

/**
 * Removes all previously set aspect ratio related classes and return the rest
 * existing class names.
 *
 * @param {string} existingClassNames Any existing class names.
 * @return {string} The class names without any aspect ratio related class.
 */
exports.hasAspectRatioClass = hasAspectRatioClass;
const removeAspectRatioClasses = existingClassNames => {
  if (!existingClassNames) {
    // Avoids extraneous work and also, by returning the same value as
    // received, ensures the post is not dirtied by a change of the block
    // attribute from `undefined` to an emtpy string.
    return existingClassNames;
  }
  const aspectRatioClassNames = _constants.ASPECT_RATIOS.reduce((accumulator, {
    className
  }) => {
    accumulator[className] = false;
    return accumulator;
  }, {
    'wp-has-aspect-ratio': false
  });
  return (0, _dedupe.default)(existingClassNames, aspectRatioClassNames);
};

/**
 * Returns class names with any relevant responsive aspect ratio names.
 *
 * @param {string}  html               The preview HTML that possibly contains an iframe with width and height set.
 * @param {string}  existingClassNames Any existing class names.
 * @param {boolean} allowResponsive    If the responsive class names should be added, or removed.
 * @return {string} Deduped class names.
 */
exports.removeAspectRatioClasses = removeAspectRatioClasses;
function getClassNames(html, existingClassNames, allowResponsive = true) {
  if (!allowResponsive) {
    return removeAspectRatioClasses(existingClassNames);
  }
  const previewDocument = document.implementation.createHTMLDocument('');
  previewDocument.body.innerHTML = html;
  const iframe = previewDocument.body.querySelector('iframe');

  // If we have a fixed aspect iframe, and it's a responsive embed block.
  if (iframe && iframe.height && iframe.width) {
    const aspectRatio = (iframe.width / iframe.height).toFixed(2);
    // Given the actual aspect ratio, find the widest ratio to support it.
    for (let ratioIndex = 0; ratioIndex < _constants.ASPECT_RATIOS.length; ratioIndex++) {
      const potentialRatio = _constants.ASPECT_RATIOS[ratioIndex];
      if (aspectRatio >= potentialRatio.ratio) {
        // Evaluate the difference between actual aspect ratio and closest match.
        // If the difference is too big, do not scale the embed according to aspect ratio.
        const ratioDiff = aspectRatio - potentialRatio.ratio;
        if (ratioDiff > 0.1) {
          // No close aspect ratio match found.
          return removeAspectRatioClasses(existingClassNames);
        }
        // Close aspect ratio match found.
        return (0, _dedupe.default)(removeAspectRatioClasses(existingClassNames), potentialRatio.className, 'wp-has-aspect-ratio');
      }
    }
  }
  return existingClassNames;
}

/**
 * Fallback behaviour for unembeddable URLs.
 * Creates a paragraph block containing a link to the URL, and calls `onReplace`.
 *
 * @param {string}   url       The URL that could not be embedded.
 * @param {Function} onReplace Function to call with the created fallback block.
 */
function fallback(url, onReplace) {
  const link = (0, _react.createElement)("a", {
    href: url
  }, url);
  onReplace((0, _blocks.createBlock)('core/paragraph', {
    content: (0, _element.renderToString)(link)
  }));
}

/***
 * Gets block attributes based on the preview and responsive state.
 *
 * @param {Object} preview The preview data.
 * @param {string} title The block's title, e.g. Twitter.
 * @param {Object} currentClassNames The block's current class names.
 * @param {boolean} isResponsive Boolean indicating if the block supports responsive content.
 * @param {boolean} allowResponsive Apply responsive classes to fixed size content.
 * @return {Object} Attributes and values.
 */
const getAttributesFromPreview = exports.getAttributesFromPreview = (0, _memize.default)((preview, title, currentClassNames, isResponsive, allowResponsive = true) => {
  if (!preview) {
    return {};
  }
  const attributes = {};
  // Some plugins only return HTML with no type info, so default this to 'rich'.
  let {
    type = 'rich'
  } = preview;
  // If we got a provider name from the API, use it for the slug, otherwise we use the title,
  // because not all embed code gives us a provider name.
  const {
    html,
    provider_name: providerName
  } = preview;
  const {
    kebabCase
  } = (0, _lockUnlock.unlock)(_components.privateApis);
  const providerNameSlug = kebabCase((providerName || title).toLowerCase());
  if (isFromWordPress(html)) {
    type = _constants.WP_EMBED_TYPE;
  }
  if (html || 'photo' === type) {
    attributes.type = type;
    attributes.providerNameSlug = providerNameSlug;
  }

  // Aspect ratio classes are removed when the embed URL is updated.
  // If the embed already has an aspect ratio class, that means the URL has not changed.
  // Which also means no need to regenerate it with getClassNames.
  if (hasAspectRatioClass(currentClassNames)) {
    return attributes;
  }
  attributes.className = getClassNames(html, currentClassNames, isResponsive && allowResponsive);
  return attributes;
});

/**
 * Returns the attributes derived from the preview, merged with the current attributes.
 *
 * @param {Object}  currentAttributes The current attributes of the block.
 * @param {Object}  preview           The preview data.
 * @param {string}  title             The block's title, e.g. Twitter.
 * @param {boolean} isResponsive      Boolean indicating if the block supports responsive content.
 * @return {Object} Merged attributes.
 */
const getMergedAttributesWithPreview = (currentAttributes, preview, title, isResponsive) => {
  const {
    allowResponsive,
    className
  } = currentAttributes;
  return {
    ...currentAttributes,
    ...getAttributesFromPreview(preview, title, className, isResponsive, allowResponsive)
  };
};
exports.getMergedAttributesWithPreview = getMergedAttributesWithPreview;
//# sourceMappingURL=util.js.map