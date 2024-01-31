/**
 * WordPress dependencies
 */
import { formatListNumbered as icon } from '@wordpress/icons';
import { registerFormatType } from '@wordpress/rich-text';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/footnotes",
  title: "Footnotes",
  category: "text",
  description: "Display footnotes added to the page.",
  keywords: ["references"],
  textdomain: "default",
  usesContext: ["postId", "postType"],
  supports: {
    __experimentalBorder: {
      radius: true,
      color: true,
      width: true,
      style: true,
      __experimentalDefaultControls: {
        radius: false,
        color: false,
        width: false,
        style: false
      }
    },
    color: {
      background: true,
      link: true,
      text: true,
      __experimentalDefaultControls: {
        link: true,
        text: true
      }
    },
    html: false,
    multiple: false,
    reusable: false,
    inserter: false,
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalTextDecoration: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalWritingMode: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    }
  },
  style: "wp-block-footnotes"
};
import { formatName, format } from './format';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit
};
registerFormatType(formatName, format);
export const init = () => {
  initBlock({
    name,
    metadata,
    settings
  });
};
//# sourceMappingURL=index.js.map