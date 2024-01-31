/**
 * WordPress dependencies
 */
import { tableOfContents as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  __experimental: true,
  name: "core/table-of-contents",
  title: "Table of Contents",
  category: "layout",
  description: "Summarize your post with a list of headings. Add HTML anchors to Heading blocks to link them here.",
  keywords: ["document outline", "summary"],
  textdomain: "default",
  attributes: {
    headings: {
      type: "array",
      items: {
        type: "object"
      },
      "default": []
    },
    onlyIncludeCurrentPage: {
      type: "boolean",
      "default": false
    }
  },
  supports: {
    html: false,
    color: {
      text: true,
      background: true,
      gradients: true,
      link: true
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
  example: {}
};
import edit from './edit';
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit,
  save
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map