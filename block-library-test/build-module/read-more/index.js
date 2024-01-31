/**
 * WordPress dependencies
 */
import { link as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/read-more",
  title: "Read More",
  category: "theme",
  description: "Displays the link of a post, page, or any other content-type.",
  textdomain: "default",
  attributes: {
    content: {
      type: "string"
    },
    linkTarget: {
      type: "string",
      "default": "_self"
    }
  },
  usesContext: ["postId"],
  supports: {
    html: false,
    color: {
      gradients: true,
      text: true
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true,
      __experimentalLetterSpacing: true,
      __experimentalTextDecoration: true,
      __experimentalDefaultControls: {
        fontSize: true,
        textDecoration: true
      }
    },
    spacing: {
      margin: ["top", "bottom"],
      padding: true,
      __experimentalDefaultControls: {
        padding: true
      }
    },
    __experimentalBorder: {
      color: true,
      radius: true,
      width: true,
      __experimentalDefaultControls: {
        width: true
      }
    }
  },
  style: "wp-block-read-more"
};
import edit from './edit';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map