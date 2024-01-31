"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blocks = require("@wordpress/blocks");
var _blockEditor = require("@wordpress/block-editor");
var _shared = require("./shared");
var _lockUnlock = require("../lock-unlock");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const {
  cleanEmptyObject
} = (0, _lockUnlock.unlock)(_blockEditor.privateApis);
const transforms = {
  from: [{
    type: 'block',
    blocks: ['core/image'],
    transform: ({
      caption,
      url,
      alt,
      align,
      id,
      anchor,
      style
    }) => (0, _blocks.createBlock)('core/cover', {
      dimRatio: 50,
      url,
      alt,
      align,
      id,
      anchor,
      style: {
        color: {
          duotone: style?.color?.duotone
        }
      }
    }, [(0, _blocks.createBlock)('core/paragraph', {
      content: caption,
      fontSize: 'large',
      align: 'center'
    })])
  }, {
    type: 'block',
    blocks: ['core/video'],
    transform: ({
      caption,
      src,
      align,
      id,
      anchor
    }) => (0, _blocks.createBlock)('core/cover', {
      dimRatio: 50,
      url: src,
      align,
      id,
      backgroundType: _shared.VIDEO_BACKGROUND_TYPE,
      anchor
    }, [(0, _blocks.createBlock)('core/paragraph', {
      content: caption,
      fontSize: 'large',
      align: 'center'
    })])
  }, {
    type: 'block',
    blocks: ['core/group'],
    transform: (attributes, innerBlocks) => {
      const {
        align,
        anchor,
        backgroundColor,
        gradient,
        style
      } = attributes;

      // If the Group block being transformed has a Cover block as its
      // only child return that Cover block.
      if (innerBlocks?.length === 1 && innerBlocks[0]?.name === 'core/cover') {
        return (0, _blocks.createBlock)('core/cover', innerBlocks[0].attributes, innerBlocks[0].innerBlocks);
      }

      // If no background or gradient color is provided, default to 50% opacity.
      // This matches the styling of a Cover block with a background image,
      // in the state where a background image has been removed.
      const dimRatio = backgroundColor || gradient || style?.color?.background || style?.color?.gradient ? undefined : 50;

      // Move the background or gradient color to the parent Cover block.
      const parentAttributes = {
        align,
        anchor,
        dimRatio,
        overlayColor: backgroundColor,
        customOverlayColor: style?.color?.background,
        gradient,
        customGradient: style?.color?.gradient
      };
      const attributesWithoutBackgroundColors = {
        ...attributes,
        backgroundColor: undefined,
        gradient: undefined,
        style: cleanEmptyObject({
          ...attributes?.style,
          color: style?.color ? {
            ...style?.color,
            background: undefined,
            gradient: undefined
          } : undefined
        })
      };

      // Preserve the block by nesting it within the Cover block,
      // instead of converting the Group block directly to the Cover block.
      return (0, _blocks.createBlock)('core/cover', parentAttributes, [(0, _blocks.createBlock)('core/group', attributesWithoutBackgroundColors, innerBlocks)]);
    }
  }],
  to: [{
    type: 'block',
    blocks: ['core/image'],
    isMatch: ({
      backgroundType,
      url,
      overlayColor,
      customOverlayColor,
      gradient,
      customGradient
    }) => {
      if (url) {
        // If a url exists the transform could happen if that URL represents an image background.
        return backgroundType === _shared.IMAGE_BACKGROUND_TYPE;
      }
      // If a url is not set the transform could happen if the cover has no background color or gradient;
      return !overlayColor && !customOverlayColor && !gradient && !customGradient;
    },
    transform: ({
      title,
      url,
      alt,
      align,
      id,
      anchor,
      style
    }) => (0, _blocks.createBlock)('core/image', {
      caption: title,
      url,
      alt,
      align,
      id,
      anchor,
      style: {
        color: {
          duotone: style?.color?.duotone
        }
      }
    })
  }, {
    type: 'block',
    blocks: ['core/video'],
    isMatch: ({
      backgroundType,
      url,
      overlayColor,
      customOverlayColor,
      gradient,
      customGradient
    }) => {
      if (url) {
        // If a url exists the transform could happen if that URL represents a video background.
        return backgroundType === _shared.VIDEO_BACKGROUND_TYPE;
      }
      // If a url is not set the transform could happen if the cover has no background color or gradient;
      return !overlayColor && !customOverlayColor && !gradient && !customGradient;
    },
    transform: ({
      title,
      url,
      align,
      id,
      anchor
    }) => (0, _blocks.createBlock)('core/video', {
      caption: title,
      src: url,
      id,
      align,
      anchor
    })
  }, {
    type: 'block',
    blocks: ['core/group'],
    isMatch: ({
      url,
      useFeaturedImage
    }) => {
      // If the Cover block uses background media, skip this transform,
      // and instead use the Group block's default transform.
      if (url || useFeaturedImage) {
        return false;
      }
      return true;
    },
    transform: (attributes, innerBlocks) => {
      // Convert Cover overlay colors to comparable Group background colors.
      const transformedColorAttributes = {
        backgroundColor: attributes?.overlayColor,
        gradient: attributes?.gradient,
        style: cleanEmptyObject({
          ...attributes?.style,
          color: attributes?.customOverlayColor || attributes?.customGradient || attributes?.style?.color ? {
            background: attributes?.customOverlayColor,
            gradient: attributes?.customGradient,
            ...attributes?.style?.color
          } : undefined
        })
      };

      // If the Cover block contains only a single Group block as a direct child,
      // then attempt to merge the Cover's background colors with the child Group block,
      // and remove the Cover block as the wrapper.
      if (innerBlocks?.length === 1 && innerBlocks[0]?.name === 'core/group') {
        const groupAttributes = cleanEmptyObject(innerBlocks[0].attributes || {});

        // If the Group block contains any kind of background color or gradient,
        // skip merging Cover background colors, and preserve the Group block's colors.
        if (groupAttributes?.backgroundColor || groupAttributes?.gradient || groupAttributes?.style?.color?.background || groupAttributes?.style?.color?.gradient) {
          return (0, _blocks.createBlock)('core/group', groupAttributes, innerBlocks[0]?.innerBlocks);
        }
        return (0, _blocks.createBlock)('core/group', {
          ...transformedColorAttributes,
          ...groupAttributes,
          style: cleanEmptyObject({
            ...groupAttributes?.style,
            color: transformedColorAttributes?.style?.color || groupAttributes?.style?.color ? {
              ...transformedColorAttributes?.style?.color,
              ...groupAttributes?.style?.color
            } : undefined
          })
        }, innerBlocks[0]?.innerBlocks);
      }

      // In all other cases, transform the Cover block directly to a Group block.
      return (0, _blocks.createBlock)('core/group', {
        ...attributes,
        ...transformedColorAttributes
      }, innerBlocks);
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map