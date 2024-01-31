/**
 * WordPress dependencies
 */
import { postCategories as icon } from '@wordpress/icons';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/post-terms",
  title: "Post Terms",
  category: "theme",
  description: "Post terms.",
  textdomain: "default",
  attributes: {
    term: {
      type: "string"
    },
    textAlign: {
      type: "string"
    },
    separator: {
      type: "string",
      "default": ", "
    },
    prefix: {
      type: "string",
      "default": ""
    },
    suffix: {
      type: "string",
      "default": ""
    }
  },
  usesContext: ["postId", "postType"],
  supports: {
    html: false,
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true,
        link: true
      }
    },
    spacing: {
      margin: true,
      padding: true
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
    }
  },
  style: "wp-block-post-terms"
};
import edit from './edit';
import enhanceVariations from './hooks';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit
};
export const init = () => {
  addFilter('blocks.registerBlockType', 'core/template-part', enhanceVariations);
  return initBlock({
    name,
    metadata,
    settings
  });
};
//# sourceMappingURL=index.js.map