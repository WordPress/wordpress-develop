/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { cover as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import edit from './edit';
const metadata = {
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
import save from './save';
import transforms from './transforms';
import variations from './variations';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {
    attributes: {
      customOverlayColor: '#065174',
      dimRatio: 40,
      url: 'https://s.w.org/images/core/5.3/Windbuchencom.jpg'
    },
    innerBlocks: [{
      name: 'core/paragraph',
      attributes: {
        content: __('<strong>Snow Patrol</strong>'),
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
  transforms,
  save,
  edit,
  deprecated,
  variations
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map